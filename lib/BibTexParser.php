<?php
/**
 * PRISMA-SLR - Parser BibTeX
 *
 * Suporta os dois formatos detectados:
 *   - Scopus: indentação com \t, campos como affiliations, author_keywords,
 *             note com "Cited by: N", EID, etc.
 *   - Web of Science: indentação com espaços, campos Unique-ID, Times-Cited,
 *                     Keywords-Plus, Affiliation, Web-of-Science-Index, etc.
 */

class BibTexParser
{
    /** @var array Entradas já parseadas */
    private array $entries = [];

    /** @var array Log de avisos e erros */
    private array $log = [];

    // -----------------------------------------------------------------
    // Interface pública
    // -----------------------------------------------------------------

    /**
     * Parseia um arquivo .bib e retorna array de entradas normalizadas.
     *
     * @param  string $content  Conteúdo bruto do arquivo
     * @param  string $sourceType 'scopus'|'wos'|'auto'
     * @return array ['entries' => [...], 'log' => [...]]
     */
    public function parse(string $content, string $sourceType = 'auto'): array
    {
        $this->entries = [];
        $this->log     = [];

        // Remove BOM UTF-8
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Detecta tipo automaticamente se necessário
        if ($sourceType === 'auto') {
            $sourceType = $this->detectSourceType($content);
        }

        // Extrai blocos @TYPE{...}
        $blocks = $this->extractBlocks($content);
        $this->log[] = ['type' => 'info', 'msg' => sprintf('%d blocos encontrados (fonte: %s)', count($blocks), $sourceType)];

        foreach ($blocks as $index => $block) {
            try {
                $entry = $this->parseBlock($block, $sourceType);
                if ($entry !== null) {
                    $this->entries[] = $entry;
                }
            } catch (Throwable $e) {
                $this->log[] = [
                    'type' => 'warning',
                    'msg'  => "Erro no bloco #{$index}: " . $e->getMessage()
                ];
            }
        }

        $this->log[] = ['type' => 'info', 'msg' => sprintf('%d entradas parseadas com sucesso', count($this->entries))];

        return [
            'entries'     => $this->entries,
            'log'         => $this->log,
            'source_type' => $sourceType,
        ];
    }

    // -----------------------------------------------------------------
    // Detecção de formato
    // -----------------------------------------------------------------

    private function detectSourceType(string $content): string
    {
        // Scopus usa EID e affiliations em minúsculo
        if (preg_match('/\bEID\s*=|affiliations\s*=|author_keywords\s*=/i', $content)) {
            return 'scopus';
        }
        // WoS usa Unique-ID e Times-Cited
        if (preg_match('/Unique-ID\s*=|Times-Cited\s*=|Web-of-Science-Index\s*=/i', $content)) {
            return 'wos';
        }
        return 'other';
    }

    // -----------------------------------------------------------------
    // Extração de blocos
    // -----------------------------------------------------------------

    private function extractBlocks(string $content): array
    {
        $blocks = [];
        $len    = strlen($content);
        $i      = 0;

        while ($i < $len) {
            // Procura início de uma entrada @
            $pos = strpos($content, '@', $i);
            if ($pos === false) break;

            // Ignora @comment, @preamble, @string
            $rest = substr($content, $pos + 1, 20);
            if (preg_match('/^(comment|preamble|string)\b/i', $rest)) {
                // Pula até o próximo @
                $i = $pos + 1;
                continue;
            }

            // Encontra a abertura {
            $openPos = strpos($content, '{', $pos);
            if ($openPos === false) break;

            // Percorre com contador de chaves para achar o fechamento
            $depth  = 0;
            $end    = $openPos;
            $inStr  = false;

            for ($j = $openPos; $j < $len; $j++) {
                $ch = $content[$j];
                if ($ch === '{') $depth++;
                elseif ($ch === '}') {
                    $depth--;
                    if ($depth === 0) { $end = $j; break; }
                }
            }

            if ($depth !== 0) {
                // Bloco mal formado - tenta recuperar
                $this->log[] = ['type' => 'warning', 'msg' => "Bloco mal formado iniciado na posição {$pos}"];
                $i = $pos + 1;
                continue;
            }

            $blocks[] = substr($content, $pos, $end - $pos + 1);
            $i = $end + 1;
        }

        return $blocks;
    }

    // -----------------------------------------------------------------
    // Parse de um bloco individual
    // -----------------------------------------------------------------

    private function parseBlock(string $block, string $sourceType): ?array
    {
        // Extrai tipo e chave: @ARTICLE{ChaveAqui,
        if (!preg_match('/^@(\w+)\s*\{\s*([^,\n]*)/s', $block, $m)) {
            return null;
        }

        $entryType = strtolower(trim($m[1]));
        $citeKey   = trim($m[2]);

        // Ignora entradas sem tipo reconhecido
        $validTypes = ['article','inproceedings','proceedings','conference','book',
                       'incollection','phdthesis','mastersthesis','techreport','misc','review'];
        // Passa mesmo se não reconhecido
        if (empty($citeKey)) return null;

        // Extrai o interior das chaves (tudo depois da vírgula da chave)
        $inner = substr($block, strpos($block, ',') + 1);
        // Remove a chave fechante final
        $inner = rtrim($inner, " \t\n\r}");

        // Parseia campos
        $rawFields = $this->parseFields($inner);

        // Normaliza para estrutura unificada
        $entry = $this->normalize($rawFields, $entryType, $citeKey, $sourceType, $block);

        return $entry;
    }

    // -----------------------------------------------------------------
    // Parse de todos os campos de um bloco
    // -----------------------------------------------------------------

    private function parseFields(string $inner): array
    {
        $fields = [];
        $len    = strlen($inner);
        $i      = 0;

        while ($i < $len) {
            // Pula espaços e vírgulas entre campos
            while ($i < $len && in_array($inner[$i], [' ', "\t", "\n", "\r", ','])) {
                $i++;
            }
            if ($i >= $len) break;

            // Lê o nome do campo (até o =)
            $eqPos = strpos($inner, '=', $i);
            if ($eqPos === false) break;

            $fieldName = strtolower(trim(substr($inner, $i, $eqPos - $i)));
            $fieldName = str_replace(['-', ' '], '_', $fieldName); // normaliza nome
            $i = $eqPos + 1;

            // Pula espaços após o =
            while ($i < $len && in_array($inner[$i], [' ', "\t"])) $i++;

            if ($i >= $len) break;

            // Lê valor: pode ser {conteudo}, "conteudo" ou número
            $value = '';
            if ($inner[$i] === '{') {
                // Valor entre chaves
                $depth = 0;
                $start = $i;
                for ($j = $i; $j < $len; $j++) {
                    if ($inner[$j] === '{') $depth++;
                    elseif ($inner[$j] === '}') {
                        $depth--;
                        if ($depth === 0) {
                            $value = substr($inner, $start + 1, $j - $start - 1);
                            $i = $j + 1;
                            break;
                        }
                    }
                }
            } elseif ($inner[$i] === '"') {
                // Valor entre aspas
                $i++;
                $start = $i;
                while ($i < $len && $inner[$i] !== '"') {
                    if ($inner[$i] === '\\') $i++; // escapa
                    $i++;
                }
                $value = substr($inner, $start, $i - $start);
                $i++; // fecha aspas
            } else {
                // Número ou referência a @string
                $start = $i;
                while ($i < $len && !in_array($inner[$i], [',', "\n", "\r"])) $i++;
                $value = trim(substr($inner, $start, $i - $start));
            }

            if (!empty($fieldName)) {
                $fields[$fieldName] = trim($value);
            }
        }

        return $fields;
    }

    // -----------------------------------------------------------------
    // Normalização para estrutura unificada
    // -----------------------------------------------------------------

    private function normalize(array $f, string $type, string $key, string $src, string $raw): array
    {
        $e = [
            'source_type'    => $src,
            'source_key'     => $key,
            'document_type'  => $this->normalizeDocType($type, $f['type'] ?? ''),
            'title'          => $this->cleanBraces($f['title'] ?? ''),
            'abstract'       => $this->cleanBraces($f['abstract'] ?? ''),
            'year'           => (int) ($f['year'] ?? 0) ?: null,
            'language'       => $this->cleanBraces($f['language'] ?? 'English'),

            // Periódico
            'journal'        => $this->cleanBraces(
                $f['journal'] ?? $f['booktitle'] ?? $f['source'] ?? ''
            ),
            'volume'         => $this->cleanBraces($f['volume'] ?? ''),
            'issue'          => $this->cleanBraces($f['number'] ?? $f['issue'] ?? ''),
            'pages'          => $this->cleanBraces($f['pages'] ?? ''),
            'article_number' => $this->cleanBraces($f['art_no'] ?? $f['article_number'] ?? ''),
            'issn'           => $this->normalizeISSN($f['issn'] ?? ''),
            'eissn'          => $this->normalizeISSN($f['eissn'] ?? ''),
            'isbn'           => $this->cleanBraces($f['isbn'] ?? ''),

            // Identificadores
            'doi'            => $this->normalizeDOI($f['doi'] ?? ''),
            'url'            => $this->cleanBraces($f['url'] ?? $f['link'] ?? ''),
            'eid'            => $this->cleanBraces($f['eid'] ?? ''),
            'wos_id'         => $this->extractWosId($f),
            'pubmed_id'      => $this->cleanBraces($f['pubmed_id'] ?? $f['pmid'] ?? ''),

            // Editora
            'publisher'      => $this->cleanBraces($f['publisher'] ?? ''),

            // Métricas
            'cited_by'       => $this->extractCitedBy($f),
            'open_access'    => $this->detectOpenAccess($f),

            // Autores, palavras-chave e afiliações (arrays)
            'authors'        => $this->parseAuthors($f['author'] ?? ''),
            'keywords_author'=> $this->parseKeywordList($f['author_keywords'] ?? $f['keywords'] ?? ''),
            'keywords_plus'  => $this->parseKeywordList($f['keywords_plus'] ?? ''),
            'affiliations'   => $this->parseAffiliations($f, $src),

            'raw_bibtex'     => $raw,
        ];

        // Remove campos vazios (mantém 0 e false)
        foreach ($e as $k => $v) {
            if ($v === '' || $v === [] || $v === null) {
                $e[$k] = null;
            }
        }

        return $e;
    }

    // -----------------------------------------------------------------
    // Helpers de normalização
    // -----------------------------------------------------------------

    /** Remove chaves aninhadas e quebras de linha extras */
    private function cleanBraces(string $s): string
    {
        $s = preg_replace('/\{+([^{}]*)\}+/', '$1', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    private function normalizeDocType(string $type, string $rawType): string
    {
        $map = [
            'article'         => 'Article',
            'review'          => 'Review',
            'inproceedings'   => 'Conference Paper',
            'proceedings'     => 'Conference Paper',
            'conference'      => 'Conference Paper',
            'book'            => 'Book',
            'incollection'    => 'Book Chapter',
            'phdthesis'       => 'Thesis',
            'mastersthesis'   => 'Thesis',
            'techreport'      => 'Report',
            'misc'            => 'Other',
        ];

        // Scopus/WoS podem ter type= dentro do campo
        if (!empty($rawType)) {
            $rt = strtolower(trim($rawType));
            foreach ($map as $k => $v) {
                if (str_contains($rt, $k)) return $v;
            }
            return ucfirst($rawType);
        }

        return $map[$type] ?? ucfirst($type);
    }

    private function normalizeDOI(string $doi): string
    {
        $doi = trim($doi);
        // Remove prefixos como https://doi.org/
        $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi);
        return $doi;
    }

    private function normalizeISSN(string $issn): string
    {
        $issn = trim($issn);
        // Pega o primeiro ISSN se houver vários separados por ; ou ,
        $parts = preg_split('/[;,\s]+/', $issn);
        $first = trim($parts[0] ?? '');
        // Formata como XXXX-XXXX
        $digits = preg_replace('/\D/', '', $first);
        if (strlen($digits) === 8) {
            return substr($digits, 0, 4) . '-' . substr($digits, 4);
        }
        return $first;
    }

    private function extractWosId(array $f): ?string
    {
        // WoS UT ou Unique-ID
        $uid = $f['unique_id'] ?? $f['ut'] ?? $f['wos_id'] ?? '';
        $uid = $this->cleanBraces($uid);
        // Remove prefixo WOS:
        $uid = preg_replace('/^WOS:/i', '', $uid);
        return $uid ?: null;
    }

    private function extractCitedBy(array $f): int
    {
        // WoS: Times-Cited
        if (isset($f['times_cited'])) {
            return (int) preg_replace('/\D/', '', $f['times_cited']);
        }
        // Scopus: note = {Cited by: N; ...}
        if (isset($f['note'])) {
            if (preg_match('/Cited by:\s*(\d+)/i', $f['note'], $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    private function detectOpenAccess(array $f): bool
    {
        $oa = strtolower($f['open_access'] ?? $f['oa'] ?? '');
        if (in_array($oa, ['true', '1', 'yes', 'gold', 'green', 'diamond'])) return true;

        // Scopus: publication_stage = Final + alguns campos
        if (isset($f['note']) && str_contains(strtolower($f['note']), 'open access')) return true;

        return false;
    }

    // -----------------------------------------------------------------
    // Autores
    // -----------------------------------------------------------------

    /**
     * Parseia campo author = {Sobrenome, Nome and Sobrenome2, Nome2 and ...}
     * Retorna array de strings com nomes completos
     */
    private function parseAuthors(string $authorStr): array
    {
        if (empty($authorStr)) return [];

        $authorStr = $this->cleanBraces($authorStr);

        // Divide por " and " (case-insensitive)
        $parts = preg_split('/\s+and\s+/i', $authorStr);
        $authors = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // Formato "Sobrenome, Nome" → "Nome Sobrenome"
            if (str_contains($part, ',')) {
                [$last, $first] = array_map('trim', explode(',', $part, 2));
                $full = trim("$first $last");
            } else {
                $full = $part;
            }

            // Remove pontuação dupla e espaços extras
            $full = preg_replace('/\s+/', ' ', $full);
            if (!empty($full)) {
                $authors[] = $full;
            }
        }

        return $authors;
    }

    // -----------------------------------------------------------------
    // Palavras-chave
    // -----------------------------------------------------------------

    /**
     * Parseia listas de palavras-chave separadas por ; ou ,
     */
    private function parseKeywordList(string $kwStr): array
    {
        if (empty($kwStr)) return [];

        $kwStr = $this->cleanBraces($kwStr);

        // Scopus usa ; WoS usa ;  mas às vezes ,
        $parts = preg_split('/\s*[;]\s*/', $kwStr);
        if (count($parts) === 1) {
            // Tenta separar por ,
            $parts = preg_split('/\s*,\s*/', $kwStr);
        }

        $keywords = [];
        foreach ($parts as $kw) {
            $kw = trim($kw, " \t\n\r\"'{}");
            if (strlen($kw) > 1) {
                $keywords[] = $kw;
            }
        }

        return array_unique($keywords);
    }

    // -----------------------------------------------------------------
    // Afiliações
    // -----------------------------------------------------------------

    private function parseAffiliations(array $f, string $src): array
    {
        $raw = '';

        if ($src === 'scopus') {
            $raw = $f['affiliations'] ?? '';
        } elseif ($src === 'wos') {
            $raw = $f['affiliation'] ?? '';
        } else {
            $raw = $f['affiliations'] ?? $f['affiliation'] ?? '';
        }

        $raw = $this->cleanBraces($raw);
        if (empty($raw)) return [];

        $affs = [];
        // Scopus: separado por ; entre afiliações completas
        $parts = preg_split('/;\s*(?=[A-Z])/u', $raw);
        if (count($parts) === 1) {
            $parts = explode(';', $raw);
        }

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            $country = $this->extractCountry($part);
            $affs[] = [
                'institution' => $part,
                'country'     => $country,
            ];
        }

        return $affs;
    }

    /**
     * Tenta extrair o país do texto da afiliação
     * (geralmente é o último elemento separado por vírgula)
     */
    private function extractCountry(string $affiliation): string
    {
        $parts = array_map('trim', explode(',', $affiliation));
        $last  = end($parts);

        // Lista parcial de países para validação básica
        $countries = [
            'Brazil', 'Brasil', 'United States', 'USA', 'United Kingdom', 'UK',
            'China', 'Germany', 'France', 'Italy', 'Spain', 'Portugal', 'Australia',
            'Canada', 'Japan', 'India', 'Netherlands', 'Sweden', 'Norway', 'Denmark',
            'Switzerland', 'Belgium', 'Austria', 'Poland', 'Russia', 'South Korea',
            'Korea', 'Taiwan', 'Singapore', 'Malaysia', 'Indonesia', 'Mexico',
            'Argentina', 'Colombia', 'Chile', 'South Africa', 'Turkey', 'Iran',
            'Saudi Arabia', 'Egypt', 'Israel', 'Greece', 'Czech Republic', 'Finland',
        ];

        foreach ($countries as $c) {
            if (stripos($last, $c) !== false) return $c;
        }

        // Se o último fragmento parece ser país (curto, sem números)
        if (strlen($last) < 50 && !preg_match('/\d/', $last)) {
            return $last;
        }

        return '';
    }

    // -----------------------------------------------------------------
    // Getters
    // -----------------------------------------------------------------

    public function getEntries(): array { return $this->entries; }
    public function getLog(): array     { return $this->log; }
}
