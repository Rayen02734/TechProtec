<?php
/**
 * TechProtect v2.1 – Client: My Devices
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_CLIENT);

$db     = getDB();
$userId = currentUserId();

$stmt = $db->prepare("SELECT * FROM devices WHERE owner_id = ? ORDER BY registered_at DESC");
$stmt->execute([$userId]);
$devices = $stmt->fetchAll();

$pageTitle  = 'My Devices';
$activePage = 'devices';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
    <i class="fa-solid fa-mobile"></i> My Devices
    <a href="<?= APP_URL ?>/client/register_device.php" class="btn btn-primary btn-sm" style="margin-left:auto;">
        <i class="fa-solid fa-plus"></i> Add Device
    </a>
</div>

<div class="card">
    <?php if ($devices): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Device</th>
                    <th>Serial Number</th>
                    <th>MAC</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($devices as $d): ?>
            <tr>
                <td>
                    <strong><?= e($d['device_name']) ?></strong><br>
                    <small style="color:var(--text-muted)"><?= e($d['brand']) ?> <?= e($d['model']) ?></small>
                </td>
                <td><code style="font-size:.8rem"><?= e($d['serial_number']) ?></code></td>
                <td><code style="font-size:.78rem;color:var(--text-muted)"><?= $d['mac_address'] ? e($d['mac_address']) : '—' ?></code></td>
                <td><span style="color:var(--text-muted);font-size:.82rem"><?= ucfirst($d['device_type']) ?></span></td>
                <td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span></td>
                <td style="font-size:.82rem;color:var(--text-muted)"><?= formatDate($d['registered_at'], 'd M Y') ?></td>
                <td>
                    <div style="display:flex;gap:8px;">
                        <?php if ($d['status'] === 'active'): ?>
                        <a href="<?= APP_URL ?>/client/declare_theft.php?device_id=<?= $d['id'] ?>"
                           class="btn btn-danger btn-sm" title="Report as stolen">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-mobile"></i>
        <p>No devices registered yet. Add your devices to protect them.</p>
        <a href="<?= APP_URL ?>/client/register_device.php" class="btn btn-primary" style="margin-top:16px;">
            <i class="fa-solid fa-plus"></i> Register First Device
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
