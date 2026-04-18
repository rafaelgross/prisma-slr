<?php
/**
 * PRISMA-SLR - Bibliometria
 */
if (!$projectId): ?>
<div class="empty-state"><i class="fa fa-triangle-exclamation"></i>
  <p>Selecione um projeto para ver as análises bibliométricas.</p></div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-chart-bar"></i> Bibliometria</h1>
    <p class="page-subtitle">Análises gráficas e estatísticas sobre a base de artigos do projeto.</p>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <label style="font-size:0.82rem;color:var(--text-muted)">Escopo:</label>
    <select class="form-control" id="bib-scope" style="width:auto" onchange="reloadAll()">
      <option value="all">Todos (não-duplicatas)</option>
      <option value="included">Apenas incluídos</option>
    </select>
    <button class="btn btn-outline btn-sm" onclick="reloadAll()"><i class="fa fa-rotate"></i> Atualizar</button>
  </div>
</div>

<!-- Grid de gráficos -->
<div class="charts-grid">

  <!-- Publicações por Ano -->
  <div class="chart-card" id="chart-card-publications_year">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-chart-line"></i> Publicações por Ano</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-publications_year','publicacoes-por-ano.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper"><canvas id="chart-publications_year"></canvas></div>
  </div>

  <!-- Distribuição por Base -->
  <div class="chart-card" id="chart-card-source_types">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-database"></i> Distribuição por Base</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-source_types','bases.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper"><canvas id="chart-source_types"></canvas></div>
  </div>

  <!-- Tipos de Documento -->
  <div class="chart-card" id="chart-card-document_types">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-file-alt"></i> Tipos de Documento</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-document_types','tipos-documento.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper"><canvas id="chart-document_types"></canvas></div>
  </div>

  <!-- Acesso Aberto -->
  <div class="chart-card" id="chart-card-open_access">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-lock-open"></i> Acesso Aberto</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-open_access','acesso-aberto.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper"><canvas id="chart-open_access"></canvas></div>
  </div>

  <!-- Top Periódicos (full width) -->
  <div class="chart-card chart-card-wide" id="chart-card-top_journals">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-journal-whills"></i> Top 15 Periódicos</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-top_journals','periodicos.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper chart-wrapper-tall"><canvas id="chart-top_journals"></canvas></div>
  </div>

  <!-- Top Autores (full width) -->
  <div class="chart-card chart-card-wide" id="chart-card-top_authors">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-users"></i> Top 15 Autores com Mais Publicações</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-top_authors','autores.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper chart-wrapper-tall"><canvas id="chart-top_authors"></canvas></div>
  </div>

  <!-- Top Países -->
  <div class="chart-card" id="chart-card-top_countries">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-globe"></i> Top Países</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-top_countries','paises.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper"><canvas id="chart-top_countries"></canvas></div>
  </div>

  <!-- Distribuição de Citações -->
  <div class="chart-card" id="chart-card-citations_distribution">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-quote-left"></i> Distribuição de Citações</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-citations_distribution','citacoes.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper"><canvas id="chart-citations_distribution"></canvas></div>
  </div>

  <!-- Top Editoras -->
  <div class="chart-card" id="chart-card-top_publishers">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-building"></i> Top Editoras</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-top_publishers','editoras.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper"><canvas id="chart-top_publishers"></canvas></div>
  </div>

  <!-- Avaliação por Estrelas -->
  <div class="chart-card" id="chart-card-ratings_distribution">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-star"></i> Relevância dos Incluídos (★)</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-ratings_distribution','relevancia.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper"><canvas id="chart-ratings_distribution"></canvas></div>
  </div>

  <!-- Keywords Frequentes (full width) -->
  <div class="chart-card chart-card-wide" id="chart-card-keywords_frequency">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-tags"></i> Palavras-chave Mais Frequentes</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-keywords_frequency','keywords.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper chart-wrapper-tall"><canvas id="chart-keywords_frequency"></canvas></div>
  </div>

  <!-- Evolução de Keywords (full width) -->
  <div class="chart-card chart-card-wide" id="chart-card-keywords_by_year">
    <div class="chart-card-header">
      <h3 class="chart-title"><i class="fa fa-chart-area"></i> Evolução de Keywords por Ano</h3>
      <button class="btn btn-ghost btn-sm" onclick="exportCanvasPng('chart-keywords_by_year','keywords-ano.png')" title="Exportar PNG"><i class="fa fa-download"></i></button>
    </div>
    <div class="chart-wrapper"><canvas id="chart-keywords_by_year"></canvas></div>
  </div>
</div>

<!-- Tabela: Artigos Mais Citados -->
<div class="chart-card chart-card-full" style="margin-top:20px" id="section-top-cited">
  <div class="chart-card-header">
    <h3 class="chart-title"><i class="fa fa-trophy"></i> Top 10 Artigos Mais Citados</h3>
  </div>
  <div id="top-cited-table" style="overflow-x:auto">
    <div class="text-center" style="padding:30px"><div class="spinner"></div></div>
  </div>
</div>

<!-- Rede de Co-autoria -->
<div class="chart-card chart-card-full" style="margin-top:20px">
  <div class="chart-card-header">
    <h3 class="chart-title"><i class="fa fa-network-wired"></i> Rede de Co-autoria</h3>
    <div style="display:flex;gap:8px;align-items:center">
      <label style="font-size:0.78rem;color:var(--text-muted)">Mín. artigos:</label>
      <select class="form-control" id="coauth-min" style="width:auto;font-size:0.78rem" onchange="loadCoauthorship()">
        <option value="1">1 artigo</option>
        <option value="2" selected>2 artigos</option>
        <option value="3">3 artigos</option>
      </select>
      <button class="btn btn-ghost btn-sm" onclick="loadCoauthorship()"><i class="fa fa-rotate"></i></button>
    </div>
  </div>
  <div id="network-coauthorship" style="height:480px;background:var(--bg-card2);border-radius:8px;position:relative">
    <div class="text-center" style="padding:60px"><div class="spinner"></div></div>
  </div>
</div>

<!-- Co-ocorrência de Keywords -->
<div class="chart-card chart-card-full" style="margin-top:20px">
  <div class="chart-card-header">
    <h3 class="chart-title"><i class="fa fa-diagram-project"></i> Co-ocorrência de Keywords</h3>
    <div style="display:flex;gap:8px;align-items:center">
      <label style="font-size:0.78rem;color:var(--text-muted)">Mín. ocorrências:</label>
      <select class="form-control" id="kwnet-min" style="width:auto;font-size:0.78rem" onchange="loadKeywordNetwork()">
        <option value="2" selected>2</option>
        <option value="3">3</option>
        <option value="5">5</option>
      </select>
      <button class="btn btn-ghost btn-sm" onclick="loadKeywordNetwork()"><i class="fa fa-rotate"></i></button>
    </div>
  </div>
  <div id="network-keywords" style="height:480px;background:var(--bg-card2);border-radius:8px;position:relative">
    <div class="text-center" style="padding:60px"><div class="spinner"></div></div>
  </div>
</div>

<script>
const BIB_PROJECT_ID = <?= $projectId ?>;
const chartInstances  = {};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getScope() {
  return document.getElementById('bib-scope').value;
}

function bibColors(n) {
  const base = [
    'rgba(0,212,255,.82)','rgba(0,229,160,.82)','rgba(116,192,252,.82)',
    'rgba(229,153,247,.82)','rgba(255,212,59,.82)','rgba(255,135,135,.82)',
    'rgba(255,180,80,.82)','rgba(80,200,120,.82)','rgba(200,120,200,.82)',
    'rgba(120,160,255,.82)','rgba(255,100,80,.82)','rgba(160,220,60,.82)',
  ];
  return Array.from({length: n}, (_, i) => base[i % base.length]);
}

function axis(extra = {}) {
  const isLight = document.body.classList.contains('theme-light');
  return {
    grid: { color: isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.05)', drawBorder: false },
    border: { display: false },
    ticks: { color: isLight ? '#475569' : '#94a3b8', font: { size: 11 } },
    ...extra,
  };
}

function destroyChart(key) {
  if (chartInstances[key]) { chartInstances[key].destroy(); delete chartInstances[key]; }
}

// ─── Transforma dados brutos da API → formato Chart.js ────────────────────────

function transformData(type, raw) {
  if (!raw) return null;

  switch (type) {
    case 'publications_year':
      return { labels: raw.map(r => r.year), datasets: [{ data: raw.map(r => +r.total) }] };

    case 'source_types':
      return { labels: raw.map(r => r.source_type), datasets: [{ data: raw.map(r => +r.total) }] };

    case 'document_types':
      return { labels: raw.map(r => r.type), datasets: [{ data: raw.map(r => +r.total) }] };

    case 'open_access':
      return {
        labels: ['Acesso Aberto', 'Acesso Restrito'],
        datasets: [{ data: [+(raw.open_access||0), +(raw.closed_access||0)] }],
      };

    case 'top_journals':
      return { labels: raw.map(r => r.journal), datasets: [{ data: raw.map(r => +r.total) }] };

    case 'top_authors':
      return { labels: raw.map(r => r.author), datasets: [{ data: raw.map(r => +r.total) }] };

    case 'top_countries':
      return { labels: raw.map(r => r.country), datasets: [{ data: raw.map(r => +r.total) }] };

    case 'top_publishers':
      return { labels: raw.map(r => r.publisher), datasets: [{ data: raw.map(r => +r.total) }] };

    case 'citations_distribution':
      return { labels: raw.map(r => r.range), datasets: [{ data: raw.map(r => +r.total) }] };

    case 'ratings_distribution': {
      const starLabels = {1:'★ Pouco relevante',2:'★★ Relevância baixa',3:'★★★ Relevante',4:'★★★★ Muito relevante',5:'★★★★★ Altamente relevante'};
      const all = [1,2,3,4,5];
      const map = Object.fromEntries(raw.map(r => [+r.rating, +r.total]));
      return {
        labels: all.map(n => starLabels[n]),
        datasets: [{ data: all.map(n => map[n] || 0) }],
      };
    }

    case 'keywords_frequency':
      return { labels: raw.map(r => r.keyword), datasets: [{ data: raw.map(r => +r.total) }] };

    case 'keywords_by_year': {
      if (!raw.top_keywords || !raw.data) return null;
      const years = [...new Set(raw.data.map(r => r.year))].sort();
      const datasets = raw.top_keywords.map(kw => ({
        label: kw,
        data: years.map(y => {
          const found = raw.data.find(r => r.year == y && r.keyword === kw);
          return found ? +found.total : 0;
        }),
      }));
      return { labels: years, datasets };
    }

    default:
      return null;
  }
}

// ─── Constrói gráficos Chart.js ───────────────────────────────────────────────

function buildChart(type, data) {
  if (!data) return;
  const canvasId = `chart-${type}`;
  const canvas   = document.getElementById(canvasId);
  if (!canvas) return;

  destroyChart(type);
  const ctx    = canvas.getContext('2d');
  const labels = data.labels;
  const values = data.datasets[0].data;
  const n      = labels.length;
  const colors = bibColors(n);

  const isLight   = document.body.classList.contains('theme-light');
  const tickColor = isLight ? '#475569' : '#94a3b8';
  const pluginBase = {
    legend: { labels: { color: tickColor, font: { size: 11 }, padding: 14, usePointStyle: true } },
    tooltip: { mode: 'index', intersect: false },
  };

  let config;

  // ── Publicações por Ano ──────────────────────────────────────────────────
  if (type === 'publications_year') {
    config = {
      type: 'bar',
      data: { labels, datasets: [{
        label: 'Publicações',
        data: values,
        backgroundColor: 'rgba(0,212,255,0.7)',
        borderColor: '#00D4FF',
        borderWidth: 1, borderRadius: 5,
        order: 2,
      }, {
        label: 'Tendência',
        data: values,
        type: 'line',
        borderColor: 'rgba(0,229,160,0.9)',
        borderWidth: 2,
        pointRadius: 3,
        pointBackgroundColor: '#00e5a0',
        fill: false, tension: 0.4,
        order: 1,
      }]},
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { ...pluginBase, legend: { ...pluginBase.legend, position: 'top' } },
        scales: { y: { ...axis(), beginAtZero: true }, x: axis() },
      },
    };

  // ── Bases / Tipos de Documento / Acesso Aberto ───────────────────────────
  } else if (['source_types','document_types','open_access'].includes(type)) {
    const isDoughnut = type === 'document_types';
    config = {
      type: isDoughnut ? 'doughnut' : 'pie',
      data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: 'rgba(15,23,41,0.6)', hoverOffset: 8 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { ...pluginBase, legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 11 }, padding: 14, usePointStyle: true } } },
        cutout: isDoughnut ? '55%' : '0%',
      },
    };

  // ── Horizontal bars: periódicos, autores, países, editoras ──────────────
  } else if (['top_journals','top_authors','top_countries','top_publishers','citations_distribution'].includes(type)) {
    const isHoriz = type !== 'citations_distribution';
    const isCit   = type === 'citations_distribution';

    // Uma cor por tipo de gráfico com gradiente de opacidade (barra maior = mais vívida)
    const colorMap = {
      top_journals:            [0, 212, 255],
      top_authors:             [0, 229, 160],
      top_countries:           [251, 191,  36],
      top_publishers:          [232, 121, 249],
      citations_distribution:  [116, 192, 252],
    };
    const [r2, g2, b2] = colorMap[type] || [0, 212, 255];
    const maxVal = Math.max(...values, 1);
    // Opacidade proporcional ao valor: barra maior fica mais sólida
    const gradBg = values.map(v => `rgba(${r2},${g2},${b2},${(0.35 + 0.55 * (v / maxVal)).toFixed(2)})`);

    config = {
      type: 'bar',
      data: { labels, datasets: [{
        label: 'Artigos',
        data: values,
        backgroundColor: gradBg,
        borderColor:   `rgba(${r2},${g2},${b2},0.9)`,
        borderWidth:   0,
        borderRadius:  isHoriz ? 3 : 4,
        maxBarThickness: isHoriz ? 26 : 40,
        minBarLength:  4,
      }]},
      options: {
        responsive: true, maintainAspectRatio: false,
        indexAxis: isHoriz ? 'y' : 'x',
        plugins: { ...pluginBase, legend: { display: false } },
        scales: isHoriz
          ? {
              x: { ...axis(), beginAtZero: true, max: Math.ceil(maxVal * 1.18),
                   ticks: { color: '#94a3b8', font: { size: 11 }, stepSize: 1 } },
              y: { ...axis(), ticks: { color: '#94a3b8', font: { size: 11 } } },
            }
          : {
              y: { ...axis(), beginAtZero: true,
                   ...(isCit ? { type: 'logarithmic',
                     title: { display: true, text: 'Artigos (escala log)', color: '#94a3b8', font:{ size:10 } } } : {}) },
              x: axis(),
            },
      },
    };

  // ── Relevância por estrelas ──────────────────────────────────────────────
  } else if (type === 'ratings_distribution') {
    const starColors = ['rgba(156,163,175,0.7)','rgba(96,165,250,0.7)','rgba(52,211,153,0.7)','rgba(251,191,36,0.7)','rgba(245,158,11,0.9)'];
    config = {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Artigos', data: values, backgroundColor: starColors, borderWidth: 0, borderRadius: 6 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { ...pluginBase, legend: { display: false } },
        scales: { y: { ...axis(), beginAtZero: true, ticks: { stepSize: 1 } }, x: { ...axis(), ticks: { font: { size: 10 } } } },
      },
    };

  // ── Keywords Frequentes (horizontal bar) ─────────────────────────────────
  } else if (type === 'keywords_frequency') {
    config = {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Ocorrências', data: values, backgroundColor: colors, borderWidth: 0, borderRadius: 4 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { ...pluginBase, legend: { display: false } },
        scales: { x: { ...axis(), beginAtZero: true }, y: { ...axis(), ticks: { color: '#94a3b8', font: { size: 10 } } } },
      },
    };

  // ── Keywords por Ano ──────────────────────────────────────────────────────
  } else if (type === 'keywords_by_year') {
    const kColors = bibColors(data.datasets.length);
    config = {
      type: 'line',
      data: {
        labels,
        datasets: data.datasets.map((ds, i) => ({
          label: ds.label,
          data: ds.data,
          borderColor: kColors[i],
          backgroundColor: kColors[i].replace('.82', '.12'),
          fill: true, tension: 0.35, pointRadius: 4,
          borderWidth: 2,
        })),
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { ...pluginBase },
        scales: { y: { ...axis(), beginAtZero: true, stacked: false }, x: axis() },
      },
    };

  } else { return; }

  chartInstances[type] = new Chart(ctx, config);
}

// ─── Carga de cada gráfico ────────────────────────────────────────────────────

async function loadChart(type) {
  try {
    // CORRIGIDO: usa 'chart=' em vez de 'type='
    const r = await fetch(`api/bibliometrics.php?project_id=${BIB_PROJECT_ID}&chart=${type}&scope=${getScope()}`);
    const d = await r.json();
    if (d.error) return;
    const data = transformData(type, d[type]);
    if (data) buildChart(type, data);
  } catch(e) { console.error('Erro ao carregar ' + type, e); }
}

// ─── Tabela: artigos mais citados ────────────────────────────────────────────

async function loadTopCited() {
  const el = document.getElementById('top-cited-table');
  try {
    const r = await fetch(`api/bibliometrics.php?project_id=${BIB_PROJECT_ID}&chart=top_cited&scope=${getScope()}&limit=10`);
    const d = await r.json();
    const rows = d.top_cited || [];
    if (!rows.length) {
      el.innerHTML = '<div class="empty-state" style="padding:24px"><i class="fa fa-database"></i><p style="font-size:0.82rem">Dados de citação não disponíveis.<br>A base WoS exporta o campo "Times Cited".</p></div>';
      return;
    }
    let html = '<table style="width:100%;border-collapse:collapse;font-size:0.82rem"><thead><tr>'
      + '<th style="text-align:left;padding:8px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-weight:600">#</th>'
      + '<th style="text-align:left;padding:8px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-weight:600">Título</th>'
      + '<th style="text-align:left;padding:8px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-weight:600">Autores</th>'
      + '<th style="text-align:center;padding:8px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-weight:600">Ano</th>'
      + '<th style="text-align:center;padding:8px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-weight:600">Citações</th>'
      + '</tr></thead><tbody>';

    rows.forEach((a, i) => {
      const titleEl = a.doi
        ? `<a href="https://doi.org/${escAttr(a.doi)}" target="_blank" style="color:var(--primary);text-decoration:none" title="${escAttr(a.title)}">${escHtml((a.title||'').substring(0,90))}${a.title.length>90?'…':''}</a>`
        : `<span title="${escAttr(a.title)}">${escHtml((a.title||'').substring(0,90))}${(a.title||'').length>90?'…':''}</span>`;
      const authShort = (a.authors||'').split(';')[0].trim() + ((a.authors||'').includes(';') ? ' et al.' : '');
      html += `<tr style="border-bottom:1px solid var(--border);${i%2===0?'':'background:rgba(255,255,255,0.02)'}">
        <td style="padding:8px 12px;color:var(--text-muted);text-align:center;font-weight:700">${i+1}</td>
        <td style="padding:8px 12px;font-weight:500">${titleEl}</td>
        <td style="padding:8px 12px;color:var(--text-muted)">${escHtml(authShort)}</td>
        <td style="padding:8px 12px;text-align:center">${a.year||'—'}</td>
        <td style="padding:8px 12px;text-align:center">
          <span style="background:rgba(0,212,255,0.15);color:#00D4FF;border-radius:12px;padding:2px 10px;font-weight:700">${a.cited_by}</span>
        </td>
      </tr>`;
    });
    html += '</tbody></table>';
    el.innerHTML = html;
  } catch(e) {
    el.innerHTML = `<div class="alert alert-error">Erro: ${e.message}</div>`;
  }
}

// ─── Redes ────────────────────────────────────────────────────────────────────

async function loadCoauthorship() {
  const container = document.getElementById('network-coauthorship');
  container.innerHTML = '<div class="text-center" style="padding:60px"><div class="spinner"></div></div>';
  try {
    const minA = document.getElementById('coauth-min').value;
    // CORRIGIDO: usa chart= e min_articles=
    const r = await fetch(`api/bibliometrics.php?project_id=${BIB_PROJECT_ID}&chart=coauthorship&scope=${getScope()}&min_articles=${minA}`);
    const d = await r.json();
    if (!d.nodes || !d.nodes.length) {
      container.innerHTML = '<div class="empty-state"><i class="fa fa-network-wired"></i><p>Dados insuficientes. Tente reduzir o mínimo de artigos.</p></div>';
      return;
    }
    container.innerHTML = '';
    // CORRIGIDO: mapeia full_name → label e article_count → value
    const nodes = d.nodes.map(n => ({
      id:    n.id,
      label: n.full_name,
      value: n.article_count,
      title: `${n.full_name}\n${n.article_count} artigo(s)`,
    }));
    // CORRIGIDO: mapeia source/target → from/to
    const edges = d.edges.map(e => ({ from: e.source, to: e.target, value: +e.weight, title: `${e.weight} artigo(s) em conjunto` }));
    buildNetwork(container, nodes, edges, { nodeColor:'#00D4FF', edgeColor:'rgba(0,212,255,0.25)' });
  } catch(e) {
    container.innerHTML = `<div class="alert alert-error">Erro: ${e.message}</div>`;
  }
}

async function loadKeywordNetwork() {
  const container = document.getElementById('network-keywords');
  container.innerHTML = '<div class="text-center" style="padding:60px"><div class="spinner"></div></div>';
  try {
    const minO = document.getElementById('kwnet-min').value;
    // CORRIGIDO: usa chart= e min_occ=
    const r = await fetch(`api/bibliometrics.php?project_id=${BIB_PROJECT_ID}&chart=keyword_cooccurrence&scope=${getScope()}&min_occ=${minO}`);
    const d = await r.json();
    if (!d.nodes || !d.nodes.length) {
      container.innerHTML = '<div class="empty-state"><i class="fa fa-diagram-project"></i><p>Dados insuficientes. Tente reduzir o mínimo de ocorrências.</p></div>';
      return;
    }
    container.innerHTML = '';
    // Keywords usam strings como ID — criar índice numérico
    const kwMap = {};
    d.nodes.forEach((n, i) => { kwMap[n.keyword] = i; });
    const nodes = d.nodes.map((n, i) => ({
      id:    i,
      label: n.keyword,
      value: n.freq,
      title: `"${n.keyword}"\n${n.freq} ocorrência(s)`,
    }));
    const edges = d.edges.map(e => ({
      from:  kwMap[e.source],
      to:    kwMap[e.target],
      value: +e.weight,
      title: `Co-ocorrências: ${e.weight}`,
    }));
    buildNetwork(container, nodes, edges, { nodeColor:'#00e5a0', edgeColor:'rgba(0,229,160,0.2)' });
  } catch(e) {
    container.innerHTML = `<div class="alert alert-error">Erro: ${e.message}</div>`;
  }
}

function buildNetwork(container, nodes, edges, opts) {
  if (typeof vis === 'undefined') {
    container.innerHTML = '<div class="alert alert-error">vis.js não disponível</div>';
    return;
  }

  // Detecta se estamos no tema claro para ajustar cor da fonte
  const isLight   = document.body.classList.contains('theme-light');
  const fontColor = isLight ? '#1e293b' : '#f1f5f9';
  const strokeCol = isLight ? 'rgba(255,255,255,0.95)' : 'rgba(15,23,41,0.9)';

  const visNodes = new vis.DataSet(nodes.map(n => ({
    id:    n.id,
    label: n.label,
    value: n.value || 1,
    title: n.title,
    color: {
      background: opts.nodeColor + '55',
      border:     opts.nodeColor,
      highlight:  { background: opts.nodeColor + 'bb', border: isLight ? '#0f172a' : '#fff' },
      hover:      { background: opts.nodeColor + '99', border: opts.nodeColor },
    },
    font: {
      color:       fontColor,
      size:        15,
      strokeWidth: 4,
      strokeColor: strokeCol,
      bold:        { color: fontColor, size: 15 },
    },
    shape:       'dot',
    borderWidth: 2,
  })));

  const visEdges = new vis.DataSet(edges.map(e => ({
    from:  e.from,
    to:    e.to,
    value: e.value || 1,
    title: e.title,
    color: { color: opts.nodeColor + '55', highlight: opts.nodeColor + 'cc', opacity: 0.7 },
    smooth: { type: 'continuous' },
    width: Math.max(1, (e.value || 1)),
  })));

  new vis.Network(container, { nodes: visNodes, edges: visEdges }, {
    physics: {
      stabilization:  { iterations: 250 },
      barnesHut:      { springLength: 160, damping: 0.15, gravitationalConstant: -4000, springConstant: 0.04 },
    },
    interaction: { hover: true, tooltipDelay: 80, zoomView: true, dragView: true },
    nodes:       { scaling: { min: 16, max: 55 }, font: { size: 15 } },
    edges:       { scaling: { min: 1, max: 12 } },
  });
}

// ─── Recarregar tudo ─────────────────────────────────────────────────────────

async function reloadAll() {
  const charts = [
    'publications_year','source_types','document_types','open_access',
    'top_journals','top_authors','top_countries','top_publishers',
    'citations_distribution','ratings_distribution',
    'keywords_frequency','keywords_by_year',
  ];
  await Promise.all(charts.map(t => loadChart(t)));
  loadTopCited();
  loadCoauthorship();
  loadKeywordNetwork();
}

// Inicia
reloadAll();
</script>
<?php endif; ?>
