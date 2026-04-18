<?php
/**
 * PRISMA-SLR — pages/references.php
 * Exportação de referências em ABNT NBR 6023:2018 ou APA 7ª ed.
 */
if (!$projectId): ?>
<div class="empty-state">
  <i class="fa fa-book-open"></i>
  <p>Selecione um projeto para gerar referências.</p>
</div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-quote-right"></i> Referências Bibliográficas</h1>
    <p class="page-subtitle">Gere referências formatadas em ABNT ou APA a partir dos artigos incluídos.</p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <button class="btn btn-outline" id="btn-generate" onclick="generateRefs()">
      <i class="fa fa-wand-magic-sparkles"></i> Gerar Referências
    </button>
    <button class="btn btn-ghost" id="btn-copy" onclick="copyRefs()" disabled>
      <i class="fa fa-copy"></i> Copiar
    </button>
    <button class="btn btn-ghost" id="btn-download" onclick="downloadRefs()" disabled>
      <i class="fa fa-download"></i> Baixar .txt
    </button>
  </div>
</div>

<!-- Barra de opções -->
<div class="card" style="padding:16px 20px;margin-bottom:20px">
  <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">

    <!-- Formato -->
    <div style="display:flex;align-items:center;gap:10px">
      <span style="font-size:0.85rem;font-weight:600;color:var(--text-secondary)">Formato:</span>
      <div style="display:flex;gap:4px;background:var(--bg-surface);border:1px solid var(--border);border-radius:8px;padding:3px">
        <button id="fmt-abnt" class="fmt-btn active-fmt" onclick="setFormat('abnt')"
          style="border:none;cursor:pointer;padding:4px 14px;border-radius:6px;font-size:12px;font-weight:700;background:var(--primary);color:#fff">
          ABNT
        </button>
        <button id="fmt-apa" class="fmt-btn" onclick="setFormat('apa')"
          style="border:none;cursor:pointer;padding:4px 14px;border-radius:6px;font-size:12px;font-weight:700;background:none;color:var(--text-muted)">
          APA 7ª
        </button>
      </div>
    </div>

    <!-- Escopo -->
    <div style="display:flex;align-items:center;gap:10px">
      <span style="font-size:0.85rem;font-weight:600;color:var(--text-secondary)">Artigos:</span>
      <select id="scope-select" style="font-size:0.82rem;padding:4px 10px;border:1px solid var(--border);border-radius:6px;background:var(--bg-card);color:var(--text-primary)">
        <option value="included">Apenas incluídos</option>
        <option value="all">Todos (exceto duplicatas)</option>
      </select>
    </div>

    <!-- Contador -->
    <div style="margin-left:auto;font-size:0.82rem;color:var(--text-muted)" id="ref-counter"></div>
  </div>
</div>

<!-- Layout principal: lista de artigos | saída das referências -->
<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start">

  <!-- Painel esquerdo: seleção de artigos -->
  <div class="card" style="padding:0;overflow:hidden">
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:0.85rem;font-weight:600">Selecionar Artigos</span>
      <div style="display:flex;gap:8px">
        <button class="btn btn-ghost btn-sm" style="font-size:11px;padding:2px 8px" onclick="selectAll()">Todos</button>
        <button class="btn btn-ghost btn-sm" style="font-size:11px;padding:2px 8px" onclick="selectNone()">Nenhum</button>
      </div>
    </div>
    <div id="article-list" style="max-height:520px;overflow-y:auto;padding:4px 0">
      <div class="text-center" style="padding:40px">
        <div class="spinner"></div>
      </div>
    </div>
  </div>

  <!-- Painel direito: referências geradas -->
  <div class="card" style="padding:0;overflow:hidden;min-height:200px">
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:0.85rem;font-weight:600" id="output-title">Referências</span>
      <span id="output-badge" style="font-size:0.75rem;color:var(--text-muted)"></span>
    </div>
    <div id="refs-output" style="padding:20px;min-height:200px;font-size:0.83rem;line-height:1.8">
      <div class="empty-state" style="padding:60px 20px">
        <i class="fa fa-quote-right" style="font-size:2rem;margin-bottom:12px;opacity:0.3"></i>
        <p style="color:var(--text-muted)">Selecione os artigos e clique em <strong>Gerar Referências</strong>.</p>
      </div>
    </div>
  </div>

</div>

<style>
.article-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px 16px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  transition: background 0.15s;
}
.article-item:last-child { border-bottom: none; }
.article-item:hover { background: var(--bg-surface); }
.article-item input[type=checkbox] {
  margin-top: 3px;
  flex-shrink: 0;
  accent-color: var(--primary);
  width: 15px;
  height: 15px;
}
.article-item-title {
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--text-primary);
  line-height: 1.4;
}
.article-item-meta {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin-top: 2px;
}
.ref-item {
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
  color: var(--text-primary);
  text-indent: -2em;
  padding-left: 2em;
}
.ref-item:last-child { border-bottom: none; }
.ref-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  margin-right: 8px;
  flex-shrink: 0;
  vertical-align: middle;
  text-indent: 0;
}
</style>

<script>
const REFS_PROJECT_ID = <?= $projectId ?>;
let currentFormat   = 'abnt';
let currentScope    = 'included';
let allArticles     = [];
let lastRefs        = [];

// ── Inicialização ───────────────────────────────────────────────
async function loadArticles() {
  try {
    const r = await fetch(`api/articles.php?project_id=${REFS_PROJECT_ID}&status=included&per_page=500&page=1`);
    const d = await r.json();
    allArticles = d.articles || [];
    renderArticleList();
    document.getElementById('ref-counter').textContent =
      allArticles.length + ' artigo(s) disponível(eis)';
  } catch(e) {
    document.getElementById('article-list').innerHTML =
      '<div class="alert alert-error" style="margin:12px">Erro ao carregar artigos.</div>';
  }
}

function renderArticleList() {
  const el = document.getElementById('article-list');
  if (!allArticles.length) {
    el.innerHTML = '<div style="padding:30px;text-align:center;color:var(--text-muted);font-size:0.82rem">Nenhum artigo incluído neste projeto.</div>';
    return;
  }
  el.innerHTML = allArticles.map(a => {
    const authors = (a.authors || '').split(';')[0].trim();
    const meta    = [authors, a.year, a.journal].filter(Boolean).join(' · ');
    return `<label class="article-item">
      <input type="checkbox" class="art-chk" value="${a.id}" checked>
      <div>
        <div class="article-item-title">${escHtml(truncate(a.title, 80))}</div>
        <div class="article-item-meta">${escHtml(truncate(meta, 60))}</div>
      </div>
    </label>`;
  }).join('');
}

// ── Geração de referências ───────────────────────────────────────
async function generateRefs() {
  const checked = [...document.querySelectorAll('.art-chk:checked')].map(c => c.value);
  if (!checked.length) {
    showToast('Selecione ao menos um artigo.', 'warning');
    return;
  }

  const btn = document.getElementById('btn-generate');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Gerando...';

  document.getElementById('refs-output').innerHTML =
    '<div class="text-center" style="padding:60px"><div class="spinner"></div></div>';

  try {
    const ids = checked.join(',');
    const url = `api/references.php?project_id=${REFS_PROJECT_ID}&format=${currentFormat}&scope=${currentScope}&ids=${ids}`;
    const r   = await fetch(url);
    const d   = await r.json();

    if (d.error) throw new Error(d.message);

    lastRefs = d.references || [];
    renderRefs(lastRefs);

    document.getElementById('btn-copy').disabled     = false;
    document.getElementById('btn-download').disabled  = false;
    document.getElementById('output-badge').textContent = lastRefs.length + ' referência(s)';
    document.getElementById('output-title').textContent =
      'Referências — ' + (currentFormat === 'abnt' ? 'ABNT NBR 6023:2018' : 'APA 7ª Edição');

  } catch(e) {
    document.getElementById('refs-output').innerHTML =
      `<div class="alert alert-error">Erro: ${e.message}</div>`;
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-wand-magic-sparkles"></i> Gerar Referências';
  }
}

function renderRefs(refs) {
  if (!refs.length) {
    document.getElementById('refs-output').innerHTML =
      '<div style="padding:30px;text-align:center;color:var(--text-muted)">Nenhuma referência gerada.</div>';
    return;
  }
  const html = refs.map((r, i) =>
    `<div class="ref-item">
      <span class="ref-number">${i + 1}</span>${escHtml(r.reference)}
    </div>`
  ).join('');
  document.getElementById('refs-output').innerHTML = html;
}

// ── Ações ────────────────────────────────────────────────────────
function copyRefs() {
  if (!lastRefs.length) return;
  const text = lastRefs.map((r, i) => (i + 1) + '. ' + r.reference).join('\n\n');
  navigator.clipboard.writeText(text).then(() => {
    showToast('Referências copiadas!', 'success');
  }).catch(() => {
    // Fallback
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('Referências copiadas!', 'success');
  });
}

function downloadRefs() {
  if (!lastRefs.length) return;
  const fmtLabel = currentFormat === 'abnt' ? 'ABNT_NBR6023' : 'APA7';
  const text = lastRefs.map((r, i) => (i + 1) + '. ' + r.reference).join('\n\n');
  const blob = new Blob(['\uFEFF' + text], { type: 'text/plain;charset=utf-8' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `referencias_${fmtLabel}_${REFS_PROJECT_ID}.txt`;
  a.click();
  URL.revokeObjectURL(url);
}

// ── Formato / escopo ─────────────────────────────────────────────
function setFormat(fmt) {
  currentFormat = fmt;
  ['abnt','apa'].forEach(f => {
    const btn = document.getElementById('fmt-' + f);
    if (f === fmt) { btn.style.background = 'var(--primary)'; btn.style.color = '#fff'; }
    else           { btn.style.background = 'none'; btn.style.color = 'var(--text-muted)'; }
  });
  // Se já havia referências geradas, regenera automaticamente
  if (lastRefs.length) generateRefs();
}

document.getElementById('scope-select').addEventListener('change', function() {
  currentScope = this.value;
});

// ── Selecionar todos / nenhum ────────────────────────────────────
function selectAll()  { document.querySelectorAll('.art-chk').forEach(c => c.checked = true); }
function selectNone() { document.querySelectorAll('.art-chk').forEach(c => c.checked = false); }

// ── Utilidades ───────────────────────────────────────────────────
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function truncate(s, n) {
  return s && s.length > n ? s.slice(0, n) + '…' : (s || '');
}

// Reage à mudança de idioma do diagrama (não necessário, mas mantém consistência)
document.addEventListener('prismaLangChanged', () => {});

// Carrega ao inicializar
loadArticles();
</script>

<?php endif; ?>
