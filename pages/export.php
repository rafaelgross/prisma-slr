<?php
/**
 * PRISMA-SLR - Exportação
 */
if (!$projectId): ?>
<div class="empty-state"><i class="fa fa-triangle-exclamation"></i>
  <p>Selecione um projeto para exportar dados.</p></div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-file-export"></i> Exportar Dados</h1>
    <p class="page-subtitle">Exporte artigos em diferentes formatos e escopos.</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px">

  <!-- Todos os artigos -->
  <div class="card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="width:42px;height:42px;border-radius:10px;background:rgba(0,212,255,0.1);display:flex;align-items:center;justify-content:center">
        <i class="fa fa-database" style="color:var(--primary)"></i>
      </div>
      <div>
        <h3 style="font-size:0.95rem;font-weight:600">Todos os Artigos</h3>
        <p class="text-muted text-xs">Todos os registros importados (exceto duplicatas confirmadas)</p>
      </div>
    </div>
    <span class="badge badge-pending" id="count-all">— artigos</span>
    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
      <button class="btn btn-outline btn-sm" onclick="doExport('csv','all')">
        <i class="fa fa-file-csv"></i> CSV
      </button>
      <button class="btn btn-outline btn-sm" onclick="doExport('bibtex','all')">
        <i class="fa fa-file-code"></i> BibTeX
      </button>
      <button class="btn btn-outline btn-sm" onclick="doExport('json','all')">
        <i class="fa fa-code"></i> JSON
      </button>
    </div>
  </div>

  <!-- Triados (passaram na triagem) -->
  <div class="card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="width:42px;height:42px;border-radius:10px;background:rgba(116,192,252,0.1);display:flex;align-items:center;justify-content:center">
        <i class="fa fa-magnifying-glass" style="color:#74c0fc"></i>
      </div>
      <div>
        <h3 style="font-size:0.95rem;font-weight:600">Aprovados na Triagem</h3>
        <p class="text-muted text-xs">Artigos que passaram para a fase de elegibilidade</p>
      </div>
    </div>
    <span class="badge badge-screened" id="count-screened">— artigos</span>
    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
      <button class="btn btn-outline btn-sm" onclick="doExport('csv','screened')">
        <i class="fa fa-file-csv"></i> CSV
      </button>
      <button class="btn btn-outline btn-sm" onclick="doExport('bibtex','screened')">
        <i class="fa fa-file-code"></i> BibTeX
      </button>
      <button class="btn btn-outline btn-sm" onclick="doExport('json','screened')">
        <i class="fa fa-code"></i> JSON
      </button>
    </div>
  </div>

  <!-- Artigos incluídos -->
  <div class="card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="width:42px;height:42px;border-radius:10px;background:rgba(0,229,160,0.1);display:flex;align-items:center;justify-content:center">
        <i class="fa fa-star" style="color:var(--accent-green)"></i>
      </div>
      <div>
        <h3 style="font-size:0.95rem;font-weight:600">Artigos Incluídos</h3>
        <p class="text-muted text-xs">Corpus final da revisão sistemática</p>
      </div>
    </div>
    <span class="badge badge-included" id="count-included">— artigos</span>
    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
      <button class="btn btn-success btn-sm" onclick="doExport('csv','included')">
        <i class="fa fa-file-csv"></i> CSV
      </button>
      <button class="btn btn-success btn-sm" onclick="doExport('bibtex','included')">
        <i class="fa fa-file-code"></i> BibTeX
      </button>
      <button class="btn btn-success btn-sm" onclick="doExport('json','included')">
        <i class="fa fa-code"></i> JSON
      </button>
    </div>
  </div>

  <!-- Artigos excluídos -->
  <div class="card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="width:42px;height:42px;border-radius:10px;background:rgba(255,77,106,0.1);display:flex;align-items:center;justify-content:center">
        <i class="fa fa-ban" style="color:var(--accent-red)"></i>
      </div>
      <div>
        <h3 style="font-size:0.95rem;font-weight:600">Artigos Excluídos</h3>
        <p class="text-muted text-xs">Artigos descartados com motivos de exclusão</p>
      </div>
    </div>
    <span class="badge badge-excluded" id="count-excluded">— artigos</span>
    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
      <button class="btn btn-outline btn-sm" onclick="doExport('csv','excluded')">
        <i class="fa fa-file-csv"></i> CSV
      </button>
      <button class="btn btn-outline btn-sm" onclick="doExport('json','excluded')">
        <i class="fa fa-code"></i> JSON
      </button>
    </div>
  </div>

</div>

<!-- Campos do CSV -->
<div class="card" style="margin-top:24px">
  <h3 class="card-title"><i class="fa fa-table"></i> Campos exportados (CSV)</h3>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
    <?php foreach (['ID','Título','Autores','Ano','Periódico','Volume','Número','Páginas','DOI','URL',
      'Tipo de Documento','Editora','ISSN','Idioma','Palavras-chave','Base de Dados',
      'Citações','Acesso Aberto','Status','Motivo de Exclusão'] as $f): ?>
    <span class="chip"><?= $f ?></span>
    <?php endforeach; ?>
  </div>
</div>

<!-- Log de exportações -->
<div class="card" style="margin-top:20px">
  <h3 class="card-title"><i class="fa fa-history"></i> Exportações desta sessão</h3>
  <div id="export-log" style="font-size:0.82rem;color:var(--text-muted);min-height:40px">
    <span style="font-style:italic">Nenhuma exportação realizada ainda.</span>
  </div>
</div>

<script>
const EXP_PROJECT_ID = <?= $projectId ?>;
const exportLog = [];

async function loadCounts() {
  try {
    const r = await fetch(`api/articles.php?project_id=${EXP_PROJECT_ID}&counts_only=1`);
    const d = await r.json();
    if (d.counts) {
      document.getElementById('count-all').textContent      = (d.counts.all || 0) + ' artigos';
      document.getElementById('count-screened').textContent = (d.counts.screened || 0) + ' artigos';
      document.getElementById('count-included').textContent = (d.counts.included || 0) + ' artigos';
      document.getElementById('count-excluded').textContent = (d.counts.excluded || 0) + ' artigos';
    }
  } catch(e) {}
}

function doExport(format, scope) {
  const url = `api/export.php?project_id=${EXP_PROJECT_ID}&format=${format}&scope=${scope}`;
  window.location.href = url;

  const label = {csv:'CSV', bibtex:'BibTeX', json:'JSON'}[format];
  const scopeLabel = {all:'Todos', screened:'Triados', included:'Incluídos', excluded:'Excluídos'}[scope];
  const time = new Date().toLocaleTimeString('pt-BR');
  exportLog.unshift(`<span style="color:var(--text-secondary)">[${time}]</span> ${label} — ${scopeLabel}`);

  const logEl = document.getElementById('export-log');
  logEl.innerHTML = exportLog.slice(0, 10).join('<br>');

  showToast(`Exportando ${label} (${scopeLabel})...`, 'success');
}

loadCounts();
</script>
<?php endif; ?>
