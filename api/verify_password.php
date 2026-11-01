<?php
// api/verify_password.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$examId = (int)($input['exam_id'] ?? 0);
$password = trim($input['password'] ?? '');

if (!$examId) {
    echo json_encode(['success' => false, 'message' => 'Invalid exam']);
    exit;
}

$stmt = $pdo->prepare("SELECT exam_password FROM exams WHERE id = ? AND is_live = 1 LIMIT 1");
$stmt->execute([$examId]);
$exam = $stmt->fetch();

if (!$exam) {
    echo json_encode(['success' => false, 'message' => 'Exam not found or not active']);
    exit;
}

if (empty($exam['exam_password']) || $exam['exam_password'] === $password) {
    $_SESSION['exam_authorized_' . $examId] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect exam password']);
}

