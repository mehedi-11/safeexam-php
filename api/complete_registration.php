<?php
// api/complete_registration.php — Verifies OTP and Finalizes Account Creation
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? 'student');
$studentId = trim($_POST['student_id'] ?? '');
$enteredOtp = trim($_POST['otp'] ?? '');

if (empty($name) || empty($email) || empty($password) || empty($enteredOtp)) {
    echo json_encode(['success' => false, 'message' => 'All fields including the verification code are required.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

// 1. Verify OTP against database
$otpStmt = $pdo->prepare("
    SELECT id FROM otps 
    WHERE email = ? AND otp = ? AND purpose = 'registration' AND expires_at >= NOW()
    LIMIT 1
");
$otpStmt->execute([$email, $enteredOtp]);
$validOtp = $otpStmt->fetch();

// Also check session fallback
$sessionOtpValid = (
    isset($_SESSION['pending_reg_otp']) &&
    $_SESSION['pending_reg_otp']['email'] === $email &&
    $_SESSION['pending_reg_otp']['otp'] === $enteredOtp &&
    $_SESSION['pending_reg_otp']['expires'] >= time()
);

if (!$validOtp && !$sessionOtpValid) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code. Please check your Gmail or resend code.']);
    exit;
}

// 2. Double check uniqueness
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'An account with this email address already exists.']);
    exit;
}

if ($role === 'student' && !empty($studentId)) {
    $stmt2 = $pdo->prepare("SELECT id FROM users WHERE student_id_str = ? LIMIT 1");
    $stmt2->execute([$studentId]);
    if ($stmt2->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This Student ID is already registered.']);
        exit;
    }
}

// 3. Create User
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$status = ($role === 'teacher') ? 'pending' : 'approved';

$insUser = $pdo->prepare("
    INSERT INTO users (name, email, password, role, status, student_id_str, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$insUser->execute([
    $name,
    $email,
    $hashedPassword,
    $role,
    $status,
    ($role === 'student' ? $studentId : null)
]);

// 4. Invalidate OTP
$pdo->prepare("DELETE FROM otps WHERE email = ? AND purpose = 'registration'")->execute([$email]);
unset($_SESSION['pending_reg_otp']);

// 5. Success Message
if ($role === 'teacher') {
    $msg = 'Faculty registration submitted successfully! Your account is pending administrator approval.';
} else {
    $msg = 'Student account verified and created successfully! You can now sign in.';
}

echo json_encode([
    'success' => true,
    'role' => $role,
    'message' => $msg
]);

