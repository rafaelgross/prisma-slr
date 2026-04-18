<?php
/**
 * PRISMA-SLR - API: Exportação
 * GET /api/export.php?project_id=N&format=FORMAT&scope=SCOPE
 *
 * Formatos: csv, bibtex, json
 * Scope: all, included, screened
 */

require_once __DIR__ . '/../config/database.php';

$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
$format    = strtolower($_GET['format'] ?? 'csv');
$scope     = $_GET['scope'] ?? 'included';

if (!$projectId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'project_id obrigatório']);
    exit;
}

$pdo = getDB();

// Projeto
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) {
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Projeto não encontrado']);
    exit;
}

// Escopo
$where = "a.project_id = ?";
if ($scope === 'included')      $where .= " AND a.status = 'included'";
elseif ($scope === 'screened')  $where .= " AND a.status IN ('eligible','included') AND a.is_duplicate = 0";
elseif ($scope === 'all')       $where .= " AND a.is_duplicate = 0";

// Busca artigos
$stmt = $pdo->prepare("
    SELECT a.*,
           ss.name AS source_name
    FROM articles a
    LEFT JOIN search_sources ss ON ss.id = a.source_id
    WHERE $where
    ORDER BY a.year DESC, a.title
");
$stmt->execute([$projectId]);
$articles = $stmt->fetchAll();

// Para cada artigo, busca autores e keywords
$ids = array_column($articles, 'id');
$authorMap  = [];
$keywordMap = [];

if (!empty($ids)) {
    $ph = implode(',', array_fill(0, count($ids), '?'));

    $stmtAut = $pdo->prepare("
        SELECT aa.article_id, GROUP_CONCAT(au.full_name ORDER BY aa.position SEPARATOR ' and ') AS authors
        FROM article_authors aa JOIN authors au ON au.id = aa.author_id
        WHERE aa.article_id IN ($ph) GROUP BY aa.article_id
    ");
    $stmtAut->execute($ids);
    foreach ($stmtAut->fetchAll() as $row) {
        $authorMap[$row['article_id']] = $row['authors'];
    }

    $stmtKw = $pdo->prepare("
        SELECT article_id, GROUP_CONCAT(keyword ORDER BY keyword SEPARATOR '; ') AS keywords
        FROM article_keywords WHERE keyword_type = 'author' AND article_id IN ($ph)
        GROUP BY article_id
    ");
    $stmtKw->execute($ids);
    foreach ($stmtKw->fetchAll() as $row) {
        $keywordMap[$row['article_id']] = $row['keywords'];
    }
}

// Adiciona autores/keywords nos artigos
foreach ($articles as &$art) {
    $art['authors_str']  = $authorMap[$art['id']]  ?? '';
    $art['keywords_str'] = $keywordMap[$art['id']] ?? '';
}

$filename = 'PRISMA-SLR_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['title']) . '_' . $scope;

// -----------------------------------------------------------------
// CSV
// -----------------------------------------------------------------
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

    // Cabeçalho
    fputcsv($out, [
        'ID','Título','Autores','Ano','Periódico','Volume','Número','Páginas',
        'DOI','ISSN','Tipo','Idioma','Editora','Citações','Open Access',
        'Base','Status','Palavras-chave','Resumo'
    ]);

    foreach ($articles as $art) {
        fputcsv($out, [
            $art['id'],
            $art['title'],
            $art['authors_str'],
            $art['year'],
            $art['journal'],
            $art['volume'],
            $art['issue'],
            $art['pages'],
            $art['doi'],
            $art['issn'],
            $art['document_type'],
            $art['language'],
            $art['publisher'],
            $art['cited_by'],
            $art['open_access'] ? 'Sim' : 'Não',
            $art['source_name'],
            $art['status'],
            $art['keywords_str'],
            $art['abstract'],
        ]);
    }

    fclose($out);
    exit;
}

// -----------------------------------------------------------------
// BibTeX
// -----------------------------------------------------------------
if ($format === 'bibtex') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.bib"');
    header('Cache-Control: no-cache');

    $typeMap = [
        'Article'        => 'article',
        'Review'         => 'article',
        'Conference Paper' => 'inproceedings',
        'Book'           => 'book',
        'Book Chapter'   => 'incollection',
        'Thesis'         => 'phdthesis',
        'Report'         => 'techreport',
    ];

    foreach ($articles as $art) {
        $type = $typeMap[$art['document_type'] ?? ''] ?? 'misc';

        // Gera chave BibTeX
        $firstAuthor = '';
        if (!empty($art['authors_str'])) {
            $parts = explode(' and ', $art['authors_str']);
            $nameParts = explode(' ', trim($parts[0]));
            $firstAuthor = end($nameParts);
        }
        $key = preg_replace('/[^a-zA-Z0-9]/', '', $firstAuthor) . ($art['year'] ?? '');
        if (empty($key)) $key = 'ref' . $art['id'];

        echo "@{$type}{{$key},\n";
        $printField = function (string $name, ?string $value) {
            if (!empty($value)) {
                echo "  {$name} = {{$value}},\n";
            }
        };

        $printField('author',  $art['authors_str']);
        $printField('title',   $art['title']);
        $printField('year',    $art['year']);
        $printField('journal', $art['journal']  ?? $art['journal']);
        $printField('volume',  $art['volume']);
        $printField('number',  $art['issue']);
        $printField('pages',   $art['pages']);
        $printField('doi',     $art['doi']);
        $printField('issn',    $art['issn']);
        $printField('publisher', $art['publisher']);
        $printField('keywords',  $art['keywords_str']);
        $printField('abstract',  $art['abstract']);
        echo "}\n\n";
    }
    exit;
}

// -----------------------------------------------------------------
// JSON
// -----------------------------------------------------------------
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');

    echo json_encode([
        'project' => $project['title'],
        'exported_at' => date('Y-m-d H:i:s'),
        'scope'   => $scope,
        'total'   => count($articles),
        'articles' => array_map(fn($a) => [
            'id'           => $a['id'],
            'title'        => $a['title'],
            'authors'      => $a['authors_str'],
            'year'         => $a['year'],
            'journal'      => $a['journal'],
            'volume'       => $a['volume'],
            'issue'        => $a['issue'],
            'pages'        => $a['pages'],
            'doi'          => $a['doi'],
            'issn'         => $a['issn'],
            'document_type'=> $a['document_type'],
            'language'     => $a['language'],
            'publisher'    => $a['publisher'],
            'cited_by'     => $a['cited_by'],
            'open_access'  => (bool) $a['open_access'],
            'source'       => $a['source_name'],
            'status'       => $a['status'],
            'keywords'     => $a['keywords_str'],
            'abstract'     => $a['abstract'],
        ], $articles),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['error' => true, 'message' => "Formato '{$format}' não suportado. Use: csv, bibtex, json"]);
