<?php
// api/send_registration_otp.php — Generates & Sends Real 6-Digit OTP via Gmail SMTP
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = trim($_POST['role'] ?? 'student');
$studentId = trim($_POST['student_id'] ?? '');

if (empty($name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

if ($role === 'student' && empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required for student registration.']);
    exit;
}

// 1. Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'An account with this email address already exists.']);
    exit;
}

// 2. Check if student ID already exists
if ($role === 'student' && !empty($studentId)) {
    $stmt2 = $pdo->prepare("SELECT id FROM users WHERE student_id_str = ? LIMIT 1");
    $stmt2->execute([$studentId]);
    if ($stmt2->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This Student ID is already registered in the system.']);
        exit;
    }
}

// 3. Generate 6-digit cryptographically secure OTP
$otp = (string)random_int(100000, 999999);

// 4. Save OTP in MySQL (and session)
$pdo->prepare("DELETE FROM otps WHERE email = ? AND purpose = 'registration'")->execute([$email]);
$insOtp = $pdo->prepare("
    INSERT INTO otps (email, otp, purpose, expires_at, created_at)
    VALUES (?, ?, 'registration', DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())
");
$insOtp->execute([$email, $otp]);

$_SESSION['pending_reg_otp'] = [
    'email' => $email,
    'otp' => $otp,
    'expires' => time() + 600
];

// 5. Send Real Email via Google SMTP
$mailRes = sendOtpEmail($email, $name, $otp, ($role === 'teacher' ? 'Faculty Registration' : 'Student Registration'));

if ($mailRes['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'A 6-digit verification code has been dispatched to your Gmail (' . htmlspecialchars($email) . ').'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to deliver email: ' . ($mailRes['error'] ?? 'SMTP delivery error. Please try again.')
    ]);
}

