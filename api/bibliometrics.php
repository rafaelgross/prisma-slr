<?php
/**
 * PRISMA-SLR - API: Bibliometria
 * GET /api/bibliometrics.php?project_id=N&chart=TIPO
 *
 * Tipos disponíveis:
 *   publications_year, document_types, top_journals, top_authors,
 *   top_countries, keywords_frequency, citations_distribution,
 *   open_access, top_publishers, keywords_by_year
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$pdo       = getDB();
$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
$chart     = $_GET['chart'] ?? 'all';
$scope     = $_GET['scope']  ?? 'all'; // 'all' ou 'included'

if (!$projectId) jsonResponse(['error' => true, 'message' => 'project_id obrigatório'], 422);

// Filtro de escopo
$scopeWhere = $scope === 'included' ? "AND a.status = 'included'" : "AND a.is_duplicate = 0";

$result = [];

if ($chart === 'all' || $chart === 'publications_year') {
    $stmt = $pdo->prepare("
        SELECT year, COUNT(*) AS total
        FROM articles a
        WHERE project_id = ? $scopeWhere AND year IS NOT NULL AND year > 1900 AND year <= YEAR(NOW())
        GROUP BY year ORDER BY year
    ");
    $stmt->execute([$projectId]);
    $result['publications_year'] = $stmt->fetchAll();
}

if ($chart === 'all' || $chart === 'document_types') {
    $stmt = $pdo->prepare("
        SELECT COALESCE(document_type, 'Não especificado') AS type, COUNT(*) AS total
        FROM articles a
        WHERE project_id = ? $scopeWhere
        GROUP BY document_type ORDER BY total DESC
    ");
    $stmt->execute([$projectId]);
    $result['document_types'] = $stmt->fetchAll();
}

if ($chart === 'all' || $chart === 'top_journals') {
    $limit = (int) ($_GET['limit'] ?? 15);
    $stmt  = $pdo->prepare("
        SELECT COALESCE(journal, 'Sem periódico') AS journal, COUNT(*) AS total
        FROM articles a
        WHERE project_id = ? $scopeWhere AND journal IS NOT NULL AND journal != ''
        GROUP BY journal ORDER BY total DESC LIMIT ?
    ");
    $stmt->execute([$projectId, $limit]);
    $result['top_journals'] = $stmt->fetchAll();
}

if ($chart === 'all' || $chart === 'top_authors') {
    $limit = (int) ($_GET['limit'] ?? 15);
    $stmt  = $pdo->prepare("
        SELECT au.full_name AS author, COUNT(DISTINCT aa.article_id) AS total
        FROM article_authors aa
        JOIN authors au ON au.id = aa.author_id
        JOIN articles a ON a.id = aa.article_id
        WHERE a.project_id = ? $scopeWhere
        GROUP BY au.id ORDER BY total DESC LIMIT ?
    ");
    $stmt->execute([$projectId, $limit]);
    $result['top_authors'] = $stmt->fetchAll();
}

if ($chart === 'all' || $chart === 'top_countries') {
    $limit = (int) ($_GET['limit'] ?? 15);
    $stmt  = $pdo->prepare("
        SELECT country, COUNT(DISTINCT af.article_id) AS total
        FROM article_affiliations af
        JOIN articles a ON a.id = af.article_id
        WHERE a.project_id = ? $scopeWhere AND country IS NOT NULL AND country != ''
        GROUP BY country ORDER BY total DESC LIMIT ?
    ");
    $stmt->execute([$projectId, $limit]);
    $result['top_countries'] = $stmt->fetchAll();
}

if ($chart === 'all' || $chart === 'keywords_frequency') {
    $limit    = (int)    ($_GET['limit']   ?? 30);
    $kwType   = $_GET['kw_type'] ?? 'author'; // 'author', 'plus', 'all'
    $kwWhere  = $kwType !== 'all' ? "AND ak.keyword_type = '$kwType'" : '';
    $stmt     = $pdo->prepare("
        SELECT ak.keyword, COUNT(*) AS total
        FROM article_keywords ak
        JOIN articles a ON a.id = ak.article_id
        WHERE a.project_id = ? $scopeWhere $kwWhere
        GROUP BY ak.keyword ORDER BY total DESC LIMIT ?
    ");
    $stmt->execute([$projectId, $limit]);
    $result['keywords_frequency'] = $stmt->fetchAll();
}

if ($chart === 'all' || $chart === 'citations_distribution') {
    $stmt = $pdo->prepare("
        SELECT cited_by, COUNT(*) AS total
        FROM articles a
        WHERE project_id = ? $scopeWhere AND cited_by IS NOT NULL
        GROUP BY cited_by ORDER BY cited_by
    ");
    $stmt->execute([$projectId]);
    $raw = $stmt->fetchAll();
    // Agrupa em faixas
    $buckets = ['0' => 0, '1-5' => 0, '6-10' => 0, '11-25' => 0, '26-50' => 0, '51-100' => 0, '100+' => 0];
    foreach ($raw as $row) {
        $c = (int) $row['cited_by'];
        if ($c === 0)       $buckets['0']      += $row['total'];
        elseif ($c <= 5)    $buckets['1-5']    += $row['total'];
        elseif ($c <= 10)   $buckets['6-10']   += $row['total'];
        elseif ($c <= 25)   $buckets['11-25']  += $row['total'];
        elseif ($c <= 50)   $buckets['26-50']  += $row['total'];
        elseif ($c <= 100)  $buckets['51-100'] += $row['total'];
        else                $buckets['100+']   += $row['total'];
    }
    $result['citations_distribution'] = array_map(
        fn($k, $v) => ['range' => $k, 'total' => $v],
        array_keys($buckets), $buckets
    );
}

if ($chart === 'all' || $chart === 'open_access') {
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN open_access = 1 THEN 1 ELSE 0 END) AS open_access,
            SUM(CASE WHEN open_access = 0 THEN 1 ELSE 0 END) AS closed_access
        FROM articles a
        WHERE project_id = ? $scopeWhere
    ");
    $stmt->execute([$projectId]);
    $result['open_access'] = $stmt->fetch();
}

if ($chart === 'all' || $chart === 'top_publishers') {
    $limit = (int) ($_GET['limit'] ?? 10);
    $stmt  = $pdo->prepare("
        SELECT COALESCE(publisher, 'Não informado') AS publisher, COUNT(*) AS total
        FROM articles a
        WHERE project_id = ? $scopeWhere AND publisher IS NOT NULL AND publisher != ''
        GROUP BY publisher ORDER BY total DESC LIMIT ?
    ");
    $stmt->execute([$projectId, $limit]);
    $result['top_publishers'] = $stmt->fetchAll();
}

if ($chart === 'all' || $chart === 'keywords_by_year') {
    // Top 5 keywords por ano (para gráfico de área empilhada)
    $limit = (int) ($_GET['limit'] ?? 8);

    // Primeiro, pega as top keywords globais
    $stmtTop = $pdo->prepare("
        SELECT ak.keyword
        FROM article_keywords ak
        JOIN articles a ON a.id = ak.article_id
        WHERE a.project_id = ? $scopeWhere AND ak.keyword_type = 'author'
        GROUP BY ak.keyword ORDER BY COUNT(*) DESC LIMIT ?
    ");
    $stmtTop->execute([$projectId, $limit]);
    $topKw = array_column($stmtTop->fetchAll(), 'keyword');

    if (!empty($topKw)) {
        $ph    = implode(',', array_fill(0, count($topKw), '?'));
        $params = [$projectId, ...$topKw];
        $stmt  = $pdo->prepare("
            SELECT a.year, ak.keyword, COUNT(*) AS total
            FROM article_keywords ak
            JOIN articles a ON a.id = ak.article_id
            WHERE a.project_id = ? $scopeWhere AND ak.keyword IN ($ph)
                  AND a.year IS NOT NULL AND a.year > 1990
            GROUP BY a.year, ak.keyword ORDER BY a.year, ak.keyword
        ");
        $stmt->execute($params);
        $result['keywords_by_year'] = [
            'top_keywords' => $topKw,
            'data'         => $stmt->fetchAll(),
        ];
    } else {
        $result['keywords_by_year'] = ['top_keywords' => [], 'data' => []];
    }
}

if ($chart === 'all' || $chart === 'source_types') {
    $stmt = $pdo->prepare("
        SELECT UPPER(COALESCE(source_type,'outros')) AS source_type, COUNT(*) AS total
        FROM articles a
        WHERE project_id = ? $scopeWhere
        GROUP BY source_type ORDER BY total DESC
    ");
    $stmt->execute([$projectId]);
    $result['source_types'] = $stmt->fetchAll();
}

if ($chart === 'all' || $chart === 'top_cited') {
    $limit = (int) ($_GET['limit'] ?? 10);
    $stmt  = $pdo->prepare("
        SELECT a.id, a.title, a.year, a.journal, a.cited_by, a.doi
        FROM articles a
        WHERE a.project_id = ? $scopeWhere AND a.cited_by IS NOT NULL AND a.cited_by > 0
        ORDER BY a.cited_by DESC LIMIT ?
    ");
    $stmt->execute([$projectId, $limit]);
    $top = $stmt->fetchAll();
    // Autores de cada artigo
    if (!empty($top)) {
        $ids = array_column($top, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmtA = $pdo->prepare("
            SELECT aa.article_id, GROUP_CONCAT(au.full_name ORDER BY aa.position SEPARATOR '; ') AS authors
            FROM article_authors aa JOIN authors au ON au.id = aa.author_id
            WHERE aa.article_id IN ($ph) GROUP BY aa.article_id
        ");
        $stmtA->execute($ids);
        $authMap = array_column($stmtA->fetchAll(), 'authors', 'article_id');
        foreach ($top as &$t) { $t['authors'] = $authMap[$t['id']] ?? ''; }
    }
    $result['top_cited'] = $top;
}

if ($chart === 'all' || $chart === 'ratings_distribution') {
    $stmt = $pdo->prepare("
        SELECT sd.rating, COUNT(*) AS total
        FROM screening_decisions sd
        JOIN articles a ON a.id = sd.article_id
        WHERE a.project_id = ? AND sd.phase = 'screening'
              AND sd.decision = 'include' AND sd.rating IS NOT NULL AND sd.rating BETWEEN 1 AND 5
        GROUP BY sd.rating ORDER BY sd.rating
    ");
    $stmt->execute([$projectId]);
    $result['ratings_distribution'] = $stmt->fetchAll();
}

// Rede de co-autoria
if ($chart === 'coauthorship') {
    $minArticles = (int) ($_GET['min_articles'] ?? 2);

    // Autores com pelo menos min_articles
    $stmt = $pdo->prepare("
        SELECT au.id, au.full_name, COUNT(DISTINCT aa.article_id) AS article_count
        FROM authors au
        JOIN article_authors aa ON aa.author_id = au.id
        JOIN articles a ON a.id = aa.article_id
        WHERE a.project_id = ? $scopeWhere
        GROUP BY au.id HAVING article_count >= ?
        ORDER BY article_count DESC
        LIMIT 100
    ");
    $stmt->execute([$projectId, $minArticles]);
    $nodes = $stmt->fetchAll();
    $nodeIds = array_column($nodes, 'id');

    $edges = [];
    if (count($nodeIds) >= 2) {
        $ph = implode(',', array_fill(0, count($nodeIds), '?'));
        $stmt = $pdo->prepare("
            SELECT aa1.author_id AS source, aa2.author_id AS target, COUNT(*) AS weight
            FROM article_authors aa1
            JOIN article_authors aa2 ON aa1.article_id = aa2.article_id AND aa1.author_id < aa2.author_id
            JOIN articles a ON a.id = aa1.article_id
            WHERE a.project_id = ? $scopeWhere
                  AND aa1.author_id IN ($ph) AND aa2.author_id IN ($ph)
            GROUP BY aa1.author_id, aa2.author_id
            HAVING weight >= 1
            LIMIT 500
        ");
        $stmt->execute([$projectId, ...$nodeIds, ...$nodeIds]);
        $edges = $stmt->fetchAll();
    }

    jsonResponse(['nodes' => $nodes, 'edges' => $edges]);
    return;
}

// Rede de co-ocorrência de palavras-chave
if ($chart === 'keyword_cooccurrence') {
    $minOccurrences = (int) ($_GET['min_occ'] ?? 2);
    $limit = (int) ($_GET['limit'] ?? 50);

    $stmt = $pdo->prepare("
        SELECT ak.keyword, COUNT(DISTINCT ak.article_id) AS freq
        FROM article_keywords ak
        JOIN articles a ON a.id = ak.article_id
        WHERE a.project_id = ? $scopeWhere AND ak.keyword_type = 'author'
        GROUP BY ak.keyword HAVING freq >= ?
        ORDER BY freq DESC LIMIT ?
    ");
    $stmt->execute([$projectId, $minOccurrences, $limit]);
    $nodes = $stmt->fetchAll();
    $topKw = array_column($nodes, 'keyword');

    $edges = [];
    if (count($topKw) >= 2) {
        $ph = implode(',', array_fill(0, count($topKw), '?'));
        $stmt = $pdo->prepare("
            SELECT LEAST(ak1.keyword, ak2.keyword) AS source,
                   GREATEST(ak1.keyword, ak2.keyword) AS target,
                   COUNT(*) AS weight
            FROM article_keywords ak1
            JOIN article_keywords ak2 ON ak1.article_id = ak2.article_id
                AND ak1.keyword < ak2.keyword
            JOIN articles a ON a.id = ak1.article_id
            WHERE a.project_id = ? $scopeWhere
                  AND ak1.keyword IN ($ph) AND ak2.keyword IN ($ph)
                  AND ak1.keyword_type = 'author' AND ak2.keyword_type = 'author'
            GROUP BY source, target
            HAVING weight >= 1
            ORDER BY weight DESC
            LIMIT 300
        ");
        $stmt->execute([$projectId, ...$topKw, ...$topKw]);
        $edges = $stmt->fetchAll();
    }

    jsonResponse(['nodes' => $nodes, 'edges' => $edges]);
    return;
}

jsonResponse($result);
