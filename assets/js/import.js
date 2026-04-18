/**
 * PRISMA-SLR — import.js
 */
'use strict';

let importInProgress = false;

function initImportPage() {
  if (typeof IMPORT_PROJECT_ID === 'undefined') return;

  const zone     = document.getElementById('drop-zone');
  const fileInput = document.getElementById('bib-file');
  if (!zone || !fileInput) return;

  // Drag-and-drop
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', ()=> zone.classList.remove('dragover'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const files = Array.from(e.dataTransfer.files).filter(f => f.name.endsWith('.bib'));
    if (!files.length) { showToast('Somente arquivos .bib são aceitos', 'warning'); return; }
    processFiles(files);
  });

  fileInput.addEventListener('change', () => {
    processFiles(Array.from(fileInput.files));
    fileInput.value = '';
  });
}

async function processFiles(files) {
  if (importInProgress) { showToast('Aguarde o import anterior terminar', 'warning'); return; }
  const sourceType = document.getElementById('source-type')?.value || 'auto';
  const sourceName = document.getElementById('source-name')?.value || '';

  for (const file of files) {
    await importFile(file, sourceType, sourceName);
  }
}

async function importFile(file, sourceType, sourceName) {
  importInProgress = true;
  const progressEl = document.getElementById('import-progress');
  const logEl      = document.getElementById('import-log');
  if (progressEl) progressEl.style.display = 'block';

  const formData = new FormData();
  formData.append('file', file);
  formData.append('project_id', IMPORT_PROJECT_ID);
  formData.append('source_type', sourceType);
  if (sourceName) formData.append('source_name', sourceName);

  updateImportLog(logEl, `Enviando: ${file.name}...`, 'info');

  try {
    const r = await fetch('api/import.php', { method: 'POST', body: formData });
    const d = await r.json();

    if (d.error) {
      updateImportLog(logEl, `✗ Erro: ${d.message}`, 'error');
      showToast('Erro no import: ' + d.message, 'error');
    } else {
      updateImportLog(logEl, `✓ ${file.name}: ${d.imported} artigos importados (${d.skipped||0} duplicatas ignoradas, ${d.errors||0} erros)`, 'success');
      showToast(`${d.imported} artigos importados de ${file.name}`, 'success');
      // Recarrega lista de fontes
      if (typeof loadSources === 'function') loadSources();
    }
  } catch(e) {
    updateImportLog(logEl, `✗ Falha na requisição: ${e.message}`, 'error');
    showToast('Falha na conexão', 'error');
  } finally {
    importInProgress = false;
    if (progressEl) progressEl.style.display = 'none';
  }
}

function updateImportLog(el, message, type) {
  if (!el) return;
  const color = { info:'#94a3b8', success:'#00e5a0', error:'#ff4d6a', warning:'#ffd43b' }[type] || '#94a3b8';
  const time  = new Date().toLocaleTimeString('pt-BR');
  el.innerHTML = `<div style="color:${color}">[${time}] ${escHtml(message)}</div>` + el.innerHTML;
  el.style.display = 'block';
}

async function deleteSources(sourceId) {
  if (!confirm('Remover esta fonte e TODOS os artigos importados dela?')) return;
  try {
    const r = await fetch(`api/import.php?source_id=${sourceId}&project_id=${IMPORT_PROJECT_ID}`, { method: 'DELETE' });
    const d = await r.json();
    if (d.error) { showToast(d.message, 'error'); return; }
    showToast('Fonte removida', 'success');
    if (typeof loadSources === 'function') loadSources();
  } catch(e) {
    showToast('Erro ao remover', 'error');
  }
}

document.addEventListener('DOMContentLoaded', initImportPage);
