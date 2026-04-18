<?php
/**
 * PRISMA-SLR — RisParser.php
 * Parseia arquivos .ris exportados por Mendeley, Zotero, EndNote,
 * Scopus (RIS), Web of Science (RIS), Embase, PubMed, etc.
 *
 * Retorna o mesmo formato de array que BibTexParser para compatibilidade
 * com api/import.php.
 */

class RisParser
{
    private array $entries = [];
    private array $log     = [];

    // Mapeamento TY → document_type legível
    private const TYPE_MAP = [
        'JOUR' => 'Journal Article',
        'JFULL'=> 'Journal Article',
        'MGZN' => 'Magazine Article',
        'NEWS' => 'Newspaper Article',
        'CONF' => 'Conference Paper',
        'CPAPER'=> 'Conference Paper',
        'BOOK' => 'Book',
        'CHAP' => 'Book Chapter',
        'THES' => 'Thesis',
        'RPRT' => 'Report',
        'ELEC' => 'Web Page',
        'ABST' => 'Abstract',
        'ADVS' => 'Audiovisual Material',
        'GENERIC'=> 'Generic',
        'GEN'  => 'Generic',
        'ICOMM'=> 'Internet Communication',
        'INPR' => 'In Press',
        'PAMP' => 'Pamphlet',
        'PAT'  => 'Patent',
        'PCOMM'=> 'Personal Communication',
        'SLIDE'=> 'Slides',
        'SOUND'=> 'Sound Recording',
        'STD'  => 'Standard',
        'STAT' => 'Statute',
        'UNBILL'=> 'Unenacted Bill',
        'UNPB' => 'Unpublished Work',
        'VIDEO'=> 'Video Recording',
    ];

    /**
     * Parseia conteúdo RIS.
     *
     * @param  string $content    Conteúdo bruto do arquivo .ris
     * @param  string $sourceType 'scopus'|'wos'|'pubmed'|'embase'|'other'|'auto'
     * @return array ['entries' => [...], 'log' => [...]]
     */
    public function parse(string $content, string $sourceType = 'auto'): array
    {
        $this->entries = [];
        $this->log     = [];

        // Remove BOM e normaliza quebras de linha
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        // Detecta fonte automaticamente
        if ($sourceType === 'auto') {
            $sourceType = $this->detectSource($content);
        }

        // Divide em blocos por ER (End of Reference)
        $blocks = $this->splitBlocks($content);
        $this->log[] = ['type' => 'info', 'msg' => sprintf('%d blocos RIS encontrados (fonte: %s)', count($blocks), $sourceType)];

        foreach ($blocks as $i => $block) {
            try {
                $entry = $this->parseBlock($block, $sourceType);
                if ($entry) $this->entries[] = $entry;
            } catch (Throwable $e) {
                $this->log[] = ['type' => 'error', 'msg' => "Bloco $i: " . $e->getMessage()];
            }
        }

        $this->log[] = ['type' => 'info', 'msg' => count($this->entries) . ' entradas parseadas com sucesso'];
        return ['entries' => $this->entries, 'log' => $this->log];
    }

    // ── Divide o conteúdo em blocos (um por referência) ─────────────
    private function splitBlocks(string $content): array
    {
        $blocks = [];
        $current = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $trimmed = rtrim($line);
            // Linha ER marca o fim de uma referência
            if (preg_match('/^ER\s*[-–]\s*/', $trimmed)) {
                if (!empty($current)) {
                    $blocks[] = implode("\n", $current);
                    $current  = [];
                }
                continue;
            }
            $current[] = $trimmed;
        }
        // Último bloco sem ER
        if (!empty(array_filter($current))) {
            $blocks[] = implode("\n", $current);
        }

        return array_filter($blocks, fn($b) => trim($b) !== '');
    }

    // ── Parseia um único bloco RIS ───────────────────────────────────
    private function parseBlock(string $block, string $sourceType): ?array
    {
        $tags   = $this->extractTags($block);
        if (empty($tags)) return null;

        // Título
        $title = $this->firstOf($tags, ['TI', 'T1', 'CT', 'BT']);
        if (!$title) return null;

        // Tipo de documento
        $tyRaw  = strtoupper(trim($this->firstOf($tags, ['TY']) ?? 'JOUR'));
        $docType = self::TYPE_MAP[$tyRaw] ?? 'Journal Article';

        // Journal / Periódico
        $journal = $this->firstOf($tags, ['JO', 'JF', 'JA', 'J2', 'T2', 'BT', 'SE']);

        // Ano — RIS usa PY ou Y1 (formato YYYY ou YYYY/MM/DD/)
        $yearRaw = $this->firstOf($tags, ['PY', 'Y1', 'DA', 'Y2']);
        $year    = null;
        if ($yearRaw) {
            preg_match('/(\d{4})/', $yearRaw, $m);
            $year = $m[1] ?? null;
        }

        // Páginas
        $sp    = trim($this->firstOf($tags, ['SP']) ?? '');
        $ep    = trim($this->firstOf($tags, ['EP']) ?? '');
        $pages = '';
        if ($sp && $ep)      $pages = "$sp-$ep";
        elseif ($sp)         $pages = $sp;
        elseif (isset($tags['PG'])) $pages = $tags['PG'][0];

        // DOI
        $doi = $this->firstOf($tags, ['DO', 'M3']);
        if ($doi && !preg_match('/^10\./', $doi)) {
            // M3 pode conter outros tipos; valida prefixo DOI
            if ($this->firstOf($tags, ['DO'])) $doi = $this->firstOf($tags, ['DO']);
            elseif (!preg_match('/^10\./', $doi)) $doi = null;
        }

        // ISSN / ISBN
        $snValues = $tags['SN'] ?? [];
        $issn  = null; $eissn = null; $isbn = null;
        foreach ($snValues as $sn) {
            $sn = trim($sn);
            if (preg_match('/^\d{4}-\d{3}[\dX]$/i', $sn) || preg_match('/^\d{8}$/', $sn)) {
                if ($issn === null) $issn = $sn; else $eissn = $sn;
            } elseif (preg_match('/^97[89]/', preg_replace('/[^0-9]/', '', $sn))) {
                $isbn = $sn;
            } elseif ($issn === null) {
                $issn = $sn;
            }
        }

        // Autores
        $authors = $tags['AU'] ?? $tags['A1'] ?? $tags['A2'] ?? [];
        $authors = array_map('trim', $authors);
        $authors = array_filter($authors);

        // Keywords
        $kwAuthor = array_map('trim', $tags['KW'] ?? $tags['DE'] ?? []);
        $kwAuthor = array_filter($kwAuthor);

        // Abstract
        $abstract = implode(' ', array_merge($tags['AB'] ?? [], $tags['N2'] ?? []));

        // IDs externos
        $pubmedId = $this->firstOf($tags, ['AN', 'C7', 'M1']);
        // Tenta extrair PMID real se vier de PubMed
        if ($sourceType === 'pubmed' && isset($tags['AN'])) {
            $pubmedId = preg_replace('/\D/', '', $tags['AN'][0] ?? '');
        }

        // URL
        $url = $this->firstOf($tags, ['UR', 'L1', 'L2', 'LK', 'U1', 'U2']);

        // Publisher
        $publisher = $this->firstOf($tags, ['PB', 'PU']);

        // Language
        $lang = $this->firstOf($tags, ['LA', 'LG']) ?? 'English';

        // Número do artigo (sem páginas, e.g. Embase)
        $articleNumber = $this->firstOf($tags, ['C7', 'M2']);

        // Source key único
        $sourceKey = $this->firstOf($tags, ['ID', 'RN', 'CN', 'RI']) ?? 'RIS_' . substr(md5($title . ($year ?? '')), 0, 8);

        // Afiliações
        $affiliations = [];
        foreach (($tags['AD'] ?? $tags['C1'] ?? []) as $aff) {
            $affiliations[] = ['institution' => trim($aff), 'country' => ''];
        }

        // Cited-by
        $citedBy = 0;
        if (!empty($tags['Z9'])) $citedBy = (int) $tags['Z9'][0];
        elseif (!empty($tags['TC'])) $citedBy = (int) $tags['TC'][0];

        // raw
        $raw = $block;

        return [
            'source_key'      => $sourceKey,
            'source_type'     => $sourceType,
            'title'           => $title,
            'abstract'        => $abstract ?: null,
            'year'            => $year,
            'document_type'   => $docType,
            'language'        => $lang,
            'journal'         => $journal,
            'volume'          => $this->firstOf($tags, ['VL', 'VO']),
            'issue'           => $this->firstOf($tags, ['IS', 'IP', 'CP']),
            'pages'           => $pages ?: null,
            'article_number'  => $articleNumber,
            'issn'            => $issn,
            'eissn'           => $eissn,
            'isbn'            => $isbn,
            'doi'             => $doi,
            'url'             => $url,
            'eid'             => null,
            'wos_id'          => $sourceType === 'wos' ? ($this->firstOf($tags, ['UT', 'M3']) ?? null) : null,
            'pubmed_id'       => $pubmedId,
            'publisher'       => $publisher,
            'cited_by'        => $citedBy,
            'open_access'     => 0,
            'raw_bibtex'      => $raw,
            'authors'         => array_values($authors),
            'keywords_author' => array_values($kwAuthor),
            'keywords_plus'   => [],
            'affiliations'    => $affiliations,
        ];
    }

    // ── Extrai pares tag => [valores] do bloco ───────────────────────
    private function extractTags(string $block): array
    {
        $tags  = [];
        $curTag = null;
        $curVal = '';

        foreach (explode("\n", $block) as $line) {
            // Linha de tag: 2 chars, espaços/hífen, valor
            // Exemplos: "TI  - Title here", "AU  - Smith, J", "KW  -keyword"
            if (preg_match('/^([A-Z][A-Z0-9])\s*[-–]\s*(.*)/u', $line, $m)) {
                if ($curTag !== null) {
                    $tags[$curTag][] = rtrim($curVal);
                }
                $curTag = $m[1];
                $curVal = $m[2];
            } elseif ($curTag !== null && (str_starts_with($line, '  ') || str_starts_with($line, "\t"))) {
                // Continuação de valor (linha indentada)
                $curVal .= ' ' . trim($line);
            }
        }
        if ($curTag !== null) {
            $tags[$curTag][] = rtrim($curVal);
        }

        return $tags;
    }

    // ── Helpers ─────────────────────────────────────────────────────
    private function firstOf(array $tags, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (!empty($tags[$k][0])) return trim($tags[$k][0]);
        }
        return null;
    }

    private function detectSource(string $content): string
    {
        if (stripos($content, 'scopus') !== false || stripos($content, 'eid') !== false)  return 'scopus';
        if (stripos($content, 'wos') !== false || strpos($content, 'UT  -') !== false)    return 'wos';
        if (stripos($content, 'pubmed') !== false || strpos($content, 'PMID') !== false)  return 'pubmed';
        if (stripos($content, 'embase') !== false)                                         return 'embase';
        return 'other';
    }
}
