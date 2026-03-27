<?php
/**
 * TechProtect v2.1 – API: Notifications
 * GET ?action=list — fetch current user's notifications
 * GET ?action=count — unread count only
 * POST ?action=read&id=X — mark a notification read
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$userId = currentUserId();
$action = $_GET['action'] ?? 'list';
$db     = getDB();

if ($action === 'count') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    echo json_encode(['count' => (int)$stmt->fetchColumn()]);
    exit;
}

if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_GET['id'] ?? 0);
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
       ->execute([$id, $userId]);
    jsonResponse(true, 'Marked read.');
}

if ($action === 'read_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
       ->execute([$userId]);
    jsonResponse(true, 'All marked read.');
}

// Default: list last 20 notifications
$stmt = $db->prepare("
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$userId]);
$notifs = $stmt->fetchAll();

echo json_encode(['success' => true, 'notifications' => $notifs]);
