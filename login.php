<?php
/**
 * PRISMA-SLR — login.php
 * Página de login com Google OAuth 2.0
 */
require_once __DIR__ . '/config/auth.php';

// Se já está logado, redireciona para o app
if (isLoggedIn()) {
    header('Location: /prisma-slr/');
    exit;
}

startSecureSession();
$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — PRISMA-SLR</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:        #0f1117;
      --bg-card:   #1a1d27;
      --border:    #2a2d3a;
      --primary:   #6c63ff;
      --primary-h: #5a52e0;
      --text:      #e8eaf0;
      --text-muted:#8b8fa8;
      --radius:    14px;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-image:
        radial-gradient(ellipse 800px 600px at 30% 20%, rgba(108,99,255,.12) 0%, transparent 60%),
        radial-gradient(ellipse 600px 400px at 70% 80%, rgba(108,99,255,.08) 0%, transparent 60%);
    }

    .login-box {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 48px 40px;
      width: 100%;
      max-width: 420px;
      text-align: center;
      box-shadow: 0 24px 80px rgba(0,0,0,.4);
    }

    .logo {
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, var(--primary), #a78bfa);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      font-size: 24px;
      color: #fff;
    }

    h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: 6px; }
    .subtitle { color: var(--text-muted); font-size: .875rem; margin-bottom: 36px; line-height: 1.5; }

    .btn-google {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      width: 100%;
      padding: 13px 20px;
      background: #fff;
      color: #3c4043;
      border: none;
      border-radius: 10px;
      font-size: .95rem;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      text-decoration: none;
      transition: background .15s, box-shadow .15s, transform .1s;
      box-shadow: 0 2px 8px rgba(0,0,0,.25);
    }
    .btn-google:hover {
      background: #f8f9fa;
      box-shadow: 0 4px 16px rgba(0,0,0,.35);
      transform: translateY(-1px);
    }
    .btn-google:active { transform: translateY(0); }

    .google-icon {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
    }

    .divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 28px 0;
    }

    .features {
      display: flex;
      flex-direction: column;
      gap: 10px;
      text-align: left;
    }
    .feature-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: .82rem;
      color: var(--text-muted);
    }
    .feature-item i {
      color: var(--primary);
      width: 16px;
      text-align: center;
      flex-shrink: 0;
    }

    .error-msg {
      background: rgba(239,68,68,.1);
      border: 1px solid rgba(239,68,68,.3);
      color: #fca5a5;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: .85rem;
      margin-bottom: 20px;
    }

    .footer-note {
      margin-top: 28px;
      font-size: .75rem;
      color: var(--text-muted);
      line-height: 1.6;
    }
  </style>
</head>
<body>

<div class="login-box">
  <div class="logo">
    <i class="fa fa-diagram-project"></i>
  </div>
  <h1>PRISMA-SLR</h1>
  <p class="subtitle">Sistema de Revisão Sistemática da Literatura<br>baseado no protocolo PRISMA 2020</p>

  <?php if ($error): ?>
    <div class="error-msg">
      <i class="fa fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <a href="auth/google.php" class="btn-google">
    <!-- Google "G" SVG oficial -->
    <svg class="google-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
      <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
      <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
      <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
    </svg>
    Entrar com Google
  </a>

  <hr class="divider">

  <div class="features">
    <div class="feature-item">
      <i class="fa fa-shield-halved"></i>
      <span>Login seguro via conta Google — sem senha adicional</span>
    </div>
    <div class="feature-item">
      <i class="fa fa-users"></i>
      <span>Múltiplos pesquisadores podem acessar o mesmo projeto</span>
    </div>
    <div class="feature-item">
      <i class="fa fa-lock"></i>
      <span>Dados protegidos e sessão com expiração automática</span>
    </div>
  </div>

  <p class="footer-note">
    Ao entrar, você concorda que seus dados de perfil do Google<br>
    (nome, e-mail e foto) serão armazenados neste sistema.
  </p>
</div>

</body>
</html>
