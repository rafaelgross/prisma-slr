-- =============================================================
-- PRISMA-SLR: Sistema de Revisão Sistemática da Literatura
-- Schema MySQL - PRISMA 2020
-- =============================================================

CREATE DATABASE IF NOT EXISTS prisma_slr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prisma_slr;

-- -------------------------------------------------------------
-- Projetos de revisão sistemática
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    objective TEXT,
    inclusion_criteria TEXT,
    exclusion_criteria TEXT,
    search_period_start YEAR,
    search_period_end YEAR,
    status ENUM('active', 'completed', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Fontes de busca (arquivos .bib importados)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS search_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'Nome ex: Scopus, Web of Science',
    source_type ENUM('scopus', 'wos', 'pubmed', 'embase', 'other') DEFAULT 'other',
    search_string TEXT,
    search_date DATE,
    file_name VARCHAR(255),
    total_imported INT DEFAULT 0,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Autores normalizados
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(500) NOT NULL,
    normalized_name VARCHAR(500),
    orcid VARCHAR(50),
    UNIQUE KEY uk_normalized (normalized_name(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Artigos / registros bibliográficos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    source_id INT,
    source_type ENUM('scopus', 'wos', 'pubmed', 'embase', 'other') DEFAULT 'other',
    source_key VARCHAR(255) COMMENT 'Chave original do BibTeX',

    -- Metadados principais
    title TEXT NOT NULL,
    abstract TEXT,
    year INT,
    document_type VARCHAR(100),
    language VARCHAR(20) DEFAULT 'English',

    -- Periódico / Evento
    journal VARCHAR(500),
    volume VARCHAR(50),
    issue VARCHAR(50),
    pages VARCHAR(100),
    article_number VARCHAR(100),
    issn VARCHAR(30),
    eissn VARCHAR(30),
    isbn VARCHAR(50),

    -- Identificadores
    doi VARCHAR(255),
    url TEXT,
    eid VARCHAR(150) COMMENT 'Scopus EID',
    wos_id VARCHAR(150) COMMENT 'WoS UT (Unique Tag)',
    pubmed_id VARCHAR(50),

    -- Editora
    publisher VARCHAR(500),

    -- Métricas
    cited_by INT DEFAULT 0,
    open_access BOOLEAN DEFAULT FALSE,

    -- Status no processo de revisão
    status ENUM('identified','screened','eligible','included','excluded') DEFAULT 'identified',

    -- Duplicatas
    is_duplicate BOOLEAN DEFAULT FALSE,
    duplicate_of INT COMMENT 'ID do artigo canônico (principal)',

    -- Registro bruto
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    raw_bibtex LONGTEXT,

    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES search_sources(id) ON DELETE SET NULL,
    FOREIGN KEY (duplicate_of) REFERENCES articles(id) ON DELETE SET NULL,

    INDEX idx_project_status (project_id, status),
    INDEX idx_doi (doi(191)),
    INDEX idx_year (year),
    INDEX idx_source_type (source_type),
    INDEX idx_is_duplicate (is_duplicate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Relação N:N Artigos ↔ Autores
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS article_authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    author_id INT NOT NULL,
    position INT DEFAULT 1,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE,
    UNIQUE KEY uk_article_author (article_id, author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Palavras-chave dos artigos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS article_keywords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    keyword VARCHAR(500) NOT NULL,
    keyword_type ENUM('author','indexed','plus') DEFAULT 'author'
                 COMMENT 'author=autor, indexed=MeSH/Emtree, plus=Keywords-Plus WoS',
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    INDEX idx_keyword (keyword(191)),
    INDEX idx_type (keyword_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Afiliações institucionais dos artigos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS article_affiliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    institution TEXT,
    country VARCHAR(150),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    INDEX idx_country (country(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Pares de duplicatas detectados
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS duplicates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    article_id_1 INT NOT NULL,
    article_id_2 INT NOT NULL,
    match_score DECIMAL(5,2) COMMENT 'Similaridade 0-100',
    match_type ENUM('doi','title','combined') DEFAULT 'combined',
    status ENUM('pending','confirmed','rejected') DEFAULT 'pending',
    canonical_id INT COMMENT 'Artigo a manter (o outro vira duplicata)',
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id_1) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id_2) REFERENCES articles(id) ON DELETE CASCADE,
    UNIQUE KEY uk_pair (article_id_1, article_id_2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Motivos de exclusão (configuráveis por projeto)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exclusion_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    phase ENUM('screening','eligibility') NOT NULL,
    reason VARCHAR(500) NOT NULL,
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Decisões de triagem (screening e elegibilidade)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS screening_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    phase ENUM('screening','eligibility') NOT NULL,
    decision ENUM('include','exclude','uncertain') NOT NULL,
    reason_id INT,
    notes TEXT,
    decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (reason_id) REFERENCES exclusion_reasons(id) ON DELETE SET NULL,
    UNIQUE KEY uk_article_phase (article_id, phase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- Checklist PRISMA 2020 (27 itens)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prisma_checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    item_number INT NOT NULL,
    section VARCHAR(100),
    item_text TEXT,
    response TEXT,
    comment TEXT,
    page_reference VARCHAR(100),
    completed BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY uk_project_item (project_id, item_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Dados iniciais: motivos de exclusão padrão
-- (serão inseridos por projeto via API)
-- =============================================================

-- =============================================================
-- View: contagens do diagrama PRISMA por projeto
-- =============================================================
CREATE OR REPLACE VIEW vw_prisma_counts AS
SELECT
    p.id AS project_id,
    p.title AS project_title,

    -- Identificação: total importado
    (SELECT COUNT(*) FROM articles a WHERE a.project_id = p.id) AS total_identified,

    -- Por fonte
    (SELECT COUNT(*) FROM articles a
     INNER JOIN search_sources ss ON a.source_id = ss.id
     WHERE a.project_id = p.id AND ss.source_type = 'scopus') AS from_scopus,

    (SELECT COUNT(*) FROM articles a
     INNER JOIN search_sources ss ON a.source_id = ss.id
     WHERE a.project_id = p.id AND ss.source_type = 'wos') AS from_wos,

    -- Duplicatas removidas (confirmadas)
    (SELECT COUNT(DISTINCT article_id_2) FROM duplicates d
     WHERE d.project_id = p.id AND d.status = 'confirmed') AS duplicates_removed,

    -- Triados (screening)
    (SELECT COUNT(*) FROM articles a WHERE a.project_id = p.id
     AND a.status IN ('screened','eligible','included','excluded')
     AND a.is_duplicate = FALSE) AS screened,

    -- Excluídos na triagem
    (SELECT COUNT(*) FROM screening_decisions sd
     INNER JOIN articles a ON sd.article_id = a.id
     WHERE a.project_id = p.id AND sd.phase = 'screening'
     AND sd.decision = 'exclude') AS excluded_screening,

    -- Avaliados texto completo
    (SELECT COUNT(*) FROM screening_decisions sd
     INNER JOIN articles a ON sd.article_id = a.id
     WHERE a.project_id = p.id AND sd.phase = 'eligibility') AS assessed_eligibility,

    -- Excluídos na elegibilidade
    (SELECT COUNT(*) FROM screening_decisions sd
     INNER JOIN articles a ON sd.article_id = a.id
     WHERE a.project_id = p.id AND sd.phase = 'eligibility'
     AND sd.decision = 'exclude') AS excluded_eligibility,

    -- Incluídos
    (SELECT COUNT(*) FROM articles a WHERE a.project_id = p.id
     AND a.status = 'included') AS included

FROM projects p;

-- =============================================================
-- View: estatísticas gerais por projeto
-- =============================================================
CREATE OR REPLACE VIEW vw_project_stats AS
SELECT
    p.id AS project_id,
    COUNT(DISTINCT ss.id) AS total_sources,
    COUNT(DISTINCT a.id) AS total_articles,
    COUNT(DISTINCT CASE WHEN a.is_duplicate = TRUE THEN a.id END) AS total_duplicates,
    COUNT(DISTINCT CASE WHEN a.status = 'screened' AND a.is_duplicate = FALSE THEN a.id END) AS total_screened,
    COUNT(DISTINCT CASE WHEN a.status = 'included' THEN a.id END) AS total_included,
    COUNT(DISTINCT CASE WHEN a.status = 'excluded' THEN a.id END) AS total_excluded
FROM projects p
LEFT JOIN search_sources ss ON ss.project_id = p.id
LEFT JOIN articles a ON a.project_id = p.id
GROUP BY p.id;
