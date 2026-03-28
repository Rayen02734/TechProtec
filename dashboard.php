<?php
/**
 * TechProtect v2.1 – Client Dashboard
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_CLIENT);

$db     = getDB();
$userId = currentUserId();

// Stats
$totalDevices   = (int)$db->prepare("SELECT COUNT(*) FROM devices WHERE owner_id = ?")->execute([$userId]) ? null : null;
$stmtD = $db->prepare("SELECT COUNT(*) FROM devices WHERE owner_id = ?"); $stmtD->execute([$userId]); $totalDevices = (int)$stmtD->fetchColumn();
$stmtS = $db->prepare("SELECT COUNT(*) FROM devices WHERE owner_id = ? AND status='stolen'"); $stmtS->execute([$userId]); $stolenCount = (int)$stmtS->fetchColumn();
$stmtT = $db->prepare("SELECT COUNT(*) FROM theft_declarations WHERE owner_id = ? AND status != 'closed'"); $stmtT->execute([$userId]); $openCases = (int)$stmtT->fetchColumn();
$stmtN = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"); $stmtN->execute([$userId]); $unreadN = (int)$stmtN->fetchColumn();

// Recent devices
$stmtRD = $db->prepare("SELECT * FROM devices WHERE owner_id = ? ORDER BY registered_at DESC LIMIT 5"); $stmtRD->execute([$userId]); $recentDevices = $stmtRD->fetchAll();

// Recent notifications
$stmtRN = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6"); $stmtRN->execute([$userId]); $notifications = $stmtRN->fetchAll();

$pageTitle  = 'My Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
    <i class="fa-solid fa-gauge"></i> My Dashboard
</div>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-tile">
        <div class="stat-icon cyan"><i class="fa-solid fa-mobile"></i></div>
        <div><div class="stat-value"><?= $totalDevices ?></div><div class="stat-label">Registered Devices</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div><div class="stat-value"><?= $stolenCount ?></div><div class="stat-label">Stolen Devices</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon orange"><i class="fa-solid fa-folder-open"></i></div>
        <div><div class="stat-value"><?= $openCases ?></div><div class="stat-label">Open Cases</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon violet"><i class="fa-solid fa-bell"></i></div>
        <div><div class="stat-value"><?= $unreadN ?></div><div class="stat-label">Unread Alerts</div></div>
    </div>
</div>

<div class="grid-2">
    <!-- My Devices -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-mobile"></i> My Devices</div>
            <a href="<?= APP_URL ?>/client/register_device.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Add Device
            </a>
        </div>
        <?php if ($recentDevices): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Device</th><th>Serial</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentDevices as $d): ?>
                <tr>
                    <td>
                        <strong><?= e($d['device_name']) ?></strong><br>
                        <small style="color:var(--text-muted)"><?= e($d['brand']) ?> <?= e($d['model']) ?></small>
                    </td>
                    <td><code style="font-size:.8rem"><?= e($d['serial_number']) ?></code></td>
                    <td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span></td>
                    <td>
                        <?php if ($d['status'] === 'active'): ?>
                        <a href="<?= APP_URL ?>/client/declare_theft.php?device_id=<?= $d['id'] ?>" class="btn btn-danger btn-sm">Report</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a href="<?= APP_URL ?>/client/my_devices.php" style="display:block;text-align:center;margin-top:14px;font-size:.83rem;">View all devices →</a>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-mobile"></i>
            <p>No devices registered yet.</p>
            <a href="<?= APP_URL ?>/client/register_device.php" class="btn btn-primary btn-sm" style="margin-top:12px;">Register your first device</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Notifications -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-bell"></i> Alerts</div>
            <?php if ($unreadN > 0): ?>
            <button onclick="markAllRead()" class="btn btn-ghost btn-sm">Mark all read</button>
            <?php endif; ?>
        </div>
        <?php if ($notifications): ?>
        <div style="display:flex;flex-direction:column;gap:10px;" id="notifList">
            <?php foreach ($notifications as $n): ?>
            <div class="notif-item <?= !$n['is_read'] ? 'notif-unread' : '' ?>" id="notif-<?= $n['id'] ?>"
                 style="padding:12px;border-radius:8px;background:<?= !$n['is_read'] ? 'rgba(6,182,212,.06)' : 'var(--bg-input)' ?>;border:1px solid <?= !$n['is_read'] ? 'rgba(6,182,212,.2)' : 'var(--border)' ?>;">
                <div style="font-size:.85rem;font-weight:<?= !$n['is_read'] ? '600' : '400' ?>">
                    <?= e($n['title']) ?>
                </div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:3px;"><?= e($n['message']) ?></div>
                <div style="font-size:.72rem;color:var(--text-dim);margin-top:5px;"><?= formatDate($n['created_at']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-bell-slash"></i><p>No notifications yet.</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
async function markAllRead() {
    await fetch('<?= APP_URL ?>/api/notifications.php?action=read_all', { method: 'POST' });
    document.querySelectorAll('.notif-unread').forEach(el => {
        el.style.background = 'var(--bg-input)';
        el.style.border     = '1px solid var(--border)';
        el.classList.remove('notif-unread');
    });
    TP.toast('All notifications marked as read.', 'success');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
