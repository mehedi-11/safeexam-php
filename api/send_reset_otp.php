<?php
// api/send_reset_otp.php — Sends Password Reset OTP via Gmail SMTP
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
if (empty($identifier)) {
    echo json_encode(['success' => false, 'message' => 'Please provide your registered email or student ID.']);
    exit;
}

// Find user
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? OR student_id_str = ? LIMIT 1");
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No account found with this identifier.']);
    exit;
}

$otp = (string)random_int(100000, 999999);

// Store in otps table
$pdo->prepare("DELETE FROM otps WHERE email = ? AND purpose = 'reset_password'")->execute([$user['email']]);
$ins = $pdo->prepare("INSERT INTO otps (email, otp, purpose, expires_at, created_at) VALUES (?, ?, 'reset_password', DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())");
$ins->execute([$user['email'], $otp]);

$mailRes = sendOtpEmail($user['email'], $user['name'], $otp, 'Password Reset');

if ($mailRes['success']) {
    echo json_encode([
        'success' => true,
        'email' => $user['email'],
        'message' => 'A 6-digit password reset code has been sent to ' . htmlspecialchars($user['email']) . '.'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to deliver email: ' . ($mailRes['error'] ?? 'SMTP delivery error')]);
}

