/**
 * PRISMA-SLR — screening.js
 * Atalhos de teclado para triagem/elegibilidade (setas + teclas I/E/U)
 */
'use strict';

(function initScreeningKeyboard() {
  // Só ativa nas páginas de screening/eligibility
  const page = new URLSearchParams(window.location.search).get('page');
  if (page !== 'screening' && page !== 'eligibility') return;

  document.addEventListener('keydown', (e) => {
    // Ignora quando o foco está em inputs/textareas
    const tag = document.activeElement?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

    // Atalhos para view card: navega entre artigos
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
      e.preventDefault();
      const btns = document.querySelectorAll('[onclick^="decide("]');
      // Scroll to first pending card
      const pending = document.querySelector('.article-card:not([class*="decided"])');
      if (pending) pending.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
})();

/**
 * Exporta lista de artigos triados para CSV
 */
async function exportScreeningCSV(phase) {
  const projectId = typeof SCR_PROJECT_ID !== 'undefined' ? SCR_PROJECT_ID : null;
  if (!projectId) return;
  window.location.href = `api/export.php?project_id=${projectId}&format=csv&scope=${phase === 'eligibility' ? 'screened' : 'all'}`;
}
