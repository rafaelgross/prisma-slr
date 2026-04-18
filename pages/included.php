<?php
/**
 * PRISMA-SLR - Artigos Incluídos
 */
if (!$projectId): ?>
<div class="empty-state"><i class="fa fa-triangle-exclamation"></i>
  <p>Selecione um projeto para ver os artigos incluídos.</p></div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-star"></i> Artigos Incluídos</h1>
    <p class="page-subtitle">Artigos aprovados em triagem e elegibilidade — corpus final da revisão.</p>
  </div>
  <div style="display:flex;gap:10px">
    <button class="btn btn-outline" onclick="exportIncluded('csv')"><i class="fa fa-file-csv"></i> CSV</button>
    <button class="btn btn-outline" onclick="exportIncluded('bibtex')"><i class="fa fa-file-code"></i> BibTeX</button>
    <button class="btn btn-outline" onclick="exportIncluded('json')"><i class="fa fa-file"></i> JSON</button>
  </div>
</div>

<!-- Estatísticas -->
<div class="stats-grid" id="inc-stats">
  <div class="stat-card"><div class="stat-value" id="inc-total">—</div><div class="stat-label">Artigos Incluídos</div></div>
  <div class="stat-card"><div class="stat-value" id="inc-years">—</div><div class="stat-label">Período</div></div>
  <div class="stat-card"><div class="stat-value" id="inc-journals">—</div><div class="stat-label">Periódicos Únicos</div></div>
  <div class="stat-card"><div class="stat-value" id="inc-citations">—</div><div class="stat-label">Citações Totais</div></div>
</div>

<!-- Filtros -->
<div class="filters-bar" style="margin-bottom:16px">
  <div class="search-input" style="flex:2">
    <i class="fa fa-magnifying-glass"></i>
    <input type="text" class="form-control" id="inc-search"
           placeholder="Buscar por título, autor ou palavra-chave..."
           oninput="debounceLoad()">
  </div>
  <select class="form-control" id="inc-year-filter" style="width:auto" onchange="loadIncluded()">
    <option value="">Todos os anos</option>
  </select>
  <select class="form-control" id="inc-sort" style="width:auto" onchange="loadIncluded()">
    <option value="year_desc">Ano ↓</option>
    <option value="year_asc">Ano ↑</option>
    <option value="title">Título A-Z</option>
    <option value="cited_by">Mais citados</option>
  </select>
  <select class="form-control" id="inc-per-page" style="width:auto" onchange="loadIncluded()">
    <option value="10">10/pág</option>
    <option value="25" selected>25/pág</option>
    <option value="50">50/pág</option>
    <option value="100">100/pág</option>
  </select>
</div>

<!-- Tabela -->
<div class="table-wrapper" id="inc-table-wrapper">
  <div class="text-center" style="padding:40px"><div class="spinner"></div></div>
</div>

<div id="inc-pagination" style="display:flex;justify-content:center;gap:8px;padding:20px 0;flex-wrap:wrap"></div>

<!-- Modal detalhe -->
<div class="modal-overlay" id="inc-detail-modal">
  <div class="modal" style="max-width:780px;max-height:92vh">
    <div class="modal-header">
      <h2 class="modal-title" id="inc-modal-title" style="font-size:1rem;line-height:1.4"></h2>
      <button class="btn btn-ghost btn-sm" onclick="document.getElementById('inc-detail-modal').classList.remove('open')"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body" id="inc-modal-body" style="overflow-y:auto;max-height:calc(92vh - 140px)"></div>
  </div>
</div>

<script>
const INC_PROJECT_ID = <?= $projectId ?>;
let incPage = 1;

const debounceLoad = (() => {
  let t;
  return () => { clearTimeout(t); t = setTimeout(loadIncluded, 350); };
})();

async function loadIncluded(page = incPage) {
  incPage = page;
  const wrapper = document.getElementById('inc-table-wrapper');
  wrapper.innerHTML = '<div class="text-center" style="padding:40px"><div class="spinner"></div></div>';

  const perPage = document.getElementById('inc-per-page').value;
  const search  = document.getElementById('inc-search').value;
  const year    = document.getElementById('inc-year-filter').value;
  const sort    = document.getElementById('inc-sort').value;

  const params = new URLSearchParams({
    project_id: INC_PROJECT_ID,
    status: 'included',
    per_page: perPage,
    page, search, year, sort,
  });

  try {
    const r = await fetch(`api/articles.php?${params}`);
    const d = await r.json();
    if (d.error) throw new Error(d.message);

    updateStats(d);
    populateYears(d.years);
    renderTable(d.articles);
    buildIncPagination(d.page, d.last_page);
  } catch(e) {
    wrapper.innerHTML = `<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> Erro: ${e.message}</div>`;
  }
}

function updateStats(d) {
  document.getElementById('inc-total').textContent      = d.total || 0;
  document.getElementById('inc-journals').textContent   = d.unique_journals || '—';
  document.getElementById('inc-citations').textContent  = d.total_citations || '—';
  const years = d.year_range;
  document.getElementById('inc-years').textContent      = years ? `${years[0]}–${years[1]}` : '—';
}

function populateYears(years) {
  const sel = document.getElementById('inc-year-filter');
  const cur = sel.value;
  // Só popula uma vez
  if (sel.querySelectorAll('option').length > 1) return;
  (years || []).forEach(y => {
    const o = document.createElement('option');
    o.value = y; o.textContent = y;
    sel.appendChild(o);
  });
  sel.value = cur;
}

function renderTable(articles) {
  const wrapper = document.getElementById('inc-table-wrapper');
  if (!articles.length) {
    wrapper.innerHTML = '<div class="empty-state"><i class="fa fa-star"></i><p>Nenhum artigo incluído ainda.</p></div>';
    return;
  }
  let html = '<table><thead><tr>'
    + '<th>#</th><th>Título</th><th>Autores</th><th>Ano</th><th>Periódico</th>'
    + '<th>Citações</th><th>DOI</th><th></th>'
    + '</tr></thead><tbody>';

  let n = (incPage - 1) * parseInt(document.getElementById('inc-per-page').value) + 1;
  articles.forEach(a => {
    html += `<tr>
      <td class="text-muted text-sm">${n++}</td>
      <td>
        <span class="link-btn" onclick="showDetail(${a.id})" style="font-weight:600">${escHtml(a.title)}</span>
      </td>
      <td><span class="truncate" style="max-width:160px">${escHtml(a.authors||'—')}</span></td>
      <td>${a.year||'—'}</td>
      <td><span class="truncate" style="max-width:160px">${escHtml(a.journal||'—')}</span></td>
      <td>${a.cited_by||0}</td>
      <td>${a.doi ? `<a href="https://doi.org/${escAttr(a.doi)}" target="_blank" class="text-muted text-xs"><i class="fa fa-link"></i> DOI</a>` : '—'}</td>
      <td><button class="btn btn-ghost btn-sm" onclick="showDetail(${a.id})"><i class="fa fa-eye"></i></button></td>
    </tr>`;
  });
  html += '</tbody></table>';
  wrapper.innerHTML = html;
}

function buildIncPagination(page, lastPage) {
  const pag = document.getElementById('inc-pagination');
  if (lastPage <= 1) { pag.innerHTML = ''; return; }
  let html = `<button class="btn btn-ghost btn-sm" ${page<=1?'disabled':''} onclick="loadIncluded(${page-1})">‹</button>`;
  const start = Math.max(1, page - 2);
  const end   = Math.min(lastPage, page + 2);
  if (start > 1) html += `<button class="btn btn-ghost btn-sm" onclick="loadIncluded(1)">1</button><span class="text-muted">…</span>`;
  for (let i = start; i <= end; i++) {
    html += `<button class="btn ${i===page?'btn-primary':'btn-ghost'} btn-sm" onclick="loadIncluded(${i})">${i}</button>`;
  }
  if (end < lastPage) html += `<span class="text-muted">…</span><button class="btn btn-ghost btn-sm" onclick="loadIncluded(${lastPage})">${lastPage}</button>`;
  html += `<button class="btn btn-ghost btn-sm" ${page>=lastPage?'disabled':''} onclick="loadIncluded(${page+1})">›</button>`;
  pag.innerHTML = html;
}

async function showDetail(articleId) {
  document.getElementById('inc-detail-modal').classList.add('open');
  document.getElementById('inc-modal-body').innerHTML = '<div class="text-center" style="padding:40px"><div class="spinner"></div></div>';

  try {
    const r = await fetch(`api/articles.php?id=${articleId}`);
    const d = await r.json();
    if (d.error) throw new Error(d.message);

    document.getElementById('inc-modal-title').textContent = d.title;

    let html = `
      <div class="article-meta" style="margin-bottom:16px">
        <span><i class="fa fa-users"></i>${escHtml(d.authors||'—')}</span>
        <span><i class="fa fa-calendar"></i>${d.year||'—'}</span>
        ${d.journal ? `<span><i class="fa fa-journal-whills"></i>${escHtml(d.journal)}</span>` : ''}
        ${d.doi ? `<a href="https://doi.org/${escAttr(d.doi)}" target="_blank" class="btn btn-outline btn-sm"><i class="fa fa-link"></i> DOI</a>` : ''}
        ${d.url ? `<a href="${escAttr(d.url)}" target="_blank" class="btn btn-ghost btn-sm"><i class="fa fa-external-link"></i> Texto Completo</a>` : ''}
      </div>`;

    if (d.abstract) {
      html += `<div class="form-group">
        <label class="form-label">Resumo</label>
        <div style="font-size:0.87rem;line-height:1.7;color:var(--text-secondary);background:var(--bg-card2);padding:14px;border-radius:8px">${escHtml(d.abstract)}</div>
      </div>`;
    }

    if (d.keywords && d.keywords.length) {
      html += `<div class="form-group"><label class="form-label">Palavras-chave</label><div style="display:flex;flex-wrap:wrap;gap:6px">`;
      d.keywords.forEach(k => { html += `<span class="chip">${escHtml(k.keyword)}</span>`; });
      html += '</div></div>';
    }

    const details = [];
    if (d.cited_by)   details.push(['<i class="fa fa-quote-left"></i> Citações', d.cited_by]);
    if (d.publisher)  details.push(['<i class="fa fa-building"></i> Editora', d.publisher]);
    if (d.volume)     details.push(['<i class="fa fa-layer-group"></i> Volume/Número', `${d.volume}${d.issue?'/'+d.issue:''}`]);
    if (d.pages)      details.push(['<i class="fa fa-file-alt"></i> Páginas', d.pages]);
    if (d.issn)       details.push(['<i class="fa fa-barcode"></i> ISSN', d.issn]);
    if (d.source_type) details.push(['<i class="fa fa-database"></i> Base', d.source_type.toUpperCase()]);
    if (details.length) {
      html += `<div class="form-group"><label class="form-label">Metadados</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">`;
      details.forEach(([k,v]) => {
        html += `<div style="background:var(--bg-card2);padding:8px 12px;border-radius:6px;font-size:0.82rem">
          <div class="text-muted" style="font-size:0.72rem;margin-bottom:2px">${k}</div>
          <div>${escHtml(String(v))}</div></div>`;
      });
      html += '</div></div>';
    }

    if (d.affiliations && d.affiliations.length) {
      html += `<div class="form-group"><label class="form-label">Afiliações</label>
        <div style="font-size:0.82rem;color:var(--text-secondary)">`;
      d.affiliations.forEach(af => {
        html += `<div style="padding:4px 0">${escHtml(af.institution||'')}${af.country?', '+escHtml(af.country):''}</div>`;
      });
      html += '</div></div>';
    }

    document.getElementById('inc-modal-body').innerHTML = html;
  } catch(e) {
    document.getElementById('inc-modal-body').innerHTML = `<div class="alert alert-error">Erro: ${e.message}</div>`;
  }
}

function exportIncluded(format) {
  const url = `api/export.php?project_id=${INC_PROJECT_ID}&format=${format}&scope=included`;
  window.location.href = url;
}

loadIncluded();
</script>
<?php endif; ?>
