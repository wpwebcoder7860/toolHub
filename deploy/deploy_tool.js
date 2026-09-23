'use strict';

// Server base path is always this — fixed, read-only in the UI.
const FIXED_BASE_PATH = '/web/AxMwWeb/htdocs/';

// ─── Date helpers ──────────────────────────────────────────────────────────
const MONTHS = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];

function getDateSuffix() {
  const d = new Date();
  return String(d.getDate()).padStart(2,'0') + MONTHS[d.getMonth()] + d.getFullYear();
}

function getPatchFolderDefault() {
  const d = new Date();
  return `${String(d.getDate()).padStart(2,'0')}_${MONTHS[d.getMonth()]}_${d.getFullYear()}_PATCH`;
}

function getNowString() {
  return new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' });
}

// ─── Toast ───────────────────────────────────────────────────────────────────
function createToast(id) {
  const el = document.getElementById(id);

  function show(msg, duration = 2500) {
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), duration);
  }

  return { show };
}

// ─── PathParser ──────────────────────────────────────────────────────────────

// Detects the first line carrying more than one absolute path — e.g. a
// shell "cp <source> <dest>" command someone pastes into a .txt file. This
// tool's parser expects exactly one path per line ("put /path/..." or just
// "/path/..."), so a two-path line like that can't be parsed correctly.
// Returns { lineNum, line } for the first offending line, or null if clean.
function findUnsupportedLine(raw) {
  const lines = raw.split('\n');
  for (let i = 0; i < lines.length; i++) {
    let line = lines[i].trim();
    if (!line || line.startsWith('#') || line.startsWith('//')) continue;
    if (line.startsWith('put ')) line = line.slice(4).trim();
    const pathTokenCount = line.split(/\s+/).filter(t => t.startsWith('/')).length;
    if (pathTokenCount >= 2) return { lineNum: i + 1, line: lines[i].trim() };
  }
  return null;
}

// Extract full absolute paths from raw input (strips put prefix)
function extractAbsPaths(lines) {
  return lines
    .map(l => l.trim())
    .filter(l => l && !l.startsWith('#'))
    .map(l => l.startsWith('put ') ? l.slice(4).trim() : l)
    .filter(l => l.startsWith('/'));
}

// Well-known web/document-root folder names. When a pasted absolute path
// contains one of these, everything up to and including it is treated as
// the server's "fixed" base path — this is what lets a SINGLE pasted path
// (with nothing else to compare against) still resolve to a sensible base
// path instead of swallowing the whole directory tree as "base".
const WEB_ROOT_MARKERS = new Set(['htdocs', 'public_html', 'www', 'wwwroot', 'httpdocs', 'webroot']);

// Index of the last segment (excluding the filename) that matches a known
// web-root marker, or -1 if none found.
function findDocRootCut(segments) {
  let cutIdx = -1;
  for (let i = 0; i < segments.length - 1; i++) {
    if (WEB_ROOT_MARKERS.has(segments[i].toLowerCase())) cutIdx = i;
  }
  return cutIdx;
}

// Detect common base path from lines — works for single AND multiple paths.
// Priority:
//   1. An already-set base path that legitimately prefixes every absolute
//      path pasted — keeps the "fixed" base sticky across pastes.
//   2. A recognized web-root marker folder (htdocs, www, public_html, ...)
//      shared by every path — works even for a single pasted path.
//   3. The longest directory prefix shared by ALL pasted absolute paths
//      (only meaningful with 2+ distinct paths).
function detectBasePath(lines, existingBase = '') {
  const absPaths = extractAbsPaths(lines);
  if (!absPaths.length) return null;

  // If existing base path matches start of paths — use it
  if (existingBase && absPaths.every(p => p.startsWith(existingBase + '/') || p.startsWith(existingBase))) {
    return existingBase;
  }

  const splitPath = p => p.split('/').filter(Boolean);
  const segsList  = absPaths.map(splitPath);

  // Web-root marker — gives a consistent result for one path or many
  const markerCuts = segsList.map(findDocRootCut);
  if (markerCuts.every(idx => idx !== -1)) {
    const minCut    = Math.min(...markerCuts);
    const candidate = segsList[0].slice(0, minCut + 1);
    const allMatch  = segsList.every(segs => candidate.every((seg, i) => segs[i] === seg));
    if (allMatch) return '/' + candidate.join('/') + '/';
  }

  // Fallback — longest common directory prefix (needs 2+ distinct paths)
  if (absPaths.length > 1) {
    const first = segsList[0];
    let common = [];

    for (let i = 0; i < first.length; i++) {
      // Stop if any path differs at this segment
      if (!segsList.every(segs => segs[i] === first[i])) break;
      // Stop before the last segment (filename) of ANY path
      const isLastInSome = segsList.some(segs => segs.length === i + 1);
      if (isLastInSome) break;
      common.push(first[i]);
    }

    return common.length ? '/' + common.join('/') + '/' : null;
  }

  return null;
}

// Smart parse: strip base path, deduplicate, skip bad lines silently
function parsePaths(raw, basePath) {
  const lines = raw.split('\n').map(l => l.trim()).filter(l => l && !l.startsWith('#') && !l.startsWith('//'));
  const seen  = new Set();
  const files = [];

  for (const line of lines) {
    let rel = line;
    if (rel.startsWith('put ')) rel = rel.slice(4).trim();

    // Strip base path if present
    if (basePath && rel.startsWith(basePath + '/')) rel = rel.slice(basePath.length + 1);
    else if (basePath && rel.startsWith(basePath))  rel = rel.slice(basePath.length).replace(/^\//, '');
    else if (rel.startsWith('/'))                   rel = rel.slice(1);

    // Auto-fix double slashes
    rel = rel.replace(/\/\/+/g, '/');

    // Skip bad paths silently — checked BEFORE the extension cleanup below,
    // since that regex can otherwise eat a literal ".." (e.g. "a/../b.js"
    // would collapse into "a/.b.js", silently hiding a path-traversal input)
    if (!rel || rel.includes('..') || rel.endsWith('/')) continue;

    // Global rule: . ke baad kuch bhi ho (non-alphanumeric) sirf alphanumeric rakho
    // e.g. .//js → .js | .php, → .php | . css → .css
    rel = rel.replace(/\.([^a-zA-Z0-9]*?)([a-zA-Z0-9]+)/g, '.$2');

    // Deduplicate silently
    if (seen.has(rel)) continue;
    seen.add(rel);
    files.push(rel);
  }
  return files;
}

function groupByDir(files) {
  return files.reduce((groups, f) => {
    const parts = f.split('/');
    const dir   = parts.length > 1 ? parts.slice(0, -1).join('/') : '(root)';
    (groups[dir] ??= []).push(f);
    return groups;
  }, {});
}

// ─── CommandBuilder ──────────────────────────────────────────────────────────
function destPath(basePath, rel) { return `${basePath}/${rel}`; }

function backupCmd(basePath, dateSuffix, rel) {
  const d = destPath(basePath, rel);
  return `cp ${d} ${d}_${dateSuffix}`;
}

function deployCmd(basePath, patchFolder, rel) {
  return `cp ${basePath}/${patchFolder}/${rel.split('/').pop()} ${destPath(basePath, rel)}`;
}

function revertCmd(basePath, dateSuffix, rel) {
  const d = destPath(basePath, rel);
  return `cp ${d}_${dateSuffix} ${d}`;
}

function buildCmdText(mode, rel, basePath, dateSuffix, patchFolder) {
  return mode === 'backup' ? backupCmd(basePath, dateSuffix, rel)
       : mode === 'deploy' ? deployCmd(basePath, patchFolder, rel)
       : revertCmd(basePath, dateSuffix, rel);
}

function buildCommands(mode, files, basePath, dateSuffix, patchFolder) {
  const groups = groupByDir(files);
  const cmds   = [];
  for (const [dir, dirFiles] of Object.entries(groups)) {
    cmds.push({ type: 'section', label: dir });
    for (const rel of dirFiles) {
      cmds.push({ type: 'cmd', text: buildCmdText(mode, rel, basePath, dateSuffix, patchFolder) });
    }
  }
  return cmds;
}

function buildFlatCommands(mode, files, basePath, dateSuffix, patchFolder) {
  return files.map(rel => buildCmdText(mode, rel, basePath, dateSuffix, patchFolder));
}

function buildShScript(files, basePath, dateSuffix, patchFolder) {
  const backup = buildFlatCommands('backup', files, basePath, dateSuffix, patchFolder);
  const deploy = buildFlatCommands('deploy', files, basePath, dateSuffix, patchFolder);
  const revert = buildFlatCommands('revert', files, basePath, dateSuffix, patchFolder);
  const now    = getNowString();

  const fn = (cmds, idx) => cmds.map(c => {
    const token = c.split(' ')[idx];
    return `  ${c} && echo "✅ $(basename ${token})" || echo "❌ FAILED: $(basename ${token})"`;
  });

  return [
    `#!/bin/bash`,
    `# ============================================================`,
    `# Generated by Deploy Tool`,
    `# Date     : ${now}`,
    `# Suffix   : ${dateSuffix}`,
    `# Patch    : ${patchFolder}`,
    `# Files    : ${files.length}`,
    `# ============================================================`,
    ``,
    `BASE="${basePath}"`,
    `DATE_SUFFIX="${dateSuffix}"`,
    `PATCH_FOLDER="${patchFolder}"`,
    ``,
    `# Usage: bash deploy_${dateSuffix}.sh backup | deploy | revert`,
    `ACTION=\${1:-""}`,
    ``,
    `backup() {`,
    `  echo "=== BACKUP START ==="`,
    ...fn(backup, 2),
    `  echo "=== BACKUP DONE ==="`,
    `}`,``,
    `deploy() {`,
    `  echo "=== DEPLOY START ==="`,
    ...fn(deploy, 1),
    `  echo "=== DEPLOY DONE ==="`,
    `}`,``,
    `revert() {`,
    `  echo "=== REVERT START ==="`,
    ...fn(revert, 2),
    `  echo "=== REVERT DONE ==="`,
    `}`,``,
    `case $ACTION in`,
    `  backup) backup ;;`,
    `  deploy) deploy ;;`,
    `  revert) revert ;;`,
    `  *)`,
    `    echo "Usage: $0 backup | deploy | revert"`,
    `    echo "  backup  — Take backup of production files (suffix: _${dateSuffix})"`,
    `    echo "  deploy  — Copy files from patch folder to production"`,
    `    echo "  revert  — Restore production files from backup"`,
    `    ;;`,
    `esac`,
  ].join('\n');
}

// Plain-text alternative to buildShScript() — same backup/deploy/revert
// commands, just listed under section headers instead of wrapped in a
// runnable bash script.
function buildAllCommandsText(files, basePath, dateSuffix, patchFolder) {
  const backup = buildFlatCommands('backup', files, basePath, dateSuffix, patchFolder);
  const deploy = buildFlatCommands('deploy', files, basePath, dateSuffix, patchFolder);
  const revert = buildFlatCommands('revert', files, basePath, dateSuffix, patchFolder);
  const now    = getNowString();

  return [
    `# Generated by Deploy Tool`,
    `# Date     : ${now}`,
    `# Suffix   : ${dateSuffix}`,
    `# Patch    : ${patchFolder}`,
    `# Files    : ${files.length}`,
    ``,
    `# === BACKUP COMMANDS (${backup.length}) ===`,
    ...backup,
    ``,
    `# === DEPLOY COMMANDS (${deploy.length}) ===`,
    ...deploy,
    ``,
    `# === REVERT COMMANDS (${revert.length}) ===`,
    ...revert,
  ].join('\n');
}

// ─── UIRenderer ──────────────────────────────────────────────────────────────
function copyToClipboard(text, btn, duration = 1500) {
  navigator.clipboard.writeText(text).then(() => {
    if (btn) { btn.textContent = '✓'; setTimeout(() => btn.textContent = '⎘', duration); }
  });
}

function downloadFile(content, filename, type = 'text/plain') {
  const blob = new Blob([content], { type });
  const url  = URL.createObjectURL(blob);
  const a    = Object.assign(document.createElement('a'), { href: url, download: filename });
  a.click();
  URL.revokeObjectURL(url);
}

// Shows the "sh vs txt" download-format modal and resolves with 'sh',
// 'txt', or null (cancelled / dismissed).
function askDownloadFormat() {
  const overlay    = document.getElementById('formatModalOverlay');
  const btnSh      = document.getElementById('formatBtnSh');
  const btnTxt     = document.getElementById('formatBtnTxt');
  const btnCancel  = document.getElementById('formatBtnCancel');

  return new Promise(resolve => {
    function choose(result) {
      overlay.style.display = 'none';
      btnSh.onclick = btnTxt.onclick = btnCancel.onclick = overlay.onclick = null;
      resolve(result);
    }

    btnSh.onclick     = () => choose('sh');
    btnTxt.onclick    = () => choose('txt');
    btnCancel.onclick = () => choose(null);
    overlay.onclick   = e => { if (e.target === overlay) choose(null); };

    overlay.style.display = 'flex';
  });
}

// Reflects the currently-uploaded .txt filename (or its absence) in an
// upload row's filename label + enables/disables that row's Reset button.
function setUploadDisplay(fileNameId, resetBtnId, fileName) {
  const nameEl = document.getElementById(fileNameId);
  const btnEl  = document.getElementById(resetBtnId);
  if (fileName) {
    nameEl.textContent = fileName;
    nameEl.classList.remove('empty');
    btnEl.disabled = false;
  } else {
    nameEl.textContent = 'No file chosen';
    nameEl.classList.add('empty');
    btnEl.disabled = true;
  }
}

// Shows/hides the upload-format error message (reuses the existing
// .field-err styling) for a given tab's upload row.
function showUploadFormatError(errId, fileName, badLine) {
  const el = document.getElementById(errId);
  el.textContent = `⚠ Unsupported format in "${fileName}" (Line ${badLine.lineNum}): "${badLine.line}" — this tool allows only ONE path per line ("put /path/..." or just "/path/..."). Two-path lines like "cp <source> <dest>" are not supported. Example (correct): put /web/AxMwWeb/htdocs/smartforms/public/x.js`;
  el.classList.add('show');
}

function clearUploadFormatError(errId) {
  const el = document.getElementById(errId);
  el.textContent = '';
  el.classList.remove('show');
}

function renderCmdBlock(cmdBlock, commands) {
  cmdBlock.innerHTML = '';
  for (const item of commands) {
    if (item.type === 'section') {
      const lbl = Object.assign(document.createElement('div'), {
        className: 'cmd-section-label',
        textContent: '📁 ' + item.label
      });
      cmdBlock.appendChild(lbl);
    } else {
      const parts   = item.text.split(' ');
      const colored = parts.map((p, i) =>
        `<span class="${i === 0 ? 'cmd-keyword' : 'cmd-path'}">${p}</span>`
      ).join(' ');
      const div = document.createElement('div');
      div.className = 'cmd-line';
      div.innerHTML = `<span class="cmd-text">${colored}</span>
        <button class="cmd-copy" title="Copy" onclick="app.copyCmd(this,\`${item.text.replace(/`/g,'\\`')}\`)">⎘</button>`;
      cmdBlock.appendChild(div);
    }
  }
}

// ─── DeployApp (main controller) ─────────────────────────────────────────────
function createDeployApp() {
  const toast = createToast('toast');
  let parsedFiles = [];
  let activeTab   = 'put';

  // ── Config helpers ───────────────────────────────────────────────────────
  function getBasePath()    { return document.getElementById('basePath').value.trim().replace(/\/$/, ''); }
  function getDateSuffixVal()  { return document.getElementById('dateSuffix').value.trim(); }
  function getPatchFolderVal() { return document.getElementById('patchFolder').value.trim(); }

  // ── Init ────────────────────────────────────────────────────────────────
  function initDefaults() {
    const suffix = getDateSuffix();
    const patch  = getPatchFolderDefault();
    document.getElementById('basePath').value          = FIXED_BASE_PATH;
    document.getElementById('dateSuffix').value       = suffix;
    document.getElementById('dateSuffix').placeholder = `e.g. ${suffix}`;
    document.getElementById('patchFolder').value       = patch;
    document.getElementById('patchFolder').placeholder = `e.g. ${patch}`;
  }

  function bindEvents() {
    document.getElementById('filesInput').addEventListener('keydown', e => {
      if (e.key === 'Tab') e.preventDefault();
    });
  }

  // ── Error handling ───────────────────────────────────────────────────────
  function showErr(id) {
    if (id === 'filesInput') {
      if (activeTab === 'put') {
        document.getElementById('putInput').classList.add('has-error');
        document.getElementById('err-filesInput').classList.add('show');
        document.getElementById('err-filesInput-manual').classList.remove('show');
      } else {
        document.getElementById('filesInput').classList.add('has-error');
        document.getElementById('err-filesInput-manual').classList.add('show');
        document.getElementById('err-filesInput').classList.remove('show');
      }
      return;
    }
    document.getElementById(id).classList.add('has-error');
    document.getElementById('err-' + id).classList.add('show');
  }

  function clearErr(id) {
    if (id === 'filesInput') {
      ['putInput','filesInput'].forEach(eid => document.getElementById(eid)?.classList.remove('has-error'));
      ['err-filesInput','err-filesInput-manual'].forEach(eid => document.getElementById(eid).classList.remove('show'));
      return;
    }
    document.getElementById(id)?.classList.remove('has-error');
    document.getElementById('err-' + id)?.classList.remove('show');
  }

  function clearAllErrs() {
    ['basePath','dateSuffix','patchFolder','filesInput'].forEach(id => clearErr(id));
  }

  // ── Tab switching ────────────────────────────────────────────────────────
  function switchTab(tab) {
    activeTab = tab;
    document.getElementById('pane-put').style.display    = tab === 'put'    ? '' : 'none';
    document.getElementById('pane-manual').style.display = tab === 'manual' ? '' : 'none';
    document.getElementById('tab-put').classList.toggle('active',    tab === 'put');
    document.getElementById('tab-manual').classList.toggle('active', tab === 'manual');
    document.getElementById('actionSection').style.display = tab === 'manual' ? 'flex' : 'none';

    // Reset state
    document.getElementById('putInput').value   = '';
    document.getElementById('filesInput').value = '';
    document.getElementById('putFileInput').value    = '';
    document.getElementById('manualFileInput').value = '';
    setUploadDisplay('putFileName', 'putResetBtn', null);
    setUploadDisplay('manualFileName', 'manualResetBtn', null);
    clearUploadFormatError('err-putUpload');
    clearUploadFormatError('err-manualUpload');
    parsedFiles = [];
    ['parsedPreview','outputCard'].forEach(id => {
      document.getElementById(id).style.display = 'none';
    });
    clearErr('filesInput');
  }

  // ── Shared smart processor — used by BOTH tabs on input ─────────────────
  function smartProcess(textareaId) {
    const ta  = document.getElementById(textareaId);
    const raw = ta.value;
    if (!raw.trim()) return;

    const lines        = raw.split('\n');
    const existingBase = getBasePath();

    // Detect base path from pasted content
    const detected = detectBasePath(lines, existingBase);

    // Auto-fill base path input if empty and detected
    let basePath = existingBase;
    if (detected && !existingBase) {
      document.getElementById('basePath').value = detected;
      basePath = detected;
      toast.show(`✅ Base path detected: ${detected}`);
    } else if (detected && existingBase) {
      basePath = existingBase; // keep existing
    }

    // Process each line: strip base path, put prefix, fix, deduplicate
    const seen       = new Set();
    const cleanLines = [];

    lines.forEach(line => {
      if (!line.trim()) return; // ignore empty lines entirely

      let rel = line.trim();
      if (rel.startsWith('put ')) rel = rel.slice(4).trim();

      // Strip base path
      if (basePath && rel.startsWith(basePath + '/')) rel = rel.slice(basePath.length + 1);
      else if (basePath && rel.startsWith(basePath))  rel = rel.slice(basePath.length).replace(/^\//, '');
      else if (rel.startsWith('/'))                   rel = rel.slice(1);

      // Fix double slashes
      rel = rel.replace(/\/\/+/g, '/');

      // Skip bad paths — checked BEFORE the extension cleanup below, since
      // that regex can otherwise eat a literal ".." and hide it
      if (!rel || rel.includes('..') || rel.endsWith('/')) return;

      // Fix extension issues
      rel = rel.replace(/\.([^a-zA-Z0-9]*?)([a-zA-Z0-9]+)/g, '.$2');

      // Deduplicate silently
      if (seen.has(rel)) return;
      seen.add(rel);
      cleanLines.push(rel);
    });

    // Rewrite textarea with clean paths
    const cleaned = cleanLines.join('\n');
    if (cleaned !== raw) {
      const cursor = ta.selectionStart;
      ta.value = cleaned;
      const pos = Math.min(cursor, cleaned.length);
      ta.setSelectionRange(pos, pos);
    }
  }

  // ── PUT tab ──────────────────────────────────────────────────────────────
  function onPutInput() {
    smartProcess('putInput');
  }

  function parsePutCommands() {
    const raw = document.getElementById('putInput').value.trim();
    if (!raw) { showErr('filesInput'); return; }

    let basePath = getBasePath();

    // Auto-detect base path only when multiple lines share a common prefix
    // AND base path input is empty
    if (!basePath) {
      const detected = detectBasePath(raw.split('\n'));
      if (detected) {
        document.getElementById('basePath').value = detected;
        basePath = detected;
        toast.show(`✅ Base path auto-detected: ${detected}`);
      }
    }

    const files = parsePaths(raw, basePath);
    if (!files.length) { showErr('filesInput'); return; }

    parsedFiles = files;
    clearErr('filesInput');
    renderPutPreview();
    toast.show(`✅ ${files.length} files parsed!`);
  }

  function renderPutPreview() {
    const preview  = document.getElementById('parsedPreview');
    const list     = document.getElementById('parsedList');
    const countEl  = document.getElementById('parsedCount');
    const basePath = getBasePath();
    const putCmds  = parsedFiles.map(rel => `put ${basePath}/${rel}`);

    preview.style.display = 'block';
    countEl.innerHTML = `
      <span>✅ ${parsedFiles.length} commands ready</span>
      <span style="display:flex;gap:6px;margin-left:auto">
        <button class="clear-btn" onclick="app.copyAllPut()" style="border-color:var(--accent);color:var(--accent)">📋 Copy All</button>
        <button class="clear-btn" onclick="app.downloadPut()" style="border-color:var(--green);color:var(--green)">⬇ Download</button>
        <button class="clear-btn" onclick="app.clearParsed()">✕ Clear</button>
      </span>`;

    list.innerHTML = '';
    parsedFiles.forEach((rel, i) => {
      const cmd = putCmds[i];
      const div = document.createElement('div');
      div.className = 'parsed-item';
      div.innerHTML = `
        <span class="fi" style="color:var(--accent)">$</span>
        <span class="fname" style="font-weight:400;color:#a5b4fc;font-family:var(--mono);font-size:11px;flex:1;word-break:break-all">${cmd}</span>
        <button class="del-file" style="opacity:1;color:var(--text-muted)" onclick="app.copyCmd(this,'${cmd.replace(/'/g,"\\'")}')">⎘</button>
        <button class="del-file" onclick="app.removeFile(${i})">✕</button>`;
      list.appendChild(div);
    });
  }

  function removeFile(idx) {
    parsedFiles.splice(idx, 1);
    if (!parsedFiles.length) { clearParsed(); return; }
    renderPutPreview();
  }

  function clearParsed() {
    parsedFiles = [];
    document.getElementById('parsedPreview').style.display = 'none';
    document.getElementById('putInput').value = '';
    document.getElementById('outputCard').style.display = 'none';
  }

  function copyAllPut() {
    const cmds = parsedFiles.map(rel => `put ${getBasePath()}/${rel}`).join('\n');
    navigator.clipboard.writeText(cmds).then(() => toast.show(`✅ ${parsedFiles.length} commands copied!`));
  }

  function downloadPut() {
    const cmds = parsedFiles.map(rel => `put ${getBasePath()}/${rel}`).join('\n');
    downloadFile(cmds, 'put_commands.txt');
    toast.show(`✅ put_commands.txt downloaded!`);
  }

  // ── PUT tab: .txt upload — reuses onPutInput() (existing smartProcess normalization) ──
  function handlePutFileUpload(inputEl) {
    const file = inputEl.files && inputEl.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onerror = () => toast.show(`❌ Could not read ${file.name}`);
    reader.onload = () => {
      const badLine = findUnsupportedLine(reader.result);
      if (badLine) {
        showUploadFormatError('err-putUpload', file.name, badLine);
        toast.show(`❌ Unsupported format in ${file.name}`);
        inputEl.value = ''; // let them re-select after fixing the file
        return;
      }
      clearUploadFormatError('err-putUpload');
      try {
        document.getElementById('putInput').value = reader.result;
        onPutInput(); // existing normalization: put-prefix strip, base-path detect/strip, dedupe, order preserved
        toast.show(`✅ ${file.name} loaded`);
      } catch (err) {
        console.error('PUT .txt upload failed:', err);
        toast.show(`❌ Failed to process ${file.name}`);
      }
      setUploadDisplay('putFileName', 'putResetBtn', file.name);
    };
    reader.readAsText(file);
  }

  function resetPutUpload() {
    document.getElementById('putFileInput').value = '';
    setUploadDisplay('putFileName', 'putResetBtn', null);
    clearUploadFormatError('err-putUpload');
    clearParsed(); // existing — clears putInput textarea, parsedFiles, preview, output card
  }

  // ── Manual tab ───────────────────────────────────────────────────────────
  function onManualInput() {
    smartProcess('filesInput');
    clearErr('filesInput');
  }

  // "All Commands" button — builds the Backup+Deploy+Revert list on demand
  // (no auto-preview; user must click this explicitly).
  function generateAllCommands() {
    clearAllErrs();
    const basePath    = getBasePath();
    const dateSuffix  = getDateSuffixVal();
    const patchFolder = getPatchFolderVal();
    const files       = getFiles();
    let   hasErr      = false;

    if (!basePath)    { showErr('basePath');    hasErr = true; }
    if (!dateSuffix)  { showErr('dateSuffix');  hasErr = true; }
    if (!patchFolder) { showErr('patchFolder'); hasErr = true; }
    if (!files.length){ showErr('filesInput');  hasErr = true; }
    if (hasErr) return;

    const backupCmds = buildFlatCommands('backup', files, basePath, dateSuffix, patchFolder);
    const deployCmds = buildFlatCommands('deploy', files, basePath, dateSuffix, patchFolder);
    const revertCmds = buildFlatCommands('revert', files, basePath, dateSuffix, patchFolder);
    renderAllPreview(backupCmds, deployCmds, revertCmds, files.length, dateSuffix);
  }

  // ── Manual tab: .txt upload — reuses onManualInput() (existing smartProcess normalization) ──
  function handleManualFileUpload(inputEl) {
    const file = inputEl.files && inputEl.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onerror = () => toast.show(`❌ Could not read ${file.name}`);
    reader.onload = () => {
      const badLine = findUnsupportedLine(reader.result);
      if (badLine) {
        showUploadFormatError('err-manualUpload', file.name, badLine);
        toast.show(`❌ Unsupported format in ${file.name}`);
        inputEl.value = ''; // let them re-select after fixing the file
        return;
      }
      clearUploadFormatError('err-manualUpload');
      try {
        document.getElementById('filesInput').value = reader.result;
        onManualInput(); // existing normalization: base-path detect/strip, dedupe, order preserved
        toast.show(`✅ ${file.name} loaded`);
      } catch (err) {
        console.error('Manual .txt upload failed:', err);
        toast.show(`❌ Failed to process ${file.name}`);
      }
      setUploadDisplay('manualFileName', 'manualResetBtn', file.name);
    };
    reader.readAsText(file);
  }

  function resetManualUpload() {
    document.getElementById('manualFileInput').value = '';
    document.getElementById('filesInput').value = '';
    document.getElementById('outputCard').style.display = 'none';
    setUploadDisplay('manualFileName', 'manualResetBtn', null);
    clearUploadFormatError('err-manualUpload');
    clearErr('filesInput');
  }

  // ── File parsing ─────────────────────────────────────────────────────────
  function getFiles() {
    if (activeTab === 'put') return [...parsedFiles];
    const raw = document.getElementById('filesInput').value.trim();
    return raw ? parsePaths(raw, getBasePath()) : [];
  }

  // ── Generate commands ────────────────────────────────────────────────────
  function generate(mode) {
    clearAllErrs();
    const basePath    = getBasePath();
    const dateSuffix  = getDateSuffixVal();
    const patchFolder = getPatchFolderVal();
    const files        = getFiles();
    let   hasErr        = false;

    if (!basePath)                                                  { showErr('basePath');    hasErr = true; }
    if (['backup','revert'].includes(mode) && !dateSuffix)          { showErr('dateSuffix');  hasErr = true; }
    if (mode === 'deploy'  && !patchFolder)                         { showErr('patchFolder'); hasErr = true; }
    if (!files.length)                                              { showErr('filesInput');  hasErr = true; }
    if (hasErr) return;

    const commands = buildCommands(mode, files, basePath, dateSuffix, patchFolder);
    renderOutput(mode, commands, files.length);
  }

  async function generateAllAndDownload() {
    clearAllErrs();
    const basePath    = getBasePath();
    const dateSuffix  = getDateSuffixVal();
    const patchFolder = getPatchFolderVal();
    const files  = getFiles();
    let   hasErr = false;

    if (!basePath)    { showErr('basePath');    hasErr = true; }
    if (!dateSuffix)  { showErr('dateSuffix');  hasErr = true; }
    if (!patchFolder) { showErr('patchFolder'); hasErr = true; }
    if (!files.length){ showErr('filesInput');  hasErr = true; }
    if (hasErr) return;

    const format = await askDownloadFormat();
    if (!format) return; // cancelled

    const backupCmds = buildFlatCommands('backup', files, basePath, dateSuffix, patchFolder);
    const deployCmds = buildFlatCommands('deploy', files, basePath, dateSuffix, patchFolder);
    const revertCmds = buildFlatCommands('revert', files, basePath, dateSuffix, patchFolder);

    if (format === 'sh') {
      const shContent = buildShScript(files, basePath, dateSuffix, patchFolder);
      downloadFile(shContent, `deploy_${dateSuffix}.sh`);
      toast.show(`✓ deploy_${dateSuffix}.sh downloaded! (${files.length} files)`);
    } else {
      const txtContent = buildAllCommandsText(files, basePath, dateSuffix, patchFolder);
      downloadFile(txtContent, `deploy_${dateSuffix}_all_commands.txt`);
      toast.show(`✓ deploy_${dateSuffix}_all_commands.txt downloaded! (${files.length} files)`);
    }

    renderAllPreview(backupCmds, deployCmds, revertCmds, files.length, dateSuffix);
  }

  // ── Render output ────────────────────────────────────────────────────────
  function renderOutput(mode, commands, fileCount) {
    const card     = document.getElementById('outputCard');
    const cmdBlock = document.getElementById('cmdBlock');
    const badge    = document.getElementById('modeBadge');
    const statsBar = document.getElementById('statsBar');
    const badgeMap = { backup:['BACKUP','badge-backup'], deploy:['DEPLOY','badge-deploy'], revert:['REVERT','badge-revert'] };

    card.style.display = 'block';
    badge.textContent  = badgeMap[mode][0];
    badge.className    = `badge ${badgeMap[mode][1]}`;

    renderCmdBlock(cmdBlock, commands);

    const cmdCount = commands.filter(c => c.type === 'cmd').length;
    statsBar.innerHTML = `
      <span>📄 ${fileCount} files</span>
      <span>⚡ ${cmdCount} commands</span>`;

    card.scrollIntoView({ behavior:'smooth', block:'start' });
  }

  function renderAllPreview(backupCmds, deployCmds, revertCmds, fileCount, dateSuffix) {
    const card     = document.getElementById('outputCard');
    const cmdBlock = document.getElementById('cmdBlock');
    const badge    = document.getElementById('modeBadge');
    const statsBar = document.getElementById('statsBar');

    card.style.display = 'block';
    badge.textContent  = 'ALL';
    badge.className    = 'badge badge-deploy';
    cmdBlock.innerHTML = '';

    const sections = [
      { label:'💾 BACKUP COMMANDS', cmds:backupCmds, color:'#f59e0b' },
      { label:'🚀 DEPLOY COMMANDS', cmds:deployCmds, color:'#22c55e' },
      { label:'↩ REVERT COMMANDS',  cmds:revertCmds, color:'#ef4444' },
    ];

    for (const sec of sections) {
      const lbl = Object.assign(document.createElement('div'), { className:'cmd-section-label' });
      lbl.style.color = sec.color;
      const lblText = Object.assign(document.createElement('span'), { textContent: sec.label });
      const copyBtn = Object.assign(document.createElement('button'), { className:'section-copy-btn', textContent:'📋 Copy' });
      copyBtn.onclick = () => copySectionCmds(copyBtn, sec.cmds);
      lbl.append(lblText, copyBtn);
      cmdBlock.appendChild(lbl);
      for (const cmd of sec.cmds) {
        const div = document.createElement('div');
        div.className = 'cmd-line';
        const safe = cmd.replace(/`/g,'\\`');
        div.innerHTML = `<span class="cmd-text"><span class="cmd-keyword">cp</span> <span class="cmd-path">${cmd.slice(3)}</span></span>
          <button class="cmd-copy" onclick="app.copyCmd(this,\`${safe}\`)">⎘</button>`;
        cmdBlock.appendChild(div);
      }
    }

    const total = backupCmds.length + deployCmds.length + revertCmds.length;
    statsBar.innerHTML = `
      <span>📄 ${fileCount} files</span><span>⚡ ${total} total</span>
      <span>💾 ${backupCmds.length}</span><span>🚀 ${deployCmds.length}</span><span>↩ ${revertCmds.length}</span>`;

    card.scrollIntoView({ behavior:'smooth', block:'start' });
  }

  // ── Clipboard helpers ────────────────────────────────────────────────────
  function copyCmd(btn, text) { copyToClipboard(text, btn); }

  function copyAll() {
    const cmds = [...document.querySelectorAll('.cmd-line')]
      .map(el => el.querySelector('.cmd-text')?.textContent.trim())
      .filter(Boolean);
    navigator.clipboard.writeText(cmds.join('\n')).then(() => toast.show(`✓ ${cmds.length} commands copied!`));
  }

  function copySectionCmds(btn, cmds) {
    navigator.clipboard.writeText(cmds.join('\n')).then(() => {
      const original = btn.textContent;
      btn.textContent = '✓ Copied';
      setTimeout(() => btn.textContent = original, 1500);
      toast.show(`✓ ${cmds.length} commands copied!`);
    });
  }

  initDefaults();
  bindEvents();

  return {
    switchTab, onPutInput, parsePutCommands, onManualInput,
    generate, generateAllCommands, generateAllAndDownload,
    copyCmd, copyAll,
    removeFile, clearParsed, copyAllPut, downloadPut,
    clearErr,
    handlePutFileUpload, resetPutUpload,
    handleManualFileUpload, resetManualUpload,
  };
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
const app = createDeployApp();

// Global shims for inline onclick/oninput attributes
function switchTab(tab)              { app.switchTab(tab); }
function parsePutCommands()          { app.parsePutCommands(); }
function onManualInput()             { app.onManualInput(); }
function generate(mode)              { app.generate(mode); }
function generateAllAndDownload()    { app.generateAllAndDownload(); }
function clearErr(id)                { app.clearErr(id); }
