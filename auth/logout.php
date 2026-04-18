<?php
/**
 * PRISMA-SLR — auth/logout.php
 * Encerra a sessão do usuário.
 */
require_once __DIR__ . '/../config/auth.php';

startSecureSession();

// Limpa todos os dados da sessão
$_SESSION = [];

// Apaga o cookie de sessão
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'],   $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

header('Location: /prisma-slr/login.php');
exit;
