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
    </style>
</head>

<body>
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

    <script>
        class UserManager {
            constructor() {
                this.csrf = document.querySelector('meta[name="csrf-token"]').content;
                this.rows = document.getElementById('userRows');
                this.roles = [];
                document.getElementById('addUserBtn').addEventListener('click', () => this.addUser());
                this.rows.addEventListener('click', (e) => this.onRowClick(e));
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
                    this.rows.innerHTML = res.data.map((u, i) => `
                        <tr>
                            <td>${i + 1}</td>
                            <td>${this.esc(u.username)}${u.self ? ' <span class="badge bg-info">you</span>' : ''}</td>
                            <td>${this.esc(u.role.toUpperCase())}</td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-primary" data-act="edit" data-id="${u.id}" data-role="${this.esc(u.role)}" data-name="${this.esc(u.username)}">ROLE</button>
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
                const { act, id, role, name } = btn.dataset;
                if (act === 'edit') this.editRole(id, role, name);
                if (act === 'reset') this.resetPassword(id, name);
                if (act === 'delete') this.deleteUser(id, name);
            }

            addUser() {
                Swal.fire({
                    title: 'Add User',
                    html: `
                        <input id="nuName" class="form-control mb-2" placeholder="Username">
                        <input id="nuPwd" type="password" class="form-control mb-2" placeholder="Password (3-8 chars)" maxlength="8">
                        <select id="nuRole" class="form-select">${this.roleOptions('user')}</select>`,
                    showCancelButton: true,
                    confirmButtonText: 'ADD',
                    preConfirm: () => {
                        const username = document.getElementById('nuName').value.trim();
                        const password = document.getElementById('nuPwd').value;
                        const role = document.getElementById('nuRole').value;
                        if (!username || !password) {
                            Swal.showValidationMessage('All fields are required!');
                            return false;
                        }
                        if (password.length < 3 || password.length > 8) {
                            Swal.showValidationMessage('Password must be 3 to 8 characters');
                            return false;
                        }
                        return { username, password, role };
                    }
                }).then(r => {
                    if (r.isConfirmed) this.post({ action: 'add', ...r.value }).then(res => this.result(res));
                });
            }

            editRole(id, role, name) {
                Swal.fire({
                    title: `Change role: ${this.esc(name)}`,
                    html: `<select id="edRole" class="form-select">${this.roleOptions(role)}</select>`,
                    showCancelButton: true,
                    confirmButtonText: 'UPDATE',
                    preConfirm: () => document.getElementById('edRole').value
                }).then(r => {
                    if (r.isConfirmed) this.post({ action: 'edit', id, role: r.value }).then(res => this.result(res));
                });
            }

            resetPassword(id, name) {
                Swal.fire({
                    title: `Reset password: ${this.esc(name)}`,
                    input: 'password',
                    inputPlaceholder: 'New password (3-8 chars)',
                    showCancelButton: true,
                    confirmButtonText: 'RESET',
                    inputAttributes: { maxlength: 8 },
                    inputValidator: v => (!v || v.length < 3 || v.length > 8) ? '3 to 8 characters' : undefined
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
