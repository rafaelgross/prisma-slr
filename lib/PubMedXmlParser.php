<?php
/**
 * PRISMA-SLR — PubMedXmlParser.php
 * Parseia arquivos XML exportados diretamente do PubMed
 * (formato PubmedArticleSet / MedlineCitationSet).
 *
 * Retorna o mesmo formato de array que BibTexParser para compatibilidade
 * com api/import.php.
 */

class PubMedXmlParser
{
    private array $log = [];

    /**
     * Parseia conteúdo XML do PubMed.
     *
     * @param  string $content    Conteúdo bruto do arquivo .xml
     * @param  string $sourceType sempre 'pubmed' neste parser
     * @return array ['entries' => [...], 'log' => [...]]
     */
    public function parse(string $content, string $sourceType = 'pubmed'): array
    {
        $this->log = [];
        $entries   = [];

        // Remove BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Suprime avisos XML e carrega
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $msg = implode('; ', array_map(fn($e) => trim($e->message), $errors));
            $this->log[] = ['type' => 'error', 'msg' => 'XML inválido: ' . $msg];
            libxml_clear_errors();
            return ['entries' => [], 'log' => $this->log];
        }

        // Suporta tanto PubmedArticleSet quanto MedlineCitationSet
        $rootTag = $xml->getName();
        $articles = [];

        if ($rootTag === 'PubmedArticleSet') {
            foreach ($xml->PubmedArticle as $pa) {
                $articles[] = $pa;
            }
            // Alguns exports têm PubmedBookArticle também
            foreach ($xml->PubmedBookArticle as $pa) {
                $articles[] = $pa;
            }
        } elseif ($rootTag === 'PubmedArticle') {
            $articles[] = $xml;
        } else {
            // Tenta encontrar artigos em qualquer estrutura
            $articles = iterator_to_array($xml->children(), false);
        }

        $this->log[] = ['type' => 'info', 'msg' => count($articles) . ' artigos encontrados no XML PubMed'];

        foreach ($articles as $i => $pa) {
            try {
                $entry = $this->parseArticle($pa);
                if ($entry) $entries[] = $entry;
            } catch (Throwable $e) {
                $this->log[] = ['type' => 'error', 'msg' => "Artigo $i: " . $e->getMessage()];
            }
        }

        $this->log[] = ['type' => 'info', 'msg' => count($entries) . ' entradas parseadas com sucesso'];
        return ['entries' => $entries, 'log' => $this->log];
    }

    // ── Parseia um único elemento <PubmedArticle> ───────────────────
    private function parseArticle(SimpleXMLElement $pa): ?array
    {
        // Navega para MedlineCitation
        $mc = $pa->MedlineCitation ?? $pa;
        if (!$mc) return null;

        $article = $mc->Article ?? null;
        if (!$article) return null;

        // ── PMID ──────────────────────────────────────────────────────
        $pmid = (string)($mc->PMID ?? '');

        // ── Título ────────────────────────────────────────────────────
        $title = $this->xmlText($article->ArticleTitle);
        if (!$title) return null;

        // ── Journal ───────────────────────────────────────────────────
        $journal = (string)($article->Journal->Title ?? '');
        $issn    = (string)($article->Journal->ISSN ?? '');
        $issnType = (string)($article->Journal->ISSN['IssnType'] ?? '');

        $ji     = $article->Journal->JournalIssue ?? null;
        $vol    = (string)($ji->Volume ?? '');
        $issue  = (string)($ji->Issue  ?? '');

        // Ano
        $year = null;
        if ($ji && $ji->PubDate) {
            $year = (string)($ji->PubDate->Year  ?? '') ?: null;
            if (!$year) {
                // MedlineDate: "2023 Jan-Feb"
                $med = (string)($ji->PubDate->MedlineDate ?? '');
                if ($med) { preg_match('/(\d{4})/', $med, $m); $year = $m[1] ?? null; }
            }
        }

        // ── Páginas ───────────────────────────────────────────────────
        $pages = (string)($article->Pagination->MedlinePgn ?? '');

        // ── DOI ───────────────────────────────────────────────────────
        $doi = null;
        foreach (($article->ELocationID ?? []) as $loc) {
            if ((string)$loc['EIdType'] === 'doi') { $doi = (string)$loc; break; }
        }
        // Também busca em ArticleIdList
        if (!$doi) {
            foreach (($pa->PubmedData->ArticleIdList->ArticleId ?? []) as $aid) {
                if ((string)$aid['IdType'] === 'doi') { $doi = (string)$aid; break; }
            }
        }

        // ── Abstract ─────────────────────────────────────────────────
        $abstract = '';
        foreach (($article->Abstract->AbstractText ?? []) as $at) {
            $label = (string)($at['Label'] ?? '');
            $text  = $this->xmlText($at);
            if ($label) $abstract .= "$label: $text ";
            else        $abstract .= $text . ' ';
        }
        $abstract = trim($abstract);

        // ── Autores ───────────────────────────────────────────────────
        $authors      = [];
        $affiliations = [];
        foreach (($article->AuthorList->Author ?? []) as $au) {
            $last  = (string)($au->LastName  ?? '');
            $fore  = (string)($au->ForeName  ?? '');
            $init  = (string)($au->Initials  ?? '');
            $coll  = (string)($au->CollectiveName ?? '');

            if ($coll) {
                $authors[] = $coll;
            } elseif ($last) {
                $name = $last;
                if ($fore)     $name .= ', ' . $fore;
                elseif ($init) $name .= ', ' . $init . '.';
                $authors[] = $name;
            }

            // Afiliações
            foreach (($au->AffiliationInfo->Affiliation ?? []) as $aff) {
                $affText = (string)$aff;
                if ($affText) {
                    $country = $this->extractCountry($affText);
                    $affiliations[] = ['institution' => $affText, 'country' => $country];
                }
            }
        }

        // ── Keywords ─────────────────────────────────────────────────
        $kwAuthor = [];
        foreach (($mc->KeywordList->Keyword ?? []) as $kw) {
            $k = trim((string)$kw);
            if ($k) $kwAuthor[] = $k;
        }

        // MeSH como keywords indexadas
        $kwMesh = [];
        foreach (($mc->MeshHeadingList->MeshHeading ?? []) as $mh) {
            $k = trim((string)($mh->DescriptorName ?? ''));
            if ($k) $kwMesh[] = $k;
        }

        // ── Tipo de documento ─────────────────────────────────────────
        $docType = 'Journal Article';
        foreach (($article->PublicationTypeList->PublicationType ?? []) as $pt) {
            $t = (string)$pt;
            if (stripos($t, 'review') !== false) { $docType = 'Review'; break; }
            if (stripos($t, 'clinical trial') !== false) { $docType = 'Clinical Trial'; break; }
        }

        // ── Language ─────────────────────────────────────────────────
        $lang = (string)($article->Language ?? 'English');
        if ($lang === 'eng') $lang = 'English';

        // ── Source key ───────────────────────────────────────────────
        $sourceKey = $pmid ? 'PMID_' . $pmid : 'XML_' . substr(md5($title), 0, 8);

        // ── ISSN splitting ────────────────────────────────────────────
        $printIssn = null; $elecIssn = null;
        if ($issnType === 'Print')       $printIssn = $issn;
        elseif ($issnType === 'Electronic') $elecIssn = $issn;
        else $printIssn = $issn;

        // Busca ISSN eletrônico separado
        foreach (($article->Journal->children() ?? []) as $child) {
            if ($child->getName() === 'ISSN' && (string)$child['IssnType'] === 'Electronic') {
                $elecIssn = (string)$child;
            }
        }

        return [
            'source_key'      => $sourceKey,
            'source_type'     => 'pubmed',
            'title'           => $title,
            'abstract'        => $abstract ?: null,
            'year'            => $year,
            'document_type'   => $docType,
            'language'        => $lang,
            'journal'         => $journal ?: null,
            'volume'          => $vol ?: null,
            'issue'           => $issue ?: null,
            'pages'           => $pages ?: null,
            'article_number'  => null,
            'issn'            => $printIssn ?: null,
            'eissn'           => $elecIssn ?: null,
            'isbn'            => null,
            'doi'             => $doi ?: null,
            'url'             => $doi ? 'https://doi.org/' . $doi : ($pmid ? 'https://pubmed.ncbi.nlm.nih.gov/' . $pmid : null),
            'eid'             => null,
            'wos_id'          => null,
            'pubmed_id'       => $pmid ?: null,
            'publisher'       => null,
            'cited_by'        => 0,
            'open_access'     => 0,
            'raw_bibtex'      => null,
            'authors'         => $authors,
            'keywords_author' => $kwAuthor,
            'keywords_plus'   => $kwMesh,
            'affiliations'    => $affiliations,
        ];
    }

    // ── Extrai texto limpo de um elemento XML (remove tags filhas) ───
    private function xmlText(SimpleXMLElement|null $el): string
    {
        if ($el === null) return '';
        // Pega texto incluindo filhos (e.g. <i>, <sub>, <sup> em títulos)
        $dom  = dom_import_simplexml($el);
        return trim($dom->textContent ?? (string)$el);
    }

    // ── Tenta extrair país da string de afiliação ────────────────────
    private function extractCountry(string $aff): string
    {
        // Geralmente o país é o último token após a última vírgula ou ponto
        $parts = preg_split('/[,;.]/', $aff);
        return trim(end($parts) ?: '');
    }
}
