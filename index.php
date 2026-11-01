<?php
// index.php - Main gateway
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'student';
    if ($role === 'admin') header("Location: /safeexam/admin/dashboard.php");
    elseif ($role === 'teacher') header("Location: /safeexam/teacher/dashboard.php");
    else header("Location: /safeexam/student/dashboard.php");
    exit;
}

header("Location: /safeexam/login.php");
exit;
