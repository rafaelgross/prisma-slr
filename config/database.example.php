<?php
/**
 * PRISMA-SLR - Configuração do Banco de Dados
 * MySQL via PDO - MAMP (root/root, porta 8889)
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');        // use 8889 para MAMP no macOS
define('DB_NAME', 'prisma_slr');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => 'Falha na conexão com o banco de dados: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    return $pdo;
}

/**
 * Retorna JSON e encerra a execução
 */
function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Lê o body JSON da requisição
 */
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Inicializa o banco de dados executando o schema.sql
 */
function initializeDatabase(): array {
    $pdo = getDB();
    $schemaFile = __DIR__ . '/../sql/schema.sql';

    if (!file_exists($schemaFile)) {
        return ['error' => true, 'message' => 'Arquivo schema.sql não encontrado'];
    }

    try {
        $sql = file_get_contents($schemaFile);
        // Executa statement por statement
        $pdo->exec($sql);
        return ['success' => true, 'message' => 'Banco de dados inicializado com sucesso'];
    } catch (PDOException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    }
}
