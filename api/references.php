<?php
/**
 * PRISMA-SLR — api/references.php
 * Gera referências formatadas em ABNT NBR 6023:2018 ou APA 7ª ed.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$projectId = (int)($_GET['project_id'] ?? 0);
$format    = strtolower(trim($_GET['format'] ?? 'abnt'));  // abnt | apa
$scope     = $_GET['scope'] ?? 'included';                // included | all
$idsRaw    = trim($_GET['ids'] ?? '');

if (!$projectId) {
    echo json_encode(['error' => true, 'message' => 'project_id obrigatório']);
    exit;
}

try {
    $db = getDB();

    // ── Busca artigos ────────────────────────────────────────────
    $statusFilter = ($scope === 'all') ? "a.is_duplicate = 0" : "a.status = 'included'";

    if ($idsRaw !== '') {
        $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
        if (empty($ids)) { echo json_encode(['references' => [], 'count' => 0]); exit; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$projectId], $ids);
        $sql = "SELECT a.id, a.title, a.year, a.journal, a.volume, a.issue,
                       a.pages, a.article_number, a.doi, a.publisher,
                       a.document_type, a.source_type
                FROM articles a
                WHERE a.project_id = ? AND $statusFilter AND a.id IN ($ph)
                ORDER BY a.year DESC, a.title ASC";
    } else {
        $params = [$projectId];
        $sql = "SELECT a.id, a.title, a.year, a.journal, a.volume, a.issue,
                       a.pages, a.article_number, a.doi, a.publisher,
                       a.document_type, a.source_type
                FROM articles a
                WHERE a.project_id = ? AND $statusFilter
                ORDER BY a.year DESC, a.title ASC";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($articles)) {
        echo json_encode(['references' => [], 'count' => 0, 'format' => $format]);
        exit;
    }

    // ── Busca autores para todos os artigos de uma vez ───────────
    $artIds = array_column($articles, 'id');
    $ph2    = implode(',', array_fill(0, count($artIds), '?'));
    $authSql = "SELECT aa.article_id, au.full_name, aa.position
                FROM article_authors aa
                JOIN authors au ON au.id = aa.author_id
                WHERE aa.article_id IN ($ph2)
                ORDER BY aa.article_id, aa.position";
    $authStmt = $db->prepare($authSql);
    $authStmt->execute($artIds);
    $authRows = $authStmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupa autores por article_id (parseia full_name em last/first)
    $authorsByArticle = [];
    foreach ($authRows as $row) {
        $authorsByArticle[$row['article_id']][] = parseFullName($row['full_name'] ?? '');
    }

    // ── Gera referências ─────────────────────────────────────────
    $references = [];
    foreach ($articles as $art) {
        $authors = $authorsByArticle[$art['id']] ?? [];
        $ref = ($format === 'apa')
             ? formatAPA($art, $authors)
             : formatABNT($art, $authors);
        $references[] = [
            'id'        => (int)$art['id'],
            'title'     => $art['title'],
            'year'      => (int)$art['year'],
            'reference' => $ref,
        ];
    }

    // Ordem alfabética pelo texto da referência (sobrenome do 1º autor)
    usort($references, fn($a, $b) => mb_strtolower($a['reference']) <=> mb_strtolower($b['reference']));

    echo json_encode([
        'references' => $references,
        'count'      => count($references),
        'format'     => $format,
    ]);

} catch (Throwable $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════════════════
//  ABNT NBR 6023:2018
// ═══════════════════════════════════════════════════════════════════
function formatABNT(array $art, array $authors): string
{
    $authorStr = abntAuthors($authors);
    $title     = mb_strtoupper(trim($art['title'] ?? ''));
    $journal   = trim($art['journal'] ?? '');
    $year      = $art['year'] ?? '';
    $vol       = $art['volume'] ?? '';
    $num       = $art['issue'] ?? '';
    $pgs       = normPages($art['pages'] ?? $art['article_number'] ?? '');
    $doi       = normDoi($art['doi'] ?? '');
    $publisher = trim($art['publisher'] ?? '');

    $ref = $authorStr . '. ' . $title . '.';

    if ($journal) {
        $ref .= ' ' . $journal;
        if ($vol)  $ref .= ', v. ' . $vol;
        if ($num)  $ref .= ', n. ' . $num;
        if ($pgs)  $ref .= ', p. ' . $pgs;
        if ($year) $ref .= ', ' . $year;
        $ref .= '.';
    } elseif ($publisher) {
        $ref .= ' ' . $publisher . ($year ? ', ' . $year : '') . '.';
    } elseif ($year) {
        $ref .= ' ' . $year . '.';
    }

    if ($doi) $ref .= ' Disponível em: https://doi.org/' . $doi . '. Acesso em: ' . date('d') . ' ' . monthPT(date('n')) . '. ' . date('Y') . '.';

    return $ref;
}

function abntAuthors(array $authors): string
{
    if (empty($authors)) return '[s.n.]';

    // ABNT: lista até 3; se mais de 3, primeiro + et al.
    $list  = array_slice($authors, 0, 3);
    $parts = [];
    foreach ($list as $a) {
        $last  = mb_strtoupper(trim($a['last']));
        $first = trim($a['first'] ?? '');
        if ($first) {
            $words = preg_split('/[\s\-]+/', $first, -1, PREG_SPLIT_NO_EMPTY);
            $abbr  = array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)) . '.', $words);
            $first = implode(' ', $abbr);
        }
        $parts[] = $first ? "$last, $first" : $last;
    }

    $str = implode('; ', $parts);
    if (count($authors) > 3) $str .= ' et al.';
    return $str;
}

// ═══════════════════════════════════════════════════════════════════
//  APA 7ª edição
// ═══════════════════════════════════════════════════════════════════
function formatAPA(array $art, array $authors): string
{
    $authorStr = apaAuthors($authors);
    $year      = $art['year'] ? '(' . $art['year'] . ')' : '(s.d.)';
    $title     = sentenceCase(trim($art['title'] ?? ''));
    $journal   = trim($art['journal'] ?? '');
    $vol       = $art['volume'] ?? '';
    $num       = $art['issue'] ?? '';
    $pgs       = normPages($art['pages'] ?? $art['article_number'] ?? '');
    $doi       = normDoi($art['doi'] ?? '');
    $publisher = trim($art['publisher'] ?? '');

    $ref = $authorStr . ' ' . $year . ' ' . $title . '.';

    if ($journal) {
        $ref .= ' ' . $journal;
        if ($vol) {
            $ref .= ', ' . $vol;
            if ($num) $ref .= '(' . $num . ')';
        }
        if ($pgs) $ref .= ', ' . $pgs;
        $ref .= '.';
    } elseif ($publisher) {
        $ref .= ' ' . $publisher . '.';
    }

    if ($doi) $ref .= ' https://doi.org/' . $doi;

    return $ref;
}

function apaAuthors(array $authors): string
{
    if (empty($authors)) return 'Autor desconhecido.';

    $total = count($authors);
    $limit = ($total > 20) ? 19 : $total;
    $parts = [];

    for ($i = 0; $i < $limit; $i++) {
        $a     = $authors[$i];
        $last  = trim($a['last']);
        $first = trim($a['first'] ?? '');
        if ($first) {
            $words = preg_split('/[\s\-]+/', $first, -1, PREG_SPLIT_NO_EMPTY);
            $abbr  = array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)) . '.', $words);
            $first = implode(' ', $abbr);
        }
        $parts[] = $first ? "$last, $first" : $last;
    }

    if ($total > 20) {
        // 19 autores + ... + último
        $last_a = $authors[$total - 1];
        $l = trim($last_a['last']);
        $f = trim($last_a['first'] ?? '');
        if ($f) {
            $words = preg_split('/[\s\-]+/', $f, -1, PREG_SPLIT_NO_EMPTY);
            $f = implode(' ', array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)) . '.', $words));
        }
        $str = implode(', ', $parts) . ', . . . ' . ($f ? "$l, $f" : $l) . '.';
    } elseif ($total === 1) {
        $str = $parts[0] . '.';
    } else {
        $last = array_pop($parts);
        $str  = implode(', ', $parts) . ', & ' . $last . '.';
    }

    return $str;
}

// ═══════════════════════════════════════════════════════════════════
//  Parser de nome completo → {last, first}
// ═══════════════════════════════════════════════════════════════════
function parseFullName(string $fullName): array
{
    $name = trim($fullName);
    if (!$name) return ['last' => '', 'first' => ''];

    // Formato "Sobrenome, Nome" ou "Sobrenome, N."  (mais comum em bases bibliográficas)
    if (strpos($name, ',') !== false) {
        [$last, $first] = explode(',', $name, 2);
        return ['last' => trim($last), 'first' => trim($first)];
    }

    // Formato "Nome Sobrenome" — última palavra é o sobrenome
    $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) === 1) return ['last' => $parts[0], 'first' => ''];
    $last  = array_pop($parts);
    $first = implode(' ', $parts);
    return ['last' => $last, 'first' => $first];
}

// ═══════════════════════════════════════════════════════════════════
//  Helpers
// ═══════════════════════════════════════════════════════════════════
function normDoi(string $doi): string
{
    $doi = trim($doi);
    foreach (['https://doi.org/', 'http://doi.org/', 'http://dx.doi.org/', 'https://dx.doi.org/', 'doi:'] as $prefix) {
        if (stripos($doi, $prefix) === 0) { $doi = substr($doi, strlen($prefix)); break; }
    }
    return $doi;
}

function normPages(string $p): string
{
    return trim(str_replace('--', '-', $p));
}

function sentenceCase(string $s): string
{
    if (!$s) return $s;
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_strtolower(mb_substr($s, 1));
}

function monthPT(int $m): string
{
    return ['jan', 'fev', 'mar', 'abr', 'maio', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'][$m - 1] ?? '';
}
