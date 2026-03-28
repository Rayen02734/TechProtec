<?php
/**
 * TechProtect v2.1 – Client: Register Device
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_CLIENT);

$db     = getDB();
$userId = currentUserId();
$errors = [];
$vals   = [];

// Check device limit
$countStmt = $db->prepare("SELECT COUNT(*) FROM devices WHERE owner_id = ?");
$countStmt->execute([$userId]);
$deviceCount = (int)$countStmt->fetchColumn();
$maxDevices  = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vals = [
        'device_name'   => sanitize($_POST['device_name']   ?? ''),
        'brand'         => sanitize($_POST['brand']         ?? ''),
        'model'         => sanitize($_POST['model']         ?? ''),
        'serial_number' => strtoupper(sanitize($_POST['serial_number'] ?? '')),
        'mac_address'   => strtoupper(sanitize($_POST['mac_address']   ?? '')),
        'device_type'   => sanitize($_POST['device_type']   ?? 'smartphone'),
        'color'         => sanitize($_POST['color']         ?? ''),
        'purchase_date' => sanitize($_POST['purchase_date'] ?? ''),
    ];

    if (!$vals['device_name'])   $errors[] = 'Device name is required.';
    if (!$vals['brand'])         $errors[] = 'Brand is required.';
    if (!$vals['model'])         $errors[] = 'Model is required.';
    if (!$vals['serial_number']) $errors[] = 'Serial number is required.';
    if ($deviceCount >= $maxDevices) $errors[] = "Device limit ({$maxDevices}) reached.";

    if (!in_array($vals['device_type'], ['smartphone','laptop','tablet','other'])) {
        $vals['device_type'] = 'smartphone';
    }

    if (!$errors) {
        // Check serial uniqueness
        $sStmt = $db->prepare("SELECT id FROM devices WHERE serial_number = ? LIMIT 1");
        $sStmt->execute([$vals['serial_number']]);
        if ($sStmt->fetch()) {
            $errors[] = 'A device with this serial number is already registered.';
        } else {
            $db->prepare("
                INSERT INTO devices (owner_id, device_name, brand, model, serial_number, mac_address, device_type, color, purchase_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $userId,
                $vals['device_name'], $vals['brand'], $vals['model'],
                $vals['serial_number'],
                $vals['mac_address']   ?: null,
                $vals['device_type'],
                $vals['color']         ?: null,
                $vals['purchase_date'] ?: null,
            ]);

            logAction($userId, 'DEVICE_REGISTERED', "Device S/N: {$vals['serial_number']}");
            setFlash('success', "Device '{$vals['device_name']}' registered successfully!");
            redirect(APP_URL . '/client/my_devices.php');
        }
    }
}

$pageTitle  = 'Register Device';
$activePage = 'register';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
    <i class="fa-solid fa-plus-circle"></i> Register a Device
</div>

<div style="max-width:680px;">
<div class="card">
    <?php if ($errors): ?>
    <div class="flash flash-error" style="margin-bottom:16px;">
        <i class="fa-solid fa-circle-xmark"></i>
        <ul style="margin:0;padding-left:14px;"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="device_name">Device Nickname <span class="required">*</span></label>
                <div class="input-icon">
                    <i class="fa-solid fa-tag"></i>
                    <input type="text" id="device_name" name="device_name" class="form-control"
                           placeholder="My iPhone 15 Pro" value="<?= e($vals['device_name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="device_type">Device Type <span class="required">*</span></label>
                <select id="device_type" name="device_type" class="form-control">
                    <?php foreach (['smartphone','laptop','tablet','other'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($vals['device_type'] ?? 'smartphone') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="brand">Brand <span class="required">*</span></label>
                <div class="input-icon">
                    <i class="fa-solid fa-building"></i>
                    <input type="text" id="brand" name="brand" class="form-control"
                           placeholder="Apple / Samsung…" value="<?= e($vals['brand'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="model">Model <span class="required">*</span></label>
                <div class="input-icon">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                    <input type="text" id="model" name="model" class="form-control"
                           placeholder="iPhone 15 Pro" value="<?= e($vals['model'] ?? '') ?>" required>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="serial_number">Serial Number <span class="required">*</span></label>
            <div class="input-icon">
                <i class="fa-solid fa-barcode"></i>
                <input type="text" id="serial_number" name="serial_number" class="form-control"
                       style="font-family:monospace;letter-spacing:.5px;text-transform:uppercase"
                       placeholder="e.g. SN1234567890"
                       value="<?= e($vals['serial_number'] ?? '') ?>" required spellcheck="false">
            </div>
            <span class="form-help">Settings → About → Serial Number (mobile) | On the box label.</span>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="mac_address">MAC Address <span style="color:var(--text-dim)">(optional)</span></label>
                <div class="input-icon">
                    <i class="fa-solid fa-network-wired"></i>
                    <input type="text" id="mac_address" name="mac_address" class="form-control"
                           style="font-family:monospace"
                           placeholder="AA:BB:CC:DD:EE:FF"
                           value="<?= e($vals['mac_address'] ?? '') ?>" maxlength="20">
                </div>
            </div>
            <div class="form-group">
                <label for="color">Color <span style="color:var(--text-dim)">(optional)</span></label>
                <div class="input-icon">
                    <i class="fa-solid fa-palette"></i>
                    <input type="text" id="color" name="color" class="form-control"
                           placeholder="Space Black" value="<?= e($vals['color'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="purchase_date">Purchase Date <span style="color:var(--text-dim)">(optional)</span></label>
            <input type="date" id="purchase_date" name="purchase_date" class="form-control"
                   value="<?= e($vals['purchase_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
        </div>

        <div style="display:flex;gap:12px;margin-top:8px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Register Device</button>
            <a href="<?= APP_URL ?>/client/my_devices.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
