import Sortable from 'sortablejs';

function tFactory(i18n) {
  return (key, replacements = {}) => {
    let text = i18n[key] ?? key;
    Object.entries(replacements).forEach(([name, value]) => {
      text = text.replaceAll(':' + name, String(value));
    });
    return text;
  };
}

function normalizeAreas(iltAreas) {
  if (!iltAreas || Array.isArray(iltAreas)) {
    return {};
  }
  return iltAreas;
}

/**
 * Boot the org designer canvas inside #org-designer-root.
 */
export function initOrgDesigner(boot, wire) {
  window.Sortable = Sortable;
  const rootEl = document.getElementById('org-designer-root');
  if (!rootEl) {
    return;
  }
  const t = tFactory(boot.i18n || {});
  const $ = (id) => document.getElementById(id);
  let lockVersion = boot.lockVersion || 1;
  let persistTimer = null;
  let persistChain = Promise.resolve();

  function schedulePersist() {
    clearTimeout(persistTimer);
    persistTimer = setTimeout(() => { flushPersist(); }, 400);
  }

  function flushPersist() {
    persistChain = persistChain.then(async () => {
      const result = await wire.persist(JSON.parse(JSON.stringify(state)), JSON.parse(JSON.stringify(CONFIG)), lockVersion);
      if (result?.conflict) {
        applyBoot(result);
        toast(result.message || t('conflict'));
        return result;
      }
      if (result?.ok) {
        lockVersion = result.lockVersion;
        state.savedAt = result.savedAt;
        updateSaveStatus();
      }
      return result;
    }).catch((error) => {
      console.error(error);
      toast(t('conflict'));
    });
    return persistChain;
  }

  function applyBoot(payload) {
    lockVersion = payload.lockVersion || lockVersion;
    state = JSON.parse(JSON.stringify(payload.state || state));
    state.iltAreas = normalizeAreas(state.iltAreas);
    if (payload.config) {
      Object.assign(CONFIG, payload.config);
    }
    ensureBigPictureRows();
    applyTopologyColors();
    populateILTSelect();
    render();
    updateSaveStatus();
  }

  let confirmYes = null;
  function askConfirm(message, onYes) {
    const modal = $('confirmModal');
    $('confirmMessage').textContent = message;
    confirmYes = onYes;
    modal.classList.add('open');
  }
  $('confirmCancel').onclick = () => { $('confirmModal').classList.remove('open'); confirmYes = null; };
  $('confirmOk').onclick = () => {
    $('confirmModal').classList.remove('open');
    const fn = confirmYes; confirmYes = null;
    if (fn) fn();
  };

  let promptYes = null;
  function askPrompt(kind, value, onYes) {
    $('promptTitle').textContent = kind === 'new-area' ? ($('promptTitle').dataset.newArea || $('promptTitle').textContent) : ($('promptTitle').dataset.rename || $('promptTitle').textContent);
    if (kind === 'new-area') {
      $('promptTitle').textContent = $('promptTitle').dataset.newArea;
    } else {
      $('promptTitle').textContent = $('promptTitle').dataset.rename;
    }
    $('promptInput').value = value || '';
    promptYes = onYes;
    $('promptModal').classList.add('open');
    setTimeout(() => $('promptInput').focus(), 0);
  }
  $('promptCancel').onclick = () => { $('promptModal').classList.remove('open'); promptYes = null; };
  $('promptOk').onclick = () => {
    $('promptModal').classList.remove('open');
    const fn = promptYes; promptYes = null;
    if (fn) fn($('promptInput').value);
  };

  function deleteBpRowConfirmed(idx) {
    if (state.bigPictureRows.length <= 1) {
      toast(t('keep_one_row'));
      return;
    }
    const row = state.bigPictureRows[idx];
    const target = idx > 0 ? idx - 1 : 1;
    state.bigPictureRows[target].push(...row);
    state.bigPictureRows.splice(idx, 1);
    snapshot(); render();
  }

  function bulkDeleteConfirmed() {
    const area = currentArea();
    let count = 0;
    selectedCards.forEach(k => {
      const [tid, pid] = k.split("::");
      const team = area.teams.find(x => x.id === tid);
      if (!team) return;
      team.positions = team.positions.filter(p => p.id !== pid);
      count++;
    });
    clearSelection();
    snapshot(); render();
    toast(t('deleted_many', { count: String(count) }));
  }

  function teamDeleteConfirmed() {
    if (!editingTeamId) return;
    const area = currentArea();
    area.teams = area.teams.filter(t => t.id !== editingTeamId);
    $('teamModal').classList.remove('open');
    snapshot(); render();
  }

/* ============================================================
   ORG DESIGNER - v9
   Changes:
   - Removed PNG export entirely.
   - Head of Area is now fully editable (click the box), with
     Internal/External + Notes + colored person icon.
   - ILT area is renamable by clicking its title.
   ============================================================ */
const DEFAULT_ROLE_COLORS = [
  { pattern: "Team Lead", color: "#f4b7b7", group: 1 },
  { pattern: "Chief Product Owner", color: "#ffd966", group: 2 },
  { pattern: "Product Owner Lead", color: "#ffd966", group: 2 },
  { pattern: "Product Owner", color: "#ffd966", group: 2 },
  { pattern: "Chief Engineer", color: "#fff6b3", group: 3 },
  { pattern: "DevOps / IT Infrastructure Engineer", color: "#fff6b3", group: 3 },
  { pattern: "IT Support Specialist", color: "#fff6b3", group: 3 },
  { pattern: "SW Developer / Engineer", color: "#fff6b3", group: 3 },
  { pattern: "IT Security Consultant", color: "#fff6b3", group: 3 },
  { pattern: "Chief Analyst", color: "#6fa8dc", group: 4 },
  { pattern: "Digital Business Analyst", color: "#6fa8dc", group: 4 },
  { pattern: "SW Quality Assurance Engineer", color: "#6fa8dc", group: 4 },
  { pattern: "SW Quality Assurance", color: "#6fa8dc", group: 4 },
  { pattern: "UI/UX Designer / Consultant", color: "#6fa8dc", group: 4 },
  { pattern: "Chief Project Manager / Agile Coach", color: "#b7b7b7", group: 5 },
  { pattern: "Project Manager / Agile Coach Lead", color: "#b7b7b7", group: 5 },
  { pattern: "Project Manager", color: "#b7b7b7", group: 5 },
  { pattern: "IT Project Manager", color: "#b7b7b7", group: 5 },
  { pattern: "IT / Software Project Manager", color: "#b7b7b7", group: 5 },
  { pattern: "Agile Coach / Scrum Master", color: "#b7b7b7", group: 5 },
  { pattern: "IT Scrum Master", color: "#b7b7b7", group: 5 },
  { pattern: "Chief Architect", color: "#93c47d", group: 6 },
  { pattern: "Architect", color: "#93c47d", group: 6 },
  { pattern: "IT Architect", color: "#93c47d", group: 6 },
  { pattern: "Chief Cohort / Function", color: "#f4b7b7", group: 7 },
  { pattern: "Cohort / Functional Lead", color: "#f4b7b7", group: 7 },
  { pattern: "Head of IT Vertical / Horizontal", color: "#f5a623", group: 7 },
  { pattern: "Head of SV", color: "#f5a623", group: 7 },
  { pattern: "Head of HRIT", color: "#f5a623", group: 7 },
];
const DEFAULT_LOCATIONS = [
  { code: "KL", flag: "\uD83C\uDDF2\uD83C\uDDFE" },
  { code: "Buchs", flag: "\uD83C\uDDE8\uD83C\uDDED" },
  { code: "Schaan", flag: "\uD83C\uDDF1\uD83C\uDDEE" },
  { code: "Kaufering", flag: "\uD83C\uDDE9\uD83C\uDDEA" },
  { code: "HNA", flag: "\uD83C\uDDFA\uD83C\uDDF8" },
  { code: "Tulsa", flag: "\uD83C\uDDFA\uD83C\uDDF8" },
  { code: "Berkel", flag: "\uD83C\uDDF3\uD83C\uDDF1" },
];
const DEFAULT_TOPOLOGY = [
  { key: "stream-aligned", color: "#E6E0D5" },
  { key: "enabling", color: "#83D4A5" },
  { key: "platform", color: "#D2051E" },
  { key: "complicated-subsystem", color: "#edc948" },
];
let CONFIG = {
  sheetName: "New Baseline File - Option C",
  storageKey: "orgDesignerState_v5", // unused; persistence is server-side
  colWidth: 240,
  roleColors: JSON.parse(JSON.stringify(DEFAULT_ROLE_COLORS)),
  locations: JSON.parse(JSON.stringify(DEFAULT_LOCATIONS)),
  topology: JSON.parse(JSON.stringify(DEFAULT_TOPOLOGY)),
};
const FALLBACK_GROUP = 99;
function lookupRole(role) {
  if (!role) return null;
  const norm = role.trim().toLowerCase();
  return CONFIG.roleColors.find(m => m.pattern.trim().toLowerCase() === norm) || null;
}
function iconColorForRole(role) {
  const hit = lookupRole(role);
  return hit ? hit.color : "#ffffff";
}
function groupForRole(role) {
  const hit = lookupRole(role);
  return hit ? (hit.group || FALLBACK_GROUP) : FALLBACK_GROUP;
}
function flagFor(location) {
  if (!location) return "";
  const key = location.trim().toLowerCase();
  const hit = CONFIG.locations.find(l => l.code.trim().toLowerCase() === key);
  return hit ? hit.flag : escapeHtml(location);
}
function applyTopologyColors() {
  const map = {};
  CONFIG.topology.forEach(t => map[t.key] = t.color);
  rootEl.style.setProperty("--topo-stream-aligned", map["stream-aligned"] || "#E6E0D5");
  rootEl.style.setProperty("--topo-enabling", map["enabling"] || "#83D4A5");
  rootEl.style.setProperty("--topo-platform", map["platform"] || "#D2051E");
  rootEl.style.setProperty("--topo-complicated", map["complicated-subsystem"] || "#edc948");
  rootEl.style.setProperty("--team-col-width", CONFIG.colWidth + "px");
}
let KNOWN_ROLES = boot.knownRoles || [
  "Team Lead", "Product Owner", "Product Owner Lead", "Chief Product Owner",
  "Digital Business Analyst", "Chief Analyst", "SW Quality Assurance Engineer",
  "UI/UX Designer / Consultant",
  "SW Developer / Engineer", "DevOps / IT Infrastructure Engineer",
  "IT Support Specialist", "Chief Engineer", "IT Security Consultant",
  "Architect", "IT Architect", "Chief Architect",
  "Project Manager", "IT Project Manager", "Agile Coach / Scrum Master",
  "Chief Project Manager / Agile Coach", "Project Manager / Agile Coach Lead",
  "IT Scrum Master",
  "Chief Cohort / Function", "Cohort / Functional Lead",
  "Head of IT Vertical / Horizontal", "Head of SV", "Head of HRIT",
];
let state = {
  iltAreas: {},
  currentILT: null,
  viewMode: "team",
  bigPictureOrder: [],
  bigPictureRows: [[]],
  zoom: 1.0,
  savedAt: null,
  dirty: false,
};
function ensureBigPictureRows() {
  if (!Array.isArray(state.bigPictureRows) || !state.bigPictureRows.length) {
    const src = (Array.isArray(state.bigPictureOrder) && state.bigPictureOrder.length)
      ? state.bigPictureOrder
      : Object.keys(state.iltAreas);
    state.bigPictureRows = [src.slice()];
  }
  const known = new Set(Object.keys(state.iltAreas));
  const seen = new Set();
  state.bigPictureRows = state.bigPictureRows.map(row =>
    row.filter(n => {
      if (!known.has(n) || seen.has(n)) return false;
      seen.add(n); return true;
    })
  );
  const missing = [...known].filter(n => !seen.has(n));
  if (missing.length) {
    if (!state.bigPictureRows.length) state.bigPictureRows.push([]);
    state.bigPictureRows[state.bigPictureRows.length - 1].push(...missing);
  }
  if (!state.bigPictureRows.length) state.bigPictureRows = [[]];
}
let history = [];
let historyIndex = -1;
let selectedCards = new Set();
function snapshot() {
  history = history.slice(0, historyIndex + 1);
  history.push(JSON.parse(JSON.stringify(state)));
  if (history.length > 50) history.shift();
  historyIndex = history.length - 1;
  state.dirty = true;
  persist();
  updateUndoRedoButtons();
  updateSaveStatus();
}
function undo() {
  if (historyIndex > 0) {
    historyIndex--;
    state = JSON.parse(JSON.stringify(history[historyIndex]));
    render(); persist(); updateUndoRedoButtons(); updateSaveStatus();
  }
}
function redo() {
  if (historyIndex < history.length - 1) {
    historyIndex++;
    state = JSON.parse(JSON.stringify(history[historyIndex]));
    render(); persist(); updateUndoRedoButtons(); updateSaveStatus();
  }
}
function updateUndoRedoButtons() {
  $("btnUndo").disabled = historyIndex <= 0;
  $("btnRedo").disabled = historyIndex >= history.length - 1;
}
function updateSaveStatus() {
  const el = $("saveStatus");
  const txt = $("saveStatusText");
  if (state.savedAt) {
    const d = new Date(state.savedAt);
    const time = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    txt.textContent = t("auto_saved", { time });
  } else {
    txt.textContent = t("not_saved");
  }
  el.classList.remove("dirty");
}
function persist() {
  state.savedAt = new Date().toISOString();
  state.dirty = false;
  schedulePersist();
}
function restore() {
  return false;
}
function uid(prefix="ID") {
  return prefix + "_" + Math.random().toString(36).slice(2,8) + Date.now().toString(36).slice(-4);
}
function toast(msg) {
  const t = $("toast");
  t.textContent = msg;
  t.classList.add("show");
  clearTimeout(t._to);
  t._to = setTimeout(() => t.classList.remove("show"), 1800);
}
function currentArea() { return state.iltAreas[state.currentILT] || null; }
function escapeHtml(s) {
  return String(s||"").replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function sortPositionsFull(team) {
  team.positions.sort((a, b) => {
    const gA = groupForRole(a.role);
    const gB = groupForRole(b.role);
    if (gA !== gB) return gA - gB;
    const grA = parseFloat(a.grade) || 0;
    const grB = parseFloat(b.grade) || 0;
    return grB - grA;
  });
}
function sortPositions(team) {
  // Keep whatever sits in the lead slot (index 0) pinned in place, since it may
  // have been manually dragged there (any role) and shouldn't be bumped by
  // auto-sorting. Only positions from index 1 onward get sorted by role group.
  const lead = team.positions.length ? team.positions[0] : null;
  const rest = lead ? team.positions.slice(1) : team.positions;
  rest.sort((a, b) => {
    const gA = groupForRole(a.role);
    const gB = groupForRole(b.role);
    if (gA !== gB) return gA - gB;
    const grA = parseFloat(a.grade) || 0;
    const grB = parseFloat(b.grade) || 0;
    return grB - grA;
  });
  team.positions = lead ? [lead, ...rest] : rest;
}
function renderTwemoji(root) {
  if (typeof twemoji === "undefined" || !twemoji.parse) return;
  const container = root || document.body;
  const targets = container.querySelectorAll(".flag, .flag-here");
  targets.forEach(el => {
    twemoji.parse(el, {
      folder: "svg",
      ext: ".svg",
      base: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/",
    });
  });
}
function normalizeHeader(s) {
  return String(s||"").trim().toLowerCase().replace(/[\s_/]+/g, "");
}
let pendingFile = null;
let pendingImportMode = "add";
async function handleFile(file) {
  /* Livewire handles Excel upload */
}
async function doImport() {
  /* Livewire handles Excel import */
}
document.querySelectorAll(".upload-choice").forEach(el => {
  el.addEventListener("click", () => {
    document.querySelectorAll(".upload-choice").forEach(x => x.classList.remove("selected"));
    el.classList.add("selected");
    pendingImportMode = el.dataset.mode;
  });
});
$("uploadCancel").onclick = () => {
  $("uploadModal").classList.remove("open");
  pendingFile = null;
  wire.set('excelFile', null);
};
$("uploadConfirm").onclick = async () => {
  $("uploadModal").classList.remove("open");
  await wire.set('importMode', pendingImportMode);
  await wire.importExcel();
};
function populateILTSelect() {
  const sel = $("iltSelect");
  sel.innerHTML = "";
  Object.keys(state.iltAreas).forEach(name => {
    const opt = document.createElement("option");
    opt.value = name; opt.textContent = name;
    if (name === state.currentILT) opt.selected = true;
    sel.appendChild(opt);
  });
  const dl = $("roleList");
  dl.innerHTML = "";
  KNOWN_ROLES.forEach(r => {
    const o = document.createElement("option"); o.value = r; dl.appendChild(o);
  });
  const locDL = $("locationList");
  locDL.innerHTML = "";
  CONFIG.locations.forEach(l => {
    const o = document.createElement("option"); o.value = l.code; locDL.appendChild(o);
  });
}
function personIconSVG(color) {
  return `<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
    <circle cx="16" cy="10" r="6" fill="${color}" stroke="#888" stroke-width="1"/>
    <path d="M4 30 C4 20 12 18 16 18 C20 18 28 20 28 30 Z" fill="${color}" stroke="#888" stroke-width="1"/>
  </svg>`;
}
function selKey(teamId, posId) { return teamId + "::" + posId; }
function renderPCard(pos, teamId) {
  const div = document.createElement("div");
  div.className = "pcard " + (pos.intExt === "External" ? "external" : "internal");
  div.dataset.posId = pos.id;
  div.dataset.teamId = teamId;
  if (selectedCards.has(selKey(teamId, pos.id))) div.classList.add("selected");
  const iconColor = iconColorForRole(pos.role);
  div.innerHTML = `
    <input type="checkbox" class="select-check" ${selectedCards.has(selKey(teamId, pos.id))?"checked":""} title="${escapeHtml(t("select"))}">
    <div class="icon">${personIconSVG(iconColor)}</div>
    <div class="info">
      <div class="role" title="${escapeHtml(pos.role)}">${escapeHtml(pos.role || "—")}</div>
      <div class="meta">
        <span>${escapeHtml(t("grade"))}: ${escapeHtml(pos.grade || "—")}</span>
        <span class="flag" title="${escapeHtml(pos.location)}">${flagFor(pos.location)}</span>
      </div>
    </div>
    <div class="card-actions">
      <button data-act="dup" title="${escapeHtml(t("duplicate"))}">⧉</button>
      <button data-act="del" title="${escapeHtml(t("delete"))}">×</button>
    </div>
  `;
  div.addEventListener("click", (e) => {
    if (e.target.classList.contains("select-check")) return;
    if (e.target.closest(".card-actions")) return;
    if (e.shiftKey || e.ctrlKey || e.metaKey) {
      toggleSelect(teamId, pos.id);
      div.classList.toggle("selected");
      div.querySelector(".select-check").checked = selectedCards.has(selKey(teamId, pos.id));
      updateBulkBar();
      return;
    }
    openCardModal(teamId, pos.id);
  });
  div.querySelector(".select-check").addEventListener("click", (e) => {
    e.stopPropagation();
    toggleSelect(teamId, pos.id);
    div.classList.toggle("selected");
    updateBulkBar();
  });
  div.querySelector('[data-act="del"]').addEventListener("click", (e) => {
    e.stopPropagation();
    askConfirm(t("delete_position", { role: pos.role || "position" }), () => deletePosition(teamId, pos.id));
  });
  div.querySelector('[data-act="dup"]').addEventListener("click", (e) => {
    e.stopPropagation();
    duplicatePosition(teamId, pos.id);
  });
  return div;
}
function renderTeamCol(team) {
  const col = document.createElement("div");
  col.className = "team-col";
  col.dataset.teamId = team.id;
  const head = document.createElement("div");
  head.className = "team-header " + (team.topology||"stream-aligned");
  const totalPos = team.positions.length;
  head.innerHTML = `
    <div class="topo-label">${escapeHtml(team.topology||"")}</div>
    <div class="team-name">${escapeHtml(team.name||t("untitled_team"))}</div>
    <div class="team-products">${team.products.map(escapeHtml).join(" · ") || "&nbsp;"}</div>
    <div class="team-header-actions">
      <button data-act="edit">${escapeHtml(t("edit"))}</button>
      <button data-act="add">${escapeHtml(t("add_position"))}</button>
      <span class="pos-count">${escapeHtml(t("pos_count", { count: totalPos }))}</span>
    </div>
  `;
  head.querySelector('[data-act="edit"]').addEventListener("click", () => openTeamModal(team.id));
  head.querySelector('[data-act="add"]').addEventListener("click", () => openCardModal(team.id, null));
  const body = document.createElement("div");
  body.className = "team-body";
  body.dataset.teamId = team.id;
  const hasLead = team.positions.length > 0;
  if (!hasLead) {
    const ph = document.createElement("div");
    ph.className = "position-row lead";
    ph.innerHTML = `<div class="lead-placeholder" title="${escapeHtml(t("lead_slot_title"))}">${escapeHtml(t("lead_placeholder"))}</div>`;
    ph.querySelector(".lead-placeholder").addEventListener("click", () => openCardModal(team.id, null, {role: "Team Lead"}));
    body.appendChild(ph);
  }
  team.positions.forEach((p, idx) => {
    const row = document.createElement("div");
    const isLead = idx === 0;
    row.className = "position-row " + (isLead ? "lead" : "member");
    row.appendChild(renderPCard(p, team.id));
    body.appendChild(row);
  });
  const addBtn = document.createElement("button");
  addBtn.className = "add-card-btn";
  addBtn.textContent = t("add_position_full");
  addBtn.addEventListener("click", () => openCardModal(team.id, null));
  body.appendChild(addBtn);
  col.appendChild(head);
  col.appendChild(body);
  new Sortable(body, {
    group: "positions",
    animation: 150,
    draggable: ".position-row",
    filter: ".add-card-btn, .lead-placeholder",
    preventOnFilter: false,
    onEnd: (evt) => { handleCardMove(evt); }
  });
  return col;
}
function render() {
  applyTopologyColors();
  applyZoom();
  if (state.viewMode === "bigpicture") {
    renderBigPicture();
    $("teamView").style.display = "none";
    $("bigPicture").style.display = "flex";
    $("btnBigPicture").style.display = "none";
    $("btnTeamView").style.display = "";
    updateBulkBar();
    renderTwemoji($("bigPicture"));
    return;
  }
  $("teamView").style.display = "";
  $("bigPicture").style.display = "none";
  $("btnBigPicture").style.display = "";
  $("btnTeamView").style.display = "none";
  const area = currentArea();
  const row = $("teamsRow");
  row.innerHTML = "";
  if (!area) {
    $("iltTitle").innerHTML = escapeHtml(t("no_file_loaded")) + '<span class="edit-hint">' + escapeHtml(t("rename_hint")) + '</span>';
    $("iltHeadBox").style.display = "none";
    $("emptyState").style.display = "";
    updateBulkBar();
    return;
  }
  $("emptyState").style.display = "none";
  $("iltTitle").innerHTML = escapeHtml(area.name) + '<span class="edit-hint">' + escapeHtml(t("rename_hint")) + '</span>';
  if (area.head) {
    $("iltHeadBox").style.display = "";
    $("iltHeadBox").classList.toggle("external", area.head.intExt === "External");
    $("iltHeadIcon").innerHTML = personIconSVG(iconColorForRole(area.head.role));
    $("iltHeadRole").textContent = area.head.role || "—";
    $("iltHeadGrade").innerHTML = escapeHtml(t("grade")) + ": " + escapeHtml(area.head.grade || "—") + ' <span class="flag-here">' + flagFor(area.head.location) + '</span>';
  } else {
    $("iltHeadBox").style.display = "none";
  }
  area.teams.forEach(team => row.appendChild(renderTeamCol(team)));
  const addCol = document.createElement("div");
  addCol.className = "add-team-col";
  addCol.textContent = t("add_team_col");
  addCol.addEventListener("click", () => openTeamModal(null));
  row.appendChild(addCol);
  new Sortable(row, {
    animation: 150,
    handle: ".team-header",
    draggable: ".team-col",
    onEnd: (evt) => {
      const area = currentArea();
      const [moved] = area.teams.splice(evt.oldIndex, 1);
      area.teams.splice(evt.newIndex, 0, moved);
      snapshot();
    }
  });
  updateBulkBar();
  renderTwemoji($("teamView"));
}
function renderReadOnlyTeamCol(team) {
  const col = document.createElement("div");
  col.className = "team-col";
  const head = document.createElement("div");
  head.className = "team-header " + (team.topology||"stream-aligned");
  const totalPos = team.positions.length;
  head.innerHTML = `
    <div class="topo-label">${escapeHtml(team.topology||"")}</div>
    <div class="team-name">${escapeHtml(team.name||t("untitled_team"))}</div>
    <div class="team-products">${team.products.map(escapeHtml).join(" · ") || "&nbsp;"}</div>
    <div class="team-header-actions">
      <span class="pos-count">${escapeHtml(t("pos_count", { count: totalPos }))}</span>
    </div>
  `;
  const body = document.createElement("div");
  body.className = "team-body";
  const hasLead = team.positions.length > 0;
  if (!hasLead) {
    const ph = document.createElement("div");
    ph.className = "position-row lead";
    ph.innerHTML = `<div class="lead-placeholder">${escapeHtml(t("no_lead"))}</div>`;
    body.appendChild(ph);
  }
  team.positions.forEach((p, idx) => {
    const row = document.createElement("div");
    const isLead = idx === 0;
    row.className = "position-row " + (isLead ? "lead" : "member");
    const card = document.createElement("div");
    card.className = "pcard " + (p.intExt === "External" ? "external" : "internal");
    const iconColor = iconColorForRole(p.role);
    card.innerHTML = `
      <div class="icon">${personIconSVG(iconColor)}</div>
      <div class="info">
        <div class="role" title="${escapeHtml(p.role)}">${escapeHtml(p.role || "—")}</div>
        <div class="meta">
          <span>${escapeHtml(t("grade"))}: ${escapeHtml(p.grade || "—")}</span>
          <span class="flag" title="${escapeHtml(p.location)}">${flagFor(p.location)}</span>
        </div>
      </div>
    `;
    row.appendChild(card);
    body.appendChild(row);
  });
  col.appendChild(head);
  col.appendChild(body);
  return col;
}
function renderBigPicture() {
  const bp = $("bigPicture");
  bp.innerHTML = "";
  ensureBigPictureRows();
  const toolbar = document.createElement("div");
  toolbar.className = "bp-toolbar";
  toolbar.innerHTML = `
    <button class="btn secondary" id="bpAddRow">${escapeHtml(t("add_row"))}</button>
    <span class="hint">${escapeHtml(t("bp_hint"))}</span>
  `;
  bp.appendChild(toolbar);
  state.bigPictureRows.forEach((row, rowIdx) => {
    const rowEl = document.createElement("div");
    rowEl.className = "bp-row" + (row.length === 0 ? " empty" : "");
    rowEl.dataset.rowIdx = rowIdx;
    const handle = document.createElement("div");
    handle.className = "bp-row-drag-handle";
    handle.title = t("drag_row");
    handle.textContent = t("row_label", { n: rowIdx + 1 });
    rowEl.appendChild(handle);
    const inner = document.createElement("div");
    inner.className = "bp-row-inner";
    inner.style.display = "flex";
    inner.style.gap = "16px";
    inner.style.flex = "1";
    inner.style.alignItems = "flex-start";
    inner.style.flexWrap = "nowrap";
    inner.style.overflowX = "auto";
    inner.style.minWidth = "0";
    inner.dataset.rowIdx = rowIdx;
    rowEl.appendChild(inner);
    row.forEach(name => {
      const area = state.iltAreas[name];
      if (!area) return;
      inner.appendChild(renderIltAreaTile(area));
    });
    if (row.length === 0) {
      const hint = document.createElement("div");
      hint.style.cssText = "color:#999;font-size:12px;flex:1;text-align:center;padding:14px;";
      hint.textContent = t("drop_here");
      inner.appendChild(hint);
    }
    const actions = document.createElement("div");
    actions.className = "bp-row-actions";
    actions.innerHTML = `
      <button data-act="row-up" ${rowIdx===0?"disabled":""} title="${escapeHtml(t("move_row_up"))}">▲</button>
      <button data-act="row-down" ${rowIdx===state.bigPictureRows.length-1?"disabled":""} title="${escapeHtml(t("move_row_down"))}">▼</button>
      <button data-act="row-delete" title="${escapeHtml(t("delete_row"))}">×</button>
    `;
    actions.querySelector('[data-act="row-up"]').onclick = () => moveRow(rowIdx, -1);
    actions.querySelector('[data-act="row-down"]').onclick = () => moveRow(rowIdx, 1);
    actions.querySelector('[data-act="row-delete"]').onclick = () => deleteBpRow(rowIdx);
    rowEl.appendChild(actions);
    bp.appendChild(rowEl);
    new Sortable(inner, {
      group: "bpAreas",
      animation: 150,
      draggable: ".bp-area",
      onEnd: () => {
        state.bigPictureRows = Array.from(bp.querySelectorAll(".bp-row-inner")).map(el =>
          Array.from(el.querySelectorAll(".bp-area")).map(a => a.dataset.iltName)
        );
        snapshot(); render();
      }
    });
  });
  const addRow = document.createElement("div");
  addRow.className = "bp-add-row";
  addRow.textContent = t("add_row");
  addRow.onclick = () => {
    state.bigPictureRows.push([]);
    snapshot(); render();
  };
  bp.appendChild(addRow);
  new Sortable(bp, {
    animation: 150,
    draggable: ".bp-row",
    handle: ".bp-row-drag-handle",
    onEnd: () => {
      state.bigPictureRows = Array.from(bp.querySelectorAll(".bp-row")).map(rowEl =>
        Array.from(rowEl.querySelectorAll(".bp-area")).map(a => a.dataset.iltName)
      );
      snapshot(); render();
    }
  });
  $("bpAddRow").onclick = () => {
    state.bigPictureRows.push([]);
    snapshot(); render();
  };
}
function renderIltAreaTile(area) {
  const tile = document.createElement("div");
  tile.className = "bp-area";
  tile.dataset.iltName = area.name;
  const header = document.createElement("div");
  header.className = "bp-area-header";
  const totalPos = area.teams.reduce((s,t) => s + t.positions.length, 0);
  header.innerHTML = `
    <h3>${escapeHtml(area.name)}</h3>
    <span class="bp-area-stat">${escapeHtml(t("stat", { teams: area.teams.length, positions: totalPos }))}</span>
    <button class="bp-area-open" title="${escapeHtml(t("open"))}">${escapeHtml(t("open"))}</button>
  `;
  header.querySelector(".bp-area-open").onclick = (e) => {
    e.stopPropagation();
    state.currentILT = area.name;
    state.viewMode = "team";
    populateILTSelect();
    render();
  };
  header.addEventListener("dblclick", (e) => {
    if (e.target.closest("button")) return;
    state.currentILT = area.name;
    state.viewMode = "team";
    populateILTSelect();
    render();
  });
  tile.appendChild(header);
  const teamsWrap = document.createElement("div");
  teamsWrap.className = "bp-area-teams";
  area.teams.forEach(team => teamsWrap.appendChild(renderReadOnlyTeamCol(team)));
  tile.appendChild(teamsWrap);
  return tile;
}
function moveRow(idx, delta) {
  const target = idx + delta;
  if (target < 0 || target >= state.bigPictureRows.length) return;
  const rows = state.bigPictureRows;
  [rows[idx], rows[target]] = [rows[target], rows[idx]];
  snapshot(); render();
}
function deleteBpRow(idx) {
  if (state.bigPictureRows.length <= 1) {
    toast(t("keep_one_row"));
    return;
  }
  const row = state.bigPictureRows[idx];
  if (row.length) { askConfirm(t("delete_row"), () => deleteBpRowConfirmed(idx)); return; }
  const target = idx > 0 ? idx - 1 : 1;
  state.bigPictureRows[target].push(...row);
  state.bigPictureRows.splice(idx, 1);
  snapshot(); render();
}
function toggleSelect(teamId, posId) {
  const k = selKey(teamId, posId);
  if (selectedCards.has(k)) selectedCards.delete(k);
  else selectedCards.add(k);
}
function updateBulkBar() {
  const bar = $("bulkBar");
  $("bulkCount").textContent = selectedCards.size;
  if (selectedCards.size > 0) {
    bar.classList.add("show");
    rootEl.classList.add("bulk-mode");
    const sel = $("bulkMoveTeam");
    sel.innerHTML = "";
    (currentArea()?.teams||[]).forEach(t => {
      const o = document.createElement("option");
      o.value = t.id; o.textContent = t.name;
      sel.appendChild(o);
    });
  } else {
    bar.classList.remove("show");
    rootEl.classList.remove("bulk-mode");
  }
}
function clearSelection() {
  selectedCards.clear();
  updateBulkBar();
  document.querySelectorAll(".pcard.selected").forEach(el => {
    el.classList.remove("selected");
    const cb = el.querySelector(".select-check"); if (cb) cb.checked = false;
  });
}
function bulkMove(toTeamId) {
  const area = currentArea();
  const toTeam = area.teams.find(t => t.id === toTeamId);
  if (!toTeam) return;
  const moved = [];
  selectedCards.forEach(k => {
    const [tid, pid] = k.split("::");
    const t = area.teams.find(x => x.id === tid);
    if (!t) return;
    const idx = t.positions.findIndex(p => p.id === pid);
    if (idx === -1) return;
    const [pos] = t.positions.splice(idx, 1);
    moved.push(pos);
  });
  toTeam.positions.push(...moved);
  sortPositions(toTeam);
  clearSelection();
  snapshot(); render();
    toast(t("moved", { count: String(moved.length), team: toTeam.name }));
}
function bulkDuplicate() {
  const area = currentArea();
  let count = 0;
  const newSel = new Set();
  selectedCards.forEach(k => {
    const [tid, pid] = k.split("::");
    const t = area.teams.find(x => x.id === tid);
    if (!t) return;
    const p = t.positions.find(p => p.id === pid);
    if (!p) return;
    const copy = { ...p, id: uid("Pos") };
    t.positions.push(copy);
    newSel.add(selKey(tid, copy.id));
    count++;
  });
  area.teams.forEach(sortPositions);
  selectedCards = newSel;
  snapshot(); render();
  toast(t("duplicated_many", { count: String(count) }));
}
function bulkDelete() {
  askConfirm(t("delete_positions", { count: String(selectedCards.size) }), () => bulkDeleteConfirmed()); return;
  const area = currentArea();
  let count = 0;
  selectedCards.forEach(k => {
    const [tid, pid] = k.split("::");
    const t = area.teams.find(x => x.id === tid);
    if (!t) return;
    t.positions = t.positions.filter(p => p.id !== pid);
    count++;
  });
  clearSelection();
  snapshot(); render();
  toast(`Deleted ${count} position(s)`);
}
function bulkFlip() {
  const area = currentArea();
  selectedCards.forEach(k => {
    const [tid, pid] = k.split("::");
    const t = area.teams.find(x => x.id === tid);
    const p = t?.positions.find(p => p.id === pid);
    if (p) p.intExt = p.intExt === "Internal" ? "External" : "Internal";
  });
  snapshot(); render();
}
$("bulkMoveTeam").addEventListener("change", (e) => {
  if (e.target.value && selectedCards.size > 0) bulkMove(e.target.value);
});
$("bulkDuplicate").onclick = bulkDuplicate;
$("bulkDelete").onclick = bulkDelete;
$("bulkFlip").onclick = bulkFlip;
$("bulkClear").onclick = clearSelection;
function handleCardMove(evt) {
  const fromTeamId = evt.from.dataset.teamId;
  const toTeamId = evt.to.dataset.teamId;
  const posId = evt.item.querySelector(".pcard")?.dataset.posId;
  if (!posId) return;
  const area = currentArea();
  const fromTeam = area.teams.find(t => t.id === fromTeamId);
  const toTeam = area.teams.find(t => t.id === toTeamId);
  if (!fromTeam || !toTeam) return;
  const idxFrom = fromTeam.positions.findIndex(p => p.id === posId);
  if (idxFrom === -1) return;
  const [pos] = fromTeam.positions.splice(idxFrom, 1);
  const destRows = Array.from(evt.to.children).filter(el => el.classList.contains("position-row") && !el.querySelector(".lead-placeholder"));
  let insertIdx = destRows.indexOf(evt.item);
  if (insertIdx === -1) insertIdx = toTeam.positions.length;
  toTeam.positions.splice(insertIdx, 0, pos);
  snapshot();
  render();
}
let editingContext = { teamId: null, posId: null };
function openCardModal(teamId, posId, defaults={}) {
  editingContext = { teamId, posId };
  const area = currentArea();
  const team = area.teams.find(t => t.id === teamId);
  const pos = posId ? team.positions.find(p => p.id === posId) : null;
  $("cardModalTitle").textContent = pos ? t("edit_position") : t("new_position", { team: team.name });
  $("cardRole").value = pos?.role || defaults.role || "";
  $("cardGrade").value = pos?.grade || defaults.grade || "";
  $("cardFTE").value = pos?.fte || "1";
  $("cardIntExt").value = pos?.intExt || "Internal";
  $("cardLocation").value = pos?.location || defaults.location || "";
  $("cardNotes").value = pos?.notes || "";
  $("cardDuplicate").style.display = pos ? "" : "none";
  $("cardModal").classList.add("open");
}
$("cardCancel").onclick = () => $("cardModal").classList.remove("open");
$("cardDuplicate").onclick = () => {
  const { teamId, posId } = editingContext;
  if (posId) duplicatePosition(teamId, posId);
  $("cardModal").classList.remove("open");
};
$("cardSave").onclick = () => {
  const { teamId, posId } = editingContext;
  const area = currentArea();
  const team = area.teams.find(t => t.id === teamId);
  const data = {
    role: $("cardRole").value.trim(),
    grade: $("cardGrade").value.trim(),
    fte: $("cardFTE").value.trim() || "1",
    intExt: $("cardIntExt").value,
    location: $("cardLocation").value.trim(),
    notes: $("cardNotes").value.trim(),
  };
  if (posId) {
    const p = team.positions.find(p => p.id === posId);
    Object.assign(p, data);
  } else {
    team.positions.push({ id: uid("Pos"), ...data });
  }
  sortPositions(team);
  $("cardModal").classList.remove("open");
  snapshot(); render();
};
function deletePosition(teamId, posId) {
  const team = currentArea().teams.find(t => t.id === teamId);
  team.positions = team.positions.filter(p => p.id !== posId);
  selectedCards.delete(selKey(teamId, posId));
  snapshot(); render();
}
function duplicatePosition(teamId, posId) {
  const team = currentArea().teams.find(t => t.id === teamId);
  const p = team.positions.find(p => p.id === posId);
  if (!p) return;
  const copy = { ...p, id: uid("Pos") };
  team.positions.push(copy);
  sortPositions(team);
  snapshot(); render();
  toast(t("duplicated"));
}
let editingTeamId = null;
function openTeamModal(teamId) {
  editingTeamId = teamId;
  const area = currentArea();
    if (!area) { toast(t("need_area")); return; }
  const team = teamId ? area.teams.find(t => t.id === teamId) : null;
  $("teamModalTitle").textContent = team ? t("edit_team") : t("new_team");
  $("teamName").value = team?.name || "";
  $("teamTopo").value = team?.topology || "stream-aligned";
  $("teamP1").value = team?.products[0] || "";
  $("teamP2").value = team?.products[1] || "";
  $("teamP3").value = team?.products[2] || "";
  $("teamNotes").value = team?.notes || "";
  $("teamDelete").style.display = team ? "" : "none";
  $("teamModal").classList.add("open");
}
$("teamCancel").onclick = () => $("teamModal").classList.remove("open");
$("teamDelete").onclick = () => {
  if (!editingTeamId) return;
  askConfirm(t("delete_team"), () => teamDeleteConfirmed());
};
$("teamSave").onclick = () => {
  const area = currentArea();
  const data = {
    name: $("teamName").value.trim() || t("untitled_team"),
    topology: $("teamTopo").value,
    products: [$("teamP1").value.trim(), $("teamP2").value.trim(), $("teamP3").value.trim()].filter(x => x),
    notes: $("teamNotes").value.trim(),
  };
  if (editingTeamId) {
    const team = area.teams.find(item => item.id === editingTeamId);
    Object.assign(team, data);
  } else {
    area.teams.push({ id: uid("Team"), positions: [], ...data });
  }
  $("teamModal").classList.remove("open");
  snapshot(); render();
};
/* ===== Head of Area (fully editable) ===== */
function openHeadModal() {
  const area = currentArea();
    if (!area) { toast(t("need_area")); return; }
  $("headRole").value = area.head?.role || "";
  $("headGrade").value = area.head?.grade || "";
  $("headIntExt").value = area.head?.intExt || "Internal";
  $("headLocation").value = area.head?.location || "";
  $("headNotes").value = area.head?.notes || "";
  $("headModal").classList.add("open");
}
$("btnEditHead").onclick = openHeadModal;
$("iltHeadBox").addEventListener("click", openHeadModal);
$("headCancel").onclick = () => $("headModal").classList.remove("open");
$("headClear").onclick = () => {
  currentArea().head = null;
  $("headModal").classList.remove("open");
  snapshot(); render();
};
$("headSave").onclick = () => {
  const area = currentArea();
  const role = $("headRole").value.trim();
  if (!role) {
    area.head = null;
  } else {
    area.head = {
      role: role,
      grade: $("headGrade").value.trim(),
      intExt: $("headIntExt").value,
      location: $("headLocation").value.trim(),
      notes: $("headNotes").value.trim(),
    };
  }
  $("headModal").classList.remove("open");
  snapshot(); render();
};
/* ===== Rename current ILT area by clicking the title ===== */
function renameCurrentILT() {
  const old = state.currentILT;
  if (!old) { toast(t("need_area")); return; }
  askPrompt("rename", old, (input) => {
    const newName = (input || "").trim();
    if (!newName || newName === old) return;
    if (state.iltAreas[newName]) { toast(t("area_exists")); return; }
    const rebuilt = {};
    Object.keys(state.iltAreas).forEach(k => {
      if (k === old) {
        state.iltAreas[old].name = newName;
        rebuilt[newName] = state.iltAreas[old];
      } else {
        rebuilt[k] = state.iltAreas[k];
      }
    });
    state.iltAreas = rebuilt;
    state.currentILT = newName;
    state.bigPictureOrder = (state.bigPictureOrder || []).map(n => n === old ? newName : n);
    state.bigPictureRows = (state.bigPictureRows || []).map(r => r.map(n => n === old ? newName : n));
    populateILTSelect();
    snapshot(); render();
    toast(t("renamed", { name: newName }));
  });
}
$("iltTitle").addEventListener("click", renameCurrentILT);
document.querySelectorAll(".tab").forEach(t => {
  t.addEventListener("click", () => {
    document.querySelectorAll(".tab").forEach(x => x.classList.remove("active"));
    t.classList.add("active");
    const target = t.dataset.tab;
    document.querySelectorAll(".tab-pane").forEach(p => {
      p.style.display = (p.dataset.pane === target) ? "" : "none";
    });
  });
});
function renderRoleConfig() {
  const container = $("roleConfigList");
  container.innerHTML = "";
  const header = document.createElement("div");
  header.style.cssText = "display:grid;grid-template-columns:1fr 60px 100px 30px;gap:8px;font-size:11px;color:#888;font-weight:700;margin-bottom:4px;padding:0 4px;";
  header.innerHTML = `<div>${escapeHtml(t("role_pattern"))}</div><div>${escapeHtml(t("group"))}</div><div>${escapeHtml(t("color"))}</div><div></div>`;
  container.appendChild(header);
  CONFIG.roleColors.forEach((m, i) => {
    const row = document.createElement("div");
    row.style.cssText = "display:grid;grid-template-columns:1fr 60px 100px 30px;gap:8px;margin-bottom:5px;align-items:center;";
    row.innerHTML = `
      <input type="text" value="${escapeHtml(m.pattern)}" data-i="${i}" data-f="pattern" placeholder="${escapeHtml(t("role_name"))}" style="padding:5px 7px;font-size:12px;border:1px solid var(--border);border-radius:4px;" />
      <input type="number" min="1" max="99" value="${m.group||99}" data-i="${i}" data-f="group" style="padding:5px 7px;font-size:12px;border:1px solid var(--border);border-radius:4px;text-align:center;" />
      <input type="color" value="${m.color}" data-i="${i}" data-f="color" style="height:28px;padding:0;width:100%;cursor:pointer;border:1px solid var(--border);border-radius:4px;" />
      <button data-i="${i}" data-del="role" style="background:none;border:none;cursor:pointer;color:#999;font-size:15px;">×</button>
    `;
    container.appendChild(row);
  });
  container.querySelectorAll("input").forEach(inp => {
    inp.addEventListener("input", (e) => {
      const i = +e.target.dataset.i;
      const f = e.target.dataset.f;
      let v = e.target.value;
      if (f === "group") v = parseInt(v)||99;
      CONFIG.roleColors[i][f] = v;
    });
  });
  container.querySelectorAll("[data-del]").forEach(b => {
    b.onclick = () => { CONFIG.roleColors.splice(+b.dataset.i, 1); renderRoleConfig(); };
  });
}
$("addRoleMapping").onclick = () => {
  CONFIG.roleColors.push({ pattern: "", color: "#ffffff", group: 99 });
  renderRoleConfig();
};
function renderLocationConfig() {
  const container = $("locationConfigList");
  container.innerHTML = "";
  CONFIG.locations.forEach((m, i) => {
    const row = document.createElement("div");
    row.className = "config-row";
    row.innerHTML = `
      <input type="text" value="${escapeHtml(m.code)}" data-i="${i}" data-f="code" placeholder="${escapeHtml(t("location_code"))}" />
      <input type="text" value="${escapeHtml(m.flag)}" data-i="${i}" data-f="flag" placeholder="\uD83C\uDDE9\uD83C\uDDEA" />
      <button data-i="${i}" data-del="loc">×</button>
    `;
    container.appendChild(row);
  });
  container.querySelectorAll("input").forEach(inp => {
    inp.addEventListener("input", (e) => {
      const i = +e.target.dataset.i;
      CONFIG.locations[i][e.target.dataset.f] = e.target.value;
    });
  });
  container.querySelectorAll("[data-del]").forEach(b => {
    b.onclick = () => { CONFIG.locations.splice(+b.dataset.i, 1); renderLocationConfig(); };
  });
}
$("addLocationMapping").onclick = () => {
  CONFIG.locations.push({ code: "", flag: "" });
  renderLocationConfig();
};
function renderTopologyConfig() {
  const container = $("topologyConfigList");
  container.innerHTML = "";
  CONFIG.topology.forEach((m, i) => {
    const row = document.createElement("div");
    row.className = "config-row";
    row.innerHTML = `
      <input type="text" value="${escapeHtml(m.key)}" disabled />
      <input type="color" value="${m.color}" data-i="${i}" />
      <span></span>
    `;
    container.appendChild(row);
  });
  container.querySelectorAll('input[type="color"]').forEach(inp => {
    inp.addEventListener("input", (e) => {
      CONFIG.topology[+e.target.dataset.i].color = e.target.value;
    });
  });
}
$("btnSettings").onclick = () => {
  $("settingSheetName").value = CONFIG.sheetName;
  $("settingIltName").value = state.currentILT || "";
  $("settingColWidth").value = CONFIG.colWidth;
  renderRoleConfig();
  renderLocationConfig();
  renderTopologyConfig();
  $("settingsModal").classList.add("open");
};
$("settingsCancel").onclick = () => {
  $("settingsModal").classList.remove("open");
  restore();
  render();
};
$("settingsSave").onclick = () => {
  CONFIG.sheetName = $("settingSheetName").value.trim() || CONFIG.sheetName;
  CONFIG.colWidth = Math.max(180, Math.min(400, +$("settingColWidth").value || 240));
  const newName = $("settingIltName").value.trim();
  if (newName && state.currentILT && newName !== state.currentILT) {
    const old = state.currentILT;
    state.iltAreas[newName] = state.iltAreas[old];
    state.iltAreas[newName].name = newName;
    delete state.iltAreas[old];
    state.currentILT = newName;
    state.bigPictureOrder = state.bigPictureOrder.map(n => n === old ? newName : n);
    state.bigPictureRows = (state.bigPictureRows||[]).map(row => row.map(n => n === old ? newName : n));
    populateILTSelect();
  }
  Object.values(state.iltAreas).forEach(a => a.teams.forEach(sortPositions));
  applyTopologyColors();
  populateILTSelect();
  $("settingsModal").classList.remove("open");
  snapshot(); render();
};
function exportExcel() {
  flushPersist().then(() => { window.location = boot.exportExcelUrl; });
}
function saveProject() {
  flushPersist().then(() => { window.location = boot.exportJsonUrl; });
}
async function loadProjectFile(file) {
  /* Livewire handles JSON upload */
}
function clearAll() {
  askConfirm(t("clear_all"), () => wire.resetProject());
}
$("btnSaveMenu").addEventListener("click", (e) => {
  e.stopPropagation();
  $("saveMenuContent").classList.toggle("open");
});
document.addEventListener("click", (e) => {
  if (!e.target.closest(".save-menu")) $("saveMenuContent").classList.remove("open");
});
$("saveMenuContent").addEventListener("click", (e) => {
  const btn = e.target.closest("button");
  if (!btn) return;
  $("saveMenuContent").classList.remove("open");
  const act = btn.dataset.act;
  if (act === "save-project") saveProject();
  else if (act === "load-project") {
    if (Object.keys(state.iltAreas || {}).length) {
      askConfirm(t("load_replace"), () => $("projectFileInput").click());
    } else {
      $("projectFileInput").click();
    }
  }
  else if (act === "export-excel") exportExcel();
  else if (act === "new-project") clearAll();
});
function applyZoom() {
  const w = $("zoomWrap");
  w.style.transform = `scale(${state.zoom})`;
  w.style.width = (100/state.zoom) + "%";
  $("zoomVal").textContent = Math.round(state.zoom * 100) + "%";
}
function zoomStep(delta) {
  state.zoom = Math.max(0.15, Math.min(2.0, +(state.zoom + delta).toFixed(2)));
  applyZoom(); persist();
}
$("btnZoomIn").onclick = () => zoomStep(0.1);
$("btnZoomOut").onclick = () => zoomStep(-0.1);
$("btnZoomReset").onclick = () => { state.zoom = 1; applyZoom(); persist(); };
$("iltSelect").addEventListener("change", (e) => {
  state.currentILT = e.target.value;
  clearSelection();
  render();
});
$("btnBigPicture").onclick = () => { state.viewMode = "bigpicture"; render(); };
$("btnTeamView").onclick = () => { state.viewMode = "team"; render(); };
$("btnUndo").onclick = undo;
$("btnRedo").onclick = redo;
$("btnAddTeam").onclick = () => {
  if (!currentArea()) {
    askPrompt("new-area", t("new_area_default"), (name) => {
      name = (name || "").trim();
      if (!name) return;
      if (state.iltAreas[name]) { toast(t("area_exists")); return; }
      state.iltAreas[name] = { name, head: null, teams: [] };
      state.currentILT = name;
      state.bigPictureOrder.push(name);
      ensureBigPictureRows();
      state.bigPictureRows[state.bigPictureRows.length - 1].push(name);
      populateILTSelect();
      openTeamModal(null);
      snapshot(); render();
    });
    return;
  }
  openTeamModal(null);
};
document.addEventListener("keydown", (e) => {
  if ((e.ctrlKey||e.metaKey) && e.key === "z") { e.preventDefault(); undo(); }
  if ((e.ctrlKey||e.metaKey) && (e.key === "y" || (e.shiftKey && e.key === "Z"))) { e.preventDefault(); redo(); }
  if ((e.ctrlKey||e.metaKey) && e.key === "s") { e.preventDefault(); saveProject(); }
  if (e.key === "Escape") clearSelection();
  if ((e.ctrlKey||e.metaKey) && e.key === "+") { e.preventDefault(); zoomStep(0.1); }
  if ((e.ctrlKey||e.metaKey) && e.key === "-") { e.preventDefault(); zoomStep(-0.1); }
  if ((e.ctrlKey||e.metaKey) && e.key === "0") { e.preventDefault(); state.zoom=1; applyZoom(); }
});

  if (!state.iltAreas || Array.isArray(state.iltAreas)) {
    state.iltAreas = normalizeAreas(state.iltAreas);
  }
  if (boot.config) {
    Object.assign(CONFIG, boot.config);
  }
  if (boot.state) {
    state = JSON.parse(JSON.stringify(boot.state));
    state.iltAreas = normalizeAreas(state.iltAreas);
  }
  applyTopologyColors();
  updateSaveStatus();
  populateILTSelect();
  render();
  history = [];
  historyIndex = -1;
  snapshot();

  if (window.Livewire) {
    Livewire.on('org-designer-reloaded', (event) => {
      const payload = event.payload || event[0]?.payload || event;
      applyBoot(payload);
      history = []; historyIndex = -1; snapshot();
    });
    Livewire.on('org-designer-ask-import-mode', (event) => {
      const count = event.areaCount ?? event[0]?.areaCount ?? 0;
      if ($('existingAreaCount')) $('existingAreaCount').textContent = String(count);
      pendingImportMode = 'add';
      document.querySelectorAll('.upload-choice').forEach((el) => el.classList.remove('selected'));
      document.querySelector('.upload-choice[data-mode="add"]')?.classList.add('selected');
      $('uploadModal').classList.add('open');
    });
  }
}
