/**
 * PRISMA-SLR — prisma-flow.js
 * Utilitários para o diagrama PRISMA 2020 (complementa prisma-flow.php)
 */
'use strict';

/**
 * Imprime o diagrama PRISMA em uma nova aba
 */
function printPrismaFlow() {
  const svgEl = document.getElementById('prisma-svg');
  if (!svgEl) { showToast('Gere o diagrama primeiro', 'warning'); return; }

  const svgData = new XMLSerializer().serializeToString(svgEl);
  const win = window.open('', '_blank');
  win.document.write(`<!DOCTYPE html><html><head><title>Diagrama PRISMA 2020</title>
    <style>body{margin:0;background:#070b14;display:flex;justify-content:center;padding:40px}
    svg{max-width:900px;width:100%}</style></head>
    <body>${svgData}</body></html>`);
  win.document.close();
  setTimeout(() => win.print(), 300);
}

/**
 * Copia os números do diagrama PRISMA formatados como texto
 */
function copyFlowNumbers(data) {
  if (!data) { showToast('Carregue o diagrama primeiro', 'warning'); return; }
  const id  = data.identification;
  const sc  = data.screening;
  const el  = data.eligibility;
  const inc = data.included;

  const scExc = (sc.excluded_reasons||[]).reduce((a,r)=>a+r.count,0);
  const elExc = (el.excluded_reasons||[]).reduce((a,r)=>a+r.count,0);

  let text = `DIAGRAMA PRISMA 2020\n`;
  text += `${'═'.repeat(40)}\n`;
  text += `IDENTIFICAÇÃO\n`;
  text += `  Total identificado: ${id.total_identified}\n`;
  (id.by_database||[]).forEach(b => { text += `  - ${b.name}: ${b.count}\n`; });
  text += `  Duplicatas removidas: ${id.duplicates_removed}\n`;
  text += `  Únicos para triagem: ${id.unique_after_dedup}\n\n`;

  text += `TRIAGEM\n`;
  text += `  Avaliados: ${sc.records_screened}\n`;
  text += `  Excluídos: ${scExc}\n`;
  (sc.excluded_reasons||[]).forEach(r => { text += `  - ${r.reason}: ${r.count}\n`; });
  text += '\n';

  text += `ELEGIBILIDADE\n`;
  text += `  Texto completo avaliado: ${el.assessed_full_text}\n`;
  text += `  Excluídos: ${elExc}\n`;
  (el.excluded_reasons||[]).forEach(r => { text += `  - ${r.reason}: ${r.count}\n`; });
  text += '\n';

  text += `INCLUÍDOS\n`;
  text += `  Estudos incluídos: ${inc.studies_included}\n`;

  copyToClipboard(text);
}
