<?php
// api/proctor_log.php - Records proctoring violations and demerit points
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$studentId = $_SESSION['user_id'];
$examId = (int)($input['exam_id'] ?? 0);
$studentExamId = (int)($input['student_exam_id'] ?? 0);
$activity = trim($input['activity_type'] ?? 'General Incident');
$demerits = (int)($input['demerit_points'] ?? 1);

if (!$examId) {
    echo json_encode(['success' => false, 'message' => 'Invalid exam id']);
    exit;
}

// 1. Insert proctoring log
$stmt = $pdo->prepare("
    INSERT INTO proctoring_logs (exam_id, student_id, activity_type, demerit_points, details, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->execute([$examId, $studentId, $activity, $demerits, $activity]);

// 2. Increment demerit points on student_exams
if ($studentExamId) {
    $upStmt = $pdo->prepare("UPDATE student_exams SET demerit_points = demerit_points + ? WHERE id = ?");
    $upStmt->execute([$demerits, $studentExamId]);
}

echo json_encode(['success' => true]);

