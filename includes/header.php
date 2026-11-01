<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';
$auth = getCurrentUser();
$pageTitle = $pageTitle ?? 'SafeExam.AI — Intelligent Examination Platform';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- Google Fonts: Inter -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    .animate-fade-in { animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body class="min-h-screen flex flex-col text-slate-800">

  <!-- Top Navigation Bar -->
  <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-40 transition-all">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      
      <!-- Brand Logo -->
      <a href="/safeexam/" class="flex items-center gap-2.5 group">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform">
          <i data-lucide="shield-check" class="w-6 h-6"></i>
        </div>
        <div>
          <span class="text-lg font-black tracking-tight text-slate-900 flex items-center gap-1">
            SafeExam<span class="text-indigo-600">.AI</span>
          </span>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600 block -mt-1">PHP / MySQL Edition</span>
        </div>
      </a>

      <!-- Navigation & User Status -->
      <div class="flex items-center gap-4">
        <?php if ($auth): ?>
          <!-- Active Role Navigation Links -->
          <nav class="hidden md:flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs font-semibold text-slate-600">
            <?php if ($auth['role'] === 'student'): ?>
              <a href="/safeexam/student/dashboard.php" class="px-3 py-1.5 rounded-lg hover:text-indigo-600 hover:bg-white transition-all flex items-center gap-1.5">
                <i data-lucide="book-open" class="w-3.5 h-3.5"></i> My Exams
              </a>
              <a href="/safeexam/student/results.php" class="px-3 py-1.5 rounded-lg hover:text-indigo-600 hover:bg-white transition-all flex items-center gap-1.5">
                <i data-lucide="award" class="w-3.5 h-3.5"></i> Results
              </a>
              <a href="/safeexam/student/events.php" class="px-3 py-1.5 rounded-lg hover:text-indigo-600 hover:bg-white transition-all flex items-center gap-1.5">
                <i data-lucide="calendar" class="w-3.5 h-3.5"></i> Events
              </a>
            <?php elseif ($auth['role'] === 'teacher'): ?>
              <a href="/safeexam/teacher/dashboard.php" class="px-3 py-1.5 rounded-lg hover:text-indigo-600 hover:bg-white transition-all flex items-center gap-1.5">
                <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i> Dashboard
              </a>
              <a href="/safeexam/teacher/events.php" class="px-3 py-1.5 rounded-lg hover:text-indigo-600 hover:bg-white transition-all flex items-center gap-1.5">
                <i data-lucide="calendar" class="w-3.5 h-3.5"></i> Manage Events
              </a>
            <?php elseif ($auth['role'] === 'admin'): ?>
              <a href="/safeexam/admin/dashboard.php" class="px-3 py-1.5 rounded-lg hover:text-indigo-600 hover:bg-white transition-all flex items-center gap-1.5">
                <i data-lucide="shield" class="w-3.5 h-3.5"></i> Admin Console
              </a>
            <?php endif; ?>
          </nav>

          <!-- User Profile Dropdown / Card -->
          <div class="flex items-center gap-3 pl-2 border-l border-slate-200">
            <div class="text-right hidden sm:block">
              <span class="block text-xs font-bold text-slate-900 leading-tight"><?= htmlspecialchars($auth['name']) ?></span>
              <span class="block text-[10px] font-semibold uppercase tracking-wider text-indigo-600"><?= htmlspecialchars($auth['role']) ?></span>
            </div>
            <a href="/safeexam/logout.php" title="Sign Out" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-colors">
              <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
          </div>
        <?php else: ?>
          <a href="/safeexam/login.php" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5">
            <i data-lucide="log-in" class="w-3.5 h-3.5"></i> Sign In
          </a>
        <?php endif; ?>
      </div>

    </div>
  </header>

  <!-- Main Content Wrapper -->
  <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
