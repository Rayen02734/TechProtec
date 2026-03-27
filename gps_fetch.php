<?php
/**
 * TechProtect v2.1 – API: Fetch GPS Positions for Police Dashboard
 * Requires authentication as police or admin.
 * Returns decrypted recent positions for a given device.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_POLICE, ROLE_ADMIN);
header('Content-Type: application/json; charset=utf-8');

$deviceId = (int)($_GET['device_id'] ?? 0);
$limit    = min((int)($_GET['limit'] ?? 50), 200);

if (!$deviceId) { jsonResponse(false, 'device_id required.'); }

$db = getDB();

// Verify device exists and is stolen
$devStmt = $db->prepare("SELECT * FROM devices WHERE id = ? LIMIT 1");
$devStmt->execute([$deviceId]);
$device = $devStmt->fetch();
if (!$device) { jsonResponse(false, 'Device not found.'); }

// Get GPS module
$modStmt = $db->prepare("SELECT * FROM gps_modules WHERE device_id = ? LIMIT 1");
$modStmt->execute([$deviceId]);
$module = $modStmt->fetch();

$positions = [];
if ($module) {
    $trackStmt = $db->prepare("
        SELECT lat_enc, lng_enc, accuracy, speed, altitude, recorded_at
        FROM gps_tracking
        WHERE module_id = ?
        ORDER BY recorded_at DESC
        LIMIT ?
    ");
    $trackStmt->execute([$module['id'], $limit]);
    $rows = $trackStmt->fetchAll();

    foreach ($rows as $row) {
        // Decrypt coordinates for authorised viewers only
        $positions[] = [
            'lat'       => decryptCoordinate($row['lat_enc']),
            'lng'       => decryptCoordinate($row['lng_enc']),
            'accuracy'  => $row['accuracy'],
            'speed'     => $row['speed'],
            'altitude'  => $row['altitude'],
            'time'      => $row['recorded_at'],
        ];
    }
}

jsonResponse(true, '', [
    'device'    => [
        'id'            => $device['id'],
        'name'          => $device['device_name'],
        'brand'         => $device['brand'],
        'model'         => $device['model'],
        'serial_number' => $device['serial_number'],
        'status'        => $device['status'],
    ],
    'module'    => $module ? [
        'is_active'    => (bool)$module['is_active'],
        'activated_at' => $module['activated_at'],
        'last_ping'    => $module['last_ping'],
        'expires_at'   => $module['expires_at'],
    ] : null,
    'positions' => $positions,
    'count'     => count($positions),
]);
