<?php
// api/submit_exam.php - Finalize examination attempt and calculate score
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$studentId = $_SESSION['user_id'];
$studentExamId = (int)($input['student_exam_id'] ?? 0);
$examId = (int)($input['exam_id'] ?? 0);
$reason = trim($input['reason'] ?? 'Submitted manually by student');

if (!$studentExamId || !$examId) {
    echo json_encode(['success' => false, 'message' => 'Invalid exam submission payload']);
    exit;
}

// 1. Calculate total score achieved
$scoreStmt = $pdo->prepare("SELECT COALESCE(SUM(marks_obtained), 0) FROM student_answers WHERE student_exam_id = ?");
$scoreStmt->execute([$studentExamId]);
$totalScore = (int)$scoreStmt->fetchColumn();

// 2. Finalize attempt in student_exams
$upStmt = $pdo->prepare("
    UPDATE student_exams 
    SET status = 'completed', 
        total_score = ?, 
        submission_reason = ?, 
        completed_at = NOW() 
    WHERE id = ? AND student_id = ?
");
$upStmt->execute([$totalScore, $reason, $studentExamId, $studentId]);

// Remove authorized session flag
unset($_SESSION['exam_authorized_' . $examId]);

echo json_encode([
    'success' => true, 
    'message' => 'Exam submitted successfully',
    'score' => $totalScore
]);

