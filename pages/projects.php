<?php
/**
 * PRISMA-SLR - Página: Projetos
 */
?>
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-folder-open"></i> Projetos</h1>
    <p class="page-subtitle">Gerencie os projetos de revisão sistemática.</p>
  </div>
  <button class="btn btn-primary" onclick="openProjectModal()">
    <i class="fa fa-plus"></i> Novo Projeto
  </button>
</div>

<div id="projects-list">
  <div class="text-center" style="padding:40px"><div class="spinner"></div></div>
</div>

<!-- Modal: Criar / Editar Projeto -->
<div class="modal-overlay" id="project-modal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="modal-title">Novo Projeto</h2>
      <button class="btn btn-ghost btn-sm" onclick="closeProjectModal()">
        <i class="fa fa-times"></i>
      </button>
    </div>
    <div class="modal-body">
      <form id="project-form" onsubmit="saveProject(event)">
        <input type="hidden" id="project-id-field" value="">

        <div class="form-group">
          <label class="form-label">Título *</label>
          <input type="text" class="form-control" id="f-title" required
                 placeholder="Ex: Blockchain e Economia Circular: Revisão Sistemática 2020-2024">
        </div>

        <div class="form-group">
          <label class="form-label">Objetivo / Questão de Pesquisa</label>
          <textarea class="form-control" id="f-objective" rows="3"
                    placeholder="Qual é o objetivo principal da revisão?"></textarea>
        </div>

        <!-- Critérios de Inclusão -->
        <div class="form-group">
          <label class="form-label" style="display:flex;align-items:center;justify-content:space-between">
            <span><i class="fa fa-circle-check" style="color:var(--accent-green)"></i> Critérios de Inclusão</span>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addCriterion('inclusion')"
                    style="font-size:0.75rem;padding:3px 10px">
              <i class="fa fa-plus"></i> Adicionar
            </button>
          </label>
          <div id="inclusion-list" style="display:flex;flex-direction:column;gap:6px;margin-bottom:4px"></div>
          <div id="inclusion-empty" style="font-size:0.78rem;color:var(--text-muted);padding:8px 0;display:none">
            Nenhum critério adicionado ainda.
          </div>
        </div>

        <!-- Critérios de Exclusão -->
        <div class="form-group">
          <label class="form-label" style="display:flex;align-items:center;justify-content:space-between">
            <span><i class="fa fa-circle-xmark" style="color:var(--accent-red)"></i> Critérios de Exclusão</span>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addCriterion('exclusion')"
                    style="font-size:0.75rem;padding:3px 10px">
              <i class="fa fa-plus"></i> Adicionar
            </button>
          </label>
          <div id="exclusion-list" style="display:flex;flex-direction:column;gap:6px;margin-bottom:4px"></div>
          <div id="exclusion-empty" style="font-size:0.78rem;color:var(--text-muted);padding:8px 0;display:none">
            Nenhum critério adicionado ainda.
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Período de Busca — Início</label>
            <input type="number" class="form-control" id="f-start"
                   min="1900" max="2030" placeholder="Ex: 2013">
          </div>
          <div class="form-group">
            <label class="form-label">Período de Busca — Fim</label>
            <input type="number" class="form-control" id="f-end"
                   min="1900" max="2030" placeholder="Ex: 2024">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Descrição (opcional)</label>
          <textarea class="form-control" id="f-description" rows="2"
                    placeholder="Descrição adicional do projeto"></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeProjectModal()">Cancelar</button>
      <button class="btn btn-primary" onclick="saveProject(event)" id="btn-save-project">
        <i class="fa fa-save"></i> Salvar
      </button>
    </div>
  </div>
</div>

<script>
let editingProjectId = null;

// ---- Critérios dinâmicos ----
function addCriterion(type, value = '') {
  const list = document.getElementById(type + '-list');
  const empty = document.getElementById(type + '-empty');
  if (empty) empty.style.display = 'none';

  const accentColor = type === 'inclusion' ? 'var(--accent-green)' : 'var(--accent-red)';
  const row = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:8px';
  row.innerHTML = `
    <span style="color:${accentColor};font-size:0.9rem;flex-shrink:0">
      <i class="fa fa-${type === 'inclusion' ? 'check' : 'xmark'}"></i>
    </span>
    <input type="text" class="form-control criterion-input"
           data-criterion-type="${type}"
           style="flex:1;padding:7px 10px;font-size:0.85rem"
           placeholder="${type === 'inclusion' ? 'Ex: Artigos em inglês publicados 2020-2025' : 'Ex: Estudos sem relação com blockchain'}"
           value="${escHtml(value)}">
    <button type="button" onclick="removeCriterion(this)"
            style="flex-shrink:0;background:none;border:none;color:var(--text-muted);cursor:pointer;padding:4px 6px;font-size:1rem"
            title="Remover">
      <i class="fa fa-times"></i>
    </button>`;
  list.appendChild(row);

  // Foca no input recém-adicionado
  row.querySelector('input').focus();
}

function removeCriterion(btn) {
  const row  = btn.closest('div');
  const type = row.querySelector('input').dataset.criterionType;
  row.remove();
  updateEmptyState(type);
}

function updateEmptyState(type) {
  const list  = document.getElementById(type + '-list');
  const empty = document.getElementById(type + '-empty');
  if (!empty) return;
  empty.style.display = list.children.length === 0 ? 'block' : 'none';
}

function getCriteria(type) {
  return Array.from(
    document.querySelectorAll(`#${type}-list .criterion-input`)
  ).map(i => i.value.trim()).filter(v => v !== '');
}

function setCriteria(type, raw) {
  const list = document.getElementById(type + '-list');
  list.innerHTML = '';
  let items = [];
  if (raw) {
    try { items = JSON.parse(raw); } catch(_) {
      // fallback: texto simples separado por nova linha
      items = raw.split('\n').map(s => s.trim()).filter(Boolean);
    }
  }
  items.forEach(v => addCriterion(type, v));
  updateEmptyState(type);
}

function clearCriteria() {
  ['inclusion','exclusion'].forEach(t => {
    document.getElementById(t + '-list').innerHTML = '';
    updateEmptyState(t);
  });
}
// ---- fim critérios ----

function parseCriteria(raw) {
  if (!raw) return [];
  try { return JSON.parse(raw); } catch(_) {
    return raw.split('\n').map(s => s.trim()).filter(Boolean);
  }
}

function renderCriteriaPreview(inc, exc) {
  const incItems = parseCriteria(inc);
  const excItems = parseCriteria(exc);
  if (!incItems.length && !excItems.length) return '';
  let html = '<div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:10px">';
  if (incItems.length) {
    html += `<div style="font-size:0.78rem"><span style="color:var(--accent-green);font-weight:700;display:block;margin-bottom:3px"><i class="fa fa-circle-check"></i> Inclusão</span>`;
    incItems.slice(0,3).forEach(i => { html += `<div style="color:var(--text-secondary);margin-left:14px">• ${escHtml(i)}</div>`; });
    if (incItems.length > 3) html += `<div style="color:var(--text-muted);margin-left:14px">+${incItems.length-3} mais</div>`;
    html += '</div>';
  }
  if (excItems.length) {
    html += `<div style="font-size:0.78rem"><span style="color:var(--accent-red);font-weight:700;display:block;margin-bottom:3px"><i class="fa fa-circle-xmark"></i> Exclusão</span>`;
    excItems.slice(0,3).forEach(i => { html += `<div style="color:var(--text-secondary);margin-left:14px">• ${escHtml(i)}</div>`; });
    if (excItems.length > 3) html += `<div style="color:var(--text-muted);margin-left:14px">+${excItems.length-3} mais</div>`;
    html += '</div>';
  }
  html += '</div>';
  return html;
}

async function loadProjects() {
  const container = document.getElementById('projects-list');
  try {
    const r = await fetch('api/projects.php');
    const projects = await r.json();

    if (!projects.length) {
      container.innerHTML = `
        <div class="empty-state">
          <i class="fa fa-folder-open"></i>
          <p>Nenhum projeto cadastrado.</p>
          <small>Clique em "Novo Projeto" para criar o primeiro.</small>
          <br><br>
          <button class="btn btn-primary" onclick="openProjectModal()">
            <i class="fa fa-plus"></i> Criar meu primeiro projeto
          </button>
        </div>`;
      return;
    }

    let html = '<div style="display:flex;flex-direction:column;gap:14px">';
    projects.forEach(p => {
      const statusColors = { active: 'green', completed: '', archived: 'red' };
      html += `
        <div class="card" style="cursor:default">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px">
            <div style="flex:1">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <span style="font-size:1rem;font-weight:700">${escHtml(p.title)}</span>
                <span class="badge badge-${p.status}">${p.status}</span>
              </div>
              ${p.objective ? `<p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:10px">${escHtml(p.objective.substring(0,200))}${p.objective.length>200?'...':''}</p>` : ''}
              ${renderCriteriaPreview(p.inclusion_criteria, p.exclusion_criteria)}
              <div style="display:flex;gap:20px;font-size:0.78rem;color:var(--text-muted)">
                <span><i class="fa fa-database"></i> ${p.total_sources} fonte(s)</span>
                <span><i class="fa fa-book"></i> ${p.total_articles} artigos</span>
                <span><i class="fa fa-copy" style="color:var(--accent-red)"></i> ${p.total_duplicates} dup.</span>
                <span><i class="fa fa-circle-check" style="color:var(--accent-green)"></i> ${p.total_included} incluídos</span>
                <span><i class="fa fa-clock"></i> ${formatDate(p.created_at)}</span>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;min-width:140px">
              <a href="?page=dashboard&project_id=${p.id}" class="btn btn-primary btn-sm">
                <i class="fa fa-arrow-right"></i> Abrir
              </a>
              <button class="btn btn-ghost btn-sm" onclick="editProject(${p.id})">
                <i class="fa fa-pen"></i> Editar
              </button>
              <button class="btn btn-ghost btn-sm text-danger" onclick="deleteProject(${p.id},'${escHtml(p.title)}')">
                <i class="fa fa-trash"></i> Excluir
              </button>
            </div>
          </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
  } catch(e) {
    container.innerHTML = `<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> Erro ao carregar projetos: ${e.message}</div>`;
  }
}

function openProjectModal(data = null) {
  editingProjectId = null;
  document.getElementById('modal-title').textContent = 'Novo Projeto';
  document.getElementById('project-id-field').value = '';
  ['title','objective','start','end','description'].forEach(f => {
    document.getElementById('f-'+f).value = '';
  });
  clearCriteria();
  document.getElementById('project-modal').classList.add('open');
}

async function editProject(id) {
  try {
    const r = await fetch(`api/projects.php?id=${id}`);
    const p = await r.json();
    editingProjectId = id;
    document.getElementById('modal-title').textContent = 'Editar Projeto';
    document.getElementById('project-id-field').value = id;
    document.getElementById('f-title').value        = p.title || '';
    document.getElementById('f-objective').value    = p.objective || '';
    document.getElementById('f-start').value        = p.search_period_start || '';
    document.getElementById('f-end').value          = p.search_period_end || '';
    document.getElementById('f-description').value  = p.description || '';
    setCriteria('inclusion', p.inclusion_criteria || '');
    setCriteria('exclusion', p.exclusion_criteria || '');
    document.getElementById('project-modal').classList.add('open');
  } catch(e) {
    showToast('Erro ao carregar projeto', 'error');
  }
}

function closeProjectModal() {
  document.getElementById('project-modal').classList.remove('open');
}

async function saveProject(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-save-project');
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner"></div>';

  const inclusionItems = getCriteria('inclusion');
  const exclusionItems = getCriteria('exclusion');
  const data = {
    title:               document.getElementById('f-title').value.trim(),
    objective:           document.getElementById('f-objective').value.trim(),
    inclusion_criteria:  inclusionItems.length ? JSON.stringify(inclusionItems) : '',
    exclusion_criteria:  exclusionItems.length ? JSON.stringify(exclusionItems) : '',
    search_period_start: document.getElementById('f-start').value || null,
    search_period_end:   document.getElementById('f-end').value   || null,
    description:         document.getElementById('f-description').value.trim(),
  };

  if (!data.title) {
    showToast('Título é obrigatório', 'error');
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-save"></i> Salvar';
    return;
  }

  const id = document.getElementById('project-id-field').value;
  const url    = id ? `api/projects.php?id=${id}` : 'api/projects.php';
  const method = id ? 'PUT' : 'POST';

  try {
    const r = await fetch(url, {
      method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)
    });
    const res = await r.json();
    if (res.error) throw new Error(res.message);
    showToast(id ? 'Projeto atualizado!' : 'Projeto criado!', 'success');
    closeProjectModal();
    loadProjects();

    // Atualiza seletor de projetos
    if (!id && res.id) {
      const sel = document.getElementById('project-select');
      const opt = document.createElement('option');
      opt.value = res.id;
      opt.textContent = data.title;
      sel.insertBefore(opt, sel.options[1]);
    }
  } catch(e) {
    showToast('Erro: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-save"></i> Salvar';
  }
}

async function deleteProject(id, title) {
  if (!confirm(`Excluir o projeto "${title}" e TODOS os seus dados?\n\nEssa ação não pode ser desfeita.`)) return;
  try {
    const r = await fetch(`api/projects.php?id=${id}`, { method: 'DELETE' });
    const res = await r.json();
    if (res.error) throw new Error(res.message);
    showToast('Projeto excluído', 'success');
    loadProjects();
  } catch(e) {
    showToast('Erro: ' + e.message, 'error');
  }
}

loadProjects();
</script>
