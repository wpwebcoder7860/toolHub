<?php
declare(strict_types=1);
require __DIR__ . '/../common/auth.php';
$me = requireLogin('manage_users');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Users</title>
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="csrf-token" content="<?= h(csrfToken()) ?>">
    <link href="../common/assets/bootstrap.min.css" rel="stylesheet">
    <link href="../common/assets/hub.css" rel="stylesheet">
    <link rel="shortcut icon" href="/srfAddon/images/favicon.ico">
    <script src="../common/assets/sweetalert2.js"></script>
    <style>
        body {
            background-color: #20c9a6;
            min-height: 100vh;
            padding: 16px;
        }

        .panel {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
        }

        .user-table th {
            background-color: #4CAF50;
            color: #fff;
        }

        .user-table td,
        .user-table th {
            vertical-align: middle;
            font-size: 14px;
        }

        /* ---------------- Access modal ---------------- */
        .access-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            align-items: flex-start;
            justify-content: center;
            padding: 32px 16px;
            overflow-y: auto;
            z-index: 1080;
        }

        .access-backdrop.open {
            display: flex;
        }

        .access-modal {
            background: #fff;
            width: 100%;
            max-width: 460px;
            border-radius: 14px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, .3);
            overflow: hidden;
        }

        .access-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-bottom: 1px solid #eef0f4;
        }

        .access-head-icon {
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #dbeafe;
            color: #3b5bfd;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .access-head-icon svg {
            width: 17px;
            height: 17px;
        }

        .access-head h2 {
            flex: 1;
            margin: 0;
            font-size: 15.5px;
            font-weight: 800;
            color: #16233f;
        }

        .access-close {
            flex: 0 0 auto;
            width: 24px;
            height: 24px;
            border: none;
            background: transparent;
            color: #9aa3b2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .access-close:hover {
            background: #f1f2f6;
            color: #4b5563;
        }

        .access-close svg {
            width: 13px;
            height: 13px;
        }

        .access-body {
            padding: 14px 16px;
            max-height: 65vh;
            overflow-y: auto;
        }

        .access-field-label {
            display: block;
            font-weight: 700;
            color: #16233f;
            margin-bottom: 5px;
            font-size: 12.5px;
        }

        .access-input,
        .access-select-wrap select {
            width: 100%;
            border: 1px solid #dde1e8;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12.5px;
            color: #16233f;
        }

        .access-input {
            margin-bottom: 10px;
        }

        .access-select-wrap {
            position: relative;
            margin-bottom: 14px;
        }

        .access-select-wrap svg.select-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 13px;
            height: 13px;
            color: #6b7280;
            pointer-events: none;
        }

        .access-select-wrap svg.chevron-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            color: #6b7280;
            pointer-events: none;
        }

        .access-select-wrap select {
            appearance: none;
            padding-left: 30px;
            padding-right: 28px;
            font-weight: 700;
            cursor: pointer;
        }

        .access-section-title {
            margin: 0 0 2px;
            font-size: 13.5px;
            font-weight: 800;
            color: #16233f;
        }

        .access-section-sub {
            margin: 0 0 10px;
            font-size: 11px;
            color: #6b7280;
        }

        .access-group-card {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 9px 11px;
            margin-bottom: 8px;
        }

        .access-group-card.tone-green {
            background: #eefaf2;
            border-color: #d5f0e0;
        }

        .access-group-card.tone-amber {
            background: #fdf3e9;
            border-color: #f6e2c9;
        }

        .access-group-icon {
            flex: 0 0 auto;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .access-group-icon svg {
            width: 14px;
            height: 14px;
        }

        .tone-green .access-group-icon {
            background: #17b26a;
        }

        .tone-amber .access-group-icon {
            background: #f79a3e;
        }

        .access-group-info {
            flex: 0 0 auto;
            width: 120px;
            padding-right: 8px;
            border-right: 1px solid rgba(0, 0, 0, .06);
        }

        .access-group-title {
            font-weight: 800;
            color: #16233f;
            font-size: 12.5px;
        }

        .access-group-desc {
            font-size: 10.5px;
            color: #6b7280;
            margin-top: 1px;
        }

        .access-group-checks {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            align-items: center;
        }

        .access-check {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            color: #16233f;
            cursor: pointer;
        }

        .access-check input[type="checkbox"] {
            width: 14px;
            height: 14px;
            accent-color: #5b5ef4;
            cursor: pointer;
        }

        .access-group-empty {
            font-size: 11px;
            color: #9aa3b2;
            font-style: italic;
        }

        .access-foot {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 12px 16px;
            border-top: 1px solid #eef0f4;
        }

        .access-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            border-radius: 8px;
            padding: 7px 14px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
        }

        .access-btn svg {
            width: 13px;
            height: 13px;
        }

        .access-btn-primary {
            background: #5b5ef4;
            color: #fff;
        }

        .access-btn-primary:hover {
            background: #4b4ee0;
        }

        .access-btn-cancel {
            background: #667085;
            color: #fff;
        }

        .access-btn-cancel:hover {
            background: #565f75;
        }

        @media (max-width: 560px) {
            .access-group-card {
                flex-wrap: wrap;
            }

            .access-group-info {
                width: 100%;
                border-right: none;
                padding-right: 0;
                border-bottom: 1px solid rgba(0, 0, 0, .06);
                padding-bottom: 10px;
            }
        }
    </style>
</head>

<body>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <symbol id="i-person" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2" /><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></symbol>
            <symbol id="i-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></symbol>
            <symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" /></symbol>
            <symbol id="i-db" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3" fill="none" stroke="currentColor" stroke-width="2" /><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" fill="none" stroke="currentColor" stroke-width="2" /></symbol>
            <symbol id="i-gear" viewBox="0 0 24 24"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zm8 3.5l2-1.5-2-3.5-2.4.8a7 7 0 0 0-1.9-1.1L15.2 4h-4l-.5 2.7a7 7 0 0 0-1.9 1.1L6.4 7 4.4 10.5l2 1.5a7 7 0 0 0 0 2.2l-2 1.5 2 3.5 2.4-.8c.6.5 1.2.8 1.9 1.1l.5 2.5h4l.5-2.5c.7-.3 1.3-.6 1.9-1.1l2.4.8 2-3.5-2-1.5c.1-.7.1-1.5 0-2.2z" fill="currentColor" /></symbol>
            <symbol id="i-save" viewBox="0 0 24 24"><path d="M5 3h11l3 3v15H5zM8 3v5h7V3M8 21v-7h8v7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></symbol>
        </defs>
    </svg>

    <div class="panel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="d-flex align-items-center gap-2"><a href="../" class="hub-back" title="Back to Tool Hub"><svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>Tool Hub</a><h5 class="m-0 fw-bold">User Management</h5></div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-success btn-sm" id="addUserBtn">ADD USER</button>
                <a href="../common/logout.php" class="btn btn-outline-danger btn-sm">LOGOUT (<?= h($me['username']) ?>)</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped user-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>USERNAME</th>
                        <th>ROLE</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody id="userRows">
                    <tr>
                        <td colspan="4" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Access modal: role + module permissions, shared by Add User and Access -->
    <div class="access-backdrop" id="accessBackdrop">
        <div class="access-modal">
            <div class="access-head">
                <div class="access-head-icon"><svg><use href="#i-person" /></svg></div>
                <h2 id="accessTitle">Access</h2>
                <button type="button" class="access-close" id="accessCloseBtn" aria-label="Close"><svg><use href="#i-close" /></svg></button>
            </div>
            <div class="access-body">
                <div id="accessCreds" style="display:none">
                    <label class="access-field-label" for="accessUsername">Username</label>
                    <input type="text" id="accessUsername" class="access-input" placeholder="Username">
                    <label class="access-field-label" for="accessPassword">Password</label>
                    <input type="password" id="accessPassword" class="access-input" placeholder="Password (min 3 chars)">
                </div>

                <label class="access-field-label" for="accessRole">Role</label>
                <div class="access-select-wrap">
                    <svg class="select-icon"><use href="#i-person" /></svg>
                    <select id="accessRole"></select>
                    <svg class="chevron-icon"><use href="#i-chevron" /></svg>
                </div>

                <h3 class="access-section-title">Module Access</h3>
                <p class="access-section-sub">Select the modules and actions this user can access.</p>

                <div id="accessGroups"></div>
                <div id="accessMsg" class="text-danger small mb-2"></div>
            </div>
            <div class="access-foot">
                <button type="button" class="access-btn access-btn-cancel" id="accessCancelBtn"><svg><use href="#i-close" /></svg>Cancel</button>
                <button type="button" class="access-btn access-btn-primary" id="accessSaveBtn"><svg><use href="#i-save" /></svg><span id="accessSaveLabel">UPDATE</span></button>
            </div>
        </div>
    </div>

    <script>
        class UserManager {
            constructor() {
                this.csrf = document.querySelector('meta[name="csrf-token"]').content;
                this.rows = document.getElementById('userRows');
                this.roles = [];
                this.modules = {};
                this.groups = {};
                this.accessMode = 'edit'; // 'add' | 'edit'
                this.accessId = null;
                document.getElementById('addUserBtn').addEventListener('click', () => this.openAccess('add'));
                this.rows.addEventListener('click', (e) => this.onRowClick(e));
                document.getElementById('accessCloseBtn').addEventListener('click', () => this.closeAccess());
                document.getElementById('accessCancelBtn').addEventListener('click', () => this.closeAccess());
                document.getElementById('accessBackdrop').addEventListener('mousedown', (e) => {
                    if (e.target.id === 'accessBackdrop') this.closeAccess();
                });
                document.getElementById('accessSaveBtn').addEventListener('click', () => this.saveAccess());
                document.getElementById('accessRole').addEventListener('change', (e) => {
                    // Keep any checkboxes already toggled before re-rendering for the new role
                    this._currentPerm = { ...this._currentPerm, ...JSON.parse(this.readPermissions()) };
                    this.renderGroups(e.target.value);
                });
                this.load();
            }

            post(data) {
                const fd = new FormData();
                fd.append('csrf', this.csrf);
                Object.entries(data).forEach(([k, v]) => fd.append(k, v));
                return fetch('userApi.php', { method: 'POST', body: fd }).then(r => r.json());
            }

            esc(s) {
                const d = document.createElement('div');
                d.textContent = String(s);
                return d.innerHTML;
            }

            roleOptions(selected) {
                return this.roles.map(r =>
                    `<option value="${this.esc(r)}" ${r === selected ? 'selected' : ''}>${this.esc(r.toUpperCase())}</option>`
                ).join('');
            }

            load() {
                this.post({ action: 'list' }).then(res => {
                    if (!res.status) {
                        Swal.fire('Error', res.message, 'error');
                        return;
                    }
                    this.roles = res.roles;
                    this.modules = res.modules;
                    this.groups = res.groups || {};
                    this.rows.innerHTML = res.data.map((u, i) => `
                        <tr>
                            <td>${i + 1}</td>
                            <td>${this.esc(u.username)}${u.self ? ' <span class="badge bg-info">you</span>' : ''}</td>
                            <td>${this.esc(u.role.toUpperCase())}</td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-primary" data-act="edit" data-id="${u.id}" data-role="${this.esc(u.role)}" data-perm="${encodeURIComponent(JSON.stringify(u.permissions || {}))}" data-name="${this.esc(u.username)}">ACCESS</button>
                                <button class="btn btn-sm btn-warning" data-act="reset" data-id="${u.id}" data-name="${this.esc(u.username)}">RESET PWD</button>
                                ${u.self ? '' : `<button class="btn btn-sm btn-danger" data-act="delete" data-id="${u.id}" data-name="${this.esc(u.username)}">DELETE</button>`}
                            </td>
                        </tr>`).join('');
                });
            }

            result(res) {
                Swal.fire({ icon: res.status ? 'success' : 'error', text: res.message, timer: res.status ? 1500 : undefined });
                if (res.status) this.load();
            }

            onRowClick(e) {
                const btn = e.target.closest('button[data-act]');
                if (!btn) return;
                const { act, id, role, perm, name } = btn.dataset;
                if (act === 'edit') {
                    let current = {};
                    try { current = JSON.parse(decodeURIComponent(perm || '')) || {}; } catch (e2) { current = {}; }
                    this.openAccess('edit', { id, role, name, permissions: current });
                }
                if (act === 'reset') this.resetPassword(id, name);
                if (act === 'delete') this.deleteUser(id, name);
            }

            // ---------- Access modal (Add User / Access) ----------
            groupIcon(icon) {
                return icon === 'gear' ? 'i-gear' : 'i-db';
            }

            groupTone(group) {
                return group === 'Admin' ? 'tone-amber' : 'tone-green';
            }

            renderGroups(role) {
                const current = this._currentPerm || {};
                const byGroup = {};
                Object.entries(this.modules).forEach(([key, m]) => {
                    (byGroup[m.group] ||= []).push({ key, label: m.label });
                });
                document.getElementById('accessGroups').innerHTML = Object.entries(byGroup).map(([group, items]) => {
                    const meta = this.groups[group] || { icon: 'db', desc: '' };
                    // Manage Users item only ever applies to role=admin (besides super_admin)
                    const visible = items.filter(it => it.key !== 'manage_users' || role === 'admin');
                    const checks = visible.length
                        ? visible.map(it => `
                            <label class="access-check">
                                <input type="checkbox" class="perm-check" value="${this.esc(it.key)}" ${current[it.key] ? 'checked' : ''}>
                                ${this.esc(it.label)}
                            </label>`).join('')
                        : '<span class="access-group-empty">Not applicable for this role</span>';
                    return `
                        <div class="access-group-card ${this.groupTone(group)}">
                            <div class="access-group-icon"><svg><use href="#${this.groupIcon(meta.icon)}" /></svg></div>
                            <div class="access-group-info">
                                <div class="access-group-title">${this.esc(group)}</div>
                                <div class="access-group-desc">${this.esc(meta.desc)}</div>
                            </div>
                            <div class="access-group-checks">${checks}</div>
                        </div>`;
                }).join('');
            }

            openAccess(mode, data = {}) {
                this.accessMode = mode;
                this.accessId = data.id ?? null;
                this._currentPerm = data.permissions || {};
                document.getElementById('accessMsg').textContent = '';
                document.getElementById('accessTitle').textContent = mode === 'add' ? 'Add User' : `Access: ${data.name || ''}`;
                document.getElementById('accessSaveLabel').textContent = mode === 'add' ? 'ADD' : 'UPDATE';
                document.getElementById('accessCreds').style.display = mode === 'add' ? '' : 'none';
                document.getElementById('accessUsername').value = '';
                document.getElementById('accessPassword').value = '';

                const roleSel = document.getElementById('accessRole');
                roleSel.innerHTML = this.roleOptions(data.role || 'user');
                this.renderGroups(roleSel.value);

                document.getElementById('accessBackdrop').classList.add('open');
                document.body.style.overflow = 'hidden';
            }

            closeAccess() {
                document.getElementById('accessBackdrop').classList.remove('open');
                document.body.style.overflow = '';
            }

            readPermissions() {
                const out = {};
                document.querySelectorAll('#accessGroups .perm-check').forEach(cb => out[cb.value] = cb.checked);
                return JSON.stringify(out);
            }

            saveAccess() {
                const role = document.getElementById('accessRole').value;
                const permissions = this.readPermissions();
                const msg = document.getElementById('accessMsg');
                msg.textContent = '';

                if (this.accessMode === 'add') {
                    const username = document.getElementById('accessUsername').value.trim();
                    const password = document.getElementById('accessPassword').value;
                    if (!username || !password) {
                        msg.textContent = 'Username and password are required.';
                        return;
                    }
                    if (password.length < 3) {
                        msg.textContent = 'Password must be at least 3 characters.';
                        return;
                    }
                    this.post({ action: 'add', username, password, role, permissions }).then(res => {
                        if (res.status) this.closeAccess();
                        this.result(res);
                    });
                } else {
                    this.post({ action: 'edit', id: this.accessId, role, permissions }).then(res => {
                        if (res.status) this.closeAccess();
                        this.result(res);
                    });
                }
            }

            resetPassword(id, name) {
                Swal.fire({
                    title: `Reset password: ${this.esc(name)}`,
                    input: 'password',
                    inputPlaceholder: 'New password (min 3 chars)',
                    showCancelButton: true,
                    confirmButtonText: 'RESET',
                    inputValidator: v => (!v || v.length < 3) ? 'At least 3 characters' : undefined
                }).then(r => {
                    if (r.isConfirmed) this.post({ action: 'reset', id, password: r.value }).then(res => this.result(res));
                });
            }

            deleteUser(id, name) {
                Swal.fire({
                    title: `Delete ${this.esc(name)}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'DELETE',
                    confirmButtonColor: '#dc3545'
                }).then(r => {
                    if (r.isConfirmed) this.post({ action: 'delete', id }).then(res => this.result(res));
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => new UserManager());
    </script>
</body>

</html>
