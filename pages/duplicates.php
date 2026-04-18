<?php
/**
 * PRISMA-SLR - Página: Duplicatas
 */
if (!$projectId): ?>
<div class="empty-state"><i class="fa fa-triangle-exclamation"></i>
  <p>Selecione um projeto para gerenciar duplicatas.</p></div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-copy"></i> Gerenciamento de Duplicatas</h1>
    <p class="page-subtitle">Detecte e resolva registros duplicados entre as bases de dados.</p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn btn-primary" onclick="runDetection()" id="btn-detect">
      <i class="fa fa-magnifying-glass"></i> Detectar Duplicatas
    </button>
    <button class="btn btn-success" onclick="confirmAllPending()" id="btn-confirm-all" style="display:none">
      <i class="fa fa-check-double"></i> Confirmar todos pendentes
    </button>
    <button class="btn btn-ghost" onclick="loadDuplicates()">
      <i class="fa fa-rotate"></i> Atualizar
    </button>
    <button class="btn btn-ghost" onclick="resetDetection()" style="color:var(--accent-red);border-color:var(--accent-red)">
      <i class="fa fa-trash"></i> Redefinir
    </button>
  </div>
</div>

<!-- Banner informativo -->
<div class="alert" id="info-banner" style="display:none;margin-bottom:16px;background:var(--bg-input);border:1px solid var(--primary);color:var(--text-primary)">
  <i class="fa fa-circle-info" style="color:var(--primary)"></i>
  <span id="info-banner-text"></span>
</div>

<!-- Resumo -->
<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card" id="stat-pending">
    <div class="stat-icon"><i class="fa fa-clock"></i></div>
    <div class="stat-value">—</div>
    <div class="stat-label">Possíveis duplicatas</div>
    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px">Aguardando sua revisão</div>
  </div>
  <div class="stat-card red" id="stat-confirmed">
    <div class="stat-icon"><i class="fa fa-copy"></i></div>
    <div class="stat-value">—</div>
    <div class="stat-label">Pares confirmados</div>
  </div>
  <div class="stat-card green" id="stat-rejected">
    <div class="stat-icon"><i class="fa fa-circle-check"></i></div>
    <div class="stat-value">—</div>
    <div class="stat-label">Rejeitados (não são dup.)</div>
  </div>
  <div class="stat-card blue" id="stat-unique">
    <div class="stat-icon"><i class="fa fa-list-check"></i></div>
    <div class="stat-value">—</div>
    <div class="stat-label">Artigos únicos para triagem</div>
  </div>
</div>

<!-- Config de detecção -->
<div class="card" style="margin-bottom:18px" id="detection-config">
  <div class="card-header">
    <div class="card-title"><i class="fa fa-sliders"></i> Configuração da Detecção</div>
  </div>
  <div style="display:flex;gap:20px;align-items:flex-end">
    <div class="form-group" style="margin-bottom:0;flex:1">
      <label class="form-label">Limiar de similaridade de título (%)</label>
      <input type="range" id="threshold-slider" min="60" max="100" value="85" step="5"
             oninput="document.getElementById('threshold-val').textContent=this.value+'%'"
             style="width:100%;accent-color:var(--primary)">
      <div style="display:flex;justify-content:space-between;font-size:0.72rem;color:var(--text-muted);margin-top:2px">
        <span>60% (mais agressivo)</span>
        <span id="threshold-val" style="color:var(--primary);font-weight:700">85%</span>
        <span>100% (exato)</span>
      </div>
    </div>
    <div>
      <button class="btn btn-primary" onclick="runDetection()" id="btn-detect2">
        <i class="fa fa-play"></i> Executar
      </button>
    </div>
  </div>
  <p class="form-hint" style="margin-top:8px">O algoritmo verifica DOI idêntico (100% match) e similaridade de título usando similar_text. Artigos com ano > 1 ano de diferença são ignorados.</p>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab-btn active" onclick="selectDupTab('pending',this)">
    <i class="fa fa-clock"></i> Pendentes <span id="tab-count-pending" class="nav-badge" style="margin-left:6px;display:inline-block">—</span>
  </button>
  <button class="tab-btn" onclick="selectDupTab('confirmed',this)">
    <i class="fa fa-circle-xmark"></i> Confirmadas
  </button>
  <button class="tab-btn" onclick="selectDupTab('rejected',this)">
    <i class="fa fa-circle-check"></i> Rejeitadas
  </button>
</div>

<div id="duplicates-container">
  <div class="text-center" style="padding:40px"><div class="spinner"></div></div>
</div>

<script>
const DUP_PROJECT_ID = <?= $projectId ?>;
let currentDupStatus = 'pending';

function selectDupTab(status, btn) {
  currentDupStatus = status;
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  loadDuplicates(status);
}

async function runDetection() {
  const btn = document.getElementById('btn-detect');
  const btn2 = document.getElementById('btn-detect2');
  [btn, btn2].forEach(b => { if(b){b.disabled=true; b.innerHTML='<div class="spinner"></div> Detectando...'; }});

  try {
    const threshold = document.getElementById('threshold-slider').value;
    const r = await fetch('api/duplicates.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ project_id: DUP_PROJECT_ID, title_threshold: parseFloat(threshold) })
    });
    const res = await r.json();
    if (res.error) throw new Error(res.message);
    showToast(`Detecção concluída: ${res.pairs_detected} par(es) encontrado(s)`, 'success');
    loadDuplicates('pending');
  } catch(e) {
    showToast('Erro: ' + e.message, 'error');
  } finally {
    [btn, btn2].forEach(b => { if(b){b.disabled=false; }});
    if(btn) btn.innerHTML = '<i class="fa fa-magnifying-glass"></i> Detectar Duplicatas';
    if(btn2) btn2.innerHTML = '<i class="fa fa-play"></i> Executar';
  }
}

async function loadDuplicates(status = currentDupStatus) {
  const container = document.getElementById('duplicates-container');
  container.innerHTML = '<div class="text-center" style="padding:40px"><div class="spinner"></div></div>';

  try {
    const r = await fetch(`api/duplicates.php?project_id=${DUP_PROJECT_ID}&status=${status}`);
    const d = await r.json();

    // Atualiza stats
    const s = d.summary || {};
    const pending   = s.pending   || 0;
    const confirmed = s.confirmed || 0;
    const rejected  = s.rejected  || 0;
    const unique    = s.unique_count ?? '—';

    updateStatCard('stat-pending',   pending);
    updateStatCard('stat-confirmed', confirmed);
    updateStatCard('stat-rejected',  rejected);
    updateStatCard('stat-unique',    unique);

    const countEl = document.getElementById('tab-count-pending');
    if (countEl) countEl.textContent = pending;

    // Botão confirmar todos só aparece se há pendentes
    const btnAll = document.getElementById('btn-confirm-all');
    if (btnAll) btnAll.style.display = pending > 0 ? '' : 'none';

    // Banner informativo
    const banner = document.getElementById('info-banner');
    const bannerText = document.getElementById('info-banner-text');
    if (banner && bannerText && typeof unique === 'number') {
      const total = pending + confirmed + rejected;
      if (total > 0 || unique > 0) {
        bannerText.innerHTML = `<strong>${unique} artigo(s) único(s)</strong> prontos para triagem. ` +
          `${confirmed} duplicata(s) confirmada(s) e removida(s) do fluxo. ` +
          `Sobreposição entre bases (Scopus ↔ WoS) é <strong>esperada e normal</strong> em revisões multi-base.`;
        banner.style.display = '';
      }
    }

    if (!d.pairs.length) {
      container.innerHTML = `
        <div class="empty-state">
          <i class="fa fa-check-circle" style="color:var(--accent-green)"></i>
          <p>${status === 'pending' ? 'Nenhuma duplicata pendente.' : 'Nenhum registro neste status.'}</p>
          ${status === 'pending' ? '<small>Execute a detecção para encontrar possíveis duplicatas.</small>' : ''}
        </div>`;
      return;
    }

    let html = '<div style="display:flex;flex-direction:column;gap:12px">';
    d.pairs.forEach(pair => {
      const scoreColor = pair.match_score >= 95 ? 'var(--accent-red)' : pair.match_score >= 85 ? 'var(--accent-yellow)' : 'var(--primary)';
      html += `
        <div class="card" id="pair-${pair.id}">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:14px">
            <div style="display:flex;gap:10px;align-items:center">
              <span class="badge" style="background:${scoreColor}33;color:${scoreColor};font-size:0.82rem">
                ${pair.match_score}% ${pair.match_type === 'doi' ? '🔑 DOI' : pair.match_type === 'title' ? '📝 Título' : '🔗 Combinado'}
              </span>
            </div>
            ${status === 'pending' ? `
            <div style="display:flex;gap:8px">
              <button class="btn btn-danger btn-sm" onclick="confirmDup(${pair.id}, ${pair.article_id_1})">
                <i class="fa fa-check"></i> Confirmar (manter A)
              </button>
              <button class="btn btn-danger btn-sm" onclick="confirmDup(${pair.id}, ${pair.article_id_2})">
                <i class="fa fa-check"></i> Confirmar (manter B)
              </button>
              <button class="btn btn-success btn-sm" onclick="rejectDup(${pair.id})">
                <i class="fa fa-times"></i> Não é duplicata
              </button>
            </div>` : ''}
          </div>
          <div class="grid-2" style="gap:14px">
            <div style="background:var(--bg-input);padding:12px;border-radius:var(--radius);border:1px solid var(--border)">
              <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:4px">ARTIGO A · ${pair.source_1?.toUpperCase()}</div>
              <div style="font-weight:600;font-size:0.875rem;margin-bottom:6px;line-height:1.3">${escHtml(pair.title_1)}</div>
              <div style="font-size:0.78rem;color:var(--text-secondary)">
                ${pair.year_1||'s/a'} · ${escHtml(pair.journal_1||'—')}
                ${pair.doi_1 ? `· <a href="https://doi.org/${escAttr(pair.doi_1)}" target="_blank" class="text-muted">DOI</a>` : ''}
              </div>
            </div>
            <div style="background:var(--bg-input);padding:12px;border-radius:var(--radius);border:1px solid var(--border)">
              <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:4px">ARTIGO B · ${pair.source_2?.toUpperCase()}</div>
              <div style="font-weight:600;font-size:0.875rem;margin-bottom:6px;line-height:1.3">${escHtml(pair.title_2)}</div>
              <div style="font-size:0.78rem;color:var(--text-secondary)">
                ${pair.year_2||'s/a'} · ${escHtml(pair.journal_2||'—')}
                ${pair.doi_2 ? `· <a href="https://doi.org/${escAttr(pair.doi_2)}" target="_blank" class="text-muted">DOI</a>` : ''}
              </div>
            </div>
          </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
  } catch(e) {
    container.innerHTML = `<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> Erro: ${e.message}</div>`;
  }
}

function updateStatCard(id, val) {
  const el = document.querySelector(`#${id} .stat-value`);
  if (el) el.textContent = val;
}

async function confirmDup(pairId, canonicalId) {
  try {
    const r = await fetch(`api/duplicates.php?id=${pairId}`, {
      method: 'PUT',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'confirm', canonical_id: canonicalId })
    });
    const res = await r.json();
    if (res.error) throw new Error(res.message);
    showToast('Duplicata confirmada', 'success');
    document.getElementById(`pair-${pairId}`)?.remove();
    loadDuplicates('pending');
  } catch(e) {
    showToast('Erro: ' + e.message, 'error');
  }
}

async function rejectDup(pairId) {
  try {
    const r = await fetch(`api/duplicates.php?id=${pairId}`, {
      method: 'PUT',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'reject' })
    });
    const res = await r.json();
    if (res.error) throw new Error(res.message);
    showToast('Par marcado como não-duplicata', 'success');
    document.getElementById(`pair-${pairId}`)?.remove();
    loadDuplicates('pending');
  } catch(e) {
    showToast('Erro: ' + e.message, 'error');
  }
}

async function confirmAllPending() {
  const pending = parseInt(document.getElementById('tab-count-pending')?.textContent || '0');
  if (!pending) { showToast('Nenhum par pendente', 'info'); return; }
  if (!confirm(`Confirmar os ${pending} pares pendentes? Para cada par, o artigo A será mantido como canônico.`)) return;

  const btn = document.getElementById('btn-confirm-all');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner"></div> Processando...';

  try {
    const r = await fetch('api/duplicates.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'confirm_all_pending', project_id: DUP_PROJECT_ID })
    });
    const res = await r.json();
    if (res.error) throw new Error(res.message);
    showToast(`${res.confirmed} par(es) confirmado(s) com sucesso`, 'success');
    loadDuplicates('pending');
  } catch(e) {
    showToast('Erro: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check-double"></i> Confirmar todos pendentes';
  }
}

async function resetDetection() {
  if (!confirm('Redefinir toda a detecção de duplicatas?\n\nTodos os pares serão removidos e os artigos duplicados voltarão ao status "pendente".')) return;

  try {
    const r = await fetch(`api/duplicates.php?project_id=${DUP_PROJECT_ID}`, { method: 'DELETE' });
    const res = await r.json();
    if (res.error) throw new Error(res.message);
    showToast('Detecção redefinida. Execute novamente para redetectar.', 'success');
    loadDuplicates('pending');
  } catch(e) {
    showToast('Erro: ' + e.message, 'error');
  }
}

loadDuplicates();
</script>
<?php endif; ?>
