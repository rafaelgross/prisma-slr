<?php
/**
 * PRISMA-SLR - Tela de Triagem (screening de título/abstract)
 * Reutilizada também para Elegibilidade, controlada pela variável $phase
 */
$phase      = isset($_GET['phase']) ? $_GET['phase'] : 'screening';
$isElig     = $phase === 'eligibility';
$phaseLabel = $isElig ? 'Elegibilidade (Texto Completo)' : 'Triagem (Título / Resumo)';
$phaseIcon  = $isElig ? 'fa-check-double' : 'fa-magnifying-glass';

if (!$projectId): ?>
<div class="empty-state"><i class="fa fa-triangle-exclamation"></i>
  <p>Selecione um projeto para iniciar a triagem.</p></div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa <?= $phaseIcon ?>"></i> <?= $phaseLabel ?></h1>
    <p class="page-subtitle"><?= $isElig
      ? 'Avalie elegibilidade lendo o texto completo dos artigos que passaram na triagem.'
      : 'Avalie título e resumo para incluir ou excluir artigos.' ?></p>
  </div>
  <div style="display:flex;gap:10px">
    <button class="btn btn-outline" onclick="toggleView('list')" id="btn-view-list">
      <i class="fa fa-list"></i> Lista
    </button>
    <button class="btn btn-ghost" onclick="toggleView('card')" id="btn-view-card">
      <i class="fa fa-rectangle-list"></i> Detalhado
    </button>
  </div>
</div>

<!-- Progresso + filtros rápidos clicáveis -->
<div class="card" style="margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <span class="text-sm font-medium" id="prog-text">Carregando...</span>
    <span class="text-sm text-muted" id="prog-counts"></span>
  </div>
  <div class="progress-bar">
    <div class="progress-fill" id="prog-bar" style="width:0%"></div>
  </div>
  <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
    <button id="chip-pending"  onclick="setFilter('pending')"
      style="display:flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;border:2px solid var(--accent-yellow);background:transparent;cursor:pointer;font-size:0.78rem;font-weight:600;color:var(--accent-yellow);transition:all .15s">
      <i class="fa fa-clock"></i> <span id="prog-pending">0</span> pendentes
    </button>
    <button id="chip-included" onclick="setFilter('included')"
      style="display:flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;border:2px solid var(--accent-green);background:transparent;cursor:pointer;font-size:0.78rem;font-weight:600;color:var(--accent-green);transition:all .15s">
      <i class="fa fa-circle-check"></i> <span id="prog-include">0</span> incluídos
    </button>
    <button id="chip-excluded" onclick="setFilter('excluded')"
      style="display:flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;border:2px solid var(--accent-red);background:transparent;cursor:pointer;font-size:0.78rem;font-weight:600;color:var(--accent-red);transition:all .15s">
      <i class="fa fa-circle-xmark"></i> <span id="prog-exclude">0</span> excluídos
    </button>
    <button id="chip-all"      onclick="setFilter('all')"
      style="display:flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;border:2px solid var(--border);background:transparent;cursor:pointer;font-size:0.78rem;font-weight:600;color:var(--text-muted);transition:all .15s">
      <i class="fa fa-list"></i> Todos
    </button>
  </div>
</div>

<!-- Filtros rápidos -->
<div class="filters-bar" style="margin-bottom:16px">
  <div class="search-input" style="flex:2">
    <i class="fa fa-magnifying-glass"></i>
    <input type="text" class="form-control" id="scr-search"
           placeholder="Buscar por título ou resumo..."
           oninput="debounceLoad()">
  </div>
  <!-- scr-filter hidden — controlado pelos chips acima -->
  <select class="form-control" id="scr-filter" style="display:none">
    <option value="all">Todos</option>
    <option value="pending" selected>Pendentes</option>
    <option value="included">Incluídos</option>
    <option value="excluded">Excluídos</option>
  </select>
  <select class="form-control" id="scr-per-page" style="width:auto" onchange="loadScreening()">
    <option value="10">10/pág</option>
    <option value="20" selected>20/pág</option>
    <option value="50">50/pág</option>
  </select>
</div>

<!-- Container de artigos -->
<div id="scr-container"></div>

<!-- Paginação -->
<div id="scr-pagination" style="display:flex;justify-content:center;gap:8px;padding:20px 0;flex-wrap:wrap"></div>

<!-- Modal: resumo do artigo -->
<div class="modal-overlay" id="abstract-modal">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <h2 class="modal-title" style="font-size:1rem;line-height:1.4" id="abs-modal-title">—</h2>
      <button class="btn btn-ghost btn-sm" onclick="closeAbstractModal()"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body" style="max-height:65vh;overflow-y:auto">
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;font-size:0.8rem;color:var(--text-muted)">
        <span id="abs-modal-authors"><i class="fa fa-users"></i> —</span>
        <span id="abs-modal-year"><i class="fa fa-calendar"></i> —</span>
        <span id="abs-modal-journal"><i class="fa fa-book"></i> —</span>
        <span id="abs-modal-source"></span>
      </div>
      <div id="abs-modal-keywords" style="margin-bottom:14px;font-size:0.78rem;color:var(--text-muted)"></div>
      <div style="border-top:1px solid var(--border);padding-top:14px">
        <p style="font-size:0.78rem;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em">Resumo</p>
        <p id="abs-modal-abstract" style="font-size:0.875rem;line-height:1.7;color:var(--text-primary)">—</p>
      </div>
      <!-- Critérios do projeto (carregados dinamicamente) -->
      <div id="abs-modal-criteria"></div>
    </div>
    <div class="modal-footer" style="justify-content:space-between">
      <div style="display:flex;gap:8px" id="abs-modal-actions"></div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-ghost btn-sm" id="btn-translate" onclick="translateAbstract()" title="Traduzir resumo para português">
          <i class="fa fa-language"></i> Traduzir
        </button>
        <span id="abs-modal-doi-link"></span>
        <button class="btn btn-ghost" onclick="closeAbstractModal()">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: avaliação por estrelas ao incluir -->
<div class="modal-overlay" id="include-modal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h2 class="modal-title"><i class="fa fa-circle-check" style="color:var(--accent-green)"></i> Incluir Artigo</h2>
      <button class="btn btn-ghost btn-sm" onclick="closeIncludeModal()"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body" style="text-align:center;padding:28px 24px 16px">
      <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:22px">
        Avalie a relevância do artigo para sua pesquisa
      </p>
      <div id="star-picker" style="display:flex;justify-content:center;gap:10px;margin-bottom:12px"></div>
      <p id="star-label" style="font-size:0.82rem;font-weight:600;color:var(--accent-yellow);min-height:22px;transition:all .15s"></p>
    </div>
    <div class="modal-footer" style="justify-content:space-between">
      <button class="btn btn-ghost" onclick="closeIncludeModal()">Cancelar</button>
      <div style="display:flex;gap:8px">
        <button class="btn btn-ghost btn-sm" onclick="confirmIncludeDecision(true)" title="Incluir sem avaliar">
          <i class="fa fa-check"></i> Sem avaliação
        </button>
        <button class="btn btn-success" onclick="confirmIncludeDecision(false)" id="btn-confirm-include">
          <i class="fa fa-star"></i> Confirmar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: motivo de exclusão -->
<div class="modal-overlay" id="exclude-modal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h2 class="modal-title"><i class="fa fa-ban" style="color:var(--accent-red)"></i> Motivo de Exclusão</h2>
      <button class="btn btn-ghost btn-sm" onclick="closeExcludeModal()"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Selecione o motivo *</label>
        <select class="form-control" id="exclude-reason">
          <option value="">— Selecione —</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Observação (opcional)</label>
        <textarea class="form-control" id="exclude-notes" rows="2" placeholder="Detalhes adicionais..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeExcludeModal()">Cancelar</button>
      <button class="btn btn-danger" onclick="confirmExcludeDecision()" id="btn-confirm-exclude">
        <i class="fa fa-ban"></i> Confirmar Exclusão
      </button>
    </div>
  </div>
</div>

<script>
const SCR_PROJECT_ID  = <?= $projectId ?>;
const SCR_PHASE       = '<?= $phase ?>';
let scrPage          = 1;
let pendingArticleId = null;
let pendingIncludeId = null;
let selectedRating   = 0;
let reasons          = [];
let viewMode         = 'list';
let projectCriteria  = { inclusion: [], exclusion: [] };

const STAR_LABELS = ['','Pouco relevante','Relevância baixa','Relevante','Muito relevante','Altamente relevante'];

function askInclude(articleId) {
  pendingIncludeId = articleId;
  selectedRating   = 0;

  // Renderiza as estrelas UMA VEZ ao abrir — eventos de hover/click só atualizam cores
  let html = '';
  for (let i = 1; i <= 5; i++) {
    html += `<i class="fa fa-star"
      data-star="${i}"
      style="font-size:2.4rem;cursor:pointer;color:var(--border);transition:color .12s"
      onmouseover="hoverStar(${i})"
      onmouseout="hoverStar(0)"
      onclick="selectStar(${i})"></i>`;
  }
  document.getElementById('star-picker').innerHTML = html;
  document.getElementById('star-label').textContent = '';
  document.getElementById('btn-confirm-include').innerHTML = '<i class="fa fa-star"></i> Confirmar';
  document.getElementById('include-modal').classList.add('open');
}

function _paintStars(active) {
  document.querySelectorAll('#star-picker [data-star]').forEach(el => {
    const n = parseInt(el.dataset.star);
    el.style.color = n <= active ? '#f59e0b' : 'var(--border)';
  });
}

function hoverStar(n) {
  _paintStars(n || selectedRating);
  const lbl = document.getElementById('star-label');
  lbl.textContent = n ? STAR_LABELS[n] : (selectedRating ? STAR_LABELS[selectedRating] : '');
}

function selectStar(n) {
  selectedRating = n;
  _paintStars(n);
  document.getElementById('star-label').textContent = STAR_LABELS[n];
  document.getElementById('btn-confirm-include').innerHTML = `<i class="fa fa-star"></i> Confirmar (${n} ★)`;
}

function closeIncludeModal() {
  document.getElementById('include-modal').classList.remove('open');
  pendingIncludeId = null;
  selectedRating   = 0;
}

function confirmIncludeDecision(skipRating = false) {
  const articleId = pendingIncludeId;
  const rating    = skipRating ? null : (selectedRating || null);
  closeIncludeModal();
  decide(articleId, 'include', null, null, rating);
}

function starsHtml(rating, size = '0.85rem') {
  if (!rating) return '';
  let s = '';
  for (let i = 1; i <= 5; i++) {
    s += `<i class="fa fa-star" style="font-size:${size};color:${i <= rating ? '#f59e0b' : 'var(--border)'}"></i>`;
  }
  return `<span title="${STAR_LABELS[rating]}">${s}</span>`;
}

const debounceLoad = (() => {
  let t;
  return () => { clearTimeout(t); t = setTimeout(loadScreening, 350); };
})();

function toggleView(mode) {
  viewMode = mode;
  document.getElementById('btn-view-list').className = `btn ${mode==='list'?'btn-outline':'btn-ghost'}`;
  document.getElementById('btn-view-card').className = `btn ${mode==='card'?'btn-outline':'btn-ghost'}`;
  loadScreening();
}

function setFilter(val) {
  document.getElementById('scr-filter').value = val;
  scrPage = 1;
  updateChips(val);
  loadScreening(1);
}

function updateChips(val) {
  const chips = { pending:'chip-pending', included:'chip-included', excluded:'chip-excluded', all:'chip-all' };
  const activeStyle = { pending:'background:var(--accent-yellow);color:#000', included:'background:var(--accent-green);color:#000', excluded:'background:var(--accent-red);color:#fff', all:'background:var(--border);color:var(--text-primary)' };
  Object.keys(chips).forEach(k => {
    const el = document.getElementById(chips[k]);
    if (!el) return;
    if (k === val) {
      el.style.cssText += ';' + activeStyle[k];
    } else {
      // reset to outline style
      const borderMap = { pending:'var(--accent-yellow)', included:'var(--accent-green)', excluded:'var(--accent-red)', all:'var(--border)' };
      const colorMap  = { pending:'var(--accent-yellow)', included:'var(--accent-green)', excluded:'var(--accent-red)', all:'var(--text-muted)' };
      el.style.background = 'transparent';
      el.style.color      = colorMap[k];
      el.style.border     = `2px solid ${borderMap[k]}`;
    }
  });
}

async function loadScreening(page = scrPage) {
  scrPage = page;
  const container = document.getElementById('scr-container');
  container.innerHTML = '<div class="text-center" style="padding:40px"><div class="spinner"></div></div>';

  const filterVal      = document.getElementById('scr-filter').value;
  const pendingOnly    = filterVal === 'pending';
  const decisionFilter = filterVal === 'included' ? 'include' : filterVal === 'excluded' ? 'exclude' : '';
  const search         = document.getElementById('scr-search').value;
  const perPage        = document.getElementById('scr-per-page').value;

  const params = new URLSearchParams({
    project_id: SCR_PROJECT_ID,
    phase: SCR_PHASE,
    page, per_page: perPage,
    pending_only:    pendingOnly    ? 1 : 0,
    decision_filter: decisionFilter,
    search,
  });

  try {
    const r = await fetch(`api/screening.php?${params}`);
    const d = await r.json();
    if (d.error) throw new Error(d.message);

    reasons = d.exclusion_reasons || [];
    updateProgress(d);

    if (!d.articles.length) {
      container.innerHTML = `<div class="empty-state"><i class="fa fa-check-circle" style="color:var(--accent-green)"></i>
        <p>Nenhum artigo neste filtro.</p>
        ${filterVal === 'pending' ? '<small>Todos os artigos já foram avaliados!</small>' : ''}</div>`;
      document.getElementById('scr-pagination').innerHTML = '';
      return;
    }

    if (viewMode === 'list') {
      renderList(d.articles, filterVal);
    } else {
      renderCards(d.articles, filterVal);
    }

    buildScrPagination(d.page, d.last_page);
  } catch(e) {
    container.innerHTML = `<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> Erro: ${e.message}</div>`;
  }
}

function updateProgress(d) {
  const articles = d.articles;
  // Busca dados do sumário
  fetch(`api/screening.php?project_id=${SCR_PROJECT_ID}&summary=1`)
    .then(r => r.json()).then(s => {
      const phase = s[SCR_PHASE === 'screening' ? 'screening' : 'eligibility'];
      if (!phase) return;
      const total   = phase.total || 0;
      const done    = (phase.included || 0) + (phase.excluded || 0);
      const pct     = total > 0 ? Math.round(done / total * 100) : 0;
      document.getElementById('prog-text').textContent    = `${pct}% avaliado (${done} de ${total})`;
      document.getElementById('prog-counts').textContent  = `${d.total} artigos no filtro`;
      document.getElementById('prog-bar').style.width     = pct + '%';
      document.getElementById('prog-include').textContent = phase.included || 0;
      document.getElementById('prog-exclude').textContent = phase.excluded || 0;
      document.getElementById('prog-pending').textContent = phase.pending || 0;
    }).catch(() => {});
}

function renderList(articles, filter) {
  let html = '<div class="table-wrapper"><table><thead><tr>'
    + '<th>Título</th><th>Autores</th><th>Ano</th><th>Base</th><th>Decisão</th><th>Ações</th>'
    + '</tr></thead><tbody>';

  articles.forEach(a => {
    const dec = a.current_decision;
    const decBadge = dec === 'include'
      ? `<span class="badge badge-included">Incluído</span>${a.rating ? '<br><span style="white-space:nowrap">' + starsHtml(a.rating, '0.8rem') + '</span>' : ''}`
      : dec === 'exclude' ? `<span class="badge badge-excluded">Excluído <small>(${escHtml(a.exclusion_reason||'')})</small></span>`
      : `<span class="badge badge-pending">Pendente</span>`;
    html += `<tr>
      <td>
        <span style="font-weight:600;font-size:0.86rem;cursor:pointer;color:var(--primary);text-decoration:none"
              onclick='showAbstract(${JSON.stringify(a).replace(/'/g,"&apos;")})'
              title="Clique para ver o resumo">${escHtml(a.title)}</span>
        ${a.doi ? `<br><small><a href="https://doi.org/${escAttr(a.doi)}" target="_blank" class="text-muted" style="font-size:0.72rem">DOI</a></small>` : ''}
      </td>
      <td><span class="truncate" style="max-width:130px" title="${escAttr(a.authors)}">${escHtml(a.authors||'—')}</span></td>
      <td>${a.year||'—'}</td>
      <td><span class="badge badge-${a.source_type}">${a.source_type?.toUpperCase()}</span></td>
      <td>${decBadge}</td>
      <td>
        <div style="display:flex;gap:6px">
          ${dec !== 'include' ? `<button class="btn btn-success btn-sm" onclick="askInclude(${a.id})" title="Incluir">
            <i class="fa fa-check"></i></button>` : ''}
          ${dec !== 'exclude' ? `<button class="btn btn-danger btn-sm" onclick="askExclude(${a.id})" title="Excluir">
            <i class="fa fa-ban"></i></button>` : ''}
        </div>
      </td>
    </tr>`;
  });

  html += '</tbody></table></div>';
  document.getElementById('scr-container').innerHTML = html;
}

function renderCards(articles, filter) {
  let html = '<div style="display:flex;flex-direction:column;gap:12px">';
  articles.forEach(a => {
    const dec = a.current_decision;
    const borderColor = dec === 'include' ? 'rgba(0,229,160,0.3)' : dec === 'exclude' ? 'rgba(255,77,106,0.3)' : 'var(--border)';
    html += `
      <div class="article-card" style="border-color:${borderColor}">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start">
          <div style="flex:1">
            <div class="article-title">${escHtml(a.title)}</div>
            <div class="article-meta">
              <span><i class="fa fa-users"></i>${escHtml(a.authors||'—')}</span>
              <span><i class="fa fa-calendar"></i>${a.year||'—'}</span>
              <span><i class="fa fa-journal-whills"></i>${escHtml(a.journal||'—')}</span>
              <span class="badge badge-${a.source_type}">${a.source_type?.toUpperCase()}</span>
              ${a.doi ? `<a href="https://doi.org/${escAttr(a.doi)}" target="_blank" class="text-muted text-xs"><i class="fa fa-link"></i> DOI</a>` : ''}
              ${a.url ? `<a href="${escAttr(a.url)}" target="_blank" class="text-muted text-xs"><i class="fa fa-external-link"></i> Link</a>` : ''}
            </div>
            ${a.abstract ? `<div class="article-abstract">${escHtml(a.abstract)}</div>` : '<div class="text-muted text-xs" style="font-style:italic">Resumo não disponível</div>'}
            ${dec === 'include' && a.rating ? `<div style="margin-top:6px">${starsHtml(a.rating, '0.9rem')} <span style="font-size:0.75rem;color:var(--text-muted)">${STAR_LABELS[a.rating]}</span></div>` : ''}
            ${dec === 'exclude' && a.exclusion_reason ? `<div style="margin-top:8px;font-size:0.78rem;color:var(--accent-red)"><i class="fa fa-ban"></i> ${escHtml(a.exclusion_reason)}</div>` : ''}
            ${dec === 'exclude' && a.notes ? `<div style="font-size:0.78rem;color:var(--text-muted)">${escHtml(a.notes)}</div>` : ''}
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;min-width:130px">
            <button class="btn ${dec==='include'?'btn-success':'btn-ghost'} btn-sm" onclick="askInclude(${a.id})">
              <i class="fa fa-${dec==='include'?'circle-check':'check'}"></i> Incluir
            </button>
            <button class="btn ${dec==='exclude'?'btn-danger':'btn-ghost'} btn-sm" onclick="askExclude(${a.id})">
              <i class="fa fa-${dec==='exclude'?'circle-xmark':'ban'}"></i> Excluir
            </button>
            ${dec === 'exclude' || dec === 'include' ? `<button class="btn btn-ghost btn-sm text-muted" onclick="undecide(${a.id})" title="Desfazer decisão">
              <i class="fa fa-rotate-left"></i> Desfazer
            </button>` : ''}
          </div>
        </div>
      </div>`;
  });
  html += '</div>';
  document.getElementById('scr-container').innerHTML = html;
}

function buildScrPagination(page, lastPage) {
  const pag = document.getElementById('scr-pagination');
  if (lastPage <= 1) { pag.innerHTML = ''; return; }
  let html = `<button class="btn btn-ghost btn-sm" ${page<=1?'disabled':''} onclick="loadScreening(${page-1})">‹</button>`;
  for (let i = Math.max(1,page-2); i <= Math.min(lastPage,page+2); i++) {
    html += `<button class="btn ${i===page?'btn-primary':'btn-ghost'} btn-sm" onclick="loadScreening(${i})">${i}</button>`;
  }
  html += `<button class="btn btn-ghost btn-sm" ${page>=lastPage?'disabled':''} onclick="loadScreening(${page+1})">›</button>`;
  pag.innerHTML = html;
}

async function decide(articleId, decision, reasonId = null, notes = null, rating = null) {
  try {
    const r = await fetch('api/screening.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ article_id: articleId, phase: SCR_PHASE, decision, reason_id: reasonId, notes, rating })
    });
    const res = await r.json();
    if (res.error) throw new Error(res.message);
    showToast(decision === 'include' ? 'Artigo incluído' : 'Artigo excluído',
              decision === 'include' ? 'success' : 'warning');
    loadScreening();
  } catch(e) {
    showToast('Erro: ' + e.message, 'error');
  }
}

function askExclude(articleId) {
  pendingArticleId = articleId;
  // Popula motivos
  const sel = document.getElementById('exclude-reason');
  sel.innerHTML = '<option value="">— Selecione o motivo —</option>';
  reasons.forEach(r => {
    const opt = document.createElement('option');
    opt.value = r.id;
    opt.textContent = r.reason;
    sel.appendChild(opt);
  });
  document.getElementById('exclude-notes').value = '';
  document.getElementById('exclude-modal').classList.add('open');
}

function closeExcludeModal() {
  document.getElementById('exclude-modal').classList.remove('open');
  pendingArticleId = null;
}

function confirmExcludeDecision() {
  const reasonId  = document.getElementById('exclude-reason').value;
  const notes     = document.getElementById('exclude-notes').value;
  if (!reasonId) { showToast('Selecione o motivo de exclusão', 'warning'); return; }
  const articleId = pendingArticleId; // salva ANTES de fechar o modal (closeExcludeModal zera pendingArticleId)
  closeExcludeModal();
  decide(articleId, 'exclude', parseInt(reasonId), notes || null);
}

function showAbstract(a) {
  document.getElementById('abs-modal-title').textContent    = a.title || '—';
  document.getElementById('abs-modal-authors').innerHTML    = `<i class="fa fa-users"></i> ${escHtml(a.authors || '—')}`;
  document.getElementById('abs-modal-year').innerHTML       = `<i class="fa fa-calendar"></i> ${a.year || '—'}`;
  document.getElementById('abs-modal-journal').innerHTML    = `<i class="fa fa-book"></i> ${escHtml(a.journal || '—')}`;
  document.getElementById('abs-modal-source').innerHTML     = a.source_type
    ? `<span class="badge badge-${a.source_type}">${a.source_type.toUpperCase()}</span>` : '';

  const kw = a.keywords ? `<i class="fa fa-tags"></i> ${escHtml(a.keywords)}` : '';
  document.getElementById('abs-modal-keywords').innerHTML   = kw;

  document.getElementById('abs-modal-abstract').textContent =
    a.abstract ? a.abstract : 'Resumo não disponível para este artigo.';

  // Botões de ação (incluir/excluir)
  const dec = a.current_decision;
  let actHtml = '';
  if (dec !== 'include') actHtml += `<button class="btn btn-success btn-sm" onclick="closeAbstractModal();askInclude(${a.id})"><i class="fa fa-check"></i> Incluir</button>`;
  if (dec !== 'exclude') actHtml += `<button class="btn btn-danger btn-sm" onclick="closeAbstractModal();askExclude(${a.id})"><i class="fa fa-ban"></i> Excluir</button>`;
  if (dec === 'include' || dec === 'exclude') actHtml += `<button class="btn btn-ghost btn-sm" onclick="undecide(${a.id});closeAbstractModal()"><i class="fa fa-rotate-left"></i> Desfazer</button>`;
  document.getElementById('abs-modal-actions').innerHTML = actHtml;

  // Link DOI
  document.getElementById('abs-modal-doi-link').innerHTML = a.doi
    ? `<a href="https://doi.org/${escAttr(a.doi)}" target="_blank" class="btn btn-ghost btn-sm"><i class="fa fa-link"></i> DOI</a>` : '';

  // Painel de critérios de avaliação
  const critEl = document.getElementById('abs-modal-criteria');
  const inc = projectCriteria.inclusion;
  const exc = projectCriteria.exclusion;
  if (inc.length || exc.length) {
    let critHtml = `
      <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px">
        <p style="font-size:0.75rem;font-weight:700;color:var(--text-muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.06em">
          <i class="fa fa-list-check"></i> Critérios de Avaliação
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">`;
    if (inc.length) {
      critHtml += `<div>
        <div style="font-size:0.72rem;font-weight:700;color:var(--accent-green);margin-bottom:7px;display:flex;align-items:center;gap:5px">
          <i class="fa fa-circle-check"></i> INCLUSÃO
        </div>
        ${inc.map(c => `<div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:5px;font-size:0.78rem;line-height:1.4">
          <i class="fa fa-check" style="color:var(--accent-green);margin-top:2px;flex-shrink:0;font-size:0.7rem"></i>
          <span style="color:var(--text-primary)">${escHtml(c)}</span>
        </div>`).join('')}
      </div>`;
    }
    if (exc.length) {
      critHtml += `<div>
        <div style="font-size:0.72rem;font-weight:700;color:var(--accent-red);margin-bottom:7px;display:flex;align-items:center;gap:5px">
          <i class="fa fa-circle-xmark"></i> EXCLUSÃO
        </div>
        ${exc.map(c => `<div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:5px;font-size:0.78rem;line-height:1.4">
          <i class="fa fa-xmark" style="color:var(--accent-red);margin-top:2px;flex-shrink:0;font-size:0.7rem"></i>
          <span style="color:var(--text-primary)">${escHtml(c)}</span>
        </div>`).join('')}
      </div>`;
    }
    critHtml += `</div></div>`;
    critEl.innerHTML = critHtml;
  } else {
    critEl.innerHTML = '';
  }

  document.getElementById('abstract-modal').classList.add('open');
}

function closeAbstractModal() {
  document.getElementById('abstract-modal').classList.remove('open');
  // Reset translation button when closing
  const btn = document.getElementById('btn-translate');
  if (btn) { btn.innerHTML = '<i class="fa fa-language"></i> Traduzir'; btn.disabled = false; }
  // Reset to original text if translated
  const absEl = document.getElementById('abs-modal-abstract');
  if (absEl && absEl.dataset.original) {
    absEl.textContent = absEl.dataset.original;
    delete absEl.dataset.original;
    delete absEl.dataset.translated;
  }
}

let _translating = false;
async function translateAbstract() {
  if (_translating) return;
  const absEl  = document.getElementById('abs-modal-abstract');
  const btn    = document.getElementById('btn-translate');
  const text   = absEl.textContent.trim();
  if (!text || text === 'Resumo não disponível para este artigo.') return;

  // Toggle: se já está traduzido, volta ao original
  if (absEl.dataset.translated === '1') {
    absEl.textContent = absEl.dataset.original;
    absEl.dataset.translated = '0';
    btn.innerHTML = '<i class="fa fa-language"></i> Traduzir';
    return;
  }

  _translating = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Traduzindo...';
  btn.disabled  = true;

  try {
    // Proxy local PHP — evita CORS ao chamar API externa
    const encoded = encodeURIComponent(text.substring(0, 1500));
    const res = await fetch(`api/translate.php?text=${encoded}`);
    const data = await res.json();

    if (!data.error && data.translated) {
      absEl.dataset.original   = text;
      absEl.dataset.translated = '1';
      absEl.textContent        = data.translated;
      btn.innerHTML = '<i class="fa fa-language"></i> Ver original';
      btn.disabled  = false;
    } else {
      throw new Error(data.message || 'Tradução não disponível');
    }
  } catch(e) {
    btn.innerHTML = '<i class="fa fa-language"></i> Traduzir';
    btn.disabled  = false;
    showToast('Erro: ' + e.message, 'error');
  } finally {
    _translating = false;
  }
}

async function undecide(articleId) {
  // Remove a decisão simplesmente resubmetendo sem decisão — não há endpoint DELETE
  // Workaround: API não tem DELETE de decisão, mas podemos marcar como 'uncertain'
  try {
    await fetch('api/screening.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ article_id: articleId, phase: SCR_PHASE, decision: 'uncertain' })
    });
    showToast('Decisão desfeita (marcada como incerta)', 'info');
    loadScreening();
  } catch(e) {
    showToast('Erro: ' + e.message, 'error');
  }
}

// Carrega critérios do projeto e sincroniza motivos de exclusão na triagem
async function loadProjectCriteria() {
  try {
    // Carrega dados do projeto
    const r = await fetch(`api/projects.php?id=${SCR_PROJECT_ID}`);
    const d = await r.json();
    if (d.error) return;

    function parseCrit(raw) {
      if (!raw) return [];
      try {
        const arr = JSON.parse(raw);
        if (Array.isArray(arr)) return arr.filter(Boolean);
      } catch(e) {}
      return raw.split('\n').map(s => s.trim()).filter(Boolean);
    }
    projectCriteria.inclusion = parseCrit(d.inclusion_criteria);
    projectCriteria.exclusion = parseCrit(d.exclusion_criteria);

    // Sincroniza exclusion_reasons da triagem com os critérios cadastrados
    // Isso garante que o dropdown de exclusão reflita os critérios do projeto
    const syncR = await fetch(`api/projects.php?action=sync_reasons&id=${SCR_PROJECT_ID}`);
    const syncD = await syncR.json();
    if (syncD.success && syncD.reasons) {
      reasons = syncD.reasons; // atualiza a lista global de motivos
    }
  } catch(e) {}
}

// Inicializa chips com filtro padrão = pendentes
updateChips('pending');
loadProjectCriteria();
loadScreening();
</script>
<?php endif; ?>
