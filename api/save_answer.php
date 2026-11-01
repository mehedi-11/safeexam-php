<?php
// api/save_answer.php - Periodic auto-save answers
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
$answers = $input['answers'] ?? [];

if (!$studentExamId || !$examId || empty($answers)) {
    echo json_encode(['success' => true, 'saved' => 0]);
    exit;
}

// Verify student owns this exam attempt
$vStmt = $pdo->prepare("SELECT id FROM student_exams WHERE id = ? AND student_id = ? AND status = 'in_progress'");
$vStmt->execute([$studentExamId, $studentId]);
if (!$vStmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Invalid attempt']);
    exit;
}

$count = 0;
foreach ($answers as $questionId => $data) {
    $qId = (int)$questionId;
    $val = $data['value'] ?? '';
    $type = $data['type'] ?? 'mcq';

    $selOpt = ($type === 'mcq') ? $val : null;
    $written = ($type === 'written') ? $val : null;

    // Check if question exists and check MCQ correct answer
    $qStmt = $pdo->prepare("SELECT correct_option, marks FROM exam_questions WHERE id = ? AND exam_id = ?");
    $qStmt->execute([$qId, $examId]);
    $qRow = $qStmt->fetch();
    
    $isCorrect = 0;
    $marksObtained = 0;
    if ($qRow && $type === 'mcq') {
        if (strtoupper(trim($selOpt)) === strtoupper(trim($qRow['correct_option']))) {
            $isCorrect = 1;
            $marksObtained = (int)$qRow['marks'];
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO student_answers (student_exam_id, student_id, exam_id, question_id, selected_option, written_answer, is_correct, marks_obtained, answered_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            selected_option = VALUES(selected_option),
            written_answer = VALUES(written_answer),
            is_correct = VALUES(is_correct),
            marks_obtained = VALUES(marks_obtained),
            answered_at = NOW()
    ");
    $stmt->execute([$studentExamId, $studentId, $examId, $qId, $selOpt, $written, $isCorrect, $marksObtained]);
    $count++;
}

echo json_encode(['success' => true, 'saved' => $count]);

