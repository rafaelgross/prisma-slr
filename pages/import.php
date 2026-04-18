<?php
/**
 * PRISMA-SLR - Página: Importação de .bib
 */
if (!$projectId):
?>
<div class="empty-state">
  <i class="fa fa-triangle-exclamation"></i>
  <p>Selecione um projeto antes de importar arquivos.</p>
</div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-file-import"></i> Importação de Referências</h1>
    <p class="page-subtitle">Importe arquivos BibTeX (.bib), RIS (.ris) ou PubMed XML (.xml) de qualquer base de dados.</p>
  </div>
  <a href="?page=articles&project_id=<?= $projectId ?>" class="btn btn-ghost">
    <i class="fa fa-book"></i> Ver Artigos
  </a>
</div>

<!-- Upload form -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-upload"></i> Novo Upload</div>
    </div>

    <form id="import-form" onsubmit="startImport(event)">
      <div class="form-group">
        <label class="form-label">Arquivo .bib *</label>
        <!-- Seletor de formato -->
        <div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap">
          <button type="button" class="fmt-tab active-tab" id="tab-bib"  onclick="setTab('bib')"  style="flex:1;padding:7px 4px;border:1px solid var(--primary);border-radius:7px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;cursor:pointer"><i class="fa fa-file-code"></i> BibTeX (.bib)</button>
          <button type="button" class="fmt-tab"        id="tab-ris"  onclick="setTab('ris')"  style="flex:1;padding:7px 4px;border:1px solid var(--border);border-radius:7px;background:none;color:var(--text-muted);font-size:11px;font-weight:700;cursor:pointer"><i class="fa fa-file-lines"></i> RIS (.ris)</button>
          <button type="button" class="fmt-tab"        id="tab-xml"  onclick="setTab('xml')"  style="flex:1;padding:7px 4px;border:1px solid var(--border);border-radius:7px;background:none;color:var(--text-muted);font-size:11px;font-weight:700;cursor:pointer"><i class="fa fa-file-medical"></i> PubMed XML (.xml)</button>
        </div>
        <div class="upload-zone" id="upload-zone" onclick="document.getElementById('bib-file').click()">
          <i class="fa fa-file-arrow-up"></i>
          <p id="drop-text">Clique ou arraste o arquivo aqui</p>
          <small id="fmt-hint">Formatos: .bib, .ris, .xml, .txt · Máximo: 50 MB</small>
        </div>
        <input type="file" id="bib-file" accept=".bib,.ris,.xml,.txt" style="display:none"
               onchange="onFileSelect(event)">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nome da Fonte *</label>
          <input type="text" class="form-control" id="f-source-name" required
                 placeholder="Ex: Scopus 2024">
        </div>
        <div class="form-group">
          <label class="form-label">Base de dados</label>
          <select class="form-control" id="f-source-type">
            <option value="scopus">Scopus</option>
            <option value="wos">Web of Science</option>
            <option value="pubmed">PubMed</option>
            <option value="embase">Embase</option>
            <option value="other">Outra</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Data da busca</label>
        <input type="date" class="form-control" id="f-search-date"
               value="<?= date('Y-m-d') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">String de busca (opcional)</label>
        <textarea class="form-control" id="f-search-string" rows="2"
                  placeholder='Ex: TITLE-ABS-KEY("blockchain" AND "circular economy")'></textarea>
      </div>

      <button type="submit" class="btn btn-primary w-full" id="btn-import">
        <i class="fa fa-play"></i> Iniciar Importação
      </button>
    </form>
  </div>

  <!-- Dicas -->
  <div>
    <!-- Guias por formato: mostradas conforme aba selecionada -->
    <div id="guide-bib">
      <div class="card" style="margin-bottom:12px">
        <div class="card-header"><div class="card-title"><i class="fa fa-circle-info"></i> Scopus → BibTeX</div></div>
        <ol style="font-size:0.82rem;color:var(--text-secondary);line-height:1.8;padding-left:18px">
          <li>Faça a busca, selecione os resultados</li>
          <li>Clique em <strong>Export → BibTeX</strong></li>
          <li>Escolha todos os campos e exporte o .bib</li>
        </ol>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa fa-circle-info"></i> Web of Science → BibTeX</div></div>
        <ol style="font-size:0.82rem;color:var(--text-secondary);line-height:1.8;padding-left:18px">
          <li>Faça a busca, selecione os resultados</li>
          <li>Clique em <strong>Export → BibTeX</strong></li>
          <li>Selecione todos os campos e exporte</li>
        </ol>
      </div>
    </div>

    <div id="guide-ris" style="display:none">
      <div class="card" style="margin-bottom:12px">
        <div class="card-header"><div class="card-title"><i class="fa fa-circle-info"></i> Mendeley / Zotero / EndNote → RIS</div></div>
        <ol style="font-size:0.82rem;color:var(--text-secondary);line-height:1.8;padding-left:18px">
          <li>Selecione as referências desejadas</li>
          <li>Clique em <strong>File → Export</strong></li>
          <li>Escolha o formato <strong>RIS</strong> e salve o .ris</li>
        </ol>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa fa-circle-info"></i> Scopus / Embase → RIS</div></div>
        <ol style="font-size:0.82rem;color:var(--text-secondary);line-height:1.8;padding-left:18px">
          <li>Selecione os resultados da busca</li>
          <li>Clique em <strong>Export → RIS Format</strong></li>
          <li>Marque todos os campos e exporte</li>
        </ol>
      </div>
    </div>

    <div id="guide-xml" style="display:none">
      <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa fa-circle-info"></i> PubMed → XML</div></div>
        <ol style="font-size:0.82rem;color:var(--text-secondary);line-height:1.8;padding-left:18px">
          <li>Faça a busca no PubMed e selecione os resultados</li>
          <li>Clique em <strong>Save</strong> (acima da lista)</li>
          <li>Em <em>Format</em>, escolha <strong>PubMed</strong> ou <strong>XML</strong></li>
          <li>Clique em <strong>Create file</strong> e salve o .xml</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<!-- Resultado da importação -->
<div id="import-result" style="display:none" class="mb-3"></div>

<!-- Fontes já importadas -->
<div class="card" id="sources-card">
  <div class="card-header">
    <div class="card-title"><i class="fa fa-database"></i> Arquivos Importados</div>
    <span class="text-muted text-xs" id="sources-count"></span>
  </div>
  <div id="sources-list">
    <div class="text-center" style="padding:30px"><div class="spinner"></div></div>
  </div>
</div>

<script>
const PROJECT_ID = <?= $projectId ?>;
let selectedFile  = null;
let activeTab     = 'bib';

// ── Seletor de formato (abas) ────────────────────────────────────
function setTab(fmt) {
  activeTab = fmt;
  ['bib','ris','xml'].forEach(f => {
    const btn   = document.getElementById('tab-' + f);
    const guide = document.getElementById('guide-' + f);
    const active = (f === fmt);
    btn.style.background  = active ? 'var(--primary)' : 'none';
    btn.style.color       = active ? '#fff' : 'var(--text-muted)';
    btn.style.borderColor = active ? 'var(--primary)' : 'var(--border)';
    guide.style.display   = active ? 'block' : 'none';
  });
  // Atualiza accept e hint
  const acceptMap = { bib: '.bib,.txt', ris: '.ris,.txt', xml: '.xml' };
  const hintMap   = { bib: 'Formato BibTeX (.bib) · Máx 50 MB', ris: 'Formato RIS (.ris) — Mendeley, Zotero, EndNote · Máx 50 MB', xml: 'PubMed XML (.xml) · Máx 50 MB' };
  document.getElementById('bib-file').accept = acceptMap[fmt];
  document.getElementById('fmt-hint').textContent = hintMap[fmt];
  // Auto-seleciona base PubMed para XML
  if (fmt === 'xml') document.getElementById('f-source-type').value = 'pubmed';
}

function onFileSelect(e) {
  const file = e.target.files[0];
  if (!file) return;
  selectedFile = file;
  document.getElementById('drop-text').textContent = `✓ ${file.name} (${formatBytes(file.size)})`;
  document.getElementById('upload-zone').style.borderColor = 'var(--primary)';
  // Detecta formato pela extensão e muda aba
  const ext = file.name.split('.').pop().toLowerCase();
  if (ext === 'ris') setTab('ris');
  else if (ext === 'xml') setTab('xml');
  else setTab('bib');
  // Detecta base automaticamente pelo nome do arquivo
  if (file.name.toLowerCase().includes('scopus')) {
    document.getElementById('f-source-type').value = 'scopus';
    if (!document.getElementById('f-source-name').value)
      document.getElementById('f-source-name').value = 'Scopus';
  } else if (file.name.toLowerCase().includes('wos') || file.name.toLowerCase().includes('webofscience')) {
    document.getElementById('f-source-type').value = 'wos';
    if (!document.getElementById('f-source-name').value)
      document.getElementById('f-source-name').value = 'Web of Science';
  } else if (file.name.toLowerCase().includes('pubmed') || ext === 'xml') {
    document.getElementById('f-source-type').value = 'pubmed';
    if (!document.getElementById('f-source-name').value)
      document.getElementById('f-source-name').value = 'PubMed';
  } else if (file.name.toLowerCase().includes('mendeley') || file.name.toLowerCase().includes('zotero') || file.name.toLowerCase().includes('endnote')) {
    document.getElementById('f-source-type').value = 'other';
  }
}

// Drag and drop
const zone = document.getElementById('upload-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
  e.preventDefault();
  zone.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) {
    document.getElementById('bib-file').files = e.dataTransfer.files;
    onFileSelect({ target: { files: [file] } });
  }
});

async function startImport(e) {
  e.preventDefault();
  if (!selectedFile) { showToast('Selecione um arquivo .bib', 'warning'); return; }

  const btn = document.getElementById('btn-import');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner"></div> Importando...';
  document.getElementById('import-result').style.display = 'none';

  const form = new FormData();
  form.append('file', selectedFile);
  form.append('project_id',    PROJECT_ID);
  form.append('source_name',   document.getElementById('f-source-name').value || 'Importação');
  form.append('source_type',   document.getElementById('f-source-type').value);
  form.append('search_date',   document.getElementById('f-search-date').value);
  form.append('search_string', document.getElementById('f-search-string').value);

  try {
    const r   = await fetch('api/import.php', { method: 'POST', body: form });
    const res = await r.json();

    const div = document.getElementById('import-result');
    div.style.display = 'block';

    if (res.error) {
      div.innerHTML = `<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i>
        <div><strong>Erro na importação:</strong> ${escHtml(res.message)}</div></div>`;
    } else {
      div.innerHTML = `
        <div class="alert alert-success">
          <i class="fa fa-circle-check"></i>
          <div>
            <strong>Importação concluída!</strong>
            <div style="margin-top:6px;font-size:0.82rem">
              <span>Registros encontrados: <strong>${res.total_parsed}</strong></span> ·
              <span style="color:var(--accent-green)">Importados: <strong>${res.total_imported}</strong></span>
              ${res.total_errors > 0 ? `· <span style="color:var(--accent-yellow)">Erros: <strong>${res.total_errors}</strong></span>` : ''}
            </div>
            ${res.total_errors > 0 ? `<details style="margin-top:8px" class="text-xs"><summary>Ver erros</summary>
              <ul style="margin-top:6px;padding-left:16px">${res.errors.map(err => `<li>${escHtml(err.key)}: ${escHtml(err.reason)}</li>`).join('')}</ul>
            </details>` : ''}
          </div>
        </div>`;
      showToast(`${res.total_imported} artigos importados!`, 'success');
      loadSources();
      // Reset form
      selectedFile = null;
      document.getElementById('drop-text').textContent = 'Clique ou arraste o arquivo .bib aqui';
      document.getElementById('upload-zone').style.borderColor = '';
      document.getElementById('bib-file').value = '';
    }
  } catch(err) {
    document.getElementById('import-result').style.display = 'block';
    document.getElementById('import-result').innerHTML =
      `<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> Erro de conexão: ${err.message}</div>`;
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-play"></i> Iniciar Importação';
  }
}

async function deleteSourc(id, name) {
  if (!confirm(`Excluir a fonte "${name}" e todos os seus artigos?`)) return;
  try {
    await fetch(`api/import.php?source_id=${id}`, { method: 'DELETE' });
    showToast('Fonte excluída', 'success');
    loadSources();
  } catch(e) {
    showToast('Erro ao excluir', 'error');
  }
}

async function loadSources() {
  const container = document.getElementById('sources-list');
  const counter   = document.getElementById('sources-count');
  try {
    const r  = await fetch(`api/import.php?project_id=${PROJECT_ID}`);
    const sources = await r.json();
    counter.textContent = `${sources.length} arquivo(s)`;

    if (!sources.length) {
      container.innerHTML = '<div class="empty-state" style="padding:30px"><i class="fa fa-inbox"></i><p>Nenhum arquivo importado ainda.</p></div>';
      return;
    }

    let html = '<div class="table-wrapper"><table><thead><tr>'
      + '<th>Nome / Arquivo</th><th>Tipo</th><th>Artigos</th>'
      + '<th>Data da busca</th><th>Importado em</th><th></th>'
      + '</tr></thead><tbody>';
    sources.forEach(s => {
      html += `<tr>
        <td>
          <strong>${escHtml(s.name)}</strong>
          <br><small class="text-muted">${escHtml(s.file_name || '')}</small>
        </td>
        <td><span class="badge badge-${s.source_type}">${s.source_type.toUpperCase()}</span></td>
        <td><strong>${s.article_count}</strong></td>
        <td>${s.search_date || '—'}</td>
        <td>${formatDate(s.imported_at)}</td>
        <td>
          <button class="btn btn-ghost btn-sm text-danger"
                  onclick="deleteSourc(${s.id},'${escHtml(s.name)}')">
            <i class="fa fa-trash"></i>
          </button>
        </td>
      </tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
  } catch(e) {
    container.innerHTML = '<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i>Erro ao carregar fontes.</div>';
  }
}

function formatBytes(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/1048576).toFixed(1) + ' MB';
}

loadSources();
</script>
<?php endif; ?>
