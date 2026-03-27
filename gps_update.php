<?php
/**
 * TechProtect v2.1 – API: Receive GPS Coordinates from Buyer's Browser
 * Called silently by verifier.php JavaScript every 30s
 * No authentication required — uses GPS module token as authorization
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Only accept POST with JSON body
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, 'Method not allowed.');
}

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { jsonResponse(false, 'Invalid payload.'); }

$token    = trim($input['token']    ?? '');
$lat      = (float)($input['lat']   ?? 0);
$lng      = (float)($input['lng']   ?? 0);
$accuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
$speed    = isset($input['speed'])    ? (float)$input['speed']    : null;
$altitude = isset($input['altitude']) ? (float)$input['altitude'] : null;

// Validate token & coordinates
if (!$token || !$lat || !$lng) {
    jsonResponse(false, 'Missing required fields.');
}
if (abs($lat) > 90 || abs($lng) > 180) {
    jsonResponse(false, 'Invalid coordinates.');
}

$db = getDB();

// Look up active GPS module by token
$stmt = $db->prepare("
    SELECT gm.*, d.owner_id
    FROM gps_modules gm
    JOIN devices d ON d.id = gm.device_id
    WHERE gm.module_token = ?
      AND gm.is_active = 1
      AND gm.expires_at > NOW()
    LIMIT 1
");
$stmt->execute([$token]);
$module = $stmt->fetch();

if (!$module) {
    // Token invalid or expired — tell JS to stop polling
    jsonResponse(false, 'Token invalid or expired.', ['stop' => true]);
}

// Encrypt coordinates before storing
$latEnc = encryptCoordinate($lat);
$lngEnc = encryptCoordinate($lng);

$ip = $_SERVER['REMOTE_ADDR']     ?? '';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// Insert tracking record
$db->prepare("
    INSERT INTO gps_tracking (module_id, device_id, lat_enc, lng_enc, accuracy, speed, altitude, ip_address, user_agent)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
")->execute([
    $module['id'],
    $module['device_id'],
    $latEnc, $lngEnc,
    $accuracy, $speed, $altitude,
    $ip, $ua
]);

// Update last ping on module
$db->prepare("UPDATE gps_modules SET last_ping = NOW() WHERE id = ?")
   ->execute([$module['id']]);

// Notify owner every 10 updates (don't spam) — check count
$countStmt = $db->prepare("SELECT COUNT(*) FROM gps_tracking WHERE module_id = ?");
$countStmt->execute([$module['id']]);
$count = (int)$countStmt->fetchColumn();

if ($count === 1) {
    // First ping — alert owner GPS is live
    createNotification(
        $module['owner_id'],
        '📡 GPS Lock Acquired!',
        'Live GPS data is being received for your stolen device. Police have been alerted.',
        'alert',
        APP_URL . '/client/dashboard.php'
    );
}

jsonResponse(true, 'Location recorded.', [
    'next_poll' => GPS_POLL_INTERVAL,
    'count'     => $count
]);
