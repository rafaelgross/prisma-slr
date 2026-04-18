/**
 * PRISMA-SLR — bibliometrics.js
 * Funções auxiliares para os gráficos (complementa o inline JS de bibliometrics.php)
 */
'use strict';

/**
 * Configurações globais padrão para Chart.js
 */
if (typeof Chart !== 'undefined') {
  Chart.defaults.color = '#94a3b8';
  Chart.defaults.font.family = 'Inter, sans-serif';
  Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15,23,41,0.95)';
  Chart.defaults.plugins.tooltip.borderColor = 'rgba(0,212,255,0.3)';
  Chart.defaults.plugins.tooltip.borderWidth = 1;
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.titleColor = '#00D4FF';
  Chart.defaults.plugins.tooltip.bodyColor = '#e2e8f0';
  Chart.defaults.plugins.legend.labels.boxWidth = 12;
  Chart.defaults.plugins.legend.labels.padding = 16;
}

/**
 * Gera uma paleta de n cores baseadas nas cores do sistema
 */
function generatePalette(n) {
  const base = [
    [0, 212, 255],    // primary cyan
    [0, 229, 160],    // green
    [116, 192, 252],  // light blue
    [229, 153, 247],  // purple
    [255, 212, 59],   // yellow
    [255, 135, 135],  // red
    [255, 180, 80],   // orange
    [80, 200, 120],   // grass
    [200, 120, 200],  // pink
    [120, 160, 255],  // indigo
  ];
  return Array.from({ length: n }, (_, i) => {
    const c = base[i % base.length];
    return `rgba(${c[0]},${c[1]},${c[2]},0.82)`;
  });
}

/**
 * Gera configuração padrão de eixo com grid escuro
 */
function darkAxisConfig(opts = {}) {
  return {
    grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false },
    border: { display: false },
    ticks: { color: '#94a3b8', font: { size: 11 }, ...opts.ticks },
    ...opts,
  };
}

/**
 * Exporta canvas como PNG com fundo escuro
 */
function exportCanvasPng(canvasId, filename) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) { showToast('Canvas não encontrado', 'error'); return; }

  // Cria canvas temporário com fundo
  const tmp = document.createElement('canvas');
  tmp.width  = canvas.width;
  tmp.height = canvas.height;
  const ctx  = tmp.getContext('2d');
  ctx.fillStyle = '#0f1729';
  ctx.fillRect(0, 0, tmp.width, tmp.height);
  ctx.drawImage(canvas, 0, 0);

  const a = document.createElement('a');
  a.href     = tmp.toDataURL('image/png');
  a.download = filename || 'chart.png';
  a.click();
  showToast('Gráfico exportado', 'success');
}
