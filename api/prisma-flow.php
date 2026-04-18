<?php
/**
 * PRISMA-SLR - API: Dados do Diagrama PRISMA 2020
 * GET /api/prisma-flow.php?project_id=N
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$pdo       = getDB();
$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;

if (!$projectId) jsonResponse(['error' => true, 'message' => 'project_id obrigatório'], 422);

// Verifica projeto
$stmt = $pdo->prepare("SELECT id, title FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) jsonResponse(['error' => true, 'message' => 'Projeto não encontrado'], 404);

// -----------------------------------------------------------------
// Contagens por fonte de busca
// -----------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT ss.name, ss.source_type, ss.total_imported,
           COUNT(a.id) AS actual_count
    FROM search_sources ss
    LEFT JOIN articles a ON a.source_id = ss.id
    WHERE ss.project_id = ?
    GROUP BY ss.id
    ORDER BY ss.source_type, ss.name
");
$stmt->execute([$projectId]);
$sources = $stmt->fetchAll();

// Total identificado
$totalIdentified = 0;
$byDatabase = [];
foreach ($sources as $src) {
    $count = (int) $src['actual_count'];
    $totalIdentified += $count;
    $byDatabase[] = [
        'name'  => $src['name'],
        'type'  => $src['source_type'],
        'count' => $count,
    ];
}

// -----------------------------------------------------------------
// Duplicatas removidas (confirmadas)
// -----------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT article_id_2) AS total
    FROM duplicates
    WHERE project_id = ? AND status = 'confirmed'
");
$stmt->execute([$projectId]);
$duplicatesRemoved = (int) $stmt->fetchColumn();

// Artigos únicos (após remoção de duplicatas)
$uniqueAfterDedup = $totalIdentified - $duplicatesRemoved;

// -----------------------------------------------------------------
// Fase de triagem (screening)
// -----------------------------------------------------------------
// Total triado = artigos com decisão de screening
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT a.id) AS total
    FROM articles a
    JOIN screening_decisions sd ON sd.article_id = a.id AND sd.phase = 'screening'
    WHERE a.project_id = ? AND a.is_duplicate = 0
");
$stmt->execute([$projectId]);
$screened = (int) $stmt->fetchColumn();

// Excluídos na triagem
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM screening_decisions sd
    JOIN articles a ON a.id = sd.article_id
    WHERE a.project_id = ? AND sd.phase = 'screening' AND sd.decision = 'exclude'
");
$stmt->execute([$projectId]);
$excludedScreening = (int) $stmt->fetchColumn();

// Incluídos na triagem (passaram para elegibilidade)
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM screening_decisions sd
    JOIN articles a ON a.id = sd.article_id
    WHERE a.project_id = ? AND sd.phase = 'screening' AND sd.decision = 'include'
");
$stmt->execute([$projectId]);
$includedScreening = (int) $stmt->fetchColumn();

// Motivos de exclusão na triagem
$stmt = $pdo->prepare("
    SELECT er.reason, COUNT(*) AS total
    FROM screening_decisions sd
    JOIN articles a ON a.id = sd.article_id
    LEFT JOIN exclusion_reasons er ON er.id = sd.reason_id
    WHERE a.project_id = ? AND sd.phase = 'screening' AND sd.decision = 'exclude'
    GROUP BY er.id, er.reason
    ORDER BY total DESC
");
$stmt->execute([$projectId]);
$screeningReasons = $stmt->fetchAll();

// -----------------------------------------------------------------
// Fase de elegibilidade (full text)
// -----------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT a.id) AS total
    FROM articles a
    JOIN screening_decisions sd ON sd.article_id = a.id AND sd.phase = 'eligibility'
    WHERE a.project_id = ? AND a.is_duplicate = 0
");
$stmt->execute([$projectId]);
$assessedEligibility = (int) $stmt->fetchColumn();

// Excluídos na elegibilidade
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM screening_decisions sd
    JOIN articles a ON a.id = sd.article_id
    WHERE a.project_id = ? AND sd.phase = 'eligibility' AND sd.decision = 'exclude'
");
$stmt->execute([$projectId]);
$excludedEligibility = (int) $stmt->fetchColumn();

// Motivos de exclusão na elegibilidade
$stmt = $pdo->prepare("
    SELECT er.reason, COUNT(*) AS total
    FROM screening_decisions sd
    JOIN articles a ON a.id = sd.article_id
    LEFT JOIN exclusion_reasons er ON er.id = sd.reason_id
    WHERE a.project_id = ? AND sd.phase = 'eligibility' AND sd.decision = 'exclude'
    GROUP BY er.id, er.reason
    ORDER BY total DESC
");
$stmt->execute([$projectId]);
$eligibilityReasons = $stmt->fetchAll();

// -----------------------------------------------------------------
// Incluídos na revisão
// -----------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM articles WHERE project_id = ? AND status = 'included'
");
$stmt->execute([$projectId]);
$totalIncluded = (int) $stmt->fetchColumn();

// -----------------------------------------------------------------
// Resposta final
// -----------------------------------------------------------------
jsonResponse([
    'project_id'    => $projectId,
    'project_title' => $project['title'],
    'prisma' => [
        'identification' => [
            'total_identified'    => $totalIdentified,
            'by_database'         => $byDatabase,
            'duplicates_removed'  => $duplicatesRemoved,
            'unique_after_dedup'  => $uniqueAfterDedup,
        ],
        'screening' => [
            'records_screened'     => $screened,
            'records_excluded'     => $excludedScreening,
            'records_included'     => $includedScreening,
            'exclusion_reasons'    => $screeningReasons,
        ],
        'eligibility' => [
            'assessed_full_text'   => $assessedEligibility,
            'excluded_full_text'   => $excludedEligibility,
            'exclusion_reasons'    => $eligibilityReasons,
        ],
        'included' => [
            'studies_included'     => $totalIncluded,
        ],
    ],
]);
