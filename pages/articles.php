<?php
/**
 * PRISMA-SLR - Página: Lista de Artigos
 */
if (!$projectId): ?>
<div class="empty-state"><i class="fa fa-triangle-exclamation"></i>
  <p>Selecione um projeto para ver os artigos.</p></div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-book"></i> Artigos</h1>
    <p class="page-subtitle">Todos os artigos importados do projeto.</p>
  </div>
  <div style="display:flex;gap:10px">
    <a href="?page=import&project_id=<?= $projectId ?>" class="btn btn-outline">
      <i class="fa fa-file-import"></i> Importar
    </a>
    <a href="?page=export&project_id=<?= $projectId ?>" class="btn btn-ghost">
      <i class="fa fa-upload"></i> Exportar
    </a>
  </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom:18px">
  <div class="filters-bar" style="margin-bottom:0">
    <div class="search-input" style="flex:2">
      <i class="fa fa-magnifying-glass"></i>
      <input type="text" class="form-control" id="art-search" placeholder="Buscar por título, abstract, DOI..."
             oninput="debounce(loadArticles, 400)()">
    </div>
    <select class="form-control" id="art-status" style="width:auto" onchange="loadArticles()">
      <option value="">Todos os status</option>
      <option value="identified">Identificados</option>
      <option value="screened">Triados</option>
      <option value="eligible">Elegíveis</option>
      <option value="included">Incluídos</option>
      <option value="excluded">Excluídos</option>
    </select>
    <select class="form-control" id="art-type" style="width:auto" onchange="loadArticles()">
      <option value="">Todas as bases</option>
      <option value="scopus">Scopus</option>
      <option value="wos">Web of Science</option>
      <option value="other">Outras</option>
    </select>
    <select class="form-control" id="art-dup" style="width:auto" onchange="loadArticles()">
      <option value="">Incluir duplicatas</option>
      <option value="0">Sem duplicatas</option>
      <option value="1">Só duplicatas</option>
    </select>
    <select class="form-control" id="art-per-page" style="width:auto" onchange="loadArticles()">
      <option value="25">25/pág</option>
      <option value="50">50/pág</option>
      <option value="100">100/pág</option>
    </select>
  </div>
</div>

<!-- Stats resumo -->
<div id="articles-summary" style="margin-bottom:14px;font-size:0.82rem;color:var(--text-muted)"></div>

<!-- Tabela -->
<div class="card">
  <div id="articles-container">
    <div class="text-center" style="padding:40px"><div class="spinner"></div></div>
  </div>
  <!-- Paginação -->
  <div id="pagination" style="display:flex;justify-content:center;gap:8px;padding:16px 0;flex-wrap:wrap"></div>
</div>

<!-- Modal: Detalhes do artigo -->
<div class="modal-overlay" id="article-modal">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <h2 class="modal-title">Detalhes do Artigo</h2>
      <button class="btn btn-ghost btn-sm" onclick="closeArticleModal()"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body" id="article-detail-content">
      <div class="text-center"><div class="spinner"></div></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeArticleModal()">Fechar</button>
    </div>
  </div>
</div>

<script>
const ART_PROJECT_ID = <?= $projectId ?>;
let currentPage = 1;

function debounce(fn, delay) {
  let t;
  return function(...args) {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(this, args), delay);
  };
}

async function loadArticles(page = 1) {
  currentPage = page;
  const container = document.getElementById('articles-container');
  container.innerHTML = '<div class="text-center" style="padding:40px"><div class="spinner"></div></div>';

  const params = new URLSearchParams({
    project_id: ART_PROJECT_ID,
    page,
    per_page: document.getElementById('art-per-page').value,
    search:   document.getElementById('art-search').value,
    status:   document.getElementById('art-status').value,
    source_type: document.getElementById('art-type').value,
    sort: 'year', dir: 'desc',
  });

  const dupVal = document.getElementById('art-dup').value;
  if (dupVal !== '') params.set('is_duplicate', dupVal);

  try {
    const r = await fetch(`api/articles.php?${params}`);
    const d = await r.json();

    document.getElementById('articles-summary').textContent =
      `Exibindo ${d.articles.length} de ${d.total} artigos — Página ${d.page} de ${d.last_page}`;

    if (!d.articles.length) {
      container.innerHTML = '<div class="empty-state" style="padding:40px"><i class="fa fa-search"></i><p>Nenhum artigo encontrado.</p></div>';
      document.getElementById('pagination').innerHTML = '';
      return;
    }

    const offset = (currentPage - 1) * parseInt(document.getElementById('art-per-page').value);
    let html = '<div class="table-wrapper"><table style="table-layout:fixed;width:100%"><thead><tr>'
      + '<th style="width:42px">#</th>'
      + '<th style="width:35%">Título</th>'
      + '<th style="width:20%">Autores</th>'
      + '<th style="width:48px">Ano</th>'
      + '<th style="width:18%">Periódico</th>'
      + '<th style="width:60px">Base</th>'
      + '<th style="width:90px">Status</th>'
      + '<th style="width:60px">Cit.</th>'
      + '</tr></thead><tbody>';

    d.articles.forEach((a, i) => {
      const isDup = a.is_duplicate == 1;
      const doiTitle = a.doi ? ` · DOI: ${a.doi}` : '';
      html += `<tr ${isDup ? 'style="opacity:0.55"' : ''}>
        <td style="color:var(--text-muted);font-size:0.78rem;text-align:center">${offset + i + 1}</td>
        <td>
          <div class="truncate" style="cursor:pointer;font-weight:500"
               onclick="showArticle(${a.id})" title="${escAttr(a.title + doiTitle)}">
            ${isDup ? '<i class="fa fa-copy" style="color:var(--accent-red);margin-right:4px" title="Duplicata"></i>' : ''}
            ${escHtml(a.title)}
          </div>
        </td>
        <td><div class="truncate" title="${escAttr(a.authors)}">${escHtml(a.authors || '—')}</div></td>
        <td style="text-align:center">${a.year || '—'}</td>
        <td><div class="truncate" title="${escAttr(a.journal)}">${escHtml(a.journal || '—')}</div></td>
        <td><span class="badge badge-${a.source_type}">${(a.source_type || '—').toUpperCase()}</span></td>
        <td><span class="badge badge-${a.status}">${a.status}</span></td>
        <td style="text-align:center">${a.cited_by || 0}</td>
      </tr>`;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;

    // Paginação
    buildPagination(d.page, d.last_page);
  } catch(e) {
    container.innerHTML = `<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> Erro: ${e.message}</div>`;
  }
}

function buildPagination(page, lastPage) {
  const pag = document.getElementById('pagination');
  if (lastPage <= 1) { pag.innerHTML = ''; return; }

  let html = '';
  const pages = [];
  if (lastPage <= 7) {
    for(let i=1;i<=lastPage;i++) pages.push(i);
  } else {
    pages.push(1);
    if(page>3) pages.push('...');
    for(let i=Math.max(2,page-1);i<=Math.min(lastPage-1,page+1);i++) pages.push(i);
    if(page<lastPage-2) pages.push('...');
    pages.push(lastPage);
  }

  html += `<button class="btn btn-ghost btn-sm" ${page<=1?'disabled':''} onclick="loadArticles(${page-1})">‹</button>`;
  pages.forEach(p => {
    if (p === '...') { html += `<span style="padding:4px 6px;color:var(--text-muted)">…</span>`; return; }
    html += `<button class="btn ${p===page?'btn-primary':'btn-ghost'} btn-sm" onclick="loadArticles(${p})">${p}</button>`;
  });
  html += `<button class="btn btn-ghost btn-sm" ${page>=lastPage?'disabled':''} onclick="loadArticles(${page+1})">›</button>`;
  pag.innerHTML = html;
}

async function showArticle(id) {
  document.getElementById('article-modal').classList.add('open');
  const content = document.getElementById('article-detail-content');
  content.innerHTML = '<div class="text-center" style="padding:40px"><div class="spinner"></div></div>';

  try {
    const r = await fetch(`api/articles.php?id=${id}`);
    const a = await r.json();

    const authors  = (a.authors  || []).map(au => escHtml(au.full_name)).join('; ') || '—';
    const keywords = (a.keywords || []).filter(k => k.keyword_type === 'author').map(k => `<span class="chip">${escHtml(k.keyword)}</span>`).join('') || '—';
    const kwPlus   = (a.keywords || []).filter(k => k.keyword_type === 'plus').map(k => `<span class="chip purple">${escHtml(k.keyword)}</span>`).join('');

    content.innerHTML = `
      <div>
        <h3 style="font-size:1.05rem;font-weight:700;line-height:1.4;margin-bottom:12px">${escHtml(a.title)}</h3>
        <div class="article-meta" style="margin-bottom:16px">
          <span><i class="fa fa-users"></i> ${authors}</span>
          <span><i class="fa fa-calendar"></i> ${a.year || '—'}</span>
          <span><i class="fa fa-journal-whills"></i> ${escHtml(a.journal || '—')}</span>
          <span class="badge badge-${a.source_type}">${a.source_type?.toUpperCase()}</span>
          <span class="badge badge-${a.status}">${a.status}</span>
        </div>
        ${a.doi ? `<div style="margin-bottom:12px;font-size:0.82rem"><i class="fa fa-link" style="color:var(--primary)"></i> <a href="https://doi.org/${escAttr(a.doi)}" target="_blank" rel="noopener">${escHtml(a.doi)}</a></div>` : ''}
        ${a.abstract ? `<div style="margin-bottom:16px"><strong style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted)">Resumo</strong><p style="font-size:0.855rem;color:var(--text-secondary);margin-top:6px;line-height:1.7">${escHtml(a.abstract)}</p></div>` : ''}
        <div style="margin-bottom:12px"><strong style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted)">Palavras-chave</strong><div class="keyword-chips" style="margin-top:8px">${keywords}</div></div>
        ${kwPlus ? `<div><strong style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted)">Keywords-Plus (WoS)</strong><div class="keyword-chips" style="margin-top:8px">${kwPlus}</div></div>` : ''}
        <hr class="divider">
        <div class="form-row" style="font-size:0.82rem;color:var(--text-secondary)">
          <div><strong>Volume/Número:</strong> ${a.volume||'—'}/${a.issue||'—'}</div>
          <div><strong>Páginas:</strong> ${a.pages||'—'}</div>
          <div><strong>ISSN:</strong> ${a.issn||'—'}</div>
          <div><strong>Editora:</strong> ${escHtml(a.publisher||'—')}</div>
          <div><strong>Citações:</strong> ${a.cited_by||0}</div>
          <div><strong>Open Access:</strong> ${a.open_access?'✓ Sim':'✗ Não'}</div>
        </div>
      </div>`;
  } catch(e) {
    content.innerHTML = `<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> Erro: ${e.message}</div>`;
  }
}

function closeArticleModal() {
  document.getElementById('article-modal').classList.remove('open');
}

loadArticles();
</script>
<?php endif; ?>
