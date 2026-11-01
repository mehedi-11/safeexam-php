<?php
// teacher/dashboard.php — SafeExam.AI Full Teacher Console with Authentic Sidebar & All Features
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('teacher');
$user = getCurrentUser();
$teacherId = $user['id'];

$validTabs = ['dashboard', 'add_exam', 'questions', 'exam_results', 'events', 'proctoring', 'profile'];
$activeTab = $_GET['tab'] ?? 'dashboard';
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'dashboard';
}

$feedback = '';
$feedbackType = 'success';

// Handle Exam / Event / Question actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Create Exam
    if ($action === 'create_exam') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 60);
        $totalMarks = (int)($_POST['total_marks'] ?? 100);
        $passingMarks = (int)($_POST['passing_marks'] ?? 40);
        $password = trim($_POST['password'] ?? '');

        if ($title && $duration > 0 && $totalMarks > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO exams (teacher_id, title, description, course_name, exam_date, duration_minutes, total_marks, exam_password, is_live, results_published, created_at)
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, 0, 0, NOW())
            ");
            $stmt->execute([$teacherId, $title, $description, $subject, $duration, $totalMarks, $password]);
            $feedback = 'Exam created successfully! You can now add questions.';
        } else {
            $feedback = 'Please provide valid exam parameters.';
            $feedbackType = 'error';
        }
    }

    // 2. Toggle Live
    elseif ($action === 'toggle_live') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $isLive = (int)($_POST['is_live'] ?? 0);
        $stmt = $pdo->prepare("UPDATE exams SET is_live = ? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$isLive, $examId, $teacherId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // 3. Toggle Results
    elseif ($action === 'toggle_results') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $resPub = (int)($_POST['results_published'] ?? 0);
        $stmt = $pdo->prepare("UPDATE exams SET results_published = ? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$resPub, $examId, $teacherId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // 4. Delete Single Exam
    elseif ($action === 'delete_exam') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        // Verify not live
        $chk = $pdo->prepare("SELECT is_live FROM exams WHERE id = ? AND teacher_id = ?");
        $chk->execute([$examId, $teacherId]);
        $row = $chk->fetch();
        if ($row && $row['is_live']) {
            $feedback = 'Live exams cannot be deleted. Deactivate live status first.';
            $feedbackType = 'error';
        } else {
            $pdo->prepare("DELETE FROM student_answers WHERE student_exam_id IN (SELECT id FROM student_exams WHERE exam_id = ?)")->execute([$examId]);
            $pdo->prepare("DELETE FROM proctoring_logs WHERE student_exam_id IN (SELECT id FROM student_exams WHERE exam_id = ?)")->execute([$examId]);
            $pdo->prepare("DELETE FROM student_exams WHERE exam_id = ?")->execute([$examId]);
            $pdo->prepare("DELETE FROM exam_questions WHERE exam_id = ?")->execute([$examId]);
            $pdo->prepare("DELETE FROM exams WHERE id = ? AND teacher_id = ?")->execute([$examId, $teacherId]);
            $feedback = 'Exam and associated assets deleted successfully.';
        }
    }

    // 5. Bulk Delete Exams
    elseif ($action === 'bulk_delete_exams') {
        $ids = json_decode($_POST['exam_ids'] ?? '[]', true);
        if (is_array($ids) && count($ids) > 0) {
            $inQ = implode(',', array_map('intval', $ids));
            // Ensure no live exam is deleted
            $stmt = $pdo->query("SELECT id FROM exams WHERE id IN ($inQ) AND teacher_id = $teacherId AND is_live = 0");
            $deletableIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($deletableIds) > 0) {
                $delIn = implode(',', $deletableIds);
                $pdo->query("DELETE FROM student_answers WHERE student_exam_id IN (SELECT id FROM student_exams WHERE exam_id IN ($delIn))");
                $pdo->query("DELETE FROM proctoring_logs WHERE student_exam_id IN (SELECT id FROM student_exams WHERE exam_id IN ($delIn))");
                $pdo->query("DELETE FROM student_exams WHERE exam_id IN ($delIn)");
                $pdo->query("DELETE FROM exam_questions WHERE exam_id IN ($delIn)");
                $pdo->query("DELETE FROM exams WHERE id IN ($delIn) AND teacher_id = $teacherId");
                echo json_encode(['success' => true, 'count' => count($deletableIds)]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'No eligible non-live exams were selected.']);
        exit;
    }

    // 6. Add Question
    elseif ($action === 'add_question') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $qType = $_POST['question_type'] ?? 'mcq';
        $qText = trim($_POST['question_text'] ?? '');
        $marks = (int)($_POST['marks'] ?? 1);
        $optA = trim($_POST['option_a'] ?? '');
        $optB = trim($_POST['option_b'] ?? '');
        $optC = trim($_POST['option_c'] ?? '');
        $optD = trim($_POST['option_d'] ?? '');
        $correct = trim($_POST['correct_answer'] ?? '');
        $explanation = trim($_POST['explanation'] ?? '');

        if ($examId && $qText) {
            $stmt = $pdo->prepare("
                INSERT INTO exam_questions (exam_id, question_type, question_text, marks, option_a, option_b, option_c, option_d, correct_option, explanation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$examId, $qType, $qText, $marks, $optA, $optB, $optC, $optD, $correct, $explanation]);
            $feedback = 'Question added to examination successfully!';
        } else {
            $feedback = 'Please provide valid question text.';
            $feedbackType = 'error';
        }
    }

    // 7. Create Event
    elseif ($action === 'create_event') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $eventDate = $_POST['start_time'] ?? date('Y-m-d H:i:s');
        $secretCode = trim($_POST['secret_code'] ?? '');

        if ($title) {
            $stmt = $pdo->prepare("
                INSERT INTO events (title, description, event_date, secret_code, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $desc, $eventDate, $secretCode, $teacherId]);
            $feedback = 'Event announced successfully!';
        }
    }

    // 8. Grade Written Question
    elseif ($action === 'grade_answer') {
        $answerId = (int)($_POST['answer_id'] ?? 0);
        $awardedMarks = (float)($_POST['marks_awarded'] ?? 0);
        $studentExamId = (int)($_POST['student_exam_id'] ?? 0);

        $pdo->prepare("UPDATE student_answers SET marks_obtained = ?, is_correct = 1 WHERE id = ?")->execute([$awardedMarks, $answerId]);

        // Recalculate total exam score
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(marks_obtained), 0) FROM student_answers WHERE student_exam_id = ?");
        $sumStmt->execute([$studentExamId]);
        $newTotal = $sumStmt->fetchColumn();

        $pdo->prepare("UPDATE student_exams SET total_score = ? WHERE id = ?")->execute([$newTotal, $studentExamId]);

        echo json_encode(['success' => true, 'new_score' => $newTotal]);
        exit;
    }
}

// Fetch Teacher's Exams
$examsStmt = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as question_count,
           (SELECT COUNT(*) FROM student_exams WHERE exam_id = e.id) as total_submissions,
           (SELECT COUNT(*) FROM student_exams WHERE exam_id = e.id AND status = 'in_progress') as active_candidates
    FROM exams e
    WHERE e.teacher_id = ?
    ORDER BY e.created_at DESC
");
$examsStmt->execute([$teacherId]);
$teacherExams = $examsStmt->fetchAll();

// KPI Calculations
$totalExams = count($teacherExams);
$totalQuestions = 0;
$totalSubmissions = 0;
$totalActiveCandidates = 0;
foreach ($teacherExams as $te) {
    $totalQuestions += $te['question_count'];
    $totalSubmissions += $te['total_submissions'];
    $totalActiveCandidates += $te['active_candidates'];
}

// Fetch Submissions for Grading tab
$submissionsStmt = $pdo->prepare("
    SELECT se.*, se.total_score as score, e.title as exam_title, e.total_marks as max_marks, u.name as student_name, u.student_id_str
    FROM student_exams se
    JOIN exams e ON e.id = se.exam_id
    JOIN users u ON u.id = se.student_id
    WHERE e.teacher_id = ?
    ORDER BY se.start_time DESC
");
$submissionsStmt->execute([$teacherId]);
$submissions = $submissionsStmt->fetchAll();

// Fetch Events for Events tab
$eventsStmt = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registered_count
    FROM events e
    WHERE e.created_by = ?
    ORDER BY e.event_date DESC
");
$eventsStmt->execute([$teacherId]);
$teacherEvents = $eventsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Console — SafeExam.AI</title>
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
        <i data-lucide="graduation-cap" class="w-4 h-4"></i>
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
            <i data-lucide="graduation-cap" class="w-5 h-5"></i>
          </div>
          <div>
            <span class="font-black text-lg tracking-tight text-slate-900">
              SExam<span class="text-rose-500">.AI</span>
            </span>
            <span class="text-[10px] text-slate-400 block font-bold tracking-widest uppercase -mt-0.5">Teacher Console</span>
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
            ['id' => 'add_exam', 'label' => 'Manage Exams', 'icon' => 'file-text'],
            ['id' => 'exam_results', 'label' => 'Exam Results & Grading', 'icon' => 'award'],
            ['id' => 'events', 'label' => 'Events Manager', 'icon' => 'calendar'],
            ['id' => 'proctoring', 'label' => 'Live Proctoring', 'icon' => 'camera'],
            ['id' => 'profile', 'label' => 'Faculty Profile', 'icon' => 'user'],
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
            <?php if ($item['id'] === 'proctoring' && $totalActiveCandidates > 0): ?>
              <span class="ml-auto bg-rose-500 text-white text-[10px] font-black px-1.5 py-0.2 rounded-full animate-pulse">
                <?= $totalActiveCandidates ?> Live
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
            <p class="text-[10px] text-slate-400 font-semibold truncate"><?= htmlspecialchars($user['email']) ?></p>
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
          if ($activeTab === 'add_exam') echo 'Manage Institutional Examinations';
          elseif ($activeTab === 'exam_results') echo 'Student Submissions & Subjective Grading';
          elseif ($activeTab === 'events') echo 'Events & Hackathons Management';
          elseif ($activeTab === 'proctoring') echo 'Live AI Proctoring Telemetry Feed';
          elseif ($activeTab === 'profile') echo 'Faculty Account Settings';
          else echo 'Teacher Console Overview';
        ?>
      </h2>
      <div class="flex items-center gap-3">
        <span class="text-xs font-bold px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1.5">
          <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
          <span>Authorized Faculty</span>
        </span>
      </div>
    </div>

    <!-- Alert / Toast Messages -->
    <?php if ($feedback): ?>
      <div class="p-4 rounded-2xl text-xs font-semibold mb-6 flex items-center gap-2 animate-fade-in shadow-sm <?= $feedbackType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' ?>">
        <i data-lucide="<?= $feedbackType === 'success' ? 'check-circle-2' : 'alert-circle' ?>" class="w-4 h-4"></i>
        <span><?= htmlspecialchars($feedback) ?></span>
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
            <p class="text-slate-300 mt-2 text-xs md:text-sm font-medium">Faculty assessment console active. Computer vision AI models operational.</p>
            <div class="mt-4 flex flex-wrap items-center gap-2.5">
              <span class="inline-flex items-center gap-1.5 bg-white/10 px-3 py-1.5 rounded-xl text-[11px] font-semibold text-emerald-400 border border-white/10">
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Faculty Session Verified
              </span>
              <span class="inline-flex items-center gap-1.5 bg-white/10 px-3 py-1.5 rounded-xl text-[11px] font-semibold text-rose-400 border border-white/10">
                <i data-lucide="activity" class="w-3.5 h-3.5"></i> <?= $totalActiveCandidates ?> Candidates Online
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
        <!-- 4 KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
              <i data-lucide="file-text" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total Exams</p>
              <h4 class="text-2xl font-black text-slate-900"><?= $totalExams ?></h4>
            </div>
          </div>

          <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
              <i data-lucide="users" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Active Candidates</p>
              <h4 class="text-2xl font-black text-slate-900"><?= $totalActiveCandidates ?></h4>
            </div>
          </div>

          <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
              <i data-lucide="help-circle" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Question Bank</p>
              <h4 class="text-2xl font-black text-slate-900"><?= $totalQuestions ?></h4>
            </div>
          </div>

          <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
              <i data-lucide="check-circle" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Submissions</p>
              <h4 class="text-2xl font-black text-slate-900"><?= $totalSubmissions ?></h4>
            </div>
          </div>
        </div>

        <!-- Quick Action Shortcuts -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <button onclick="openCreateExamModal()" class="p-6 bg-slate-900 hover:bg-black text-white rounded-3xl shadow-md transition-all text-left flex flex-col justify-between group">
            <div class="w-10 h-10 rounded-2xl bg-white/10 flex items-center justify-center text-rose-400 mb-4 group-hover:scale-105 transition-transform">
              <i data-lucide="plus-circle" class="w-5 h-5"></i>
            </div>
            <div>
              <h4 class="font-bold text-sm">Create New Examination</h4>
              <p class="text-xs text-slate-400 mt-1">Configure time limit, marks, and passcode</p>
            </div>
          </button>

          <a href="?tab=proctoring" class="p-6 bg-white hover:bg-slate-50 border border-slate-200 rounded-3xl shadow-sm transition-all text-left flex flex-col justify-between group">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-600 mb-4 group-hover:scale-105 transition-transform">
              <i data-lucide="camera" class="w-5 h-5"></i>
            </div>
            <div>
              <h4 class="font-bold text-sm text-slate-900">Live AI Proctoring Feed</h4>
              <p class="text-xs text-slate-500 mt-1">Audit real-time demerit points and camera logs</p>
            </div>
          </a>

          <a href="?tab=exam_results" class="p-6 bg-white hover:bg-slate-50 border border-slate-200 rounded-3xl shadow-sm transition-all text-left flex flex-col justify-between group">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600 mb-4 group-hover:scale-105 transition-transform">
              <i data-lucide="award" class="w-5 h-5"></i>
            </div>
            <div>
              <h4 class="font-bold text-sm text-slate-900">Grade Submissions</h4>
              <p class="text-xs text-slate-500 mt-1">Review subjective answers and award marks</p>
            </div>
          </a>
        </div>

        <!-- Recent Examinations Table -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-base font-black text-slate-900 tracking-tight">Recent Examinations</h3>
            <a href="?tab=add_exam" class="text-xs font-bold text-rose-600 hover:underline">Manage All</a>
          </div>

          <?php if (count($teacherExams) === 0): ?>
            <p class="text-xs text-slate-400 py-6 text-center">No examinations created yet. Click "Create New Examination" to start.</p>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="border-b border-slate-100 text-[11px] font-bold uppercase text-slate-400">
                    <th class="py-3 px-3">Title</th>
                    <th class="py-3 px-3">Subject</th>
                    <th class="py-3 px-3 text-center">Questions</th>
                    <th class="py-3 px-3 text-center">Submissions</th>
                    <th class="py-3 px-3 text-center">Live Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach (array_slice($teacherExams, 0, 5) as $ex): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($ex['title']) ?></td>
                      <td class="py-3 px-3 text-slate-500"><?= htmlspecialchars($ex['subject'] ?: 'General') ?></td>
                      <td class="py-3 px-3 text-center font-bold text-slate-700"><?= $ex['question_count'] ?></td>
                      <td class="py-3 px-3 text-center font-bold text-slate-700"><?= $ex['total_submissions'] ?></td>
                      <td class="py-3 px-3 text-center">
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $ex['is_live'] ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-slate-100 text-slate-500' ?>">
                          <?= $ex['is_live'] ? '● Live' : 'Offline' ?>
                        </span>
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

    <!-- TAB 2: MANAGE EXAMS (with Select-to-Delete Bulk Delete & Live Toggles) -->
    <?php if ($activeTab === 'add_exam'): ?>
      <div class="space-y-6 animate-fade-in">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
              <h3 class="text-lg font-black text-slate-900 tracking-tight">Institutional Examinations</h3>
              <p class="text-xs text-slate-500 mt-0.5">Manage live states, scoring parameters, and questions</p>
            </div>
            <button onclick="openCreateExamModal()" class="px-5 py-2.5 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-2">
              <i data-lucide="plus" class="w-4 h-4 text-rose-400"></i>
              <span>Create New Exam</span>
            </button>
          </div>

          <!-- Bulk Action Bar -->
          <div id="bulkActionBar" class="hidden mb-4 p-3.5 bg-slate-900 text-white rounded-2xl flex items-center justify-between animate-fade-in shadow-md">
            <span class="text-xs font-bold" id="bulkSelectedCount">0 exams selected</span>
            <button onclick="performBulkDeleteExams()" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
              <span>Delete Selected</span>
            </button>
          </div>

          <!-- Exams Table with Select-to-Delete Checkboxes -->
          <?php if (count($teacherExams) === 0): ?>
            <p class="text-xs text-slate-400 py-12 text-center font-medium">No examinations created yet. Click "Create New Exam" above.</p>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-3 w-10">
                      <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAllExams()" class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500 cursor-pointer">
                    </th>
                    <th class="py-3 px-3">Title & Subject</th>
                    <th class="py-3 px-3 text-center">Duration</th>
                    <th class="py-3 px-3 text-center">Marks</th>
                    <th class="py-3 px-3 text-center">Questions</th>
                    <th class="py-3 px-3 text-center">Live Toggle</th>
                    <th class="py-3 px-3 text-center">Results Toggle</th>
                    <th class="py-3 px-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($teacherExams as $ex): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-3 px-3">
                        <input
                          type="checkbox"
                          class="exam-row-cb w-4 h-4 rounded text-rose-600 focus:ring-rose-500 cursor-pointer"
                          value="<?= $ex['id'] ?>"
                          data-islive="<?= $ex['is_live'] ? '1' : '0' ?>"
                          onchange="updateBulkBarState()"
                        >
                      </td>
                      <td class="py-3 px-3">
                        <span class="font-bold text-slate-900 block"><?= htmlspecialchars($ex['title']) ?></span>
                        <span class="text-[11px] text-slate-500"><?= htmlspecialchars($ex['subject'] ?: 'General') ?> &bull; Passcode: <?= htmlspecialchars($ex['password'] ?: 'None') ?></span>
                      </td>
                      <td class="py-3 px-3 text-center text-slate-600 font-medium"><?= $ex['duration_minutes'] ?>m</td>
                      <td class="py-3 px-3 text-center font-mono font-bold text-slate-800"><?= $ex['total_marks'] ?></td>
                      <td class="py-3 px-3 text-center">
                        <a href="?tab=questions&exam_id=<?= $ex['id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-colors">
                          <i data-lucide="plus" class="w-3 h-3"></i> <?= $ex['question_count'] ?> Qs
                        </a>
                      </td>
                      <td class="py-3 px-3 text-center">
                        <label class="inline-flex items-center cursor-pointer">
                          <input type="checkbox" onchange="toggleExamLive(<?= $ex['id'] ?>, this.checked)" <?= $ex['is_live'] ? 'checked' : '' ?> class="sr-only peer">
                          <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                      </td>
                      <td class="py-3 px-3 text-center">
                        <label class="inline-flex items-center cursor-pointer">
                          <input type="checkbox" onchange="toggleExamResults(<?= $ex['id'] ?>, this.checked)" <?= $ex['results_published'] ? 'checked' : '' ?> class="sr-only peer">
                          <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                      </td>
                      <td class="py-3 px-3 text-right space-x-1">
                        <button onclick="deleteSingleExam(<?= $ex['id'] ?>, '<?= htmlspecialchars(addslashes($ex['title'])) ?>', <?= $ex['is_live'] ? 'true' : 'false' ?>)" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition-colors" title="Delete Exam">
                          <i data-lucide="trash-2" class="w-4 h-4"></i>
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

    <!-- TAB 3: QUESTION BANK -->
    <?php if ($activeTab === 'questions'): 
      $selectedExamId = (int)($_GET['exam_id'] ?? ($teacherExams[0]['id'] ?? 0));
      $questionsStmt = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY id ASC");
      $questionsStmt->execute([$selectedExamId]);
      $examQuestions = $questionsStmt->fetchAll();
    ?>
      <div class="space-y-6 animate-fade-in">
        <!-- Exam Switcher -->
        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Exam:</span>
            <select onchange="window.location.href='?tab=questions&exam_id='+this.value" class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none">
              <?php foreach ($teacherExams as $te): ?>
                <option value="<?= $te['id'] ?>" <?= $te['id'] == $selectedExamId ? 'selected' : '' ?>>
                  <?= htmlspecialchars($te['title']) ?> (<?= $te['question_count'] ?> Qs)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button onclick="openAddQuestionModal()" class="px-4 py-2 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-2">
            <i data-lucide="plus" class="w-3.5 h-3.5 text-rose-400"></i>
            <span>Add Question</span>
          </button>
        </div>

        <!-- Questions List -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm space-y-4">
          <h3 class="text-base font-black text-slate-900 tracking-tight">Question Bank Items (<?= count($examQuestions) ?>)</h3>
          <?php if (count($examQuestions) === 0): ?>
            <p class="text-xs text-slate-400 py-10 text-center font-medium">No questions added yet for this examination. Click "Add Question" above.</p>
          <?php else: ?>
            <div class="space-y-4">
              <?php foreach ($examQuestions as $idx => $q): ?>
                <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-3">
                  <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-2.5">
                      <span class="font-black text-xs text-slate-400">Q<?= $idx + 1 ?>.</span>
                      <div>
                        <p class="font-bold text-xs text-slate-900"><?= htmlspecialchars($q['question_text']) ?></p>
                        <span class="inline-block mt-1 text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-700">
                          Type: <?= strtoupper($q['question_type']) ?> &bull; Marks: <?= $q['marks'] ?>
                        </span>
                      </div>
                    </div>
                  </div>

                  <?php if ($q['question_type'] === 'mcq'): ?>
                    <div class="grid grid-cols-2 gap-2 text-[11px] pt-2">
                      <div class="p-2 rounded-lg <?= $q['correct_answer'] === 'A' ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-white text-slate-600' ?>">A. <?= htmlspecialchars($q['option_a']) ?></div>
                      <div class="p-2 rounded-lg <?= $q['correct_answer'] === 'B' ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-white text-slate-600' ?>">B. <?= htmlspecialchars($q['option_b']) ?></div>
                      <div class="p-2 rounded-lg <?= $q['correct_answer'] === 'C' ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-white text-slate-600' ?>">C. <?= htmlspecialchars($q['option_c']) ?></div>
                      <div class="p-2 rounded-lg <?= $q['correct_answer'] === 'D' ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-white text-slate-600' ?>">D. <?= htmlspecialchars($q['option_d']) ?></div>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 4: EXAM RESULTS & SUBJECTIVE GRADING -->
    <?php if ($activeTab === 'exam_results'): ?>
      <div class="space-y-6 animate-fade-in">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h3 class="text-lg font-black text-slate-900 tracking-tight">Candidate Submissions & Grading</h3>
              <p class="text-xs text-slate-500 mt-0.5">Evaluate written responses and publish graded results</p>
            </div>
            <span class="text-xs font-bold text-slate-400"><?= count($submissions) ?> Submissions</span>
          </div>

          <?php if (count($submissions) === 0): ?>
            <p class="text-xs text-slate-400 py-12 text-center font-medium">No candidate submissions recorded yet.</p>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-3">Student Name</th>
                    <th class="py-3 px-3">Student ID</th>
                    <th class="py-3 px-3">Exam Title</th>
                    <th class="py-3 px-3 text-center">Score</th>
                    <th class="py-3 px-3 text-center">Status</th>
                    <th class="py-3 px-3 text-right">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <?php foreach ($submissions as $sub): 
                    $max = $sub['max_marks'] > 0 ? $sub['max_marks'] : 100;
                    $pct = round(($sub['score'] / $max) * 100, 1);
                  ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($sub['student_name']) ?></td>
                      <td class="py-3 px-3 font-mono text-slate-500"><?= htmlspecialchars($sub['student_id_str'] ?: 'N/A') ?></td>
                      <td class="py-3 px-3 text-slate-700"><?= htmlspecialchars($sub['exam_title']) ?></td>
                      <td class="py-3 px-3 text-center font-mono font-bold text-slate-800">
                        <?= $sub['score'] ?> / <?= $sub['max_marks'] ?>
                        <span class="text-[10px] font-normal text-slate-400 ml-1">(<?= $pct ?>%)</span>
                      </td>
                      <td class="py-3 px-3 text-center">
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $sub['status'] === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                          <?= htmlspecialchars($sub['status']) ?>
                        </span>
                      </td>
                      <td class="py-3 px-3 text-right">
                        <button onclick="openGradingModal(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['student_name'])) ?>')" class="px-3 py-1.5 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                          Grade Written Qs &rarr;
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

    <!-- TAB 5: EVENTS MANAGER -->
    <?php if ($activeTab === 'events'): ?>
      <div class="space-y-6 animate-fade-in">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h3 class="text-lg font-black text-slate-900 tracking-tight">Institutional Events & Contests</h3>
              <p class="text-xs text-slate-500 mt-0.5">Organize hackathons, webinars, and scheduled sessions</p>
            </div>
            <button onclick="openCreateEventModal()" class="px-4 py-2 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-2">
              <i data-lucide="plus" class="w-3.5 h-3.5 text-rose-400"></i>
              <span>Create Event</span>
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($teacherEvents as $ev): ?>
              <div class="p-6 rounded-3xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between">
                <div>
                  <div class="flex items-center justify-between mb-3">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700">
                      Active Event
                    </span>
                    <span class="text-xs text-slate-400 font-medium"><?= date('M d, Y', strtotime($ev['event_date'])) ?></span>
                  </div>
                  <h4 class="font-black text-base text-slate-900 mb-1"><?= htmlspecialchars($ev['title']) ?></h4>
                  <p class="text-xs text-slate-500 mb-4"><?= htmlspecialchars($ev['description']) ?></p>
                  
                  <div class="p-3 bg-white border border-slate-200 rounded-2xl text-[11px] space-y-1 mb-4">
                    <div>Passcode: <strong class="font-mono text-slate-900"><?= htmlspecialchars($ev['secret_code'] ?: 'None') ?></strong></div>
                    <div>Registered Candidates: <strong class="text-rose-600"><?= $ev['registered_count'] ?> Students</strong></div>
                  </div>
                </div>

                <div class="flex items-center gap-2 pt-3 border-t border-slate-200">
                  <button onclick="viewEventAttendees(<?= $ev['id'] ?>)" class="flex-1 py-2 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-bold transition-all text-center">
                    View Attendees (<?= $ev['registered_count'] ?>)
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 6: PROCTORING MONITOR -->
    <?php if ($activeTab === 'proctoring'): ?>
      <div class="space-y-6 animate-fade-in">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 rounded-3xl p-6 md:p-8 text-white shadow-md flex items-center justify-between border border-slate-800">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-rose-500/20 text-rose-300 text-xs font-bold mb-3">
              <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
              Live AI Edge Telemetry
            </div>
            <h2 class="text-2xl font-black">Active Proctoring Incident Stream</h2>
            <p class="text-xs text-slate-300 mt-1">Automatic real-time polling every 5 seconds. Violations penalized instantly.</p>
          </div>
          <button onclick="refreshProctorLogs()" class="px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-2">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            <span>Refresh Now</span>
          </button>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-base font-black text-slate-900 tracking-tight">Recent Violation Telemetry Feed</h3>
            <span class="text-xs text-slate-400 font-semibold" id="lastPollTime">Polling active</span>
          </div>

          <div id="liveLogsFeed" class="space-y-3">
            <div class="text-center py-10 text-xs text-slate-400">Loading live telemetry stream...</div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 7: PROFILE -->
    <?php if ($activeTab === 'profile'): ?>
      <div class="max-w-3xl mx-auto space-y-6 animate-fade-in">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-100">
            <div class="w-16 h-16 rounded-3xl bg-slate-900 text-white flex items-center justify-center font-black text-xl shadow-md">
              <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div>
              <h3 class="text-xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($user['name']) ?></h3>
              <p class="text-xs text-slate-500 mt-0.5">Faculty Email: <strong class="text-slate-800"><?= htmlspecialchars($user['email']) ?></strong></p>
              <span class="inline-block mt-2 px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-[10px] font-bold uppercase tracking-wider">
                Status: <?= htmlspecialchars($user['status']) ?>
              </span>
            </div>
          </div>

          <p class="text-xs text-slate-500">To request profile credential adjustments, contact the system administrator at mehedimridul1919@gmail.com.</p>
        </div>
      </div>
    <?php endif; ?>

  </main>

  <!-- CREATE EXAM MODAL -->
  <div id="createExamModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 md:p-8 shadow-2xl border border-slate-200 animate-fade-in relative max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-5">
        <h3 class="text-lg font-black text-slate-900">Create New Examination</h3>
        <button onclick="closeCreateExamModal()" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form method="POST" action="?tab=add_exam" class="space-y-4">
        <input type="hidden" name="action" value="create_exam">

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Exam Title <span class="text-rose-500">*</span></label>
          <input type="text" name="title" required placeholder="e.g. Data Structures & Algorithms Midterm" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800">
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Subject / Department</label>
          <input type="text" name="subject" placeholder="e.g. Computer Science" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800">
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Duration (Mins)</label>
            <input type="number" name="duration_minutes" value="60" min="5" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Total Marks</label>
            <input type="number" name="total_marks" value="100" min="1" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pass Mark</label>
            <input type="number" name="passing_marks" value="40" min="1" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800">
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Exam Passcode (Optional)</label>
          <input type="text" name="password" placeholder="e.g. EXAM2026" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800">
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Syllabus / Instructions</label>
          <textarea name="description" rows="3" placeholder="Enter instructions for examinees..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800"></textarea>
        </div>

        <button type="submit" class="w-full py-3 bg-slate-900 hover:bg-black text-white font-bold text-xs rounded-xl shadow-md transition-all">
          Create Examination & Proceed to Questions &rarr;
        </button>
      </form>
    </div>
  </div>

  <!-- ADD QUESTION MODAL -->
  <div id="addQuestionModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 md:p-8 shadow-2xl border border-slate-200 animate-fade-in relative max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-5">
        <h3 class="text-lg font-black text-slate-900">Add Question</h3>
        <button onclick="closeAddQuestionModal()" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form method="POST" action="?tab=questions&exam_id=<?= $selectedExamId ?? 0 ?>" class="space-y-4">
        <input type="hidden" name="action" value="add_question">
        <input type="hidden" name="exam_id" value="<?= $selectedExamId ?? 0 ?>">

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Question Type</label>
          <select name="question_type" id="qTypeSelect" onchange="toggleQTypeFields(this.value)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
            <option value="mcq">Multiple Choice Question (MCQ)</option>
            <option value="written">Written / Descriptive Response</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Question Text <span class="text-rose-500">*</span></label>
          <textarea name="question_text" rows="3" required placeholder="Enter the question text..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800"></textarea>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Marks Allocated</label>
          <input type="number" name="marks" value="2" min="1" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        </div>

        <!-- MCQ Options Section -->
        <div id="mcqOptionsSection" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1">Option A</label>
              <input type="text" name="option_a" placeholder="Choice A" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1">Option B</label>
              <input type="text" name="option_b" placeholder="Choice B" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1">Option C</label>
              <input type="text" name="option_c" placeholder="Choice C" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1">Option D</label>
              <input type="text" name="option_d" placeholder="Choice D" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Correct Option</label>
            <select name="correct_answer" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
              <option value="A">Option A</option>
              <option value="B">Option B</option>
              <option value="C">Option C</option>
              <option value="D">Option D</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Explanation (Optional)</label>
          <input type="text" name="explanation" placeholder="Shown to student on answersheet review" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        </div>

        <button type="submit" class="w-full py-3 bg-slate-900 hover:bg-black text-white font-bold text-xs rounded-xl shadow-md transition-all">
          Save Question to Bank
        </button>
      </form>
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

    // Modal helpers
    function openCreateExamModal() {
      document.getElementById('createExamModal').classList.remove('hidden');
    }
    function closeCreateExamModal() {
      document.getElementById('createExamModal').classList.add('hidden');
    }

    function openAddQuestionModal() {
      document.getElementById('addQuestionModal').classList.remove('hidden');
    }
    function closeAddQuestionModal() {
      document.getElementById('addQuestionModal').classList.add('hidden');
    }

    function toggleQTypeFields(val) {
      const mcqSec = document.getElementById('mcqOptionsSection');
      if (val === 'written') {
        mcqSec.classList.add('hidden');
      } else {
        mcqSec.classList.remove('hidden');
      }
    }

    // Live and Results toggles
    async function toggleExamLive(examId, isLive) {
      const fd = new FormData();
      fd.append('action', 'toggle_live');
      fd.append('exam_id', examId);
      fd.append('is_live', isLive ? '1' : '0');
      await fetch('?tab=add_exam', { method: 'POST', body: fd });
    }

    async function toggleExamResults(examId, resultsPub) {
      const fd = new FormData();
      fd.append('action', 'toggle_results');
      fd.append('exam_id', examId);
      fd.append('results_published', resultsPub ? '1' : '0');
      await fetch('?tab=add_exam', { method: 'POST', body: fd });
    }

    // Single Delete Exam with Confirmation Modal
    function deleteSingleExam(examId, examTitle, isLive) {
      if (isLive) {
        customAlert({
          title: 'Exam is Live',
          message: 'Active live examinations cannot be deleted. Deactivate live status first.',
          type: 'error'
        });
        return;
      }

      customConfirm({
        title: 'Delete Examination',
        message: 'Are you sure you want to delete "' + examTitle + '"? This will remove all associated questions and student submissions.',
        confirmText: 'Delete Exam',
        type: 'danger',
        onConfirm: async () => {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '?tab=add_exam';
          form.innerHTML = '<input type="hidden" name="action" value="delete_exam"><input type="hidden" name="exam_id" value="' + examId + '">';
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    // Select All & Bulk Delete Exams
    function toggleSelectAllExams() {
      const master = document.getElementById('selectAllCheckbox');
      const cbs = document.querySelectorAll('.exam-row-cb');
      cbs.forEach(cb => cb.checked = master.checked);
      updateBulkBarState();
    }

    function updateBulkBarState() {
      const checked = document.querySelectorAll('.exam-row-cb:checked');
      const bar = document.getElementById('bulkActionBar');
      const cnt = document.getElementById('bulkSelectedCount');
      if (checked.length > 0) {
        bar.classList.remove('hidden');
        cnt.innerText = checked.length + ' examination(s) selected';
      } else {
        bar.classList.add('hidden');
      }
    }

    function performBulkDeleteExams() {
      const checked = document.querySelectorAll('.exam-row-cb:checked');
      const ids = Array.from(checked).map(cb => parseInt(cb.value));

      customConfirm({
        title: 'Bulk Delete Exams',
        message: 'Delete ' + ids.length + ' selected examination(s)? Live exams will be protected from deletion.',
        confirmText: 'Delete Selected',
        type: 'danger',
        onConfirm: async () => {
          const fd = new FormData();
          fd.append('action', 'bulk_delete_exams');
          fd.append('exam_ids', JSON.stringify(ids));

          const res = await fetch('?tab=add_exam', { method: 'POST', body: fd });
          const d = await res.json();
          if (d.success) {
            customAlert({
              title: 'Bulk Delete Completed',
              message: d.count + ' examination(s) were successfully deleted.',
              type: 'success',
              onClose: () => window.location.reload()
            });
          } else {
            customAlert({ title: 'Notice', message: d.message || 'Error deleting exams.', type: 'error' });
          }
        }
      });
    }

    // Proctoring Polling Feed
    async function refreshProctorLogs() {
      try {
        const res = await fetch('/safeexam/api/get_live_logs.php');
        const data = await res.json();
        const container = document.getElementById('liveLogsFeed');
        if (!container) return;

        if (data.logs && data.logs.length > 0) {
          let html = '';
          data.logs.forEach(l => {
            html += `
              <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center font-bold text-xs">
                    +${l.demerit_points}
                  </div>
                  <div>
                    <h5 class="font-bold text-xs text-slate-900">${l.violation_type}</h5>
                    <p class="text-[11px] text-slate-500">${l.student_name} &bull; ${l.exam_title}</p>
                  </div>
                </div>
                <span class="text-[10px] text-slate-400 font-mono">${new Date(l.timestamp).toLocaleTimeString()}</span>
              </div>
            `;
          });
          container.innerHTML = html;
        } else {
          container.innerHTML = '<div class="text-center py-10 text-xs text-slate-400">Zero active incidents recorded. Integrity optimal.</div>';
        }
        document.getElementById('lastPollTime').innerText = 'Last updated: ' + new Date().toLocaleTimeString();
      } catch (e) {}
    }

    // Auto-poll if on proctoring tab
    if (window.location.search.includes('tab=proctoring')) {
      refreshProctorLogs();
      setInterval(refreshProctorLogs, 5000);
    }
  </script>
</body>
</html>
