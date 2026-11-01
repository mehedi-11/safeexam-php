<?php
// config/auth.php - Session and Role Access Guards

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'             => $_SESSION['user_id'],
        'name'           => $_SESSION['user_name'] ?? '',
        'email'          => $_SESSION['user_email'] ?? '',
        'role'           => $_SESSION['user_role'] ?? '',
        'status'         => $_SESSION['user_status'] ?? '',
        'student_id_str' => $_SESSION['student_id_str'] ?? ''
    ];
}

function requireRole($allowedRoles = []) {
    if (!isLoggedIn()) {
        header("Location: /safeexam/login.php");
        exit;
    }

    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    $currentRole = $_SESSION['user_role'] ?? '';
    if (!in_array($currentRole, $allowedRoles)) {
        // Redirect to their appropriate home
        if ($currentRole === 'admin') {
            header("Location: /safeexam/admin/dashboard.php");
        } elseif ($currentRole === 'teacher') {
            header("Location: /safeexam/teacher/dashboard.php");
        } elseif ($currentRole === 'student') {
            header("Location: /safeexam/student/dashboard.php");
        } else {
            header("Location: /safeexam/login.php");
        }
        exit;
    }
}

