// UI for axisDummyData/index.php — talks to the same file via ?action=...
const ENDPOINT = window.location.pathname;
const JSON_FIELDS = ['accountServiceRes', 'demographicServiceRes'];
const BATCH_SIZE = 50;
const COLS = 9;

const $ = (id) => document.getElementById(id);

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function parseJson(text) {
    try {
        return { ok: true, value: JSON.parse(text) };
    } catch (e) {
        return { ok: false, error: e.message };
    }
}

// Same rule as RESPONSE_OK_SQL in index.php
function isResponseOk(raw) {
    if (!raw || !String(raw).trim()) return false;
    const parsed = parseJson(raw);
    if (!parsed.ok || parsed.value === null || typeof parsed.value !== 'object') return false;
    return Object.keys(parsed.value).length > 0;
}

function prettyText(raw) {
    if (raw === null || raw === undefined || String(raw).trim() === '') return '';
    const parsed = parseJson(raw);
    return parsed.ok ? JSON.stringify(parsed.value, null, 4) : String(raw);
}

function highlightJson(text) {
    const re = /("(?:\\.|[^"\\])*")(\s*:)?|\b(true|false|null)\b|-?\b\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b/g;
    let out = '';
    let last = 0;
    let m;
    while ((m = re.exec(text)) !== null) {
        out += escapeHtml(text.slice(last, m.index));
        if (m[1]) {
            out += m[2]
                ? `<span class="tok-key">${escapeHtml(m[1])}</span>${escapeHtml(m[2])}`
                : `<span class="tok-str">${escapeHtml(m[1])}</span>`;
        } else if (m[3]) {
            out += `<span class="tok-lit">${m[3]}</span>`;
        } else {
            out += `<span class="tok-num">${m[0]}</span>`;
        }
        last = re.lastIndex;
    }
    // Trailing newline keeps the overlay height in step with the textarea
    return out + escapeHtml(text.slice(last)) + '\n';
}

class Toast {
    constructor(el) {
        this.el = el;
        this.timer = null;
    }

    show(message, type = '') {
        this.el.textContent = message;
        this.el.className = `toast show ${type}`;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.el.classList.remove('show'), 3000);
    }
}

class JsonEditor {
    constructor() {
        this.root = $('codeEditor');
        this.input = $('codeInput');
        this.highlight = $('codeHighlight');
        this.gutter = $('codeGutter');
        this.onChange = () => {};

        this.input.addEventListener('input', () => {
            this.render();
            this.onChange(this.input.value);
        });
        this.input.addEventListener('scroll', () => this.syncScroll());
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Tab' && !this.input.readOnly) {
                e.preventDefault();
                this.input.setRangeText('    ', this.input.selectionStart, this.input.selectionEnd, 'end');
                this.input.dispatchEvent(new Event('input'));
            }
        });
    }

    setValue(text, readOnly) {
        this.input.value = text;
        this.input.readOnly = readOnly;
        this.root.classList.toggle('readonly', readOnly);
        this.input.scrollTop = 0;
        this.input.scrollLeft = 0;
        this.render();
    }

    render() {
        const text = this.input.value;
        this.highlight.innerHTML = highlightJson(text);
        const lines = text.split('\n').length;
        this.gutter.textContent = Array.from({ length: lines }, (_, i) => i + 1).join('\n');
        this.syncScroll();
    }

    syncScroll() {
        this.highlight.scrollTop = this.input.scrollTop;
        this.highlight.scrollLeft = this.input.scrollLeft;
        this.gutter.scrollTop = this.input.scrollTop;
    }
}

class DummyDataPage {
    constructor() {
        this.canManage = document.body.dataset.canManage === '1';
        this.csrf = document.querySelector('meta[name="csrf-token"]').content;
        this.state = { sort: 'id', dir: 'DESC', column: '', value: '' };
        this.rows = [];
        this.total = 0;
        this.nextPage = 1;
        this.hasMore = true;
        this.hasStatus = false;
        this.loading = false;
        this.requestId = 0;
        this.toast = new Toast($('toast'));
        this.editor = new JsonEditor();
        this.view = { row: null, tab: 'accountServiceRes', buffers: {} };
        this.deleteId = null;

        this.tbody = $('tbody');
        this.wrap = document.querySelector('.table-wrap');
        this.bindToolbar();
        this.bindTable();
        this.bindModals();
        this.bindViewModal();
        if (this.canManage) this.bindInsert();
        this.decorateSortHeaders();
        this.reload();
    }

    async request(action, { params = {}, body = null } = {}) {
        const query = new URLSearchParams({ action, ...params });
        const options = body
            ? { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': this.csrf } }
            : {};
        const res = await fetch(`${ENDPOINT}?${query}`, options);
        if (res.status === 401) {
            window.location.reload();
            throw new Error('Session expired, please log in again.');
        }
        const data = await res.json().catch(() => ({ success: false, error: `HTTP ${res.status}` }));
        // auth.php errors use {status, message}, this page uses {success, error}
        if (!data.success) throw new Error(data.error || data.message || 'Request failed.');
        return data;
    }

    // ---------- toolbar ----------
    bindToolbar() {
        $('searchBtn').addEventListener('click', () => {
            this.state.column = $('searchColumn').value;
            this.state.value = $('searchValue').value.trim();
            if (this.state.value && !this.state.column) {
                this.toast.show('Please select a column to search in.', 'err');
                return;
            }
            this.reload();
        });
        $('searchValue').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') $('searchBtn').click();
        });
        $('clearBtn').addEventListener('click', () => {
            $('searchColumn').value = '';
            $('searchValue').value = '';
            Object.assign(this.state, { column: '', value: '' });
            this.reload();
        });
        this.wrap.addEventListener('scroll', () => {
            if (this.wrap.scrollTop + this.wrap.clientHeight >= this.wrap.scrollHeight - 120) this.loadMore();
        });
    }

    decorateSortHeaders() {
        document.querySelectorAll('th[data-sort]').forEach((th) => {
            th.insertAdjacentHTML('beforeend', '<svg class="sort-icon"><use href="#i-sort"/></svg>');
            th.addEventListener('click', () => {
                const key = th.dataset.sort;
                if (key === 'status' && !this.hasStatus) return;
                if (this.state.sort === key) {
                    this.state.dir = this.state.dir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    this.state.sort = key;
                    this.state.dir = 'ASC';
                }
                this.reload();
            });
        });
    }

    paintSortHeaders() {
        document.querySelectorAll('th[data-sort]').forEach((th) => {
            const active = th.dataset.sort === this.state.sort;
            th.classList.toggle('sorted', active);
            th.classList.toggle('asc', active && this.state.dir === 'ASC');
            th.classList.toggle('desc', active && this.state.dir === 'DESC');
        });
    }

    // ---------- data ----------
    reload() {
        this.requestId++;
        this.loading = false;
        this.rows = [];
        this.total = 0;
        this.nextPage = 1;
        this.hasMore = true;
        this.wrap.scrollTop = 0;
        this.tbody.innerHTML = `<tr class="status-row"><td colspan="${COLS}">Loading…</td></tr>`;
        this.paintSortHeaders();
        this.loadMore();
    }

    async loadMore() {
        if (this.loading || !this.hasMore) return;
        this.loading = true;
        const reqId = this.requestId;
        const first = this.nextPage === 1;
        if (!first) this.setFooter('Loading more…');

        const params = { page: this.nextPage, limit: BATCH_SIZE, sort: this.state.sort, dir: this.state.dir };
        if (this.state.column && this.state.value) {
            params.column = this.state.column;
            params.value = this.state.value;
        }
        try {
            const data = await this.request('list', { params });
            // A newer search/sort started while this was in flight
            if (reqId !== this.requestId) return;
            this.hasStatus = data.hasStatus;
            $('statusNotice').hidden = data.hasStatus;
            this.total = data.total;
            this.hasMore = data.page < data.pages;
            this.nextPage = data.page + 1;
            this.appendRows(data.rows, first);
            this.renderSummary();
            this.renderStats(data.stats);
        } catch (err) {
            if (reqId !== this.requestId) return;
            if (first) {
                this.tbody.innerHTML = `<tr class="status-row"><td colspan="${COLS}">Error: ${escapeHtml(err.message)}</td></tr>`;
                $('showingTop').textContent = '';
            } else {
                this.setFooter(`Error: ${err.message} — scroll again to retry`);
            }
        } finally {
            if (reqId === this.requestId) this.loading = false;
        }
        // Short lists may not fill the box, so no scroll event would fire
        if (reqId === this.requestId && this.hasMore && this.wrap.scrollHeight <= this.wrap.clientHeight + 10) this.loadMore();
    }

    rowHtml(row, index) {
        const ok = isResponseOk(row.accountServiceRes);
        const active = Number(row.status ?? 1) === 1;
        const canToggle = this.canManage && this.hasStatus;
        const toggleTitle = !this.hasStatus ? 'Run the status SQL to enable' : !this.canManage ? 'Only admin can change' : (active ? 'Active' : 'Inactive');
        const actions = this.canManage
            ? `<span class="actions">
                    <button type="button" class="icon-btn icon-edit" data-act="view" title="View / Edit"><svg><use href="#i-edit"/></svg></button>
                    <button type="button" class="icon-btn icon-delete" data-act="delete" title="Delete"><svg><use href="#i-trash"/></svg></button>
               </span>`
            : '<span class="muted">View only</span>';
        return `<tr data-id="${escapeHtml(row.id)}">
            <td class="col-num">${index}</td>
            <td title="${escapeHtml(row.accountNumber)}">${escapeHtml(row.accountNumber)}</td>
            <td title="${escapeHtml(row.title)}">${escapeHtml(row.title)}</td>
            <td title="${escapeHtml(row.description)}">${escapeHtml(row.description)}</td>
            <td class="col-center"><button type="button" class="view-btn" data-act="view"><svg><use href="#i-file"/></svg>View Response</button></td>
            <td>${escapeHtml(row.creationdate)}</td>
            <td><span class="badge ${ok ? 'badge-success' : 'badge-failed'}">${ok ? 'Success' : 'Failed'}</span></td>
            <td class="col-center">
                <label class="switch" title="${toggleTitle}">
                    <input type="checkbox" data-act="toggle" ${active ? 'checked' : ''} ${canToggle ? '' : 'disabled'} aria-label="Active">
                    <span class="track"></span>
                </label>
            </td>
            <td class="col-center">${actions}</td>
        </tr>`;
    }

    appendRows(rows, first) {
        this.tbody.querySelector('.footer-row')?.remove();
        if (first) this.tbody.innerHTML = '';
        if (first && !rows.length) {
            this.tbody.innerHTML = `<tr class="status-row"><td colspan="${COLS}">No records found.</td></tr>`;
            return;
        }
        const start = this.rows.length;
        this.rows.push(...rows);
        this.tbody.insertAdjacentHTML('beforeend', rows.map((row, i) => this.rowHtml(row, start + i + 1)).join(''));
        if (!this.hasMore) this.setFooter(`All ${this.total} records loaded`);
    }

    setFooter(text) {
        let row = this.tbody.querySelector('.footer-row');
        if (!row) {
            this.tbody.insertAdjacentHTML('beforeend', `<tr class="status-row footer-row"><td colspan="${COLS}"></td></tr>`);
            row = this.tbody.querySelector('.footer-row');
        }
        row.firstElementChild.textContent = text;
    }

    renumber() {
        this.tbody.querySelectorAll('tr[data-id] td.col-num').forEach((td, i) => { td.textContent = i + 1; });
    }

    renderSummary() {
        $('showingTop').textContent = this.total
            ? `Showing 1 to ${this.rows.length} of ${this.total} records`
            : 'Showing 0 records';
    }

    renderStats(stats) {
        $('statTotal').textContent = stats.total;
        $('statActive').textContent = stats.active ?? '–';
        $('statInactive').textContent = stats.inactive ?? '–';
    }

    rowById(id) {
        return this.rows.find((r) => String(r.id) === String(id));
    }

    // ---------- row actions ----------
    bindTable() {
        this.tbody.addEventListener('click', (e) => {
            const el = e.target.closest('[data-act]');
            if (!el || el.dataset.act === 'toggle') return;
            const row = this.rowById(el.closest('tr')?.dataset.id);
            if (!row) return;
            if (el.dataset.act === 'view') this.openView(row, 'accountServiceRes');
            if (el.dataset.act === 'delete' && this.canManage) this.openDelete(row);
        });
        this.tbody.addEventListener('change', (e) => {
            if (e.target.dataset.act === 'toggle') this.toggleStatus(e.target);
        });
    }

    async toggleStatus(input) {
        const row = this.rowById(input.closest('tr').dataset.id);
        const status = input.checked ? 1 : 0;
        input.disabled = true;
        try {
            const body = new FormData();
            body.append('id', row.id);
            body.append('status', String(status));
            await this.request('toggle', { body });
            row.status = status;
            input.closest('label').title = status ? 'Active' : 'Inactive';
            const active = $('statActive');
            const inactive = $('statInactive');
            active.textContent = Number(active.textContent) + (status ? 1 : -1);
            inactive.textContent = Number(inactive.textContent) + (status ? -1 : 1);
            this.toast.show(`Record marked ${status ? 'active' : 'inactive'}.`, 'ok');
        } catch (err) {
            input.checked = !input.checked;
            this.toast.show(err.message, 'err');
        } finally {
            input.disabled = false;
        }
    }

    openDelete(row) {
        this.deleteId = row.id;
        $('deleteText').textContent = `${row.accountNumber || '—'} · ${row.title || 'Untitled'} will be permanently removed.`;
        this.openModal('deleteBackdrop');
    }

    async confirmDelete() {
        const btn = $('confirmDeleteBtn');
        btn.disabled = true;
        try {
            const body = new FormData();
            body.append('id', this.deleteId);
            await this.request('delete', { body });
            const deleted = this.rowById(this.deleteId);
            this.rows = this.rows.filter((r) => r !== deleted);
            this.tbody.querySelector(`tr[data-id="${CSS.escape(String(this.deleteId))}"]`)?.remove();
            this.total = Math.max(0, this.total - 1);
            const statTotal = $('statTotal');
            statTotal.textContent = Math.max(0, Number(statTotal.textContent) - 1);
            if (this.hasStatus && deleted) {
                const stat = Number(deleted.status ?? 1) === 1 ? $('statActive') : $('statInactive');
                stat.textContent = Math.max(0, Number(stat.textContent) - 1);
            }
            this.renumber();
            this.renderSummary();
            this.closeModal('deleteBackdrop');
            this.toast.show('Record deleted.', 'ok');
        } catch (err) {
            this.toast.show(err.message, 'err');
        } finally {
            btn.disabled = false;
        }
    }

    // ---------- modals ----------
    bindModals() {
        document.querySelectorAll('[data-close]').forEach((btn) =>
            btn.addEventListener('click', () => this.closeModal(btn.dataset.close)));
        document.querySelectorAll('.modal-backdrop').forEach((bd) =>
            bd.addEventListener('mousedown', (e) => {
                if (e.target === bd) this.closeModal(bd.id);
            }));
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const open = [...document.querySelectorAll('.modal-backdrop.open')].pop();
            if (open) this.closeModal(open.id);
        });
        $('confirmDeleteBtn')?.addEventListener('click', () => this.confirmDelete());
    }

    openModal(id) {
        $(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    closeModal(id) {
        $(id).classList.remove('open');
        if (!document.querySelector('.modal-backdrop.open')) document.body.style.overflow = '';
    }

    // ---------- view / edit ----------
    bindViewModal() {
        document.querySelectorAll('.tab').forEach((tab) =>
            tab.addEventListener('click', () => this.showTab(tab.dataset.tab)));
        this.editor.onChange = (text) => {
            if (this.view.tab !== 'raw') this.view.buffers[this.view.tab] = text;
            $('jsonError').textContent = '';
        };
        $('prettyBtn').addEventListener('click', () => this.reformat(4));
        $('minifyBtn').addEventListener('click', () => this.reformat(0));
        $('submitChangesBtn')?.addEventListener('click', () => this.submitChanges());
    }

    openView(row, field) {
        this.view.row = row;
        this.view.buffers = Object.fromEntries(JSON_FIELDS.map((f) => [f, prettyText(row[f])]));
        $('vAccount').textContent = row.accountNumber ?? '';
        $('vTitle').textContent = row.title ?? '';
        $('vDesc').textContent = row.description ?? '';
        $('jsonError').textContent = '';
        this.showTab(field);
        this.openModal('viewBackdrop');
        // Caret at start, else focus jumps to the last line
        this.editor.input.setSelectionRange(0, 0);
        this.editor.input.focus({ preventScroll: true });
        this.editor.input.scrollTop = 0;
        this.editor.syncScroll();
    }

    rawView() {
        const full = { ...this.view.row };
        JSON_FIELDS.forEach((f) => {
            const parsed = parseJson(this.view.buffers[f]);
            full[f] = parsed.ok ? parsed.value : this.view.buffers[f];
        });
        return JSON.stringify(full, null, 4);
    }

    showTab(tab) {
        this.view.tab = tab;
        document.querySelectorAll('.tab').forEach((t) => t.classList.toggle('active', t.dataset.tab === tab));
        const isRaw = tab === 'raw';
        // Viewers get every tab read-only
        const readOnly = isRaw || !this.canManage;
        this.editor.setValue(isRaw ? this.rawView() : this.view.buffers[tab], readOnly);
        $('jsonError').textContent = isRaw && this.canManage ? 'Raw view is read-only. Edit in the other two tabs.' : '';
    }

    reformat(indent) {
        const text = this.editor.input.value;
        if (!text.trim()) return;
        const parsed = parseJson(text);
        if (!parsed.ok) {
            $('jsonError').textContent = `Invalid JSON: ${parsed.error}`;
            return;
        }
        const formatted = JSON.stringify(parsed.value, null, indent);
        if (this.view.tab !== 'raw') this.view.buffers[this.view.tab] = formatted;
        this.editor.setValue(formatted, this.editor.input.readOnly);
    }

    async submitChanges() {
        const labels = { accountServiceRes: 'Account Service Request', demographicServiceRes: 'Demographic Service Request' };
        const body = new FormData();
        const saved = {};
        body.append('id', this.view.row.id);
        for (const f of JSON_FIELDS) {
            const text = this.view.buffers[f].trim();
            if (text) {
                const parsed = parseJson(text);
                if (!parsed.ok) {
                    this.showTab(f);
                    $('jsonError').textContent = `${labels[f]}: invalid JSON — ${parsed.error}`;
                    return;
                }
                // Stored minified, same as existing rows
                saved[f] = JSON.stringify(parsed.value);
            } else {
                saved[f] = '';
            }
            body.append(f, saved[f]);
        }
        const btn = $('submitChangesBtn');
        btn.disabled = true;
        try {
            await this.request('update', { body });
            // Patch in place so the scroll position stays
            const row = this.view.row;
            JSON_FIELDS.forEach((f) => { row[f] = saved[f] === '' ? null : saved[f]; });
            const tr = this.tbody.querySelector(`tr[data-id="${CSS.escape(String(row.id))}"]`);
            if (tr) tr.outerHTML = this.rowHtml(row, [...this.tbody.querySelectorAll('tr[data-id]')].indexOf(tr) + 1);
            this.closeModal('viewBackdrop');
            this.toast.show('Changes saved.', 'ok');
        } catch (err) {
            $('jsonError').textContent = err.message;
        } finally {
            btn.disabled = false;
        }
    }

    // ---------- insert ----------
    bindInsert() {
        $('openInsertBtn').addEventListener('click', () => {
            $('insertMsg').textContent = '';
            this.openModal('insertBackdrop');
        });
        $('insertForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = $('insertMsg');
            msg.textContent = '';
            msg.className = 'msg wide';
            const submit = e.target.querySelector('[type=submit]');
            submit.disabled = true;
            try {
                const data = await this.request('insert', { body: new FormData(e.target) });
                e.target.reset();
                this.closeModal('insertBackdrop');
                this.toast.show(`Record added (id: ${data.id}).`, 'ok');
                this.reload();
            } catch (err) {
                msg.textContent = err.message;
                msg.className = 'msg wide err';
            } finally {
                submit.disabled = false;
            }
        });
    }
}

new DummyDataPage();
