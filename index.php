<?php
/**
 * PRISMA-SLR - index.php
 * Ponto de entrada / roteador principal
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Exige autenticação — redireciona para login.php se não logado
requireLogin();
$user = currentUser();

// ----- Roteamento -----
$validPages = [
    'dashboard', 'projects', 'import', 'articles',
    'duplicates', 'screening', 'eligibility', 'included',
    'prisma-flow', 'bibliometrics', 'export', 'checklist', 'references',
];

$page      = $_GET['page']       ?? 'dashboard';
$projectId = (int) ($_GET['project_id'] ?? 0);

if (!in_array($page, $validPages)) $page = 'dashboard';

// ----- Dados do projeto atual (só do usuário logado) -----
$currentProject = null;
if ($projectId) {
    try {
        $stmt = getDB()->prepare("SELECT id, title, status FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$projectId, $user['id']]);
        $currentProject = $stmt->fetch();
        if (!$currentProject) { $projectId = 0; }
    } catch (Throwable) { $projectId = 0; }
}

// ----- Lista de projetos do usuário logado -----
$projectList = [];
try {
    $stmt = getDB()->prepare("SELECT id, title FROM projects WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$user['id']]);
    $projectList = $stmt->fetchAll();
} catch (Throwable) {}

// ----- Labels das páginas -----
$pageLabels = [
    'dashboard'    => 'Dashboard',
    'projects'     => 'Projetos',
    'import'       => 'Importação',
    'articles'     => 'Artigos',
    'duplicates'   => 'Duplicatas',
    'screening'    => 'Triagem',
    'eligibility'  => 'Elegibilidade',
    'included'     => 'Incluídos',
    'prisma-flow'  => 'Diagrama PRISMA',
    'bibliometrics'=> 'Bibliometria',
    'export'       => 'Exportação',
    'checklist'    => 'Checklist PRISMA',
    'references'   => 'Referências',
];

$pageTitle = $pageLabels[$page] ?? 'PRISMA-SLR';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — PRISMA-SLR</title>
  <meta name="description" content="Sistema de Revisão Sistemática da Literatura baseado no protocolo PRISMA 2020">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Styles -->
  <link rel="stylesheet" href="assets/css/style.css">

  <!-- GTranslate (gtranslate.io) - tradução PT / EN / ES -->
  <style>
  .goog-te-banner-frame { display:none !important; }
  body { top: 0px !important; }
  .skiptranslate > iframe { display:none !important; }
  /* Motor do gtranslate posicionado fora da tela para inicializar corretamente */
  #gtranslate-motor {
    position: fixed;
    left: -9999px;
    top: -9999px;
    width: 1px;
    height: 1px;
    overflow: hidden;
    opacity: 0;
    pointer-events: none;
  }
  </style>
  <script>
  window.gtranslateSettings = {
    "default_language": "pt",
    "languages": ["pt","en","es"],
    "wrapper_selector": "#gtranslate-motor",
    "alt_flags": {"pt":"brazil"},
    "flag_size": 18,
    "flag_style": "3d",
  };
  </script>
  <script src="https://cdn.gtranslate.net/widgets/latest/flags.js" defer></script>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <!-- vis.js Network -->
  <script src="https://unpkg.com/vis-network@9.1.9/standalone/umd/vis-network.min.js"></script>
  <!-- WordCloud2.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/wordcloud2.js/1.2.2/wordcloud2.min.js"></script>
  <!-- FileSaver.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body>

<!-- Aplica tema salvo ANTES de renderizar para evitar flash -->
<script>
(function(){
  var t = localStorage.getItem('prisma-theme') || 'dark';
  if (t === 'light') document.body.classList.add('theme-light');
})();
</script>

<!-- ================================================
     SIDEBAR
     ================================================ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="assets/img/logo.svg" alt="Logo PRISMA-SLR">
    <div>
      <div class="sidebar-logo-text">PRISMA-SLR</div>
      <div class="sidebar-logo-sub" data-i18n="app.subtitle">Revisão Sistemática</div>
    </div>
  </div>

  <!-- Seletor de Projeto -->
  <div class="sidebar-project">
    <label for="project-select"><i class="fa fa-folder" style="margin-right:4px"></i> <span data-i18n="sidebar.active_project">Projeto Ativo</span></label>
    <select id="project-select" onchange="switchProject(this.value)">
      <option value="0" <?= $projectId === 0 ? 'selected' : '' ?>>— <span data-i18n="sidebar.select_project">Selecione um projeto</span> —</option>
      <?php foreach ($projectList as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $p['id'] === $projectId ? 'selected' : '' ?>>
          <?= htmlspecialchars($p['title']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Navegação -->
  <nav class="sidebar-nav">
    <div class="nav-section-title" data-i18n="nav.sec_overview">Visão Geral</div>
    <?= navItem('dashboard', $page, $projectId, 'fa-house',          'Dashboard',       'nav.dashboard') ?>
    <?= navItem('projects',  $page, $projectId, 'fa-folder-open',    'Projetos',        'nav.projects') ?>

    <div class="nav-section-title" style="margin-top:6px" data-i18n="nav.sec_prisma">Processo PRISMA</div>
    <?= navItem('import',       $page, $projectId, 'fa-file-import',      'Importação',     'nav.import') ?>
    <?= navItem('duplicates',   $page, $projectId, 'fa-copy',             'Duplicatas',     'nav.duplicates') ?>
    <?= navItem('screening',    $page, $projectId, 'fa-magnifying-glass', 'Triagem',        'nav.screening') ?>
    <?= navItem('eligibility',  $page, $projectId, 'fa-check-double',     'Elegibilidade',  'nav.eligibility') ?>
    <?= navItem('included',     $page, $projectId, 'fa-circle-check',     'Incluídos',      'nav.included') ?>

    <div class="nav-section-title" style="margin-top:6px" data-i18n="nav.sec_analysis">Análise</div>
    <?= navItem('bibliometrics', $page, $projectId, 'fa-chart-bar',      'Bibliometria',       'nav.bibliometrics') ?>
    <?= navItem('prisma-flow',   $page, $projectId, 'fa-diagram-project','Diagrama PRISMA',    'nav.prisma_flow') ?>
    <?= navItem('checklist',     $page, $projectId, 'fa-list-check',     'Checklist PRISMA',   'nav.checklist') ?>

    <div class="nav-section-title" style="margin-top:6px" data-i18n="nav.sec_data">Dados</div>
    <?= navItem('articles',    $page, $projectId, 'fa-book',        'Todos os Artigos', 'nav.articles') ?>
    <?= navItem('references',  $page, $projectId, 'fa-quote-right', 'Referências',      'nav.references') ?>
    <?= navItem('export',      $page, $projectId, 'fa-upload',      'Exportação',       'nav.export') ?>
  </nav>

  <!-- Usuário logado -->
  <div class="sidebar-footer">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <?php if (!empty($user['picture'])): ?>
        <img src="<?= htmlspecialchars($user['picture']) ?>" alt="Foto"
             style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);flex-shrink:0">
      <?php else: ?>
        <div style="width:32px;height:32px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="fa fa-user" style="color:#fff;font-size:14px"></i>
        </div>
      <?php endif; ?>
      <div style="min-width:0;flex:1">
        <div style="font-size:.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          <?= htmlspecialchars($user['name']) ?>
        </div>
        <div style="font-size:.68rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          <?= htmlspecialchars($user['email']) ?>
        </div>
      </div>
    </div>
    <a href="auth/logout.php" style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--text-muted);text-decoration:none;padding:5px 8px;border-radius:6px;transition:background .15s,color .15s"
       onmouseover="this.style.background='rgba(239,68,68,.12)';this.style.color='#fca5a5'"
       onmouseout="this.style.background='';this.style.color='var(--text-muted)'">
      <i class="fa fa-right-from-bracket"></i> Sair
    </a>
    <div style="margin-top:8px;border-top:1px solid var(--border);padding-top:8px;font-size:0.68rem;color:var(--text-muted)">
      PRISMA-SLR v1.0 · PRISMA 2020
    </div>
  </div>
</aside>

<!-- ================================================
     CONTEÚDO PRINCIPAL
     ================================================ -->
<div class="main">
  <!-- Header -->
  <header class="header">
    <button class="btn btn-ghost btn-sm" id="sidebar-toggle" style="display:none"
            onclick="document.getElementById('sidebar').classList.toggle('open')">
      <i class="fa fa-bars"></i>
    </button>

    <div class="header-title">
      <?php if ($currentProject): ?>
        <span class="text-muted text-sm"><i class="fa fa-folder" style="color:var(--primary)"></i>
          <?= htmlspecialchars($currentProject['title']) ?></span>
        <span style="color:var(--text-muted);margin:0 8px">›</span>
      <?php endif; ?>
      <span data-i18n="page.<?= $page ?>"><?= htmlspecialchars($pageTitle) ?></span>
    </div>

    <div class="header-actions">
      <?php if ($currentProject): ?>
        <span class="badge badge-<?= $currentProject['status'] ?>"><?= $currentProject['status'] ?></span>
      <?php endif; ?>

      <!-- Seletor de idioma PRISMA (PT / EN / ES) -->
      <div id="prisma-lang-switcher" style="display:inline-flex;gap:2px;align-items:center;background:var(--bg-card);border:1px solid var(--border);border-radius:7px;padding:2px 3px" title="Idioma do diagrama PRISMA">
        <button onclick="setPrismaLang('pt')" id="lang-btn-pt" class="lang-btn active-lang" style="border:none;background:none;cursor:pointer;padding:2px 6px;border-radius:5px;font-size:11px;font-weight:600;color:var(--text-primary)">🇧🇷 PT</button>
        <button onclick="setPrismaLang('en')" id="lang-btn-en" class="lang-btn" style="border:none;background:none;cursor:pointer;padding:2px 6px;border-radius:5px;font-size:11px;font-weight:600;color:var(--text-muted)">🇺🇸 EN</button>
        <button onclick="setPrismaLang('es')" id="lang-btn-es" class="lang-btn" style="border:none;background:none;cursor:pointer;padding:2px 6px;border-radius:5px;font-size:11px;font-weight:600;color:var(--text-muted)">🇪🇸 ES</button>
      </div>

      <a href="?page=projects" class="btn btn-ghost btn-sm" title="Gerenciar Projetos">
        <i class="fa fa-folder-open"></i>
      </a>

      <!-- Botão de tema claro/escuro -->
      <button class="btn btn-ghost btn-sm" id="theme-toggle" onclick="toggleTheme()"
              title="Alternar tema claro/escuro" style="min-width:36px;justify-content:center">
        <i class="fa fa-moon" id="theme-icon"></i>
      </button>
    </div>
  </header>

  <!-- Conteúdo da Página -->
  <main class="content">
    <?php
    // Carrega a página
    $pageFile = __DIR__ . '/pages/' . $page . '.php';
    if (file_exists($pageFile)) {
        include $pageFile;
    } else {
        echo '<div class="empty-state"><i class="fa fa-triangle-exclamation"></i>'
           . '<p>Página não encontrada: <code>' . htmlspecialchars($page) . '</code></p></div>';
    }
    ?>
  </main>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- Motor gtranslate.io (fora da tela, necessário para inicializar) -->
<div id="gtranslate-motor"></div>


<!-- ================================================
     SCRIPTS
     ================================================ -->
<!-- Configuração global JS -->
<script>
const APP = {
  projectId:   <?= $projectId ?>,
  currentPage: '<?= $page ?>',
  baseUrl:     '<?= rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
               . '://' . $_SERVER['HTTP_HOST']
               . dirname($_SERVER['PHP_SELF']), '/') ?>',
};
</script>

<script src="assets/js/app.js"></script>

<!-- Scripts específicos por página -->
<?php if ($page === 'dashboard'): ?>
<script src="assets/js/dashboard.js"></script>
<?php elseif ($page === 'import'): ?>
<script src="assets/js/import.js"></script>
<?php elseif ($page === 'screening' || $page === 'eligibility'): ?>
<script src="assets/js/screening.js"></script>
<?php elseif ($page === 'bibliometrics'): ?>
<script src="assets/js/bibliometrics.js"></script>
<?php elseif ($page === 'prisma-flow'): ?>
<script src="assets/js/prisma-flow.js"></script>
<?php endif; ?>

<script>
// ----- Tema claro / escuro -----
function applyTheme(theme) {
  if (theme === 'light') {
    document.body.classList.add('theme-light');
    document.getElementById('theme-icon').className = 'fa-solid fa-sun';
    document.getElementById('theme-toggle').title   = 'Mudar para tema escuro';
  } else {
    document.body.classList.remove('theme-light');
    document.getElementById('theme-icon').className = 'fa-solid fa-moon';
    document.getElementById('theme-toggle').title   = 'Mudar para tema claro';
  }
  localStorage.setItem('prisma-theme', theme);
}

function toggleTheme() {
  var current = localStorage.getItem('prisma-theme') || 'dark';
  applyTheme(current === 'dark' ? 'light' : 'dark');
}

// ----- Idioma PRISMA (PT / EN / ES) -----
window.PRISMA_LANG = localStorage.getItem('prisma-lang') || 'pt';

function setPrismaLang(lang, _retry) {
  window.PRISMA_LANG = lang;
  localStorage.setItem('prisma-lang', lang);

  // Atualiza estilo dos botões
  ['pt','en','es'].forEach(function(l) {
    var btn = document.getElementById('lang-btn-' + l);
    if (!btn) return;
    if (l === lang) {
      btn.style.background = 'var(--primary)';
      btn.style.color      = '#fff';
    } else {
      btn.style.color      = 'var(--text-muted)';
      btn.style.background = 'none';
    }
  });

  // Traduz toda a página via gtranslate.io (funciona online)
  if (typeof doGTranslate === 'function') {
    doGTranslate('pt|' + lang);
  } else if (!_retry || _retry < 20) {
    // flags.js carregando com defer — aguarda e tenta de novo
    setTimeout(function(){ setPrismaLang(lang, (_retry||0) + 1); }, 300);
  }

  // Redesenha o diagrama SVG na página prisma-flow
  document.dispatchEvent(new CustomEvent('prismaLangChanged', { detail: { lang } }));
}

// Sincroniza tema e idioma ao carregar
document.addEventListener('DOMContentLoaded', function() {
  var saved = localStorage.getItem('prisma-theme') || 'dark';
  applyTheme(saved);

  var savedLang = localStorage.getItem('prisma-lang') || 'pt';
  window.PRISMA_LANG = savedLang;

  // Botões: aplica visual imediatamente
  ['pt','en','es'].forEach(function(l) {
    var btn = document.getElementById('lang-btn-' + l);
    if (!btn) return;
    if (l === savedLang) { btn.style.background = 'var(--primary)'; btn.style.color = '#fff'; }
    else                 { btn.style.color = 'var(--text-muted)'; btn.style.background = 'none'; }
  });

  // Aplica tradução da página após gtranslate carregar
  if (savedLang !== 'pt') {
    setPrismaLang(savedLang, 0);
  }

  if (typeof initPage === 'function') initPage();
});
</script>

</body>
</html>

<?php
// ----- Função helper: gera item de navegação -----
function navItem(string $pg, string $current, int $projectId, string $icon, string $label, string $i18nKey = ''): string
{
    $active   = $pg === $current ? ' active' : '';
    $href     = "?page={$pg}" . ($projectId ? "&project_id={$projectId}" : '');
    $iconHtml = "<i class=\"fa {$icon}\"></i>";
    $i18nAttr = $i18nKey ? " data-i18n=\"{$i18nKey}\"" : '';
    return "<a href=\"{$href}\" class=\"nav-item{$active}\"{$i18nAttr}>{$iconHtml} {$label}</a>\n";
}
?>
