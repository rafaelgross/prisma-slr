<?php
/**
 * PRISMA-SLR - API: Projetos
 * GET    /api/projects.php        → listar todos
 * GET    /api/projects.php?id=N   → buscar um
 * POST   /api/projects.php        → criar
 * PUT    /api/projects.php?id=N   → atualizar
 * DELETE /api/projects.php?id=N   → excluir
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Sessão deve ser iniciada antes de qualquer output
startSecureSession();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Exige autenticação
$sessionUser = currentUser();
if (!$sessionUser) {
    jsonResponse(['error' => true, 'message' => 'Não autenticado'], 401);
}
$userId = $sessionUser['id'];

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = $_GET['action'] ?? null;

// ── Ação especial: sync_reasons ───────────────────────────────────
// Sincroniza exclusion_reasons da fase 'screening' com o campo exclusion_criteria do projeto
if ($action === 'sync_reasons') {
    $projId = $id ?? (int)($_GET['project_id'] ?? 0);
    if (!$projId) jsonResponse(['error'=>true,'message'=>'project_id obrigatório'], 422);
    $stmt = $pdo->prepare("SELECT exclusion_criteria FROM projects WHERE id = ?");
    $stmt->execute([$projId]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['error'=>true,'message'=>'Projeto não encontrado'], 404);
    syncExclusionReasons($pdo, $projId, $row['exclusion_criteria'] ?? '');
    // Retorna os motivos atualizados
    $stmt2 = $pdo->prepare("SELECT id, reason, description FROM exclusion_reasons WHERE project_id = ? AND phase = 'screening' ORDER BY sort_order");
    $stmt2->execute([$projId]);
    jsonResponse(['success'=>true, 'reasons' => $stmt2->fetchAll()]);
}

// ── Ações especiais: checklist ────────────────────────────────────
if ($action === 'checklist') {
    if ($method === 'GET') {
        $projId = $id ?? (int)($_GET['project_id'] ?? 0);
        if (!$projId) jsonResponse(['error'=>true,'message'=>'project_id obrigatório'], 422);
        $stmt = $pdo->prepare("SELECT * FROM prisma_checklist WHERE project_id = ? ORDER BY item_number");
        $stmt->execute([$projId]);
        jsonResponse($stmt->fetchAll());
    }
    if ($method === 'POST') {
        $data = getJsonBody();
        $projId = (int)($data['project_id'] ?? 0);
        $num    = (int)($data['item_number'] ?? 0);
        if (!$projId || !$num) jsonResponse(['error'=>true,'message'=>'project_id e item_number obrigatórios'], 422);
        $stmt = $pdo->prepare("
            INSERT INTO prisma_checklist (project_id, item_number, completed, response, comment, page_reference)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              completed      = VALUES(completed),
              response       = VALUES(response),
              comment        = VALUES(comment),
              page_reference = VALUES(page_reference),
              updated_at     = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $projId, $num,
            isset($data['completed']) ? (int)(bool)$data['completed'] : 0,
            $data['response']       ?? null,
            $data['comment']        ?? null,
            $data['page_reference'] ?? null,
        ]);
        jsonResponse(['success' => true]);
    }
    jsonResponse(['error'=>true,'message'=>'Método não suportado'], 405);
}

// -----------------------------------------------------------------
switch ($method) {
// -----------------------------------------------------------------

case 'GET':
    if ($id) {
        // Buscar projeto com estatísticas — só se pertencer ao usuário logado
        $stmt = $pdo->prepare("
            SELECT p.*,
                   (SELECT COUNT(*) FROM search_sources ss WHERE ss.project_id = p.id) AS total_sources,
                   (SELECT COUNT(*) FROM articles a WHERE a.project_id = p.id) AS total_articles,
                   (SELECT COUNT(*) FROM articles a WHERE a.project_id = p.id AND a.is_duplicate = 1) AS total_duplicates,
                   (SELECT COUNT(*) FROM articles a WHERE a.project_id = p.id AND a.status = 'included') AS total_included
            FROM projects p
            WHERE p.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$id, $userId]);
        $project = $stmt->fetch();

        if (!$project) {
            jsonResponse(['error' => true, 'message' => 'Projeto não encontrado'], 404);
        }

        // Fontes de busca
        $stmt2 = $pdo->prepare("SELECT * FROM search_sources WHERE project_id = ? ORDER BY imported_at DESC");
        $stmt2->execute([$id]);
        $project['sources'] = $stmt2->fetchAll();

        // Motivos de exclusão
        $stmt3 = $pdo->prepare("SELECT * FROM exclusion_reasons WHERE project_id = ? ORDER BY phase, sort_order");
        $stmt3->execute([$id]);
        $project['exclusion_reasons'] = $stmt3->fetchAll();

        jsonResponse($project);
    } else {
        // Listar projetos do usuário logado com estatísticas resumidas
        $stmt = $pdo->prepare("
            SELECT p.*,
                   COALESCE(s.total_sources, 0) AS total_sources,
                   COALESCE(s.total_articles, 0) AS total_articles,
                   COALESCE(s.total_duplicates, 0) AS total_duplicates,
                   COALESCE(s.total_included, 0) AS total_included
            FROM projects p
            LEFT JOIN (
                SELECT p2.id AS project_id,
                       COUNT(DISTINCT ss.id) AS total_sources,
                       COUNT(DISTINCT a.id) AS total_articles,
                       COUNT(DISTINCT CASE WHEN a.is_duplicate = 1 THEN a.id END) AS total_duplicates,
                       COUNT(DISTINCT CASE WHEN a.status = 'included' THEN a.id END) AS total_included
                FROM projects p2
                LEFT JOIN search_sources ss ON ss.project_id = p2.id
                LEFT JOIN articles a ON a.project_id = p2.id
                WHERE p2.user_id = ?
                GROUP BY p2.id
            ) s ON s.project_id = p.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        jsonResponse($stmt->fetchAll());
    }
    break;

// -----------------------------------------------------------------
case 'POST':
    $data = getJsonBody();

    if (empty($data['title'])) {
        jsonResponse(['error' => true, 'message' => 'Título é obrigatório'], 422);
    }

    $stmt = $pdo->prepare("
        INSERT INTO projects (user_id, title, description, objective, inclusion_criteria,
                              exclusion_criteria, search_period_start, search_period_end)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        trim($data['title']),
        $data['description']        ?? null,
        $data['objective']          ?? null,
        $data['inclusion_criteria'] ?? null,
        $data['exclusion_criteria'] ?? null,
        $data['search_period_start'] ?? null,
        $data['search_period_end']   ?? null,
    ]);

    $newId = (int) $pdo->lastInsertId();

    // Insere motivos de exclusão padrão para elegibilidade
    $defaultEligibility = [
        ['eligibility', 'Metodologia inadequada',             'O método não atende aos critérios de inclusão'],
        ['eligibility', 'Dados insuficientes',                'O estudo não apresenta dados suficientes para análise'],
        ['eligibility', 'Texto completo indisponível',        'Não foi possível acessar o texto completo'],
        ['eligibility', 'Não responde à questão de pesquisa', 'O conteúdo não responde às perguntas da revisão'],
    ];
    $stmtR = $pdo->prepare("
        INSERT INTO exclusion_reasons (project_id, phase, reason, description, is_default, sort_order)
        VALUES (?, ?, ?, ?, 1, ?)
    ");
    foreach ($defaultEligibility as $i => $r) {
        $stmtR->execute([$newId, $r[0], $r[1], $r[2], $i]);
    }

    // Sincroniza critérios de exclusão da triagem com exclusion_reasons
    syncExclusionReasons($pdo, $newId, $data['exclusion_criteria'] ?? '');

    // Inicializa checklist PRISMA 2020
    insertPrismaChecklist($pdo, $newId);

    jsonResponse(['success' => true, 'id' => $newId, 'message' => 'Projeto criado com sucesso'], 201);
    break;

// -----------------------------------------------------------------
case 'PUT':
    if (!$id) jsonResponse(['error' => true, 'message' => 'ID obrigatório'], 422);

    $data = getJsonBody();
    $allowed = ['title','description','objective','inclusion_criteria',
                'exclusion_criteria','search_period_start','search_period_end','status'];

    $sets   = [];
    $values = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $sets[]   = "$field = ?";
            $values[] = $data[$field];
        }
    }

    if (empty($sets)) {
        jsonResponse(['error' => true, 'message' => 'Nenhum campo para atualizar'], 422);
    }

    $values[] = $id;
    $values[] = $userId;
    $pdo->prepare("UPDATE projects SET " . implode(', ', $sets) . " WHERE id = ? AND user_id = ?")->execute($values);

    // Se exclusion_criteria foi atualizado, sincroniza os motivos de exclusão da triagem
    if (array_key_exists('exclusion_criteria', $data)) {
        syncExclusionReasons($pdo, $id, $data['exclusion_criteria'] ?? '');
    }

    jsonResponse(['success' => true, 'message' => 'Projeto atualizado com sucesso']);
    break;

// -----------------------------------------------------------------
case 'DELETE':
    if (!$id) jsonResponse(['error' => true, 'message' => 'ID obrigatório'], 422);

    // Verifica existência e propriedade
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => true, 'message' => 'Projeto não encontrado'], 404);
    }

    $pdo->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    jsonResponse(['success' => true, 'message' => 'Projeto excluído com sucesso']);
    break;

// -----------------------------------------------------------------
default:
    jsonResponse(['error' => true, 'message' => 'Método não suportado'], 405);
}

// -----------------------------------------------------------------
// Sincroniza exclusion_reasons (fase screening) com exclusion_criteria do projeto
// -----------------------------------------------------------------
function syncExclusionReasons(PDO $pdo, int $projectId, ?string $criteriaRaw): void
{
    if (empty($criteriaRaw)) return;

    $criteria = json_decode($criteriaRaw, true);
    if (!is_array($criteria)) {
        $criteria = array_values(array_filter(array_map('trim', explode("\n", $criteriaRaw))));
    }
    $criteria = array_values(array_filter($criteria));
    if (empty($criteria)) return;

    // Remove motivos antigos da fase screening deste projeto
    $pdo->prepare("DELETE FROM exclusion_reasons WHERE project_id = ? AND phase = 'screening'")->execute([$projectId]);

    // Insere os critérios de exclusão do projeto como motivos de triagem
    $stmt = $pdo->prepare("
        INSERT INTO exclusion_reasons (project_id, phase, reason, is_default, sort_order)
        VALUES (?, 'screening', ?, 1, ?)
    ");
    foreach ($criteria as $i => $criterion) {
        $stmt->execute([$projectId, trim($criterion), $i]);
    }
}

// -----------------------------------------------------------------
// Função auxiliar: insere checklist PRISMA 2020 para o projeto
// -----------------------------------------------------------------
function insertPrismaChecklist(PDO $pdo, int $projectId): void
{
    $items = [
        [1,  'Título',          'Título', 'Identificar o relatório como revisão sistemática.'],
        [2,  'Resumo',          'Resumo estruturado', 'Fornecer um resumo estruturado incluindo: contexto; objetivos; fontes de evidência; métodos de elegibilidade e avaliação; síntese; limitações; conclusões.'],
        [3,  'Justificativa',   'Justificativa', 'Descrever a justificativa para a revisão no contexto do conhecimento existente.'],
        [4,  'Objetivos',       'Objetivos', 'Fornecer um enunciado explícito dos objetivos ou questões abordadas pela revisão.'],
        [5,  'Elegibilidade',   'Critérios de elegibilidade', 'Especificar os critérios de inclusão e exclusão e justificar as escolhas.'],
        [6,  'Fontes',         'Fontes de informação', 'Especificar todas as bases de dados, registros e outras fontes consultadas.'],
        [7,  'Busca',          'Estratégia de busca', 'Apresentar as estratégias de busca completas para todas as bases de dados.'],
        [8,  'Seleção',        'Processo de seleção', 'Especificar os métodos usados para decidir se um estudo é elegível.'],
        [9,  'Coleta',         'Processo de coleta de dados', 'Especificar o processo de coleta de dados dos estudos.'],
        [10, 'Itens de dados', 'Itens de dados', 'Listar e definir todos os desfechos e variáveis coletados.'],
        [11, 'Viés',           'Avaliação do risco de viés', 'Especificar os métodos usados para avaliar o risco de viés.'],
        [12, 'Síntese',        'Métodos de síntese', 'Descrever como se decidiu o que sintetizar e como.'],
        [13, 'Viés pub.',      'Viés de publicação', 'Descrever métodos usados para avaliar o risco de viés de publicação.'],
        [14, 'Certeza',        'Avaliação da certeza', 'Descrever os métodos usados para avaliar a certeza das evidências.'],
        [15, 'Seleção',        'Seleção de estudos', 'Descrever os resultados do processo de triagem e inclusão.'],
        [16, 'Características','Características dos estudos', 'Citar as características de cada estudo incluído.'],
        [17, 'Viés estudos',   'Risco de viés nos estudos', 'Apresentar avaliações do risco de viés para cada estudo.'],
        [18, 'Resultados',     'Resultados individuais', 'Apresentar dados de todos os estudos para todos os desfechos avaliados.'],
        [19, 'Síntese res.',   'Resultados da síntese', 'Apresentar os resultados de todas as sínteses realizadas.'],
        [20, 'Viés pub. res.', 'Resultados do viés de publicação', 'Apresentar avaliações do risco de viés de publicação.'],
        [21, 'Certeza res.',   'Certeza das evidências', 'Apresentar avaliações do grau de certeza das evidências.'],
        [22, 'Discussão',      'Discussão', 'Fornecer uma interpretação geral dos resultados no contexto das evidências.'],
        [23, 'Limitações',     'Limitações das evidências', 'Discutir limitações dos estudos e das evidências.'],
        [24, 'Limitações rev.','Limitações da revisão', 'Discutir limitações do processo de revisão.'],
        [25, 'Conclusões',     'Conclusões', 'Fornecer uma interpretação geral dos resultados e implicações.'],
        [26, 'Financiamento',  'Financiamento', 'Especificar as fontes de financiamento da revisão.'],
        [27, 'Conflitos',      'Conflitos de interesse', 'Declarar conflitos de interesse dos autores da revisão.'],
    ];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO prisma_checklist
            (project_id, item_number, section, item_text, completed)
        VALUES (?, ?, ?, ?, 0)
    ");
    foreach ($items as $item) {
        $stmt->execute([$projectId, $item[0], $item[1], $item[3]]);
    }
}
