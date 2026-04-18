<?php
/**
 * PRISMA-SLR - API: Artigos
 * GET  /api/articles.php?project_id=N  → lista com filtros
 * GET  /api/articles.php?id=N          → artigo completo
 * PUT  /api/articles.php?id=N          → atualizar status/campos
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

// -----------------------------------------------------------------
switch ($method) {
// -----------------------------------------------------------------

case 'GET':
    if ($id) {
        // Artigo completo com autores, palavras-chave e afiliações
        $stmt = $pdo->prepare("SELECT a.* FROM articles a WHERE a.id = ?");
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if (!$article) jsonResponse(['error' => true, 'message' => 'Artigo não encontrado'], 404);

        // Autores
        $stmt2 = $pdo->prepare("
            SELECT au.full_name, aa.position
            FROM article_authors aa
            JOIN authors au ON au.id = aa.author_id
            WHERE aa.article_id = ?
            ORDER BY aa.position
        ");
        $stmt2->execute([$id]);
        $article['authors'] = $stmt2->fetchAll();

        // Palavras-chave
        $stmt3 = $pdo->prepare("SELECT keyword, keyword_type FROM article_keywords WHERE article_id = ? ORDER BY keyword_type, keyword");
        $stmt3->execute([$id]);
        $article['keywords'] = $stmt3->fetchAll();

        // Afiliações
        $stmt4 = $pdo->prepare("SELECT institution, country FROM article_affiliations WHERE article_id = ?");
        $stmt4->execute([$id]);
        $article['affiliations'] = $stmt4->fetchAll();

        // Decisões de triagem
        $stmt5 = $pdo->prepare("
            SELECT sd.*, er.reason AS exclusion_reason_text
            FROM screening_decisions sd
            LEFT JOIN exclusion_reasons er ON er.id = sd.reason_id
            WHERE sd.article_id = ?
        ");
        $stmt5->execute([$id]);
        $article['screening_decisions'] = $stmt5->fetchAll();

        jsonResponse($article);

    } else {
        // Lista com filtros e paginação
        $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
        if (!$projectId) jsonResponse(['error' => true, 'message' => 'project_id obrigatório'], 422);

        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset   = ($page - 1) * $perPage;

        // Filtros
        $where  = ['a.project_id = ?'];
        $params = [$projectId];

        if (!empty($_GET['status'])) {
            $where[] = 'a.status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['source_type'])) {
            $where[] = 'a.source_type = ?';
            $params[] = $_GET['source_type'];
        }
        if (!empty($_GET['year'])) {
            $where[] = 'a.year = ?';
            $params[] = (int) $_GET['year'];
        }
        if (!empty($_GET['document_type'])) {
            $where[] = 'a.document_type = ?';
            $params[] = $_GET['document_type'];
        }
        if (isset($_GET['is_duplicate'])) {
            $where[] = 'a.is_duplicate = ?';
            $params[] = (int) $_GET['is_duplicate'];
        }
        if (!empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $where[] = '(a.title LIKE ? OR a.abstract LIKE ? OR a.doi LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = implode(' AND ', $where);

        // ── Modo counts_only (para export.php) ────────────────────
        if (!empty($_GET['counts_only'])) {
            $cnt = function(string $extra, array $p = []) use ($pdo, $projectId): int {
                $s = $pdo->prepare("SELECT COUNT(*) FROM articles a WHERE a.project_id = ? AND a.is_duplicate = 0 AND $extra");
                $s->execute(array_merge([$projectId], $p));
                return (int)$s->fetchColumn();
            };
            jsonResponse(['counts' => [
                'all'      => $cnt("1=1"),
                'screened' => $cnt("a.status IN ('eligible','included')"),
                'included' => $cnt("a.status = 'included'"),
                'excluded' => $cnt("a.status = 'excluded'"),
            ]]);
        }

        // Total
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM articles a WHERE $whereClause");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // Ordenação — aceita sort=year_desc, year_asc, title, cited_by
        $sortParam = $_GET['sort'] ?? 'imported_at_desc';
        $sortMap = [
            'year_desc'  => ['a.year',        'DESC'],
            'year_asc'   => ['a.year',         'ASC'],
            'year'       => ['a.year',        'DESC'],
            'title'      => ['a.title',        'ASC'],
            'cited_by'   => ['a.cited_by',    'DESC'],
            'journal'    => ['a.journal',      'ASC'],
            'status'     => ['a.status',       'ASC'],
        ];
        [$orderField, $orderDir] = $sortMap[$sortParam] ?? ['a.imported_at', 'DESC'];

        // Artigos
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare("
            SELECT a.id, a.title, a.year, a.journal, a.document_type,
                   a.doi, a.url, a.cited_by, a.source_type, a.status,
                   a.is_duplicate, a.open_access, a.language, a.publisher,
                   a.issn, a.volume, a.issue, a.pages,
                   ss.name AS source_name
            FROM articles a
            LEFT JOIN search_sources ss ON ss.id = a.source_id
            WHERE $whereClause
            ORDER BY $orderField $orderDir
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $articles = $stmt->fetchAll();

        // Autores resumidos para cada artigo
        if (!empty($articles)) {
            $ids          = array_column($articles, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtAut      = $pdo->prepare("
                SELECT aa.article_id, GROUP_CONCAT(au.full_name ORDER BY aa.position SEPARATOR '; ') AS authors
                FROM article_authors aa
                JOIN authors au ON au.id = aa.author_id
                WHERE aa.article_id IN ($placeholders)
                GROUP BY aa.article_id
            ");
            $stmtAut->execute($ids);
            $authorMap = [];
            foreach ($stmtAut->fetchAll() as $row) {
                $authorMap[$row['article_id']] = $row['authors'];
            }
            foreach ($articles as &$art) {
                $art['authors'] = $authorMap[$art['id']] ?? '';
            }
        }

        // Metadados extras para página de incluídos
        $stmtMeta = $pdo->prepare("
            SELECT
              COUNT(DISTINCT a.journal) AS unique_journals,
              COALESCE(SUM(a.cited_by), 0) AS total_citations,
              MIN(a.year) AS year_min,
              MAX(a.year) AS year_max
            FROM articles a
            WHERE $whereClause
        ");
        $stmtMeta->execute(array_slice($params, 0, count($params) - 2));
        $meta = $stmtMeta->fetch();

        // Anos disponíveis para filtro
        $stmtYears = $pdo->prepare("SELECT DISTINCT year FROM articles a WHERE $whereClause AND year IS NOT NULL ORDER BY year DESC");
        $stmtYears->execute(array_slice($params, 0, count($params) - 2));
        $years = array_column($stmtYears->fetchAll(), 'year');

        jsonResponse([
            'articles'        => $articles,
            'total'           => $total,
            'page'            => $page,
            'per_page'        => $perPage,
            'last_page'       => (int) ceil($total / $perPage),
            'unique_journals' => (int)($meta['unique_journals'] ?? 0),
            'total_citations' => (int)($meta['total_citations'] ?? 0),
            'year_range'      => $meta['year_min'] ? [$meta['year_min'], $meta['year_max']] : null,
            'years'           => $years,
        ]);
    }
    break;

// -----------------------------------------------------------------
case 'PUT':
    if (!$id) jsonResponse(['error' => true, 'message' => 'ID obrigatório'], 422);

    $data  = getJsonBody();
    $allowed = ['status','is_duplicate','duplicate_of'];

    $sets   = [];
    $values = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $sets[]   = "$field = ?";
            $values[] = $data[$field];
        }
    }

    if (empty($sets)) jsonResponse(['error' => true, 'message' => 'Nada para atualizar'], 422);

    $values[] = $id;
    $pdo->prepare("UPDATE articles SET " . implode(', ', $sets) . " WHERE id = ?")->execute($values);

    jsonResponse(['success' => true, 'message' => 'Artigo atualizado']);
    break;

// -----------------------------------------------------------------
default:
    jsonResponse(['error' => true, 'message' => 'Método não suportado'], 405);
}
