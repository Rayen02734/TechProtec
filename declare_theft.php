<?php
/**
 * TechProtect v2.1 – Client: Declare Theft
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_CLIENT);

$db     = getDB();
$userId = currentUserId();

// Load client's active devices for dropdown
$stmtD = $db->prepare("SELECT id, device_name, brand, model, serial_number FROM devices WHERE owner_id = ? AND status = 'active' ORDER BY device_name");
$stmtD->execute([$userId]);
$activeDevices = $stmtD->fetchAll();

$errors   = [];
$vals     = [];
$preselId = (int)($_GET['device_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vals = [
        'device_id'      => (int)($_POST['device_id']      ?? 0),
        'theft_date'     => sanitize($_POST['theft_date']     ?? ''),
        'theft_location' => sanitize($_POST['theft_location'] ?? ''),
        'description'    => sanitize($_POST['description']    ?? ''),
        'police_report'  => sanitize($_POST['police_report']  ?? ''),
    ];

    if (!$vals['device_id'])      $errors[] = 'Please select a device.';
    if (!$vals['theft_date'])     $errors[] = 'Theft date is required.';
    if (!$vals['theft_location']) $errors[] = 'Theft location is required.';

    if (!$errors) {
        // Verify device belongs to user
        $chk = $db->prepare("SELECT id FROM devices WHERE id = ? AND owner_id = ? AND status = 'active' LIMIT 1");
        $chk->execute([$vals['device_id'], $userId]);
        if (!$chk->fetch()) {
            $errors[] = 'Invalid device selection.';
        } else {
            // Create theft declaration
            $db->prepare("
                INSERT INTO theft_declarations (device_id, owner_id, theft_date, theft_location, description, police_report, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ")->execute([
                $vals['device_id'], $userId,
                $vals['theft_date'], $vals['theft_location'],
                $vals['description'] ?: null,
                $vals['police_report'] ?: null,
            ]);

            // Mark device as stolen
            $db->prepare("UPDATE devices SET status = 'stolen' WHERE id = ?")
               ->execute([$vals['device_id']]);

            // Notify all police users
            $police = $db->query("SELECT id FROM users WHERE role = 'police' AND is_active = 1")->fetchAll();
            foreach ($police as $p) {
                createNotification($p['id'], '🚨 New Theft Report!', "Device ID #{$vals['device_id']} reported stolen. Check the dashboard.", 'alert', APP_URL . '/police/stolen_devices.php');
            }

            logAction($userId, 'THEFT_DECLARED', "Device #{$vals['device_id']} declared stolen.");
            setFlash('success', 'Theft declared! Police have been notified. GPS will activate when a buyer verifies your device.');
            redirect(APP_URL . '/client/dashboard.php');
        }
    }
}

$pageTitle  = 'Declare Theft';
$activePage = 'theft';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
    <i class="fa-solid fa-triangle-exclamation" style="color:var(--red)"></i> Declare Theft
</div>

<div style="max-width:660px;">
    <?php if (!$activeDevices): ?>
    <div class="card">
        <div class="empty-state">
            <i class="fa-solid fa-mobile"></i>
            <p>You have no active devices to report as stolen.</p>
            <a href="<?= APP_URL ?>/client/register_device.php" class="btn btn-primary" style="margin-top:14px;">Register a Device First</a>
        </div>
    </div>
    <?php else: ?>
    <div class="card" style="border:1px solid rgba(239,68,68,.3);">
        <div style="background:rgba(239,68,68,.06);border-radius:8px;padding:14px;margin-bottom:22px;display:flex;gap:12px;align-items:flex-start;">
            <i class="fa-solid fa-circle-info" style="color:var(--red);margin-top:2px;"></i>
            <div style="font-size:.85rem;color:var(--text-muted);">
                Once you declare theft, your device will be marked as <strong style="color:var(--red)">stolen</strong>.
                GPS will <strong>automatically activate</strong> the moment a buyer verifies its serial number.
                Police will be notified immediately.
            </div>
        </div>

        <?php if ($errors): ?>
        <div class="flash flash-error" style="margin-bottom:16px;">
            <i class="fa-solid fa-circle-xmark"></i>
            <ul style="margin:0;padding-left:14px;"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="device_id">Device <span class="required">*</span></label>
                <select id="device_id" name="device_id" class="form-control" required>
                    <option value="">— Select Device —</option>
                    <?php foreach ($activeDevices as $dev): ?>
                    <option value="<?= $dev['id'] ?>"
                        <?= ($vals['device_id'] ?? $preselId) == $dev['id'] ? 'selected' : '' ?>>
                        <?= e($dev['device_name']) ?> — <?= e($dev['brand']) ?> <?= e($dev['model']) ?> (S/N: <?= e($dev['serial_number']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="theft_date">Date of Theft <span class="required">*</span></label>
                    <input type="date" id="theft_date" name="theft_date" class="form-control"
                           value="<?= e($vals['theft_date'] ?? date('Y-m-d')) ?>"
                           max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="police_report">Police Report Ref <span style="color:var(--text-dim)">(optional)</span></label>
                    <div class="input-icon">
                        <i class="fa-solid fa-file-lines"></i>
                        <input type="text" id="police_report" name="police_report" class="form-control"
                               placeholder="e.g. PV-2024-001234" value="<?= e($vals['police_report'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="theft_location">Location of Theft <span class="required">*</span></label>
                <div class="input-icon">
                    <i class="fa-solid fa-location-dot"></i>
                    <input type="text" id="theft_location" name="theft_location" class="form-control"
                           placeholder="City, area, address…" value="<?= e($vals['theft_location'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description <span style="color:var(--text-dim)">(optional)</span></label>
                <textarea id="description" name="description" class="form-control"
                          placeholder="Describe what happened…"><?= e($vals['description'] ?? '') ?></textarea>
            </div>

            <div style="display:flex;gap:12px;margin-top:8px;">
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i> Submit Theft Declaration
                </button>
                <a href="<?= APP_URL ?>/client/dashboard.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
