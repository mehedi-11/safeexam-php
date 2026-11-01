<?php
// api/get_live_logs.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$examId = (int)($_GET['exam_id'] ?? 0);
$user = getCurrentUser();

if ($examId > 0) {
    $actStmt = $pdo->prepare("SELECT COUNT(*) FROM student_exams WHERE exam_id = ? AND status = 'in_progress'");
    $actStmt->execute([$examId]);
    $activeCount = (int)$actStmt->fetchColumn();

    $logStmt = $pdo->prepare("
        SELECT pl.*, pl.activity_type as violation_type, pl.created_at as timestamp, u.name as student_name, u.student_id_str, e.title as exam_title
        FROM proctoring_logs pl
        JOIN users u ON pl.student_id = u.id
        JOIN exams e ON e.id = pl.exam_id
        WHERE pl.exam_id = ?
        ORDER BY pl.id DESC
        LIMIT 50
    ");
    $logStmt->execute([$examId]);
    $logs = $logStmt->fetchAll();
} else {
    $teacherId = $user['id'];
    $actStmt = $pdo->prepare("
        SELECT COUNT(*) FROM student_exams se
        JOIN exams e ON e.id = se.exam_id
        WHERE e.teacher_id = ? AND se.status = 'in_progress'
    ");
    $actStmt->execute([$teacherId]);
    $activeCount = (int)$actStmt->fetchColumn();

    $logStmt = $pdo->prepare("
        SELECT pl.*, pl.activity_type as violation_type, pl.created_at as timestamp, u.name as student_name, u.student_id_str, e.title as exam_title
        FROM proctoring_logs pl
        JOIN users u ON pl.student_id = u.id
        JOIN exams e ON e.id = pl.exam_id
        WHERE e.teacher_id = ?
        ORDER BY pl.id DESC
        LIMIT 50
    ");
    $logStmt->execute([$teacherId]);
    $logs = $logStmt->fetchAll();
}

echo json_encode([
    'success' => true,
    'active_count' => $activeCount,
    'logs' => $logs
]);

