<?php
/**
 * PRISMA-SLR - Diagrama de Fluxo PRISMA 2020
 */
if (!$projectId): ?>
<div class="empty-state"><i class="fa fa-triangle-exclamation"></i>
  <p>Selecione um projeto para ver o diagrama PRISMA.</p></div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-diagram-project"></i> Diagrama PRISMA 2020</h1>
    <p class="page-subtitle" id="pf-subtitle">Fluxo de identificação, triagem, elegibilidade e inclusão de estudos.</p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <button class="btn btn-outline" onclick="exportDiagram('png')">
      <i class="fa fa-image"></i> <span id="pf-btn-png">Exportar PNG</span>
    </button>
    <button class="btn btn-ghost" onclick="exportDiagram('svg')">
      <i class="fa fa-file-code"></i> <span id="pf-btn-svg">Exportar SVG</span>
    </button>
    <button class="btn btn-ghost" onclick="loadFlow()">
      <i class="fa fa-rotate"></i> <span id="pf-btn-refresh">Atualizar</span>
    </button>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 260px;gap:20px;align-items:start;min-width:0">
  <!-- Diagrama: ocupa todo espaço restante, scroll interno se SVG for largo -->
  <div class="card" style="min-width:0;overflow:hidden;padding:24px">
    <div id="prisma-loading" class="text-center" style="padding:60px"><div class="spinner"></div></div>
    <div style="overflow-x:auto">
      <div id="prisma-diagram" style="display:none;min-width:580px"></div>
    </div>
  </div>

  <!-- Painel de dados: coluna fixa 260px, nunca sai da tela -->
  <div>
    <div class="card" style="margin-bottom:12px">
      <h3 class="card-title" style="font-size:0.85rem" id="pf-panel-id">Identificação</h3>
      <div id="detail-identification" class="detail-section"></div>
    </div>
    <div class="card" style="margin-bottom:12px">
      <h3 class="card-title" style="font-size:0.85rem" id="pf-panel-sc">Triagem</h3>
      <div id="detail-screening" class="detail-section"></div>
    </div>
    <div class="card" style="margin-bottom:12px">
      <h3 class="card-title" style="font-size:0.85rem" id="pf-panel-el">Elegibilidade</h3>
      <div id="detail-eligibility" class="detail-section"></div>
    </div>
    <div class="card">
      <h3 class="card-title" style="font-size:0.85rem" id="pf-panel-inc">Incluídos</h3>
      <div id="detail-included" class="detail-section"></div>
    </div>
  </div>
</div>

<!-- Canvas oculto para export -->
<canvas id="export-canvas" style="display:none"></canvas>

<style>
.detail-section { font-size:0.82rem; }
.detail-row { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; padding:5px 0; border-bottom:1px solid var(--border); }
.detail-row:last-child { border-bottom:none; }
.detail-row > span:first-child { flex:1; min-width:0; word-break:break-word; overflow-wrap:break-word; line-height:1.35; }
.detail-row > span:last-child { flex-shrink:0; text-align:right; }
.detail-num { font-weight:700; color:var(--primary); }
.prisma-box { cursor:default; }
/* Print: garante fundo branco na exportação */
@media print {
  #prisma-diagram svg { background:#fff !important; }
}
</style>

<script>
const PF_PROJECT_ID = <?= $projectId ?>;

// ── Translations ────────────────────────────────────────────────
const PF_T = {
  pt: {
    // Page
    subtitle:     'Fluxo de identificação, triagem, elegibilidade e inclusão de estudos.',
    btn_png:      'Exportar PNG',
    btn_svg:      'Exportar SVG',
    btn_refresh:  'Atualizar',
    // Side panel headers
    panel_id:     'Identificação',
    panel_sc:     'Triagem',
    panel_el:     'Elegibilidade',
    panel_inc:    'Incluídos',
    // Detail rows
    total_identified:     'Total identificados',
    duplicates_removed:   'Duplicatas removidas',
    unique_screening:     'Únicos para triagem',
    screened:             'Avaliados',
    included_sc:          'Incluídos',
    excluded_sc:          'Excluídos',
    fulltext_assessed:    'Texto completo avaliados',
    excluded_ft:          'Excluídos',
    studies_included:     'Estudos incluídos',
    no_reason:            'Sem motivo',
    // SVG diagram boxes
    d_records_id:    'Registros identificados',
    d_for_screening: 'Para triagem (únicos)',
    d_dup_removed:   'Duplicatas removidas',
    d_for_elig:      'Para elegibilidade',
    d_exc_sc:        'Excluídos na triagem',
    d_exc_ft:        'Excluídos (texto completo)',
    d_studies_inc:   'Estudos incluídos',
    d_phase_id:      'IDENTIFICAÇÃO',
    d_phase_sc:      'TRIAGEM',
    d_phase_el:      'ELEGIBILIDADE',
    d_phase_inc:     'INCLUÍDOS',
    d_see_reasons:   '▼ ver motivos (tooltip)',
    d_no_db:         'Bases não disponíveis',
    // Export file name
    filename:        'prisma-flow',
  },
  en: {
    subtitle:     'Identification, screening, eligibility and inclusion flow.',
    btn_png:      'Export PNG',
    btn_svg:      'Export SVG',
    btn_refresh:  'Refresh',
    panel_id:     'Identification',
    panel_sc:     'Screening',
    panel_el:     'Eligibility',
    panel_inc:    'Included',
    total_identified:     'Total identified',
    duplicates_removed:   'Duplicates removed',
    unique_screening:     'Unique for screening',
    screened:             'Assessed',
    included_sc:          'Included',
    excluded_sc:          'Excluded',
    fulltext_assessed:    'Full text assessed',
    excluded_ft:          'Excluded',
    studies_included:     'Studies included',
    no_reason:            'No reason',
    d_records_id:    'Records identified',
    d_for_screening: 'For screening (unique)',
    d_dup_removed:   'Duplicates removed',
    d_for_elig:      'For eligibility',
    d_exc_sc:        'Excluded in screening',
    d_exc_ft:        'Excluded (full text)',
    d_studies_inc:   'Studies included',
    d_phase_id:      'IDENTIFICATION',
    d_phase_sc:      'SCREENING',
    d_phase_el:      'ELIGIBILITY',
    d_phase_inc:     'INCLUDED',
    d_see_reasons:   '▼ see reasons (tooltip)',
    d_no_db:         'Databases not available',
    filename:        'prisma-flow-en',
  },
  es: {
    subtitle:     'Flujo de identificación, cribado, elegibilidad e inclusión de estudios.',
    btn_png:      'Exportar PNG',
    btn_svg:      'Exportar SVG',
    btn_refresh:  'Actualizar',
    panel_id:     'Identificación',
    panel_sc:     'Cribado',
    panel_el:     'Elegibilidad',
    panel_inc:    'Incluidos',
    total_identified:     'Total identificados',
    duplicates_removed:   'Duplicados eliminados',
    unique_screening:     'Únicos para cribado',
    screened:             'Evaluados',
    included_sc:          'Incluidos',
    excluded_sc:          'Excluidos',
    fulltext_assessed:    'Texto completo evaluado',
    excluded_ft:          'Excluidos',
    studies_included:     'Estudios incluidos',
    no_reason:            'Sin motivo',
    d_records_id:    'Registros identificados',
    d_for_screening: 'Para cribado (únicos)',
    d_dup_removed:   'Duplicados eliminados',
    d_for_elig:      'Para elegibilidad',
    d_exc_sc:        'Excluidos en cribado',
    d_exc_ft:        'Excluidos (texto completo)',
    d_studies_inc:   'Estudios incluidos',
    d_phase_id:      'IDENTIFICACIÓN',
    d_phase_sc:      'CRIBADO',
    d_phase_el:      'ELEGIBILIDAD',
    d_phase_inc:     'INCLUIDOS',
    d_see_reasons:   '▼ ver motivos (tooltip)',
    d_no_db:         'Bases no disponibles',
    filename:        'prisma-flow-es',
  },
};

function getT() { return PF_T[window.PRISMA_LANG || 'pt']; }

function applyPFLang() {
  const t = getT();
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('pf-subtitle',    t.subtitle);
  set('pf-btn-png',     t.btn_png);
  set('pf-btn-svg',     t.btn_svg);
  set('pf-btn-refresh', t.btn_refresh);
  set('pf-panel-id',    t.panel_id);
  set('pf-panel-sc',    t.panel_sc);
  set('pf-panel-el',    t.panel_el);
  set('pf-panel-inc',   t.panel_inc);
}

let flowData = null;

async function loadFlow() {
  document.getElementById('prisma-loading').style.display = 'flex';
  document.getElementById('prisma-diagram').style.display = 'none';
  try {
    const r = await fetch(`api/prisma-flow.php?project_id=${PF_PROJECT_ID}`);
    flowData = await r.json();
    if (flowData.error) throw new Error(flowData.message);
    applyPFLang();
    renderDiagram(flowData.prisma);
    renderDetails(flowData.prisma);
  } catch(e) {
    document.getElementById('prisma-loading').innerHTML = `<div class="alert alert-error">Erro: ${e.message}</div>`;
  }
}

function renderDetails(d) {
  const t = getT();

  // Identificação
  let id_html = `<div class="detail-row"><span>${t.total_identified}</span><span class="detail-num">${d.identification.total_identified}</span></div>`;
  (d.identification.by_database||[]).forEach(b => {
    id_html += `<div class="detail-row" style="padding-left:12px"><span>${escHtml(b.name)}</span><span>${b.count}</span></div>`;
  });
  id_html += `<div class="detail-row"><span>${t.duplicates_removed}</span><span class="detail-num">${d.identification.duplicates_removed}</span></div>`;
  id_html += `<div class="detail-row"><span>${t.unique_screening}</span><span class="detail-num">${d.identification.unique_after_dedup}</span></div>`;
  document.getElementById('detail-identification').innerHTML = id_html;

  // Triagem
  let sc_html = `<div class="detail-row"><span>${t.screened}</span><span class="detail-num">${d.screening.records_screened}</span></div>`;
  sc_html += `<div class="detail-row"><span>${t.included_sc}</span><span class="detail-num" style="color:var(--accent-green)">${d.screening.records_included}</span></div>`;
  sc_html += `<div class="detail-row"><span>${t.excluded_sc}</span><span class="detail-num" style="color:var(--accent-red)">${d.screening.records_excluded}</span></div>`;
  (d.screening.exclusion_reasons||[]).forEach(r => {
    sc_html += `<div class="detail-row" style="padding-left:12px;font-size:0.75rem"><span>${escHtml(r.reason||t.no_reason)}</span><span>${r.total}</span></div>`;
  });
  document.getElementById('detail-screening').innerHTML = sc_html;

  // Elegibilidade
  let el_html = `<div class="detail-row"><span>${t.fulltext_assessed}</span><span class="detail-num">${d.eligibility.assessed_full_text}</span></div>`;
  el_html += `<div class="detail-row"><span>${t.excluded_ft}</span><span class="detail-num">${d.eligibility.excluded_full_text}</span></div>`;
  (d.eligibility.exclusion_reasons||[]).forEach(r => {
    el_html += `<div class="detail-row" style="padding-left:12px"><span>${escHtml(r.reason||t.no_reason)}</span><span>${r.total}</span></div>`;
  });
  document.getElementById('detail-eligibility').innerHTML = el_html;

  // Incluídos
  document.getElementById('detail-included').innerHTML = `
    <div class="detail-row"><span>${t.studies_included}</span><span class="detail-num">${d.included.studies_included}</span></div>`;
}

function renderDiagram(d) {
  const id   = d.identification;
  const sc   = d.screening;
  const el   = d.eligibility;
  const inc  = d.included;

  const scExc      = sc.records_excluded || 0;
  const scIncluded = sc.records_included || 0;
  const elExc      = el.excluded_full_text || 0;

  // ── Layout ──────────────────────────────────────────────────────
  const W    = 820;
  const BW   = 230;   // main box width
  const BH   = 66;    // main box height
  const EBW  = 210;   // exclusion box width
  const EBH  = 76;    // exclusion box height
  const PBW  = 30;    // phase bar width (narrow strip with rotated text)

  const mainCX = 255;
  const col1   = mainCX - BW / 2;
  const excX   = mainCX + BW/2 + 60;

  // Y positions
  const yDB    = 30;
  const yTotal = 160;
  const yDedup = 215;
  const yScrn  = 285;
  const yExcSc = 365;
  const yElig  = 470;
  const yExcEl = 555;
  const yInc   = 660;

  const svgH = yInc + BH + 50;  // extra space so INCLUÍDOS bar fits rotated text

  // ── Colors ──────────────────────────────────────────────────────
  const ACC  = '#00D4FF';
  const RED  = '#ff4d6a';
  const DB_C = '#3b5bdb';
  const C_ID = '#0d1b3e';
  const C_SC = '#0b1e36';
  const C_EL = '#0b2010';
  const C_IN = '#0c2218';
  const C_EX = '#2a0f15';

  const t = getT();

  // ── Exclusion reason lines ───────────────────────────────────────
  const scReasonLines = (sc.exclusion_reasons||[])
    .filter(r => r.total > 0)
    .map(r => `• ${r.reason||t.no_reason}: ${r.total}`)
    .join('&#10;');
  const elReasonLines = (el.exclusion_reasons||[])
    .filter(r => r.total > 0)
    .map(r => `• ${r.reason||t.no_reason}: ${r.total}`)
    .join('&#10;');

  // ── Build DB boxes ───────────────────────────────────────────────
  const dbs    = id.by_database || [];
  const dbBW   = dbs.length <= 2 ? 170 : dbs.length <= 4 ? 140 : 120;
  const dbGap  = 14;
  const dbTotalW = dbs.length * dbBW + (dbs.length - 1) * dbGap;
  const dbStartX = mainCX - dbTotalW / 2;

  let dbHtml = '';
  dbs.forEach((s, i) => {
    const bx = dbStartX + i * (dbBW + dbGap);
    const bcx = bx + dbBW / 2;
    dbHtml += dbBox(bx, yDB, dbBW, BH, s.count, escHtml(s.name));
    // Converging line from DB box to Total box
    const targetX = mainCX;
    const targetY = yTotal;
    dbHtml += `<path d="M${bcx},${yDB+BH} L${bcx},${yDB+BH+14} L${targetX},${targetY-8}"
      fill="none" stroke="${DB_C}" stroke-width="1.5" opacity="0.7"
      marker-end="url(#arr-db)"/>`;
  });
  // Fallback if no DB info
  if (!dbs.length) {
    dbHtml = `<text x="${mainCX}" y="${yDB+BH/2+5}" text-anchor="middle" font-size="11" fill="#64748b">${t.d_no_db}</text>`;
  }

  // ── Phase label bars (left) — texto em 90° ─────────────────────
  const phases = [
    { y: yDB,    h: yTotal - yDB + BH + 14,     label: t.d_phase_id,  color: '#3b5bdb' },
    { y: yScrn,  h: yElig  - yScrn + 6,         label: t.d_phase_sc,  color: '#1971c2' },
    { y: yElig,  h: yInc   - yElig + 6,         label: t.d_phase_el,  color: '#0b7285' },
    { y: yInc,   h: BH + 46,                    label: t.d_phase_inc, color: '#2f9e44' },
  ];
  const phaseHtml = phases.map(p => phaseBar(4, p.y, PBW, p.h, p.label, p.color)).join('');

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${svgH}"
      id="prisma-svg" style="width:100%;max-width:${W}px;font-family:Inter,sans-serif;background:#fff">
    <defs>
      <marker id="arr" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
        <path d="M0,0 L6,3 L0,6 Z" fill="${ACC}"/>
      </marker>
      <marker id="arr-red" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
        <path d="M0,0 L6,3 L0,6 Z" fill="${RED}"/>
      </marker>
      <marker id="arr-db" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
        <path d="M0,0 L6,3 L0,6 Z" fill="${DB_C}" opacity="0.7"/>
      </marker>
    </defs>
    <!-- Fundo branco garantido para impressão e exportação -->
    <rect width="${W}" height="${svgH}" fill="#ffffff"/>

    <!-- Phase bars -->
    ${phaseHtml}

    <!-- DB boxes -->
    ${dbHtml}

    <!-- Total identificado -->
    ${flowBox(col1, yTotal, BW, BH, C_ID, ACC, id.total_identified, t.d_records_id)}

    <!-- Dedup arrow right -->
    ${arrowRight(mainCX + BW/2, yDedup + EBH/2, excX, yDedup + EBH/2, RED)}
    <!-- Dedup box -->
    ${excBox(excX, yDedup, EBW, EBH-10, C_EX, RED, id.duplicates_removed, t.d_dup_removed, '')}

    <!-- Arrow down to screening -->
    ${arrowDown(mainCX, yTotal + BH, yScrn, ACC)}

    <!-- Para triagem -->
    ${flowBox(col1, yScrn, BW, BH, C_SC, '#1971c2', id.unique_after_dedup, t.d_for_screening)}

    <!-- Arrow down to eligibility -->
    ${arrowDown(mainCX, yScrn + BH, yElig, ACC)}

    <!-- Excluídos triagem (right) -->
    ${arrowRight(mainCX + BW/2, yExcSc + EBH/2, excX, yExcSc + EBH/2, RED)}
    ${excBox(excX, yExcSc, EBW, EBH, C_EX, RED, scExc, t.d_exc_sc, scReasonLines)}

    <!-- Para elegibilidade -->
    ${flowBox(col1, yElig, BW, BH, C_EL, '#0b7285', scIncluded, t.d_for_elig)}

    <!-- Excluídos elegibilidade (right) -->
    ${arrowRight(mainCX + BW/2, yExcEl + EBH/2, excX, yExcEl + EBH/2, RED)}
    ${excBox(excX, yExcEl, EBW, EBH, C_EX, RED, elExc, t.d_exc_ft, elReasonLines)}

    <!-- Arrow down to included -->
    ${arrowDown(mainCX, yElig + BH, yInc, ACC)}

    <!-- Incluídos -->
    ${flowBox(col1, yInc, BW, BH, C_IN, '#2f9e44', inc.studies_included, t.d_studies_inc)}
  </svg>`;

  document.getElementById('prisma-loading').style.display = 'none';
  const diag = document.getElementById('prisma-diagram');
  diag.style.display = 'block';
  diag.innerHTML = svg;
}

// ── SVG primitives ──────────────────────────────────────────────────────────

function phaseBar(x, y, w, h, label, color) {
  const midX = x + w / 2;
  const midY = y + h / 2;
  return `<g>
    <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="5"
          fill="${color}" opacity="0.13"/>
    <rect x="${x}" y="${y}" width="4" height="${h}" rx="2"
          fill="${color}" opacity="0.9"/>
    <text x="${midX}" y="${midY}"
          text-anchor="middle" dominant-baseline="central"
          font-size="9" font-weight="800" letter-spacing="1.4"
          fill="${color}" opacity="0.95"
          transform="rotate(-90,${midX},${midY})">${label}</text>
  </g>`;
}

function flowBox(x, y, w, h, fill, stroke, num, label) {
  const cx = x + w/2;
  return `<g class="prisma-box">
    <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="8"
          fill="${fill}" stroke="${stroke}" stroke-width="2"/>
    <text x="${cx}" y="${y+h/2-4}" text-anchor="middle"
          font-size="22" font-weight="800" fill="${stroke}">${num}</text>
    <text x="${cx}" y="${y+h/2+16}" text-anchor="middle"
          font-size="11" fill="#64748b">${label}</text>
  </g>`;
}

function excBox(x, y, w, h, fill, stroke, num, label, reasons) {
  const cx = x + w/2;
  const tooltip = reasons ? `<title>${reasons}</title>` : '';
  const hasReasons = reasons && reasons.length > 0;
  const seeReasons = getT().d_see_reasons;
  return `<g class="prisma-box">${tooltip}
    <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="8"
          fill="${fill}" stroke="${stroke}" stroke-width="2" stroke-dasharray="5,3"/>
    <text x="${cx}" y="${y+h/2-6}" text-anchor="middle"
          font-size="18" font-weight="700" fill="${stroke}">${num}</text>
    <text x="${cx}" y="${y+h/2+11}" text-anchor="middle"
          font-size="10" fill="#64748b">${label}</text>
    ${hasReasons ? `<text x="${cx}" y="${y+h-8}" text-anchor="middle"
          font-size="8.5" fill="${stroke}" opacity="0.7">${seeReasons}</text>` : ''}
  </g>`;
}

function dbBox(x, y, w, h, count, name) {
  const cx = x + w/2;
  const DB_C = '#3b5bdb';
  return `<g class="prisma-box">
    <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="8"
          fill="#0f1a3a" stroke="${DB_C}" stroke-width="2"/>
    <text x="${cx}" y="${y+h/2-4}" text-anchor="middle"
          font-size="20" font-weight="800" fill="${DB_C}">${count}</text>
    <text x="${cx}" y="${y+h/2+14}" text-anchor="middle"
          font-size="11" fill="#64748b">${name}</text>
  </g>`;
}

function arrowDown(cx, y1, y2, color) {
  return `<line x1="${cx}" y1="${y1}" x2="${cx}" y2="${y2-6}"
    stroke="${color}" stroke-width="2"
    marker-end="url(#${color === '#ff4d6a' ? 'arr-red':'arr'})"/>`;
}

function arrowRight(x1, y1, x2, y2, color) {
  return `<line x1="${x1}" y1="${y1}" x2="${x2-6}" y2="${y2}"
    stroke="${color}" stroke-width="1.5" stroke-dasharray="5,3"
    marker-end="url(#arr-red)"/>`;
}

function exportDiagram(format) {
  const svgEl = document.getElementById('prisma-svg');
  if (!svgEl) { showToast('Gere o diagrama primeiro', 'warning'); return; }
  const t = getT();
  const svgData = new XMLSerializer().serializeToString(svgEl);
  const fname = t.filename;

  if (format === 'svg') {
    const blob = new Blob([svgData], {type: 'image/svg+xml'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = fname + '.svg'; a.click();
    URL.revokeObjectURL(url);
    showToast('SVG exported / exportado', 'success');
    return;
  }

  // PNG via canvas — white background for publication
  const img = new Image();
  const svgBlob = new Blob([svgData], {type: 'image/svg+xml;charset=utf-8'});
  const url = URL.createObjectURL(svgBlob);
  img.onload = () => {
    const canvas = document.getElementById('export-canvas');
    canvas.width  = svgEl.viewBox.baseVal.width * 2;
    canvas.height = svgEl.viewBox.baseVal.height * 2;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    URL.revokeObjectURL(url);
    canvas.toBlob(blob => {
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = fname + '.png'; a.click();
      showToast('PNG exported / exportado', 'success');
    }, 'image/png');
  };
  img.src = url;
}

// Re-render when language changes (triggered by header lang switcher)
document.addEventListener('prismaLangChanged', () => {
  applyPFLang();
  if (flowData) {
    renderDiagram(flowData.prisma);
    renderDetails(flowData.prisma);
  }
});

loadFlow();
</script>
<?php endif; ?>
