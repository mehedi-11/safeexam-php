<?php
// api/get_event_registrations.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Invalid event id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM event_registrations WHERE event_id = ? ORDER BY id DESC");
$stmt->execute([$eventId]);
$regs = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'registrations' => $regs
]);

