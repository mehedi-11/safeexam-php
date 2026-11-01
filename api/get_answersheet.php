<?php
// api/get_answersheet.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$studentExamId = (int)($_GET['id'] ?? 0);
$examId = (int)($_GET['exam_id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$studentExamId && $examId > 0) {
    $findStmt = $pdo->prepare("SELECT id FROM student_exams WHERE exam_id = ? AND student_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 1");
    $findStmt->execute([$examId, $userId]);
    $studentExamId = (int)$findStmt->fetchColumn();
}

if (!$studentExamId) {
    echo json_encode(['success' => false, 'message' => 'No completed examination attempt found']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT se.*, se.total_score as score, e.title, e.total_marks, e.results_published
    FROM student_exams se
    JOIN exams e ON se.exam_id = e.id
    WHERE se.id = ?
");
$stmt->execute([$studentExamId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    echo json_encode(['success' => false, 'message' => 'Attempt not found']);
    exit;
}

// If student, check that it belongs to them and is published
if ($_SESSION['user_role'] === 'student') {
    if ($attempt['student_id'] != $_SESSION['user_id'] || !$attempt['results_published']) {
        echo json_encode(['success' => false, 'message' => 'Results have not been published by faculty yet']);
        exit;
    }
}

// Fetch questions and student answers
$qStmt = $pdo->prepare("
    SELECT eq.*, 
           eq.correct_option as correct_answer,
           sa.selected_option as student_answer, 
           sa.written_answer, 
           sa.is_correct, 
           sa.marks_obtained as marks_awarded,
           eq.marks as max_marks
    FROM exam_questions eq
    LEFT JOIN student_answers sa ON sa.question_id = eq.id AND sa.student_exam_id = ?
    WHERE eq.exam_id = ?
    ORDER BY eq.order_num ASC, eq.id ASC
");
$qStmt->execute([$studentExamId, $attempt['exam_id']]);
$questions = $qStmt->fetchAll();

$max = $attempt['total_marks'] > 0 ? $attempt['total_marks'] : 100;
$percentage = round(($attempt['total_score'] / $max) * 100, 1);

echo json_encode([
    'success' => true,
    'exam' => ['title' => $attempt['title']],
    'score' => $attempt['total_score'],
    'total_marks' => $attempt['total_marks'],
    'percentage' => $percentage,
    'attempt' => $attempt,
    'questions' => $questions
]);

