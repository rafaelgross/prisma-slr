<?php
/**
 * PRISMA-SLR - Dashboard
 * Visão geral do projeto ativo
 */
$pdb = getDB();
$hasProject = $projectId > 0;

$stats = ['total_articles' => 0, 'total_duplicates' => 0, 'screened' => 0,
          'eligible' => 0, 'included' => 0, 'excluded' => 0];

if ($hasProject) {
    $stmt = $pdb->prepare("
        SELECT
            COUNT(*) AS total_articles,
            SUM(is_duplicate) AS total_duplicates,
            SUM(status = 'screened') AS screened,
            SUM(status = 'eligible') AS eligible,
            SUM(status = 'included') AS included,
            SUM(status = 'excluded') AS excluded
        FROM articles WHERE project_id = ?
    ");
    $stmt->execute([$projectId]);
    $stats = $stmt->fetch() ?: $stats;
}
?>

<!-- Se não há projeto selecionado, mostra boas-vindas -->
<?php if (!$hasProject): ?>
<div style="display:flex;align-items:center;justify-content:center;min-height:60vh">
  <div style="text-align:center;max-width:520px">
    <img src="assets/img/logo.svg" alt="Logo" style="width:72px;height:72px;margin:0 auto 24px">
    <h1 style="font-size:2rem;font-weight:800;color:var(--primary);margin-bottom:10px">
      Bem-vindo ao PRISMA-SLR
    </h1>
    <p style="color:var(--text-secondary);margin-bottom:28px;font-size:1rem;line-height:1.7">
      Sistema Web para Revisão Sistemática da Literatura baseado no protocolo <strong style="color:var(--text-primary)">PRISMA 2020</strong>.
      Crie ou selecione um projeto para começar.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="?page=projects" class="btn btn-primary btn-lg">
        <i class="fa fa-plus"></i> Criar Projeto
      </a>
      <?php if (!empty($projectList)): ?>
      <a href="?page=projects" class="btn btn-outline btn-lg">
        <i class="fa fa-folder-open"></i> Ver Projetos
      </a>
      <?php endif; ?>
    </div>

    <div style="margin-top:48px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
      <?php
      $features = [
        ['fa-file-import','Importação .bib','Scopus, Web of Science e outras bases.'],
        ['fa-copy','Deduplicação','Detecta e remove duplicatas automaticamente.'],
        ['fa-diagram-project','PRISMA 2020','Diagrama de fluxo gerado automaticamente.'],
      ];
      foreach ($features as $f): ?>
      <div class="card" style="text-align:center;padding:20px 16px">
        <i class="fa <?= $f[0] ?>" style="font-size:1.4rem;color:var(--primary);margin-bottom:10px;display:block"></i>
        <div style="font-weight:600;margin-bottom:6px;font-size:0.875rem"><?= $f[1] ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted)"><?= $f[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php else: ?>

<!-- ====== Dashboard com projeto ====== -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-house"></i> Dashboard</h1>
    <p class="page-subtitle"><?= htmlspecialchars($currentProject['title']) ?></p>
  </div>
  <div style="display:flex;gap:10px">
    <a href="?page=import&project_id=<?= $projectId ?>" class="btn btn-primary">
      <i class="fa fa-file-import"></i> Importar .bib
    </a>
    <a href="?page=prisma-flow&project_id=<?= $projectId ?>" class="btn btn-outline">
      <i class="fa fa-diagram-project"></i> PRISMA Flow
    </a>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"><i class="fa fa-book"></i></div>
    <div class="stat-value" id="stat-total"><?= number_format((int)$stats['total_articles']) ?></div>
    <div class="stat-label">Total identificado</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon"><i class="fa fa-copy"></i></div>
    <div class="stat-value" id="stat-dup"><?= number_format((int)$stats['total_duplicates']) ?></div>
    <div class="stat-label">Duplicatas</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon"><i class="fa fa-magnifying-glass"></i></div>
    <div class="stat-value" id="stat-screened"><?= number_format((int)$stats['screened']) ?></div>
    <div class="stat-label">Triados</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa fa-check-double"></i></div>
    <div class="stat-value"><?= number_format((int)$stats['eligible']) ?></div>
    <div class="stat-label">Elegíveis</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="fa fa-circle-check"></i></div>
    <div class="stat-value"><?= number_format((int)$stats['included']) ?></div>
    <div class="stat-label">Incluídos</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon"><i class="fa fa-ban"></i></div>
    <div class="stat-value"><?= number_format((int)$stats['excluded']) ?></div>
    <div class="stat-label">Excluídos</div>
  </div>
</div>

<!-- Fase atual e ações rápidas -->
<div class="grid-2" style="margin-bottom:20px">
  <!-- Progresso do processo -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-timeline"></i> Progresso do Processo</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px">
      <?php
      $total = max(1, (int)$stats['total_articles']);
      $nonDup = max(0, $total - (int)$stats['total_duplicates']);
      $phases = [
        ['Identificação',  $total,               $total,                  'var(--primary)',        'fa-book'],
        ['Deduplicação',   $nonDup,               $total,                  'var(--accent-red)',     'fa-copy'],
        ['Triagem',        (int)$stats['screened'],(int)$nonDup,           'var(--accent-yellow)',  'fa-magnifying-glass'],
        ['Elegibilidade',  (int)$stats['eligible'],(int)$stats['screened'],'var(--primary)',        'fa-check-double'],
        ['Incluídos',      (int)$stats['included'],(int)$stats['eligible'],'var(--accent-green)',   'fa-circle-check'],
      ];
      foreach ($phases as [$label, $n, $d, $color, $icon]):
        $pct = $d > 0 ? min(100, round($n / $d * 100)) : 0;
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:0.8rem">
          <span><i class="fa <?= $icon ?>" style="color:<?= $color ?>;margin-right:6px"></i><?= $label ?></span>
          <span style="color:var(--text-muted)"><?= $n ?> / <?= $d ?></span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Ações rápidas -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-bolt"></i> Ações Rápidas</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php
      $actions = [
        ['import',       'fa-file-import',       'btn-outline',  'Importar arquivo .bib'],
        ['duplicates',   'fa-copy',               'btn-outline',  'Gerenciar duplicatas'],
        ['screening',    'fa-magnifying-glass',   'btn-outline',  'Continuar triagem'],
        ['eligibility',  'fa-check-double',       'btn-outline',  'Avaliar elegibilidade'],
        ['bibliometrics','fa-chart-bar',           'btn-outline',  'Análise bibliométrica'],
        ['prisma-flow',  'fa-diagram-project',    'btn-outline',  'Ver diagrama PRISMA'],
        ['export',       'fa-upload',             'btn-outline',  'Exportar dados'],
        ['checklist',    'fa-list-check',          'btn-outline',  'Checklist PRISMA 2020'],
      ];
      foreach ($actions as [$pg, $icon, $cls, $label]): ?>
      <a href="?page=<?= $pg ?>&project_id=<?= $projectId ?>"
         class="btn <?= $cls ?>" style="justify-content:flex-start">
        <i class="fa <?= $icon ?>"></i> <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Fontes de busca -->
<div class="card" id="dashboard-sources">
  <div class="card-header">
    <div class="card-title"><i class="fa fa-database"></i> Fontes de Busca</div>
    <a href="?page=import&project_id=<?= $projectId ?>" class="btn btn-ghost btn-sm">
      <i class="fa fa-plus"></i> Adicionar
    </a>
  </div>
  <div id="sources-table-container">
    <div class="text-center" style="padding:30px"><div class="spinner"></div></div>
  </div>
</div>

<script>
// Carrega fontes do projeto no dashboard
(async () => {
  const container = document.getElementById('sources-table-container');
  try {
    const r = await fetch(`api/import.php?project_id=<?= $projectId ?>`);
    const sources = await r.json();
    if (!sources.length) {
      container.innerHTML = '<div class="empty-state"><i class="fa fa-inbox"></i><p>Nenhum arquivo importado ainda.</p><small>Use o botão "Importar .bib" para começar.</small></div>';
      return;
    }
    let html = '<div class="table-wrapper"><table><thead><tr>'
      + '<th>Fonte</th><th>Tipo</th><th>Artigos</th><th>Data da busca</th>'
      + '<th>Importado em</th></tr></thead><tbody>';
    sources.forEach(s => {
      html += `<tr>
        <td><strong>${escHtml(s.name)}</strong><br><small class="text-muted">${escHtml(s.file_name || '')}</small></td>
        <td><span class="badge badge-${s.source_type}">${s.source_type.toUpperCase()}</span></td>
        <td><strong>${s.article_count}</strong></td>
        <td>${s.search_date || '—'}</td>
        <td>${formatDate(s.imported_at)}</td>
      </tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
  } catch(e) {
    container.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i>Erro ao carregar fontes.</div>';
  }
})();
</script>

<?php endif; ?>
