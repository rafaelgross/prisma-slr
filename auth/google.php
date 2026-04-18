<?php
/**
 * PRISMA-SLR — auth/google.php
 * Inicia o fluxo de autenticação OAuth 2.0 com o Google.
 * Redireciona o usuário para a tela de consentimento do Google.
 */
require_once __DIR__ . '/../config/auth.php';

// Gera e salva o state CSRF
$state = generateOAuthState();

// Monta a URL de autorização
$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => GOOGLE_SCOPES,
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',  // permite trocar de conta
]);

header('Location: ' . GOOGLE_AUTH_URL . '?' . $params);
exit;
