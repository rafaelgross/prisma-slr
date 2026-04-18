<?php
/**
 * PRISMA-SLR - API: Importação de arquivos .bib
 * POST /api/import.php
 *   - Multipart/form-data: file (arquivo .bib), project_id, source_name, source_type, search_string, search_date
 *
 * GET /api/import.php?project_id=N  → listar fontes do projeto
 * DELETE /api/import.php?source_id=N → excluir fonte e seus artigos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/BibTexParser.php';
require_once __DIR__ . '/../lib/RisParser.php';
require_once __DIR__ . '/../lib/PubMedXmlParser.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// -----------------------------------------------------------------
switch ($method) {
// -----------------------------------------------------------------

case 'GET':
    $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : null;
    if (!$projectId) jsonResponse(['error' => true, 'message' => 'project_id obrigatório'], 422);

    $stmt = $pdo->prepare("
        SELECT ss.*,
               COUNT(a.id) AS article_count
        FROM search_sources ss
        LEFT JOIN articles a ON a.source_id = ss.id
        WHERE ss.project_id = ?
        GROUP BY ss.id
        ORDER BY ss.imported_at DESC
    ");
    $stmt->execute([$projectId]);
    jsonResponse($stmt->fetchAll());
    break;

// -----------------------------------------------------------------
case 'DELETE':
    $sourceId = isset($_GET['source_id']) ? (int) $_GET['source_id'] : null;
    if (!$sourceId) jsonResponse(['error' => true, 'message' => 'source_id obrigatório'], 422);

    // Excluir artigos vinculados e depois a fonte
    $pdo->prepare("DELETE FROM articles WHERE source_id = ?")->execute([$sourceId]);
    $pdo->prepare("DELETE FROM search_sources WHERE id = ?")->execute([$sourceId]);

    jsonResponse(['success' => true, 'message' => 'Fonte e artigos excluídos']);
    break;

// -----------------------------------------------------------------
case 'POST':
    // Validação básica
    $projectId  = isset($_POST['project_id'])  ? (int) $_POST['project_id']  : 0;
    $sourceName = trim($_POST['source_name']   ?? 'Desconhecida');
    $sourceType = trim($_POST['source_type']   ?? 'other');
    $searchStr  = trim($_POST['search_string'] ?? '');
    $searchDate = trim($_POST['search_date']   ?? '') ?: null;

    if (!$projectId) jsonResponse(['error' => true, 'message' => 'project_id obrigatório'], 422);

    // Verifica projeto
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => true, 'message' => 'Projeto não encontrado'], 404);
    }

    // Verifica upload
    if (empty($_FILES['file'])) {
        jsonResponse(['error' => true, 'message' => 'Arquivo .bib não enviado'], 422);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => true, 'message' => 'Erro no upload: código ' . $file['error']], 422);
    }

    // Valida extensão
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['bib', 'txt', 'ris', 'xml'];
    if (!in_array($ext, $allowedExts)) {
        jsonResponse(['error' => true, 'message' => 'Extensão não suportada. Use .bib, .ris, .xml ou .txt'], 422);
    }

    // Limita tamanho: 50 MB
    if ($file['size'] > 50 * 1024 * 1024) {
        jsonResponse(['error' => true, 'message' => 'Arquivo muito grande (máx. 50 MB)'], 422);
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        jsonResponse(['error' => true, 'message' => 'Não foi possível ler o arquivo'], 500);
    }

    // Seleciona parser conforme extensão (auto-detect para .txt)
    if ($ext === 'ris') {
        $parser = new RisParser();
    } elseif ($ext === 'xml') {
        $parser = new PubMedXmlParser();
        $sourceType = 'pubmed'; // XML é sempre PubMed
    } elseif ($ext === 'txt') {
        // Auto-detect: RIS começa com "TY  -", BibTeX começa com "@"
        $preview = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $content));
        if (preg_match('/^TY\s*[-–]/m', $preview)) {
            $parser = new RisParser();
        } else {
            $parser = new BibTexParser();
        }
    } else {
        $parser = new BibTexParser();
    }

    $result   = $parser->parse($content, $sourceType);
    $entries  = $result['entries'];
    $parseLog = $result['log'];

    if (empty($entries)) {
        jsonResponse([
            'error'   => true,
            'message' => 'Nenhuma entrada válida encontrada no arquivo',
            'log'     => $parseLog,
        ], 422);
    }

    // Cria registro de fonte
    $pdo->beginTransaction();
    try {
        $stmtSrc = $pdo->prepare("
            INSERT INTO search_sources
                (project_id, name, source_type, search_string, search_date, file_name, total_imported)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtSrc->execute([
            $projectId, $sourceName, $sourceType,
            $searchStr ?: null, $searchDate,
            $file['name'], 0
        ]);
        $sourceId = (int) $pdo->lastInsertId();

        // ----- Insere cada artigo -----
        $stmtArt = $pdo->prepare("
            INSERT INTO articles
                (project_id, source_id, source_type, source_key,
                 title, abstract, year, document_type, language,
                 journal, volume, issue, pages, article_number,
                 issn, eissn, isbn, doi, url, eid, wos_id, pubmed_id,
                 publisher, cited_by, open_access, raw_bibtex)
            VALUES
                (?, ?, ?, ?,
                 ?, ?, ?, ?, ?,
                 ?, ?, ?, ?, ?,
                 ?, ?, ?, ?, ?, ?, ?, ?,
                 ?, ?, ?, ?)
        ");

        $stmtAuthor    = $pdo->prepare("INSERT IGNORE INTO authors (full_name, normalized_name) VALUES (?, ?)");
        $stmtGetAuthor = $pdo->prepare("SELECT id FROM authors WHERE normalized_name = ?");
        $stmtArtAut    = $pdo->prepare("INSERT IGNORE INTO article_authors (article_id, author_id, position) VALUES (?, ?, ?)");
        $stmtKw        = $pdo->prepare("INSERT INTO article_keywords (article_id, keyword, keyword_type) VALUES (?, ?, ?)");
        $stmtAff       = $pdo->prepare("INSERT INTO article_affiliations (article_id, institution, country) VALUES (?, ?, ?)");

        $importedCount = 0;
        $errors        = [];

        foreach ($entries as $entry) {
            if (empty($entry['title'])) {
                $errors[] = ['key' => $entry['source_key'] ?? 'desconhecido', 'reason' => 'Título ausente'];
                continue;
            }

            $stmtArt->execute([
                $projectId, $sourceId,
                $entry['source_type'] ?? $sourceType,
                $entry['source_key']  ?? null,
                $entry['title'],
                $entry['abstract']       ?? null,
                intOrNull($entry['year'] ?? null),
                $entry['document_type']  ?? null,
                $entry['language']       ?? 'English',
                $entry['journal']        ?? null,
                $entry['volume']         ?? null,
                $entry['issue']          ?? null,
                $entry['pages']          ?? null,
                $entry['article_number'] ?? null,
                $entry['issn']           ?? null,
                $entry['eissn']          ?? null,
                $entry['isbn']           ?? null,
                $entry['doi']            ?? null,
                $entry['url']            ?? null,
                $entry['eid']            ?? null,
                $entry['wos_id']         ?? null,
                $entry['pubmed_id']      ?? null,
                $entry['publisher']      ?? null,
                intOrNull($entry['cited_by']  ?? null) ?? 0,
                intOrNull($entry['open_access'] ?? null) ?? 0,
                $entry['raw_bibtex']     ?? null,
            ]);
            $articleId = (int) $pdo->lastInsertId();
            $importedCount++;

            // Autores
            foreach (($entry['authors'] ?? []) as $pos => $authorName) {
                $normalized = normalizeAuthorName($authorName);
                $stmtAuthor->execute([$authorName, $normalized]);
                $stmtGetAuthor->execute([$normalized]);
                $authorRow = $stmtGetAuthor->fetch();
                if ($authorRow) {
                    $stmtArtAut->execute([$articleId, $authorRow['id'], $pos + 1]);
                }
            }

            // Palavras-chave do autor
            foreach (($entry['keywords_author'] ?? []) as $kw) {
                if (!empty($kw)) $stmtKw->execute([$articleId, $kw, 'author']);
            }
            // Keywords-Plus (WoS)
            foreach (($entry['keywords_plus'] ?? []) as $kw) {
                if (!empty($kw)) $stmtKw->execute([$articleId, $kw, 'plus']);
            }

            // Afiliações
            foreach (($entry['affiliations'] ?? []) as $aff) {
                $stmtAff->execute([$articleId, $aff['institution'] ?? '', $aff['country'] ?? '']);
            }
        }

        // Atualiza total importado na fonte
        $pdo->prepare("UPDATE search_sources SET total_imported = ? WHERE id = ?")->execute([$importedCount, $sourceId]);

        $pdo->commit();

        jsonResponse([
            'success'         => true,
            'message'         => "Importação concluída",
            'source_id'       => $sourceId,
            'total_parsed'    => count($entries),
            'total_imported'  => $importedCount,
            'total_errors'    => count($errors),
            'errors'          => $errors,
            'parse_log'       => $parseLog,
        ], 201);

    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(['error' => true, 'message' => 'Erro durante importação: ' . $e->getMessage()], 500);
    }
    break;

// -----------------------------------------------------------------
default:
    jsonResponse(['error' => true, 'message' => 'Método não suportado'], 405);
}

// -----------------------------------------------------------------
// Converte para inteiro ou retorna null (evita string vazia em campos INT do MySQL)
// -----------------------------------------------------------------
function intOrNull(mixed $val): ?int
{
    if ($val === null || $val === '' || $val === false) return null;
    return (int) $val;
}

// Normaliza nome do autor para deduplicação
// -----------------------------------------------------------------
function normalizeAuthorName(string $name): string
{
    $name = mb_strtolower(trim($name));
    // Remove caracteres diacríticos
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    // Remove pontuação exceto hífen
    $name = preg_replace('/[^a-z\s\-]/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}
