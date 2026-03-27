<?php
/**
 * TechProtect v2.1 – Admin: System Logs
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);
$db = getDB();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;
$action  = sanitize($_GET['action'] ?? '');

$where  = ''; $params = [];
if ($action) { $where = "WHERE l.action LIKE ?"; $params[] = "%$action%"; }

$total = (int)$db->prepare("SELECT COUNT(*) FROM logs l $where")->execute($params) ? null : null;
$cntS  = $db->prepare("SELECT COUNT(*) FROM logs l $where"); $cntS->execute($params); $total = (int)$cntS->fetchColumn();
$pages = (int)ceil($total / $perPage);

$stmt = $db->prepare("
    SELECT l.*, u.full_name AS user_name, u.role AS user_role
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    $where
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct action types for filter
$actions = $db->query("SELECT DISTINCT action FROM logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle  = 'System Logs';
$activePage = 'logs';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fa-solid fa-list"></i> System Logs</div>

<div style="display:flex;gap:12px;margin-bottom:16px;align-items:center;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;">
        <select name="action" class="form-control" style="width:220px;" onchange="this.form.submit()">
            <option value="">— All Events —</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <span style="color:var(--text-muted);font-size:.83rem;margin-left:auto"><?= number_format($total) ?> events</span>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Time</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td style="color:var(--text-dim);font-size:.78rem"><?= $l['id'] ?></td>
                <td style="font-size:.78rem;color:var(--text-muted);white-space:nowrap"><?= formatDate($l['created_at'],'d/m H:i:s') ?></td>
                <td style="font-size:.82rem">
                    <?php if ($l['user_name']): ?>
                    <?= e($l['user_name']) ?><br>
                    <span class="badge badge-<?= $l['user_role'] ?>" style="font-size:.63rem"><?= strtoupper($l['user_role'] ?? '') ?></span>
                    <?php else: ?>
                    <span style="color:var(--text-dim)">Guest</span>
                    <?php endif; ?>
                </td>
                <td><code style="font-size:.78rem;color:var(--accent)"><?= e($l['action']) ?></code></td>
                <td style="font-size:.82rem;color:var(--text-muted);max-width:300px;word-break:break-word"><?= e($l['description']) ?></td>
                <td><code style="font-size:.75rem;color:var(--text-dim)"><?= e($l['ip_address'] ?? '—') ?></code></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div style="display:flex;justify-content:center;gap:6px;margin-top:18px;flex-wrap:wrap;">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="?page=<?= $p ?>&action=<?= urlencode($action) ?>"
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
