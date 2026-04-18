<?php
/**
 * PRISMA-SLR - Checklist PRISMA 2020
 */
if (!$projectId): ?>
<div class="empty-state"><i class="fa fa-triangle-exclamation"></i>
  <p>Selecione um projeto para ver o checklist PRISMA.</p></div>
<?php else: ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa fa-clipboard-check"></i> Checklist PRISMA 2020</h1>
    <p class="page-subtitle">Verifique se o relatório da revisão está em conformidade com o protocolo PRISMA 2020.</p>
  </div>
  <div style="display:flex;gap:10px">
    <span class="badge badge-pending" id="chk-progress-badge">0/27</span>
    <button class="btn btn-ghost" onclick="loadChecklist()"><i class="fa fa-rotate"></i> Atualizar</button>
  </div>
</div>

<!-- Progresso geral -->
<div class="card" style="margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;margin-bottom:8px">
    <span class="font-medium">Progresso do Checklist</span>
    <span class="text-muted text-sm" id="chk-pct">0%</span>
  </div>
  <div class="progress-bar">
    <div class="progress-fill" id="chk-progress-bar" style="width:0%"></div>
  </div>
</div>

<!-- Checklist por seção -->
<div id="chk-container">
  <div class="text-center" style="padding:40px"><div class="spinner"></div></div>
</div>

<script>
const CHK_PROJECT_ID = <?= $projectId ?>;

// Definição das seções PRISMA 2020
const PRISMA_SECTIONS = [
  { section: 'Título', items: [
    { n:1, text: 'Título — Identifique o relatório como revisão sistemática.' },
  ]},
  { section: 'Resumo', items: [
    { n:2, text: 'Resumo estruturado — Forneça um resumo estruturado incluindo, conforme aplicável: contexto, objetivos, fonte de dados, critérios de elegibilidade, participantes e intervenções, avaliação do risco de viés dos estudos, síntese de resultados, limitações das evidências, conclusões e implicações das principais descobertas, registro da revisão sistemática.' },
  ]},
  { section: 'Introdução', items: [
    { n:3, text: 'Justificativa — Descreva o raciocínio da revisão no contexto do conhecimento existente.' },
    { n:4, text: 'Objetivos — Forneça uma declaração explícita de objetivo(s) ou questão(ões) abordada(s) pela revisão.' },
  ]},
  { section: 'Métodos', items: [
    { n:5,  text: 'Critérios de elegibilidade — Especifique os critérios de inclusão e exclusão para a revisão e como os estudos foram agrupados para síntese.' },
    { n:6,  text: 'Fontes de informação — Especifique todas as bases de dados, registros, sites e outras fontes pesquisadas ou consultadas para identificar estudos.' },
    { n:7,  text: 'Estratégia de pesquisa — Apresente a estratégia de pesquisa completa para pelo menos uma base de dados, incluindo todos os filtros usados.' },
    { n:8,  text: 'Processo de seleção — Especifique os métodos usados para decidir se um estudo atende aos critérios de elegibilidade da revisão, incluindo quantos revisores triaram cada registro e cada relatório recuperado.' },
    { n:9,  text: 'Processo de coleta de dados — Especifique os métodos usados para coletar dados dos relatórios, incluindo quantos revisores coletaram dados de cada relatório.' },
    { n:10, text: 'Itens de dados — Liste e defina todos os resultados e todos os outros itens de dados procurados e apresentados.' },
    { n:11, text: 'Avaliação do risco de viés — Especifique os métodos usados para avaliar o risco de viés dos estudos incluídos.' },
    { n:12, text: 'Medidas de efeito — Especifique para cada resultado a(s) medida(s) de efeito usada(s) em síntese ou síntese de evidências.' },
    { n:13, text: 'Métodos de síntese — Descreva os processos usados para decidir quais estudos eram elegíveis para cada síntese.' },
    { n:14, text: 'Avaliação da certeza — Descreva os métodos usados para avaliar a certeza (ou confiança) no corpo de evidências para um resultado.' },
  ]},
  { section: 'Resultados', items: [
    { n:15, text: 'Seleção de estudos — Descreva os resultados do processo de pesquisa e seleção, desde o número de registros identificados até os estudos incluídos na revisão, de preferência usando um diagrama de fluxo.' },
    { n:16, text: 'Características dos estudos — Cite os dados fornecidos sobre estudos incluídos.' },
    { n:17, text: 'Avaliação do risco de viés nos estudos — Apresente avaliações do risco de viés para cada estudo incluído.' },
    { n:18, text: 'Resultados de estudos individuais — Para todos os resultados, apresente resultados de cada estudo incluído.' },
    { n:19, text: 'Resultados das sínteses — Para cada síntese, apresente resumidamente as características e os tamanhos dos conjuntos de evidências.' },
    { n:20, text: 'Avaliação da certeza das evidências — Apresente avaliações da certeza das evidências para cada resultado avaliado.' },
  ]},
  { section: 'Discussão', items: [
    { n:21, text: 'Discussão — Forneça uma interpretação geral dos resultados no contexto de outras evidências.' },
    { n:22, text: 'Limitações — Discuta as limitações das evidências incluídas na revisão.' },
    { n:23, text: 'Conclusões — Forneça uma interpretação geral dos resultados e implicações para pesquisas futuras.' },
  ]},
  { section: 'Outras Informações', items: [
    { n:24, text: 'Registro — Forneça informações de registro da revisão, incluindo nome do registro e número de registro.' },
    { n:25, text: 'Protocolo — Indique onde o protocolo da revisão pode ser acessado, ou declare que não foi preparado.' },
    { n:26, text: 'Suporte financeiro — Descreva fontes de suporte financeiro para a revisão.' },
    { n:27, text: 'Conflito de interesses — Declare quaisquer conflitos de interesses dos autores da revisão.' },
  ]},
];

let chkData = {};

async function loadChecklist() {
  try {
    const r = await fetch(`api/projects.php?id=${CHK_PROJECT_ID}&action=checklist`);
    const d = await r.json();
    if (!d.error) {
      chkData = {};
      (d || []).forEach(item => { chkData[item.item_number] = item; });
      renderChecklist();
    } else {
      throw new Error(d.message);
    }
  } catch(e) {
    document.getElementById('chk-container').innerHTML =
      `<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> Erro: ${e.message}</div>`;
  }
}

function renderChecklist() {
  let done = 0;
  let html = '';

  PRISMA_SECTIONS.forEach(sec => {
    html += `<div class="card" style="margin-bottom:16px">
      <h3 class="card-title">${escHtml(sec.section)}</h3>
      <div style="display:flex;flex-direction:column;gap:12px;margin-top:12px">`;

    sec.items.forEach(item => {
      const data    = chkData[item.n] || {};
      const checked = data.completed ? true : false;
      if (checked) done++;
      const response  = data.response || '';
      const comment   = data.comment || '';
      const pageRef   = data.page_reference || '';

      html += `<div class="checklist-item ${checked ? 'completed' : ''}" id="chk-item-${item.n}">
        <div style="display:flex;gap:12px;align-items:flex-start">
          <div style="flex-shrink:0;padding-top:2px">
            <input type="checkbox" id="chk-check-${item.n}" ${checked ? 'checked' : ''}
              onchange="toggleItem(${item.n})"
              style="width:18px;height:18px;cursor:pointer;accent-color:var(--primary)">
          </div>
          <div style="flex:1">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
              <label for="chk-check-${item.n}" style="font-size:0.86rem;font-weight:600;cursor:pointer;color:var(--text-primary)">
                <span class="text-muted">${item.n}.</span> ${escHtml(item.text)}
              </label>
            </div>
            <div class="chk-details" id="chk-details-${item.n}" style="margin-top:10px;display:flex;flex-direction:column;gap:8px">
              <div style="display:flex;gap:8px">
                <div style="flex:1">
                  <label class="form-label" style="font-size:0.72rem">Resposta / página no relatório</label>
                  <input type="text" class="form-control" id="chk-response-${item.n}"
                    value="${escAttr(response)}"
                    placeholder="Ex: p. 5, Seção 2.1..."
                    oninput="scheduleAutoSave(${item.n})" style="font-size:0.82rem">
                </div>
                <div style="width:120px">
                  <label class="form-label" style="font-size:0.72rem">Pág./seção</label>
                  <input type="text" class="form-control" id="chk-pageref-${item.n}"
                    value="${escAttr(pageRef)}"
                    placeholder="p. 5"
                    oninput="scheduleAutoSave(${item.n})" style="font-size:0.82rem">
                </div>
              </div>
              <div>
                <label class="form-label" style="font-size:0.72rem">Comentários</label>
                <textarea class="form-control" id="chk-comment-${item.n}" rows="2"
                  placeholder="Observações adicionais..."
                  oninput="scheduleAutoSave(${item.n})" style="font-size:0.82rem">${escHtml(comment)}</textarea>
              </div>
            </div>
          </div>
        </div>
      </div>`;
    });

    html += '</div></div>';
  });

  const total = 27;
  const pct   = Math.round(done / total * 100);
  document.getElementById('chk-container').innerHTML = html;
  document.getElementById('chk-progress-bar').style.width = pct + '%';
  document.getElementById('chk-pct').textContent = pct + '%';
  document.getElementById('chk-progress-badge').textContent = `${done}/${total}`;
  document.getElementById('chk-progress-badge').className =
    done === total ? 'badge badge-included' : done > 0 ? 'badge badge-screened' : 'badge badge-pending';
}

async function toggleItem(n) {
  const checked = document.getElementById(`chk-check-${n}`).checked;
  const item    = document.getElementById(`chk-item-${n}`);
  item.classList.toggle('completed', checked);
  await saveItem(n);
  // Recount
  const total = 27;
  const done  = document.querySelectorAll('.checklist-item.completed').length;
  const pct   = Math.round(done / total * 100);
  document.getElementById('chk-progress-bar').style.width = pct + '%';
  document.getElementById('chk-pct').textContent = pct + '%';
  document.getElementById('chk-progress-badge').textContent = `${done}/${total}`;
  document.getElementById('chk-progress-badge').className =
    done === total ? 'badge badge-included' : done > 0 ? 'badge badge-screened' : 'badge badge-pending';
}

const autoSaveTimers = {};
function scheduleAutoSave(n) {
  clearTimeout(autoSaveTimers[n]);
  autoSaveTimers[n] = setTimeout(() => saveItem(n, true), 800);
}

async function saveItem(n, silent = false) {
  const completed  = document.getElementById(`chk-check-${n}`)?.checked || false;
  const response   = document.getElementById(`chk-response-${n}`)?.value || '';
  const comment    = document.getElementById(`chk-comment-${n}`)?.value || '';
  const pageRef    = document.getElementById(`chk-pageref-${n}`)?.value || '';

  try {
    const r = await fetch('api/projects.php?action=checklist', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        project_id: CHK_PROJECT_ID,
        item_number: n,
        completed, response, comment,
        page_reference: pageRef,
      }),
    });
    const d = await r.json();
    if (!silent && !d.error) showToast('Salvo', 'success');
  } catch(e) {
    if (!silent) showToast('Erro ao salvar', 'error');
  }
}

// CSS adicional para checklist
const style = document.createElement('style');
style.textContent = `
  .checklist-item { background:var(--bg-card2); padding:14px; border-radius:8px;
    border-left:3px solid var(--border); transition:border-color .2s; }
  .checklist-item.completed { border-left-color:var(--accent-green); }
  .checklist-item label { user-select:none; }
`;
document.head.appendChild(style);

loadChecklist();
</script>
<?php endif; ?>
