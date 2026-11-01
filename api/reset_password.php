<?php
// api/reset_password.php — Verifies Reset OTP & Updates User Password
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');
$newPassword = $_POST['new_password'] ?? '';

if (empty($email) || empty($otp) || empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
    exit;
}

// Verify OTP
$stmt = $pdo->prepare("SELECT id FROM otps WHERE email = ? AND otp = ? AND purpose = 'reset_password' AND expires_at >= NOW() LIMIT 1");
$stmt->execute([$email, $otp]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
    exit;
}

// Update password
$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$up = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
$up->execute([$hash, $email]);

// Delete OTP
$pdo->prepare("DELETE FROM otps WHERE email = ? AND purpose = 'reset_password'")->execute([$email]);

echo json_encode(['success' => true, 'message' => 'Password reset successfully! You can now sign in with your new password.']);

