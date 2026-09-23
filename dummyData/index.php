<?php

/**
 * toolHub/dummyData/index.php
 *
 * Standalone admin/dev tool for the `axis_addon_dummy_data` table.
 *
 * - Bootstraps the same Mezzio ServiceManager container the app uses (config/container.php),
 *   so it reuses the already-configured Laminas\Db\Adapter\Adapter (decrypted DB credentials
 *   from config/autoload/database.global.php) instead of hard-coding a new connection.
 * - Renders a sortable, searchable, scroll-loading table with add / view-edit JSON / delete /
 *   active toggle, all driven by this same file via ?action=... AJAX calls (fetch()).
 *   UI: axisDummyData.css + axisDummyData.js (same folder)
 * - Login + roles come from the decryption tool (../common/auth.php, common/users.json):
 *   `dummy_view` = see data, `dummy_manage` (admin) = add / update / delete / toggle.
 *
 * NOTE: Outside the Mezzio routing/pipeline (no AuthMiddleware).
 *
 * URL: /srfAddon/toolHub/dummyData/
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../common/auth.php';

chdir(dirname(__DIR__, 4));
require 'vendor/autoload.php';

/** @var \Psr\Container\ContainerInterface $container */
$container = require 'config/container.php';

/** @var \Laminas\Db\Adapter\Adapter $adapter */
$adapter = $container->get(\Laminas\Db\Adapter\Adapter::class);

const TABLE_NAME = 'axis_addon_dummy_data';

/**
 * Whitelisted columns. Never build SQL from raw user-supplied column names —
 * always validate against this map first.
 */
const COLUMNS = [
    'id' => ['label' => 'ID', 'type' => 'int', 'searchable' => true, 'insertable' => false],
    'accountNumber' => ['label' => 'Account Number', 'type' => 'text', 'searchable' => true, 'insertable' => true],
    'title' => ['label' => 'Title', 'type' => 'text', 'searchable' => true, 'insertable' => true],
    'description' => ['label' => 'Description', 'type' => 'textarea', 'searchable' => true, 'insertable' => true],
    'accountServiceRes' => ['label' => 'Account Service Res', 'type' => 'textarea', 'searchable' => false, 'insertable' => true],
    'demographicServiceRes' => ['label' => 'Demographic Service Res', 'type' => 'textarea', 'searchable' => false, 'insertable' => true],
    'creationdate' => ['label' => 'Creation Date', 'type' => 'datetime', 'searchable' => true, 'insertable' => true],
    'demographicServiceResJh1' => ['label' => 'Demographic Jh1', 'type' => 'textarea', 'searchable' => false, 'insertable' => true],
    'demographicServiceResJh2' => ['label' => 'Demographic Jh2', 'type' => 'textarea', 'searchable' => false, 'insertable' => true],
    'demographicServiceResJh3' => ['label' => 'Demographic Jh3', 'type' => 'textarea', 'searchable' => false, 'insertable' => true],
    'demographicServiceResJh4' => ['label' => 'Demographic Jh4', 'type' => 'textarea', 'searchable' => false, 'insertable' => true],
    'demographicServiceResJh5' => ['label' => 'Demographic Jh5', 'type' => 'textarea', 'searchable' => false, 'insertable' => true],
    'service' => ['label' => 'Service', 'type' => 'enum', 'searchable' => true, 'insertable' => true],
];

// Kept in sync with the DB enum (it has CKYC too)
const SERVICE_ENUM = ['CIFEnquiry', 'Account', 'Posidex', 'PanVerify', 'DL', 'VoterID', 'Timble', 'CKYC'];

const PAGE_SIZE = 50;

// Columns the table header can sort by
const SORTABLE = ['id', 'accountNumber', 'title', 'description', 'accountServiceRes', 'demographicServiceRes', 'creationdate', 'responseStatus', 'status'];

// Same rule as the UI badge: valid, non-empty JSON = Success
const RESPONSE_OK_SQL = "(JSON_VALID(accountServiceRes) AND TRIM(accountServiceRes) NOT IN ('{}', '[]'))";

$action = $_GET['action'] ?? $_POST['action'] ?? 'page';

/** Helper: send JSON and stop. */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// `status` column comes from DOC/SQL; page works without it
function hasStatusColumn(\Laminas\Db\Adapter\Adapter $adapter): bool
{
    return $adapter->query("SHOW COLUMNS FROM " . TABLE_NAME . " LIKE 'status'", [])->count() > 0;
}

// Writes need the custom header, so a plain cross-site form can't hit them
function requireAjaxPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        jsonResponse(['success' => false, 'error' => 'Invalid request.'], 400);
    }
}

function postedId(): int
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid id.'], 400);
    }
    return $id;
}

function runSql(\Laminas\Db\Adapter\Adapter $adapter, \Laminas\Db\Sql\SqlInterface $obj): \Laminas\Db\Adapter\Driver\ResultInterface
{
    $sql = new \Laminas\Db\Sql\Sql($adapter);
    return $sql->prepareStatementForSqlObject($obj)->execute();
}

if ($action === 'list') {
    requireLogin('dummy_view', true);
} elseif (in_array($action, ['insert', 'update', 'delete', 'toggle'], true)) {
    requireLogin('dummy_manage', true);
    requireAjaxPost();
    checkCsrf();
}

/** ------------------------------------------------------------------ */
/** action=list : fetch rows (search + sort + pagination + stats)      */
/** ------------------------------------------------------------------ */
if ($action === 'list') {
    try {
        $hasStatus = hasStatusColumn($adapter);
        $limit = max(1, min(200, (int) ($_GET['limit'] ?? PAGE_SIZE)));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $column = trim((string) ($_GET['column'] ?? ''));
        $value = trim((string) ($_GET['value'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? 'id');
        $dir = strtoupper((string) ($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if (!in_array($sort, SORTABLE, true) || ($sort === 'status' && !$hasStatus)) {
            $sort = 'id';
        }

        $sql = new \Laminas\Db\Sql\Sql($adapter);
        $select = $sql->select(TABLE_NAME);

        if ($column !== '' && $value !== '') {
            if (!isset(COLUMNS[$column]) || !COLUMNS[$column]['searchable']) {
                jsonResponse(['success' => false, 'error' => 'Invalid or non-searchable column.'], 400);
            }

            if (COLUMNS[$column]['type'] === 'int') {
                $select->where->equalTo($column, $value);
            } else {
                $select->where->like($column, '%' . $value . '%');
            }
        }

        $countSelect = clone $select;
        $countSelect->columns(['total' => new \Laminas\Db\Sql\Expression('COUNT(*)')]);
        $total = (int) (runSql($adapter, $countSelect)->current()['total'] ?? 0);

        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);

        $orderBy = $sort === 'responseStatus' ? RESPONSE_OK_SQL : $sort;
        $select->order([new \Laminas\Db\Sql\Expression("{$orderBy} {$dir}"), new \Laminas\Db\Sql\Expression("id {$dir}")]);
        $select->limit($limit)->offset(($page - 1) * $limit);

        $rows = [];
        foreach (runSql($adapter, $select) as $row) {
            $rows[] = (array) $row;
        }

        // Cards show whole-table numbers, not the filtered ones
        $statsCols = ['total' => new \Laminas\Db\Sql\Expression('COUNT(*)')];
        if ($hasStatus) {
            $statsCols['active'] = new \Laminas\Db\Sql\Expression('COALESCE(SUM(status = 1), 0)');
        }
        $stats = runSql($adapter, $sql->select(TABLE_NAME)->columns($statsCols))->current();
        $allTotal = (int) $stats['total'];
        $active = $hasStatus ? (int) $stats['active'] : null;

        jsonResponse([
            'success' => true,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
            'hasStatus' => $hasStatus,
            'stats' => [
                'total' => $allTotal,
                'active' => $active,
                'inactive' => $hasStatus ? $allTotal - $active : null,
            ],
        ]);
    } catch (\Throwable $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/** ------------------------------------------------------------------ */
/** action=insert : add a new record                                   */
/** ------------------------------------------------------------------ */
if ($action === 'insert') {
    try {
        $data = [];
        foreach (COLUMNS as $col => $meta) {
            if (!$meta['insertable']) {
                continue;
            }
            $raw = $_POST[$col] ?? '';
            $raw = is_string($raw) ? trim($raw) : $raw;

            if ($raw === '') {
                $data[$col] = null;
                continue;
            }

            if ($meta['type'] === 'enum' && !in_array($raw, SERVICE_ENUM, true)) {
                jsonResponse(['success' => false, 'error' => "Invalid value for {$col}."], 400);
            }

            if ($meta['type'] === 'datetime') {
                // <input type="datetime-local"> gives "YYYY-MM-DDTHH:MM"
                $raw = str_replace('T', ' ', $raw);
            }

            $data[$col] = $raw;
        }

        $insert = (new \Laminas\Db\Sql\Sql($adapter))->insert(TABLE_NAME)->values($data);
        $result = runSql($adapter, $insert);

        jsonResponse(['success' => true, 'id' => $result->getGeneratedValue()]);
    } catch (\Throwable $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/** ------------------------------------------------------------------ */
/** action=update : save edited JSON responses                         */
/** ------------------------------------------------------------------ */
if ($action === 'update') {
    try {
        $id = postedId();
        $data = [];
        foreach (['accountServiceRes', 'demographicServiceRes'] as $col) {
            if (!array_key_exists($col, $_POST)) {
                continue;
            }
            $raw = trim((string) $_POST[$col]);
            if ($raw !== '') {
                json_decode($raw);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    jsonResponse(['success' => false, 'error' => COLUMNS[$col]['label'] . ' is not valid JSON.'], 400);
                }
            }
            $data[$col] = $raw === '' ? null : $raw;
        }
        if (!$data) {
            jsonResponse(['success' => false, 'error' => 'Nothing to update.'], 400);
        }

        $update = (new \Laminas\Db\Sql\Sql($adapter))->update(TABLE_NAME)->set($data)->where(['id' => $id]);
        runSql($adapter, $update);

        jsonResponse(['success' => true]);
    } catch (\Throwable $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/** ------------------------------------------------------------------ */
/** action=delete : remove a record                                    */
/** ------------------------------------------------------------------ */
if ($action === 'delete') {
    try {
        $delete = (new \Laminas\Db\Sql\Sql($adapter))->delete(TABLE_NAME)->where(['id' => postedId()]);
        $affected = runSql($adapter, $delete)->getAffectedRows();

        jsonResponse($affected > 0 ? ['success' => true] : ['success' => false, 'error' => 'Record not found.'], $affected > 0 ? 200 : 404);
    } catch (\Throwable $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/** ------------------------------------------------------------------ */
/** action=toggle : set active (1) / inactive (0)                      */
/** ------------------------------------------------------------------ */
if ($action === 'toggle') {
    try {
        if (!hasStatusColumn($adapter)) {
            jsonResponse(['success' => false, 'error' => 'status column missing — run DOC/SQL/2026-09-23_axis_addon_dummy_data_status.sql'], 400);
        }
        $status = ($_POST['status'] ?? '') === '1' ? 1 : 0;
        $update = (new \Laminas\Db\Sql\Sql($adapter))->update(TABLE_NAME)->set(['status' => $status])->where(['id' => postedId()]);
        runSql($adapter, $update);

        jsonResponse(['success' => true, 'status' => $status]);
    } catch (\Throwable $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/** ------------------------------------------------------------------ */
/** default: render the page                                           */
/** ------------------------------------------------------------------ */
$me = requireLogin('dummy_view');
$canManage = can('dummy_manage');
$authUrl = authPrefix();
$hubUrl = authHubUrl();
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$assetVer = static fn(string $rel): string => $rel . '?v=' . @filemtime(__DIR__ . '/' . $rel);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Axis Addon Dummy Data</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $e(csrfToken()) ?>">
    <link href="<?= $e($assetVer('axisDummyData.css')) ?>" rel="stylesheet" type="text/css" />
    <link href="../common/assets/hub.css" rel="stylesheet" type="text/css" />
</head>

<body data-can-manage="<?= $canManage ? '1' : '0' ?>">
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <symbol id="i-db" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3" fill="none" stroke="currentColor" stroke-width="2" /><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" fill="none" stroke="currentColor" stroke-width="2" /></symbol>
            <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" /></symbol>
            <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2.4" /><path d="M20 20l-4-4" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" /></symbol>
            <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v5h-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></symbol>
            <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></symbol>
            <symbol id="i-active" viewBox="0 0 24 24"><path d="M4 20V9l4 3V6l4 5V4l4 6V8l4 3v9z" fill="currentColor" /></symbol>
            <symbol id="i-inactive" viewBox="0 0 24 24"><circle cx="12" cy="12" r="6" fill="currentColor" /><path d="M9.5 12h5" stroke="#fff" stroke-width="2" stroke-linecap="round" /></symbol>
            <symbol id="i-edit" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></symbol>
            <symbol id="i-trash" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></symbol>
            <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6zM14 3v4h4M9 13h6M9 17h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></symbol>
            <symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" /></symbol>
            <symbol id="i-gear" viewBox="0 0 24 24"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zm8 3.5l2-1.5-2-3.5-2.4.8a7 7 0 0 0-1.9-1.1L15.2 4h-4l-.5 2.7a7 7 0 0 0-1.9 1.1L6.4 7 4.4 10.5l2 1.5a7 7 0 0 0 0 2.2l-2 1.5 2 3.5 2.4-.8c.6.5 1.2.8 1.9 1.1l.5 2.5h4l.5-2.5c.7-.3 1.3-.6 1.9-1.1l2.4.8 2-3.5-2-1.5c.1-.7.1-1.5 0-2.2z" fill="currentColor" /></symbol>
            <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5" fill="currentColor" /><path d="M2 20c0-3.9 3.1-6 7-6s7 2.1 7 6z" fill="currentColor" /><circle cx="17" cy="9" r="2.5" fill="currentColor" /><path d="M17 13.5c2.9 0 5 1.6 5 4.5h-4.4" fill="currentColor" /></symbol>
            <symbol id="i-braces" viewBox="0 0 24 24"><path d="M8 4c-2 0-3 1-3 3v2.5C5 11 4 12 3 12c1 0 2 1 2 2.5V17c0 2 1 3 3 3M16 4c2 0 3 1 3 3v2.5c0 1.5 1 2.5 2 2.5-1 0-2 1-2 2.5V17c0 2-1 3-3 3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></symbol>
            <symbol id="i-minify" viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5M9 9h6v6H9z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></symbol>
            <symbol id="i-save" viewBox="0 0 24 24"><path d="M5 3h11l3 3v15H5zM8 3v5h7V3M8 21v-7h8v7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></symbol>
            <symbol id="i-sort" viewBox="0 0 24 24"><path d="M12 3l5 7H7zM12 21l-5-7h10z" fill="currentColor" /></symbol>
        </defs>
    </svg>

    <div class="page">
        <header class="hero">
            <div class="hero-left">
                <a href="<?= $e($hubUrl) ?>" class="hub-back on-dark" title="Back to Tool Hub"><svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>Tool Hub</a>
                <div class="hero-icon"><svg><use href="#i-db" /></svg></div>
                <div>
                    <h1>Axis Addon Dummy Data</h1>
                    <p>Manage response data used in Axis Addon SRF</p>
                </div>
            </div>
            <div class="hero-right">
                <span class="user-chip" title="Role: <?= $e($me['role']) ?>"><?= $e($me['username']) ?> · <?= $e(strtoupper($me['role'])) ?></span>
                <a href="<?= $e($authUrl) ?>logout.php" class="btn btn-ghost">Logout</a>
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn-white" id="openInsertBtn"><svg><use href="#i-plus" /></svg>Add New Record</button>
                <?php endif; ?>
            </div>
        </header>

        <section class="top-row">
            <div class="card filter-card">
                <div class="filter-field">
                    <label for="searchColumn">Select Column</label>
                    <div class="select-wrap">
                        <select id="searchColumn">
                            <option value="">-- Select Column --</option>
                            <?php foreach (COLUMNS as $col => $meta): if (!$meta['searchable']) continue; ?>
                                <option value="<?= $e($col) ?>"><?= $e($meta['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-field grow">
                    <label for="searchValue">Search Keyword</label>
                    <div class="search-input">
                        <input type="text" id="searchValue" placeholder="Enter search text...">
                        <span class="search-input-icon"><svg><use href="#i-search" /></svg></span>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-lg" id="searchBtn"><svg><use href="#i-search" /></svg>Search</button>
                <button type="button" class="btn btn-light btn-lg" id="clearBtn"><svg><use href="#i-refresh" /></svg>Clear</button>
            </div>

            <div class="card stat stat-total">
                <div class="stat-icon"><svg><use href="#i-check" /></svg></div>
                <div><span>Total Records</span><strong id="statTotal">–</strong></div>
            </div>
            <div class="card stat stat-active">
                <div class="stat-icon"><svg><use href="#i-active" /></svg></div>
                <div><span>Active</span><strong id="statActive">–</strong></div>
            </div>
            <div class="card stat stat-inactive">
                <div class="stat-icon"><svg><use href="#i-inactive" /></svg></div>
                <div><span>Inactive</span><strong id="statInactive">–</strong></div>
            </div>
        </section>

        <div class="notice" id="statusNotice" hidden>
            Status column is not in the table yet, so the toggle and Active/Inactive counts are off.
            Run <code>DOC/SQL/2026-09-23_axis_addon_dummy_data_status.sql</code> to enable them.
        </div>

        <section class="card table-card">
            <div class="table-top">
                <span class="showing" id="showingTop">Loading…</span>
                <span class="showing muted">Scroll down to load more</span>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th data-sort="accountNumber">Account Number</th>
                            <th data-sort="title">Title</th>
                            <th data-sort="description">Description</th>
                            <th class="col-center">Response</th>
                            <th data-sort="creationdate">Creation Date</th>
                            <th data-sort="responseStatus">Response Status</th>
                            <th data-sort="status" class="col-center">Status</th>
                            <th class="col-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tbody">
                        <tr class="status-row">
                            <td colspan="9">Loading…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- View / Edit response modal -->
    <div class="modal-backdrop" id="viewBackdrop">
        <div class="modal modal-view" role="dialog" aria-modal="true" aria-labelledby="viewTitle">
            <div class="modal-head">
                <h2 id="viewTitle"><svg><use href="#i-file" /></svg><?= $canManage ? 'View / Edit Response' : 'View Response' ?></h2>
                <button type="button" class="icon-close" data-close="viewBackdrop" aria-label="Close"><svg><use href="#i-close" /></svg></button>
            </div>
            <div class="modal-body">
                <dl class="info-box">
                    <dt>Account Number</dt>
                    <dd id="vAccount"></dd>
                    <dt>Title</dt>
                    <dd id="vTitle"></dd>
                    <dt>Description</dt>
                    <dd id="vDesc"></dd>
                </dl>

                <div class="tabs" role="tablist">
                    <button type="button" class="tab active" data-tab="accountServiceRes"><svg><use href="#i-gear" /></svg>Account Service Request</button>
                    <button type="button" class="tab" data-tab="demographicServiceRes"><svg><use href="#i-users" /></svg>Demographic Service Request</button>
                    <button type="button" class="tab" data-tab="raw"><svg><use href="#i-braces" /></svg>Raw Response (Full JSON)</button>
                </div>

                <div class="json-tools">
                    <button type="button" class="btn btn-outline btn-sm" id="minifyBtn"><svg><use href="#i-minify" /></svg>Minify JSON</button>
                    <button type="button" class="btn btn-primary btn-sm" id="prettyBtn"><svg><use href="#i-braces" /></svg>Pretty JSON</button>
                </div>

                <div class="code-editor" id="codeEditor">
                    <pre class="code-gutter" id="codeGutter" aria-hidden="true"></pre>
                    <div class="code-area">
                        <pre class="code-highlight" id="codeHighlight" aria-hidden="true"></pre>
                        <textarea id="codeInput" spellcheck="false" wrap="off" aria-label="JSON"></textarea>
                    </div>
                </div>
                <div class="json-error" id="jsonError"></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-light" data-close="viewBackdrop"><?= $canManage ? 'Cancel' : 'Close' ?></button>
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn-primary btn-lg" id="submitChangesBtn"><svg><use href="#i-save" /></svg>Submit Changes</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($canManage): ?>
    <!-- Insert modal -->
    <div class="modal-backdrop" id="insertBackdrop">
        <div class="modal modal-insert" role="dialog" aria-modal="true" aria-labelledby="insertTitle">
            <div class="modal-head">
                <h2 id="insertTitle"><svg><use href="#i-plus" /></svg>Add New Record</h2>
                <button type="button" class="icon-close" data-close="insertBackdrop" aria-label="Close"><svg><use href="#i-close" /></svg></button>
            </div>
            <form id="insertForm">
                <div class="modal-body form-grid">
                    <?php foreach (COLUMNS as $col => $meta):
                        if (!$meta['insertable']) continue;
                        $wide = $meta['type'] === 'textarea' ? ' wide' : ''; ?>
                        <div class="field<?= $wide ?>">
                            <label for="f_<?= $e($col) ?>"><?= $e($meta['label']) ?></label>
                            <?php if ($meta['type'] === 'textarea'): ?>
                                <textarea id="f_<?= $e($col) ?>" name="<?= $e($col) ?>" rows="2"></textarea>
                            <?php elseif ($meta['type'] === 'datetime'): ?>
                                <input type="datetime-local" id="f_<?= $e($col) ?>" name="<?= $e($col) ?>">
                            <?php elseif ($meta['type'] === 'enum'): ?>
                                <div class="select-wrap">
                                    <select id="f_<?= $e($col) ?>" name="<?= $e($col) ?>">
                                        <option value="">— none —</option>
                                        <?php foreach (SERVICE_ENUM as $opt): ?>
                                            <option value="<?= $e($opt) ?>"><?= $e($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="text" id="f_<?= $e($col) ?>" name="<?= $e($col) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="msg wide" id="insertMsg"></div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-light" data-close="insertBackdrop">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-lg"><svg><use href="#i-save" /></svg>Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete confirm -->
    <div class="modal-backdrop" id="deleteBackdrop">
        <div class="modal modal-confirm" role="alertdialog" aria-modal="true" aria-labelledby="deleteTitle">
            <div class="confirm-icon"><svg><use href="#i-trash" /></svg></div>
            <h2 id="deleteTitle">Delete this record?</h2>
            <p id="deleteText"></p>
            <div class="modal-foot center">
                <button type="button" class="btn btn-light" data-close="deleteBackdrop">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="toast" id="toast" role="status"></div>

    <script src="<?= $e($assetVer('axisDummyData.js')) ?>" type="module"></script>
</body>

</html>
