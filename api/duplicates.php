<?php
/**
 * PRISMA-SLR - API: Detecção e Gestão de Duplicatas
 *
 * GET    /api/duplicates.php?project_id=N          → listar pares pendentes
 * POST   /api/duplicates.php                        → detectar duplicatas (aciona algoritmo)
 * PUT    /api/duplicates.php?id=N                   → confirmar/rejeitar par
 * DELETE /api/duplicates.php?id=N                   → remover par
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

// -----------------------------------------------------------------
switch ($method) {
// -----------------------------------------------------------------

case 'GET':
    $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
    if (!$projectId) jsonResponse(['error' => true, 'message' => 'project_id obrigatório'], 422);

    $status = $_GET['status'] ?? 'pending';

    $stmt = $pdo->prepare("
        SELECT d.*,
               a1.title AS title_1, a1.year AS year_1, a1.doi AS doi_1,
               a1.journal AS journal_1, a1.source_type AS source_1,
               a2.title AS title_2, a2.year AS year_2, a2.doi AS doi_2,
               a2.journal AS journal_2, a2.source_type AS source_2
        FROM duplicates d
        JOIN articles a1 ON a1.id = d.article_id_1
        JOIN articles a2 ON a2.id = d.article_id_2
        WHERE d.project_id = ? AND d.status = ?
        ORDER BY d.match_score DESC, d.id DESC
    ");
    $stmt->execute([$projectId, $status]);

    $pairs  = $stmt->fetchAll();

    // Sumário dos estados
    $stmtSum = $pdo->prepare("
        SELECT status, COUNT(*) AS total
        FROM duplicates WHERE project_id = ?
        GROUP BY status
    ");
    $stmtSum->execute([$projectId]);
    $summary = [];
    foreach ($stmtSum->fetchAll() as $row) {
        $summary[$row['status']] = (int) $row['total'];
    }

    // Contagem de artigos únicos (não marcados como duplicata)
    $stmtUnique = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE project_id = ? AND is_duplicate = 0");
    $stmtUnique->execute([$projectId]);
    $summary['unique_count'] = (int) $stmtUnique->fetchColumn();

    jsonResponse(['pairs' => $pairs, 'summary' => $summary]);
    break;

// -----------------------------------------------------------------
case 'POST':
    $data      = getJsonBody();
    $projectId = (int) ($data['project_id'] ?? 0);
    if (!$projectId) jsonResponse(['error' => true, 'message' => 'project_id obrigatório'], 422);

    // Confirmar todos pendentes em lote (sempre mantém artigo A / article_id_1)
    if (($data['action'] ?? '') === 'confirm_all_pending') {
        $stmt = $pdo->prepare("SELECT * FROM duplicates WHERE project_id = ? AND status = 'pending' ORDER BY match_score DESC, id ASC");
        $stmt->execute([$projectId]);
        $pendingPairs = $stmt->fetchAll();

        $confirmed = 0;
        $pdo->beginTransaction();
        try {
            foreach ($pendingPairs as $pair) {
                $canonicalId = (int) $pair['article_id_1'];
                $duplicateId = (int) $pair['article_id_2'];

                // Se o artigo A já foi marcado como duplicata, inverte a ordem
                $chk = $pdo->prepare("SELECT is_duplicate FROM articles WHERE id = ?");
                $chk->execute([$canonicalId]);
                $art = $chk->fetch();
                if ($art && (int)$art['is_duplicate'] === 1) {
                    [$canonicalId, $duplicateId] = [$duplicateId, $canonicalId];
                }

                $pdo->prepare("UPDATE duplicates SET status='confirmed', canonical_id=?, reviewed_at=NOW() WHERE id=?")
                    ->execute([$canonicalId, (int)$pair['id']]);
                $pdo->prepare("UPDATE articles SET is_duplicate=1, duplicate_of=?, status='excluded' WHERE id=? AND is_duplicate=0")
                    ->execute([$canonicalId, $duplicateId]);
                $confirmed++;
            }
            $pdo->commit();
            jsonResponse(['success' => true, 'confirmed' => $confirmed]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    // Detecta duplicatas para um projeto
    // Configurações do algoritmo
    $titleThreshold = (float) ($data['title_threshold'] ?? 85); // % de similaridade mínima para título

    // Busca artigos não duplicatas
    $stmt = $pdo->prepare("
        SELECT id, title, year, doi, publisher
        FROM articles
        WHERE project_id = ? AND is_duplicate = 0
        ORDER BY id
    ");
    $stmt->execute([$projectId]);
    $articles = $stmt->fetchAll();

    $detected = 0;
    $skipped  = 0;
    $n        = count($articles);

    // Remove pares antigos pendentes para reprocessar
    $pdo->prepare("DELETE FROM duplicates WHERE project_id = ? AND status = 'pending'")->execute([$projectId]);

    $stmtInsert = $pdo->prepare("
        INSERT IGNORE INTO duplicates
            (project_id, article_id_1, article_id_2, match_score, match_type, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");

    // Algoritmo O(n²) com early-exit por DOI
    $byDoi = [];
    foreach ($articles as $art) {
        if (!empty($art['doi'])) {
            $doi = normalizeDoi($art['doi']);
            if ($doi) {
                $byDoi[$doi][] = $art['id'];
            }
        }
    }

    // 1. Match exato por DOI
    foreach ($byDoi as $doi => $ids) {
        if (count($ids) < 2) continue;
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $stmtInsert->execute([$projectId, $ids[$i], $ids[$j], 100.0, 'doi']);
                $detected++;
            }
        }
    }

    // 2. Match por título similar
    // Processamos em blocos para evitar timeout
    $titleNormMap = [];
    foreach ($articles as $art) {
        $titleNormMap[$art['id']] = normalizeTitle($art['title'] ?? '');
    }

    $processedDoi = []; // pares já detectados por DOI
    foreach ($byDoi as $doi => $ids) {
        foreach ($ids as $id1) {
            foreach ($ids as $id2) {
                if ($id1 < $id2) $processedDoi[$id1 . '_' . $id2] = true;
            }
        }
    }

    for ($i = 0; $i < $n; $i++) {
        $art1   = $articles[$i];
        $title1 = $titleNormMap[$art1['id']];
        if (empty($title1)) continue;

        for ($j = $i + 1; $j < $n; $j++) {
            $art2 = $articles[$j];

            // Pula se já detectado por DOI
            if (isset($processedDoi[$art1['id'] . '_' . $art2['id']])) continue;

            $title2 = $titleNormMap[$art2['id']];
            if (empty($title2)) continue;

            // Otimização: pula se anos muito diferentes (> 1 ano)
            if ($art1['year'] && $art2['year'] && abs($art1['year'] - $art2['year']) > 1) {
                $skipped++;
                continue;
            }

            // Calcula similaridade de título
            $sim = titleSimilarity($title1, $title2);

            if ($sim >= $titleThreshold) {
                $type = $sim >= 95 ? 'title' : 'combined';
                $stmtInsert->execute([$projectId, $art1['id'], $art2['id'], round($sim, 2), $type]);
                $detected++;
            }
        }
    }

    jsonResponse([
        'success'          => true,
        'message'          => "Detecção concluída",
        'articles_checked' => $n,
        'pairs_detected'   => $detected,
        'pairs_skipped'    => $skipped,
        'threshold_used'   => $titleThreshold,
    ]);
    break;

// -----------------------------------------------------------------
case 'PUT':
    // Confirmar (canonical) ou rejeitar par de duplicatas
    if (!$id) jsonResponse(['error' => true, 'message' => 'ID obrigatório'], 422);

    $data   = getJsonBody();
    $action = $data['action'] ?? ''; // 'confirm' ou 'reject'

    if (!in_array($action, ['confirm', 'reject'])) {
        jsonResponse(['error' => true, 'message' => "action deve ser 'confirm' ou 'reject'"], 422);
    }

    // Busca o par
    $stmt = $pdo->prepare("SELECT * FROM duplicates WHERE id = ?");
    $stmt->execute([$id]);
    $pair = $stmt->fetch();
    if (!$pair) jsonResponse(['error' => true, 'message' => 'Par não encontrado'], 404);

    if ($action === 'confirm') {
        $canonicalId = (int) ($data['canonical_id'] ?? $pair['article_id_1']);
        $duplicateId = ($canonicalId === (int) $pair['article_id_1'])
                     ? (int) $pair['article_id_2']
                     : (int) $pair['article_id_1'];

        $pdo->beginTransaction();
        try {
            // Atualiza o par
            $pdo->prepare("
                UPDATE duplicates
                SET status = 'confirmed', canonical_id = ?, reviewed_at = NOW()
                WHERE id = ?
            ")->execute([$canonicalId, $id]);

            // Marca o artigo duplicado
            $pdo->prepare("
                UPDATE articles SET is_duplicate = 1, duplicate_of = ?, status = 'excluded'
                WHERE id = ?
            ")->execute([$canonicalId, $duplicateId]);

            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Duplicata confirmada', 'canonical_id' => $canonicalId, 'duplicate_id' => $duplicateId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }

    } else {
        // Rejeitar
        $pdo->prepare("
            UPDATE duplicates SET status = 'rejected', reviewed_at = NOW() WHERE id = ?
        ")->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Par rejeitado como duplicata']);
    }
    break;

// -----------------------------------------------------------------
case 'DELETE':
    if ($id) {
        // Remove par específico
        $pdo->prepare("DELETE FROM duplicates WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true]);
    } else {
        // Reset completo: remove todos os pares e desfaz marcações de duplicata
        $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
        if (!$projectId) jsonResponse(['error' => true, 'message' => 'id ou project_id obrigatório'], 422);

        $pdo->beginTransaction();
        try {
            // Reverte artigos marcados como duplicata para pending (somente os desta detecção)
            $pdo->prepare("
                UPDATE articles SET is_duplicate=0, duplicate_of=NULL, status='pending'
                WHERE project_id=? AND is_duplicate=1
            ")->execute([$projectId]);
            // Remove todos os pares do projeto
            $pdo->prepare("DELETE FROM duplicates WHERE project_id=?")->execute([$projectId]);
            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Detecção redefinida com sucesso']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }
    break;

// -----------------------------------------------------------------
default:
    jsonResponse(['error' => true, 'message' => 'Método não suportado'], 405);
}

// -----------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------

function normalizeDoi(string $doi): string
{
    $doi = strtolower(trim($doi));
    $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi);
    return trim($doi);
}

function normalizeTitle(string $title): string
{
    $title = mb_strtolower($title);
    $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title);
    $title = preg_replace('/\s+/', ' ', $title);
    return trim($title);
}

/**
 * Similaridade entre dois títulos normalizados (0–100)
 * Usa similar_text para eficiência + Levenshtein para curtos
 */
function titleSimilarity(string $a, string $b): float
{
    if ($a === $b) return 100.0;
    if (empty($a) || empty($b)) return 0.0;

    // similar_text é eficiente para strings longas
    similar_text($a, $b, $percent);
    return $percent;
}
