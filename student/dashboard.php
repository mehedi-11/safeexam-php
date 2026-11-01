<?php
// student/dashboard.php — SafeExam.AI Full Student Portal with Authentic Sidebar & All Features
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');
$user = getCurrentUser();
$studentId = $user['id'];

// Determine active tab from URL (defaults to 'dashboard')
$validTabs = ['dashboard', 'exams', 'insights', 'events', 'results', 'profile'];
$activeTab = $_GET['tab'] ?? 'dashboard';
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'dashboard';
}

// Handle Profile Update POST
$profileSuccess = '';
$profileError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name && $email) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $studentId]);
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $user['name'] = $name;
            $user['email'] = $email;
            $profileSuccess = 'Profile details updated successfully!';
        } else {
            $profileError = 'Please provide both your name and email.';
        }
    } elseif ($_POST['action'] === 'change_password') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 6) {
            $profileError = 'New password must be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$studentId]);
            $u = $stmt->fetch();
            if ($u && password_verify($oldPassword, $u['password'])) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $studentId]);
                $profileSuccess = 'Password updated successfully!';
            } else {
                $profileError = 'Current password is incorrect.';
            }
        }
    }
}

// 1. Fetch all exams with student's attempt status
$examsStmt = $pdo->prepare("
    SELECT e.*, 
           e.course_name as subject,
           e.exam_password as password,
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as question_count,
           se.id as student_exam_id,
           se.total_score as score,
           se.status as exam_status,
           se.completed_at as finished_at,
           (SELECT COUNT(*) FROM student_exams WHERE student_id = ? AND exam_id = e.id) as attempts
    FROM exams e
    LEFT JOIN student_exams se ON se.exam_id = e.id AND se.student_id = ? AND se.status = 'completed'
    ORDER BY e.created_at DESC
");
$examsStmt->execute([$studentId, $studentId]);
$allExams = $examsStmt->fetchAll();

// 2. Compute KPI Metrics
$liveExams = array_filter($allExams, fn($e) => $e['is_live'] && $e['exam_status'] !== 'completed');
$completedExams = array_filter($allExams, fn($e) => $e['exam_status'] === 'completed' && $e['results_published']);

$totalCompleted = count($completedExams);
$avgScorePct = 0;
if ($totalCompleted > 0) {
    $totalPct = 0;
    foreach ($completedExams as $ce) {
        $maxM = $ce['total_marks'] > 0 ? $ce['total_marks'] : 100;
        $totalPct += (($ce['score'] / $maxM) * 100);
    }
    $avgScorePct = round($totalPct / $totalCompleted, 1);
}

// 3. Subject-wise Analysis for Insights
$subjectStats = [];
foreach ($completedExams as $ce) {
    $subj = $ce['subject'] ?: 'General';
    if (!isset($subjectStats[$subj])) {
        $subjectStats[$subj] = ['total_pct' => 0, 'count' => 0];
    }
    $maxM = $ce['total_marks'] > 0 ? $ce['total_marks'] : 100;
    $subjectStats[$subj]['total_pct'] += (($ce['score'] / $maxM) * 100);
    $subjectStats[$subj]['count']++;
}
$subjectAverages = [];
foreach ($subjectStats as $subj => $data) {
    $subjectAverages[] = [
        'subject' => $subj,
        'average' => round($data['total_pct'] / $data['count'], 1)
    ];
}
usort($subjectAverages, fn($a, $b) => $b['average'] <=> $a['average']);

$strongSubjects = array_filter($subjectAverages, fn($s) => $s['average'] >= 75);
$weakSubjects = array_filter($subjectAverages, fn($s) => $s['average'] < 50);

// 4. Fetch Events for Student
$eventsStmt = $pdo->prepare("
    SELECT e.*, 
           e.event_date as start_time,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registered_count,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND student_id = ?) as is_registered,
           ex.id as linked_exam_id,
           ex.is_live as linked_exam_live
    FROM events e
    LEFT JOIN exams ex ON ex.event_id = e.id
    ORDER BY e.event_date ASC
");
$eventsStmt->execute([$studentId]);
$eventsList = $eventsStmt->fetchAll();
$registeredEvents = array_filter($eventsList, fn($ev) => $ev['is_registered'] > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Portal — SafeExam.AI</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- Google Fonts: Inter -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    .animate-fade-in { animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 flex flex-col lg:flex-row antialiased">

  <!-- Mobile Top Bar with Drawer Toggle -->
  <div class="lg:hidden flex items-center justify-between bg-white border-b border-slate-200 px-4 py-3 sticky top-0 z-40 shadow-sm">
    <div class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-xl bg-slate-900 flex items-center justify-center text-white font-extrabold text-sm">
        <i data-lucide="book-open" class="w-4 h-4"></i>
      </div>
      <span class="font-extrabold text-base tracking-tight text-slate-900">
        SExam<span class="text-rose-500">.AI</span>
      </span>
    </div>
    <button onclick="toggleMobileSidebar()" class="p-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
      <i data-lucide="menu" class="w-5 h-5"></i>
    </button>
  </div>

  <!-- Left Sidebar Navigation -->
  <aside id="sidebarDrawer" class="fixed inset-y-0 left-0 bg-white border-r border-slate-200 w-64 z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out lg:static flex flex-col justify-between shrink-0 shadow-lg lg:shadow-none">
    <div>
      <!-- Brand Logo Header -->
      <div class="p-6 border-b border-slate-200 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-slate-900 flex items-center justify-center text-white shadow-md shadow-slate-900/20">
            <i data-lucide="book-open" class="w-5 h-5"></i>
          </div>
          <div>
            <span class="font-black text-lg tracking-tight text-slate-900">
              SExam<span class="text-rose-500">.AI</span>
            </span>
            <span class="text-[10px] text-slate-400 block font-bold tracking-widest uppercase -mt-0.5">Student Portal</span>
          </div>
        </div>
        <button onclick="toggleMobileSidebar()" class="lg:hidden p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <!-- Navigation Tabs -->
      <nav class="p-4 space-y-1.5">
        <?php
        $navItems = [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
            ['id' => 'exams', 'label' => 'My Exams', 'icon' => 'file-text'],
            ['id' => 'insights', 'label' => 'Subject Insights', 'icon' => 'sparkles'],
            ['id' => 'events', 'label' => 'Events', 'icon' => 'calendar'],
            ['id' => 'results', 'label' => 'My Results', 'icon' => 'check-circle-2'],
            ['id' => 'profile', 'label' => 'Student Profile', 'icon' => 'settings'],
        ];
        foreach ($navItems as $item):
            $isActive = ($activeTab === $item['id']);
        ?>
          <a
            href="?tab=<?= $item['id'] ?>"
            class="w-full flex items-center gap-3 px-4 py-3 font-bold text-xs rounded-xl transition-all <?= $isActive ? 'bg-slate-900 text-white shadow-md shadow-slate-900/20' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100' ?>"
          >
            <i data-lucide="<?= $item['icon'] ?>" class="w-4 h-4"></i>
            <span><?= $item['label'] ?></span>
            <?php if ($item['id'] === 'exams' && count($liveExams) > 0): ?>
              <span class="ml-auto bg-rose-500 text-white text-[10px] font-black px-1.5 py-0.2 rounded-full">
                <?= count($liveExams) ?>
              </span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>

    <!-- Bottom User Card & Logout -->
    <div class="p-4 border-t border-slate-200 bg-slate-50/50 space-y-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3 overflow-hidden">
          <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-sm">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
          </div>
          <div class="truncate">
            <p class="text-xs font-bold text-slate-900 truncate leading-tight"><?= htmlspecialchars($user['name']) ?></p>
            <p class="text-[10px] text-slate-400 font-semibold truncate"><?= htmlspecialchars($user['student_id_str'] ?: $user['email']) ?></p>
          </div>
        </div>
      </div>
      <a
        href="/safeexam/logout.php"
        class="w-full flex items-center justify-center gap-2 px-3 py-2 border border-slate-200 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600 rounded-xl text-xs font-bold text-slate-600 transition-all"
      >
        <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
        <span>Sign Out</span>
      </a>
    </div>
  </aside>

  <!-- Mobile Backdrop Overlay -->
  <div id="sidebarBackdrop" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden"></div>

  <!-- Main Content Workspace Area -->
  <main class="flex-1 min-w-0 p-4 md:p-8 overflow-y-auto max-h-screen">
    
    <!-- TopMenu Component -->
    <div class="hidden lg:flex items-center justify-between bg-white border border-slate-200 px-6 py-4 rounded-2xl shadow-sm mb-6">
      <h2 class="text-xl font-black text-slate-900 capitalize tracking-tight flex items-center gap-2">
        <?php
          if ($activeTab === 'insights') echo 'Subject Insights & AI Suggestions';
          elseif ($activeTab === 'exams') echo 'My Examination Workspace';
          elseif ($activeTab === 'results') echo 'Examination Results & Transcripts';
          elseif ($activeTab === 'events') echo 'Institutional Events & Hackathons';
          elseif ($activeTab === 'profile') echo 'Student Account Settings';
          else echo 'Dashboard Overview';
        ?>
      </h2>
      <div class="flex items-center gap-3">
        <div class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-xl flex items-center gap-1.5">
          <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-500"></i>
          <span>Student ID: <strong><?= htmlspecialchars($user['student_id_str'] ?: 'Verified') ?></strong></span>
        </div>
      </div>
    </div>

    <!-- Alert / Toast Messages -->
    <?php if ($profileSuccess): ?>
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 py-3 px-5 rounded-2xl text-xs font-semibold mb-6 flex items-center gap-2 animate-fade-in shadow-sm">
        <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
        <span><?= htmlspecialchars($profileSuccess) ?></span>
      </div>
    <?php endif; ?>
    <?php if ($profileError): ?>
      <div class="bg-rose-50 border border-rose-200 text-rose-700 py-3 px-5 rounded-2xl text-xs font-semibold mb-6 flex items-center gap-2 animate-fade-in shadow-sm">
        <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
        <span><?= htmlspecialchars($profileError) ?></span>
      </div>
    <?php endif; ?>

    <!-- WelcomeHeader (rendered on dashboard tab) -->
    <?php if ($activeTab === 'dashboard'): ?>
      <div class="relative overflow-hidden bg-gradient-to-r from-slate-900 to-slate-800 rounded-3xl py-8 px-6 md:px-8 mb-6 shadow-md text-white border border-slate-800 animate-fade-in">
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 rounded-full bg-white opacity-5 blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-6">
          <div>
            <h1 class="text-2xl md:text-3xl font-black flex items-center gap-2 tracking-tight">
              Welcome Back, <span class="text-rose-400 underline decoration-rose-400/40 decoration-wavy underline-offset-4"><?= htmlspecialchars($user['name']) ?></span> 👋
            </h1>
            <p class="text-slate-300 mt-2 text-xs md:text-sm font-medium">Ready to conquer your academic evaluations? AI proctoring active.</p>
            <div class="mt-4 flex flex-wrap items-center gap-2.5">
              <span class="inline-flex items-center gap-1.5 bg-white/10 px-3 py-1.5 rounded-xl text-[11px] font-semibold text-emerald-400 border border-white/10">
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> System Secure
              </span>
              <span class="inline-flex items-center gap-1.5 bg-white/10 px-3 py-1.5 rounded-xl text-[11px] font-semibold text-rose-400 border border-white/10">
                <i data-lucide="activity" class="w-3.5 h-3.5"></i> Proctoring Edge AI Active
              </span>
              <span class="inline-flex items-center gap-1.5 bg-white/10 px-3 py-1.5 rounded-xl text-[11px] font-semibold text-amber-400 border border-white/10">
                <i data-lucide="zap" class="w-3.5 h-3.5"></i> Services Online
              </span>
            </div>
          </div>
          <!-- Live Date & Clock -->
          <div class="bg-white/10 backdrop-blur-md px-5 py-3.5 rounded-2xl border border-white/20 shrink-0 text-center sm:text-right">
            <div class="flex items-center gap-2 text-slate-200 text-xs font-semibold">
              <i data-lucide="calendar" class="w-4 h-4"></i>
              <span id="liveDate"><?= date('l, F j, Y') ?></span>
            </div>
            <div class="flex items-center gap-2 text-white text-lg font-black mt-1">
              <i data-lucide="clock" class="w-4 h-4 text-rose-400"></i>
              <span id="liveClock" class="font-mono"><?= date('h:i:s A') ?></span>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 1: DASHBOARD -->
    <?php if ($activeTab === 'dashboard'): ?>
      <div class="space-y-6 animate-fade-in">
        <!-- 3 KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
              <i data-lucide="play" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Live Exams</p>
              <h4 class="text-2xl font-black text-slate-900"><?= count($liveExams) ?></h4>
            </div>
          </div>

          <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
              <i data-lucide="check-circle-2" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Completed Exams</p>
              <h4 class="text-2xl font-black text-slate-900"><?= $totalCompleted ?></h4>
            </div>
          </div>

          <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
              <i data-lucide="graduation-cap" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Overall Average</p>
              <h4 class="text-2xl font-black text-slate-900"><?= $avgScorePct ?>%</h4>
            </div>
          </div>
        </div>

        <!-- Subject Insights & Suggestions Banner -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600">
              <i data-lucide="sparkles" class="w-5 h-5"></i>
            </div>
            <div>
              <span class="text-xs font-bold text-rose-600 uppercase tracking-wider block">Agent ELI Academic Intelligence</span>
              <h3 class="text-base font-black text-slate-900">Subject Insights & Performance Diagnostics</h3>
            </div>
          </div>

          <?php if (count($subjectAverages) > 0): ?>
            <div class="space-y-4">
              <?php if (count($strongSubjects) > 0): ?>
                <div class="p-4 bg-emerald-50/70 border border-emerald-200 rounded-2xl text-emerald-900">
                  <strong class="font-extrabold flex items-center gap-1.5 text-xs">
                    <span>🚀 Strengths:</span>
                  </strong>
                  <p class="text-xs mt-1 font-medium text-emerald-800">
                    <?= implode(', ', array_map(fn($s) => $s['subject'] . ' (' . $s['average'] . '%)', $strongSubjects)) ?>
                  </p>
                  <p class="text-[11px] text-emerald-600 mt-0.5">Solid comprehension observed across these subjects.</p>
                </div>
              <?php endif; ?>

              <?php if (count($weakSubjects) > 0): ?>
                <div class="p-4 bg-rose-50/70 border border-rose-200 rounded-2xl text-rose-900">
                  <strong class="font-extrabold flex items-center gap-1.5 text-xs">
                    <span>⚠️ Recommended Focus:</span>
                  </strong>
                  <p class="text-xs mt-1 font-medium text-rose-800">
                    <?= implode(', ', array_map(fn($s) => $s['subject'] . ' (' . $s['average'] . '%)', $weakSubjects)) ?>
                  </p>
                  <p class="text-[11px] text-rose-600 mt-0.5">Review foundational concepts and complete revision drills.</p>
                </div>
              <?php endif; ?>

              <div class="flex flex-wrap gap-2 pt-2">
                <?php foreach ($subjectAverages as $sa): ?>
                  <span class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 flex items-center gap-2">
                    <span><?= htmlspecialchars($sa['subject']) ?></span>
                    <span class="px-1.5 py-0.5 rounded-md text-[11px] <?= $sa['average'] >= 75 ? 'bg-emerald-100 text-emerald-700' : ($sa['average'] < 50 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') ?>">
                      <?= $sa['average'] ?>%
                    </span>
                  </span>
                <?php endforeach; ?>
              </div>

              <a href="?tab=insights" class="inline-flex items-center gap-2 mt-4 px-4 py-2.5 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-xl shadow-md transition-all">
                <i data-lucide="sparkles" class="w-3.5 h-3.5 text-rose-400"></i>
                <span>Open Full Subject Insights & AI Study Plan</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
              </a>
            </div>
          <?php else: ?>
            <p class="text-xs text-slate-500 font-medium">Take scheduled examinations to generate personalized AI performance diagnostics.</p>
            <a href="?tab=exams" class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl transition-all">
              <span>View Available Exams</span>
              <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
          <?php endif; ?>
        </div>

        <!-- Recent Results & Active Live Exams Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Active Live Exams -->
          <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                Active Live Exams
              </h3>
              <a href="?tab=exams" class="text-xs font-bold text-rose-600 hover:underline">View All</a>
            </div>
            <?php if (count($liveExams) === 0): ?>
              <p class="text-xs text-slate-400 py-4 text-center">No live examinations active right now.</p>
            <?php else: ?>
              <div class="space-y-3">
                <?php foreach (array_slice($liveExams, 0, 3) as $le): ?>
                  <div class="p-4 rounded-2xl border border-slate-200 hover:border-slate-800 transition-all flex items-center justify-between">
                    <div>
                      <h4 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($le['title']) ?></h4>
                      <p class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars($le['subject'] ?: 'General') ?> &bull; <?= $le['duration_minutes'] ?> mins</p>
                    </div>
                    <button onclick="promptExamPassword(<?= $le['id'] ?>, '<?= htmlspecialchars(addslashes($le['title'])) ?>')" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                      Start &rarr;
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Recent Submissions -->
          <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Recent Results</h3>
              <a href="?tab=results" class="text-xs font-bold text-rose-600 hover:underline">View All</a>
            </div>
            <?php if (count($completedExams) === 0): ?>
              <p class="text-xs text-slate-400 py-4 text-center">No completed examinations yet.</p>
            <?php else: ?>
              <div class="space-y-3">
                <?php foreach (array_slice($completedExams, 0, 3) as $ce): ?>
                  <div class="p-4 rounded-2xl border border-slate-200 flex items-center justify-between">
                    <div>
                      <h4 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($ce['title']) ?></h4>
                      <p class="text-[11px] text-slate-500 mt-0.5">Finished: <?= date('M d, Y', strtotime($ce['finished_at'])) ?></p>
                    </div>
                    <div class="text-right">
                      <span class="inline-block px-3 py-1 bg-slate-900 text-white text-xs font-mono font-bold rounded-lg">
                        <?= $ce['score'] ?> / <?= $ce['total_marks'] ?>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 2: MY EXAMS -->
    <?php if ($activeTab === 'exams'): ?>
      <div class="space-y-6 animate-fade-in">
        <!-- Search and Filter Bar -->
        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="relative w-full sm:w-80">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
            <input
              type="text"
              id="examSearchInput"
              onkeyup="filterExams()"
              placeholder="Search by title, subject..."
              class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800 transition-all"
            />
          </div>
          <span class="text-xs text-slate-500 font-semibold">
            Showing <?= count($allExams) ?> available examinations
          </span>
        </div>

        <!-- Exams Grid -->
        <div id="examsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($allExams as $exam): ?>
            <div class="exam-card bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-all" data-title="<?= strtolower($exam['title'] . ' ' . $exam['subject']) ?>">
              <div>
                <div class="flex items-center justify-between mb-3">
                  <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider <?= $exam['is_live'] ? 'bg-rose-50 text-rose-600 border border-rose-200' : 'bg-slate-100 text-slate-500' ?>">
                    <?= $exam['is_live'] ? '● Live Now' : 'Scheduled' ?>
                  </span>
                  <span class="text-xs font-semibold text-slate-400 flex items-center gap-1">
                    <i data-lucide="clock" class="w-3 h-3"></i> <?= $exam['duration_minutes'] ?>m
                  </span>
                </div>

                <h3 class="text-base font-black text-slate-900 tracking-tight mb-1"><?= htmlspecialchars($exam['title']) ?></h3>
                <p class="text-xs text-slate-500 mb-4 line-clamp-2"><?= htmlspecialchars($exam['description'] ?: 'No syllabus details provided.') ?></p>

                <div class="grid grid-cols-2 gap-2 text-[11px] font-semibold text-slate-600 mb-5 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                  <div>Subject: <span class="font-bold text-slate-900"><?= htmlspecialchars($exam['subject'] ?: 'General') ?></span></div>
                  <div>Marks: <span class="font-bold text-slate-900"><?= $exam['total_marks'] ?></span></div>
                  <div>Questions: <span class="font-bold text-slate-900"><?= $exam['question_count'] ?></span></div>
                  <div>Pass Mark: <span class="font-bold text-slate-900"><?= $exam['passing_marks'] ?></span></div>
                </div>
              </div>

              <div>
                <?php if ($exam['exam_status'] === 'completed'): ?>
                  <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <span class="text-xs font-bold text-emerald-600 flex items-center gap-1">
                      <i data-lucide="check-circle-2" class="w-4 h-4"></i> Completed
                    </span>
                    <button onclick="viewAnswersheet(<?= $exam['id'] ?>)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-bold transition-all">
                      Review &rarr;
                    </button>
                  </div>
                <?php elseif ($exam['is_live']): ?>
                  <button
                    onclick="promptExamPassword(<?= $exam['id'] ?>, '<?= htmlspecialchars(addslashes($exam['title'])) ?>')"
                    class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-rose-600/10 flex items-center justify-center gap-2"
                  >
                    <i data-lucide="play" class="w-3.5 h-3.5"></i>
                    <span>Take Examination</span>
                  </button>
                <?php else: ?>
                  <button disabled class="w-full py-2.5 bg-slate-100 text-slate-400 rounded-xl text-xs font-bold cursor-not-allowed">
                    Exam Not Live Yet
                  </button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 3: SUBJECT INSIGHTS -->
    <?php if ($activeTab === 'insights'): ?>
      <div class="space-y-6 animate-fade-in">
        <!-- Agent ELI AI Diagnostic Card -->
        <div class="bg-gradient-to-tr from-slate-900 to-slate-800 rounded-3xl p-6 md:p-8 text-white shadow-md relative overflow-hidden border border-slate-800">
          <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div>
              <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs font-bold mb-3">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Agent ELI Cognitive Diagnostic
              </div>
              <h2 class="text-2xl font-black tracking-tight">AI Academic Subject Insights & Recommendations</h2>
              <p class="text-slate-300 text-xs md:text-sm mt-1 max-w-2xl">
                Real-time algorithmic performance telemetry analyzing topic retention, penalty triggers, and precision vectors.
              </p>
            </div>
            <button onclick="runAiDiagnostic()" class="px-5 py-3 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-2xl shadow-lg shadow-rose-600/30 transition-all flex items-center gap-2 shrink-0">
              <i data-lucide="refresh-cw" class="w-4 h-4"></i>
              <span>Refresh Diagnostic</span>
            </button>
          </div>
        </div>

        <!-- Strengths & Attention Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Strengths -->
          <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center gap-2.5 mb-4 text-emerald-600 font-bold text-sm">
              <i data-lucide="check-circle-2" class="w-5 h-5"></i>
              <h3>Academic Strengths (&ge; 75%)</h3>
            </div>
            <?php if (count($strongSubjects) === 0): ?>
              <p class="text-xs text-slate-400 py-4">No subjects categorized in high-mastery bracket yet.</p>
            <?php else: ?>
              <div class="space-y-3">
                <?php foreach ($strongSubjects as $ss): ?>
                  <div class="p-3.5 bg-emerald-50/60 border border-emerald-200 rounded-2xl flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($ss['subject']) ?></span>
                    <span class="text-xs font-black text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-lg"><?= $ss['average'] ?>%</span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Attention Needed -->
          <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center gap-2.5 mb-4 text-rose-600 font-bold text-sm">
              <i data-lucide="alert-triangle" class="w-5 h-5"></i>
              <h3>Focus Areas (&lt; 50%)</h3>
            </div>
            <?php if (count($weakSubjects) === 0): ?>
              <p class="text-xs text-slate-400 py-4">No critical risk subjects identified. Excellent progress!</p>
            <?php else: ?>
              <div class="space-y-3">
                <?php foreach ($weakSubjects as $ws): ?>
                  <div class="p-3.5 bg-rose-50/60 border border-rose-200 rounded-2xl flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($ws['subject']) ?></span>
                    <span class="text-xs font-black text-rose-700 bg-rose-100 px-2 py-0.5 rounded-lg"><?= $ws['average'] ?>%</span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Interactive Study Checklist -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
          <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
            <i data-lucide="target" class="w-4 h-4 text-slate-600"></i>
            Personalized Action Items & Study Goals
          </h3>
          <div class="space-y-3" id="studyGoalsList">
            <label class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors">
              <input type="checkbox" class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500" checked />
              <span class="text-xs font-medium text-slate-700">Complete AI Edge Proctoring environment diagnostics</span>
            </label>
            <label class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors">
              <input type="checkbox" class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500" />
              <span class="text-xs font-medium text-slate-700">Review detailed answersheet mistakes from recent tests</span>
            </label>
            <label class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors">
              <input type="checkbox" class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500" />
              <span class="text-xs font-medium text-slate-700">Complete written response practice for descriptive topics</span>
            </label>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 4: EVENTS -->
    <?php if ($activeTab === 'events'): ?>
      <div class="space-y-6 animate-fade-in">
        <!-- Dual Tabs: All Events vs Registered Events -->
        <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
          <button onclick="switchEventSubTab('all')" id="btnAllEvents" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold transition-all shadow-sm">
            All Events (<?= count($eventsList) ?>)
          </button>
          <button onclick="switchEventSubTab('registered')" id="btnRegEvents" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all">
            Registered Events (<?= count($registeredEvents) ?>)
          </button>
        </div>

        <!-- All Events View -->
        <div id="allEventsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <?php foreach ($eventsList as $event): ?>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
              <div>
                <div class="flex items-center justify-between mb-3">
                  <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-100">
                    Event
                  </span>
                  <span class="text-xs text-slate-400 font-medium">
                    <?= date('M d, Y', strtotime($event['start_time'])) ?>
                  </span>
                </div>
                <h3 class="text-base font-black text-slate-900 mb-1"><?= htmlspecialchars($event['title']) ?></h3>
                <p class="text-xs text-slate-500 mb-4 line-clamp-2"><?= htmlspecialchars($event['description']) ?></p>
                
                <div class="text-[11px] text-slate-600 space-y-1 mb-5 bg-slate-50 p-3 rounded-2xl">
                  <div>Date: <strong class="text-slate-900"><?= date('F j, Y, g:i a', strtotime($event['start_time'])) ?></strong></div>
                  <div>Attendees: <strong class="text-slate-900"><?= $event['registered_count'] ?> registered</strong></div>
                </div>
              </div>

              <div>
                <?php if ($event['is_registered']): ?>
                  <div class="flex items-center gap-2">
                    <span class="flex-1 py-2 text-center text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-center gap-1">
                      <i data-lucide="check" class="w-3.5 h-3.5"></i> Registered
                    </span>
                    <?php if ($event['linked_exam_id'] && $event['linked_exam_live']): ?>
                      <button onclick="promptExamPassword(<?= $event['linked_exam_id'] ?>, '<?= htmlspecialchars(addslashes($event['title'])) ?>')" class="py-2 px-3 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all">
                        Go for Exam &rarr;
                      </button>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <button onclick="registerForEvent(<?= $event['id'] ?>)" class="w-full py-2.5 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2">
                    <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Register for Event
                  </button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Registered Events View (Hidden by default) -->
        <div id="regEventsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-6 hidden">
          <?php if (count($registeredEvents) === 0): ?>
            <div class="col-span-2 text-center py-12 bg-white rounded-3xl border border-slate-200 text-slate-400 text-xs font-medium">
              You have not registered for any events yet.
            </div>
          <?php else: ?>
            <?php foreach ($registeredEvents as $re): ?>
              <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                <div>
                  <div class="flex items-center justify-between mb-3">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-100">
                      Confirmed Registration
                    </span>
                    <span class="text-xs text-slate-400 font-medium">
                      <?= date('M d, Y', strtotime($re['start_time'])) ?>
                    </span>
                  </div>
                  <h3 class="text-base font-black text-slate-900 mb-1"><?= htmlspecialchars($re['title']) ?></h3>
                  <p class="text-xs text-slate-500 mb-4"><?= htmlspecialchars($re['description']) ?></p>
                  
                  <?php if (!empty($re['secret_code'])): ?>
                    <div class="bg-slate-50 border border-slate-200 p-3 rounded-2xl flex items-center justify-between mb-5">
                      <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Secret Access Passcode</span>
                        <span class="font-mono font-black text-slate-900 text-xs"><?= htmlspecialchars($re['secret_code']) ?></span>
                      </div>
                      <button onclick="copyToClipboard('<?= htmlspecialchars($re['secret_code']) ?>')" class="px-2.5 py-1.5 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-700 transition-colors flex items-center gap-1">
                        <i data-lucide="copy" class="w-3 h-3"></i> Copy
                      </button>
                    </div>
                  <?php endif; ?>
                </div>

                <div>
                  <?php if ($re['linked_exam_id'] && $re['linked_exam_live']): ?>
                    <button onclick="promptExamPassword(<?= $re['linked_exam_id'] ?>, '<?= htmlspecialchars(addslashes($re['title'])) ?>')" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2">
                      <i data-lucide="play" class="w-3.5 h-3.5"></i> Go for Exam
                    </button>
                  <?php else: ?>
                    <button onclick="customAlert({title:'Event Information', message:'This examination session is not currently live. Please wait for faculty activation.', type:'info'})" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all">
                      Check Exam Status
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 5: MY RESULTS -->
    <?php if ($activeTab === 'results'): ?>
      <div class="space-y-6 animate-fade-in">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h3 class="text-lg font-black text-slate-900 tracking-tight">Examination Academic Transcripts</h3>
              <p class="text-xs text-slate-500 mt-0.5">Verified proctored evaluations and individual question review</p>
            </div>
            <span class="text-xs font-bold text-slate-400"><?= count($completedExams) ?> records</span>
          </div>

          <?php if (count($completedExams) === 0): ?>
            <div class="text-center py-12 text-slate-400 text-xs font-medium">
              No completed examinations found. Take an exam to generate your official transcript.
            </div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-4">Exam Title</th>
                    <th class="py-3 px-4">Subject</th>
                    <th class="py-3 px-4">Date Taken</th>
                    <th class="py-3 px-4 text-center">Score</th>
                    <th class="py-3 px-4 text-center">Percentage</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-right">Answersheet</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                  <?php foreach ($completedExams as $item): 
                    $max = $item['total_marks'] > 0 ? $item['total_marks'] : 100;
                    $pct = round(($item['score'] / $max) * 100, 1);
                    $passed = $item['score'] >= $item['passing_marks'];
                  ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($item['title']) ?></td>
                      <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars($item['subject'] ?: 'General') ?></td>
                      <td class="py-3 px-4 text-slate-500"><?= date('M d, Y', strtotime($item['finished_at'])) ?></td>
                      <td class="py-3 px-4 text-center font-mono font-bold text-slate-800">
                        <?= $item['score'] ?> / <?= $item['total_marks'] ?>
                      </td>
                      <td class="py-3 px-4 text-center">
                        <span class="inline-block px-2 py-0.5 rounded-lg font-bold text-[11px] <?= $pct >= 75 ? 'bg-emerald-100 text-emerald-800' : ($pct < 50 ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') ?>">
                          <?= $pct ?>%
                        </span>
                      </td>
                      <td class="py-3 px-4 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $passed ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                          <?= $passed ? 'Passed' : 'Failed' ?>
                        </span>
                      </td>
                      <td class="py-3 px-4 text-right">
                        <button onclick="viewAnswersheet(<?= $item['id'] ?>)" class="px-3 py-1.5 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                          View &rarr;
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 6: STUDENT PROFILE -->
    <?php if ($activeTab === 'profile'): ?>
      <div class="max-w-3xl mx-auto space-y-6 animate-fade-in">
        <!-- Profile Overview Card -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-100">
            <div class="w-16 h-16 rounded-3xl bg-slate-900 text-white flex items-center justify-center font-black text-xl shadow-md">
              <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div>
              <h3 class="text-xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($user['name']) ?></h3>
              <p class="text-xs text-slate-500 mt-0.5 font-medium">Student ID: <strong class="text-slate-800"><?= htmlspecialchars($user['student_id_str'] ?: 'N/A') ?></strong></p>
              <span class="inline-block mt-2 px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-[10px] font-bold uppercase tracking-wider">
                Status: <?= htmlspecialchars($user['status']) ?>
              </span>
            </div>
          </div>

          <!-- Edit Profile Form -->
          <form method="POST" action="?tab=profile" class="space-y-4">
            <input type="hidden" name="action" value="update_profile">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800 transition-all">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800 transition-all">
              </div>
            </div>
            <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-xl shadow-sm transition-all">
              Save Profile Changes
            </button>
          </form>
        </div>

        <!-- Change Password Card -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <h3 class="text-base font-black text-slate-900 mb-1">Update Security Credentials</h3>
          <p class="text-xs text-slate-500 mb-5">Ensure your password is at least 6 characters with mixed symbols.</p>

          <form method="POST" action="?tab=profile" class="space-y-4">
            <input type="hidden" name="action" value="change_password">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Current Password</label>
                <input type="password" name="old_password" required placeholder="••••••••" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800 transition-all">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">New Password</label>
                <input type="password" name="new_password" required minlength="6" placeholder="••••••••" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800 transition-all">
              </div>
            </div>
            <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-xl shadow-sm transition-all">
              Update Password
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>

  </main>

  <!-- PASSWORD VERIFICATION POPUP MODAL -->
  <div id="passwordModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 animate-fade-in relative">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
            <i data-lucide="lock" class="w-5 h-5"></i>
          </div>
          <div>
            <h3 class="text-base font-black text-slate-900">Protected Examination</h3>
            <p class="text-xs text-slate-500" id="modalExamTitle">Enter Exam Passcode</p>
          </div>
        </div>
        <button onclick="closePasswordModal()" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <div id="passwordModalAlert" class="hidden mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium"></div>

      <div class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Secret Exam Passcode</label>
          <input
            type="password"
            id="examPasswordInput"
            placeholder="Enter passcode provided by instructor"
            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-800 transition-all"
          />
        </div>
        <div class="flex items-center justify-between text-xs text-slate-400">
          <span>Max 3 wrong attempts before 10-minute lockout</span>
          <span id="attemptCountBadge" class="font-bold text-rose-600"></span>
        </div>
        <button
          onclick="submitExamPassword()"
          id="btnSubmitPassword"
          class="w-full py-3 bg-slate-900 hover:bg-black text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-2"
        >
          <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
          <span>Verify Passcode & Enter Exam</span>
        </button>
      </div>
    </div>
  </div>

  <!-- ANSWERSHEET MODAL -->
  <div id="answersheetModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-3xl w-full p-6 md:p-8 shadow-2xl border border-slate-200 animate-fade-in relative max-h-[85vh] flex flex-col">
      <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
        <div>
          <h3 class="text-lg font-black text-slate-900" id="asExamTitle">Answersheet Review</h3>
          <p class="text-xs text-slate-500" id="asScoreSubtitle">Detailed breakdown of answers & explanations</p>
        </div>
        <button onclick="closeAnswersheetModal()" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto space-y-4 pr-1" id="answersheetContent">
        <!-- Rendered via JavaScript -->
      </div>
    </div>
  </div>

  <script src="/safeexam/assets/js/main.js"></script>
  <script>
    lucide.createIcons();

    // Mobile Sidebar Drawer
    function toggleMobileSidebar() {
      const drawer = document.getElementById('sidebarDrawer');
      const backdrop = document.getElementById('sidebarBackdrop');
      const isOpen = !drawer.classList.contains('-translate-x-full');
      if (isOpen) {
        drawer.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
      } else {
        drawer.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
      }
    }

    // Live Clock updater
    setInterval(() => {
      const now = new Date();
      const clockEl = document.getElementById('liveClock');
      if (clockEl) {
        clockEl.innerText = now.toLocaleTimeString();
      }
    }, 1000);

    // Exam search filter
    function filterExams() {
      const query = document.getElementById('examSearchInput').value.toLowerCase();
      const cards = document.querySelectorAll('.exam-card');
      cards.forEach(card => {
        const title = card.getAttribute('data-title');
        card.style.display = title.includes(query) ? '' : 'none';
      });
    }

    // Dual tab switcher in Events tab
    function switchEventSubTab(tab) {
      const btnAll = document.getElementById('btnAllEvents');
      const btnReg = document.getElementById('btnRegEvents');
      const cAll = document.getElementById('allEventsContainer');
      const cReg = document.getElementById('regEventsContainer');

      if (tab === 'all') {
        btnAll.className = 'px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold transition-all shadow-sm';
        btnReg.className = 'px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all';
        cAll.classList.remove('hidden');
        cReg.classList.add('hidden');
      } else {
        btnReg.className = 'px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold transition-all shadow-sm';
        btnAll.className = 'px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all';
        cReg.classList.remove('hidden');
        cAll.classList.add('hidden');
      }
    }

    // Copy to clipboard helper
    function copyToClipboard(text) {
      navigator.clipboard.writeText(text);
      customAlert({
        title: 'Copied to Clipboard',
        message: 'Passcode ' + text + ' copied successfully.',
        type: 'success'
      });
    }

    // Exam Password Verification Logic
    let currentSelectedExamId = null;
    function promptExamPassword(examId, examTitle) {
      const blockKey = 'exam_block_' + examId;
      const blockedUntil = localStorage.getItem(blockKey);
      if (blockedUntil && new Date().getTime() < parseInt(blockedUntil)) {
        const mins = Math.ceil((parseInt(blockedUntil) - new Date().getTime()) / 60000);
        customAlert({
          title: 'Access Blocked',
          message: 'You have entered an incorrect password 3 times. Please try again in ' + mins + ' minutes.',
          type: 'error'
        });
        return;
      }

      currentSelectedExamId = examId;
      document.getElementById('modalExamTitle').innerText = examTitle;
      document.getElementById('examPasswordInput').value = '';
      document.getElementById('passwordModalAlert').classList.add('hidden');
      document.getElementById('passwordModal').classList.remove('hidden');
      document.getElementById('examPasswordInput').focus();
    }

    function closePasswordModal() {
      document.getElementById('passwordModal').classList.add('hidden');
    }

    async function submitExamPassword() {
      const pwd = document.getElementById('examPasswordInput').value.trim();
      const alertBox = document.getElementById('passwordModalAlert');
      const btn = document.getElementById('btnSubmitPassword');

      if (!pwd) {
        alertBox.innerText = 'Please enter the examination passcode.';
        alertBox.classList.remove('hidden');
        return;
      }

      btn.disabled = true;
      btn.innerText = 'Verifying Passcode...';

      try {
        const formData = new FormData();
        formData.append('exam_id', currentSelectedExamId);
        formData.append('password', pwd);

        const res = await fetch('/safeexam/api/verify_password.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          localStorage.removeItem('exam_attempts_' + currentSelectedExamId);
          closePasswordModal();
          // Open examination in secure fullscreen environment
          window.location.href = '/safeexam/student/exam.php?id=' + currentSelectedExamId;
        } else {
          const attemptKey = 'exam_attempts_' + currentSelectedExamId;
          let attempts = parseInt(localStorage.getItem(attemptKey) || '0') + 1;
          localStorage.setItem(attemptKey, attempts);

          if (attempts >= 3) {
            const blockUntil = new Date().getTime() + (10 * 60000);
            localStorage.setItem('exam_block_' + currentSelectedExamId, blockUntil.toString());
            closePasswordModal();
            customAlert({
              title: 'Locked Out',
              message: 'Maximum password attempts exceeded. You are locked out for 10 minutes.',
              type: 'error'
            });
          } else {
            alertBox.innerText = data.message + ' (' + (3 - attempts) + ' attempt(s) remaining)';
            alertBox.classList.remove('hidden');
          }
        }
      } catch (e) {
        alertBox.innerText = 'Server communication error. Please try again.';
        alertBox.classList.remove('hidden');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i><span>Verify Passcode & Enter Exam</span>';
        lucide.createIcons();
      }
    }

    // Answersheet Review API loader
    async function viewAnswersheet(examId) {
      try {
        const res = await fetch('/safeexam/api/get_answersheet.php?exam_id=' + examId);
        const data = await res.json();
        if (!data.success) {
          customAlert({ title: 'Notice', message: data.message || 'Answersheet not available.', type: 'info' });
          return;
        }

        document.getElementById('asExamTitle').innerText = data.exam.title;
        document.getElementById('asScoreSubtitle').innerText = 'Final Score: ' + data.score + ' / ' + data.total_marks + ' (' + data.percentage + '%)';

        let html = '';
        data.questions.forEach((q, idx) => {
          const isCorrect = q.is_correct;
          const bgBorder = isCorrect ? 'border-emerald-200 bg-emerald-50/40' : 'border-rose-200 bg-rose-50/40';
          const icon = isCorrect ? 'check-circle-2 text-emerald-600' : 'x-circle text-rose-600';

          html += `
            <div class="p-4 rounded-2xl border ${bgBorder} space-y-2">
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-2">
                  <span class="font-black text-xs text-slate-400">Q${idx + 1}.</span>
                  <p class="font-bold text-xs text-slate-900">${q.question_text}</p>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-lg ${isCorrect ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">
                  ${q.marks_awarded} / ${q.max_marks} Marks
                </span>
              </div>
              <div class="text-[11px] space-y-1 pt-1 border-t border-slate-200/60">
                <div class="text-slate-600">Your Answer: <strong class="${isCorrect ? 'text-emerald-700' : 'text-rose-700'}">${q.student_answer || 'No answer submitted'}</strong></div>
                ${!isCorrect && q.correct_answer ? `<div class="text-emerald-700">Correct Answer: <strong>${q.correct_answer}</strong></div>` : ''}
                ${q.explanation ? `<p class="text-slate-500 italic mt-1 text-[10px]">Explanation: ${q.explanation}</p>` : ''}
              </div>
            </div>
          `;
        });

        document.getElementById('answersheetContent').innerHTML = html;
        document.getElementById('answersheetModal').classList.remove('hidden');
      } catch (e) {
        customAlert({ title: 'Error', message: 'Failed to retrieve answersheet.', type: 'error' });
      }
    }

    function closeAnswersheetModal() {
      document.getElementById('answersheetModal').classList.add('hidden');
    }

    // Register for Event API
    async function registerForEvent(eventId) {
      customConfirm({
        title: 'Confirm Event Registration',
        message: 'Do you want to register for this institutional event?',
        confirmText: 'Register Now',
        type: 'info',
        onConfirm: async () => {
          try {
            const formData = new FormData();
            formData.append('event_id', eventId);
            const res = await fetch('/safeexam/api/get_event_registrations.php?action=register', {
              method: 'POST',
              body: formData
            });
            const d = await res.json();
            if (d.success) {
              customAlert({
                title: 'Registration Successful',
                message: 'You have registered for the event. Check the Registered Events tab.',
                type: 'success',
                onClose: () => window.location.reload()
              });
            } else {
              customAlert({ title: 'Notice', message: d.message || 'Already registered.', type: 'info' });
            }
          } catch (e) {
            window.location.reload();
          }
        }
      });
    }

    function runAiDiagnostic() {
      customAlert({
        title: 'Agent ELI Diagnostic Complete',
        message: 'Cognitive performance vectors synchronized. Recommendations are updated based on your completed evaluations.',
        type: 'success'
      });
    }
  </script>
</body>
</html>
