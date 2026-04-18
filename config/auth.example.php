<?php
/**
 * PRISMA-SLR — config/auth.php
 * Configuração do Google OAuth 2.0 e helpers de sessão.
 *
 * !! ANTES DE USAR !!
 * 1. Acesse https://console.cloud.google.com/
 * 2. Crie um projeto (ou selecione um existente)
 * 3. Menu "APIs e serviços" → "Credenciais" → "+ Criar Credenciais" → "ID do cliente OAuth"
 * 4. Tipo de aplicativo: "Aplicativo Web"
 * 5. Adicione a URI de redirecionamento autorizada:
 *      http://localhost/prisma-slr/auth/callback.php    (local)
 *      https://seudominio.com/prisma-slr/auth/callback.php  (produção)
 * 6. Copie o "ID do cliente" e o "Segredo do cliente" para as constantes abaixo.
 */

// ─── Credenciais do Google OAuth ──────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     'COLE_SEU_CLIENT_ID_AQUI.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'COLE_SEU_CLIENT_SECRET_AQUI');

// URI de redirecionamento — deve ser idêntica à cadastrada no Google Cloud Console
define('GOOGLE_REDIRECT_URI',  'http://localhost/prisma-slr/auth/callback.php');

// Scopes solicitados (email + perfil básico)
define('GOOGLE_SCOPES', 'openid email profile');

// URLs do Google OAuth 2.0
define('GOOGLE_AUTH_URL',  'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USER_URL',  'https://www.googleapis.com/oauth2/v3/userinfo');

// Duração da sessão: 8 horas
define('SESSION_LIFETIME', 8 * 3600);

// ─── Inicializa sessão segura ──────────────────────────────────────────────────
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false, // mude para true em HTTPS (produção)
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ─── Verifica se o usuário está autenticado ────────────────────────────────────
function isLoggedIn(): bool
{
    startSecureSession();
    if (empty($_SESSION['user_id']) || empty($_SESSION['logged_in_at'])) return false;
    // Expira sessão após SESSION_LIFETIME segundos
    if (time() - $_SESSION['logged_in_at'] > SESSION_LIFETIME) {
        session_destroy();
        return false;
    }
    return true;
}

// ─── Retorna dados do usuário logado ──────────────────────────────────────────
function currentUser(): ?array
{
    if (!isLoggedIn()) return null;
    return [
        'id'      => $_SESSION['user_id'],
        'email'   => $_SESSION['user_email']   ?? '',
        'name'    => $_SESSION['user_name']    ?? '',
        'picture' => $_SESSION['user_picture'] ?? '',
        'role'    => $_SESSION['user_role']    ?? 'user',
    ];
}

// ─── Redireciona para login se não autenticado ────────────────────────────────
function requireLogin(): void
{
    if (!isLoggedIn()) {
        // Salva URL desejada para redirecionar após login
        startSecureSession();
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /prisma-slr/login.php');
        exit;
    }
}

// ─── Gera state CSRF para o OAuth ────────────────────────────────────────────
function generateOAuthState(): string
{
    startSecureSession();
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    return $state;
}

// ─── Valida state CSRF retornado pelo Google ─────────────────────────────────
function validateOAuthState(string $state): bool
{
    startSecureSession();
    $stored = $_SESSION['oauth_state'] ?? '';
    unset($_SESSION['oauth_state']);
    return $stored !== '' && hash_equals($stored, $state);
}

// ─── Troca código de autorização por token de acesso via cURL ────────────────
function exchangeCodeForToken(string $code): ?array
{
    $postData = http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) return null;
    $data = json_decode($response, true);
    return isset($data['access_token']) ? $data : null;
}

// ─── Busca dados do usuário com o access_token ───────────────────────────────
function fetchGoogleUserInfo(string $accessToken): ?array
{
    $ch = curl_init(GOOGLE_USER_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) return null;
    $data = json_decode($response, true);
    return isset($data['email']) ? $data : null;
}
