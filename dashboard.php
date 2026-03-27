<?php
/**
 * TechProtect v2.1 – Admin Dashboard
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);
$db = getDB();

// Stats
function dbCount(string $sql): int {
    $r = getDB()->query($sql); return $r ? (int)$r->fetchColumn() : 0;
}
$stats = [
    'users'         => dbCount("SELECT COUNT(*) FROM users"),
    'clients'       => dbCount("SELECT COUNT(*) FROM users WHERE role='client'"),
    'police'        => dbCount("SELECT COUNT(*) FROM users WHERE role='police'"),
    'devices'       => dbCount("SELECT COUNT(*) FROM devices"),
    'stolen'        => dbCount("SELECT COUNT(*) FROM devices WHERE status='stolen'"),
    'declarations'  => dbCount("SELECT COUNT(*) FROM theft_declarations"),
    'active_gps'    => dbCount("SELECT COUNT(*) FROM gps_modules WHERE is_active=1 AND expires_at > NOW()"),
    'gps_points'    => dbCount("SELECT COUNT(*) FROM gps_tracking"),
    'logs'          => dbCount("SELECT COUNT(*) FROM logs"),
];

// Recent logs
$recentLogs = $db->query("
    SELECT l.*, u.full_name AS user_name, u.role AS user_role
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.created_at DESC LIMIT 10
")->fetchAll();

// Recent theft declarations
$recentThefts = $db->query("
    SELECT td.*, d.device_name, d.serial_number, u.full_name AS owner_name
    FROM theft_declarations td
    JOIN devices d ON d.id = td.device_id
    JOIN users u ON u.id = td.owner_id
    ORDER BY td.declared_at DESC LIMIT 5
")->fetchAll();

$pageTitle  = 'Admin Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fa-solid fa-gauge"></i> System Dashboard</div>

<!-- Stats Row 1 -->
<div class="stat-grid" style="margin-bottom:16px;">
    <div class="stat-tile">
        <div class="stat-icon cyan"><i class="fa-solid fa-users"></i></div>
        <div><div class="stat-value"><?= $stats['users'] ?></div><div class="stat-label">Total Users</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon green"><i class="fa-solid fa-user"></i></div>
        <div><div class="stat-value"><?= $stats['clients'] ?></div><div class="stat-label">Clients</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon violet"><i class="fa-solid fa-shield"></i></div>
        <div><div class="stat-value"><?= $stats['police'] ?></div><div class="stat-label">Police Officers</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon cyan"><i class="fa-solid fa-mobile"></i></div>
        <div><div class="stat-value"><?= $stats['devices'] ?></div><div class="stat-label">Registered Devices</div></div>
    </div>
</div>
<div class="stat-grid">
    <div class="stat-tile">
        <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div><div class="stat-value"><?= $stats['stolen'] ?></div><div class="stat-label">Stolen Devices</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon orange"><i class="fa-solid fa-file-shield"></i></div>
        <div><div class="stat-value"><?= $stats['declarations'] ?></div><div class="stat-label">Theft Declarations</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon green"><i class="fa-solid fa-satellite-dish"></i></div>
        <div><div class="stat-value"><?= $stats['active_gps'] ?></div><div class="stat-label">Active GPS Sessions</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon violet"><i class="fa-solid fa-location-dot"></i></div>
        <div><div class="stat-value"><?= number_format($stats['gps_points']) ?></div><div class="stat-label">GPS Data Points</div></div>
    </div>
</div>

<div class="grid-2" style="margin-top:24px;">
    <!-- Recent Thefts -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-triangle-exclamation"></i> Recent Thefts</div>
        </div>
        <?php if ($recentThefts): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Device</th><th>Owner</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentThefts as $t): ?>
                <tr>
                    <td><?= e($t['device_name']) ?><br><small style="color:var(--text-muted)"><?= e($t['serial_number']) ?></small></td>
                    <td style="font-size:.83rem"><?= e($t['owner_name']) ?></td>
                    <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDate($t['declared_at'],'d/m H:i') ?></td>
                    <td><span class="badge badge-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-shield-check"></i><p>No theft declarations.</p></div>
        <?php endif; ?>
    </div>

    <!-- Recent Logs -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-list"></i> Recent Events</div>
            <a href="<?= APP_URL ?>/admin/logs.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($recentLogs as $log): ?>
        <div style="display:flex;gap:12px;align-items:flex-start;padding:8px;border-radius:6px;background:var(--bg-input);">
            <div style="flex:1;overflow:hidden;">
                <div style="font-size:.82rem;font-weight:600"><?= e($log['action']) ?></div>
                <div style="font-size:.76rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($log['description']) ?></div>
            </div>
            <div style="font-size:.72rem;color:var(--text-dim);white-space:nowrap"><?= formatDate($log['created_at'],'H:i:s') ?></div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;">
    <a href="<?= APP_URL ?>/admin/manage_users.php"    class="btn btn-ghost"><i class="fa-solid fa-users"></i> Manage Users</a>
    <a href="<?= APP_URL ?>/admin/gps_activations.php" class="btn btn-ghost"><i class="fa-solid fa-satellite-dish"></i> GPS Monitor</a>
    <a href="<?= APP_URL ?>/admin/logs.php"            class="btn btn-ghost"><i class="fa-solid fa-list"></i> System Logs</a>
    <a href="<?= APP_URL ?>/admin/config.php"          class="btn btn-ghost"><i class="fa-solid fa-gear"></i> Configuration</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
