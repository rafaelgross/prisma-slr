<?php
/**
 * PRISMA-SLR — auth/callback.php
 * Recebe o código de autorização do Google, troca pelo token de acesso,
 * busca os dados do usuário e cria/atualiza o registro no banco.
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

// ─── 1. Verifica se houve erro retornado pelo Google ──────────────────────────
if (isset($_GET['error'])) {
    $_SESSION['login_error'] = 'Acesso negado ou cancelado pelo usuário.';
    header('Location: /prisma-slr/login.php');
    exit;
}

// ─── 2. Valida o state CSRF ───────────────────────────────────────────────────
$state = $_GET['state'] ?? '';
if (!validateOAuthState($state)) {
    $_SESSION['login_error'] = 'Erro de segurança (state inválido). Tente novamente.';
    header('Location: /prisma-slr/login.php');
    exit;
}

// ─── 3. Troca o código pelo token de acesso ───────────────────────────────────
$code = $_GET['code'] ?? '';
if (!$code) {
    $_SESSION['login_error'] = 'Código de autorização ausente. Tente novamente.';
    header('Location: /prisma-slr/login.php');
    exit;
}

$tokenData = exchangeCodeForToken($code);
if (!$tokenData) {
    $_SESSION['login_error'] = 'Não foi possível obter o token de acesso. Verifique as credenciais OAuth.';
    header('Location: /prisma-slr/login.php');
    exit;
}

// ─── 4. Busca dados do perfil do usuário ──────────────────────────────────────
$userInfo = fetchGoogleUserInfo($tokenData['access_token']);
if (!$userInfo) {
    $_SESSION['login_error'] = 'Não foi possível obter os dados do perfil Google.';
    header('Location: /prisma-slr/login.php');
    exit;
}

$googleId = $userInfo['sub']      ?? '';
$email    = $userInfo['email']    ?? '';
$name     = $userInfo['name']     ?? $email;
$picture  = $userInfo['picture']  ?? '';

if (!$googleId || !$email) {
    $_SESSION['login_error'] = 'Dados do perfil Google incompletos.';
    header('Location: /prisma-slr/login.php');
    exit;
}

// ─── 5. Cria ou atualiza o usuário no banco de dados ─────────────────────────
try {
    $pdo = getDB();

    // Upsert: insere se não existe, atualiza last_login se já existe
    $stmt = $pdo->prepare("
        INSERT INTO users (google_id, email, name, picture, role)
        VALUES (:google_id, :email, :name, :picture, 'user')
        ON DUPLICATE KEY UPDATE
            name       = VALUES(name),
            picture    = VALUES(picture),
            last_login = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':google_id' => $googleId,
        ':email'     => $email,
        ':name'      => $name,
        ':picture'   => $picture,
    ]);

    // Busca o usuário completo (incluindo role e id)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if (!$user) throw new RuntimeException('Usuário não encontrado após upsert.');

    // Auto-atribuição de projetos legados (sem user_id) ao primeiro usuário que fizer login
    // Garante que projetos criados antes do sistema de auth pertençam ao primeiro pesquisador
    $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($totalUsers === 1) {
        $pdo->prepare("UPDATE projects SET user_id = ? WHERE user_id IS NULL")
            ->execute([$user['id']]);
    }

} catch (Throwable $e) {
    $_SESSION['login_error'] = 'Erro ao salvar usuário no banco: ' . $e->getMessage();
    header('Location: /prisma-slr/login.php');
    exit;
}

// ─── 6. Cria a sessão autenticada ────────────────────────────────────────────
session_regenerate_id(true); // previne session fixation

$_SESSION['user_id']      = $user['id'];
$_SESSION['user_email']   = $user['email'];
$_SESSION['user_name']    = $user['name'];
$_SESSION['user_picture'] = $user['picture'];
$_SESSION['user_role']    = $user['role'];
$_SESSION['logged_in_at'] = time();

// ─── 7. Redireciona para a página solicitada originalmente ───────────────────
$redirect = $_SESSION['redirect_after_login'] ?? '/prisma-slr/';
unset($_SESSION['redirect_after_login']);

header('Location: ' . $redirect);
exit;
