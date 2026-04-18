<?php
/**
 * PRISMA-SLR - API: Triagem e Elegibilidade
 *
 * GET  /api/screening.php?project_id=N&phase=screening  → artigos para triar
 * POST /api/screening.php                                → registrar decisão
 * GET  /api/screening.php?project_id=N&summary=1        → resumo das fases
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Migração: garante coluna rating na tabela screening_decisions
try {
    $pdo->exec("ALTER TABLE screening_decisions ADD COLUMN rating TINYINT UNSIGNED NULL DEFAULT NULL");
} catch (Exception $e) { /* coluna já existe */ }

// -----------------------------------------------------------------
switch ($method) {
// -----------------------------------------------------------------

case 'GET':
    $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
    if (!$projectId) jsonResponse(['error' => true, 'message' => 'project_id obrigatório'], 422);

    // Modo sumário: retorna contagens de cada fase
    if (!empty($_GET['summary'])) {
        jsonResponse(getScreeningSummary($pdo, $projectId));
        break;
    }

    $phase          = $_GET['phase']           ?? 'screening'; // 'screening' ou 'eligibility'
    $pending        = !empty($_GET['pending_only']);            // apenas sem decisão
    $decisionFilter = $_GET['decision_filter'] ?? '';          // 'include', 'exclude' ou ''

    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(5, (int) ($_GET['per_page'] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    if ($phase === 'screening') {
        // Triagem: artigos que não são duplicatas
        $baseWhere = "a.project_id = ? AND a.is_duplicate = 0";
        $params    = [$projectId];

        if ($pending) {
            $baseWhere .= " AND NOT EXISTS (SELECT 1 FROM screening_decisions sd WHERE sd.article_id = a.id AND sd.phase = 'screening')";
        } elseif ($decisionFilter === 'include') {
            $baseWhere .= " AND EXISTS (SELECT 1 FROM screening_decisions sd WHERE sd.article_id = a.id AND sd.phase = 'screening' AND sd.decision = 'include')";
        } elseif ($decisionFilter === 'exclude') {
            $baseWhere .= " AND EXISTS (SELECT 1 FROM screening_decisions sd WHERE sd.article_id = a.id AND sd.phase = 'screening' AND sd.decision = 'exclude')";
        }
    } else {
        // Elegibilidade: artigos que passaram na triagem (incluídos nela)
        $baseWhere = "a.project_id = ? AND a.is_duplicate = 0
                      AND EXISTS (SELECT 1 FROM screening_decisions sd WHERE sd.article_id = a.id
                                  AND sd.phase = 'screening' AND sd.decision = 'include')";
        $params    = [$projectId];

        if ($pending) {
            $baseWhere .= " AND NOT EXISTS (SELECT 1 FROM screening_decisions sd2 WHERE sd2.article_id = a.id AND sd2.phase = 'eligibility')";
        } elseif ($decisionFilter === 'include') {
            $baseWhere .= " AND EXISTS (SELECT 1 FROM screening_decisions sd2 WHERE sd2.article_id = a.id AND sd2.phase = 'eligibility' AND sd2.decision = 'include')";
        } elseif ($decisionFilter === 'exclude') {
            $baseWhere .= " AND EXISTS (SELECT 1 FROM screening_decisions sd2 WHERE sd2.article_id = a.id AND sd2.phase = 'eligibility' AND sd2.decision = 'exclude')";
        }
    }

    // Filtro de busca
    if (!empty($_GET['search'])) {
        $s = '%' . $_GET['search'] . '%';
        $baseWhere .= " AND (a.title LIKE ? OR a.abstract LIKE ?)";
        $params[] = $s;
        $params[] = $s;
    }

    // Contagem total
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM articles a WHERE $baseWhere");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    // Artigos
    $stmtArt = $pdo->prepare("
        SELECT a.id, a.title, a.abstract, a.year, a.journal, a.doi,
               a.source_type, a.document_type, a.url,
               sd.decision AS current_decision,
               sd.reason_id, er.reason AS exclusion_reason,
               sd.notes, sd.rating
        FROM articles a
        LEFT JOIN screening_decisions sd ON sd.article_id = a.id AND sd.phase = ?
        LEFT JOIN exclusion_reasons er ON er.id = sd.reason_id
        WHERE $baseWhere
        ORDER BY a.id ASC
        LIMIT ? OFFSET ?
    ");
    $stmtArt->execute(array_merge([$phase], $params, [$perPage, $offset]));
    $articles = $stmtArt->fetchAll();

    // Autores para cada artigo
    if (!empty($articles)) {
        $ids = array_column($articles, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmtAut = $pdo->prepare("
            SELECT aa.article_id, GROUP_CONCAT(au.full_name ORDER BY aa.position SEPARATOR '; ') AS authors
            FROM article_authors aa JOIN authors au ON au.id = aa.author_id
            WHERE aa.article_id IN ($ph)
            GROUP BY aa.article_id
        ");
        $stmtAut->execute($ids);
        $authorMap = array_column($stmtAut->fetchAll(), 'authors', 'article_id');
        foreach ($articles as &$art) {
            $art['authors'] = $authorMap[$art['id']] ?? '';
        }
    }

    // Motivos de exclusão do projeto
    $stmtReasons = $pdo->prepare("
        SELECT id, reason, description
        FROM exclusion_reasons
        WHERE project_id = ? AND phase = ?
        ORDER BY sort_order
    ");
    $stmtReasons->execute([$projectId, $phase]);

    jsonResponse([
        'articles'         => $articles,
        'exclusion_reasons'=> $stmtReasons->fetchAll(),
        'total'            => $total,
        'page'             => $page,
        'per_page'         => $perPage,
        'last_page'        => (int) ceil($total / $perPage),
        'phase'            => $phase,
    ]);
    break;

// -----------------------------------------------------------------
case 'POST':
    $data      = getJsonBody();
    $articleId = (int) ($data['article_id'] ?? 0);
    $phase     = $data['phase']    ?? '';
    $decision  = $data['decision'] ?? '';

    if (!$articleId || !$phase || !$decision) {
        jsonResponse(['error' => true, 'message' => 'article_id, phase e decision são obrigatórios'], 422);
    }

    if (!in_array($phase,    ['screening', 'eligibility'])) {
        jsonResponse(['error' => true, 'message' => 'phase inválido'], 422);
    }
    if (!in_array($decision, ['include', 'exclude', 'uncertain'])) {
        jsonResponse(['error' => true, 'message' => 'decision inválido'], 422);
    }

    $reasonId = !empty($data['reason_id']) ? (int) $data['reason_id'] : null;
    $notes    = $data['notes'] ?? null;
    $rating   = isset($data['rating']) && $data['rating'] >= 1 && $data['rating'] <= 5
                ? (int) $data['rating'] : null;

    // Upsert da decisão
    $pdo->prepare("
        INSERT INTO screening_decisions (article_id, phase, decision, reason_id, notes, rating)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            decision   = VALUES(decision),
            reason_id  = VALUES(reason_id),
            notes      = VALUES(notes),
            rating     = VALUES(rating),
            decided_at = NOW()
    ")->execute([$articleId, $phase, $decision, $reasonId, $notes, $rating]);

    // Atualiza status do artigo
    $newStatus = computeArticleStatus($pdo, $articleId);
    $pdo->prepare("UPDATE articles SET status = ? WHERE id = ?")->execute([$newStatus, $articleId]);

    jsonResponse(['success' => true, 'article_id' => $articleId, 'new_status' => $newStatus]);
    break;

// -----------------------------------------------------------------
default:
    jsonResponse(['error' => true, 'message' => 'Método não suportado'], 405);
}

// -----------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------

/**
 * Calcula o status do artigo com base nas decisões registradas
 */
function computeArticleStatus(PDO $pdo, int $articleId): string
{
    $stmt = $pdo->prepare("
        SELECT phase, decision FROM screening_decisions WHERE article_id = ?
    ");
    $stmt->execute([$articleId]);
    $decisions = [];
    foreach ($stmt->fetchAll() as $row) {
        $decisions[$row['phase']] = $row['decision'];
    }

    // Excluído em alguma fase
    if (($decisions['screening']   ?? '') === 'exclude') return 'excluded';
    if (($decisions['eligibility'] ?? '') === 'exclude') return 'excluded';

    // Incluído na elegibilidade
    if (($decisions['eligibility'] ?? '') === 'include') return 'included';

    // Passou pela triagem
    if (($decisions['screening'] ?? '') === 'include') return 'eligible';

    // Passou pela triagem com incerteza
    if (($decisions['screening'] ?? '') === 'uncertain') return 'screened';

    return 'screened';
}

/**
 * Resumo de contagens por fase
 */
function getScreeningSummary(PDO $pdo, int $projectId): array
{
    // Total não duplicados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE project_id = ? AND is_duplicate = 0");
    $stmt->execute([$projectId]);
    $totalNonDup = (int) $stmt->fetchColumn();

    // Triagem: sem decisão
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM articles a
        WHERE a.project_id = ? AND a.is_duplicate = 0
        AND NOT EXISTS (SELECT 1 FROM screening_decisions sd WHERE sd.article_id = a.id AND sd.phase = 'screening')
    ");
    $stmt->execute([$projectId]);
    $pendingScreening = (int) $stmt->fetchColumn();

    // Triagem: incluídos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM screening_decisions sd
        JOIN articles a ON a.id = sd.article_id
        WHERE a.project_id = ? AND sd.phase = 'screening' AND sd.decision = 'include'
    ");
    $stmt->execute([$projectId]);
    $includedScreening = (int) $stmt->fetchColumn();

    // Triagem: excluídos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM screening_decisions sd
        JOIN articles a ON a.id = sd.article_id
        WHERE a.project_id = ? AND sd.phase = 'screening' AND sd.decision = 'exclude'
    ");
    $stmt->execute([$projectId]);
    $excludedScreening = (int) $stmt->fetchColumn();

    // Elegibilidade: sem decisão (passaram na triagem)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM articles a
        WHERE a.project_id = ? AND a.is_duplicate = 0
        AND EXISTS (SELECT 1 FROM screening_decisions sd WHERE sd.article_id = a.id AND sd.phase = 'screening' AND sd.decision = 'include')
        AND NOT EXISTS (SELECT 1 FROM screening_decisions sd2 WHERE sd2.article_id = a.id AND sd2.phase = 'eligibility')
    ");
    $stmt->execute([$projectId]);
    $pendingEligibility = (int) $stmt->fetchColumn();

    // Elegibilidade: incluídos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM articles a
        WHERE a.project_id = ? AND a.status = 'included'
    ");
    $stmt->execute([$projectId]);
    $included = (int) $stmt->fetchColumn();

    // Elegibilidade: excluídos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM screening_decisions sd
        JOIN articles a ON a.id = sd.article_id
        WHERE a.project_id = ? AND sd.phase = 'eligibility' AND sd.decision = 'exclude'
    ");
    $stmt->execute([$projectId]);
    $excludedEligibility = (int) $stmt->fetchColumn();

    // Exclusões por motivo (elegibilidade)
    $stmt = $pdo->prepare("
        SELECT er.reason, COUNT(*) AS total
        FROM screening_decisions sd
        JOIN articles a ON a.id = sd.article_id
        JOIN exclusion_reasons er ON er.id = sd.reason_id
        WHERE a.project_id = ? AND sd.phase = 'eligibility' AND sd.decision = 'exclude'
        GROUP BY er.id, er.reason
        ORDER BY total DESC
    ");
    $stmt->execute([$projectId]);
    $exclusionsByReason = $stmt->fetchAll();

    return [
        'total_non_duplicates'  => $totalNonDup,
        'screening' => [
            'pending'  => $pendingScreening,
            'included' => $includedScreening,
            'excluded' => $excludedScreening,
            'total'    => $pendingScreening + $includedScreening + $excludedScreening,
        ],
        'eligibility' => [
            'pending'            => $pendingEligibility,
            'included'           => $included,
            'excluded'           => $excludedEligibility,
            'total'              => $pendingEligibility + $included + $excludedEligibility,
            'exclusions_by_reason' => $exclusionsByReason,
        ],
        'final_included' => $included,
    ];
}
