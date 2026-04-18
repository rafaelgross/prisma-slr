/**
 * PRISMA-SLR — dashboard.js
 */
'use strict';

async function initDashboard() {
  if (typeof DASH_PROJECT_ID === 'undefined') return;
  await Promise.all([
    loadStats(),
    loadSources(),
    loadPrismaProgress(),
  ]);
}

async function loadStats() {
  try {
    const r = await fetch(`api/projects.php?id=${DASH_PROJECT_ID}`);
    const d = await r.json();
    if (!d || d.error) return;
    document.getElementById('stat-total').textContent    = fmtNum(d.stats?.total_articles || 0);
    document.getElementById('stat-unique').textContent   = fmtNum(d.stats?.unique_articles || 0);
    document.getElementById('stat-dupes').textContent    = fmtNum(d.stats?.duplicates || 0);
    document.getElementById('stat-included').textContent = fmtNum(d.stats?.included || 0);
  } catch(e) { console.error(e); }
}

async function loadSources() {
  try {
    const r = await fetch(`api/import.php?project_id=${DASH_PROJECT_ID}`);
    const d = await r.json();
    const tbody = document.getElementById('sources-tbody');
    if (!tbody || !d.sources) return;
    if (!d.sources.length) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding:20px;font-style:italic">
        Nenhuma base importada.</td></tr>`;
      return;
    }
    tbody.innerHTML = d.sources.map(s => `<tr>
      <td>${escHtml(s.name)}</td>
      <td><span class="badge badge-${s.name.toLowerCase().includes('scop')? 'scopus':'wos'}">${escHtml(s.database_name||s.name)}</span></td>
      <td>${fmtNum(s.total_imported)}</td>
      <td>${formatDate(s.imported_at)}</td>
      <td><a href="?page=import&project_id=${DASH_PROJECT_ID}" class="btn btn-ghost btn-sm"><i class="fa fa-eye"></i></a></td>
    </tr>`).join('');
  } catch(e) { console.error(e); }
}

async function loadPrismaProgress() {
  try {
    const r = await fetch(`api/prisma-flow.php?project_id=${DASH_PROJECT_ID}`);
    const d = await r.json();
    if (d.error) return;

    const setBar = (id, val, max) => {
      const pct = max > 0 ? Math.min(100, Math.round(val / max * 100)) : 0;
      const el  = document.getElementById(id);
      if (el) {
        el.style.width = pct + '%';
        el.setAttribute('title', `${fmtNum(val)} / ${fmtNum(max)}`);
      }
      const lbl = document.getElementById(id + '-label');
      if (lbl) lbl.textContent = `${fmtNum(val)} / ${fmtNum(max)}`;
    };

    const id      = d.identification;
    const sc      = d.screening;
    const el      = d.eligibility;
    const inc     = d.included;
    const scExc   = (sc.excluded_reasons||[]).reduce((a,r)=>a+r.count,0);
    const elExc   = (el.excluded_reasons||[]).reduce((a,r)=>a+r.count,0);
    const scDone  = (sc.included||0) + scExc;
    const elDone  = (el.included||0) + elExc;

    setBar('bar-dedup',       id.duplicates_removed,         id.total_identified);
    setBar('bar-screening',   scDone,                        id.unique_after_dedup);
    setBar('bar-eligibility', elDone,                        el.assessed_full_text || sc.records_screened);
    setBar('bar-included',    inc.studies_included,          el.assessed_full_text || 1);

    // Update numbers if elements exist
    const setEl = (id2, val) => { const e = document.getElementById(id2); if(e) e.textContent = fmtNum(val); };
    setEl('num-identified',  id.total_identified);
    setEl('num-dedup',       id.duplicates_removed);
    setEl('num-screened',    sc.records_screened);
    setEl('num-eligible',    el.assessed_full_text);
    setEl('num-included',    inc.studies_included);
  } catch(e) { console.error(e); }
}

document.addEventListener('DOMContentLoaded', initDashboard);
