<?php
/**
 * TechProtect v2.1 – Admin: System Configuration
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $allowed = ['app_name','gps_tracking_hours','gps_poll_interval','notification_email','max_devices_per_user'];
    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $val = sanitize($_POST[$key]);
            $db->prepare("UPDATE system_config SET config_value=? WHERE config_key=?")
               ->execute([$val, $key]);
        }
    }
    logAction(currentUserId(), 'ADMIN_CONFIG_UPDATE', 'System configuration updated.');
    setFlash('success', 'Configuration saved successfully.');
    redirect(APP_URL . '/admin/config.php');
}

$configStmt = $db->query("SELECT * FROM system_config ORDER BY config_key");
$configs = [];
while ($row = $configStmt->fetch()) {
    $configs[$row['config_key']] = $row;
}

$pageTitle  = 'System Configuration';
$activePage = 'config';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fa-solid fa-gear"></i> System Configuration</div>

<div style="max-width:700px;">
<div class="card">
    <div style="background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.3);border-radius:8px;padding:14px;margin-bottom:22px;font-size:.83rem;color:var(--yellow);display:flex;gap:10px;align-items:flex-start;">
        <i class="fa-solid fa-triangle-exclamation" style="margin-top:2px;"></i>
        <div>
            <strong>Security Notice:</strong> AES encryption keys are set in <code>/config/config.php</code> and cannot be changed here.
            Change defaults before deploying to production.
        </div>
    </div>

    <form method="POST">
        <div class="form-group">
            <label>Application Name</label>
            <input type="text" name="app_name" class="form-control"
                   value="<?= e($configs['app_name']['config_value'] ?? APP_NAME) ?>">
            <span class="form-help"><?= e($configs['app_name']['description'] ?? '') ?></span>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>GPS Tracking Duration (hours)</label>
                <input type="number" name="gps_tracking_hours" class="form-control" min="1" max="168"
                       value="<?= e($configs['gps_tracking_hours']['config_value'] ?? 72) ?>">
                <span class="form-help">How long GPS remains active after activation (max 168h).</span>
            </div>
            <div class="form-group">
                <label>GPS Poll Interval (seconds)</label>
                <input type="number" name="gps_poll_interval" class="form-control" min="10" max="300"
                       value="<?= e($configs['gps_poll_interval']['config_value'] ?? 30) ?>">
                <span class="form-help">How often buyer's browser sends location.</span>
            </div>
        </div>

        <div class="form-group">
            <label>Notification Email</label>
            <input type="email" name="notification_email" class="form-control"
                   value="<?= e($configs['notification_email']['config_value'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Max Devices per Client</label>
            <input type="number" name="max_devices_per_user" class="form-control" min="1" max="100"
                   value="<?= e($configs['max_devices_per_user']['config_value'] ?? 10) ?>">
        </div>

        <!-- Read-only info -->
        <div style="background:var(--bg-input);border-radius:8px;padding:16px;margin-top:6px;margin-bottom:18px;font-size:.82rem;color:var(--text-muted);">
            <div style="font-weight:600;margin-bottom:8px;color:var(--text-primary);">Read-Only Settings (config.php)</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div>AES Method: <code><?= AES_METHOD ?></code></div>
                <div>PHP Version: <code><?= phpversion() ?></code></div>
                <div>Session Lifetime: <code><?= SESSION_LIFETIME ?>s</code></div>
                <div>App URL: <code><?= APP_URL ?></code></div>
            </div>
        </div>

        <button type="submit" name="save_config" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk"></i> Save Configuration
        </button>
    </form>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
