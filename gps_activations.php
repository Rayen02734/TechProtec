<?php
/**
 * TechProtect v2.1 – Admin: GPS Activations Monitor
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);
$db = getDB();

$stmt = $db->query("
    SELECT gm.*, d.device_name, d.brand, d.model, d.serial_number, d.status AS device_status,
           u.full_name AS owner_name,
           (SELECT COUNT(*) FROM gps_tracking gt WHERE gt.module_id = gm.id) AS ping_count,
           (SELECT recorded_at FROM gps_tracking gt WHERE gt.module_id = gm.id ORDER BY recorded_at DESC LIMIT 1) AS last_ping_time
    FROM gps_modules gm
    JOIN devices d ON d.id = gm.device_id
    JOIN users u ON u.id = d.owner_id
    ORDER BY gm.activated_at DESC
");
$modules = $stmt->fetchAll();

// Admin can deactivate GPS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate'])) {
    $mid = (int)$_POST['module_id'];
    $db->prepare("UPDATE gps_modules SET is_active=0 WHERE id=?")->execute([$mid]);
    logAction(currentUserId(), 'ADMIN_GPS_DEACTIVATE', "Deactivated GPS module #$mid");
    setFlash('success', 'GPS module deactivated.');
    redirect(APP_URL . '/admin/gps_activations.php');
}

$pageTitle  = 'GPS Activations';
$activePage = 'gps';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fa-solid fa-satellite-dish"></i> GPS Activation Monitor</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Device</th><th>Owner</th><th>Status</th><th>Activated</th><th>Activated By (IP)</th><th>Ping Points</th><th>Last Ping</th><th>Expires</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($modules): foreach ($modules as $m): ?>
            <tr>
                <td>
                    <strong><?= e($m['device_name']) ?></strong><br>
                    <small style="color:var(--text-muted)"><?= e($m['serial_number']) ?></small>
                </td>
                <td style="font-size:.85rem"><?= e($m['owner_name']) ?></td>
                <td>
                    <?php if ($m['is_active'] && strtotime($m['expires_at']) > time()): ?>
                    <span style="color:var(--green);font-size:.82rem;display:flex;align-items:center;gap:5px;">
                        <span class="gps-status-dot gps-active"></span> ACTIVE
                    </span>
                    <?php else: ?>
                    <span style="color:var(--text-dim);font-size:.82rem;">Inactive</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDate($m['activated_at'], 'd/m H:i') ?></td>
                <td><code style="font-size:.78rem"><?= e($m['activated_by'] ?? '—') ?></code></td>
                <td style="text-align:center">
                    <span class="badge <?= $m['ping_count'] > 0 ? 'badge-active' : 'badge-pending' ?>">
                        <?= number_format($m['ping_count']) ?>
                    </span>
                </td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?= $m['last_ping_time'] ? formatDate($m['last_ping_time'],'H:i:s') : '—' ?></td>
                <td style="font-size:.8rem;color:<?= strtotime($m['expires_at']) < time() ? 'var(--text-dim)' : 'var(--orange)' ?>">
                    <?= formatDate($m['expires_at'],'d/m H:i') ?>
                </td>
                <td>
                    <?php if ($m['is_active'] && strtotime($m['expires_at']) > time()): ?>
                    <form method="POST" onsubmit="return confirm('Deactivate GPS for this device?')">
                        <input type="hidden" name="module_id" value="<?= $m['id'] ?>">
                        <button type="submit" name="deactivate" class="btn btn-danger btn-sm">
                            <i class="fa-solid fa-power-off"></i> Stop
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9"><div class="empty-state"><i class="fa-solid fa-satellite-dish"></i><p>No GPS modules created yet.</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
