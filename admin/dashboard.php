<?php
// admin/dashboard.php — SafeExam.AI Full Administrator Console with Authentic Sidebar & All Features
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');
$user = getCurrentUser();
$adminId = $user['id'];

$validTabs = ['overview', 'users', 'exams', 'proctoring', 'events', 'settings'];
$activeTab = $_GET['tab'] ?? 'overview';
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'overview';
}

$feedback = '';
$feedbackType = 'success';

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Update Faculty / Student Status
    if ($action === 'set_status') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'approved';
        if ($targetUserId) {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role != 'admin'");
            $stmt->execute([$newStatus, $targetUserId]);
            $feedback = 'User status updated successfully.';
        }
    }

    // Delete User
    elseif ($action === 'delete_user') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId) {
            // Delete associated student answers, exams, registrations
            $pdo->prepare("DELETE FROM student_answers WHERE student_exam_id IN (SELECT id FROM student_exams WHERE student_id = ?)")->execute([$targetUserId]);
            $pdo->prepare("DELETE FROM proctoring_logs WHERE student_exam_id IN (SELECT id FROM student_exams WHERE student_id = ?)")->execute([$targetUserId]);
            $pdo->prepare("DELETE FROM student_exams WHERE student_id = ?")->execute([$targetUserId]);
            $pdo->prepare("DELETE FROM event_registrations WHERE student_id = ?")->execute([$targetUserId]);
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$targetUserId]);
            $feedback = 'User record and associated data removed.';
        }
    }

    // Single Delete Exam
    elseif ($action === 'delete_exam') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $chk = $pdo->prepare("SELECT is_live FROM exams WHERE id = ?");
        $chk->execute([$examId]);
        $row = $chk->fetch();
        if ($row && $row['is_live']) {
            $feedback = 'Live exams cannot be deleted. Deactivate live status first.';
            $feedbackType = 'error';
        } else {
            $pdo->prepare("DELETE FROM student_answers WHERE student_exam_id IN (SELECT id FROM student_exams WHERE exam_id = ?)")->execute([$examId]);
            $pdo->prepare("DELETE FROM proctoring_logs WHERE student_exam_id IN (SELECT id FROM student_exams WHERE exam_id = ?)")->execute([$examId]);
            $pdo->prepare("DELETE FROM student_exams WHERE exam_id = ?")->execute([$examId]);
            $pdo->prepare("DELETE FROM exam_questions WHERE exam_id = ?")->execute([$examId]);
            $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$examId]);
            $feedback = 'Exam and associated data deleted.';
        }
    }

    // Bulk Delete Exams
    elseif ($action === 'bulk_delete_exams') {
        $ids = json_decode($_POST['exam_ids'] ?? '[]', true);
        if (is_array($ids) && count($ids) > 0) {
            $inQ = implode(',', array_map('intval', $ids));
            $stmt = $pdo->query("SELECT id FROM exams WHERE id IN ($inQ) AND is_live = 0");
            $deletableIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($deletableIds) > 0) {
                $delIn = implode(',', $deletableIds);
                $pdo->query("DELETE FROM student_answers WHERE student_exam_id IN (SELECT id FROM student_exams WHERE exam_id IN ($delIn))");
                $pdo->query("DELETE FROM proctoring_logs WHERE student_exam_id IN (SELECT id FROM student_exams WHERE exam_id IN ($delIn))");
                $pdo->query("DELETE FROM student_exams WHERE exam_id IN ($delIn)");
                $pdo->query("DELETE FROM exam_questions WHERE exam_id IN ($delIn)");
                $pdo->query("DELETE FROM exams WHERE id IN ($delIn)");
                echo json_encode(['success' => true, 'count' => count($deletableIds)]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'No non-live exams selected.']);
        exit;
    }
}

// Global KPI Metrics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalExams = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$totalIncidents = $pdo->query("SELECT COUNT(*) FROM proctoring_logs")->fetchColumn();

// Fetch Users
$facultyList = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY created_at DESC")->fetchAll();
$studentList = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY created_at DESC")->fetchAll();

// Fetch All Exams
$allExamsList = $pdo->query("
    SELECT e.*, u.name as teacher_name,
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as question_count,
           (SELECT COUNT(*) FROM student_exams WHERE exam_id = e.id) as submission_count
    FROM exams e
    JOIN users u ON u.id = e.teacher_id
    ORDER BY e.created_at DESC
")->fetchAll();

// Fetch Global Proctoring Logs
$globalLogs = $pdo->query("
    SELECT pl.*, pl.activity_type as violation_type, pl.created_at as timestamp, u.name as student_name, e.title as exam_title
    FROM proctoring_logs pl
    JOIN users u ON u.id = pl.student_id
    JOIN exams e ON e.id = pl.exam_id
    ORDER BY pl.created_at DESC
    LIMIT 50
")->fetchAll();

// Fetch All Events
$allEvents = $pdo->query("
    SELECT e.*, e.event_date as start_time, u.name as creator_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registered_count
    FROM events e
    JOIN users u ON u.id = e.created_by
    ORDER BY e.event_date DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Console — SafeExam.AI</title>
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
        <i data-lucide="shield" class="w-4 h-4"></i>
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
            <i data-lucide="shield" class="w-5 h-5"></i>
          </div>
          <div>
            <span class="font-black text-lg tracking-tight text-slate-900">
              SExam<span class="text-rose-500">.AI</span>
            </span>
            <span class="text-[10px] text-slate-400 block font-bold tracking-widest uppercase -mt-0.5">Admin Console</span>
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
            ['id' => 'overview', 'label' => 'Overview', 'icon' => 'layout-dashboard'],
            ['id' => 'users', 'label' => 'Manage Users', 'icon' => 'users'],
            ['id' => 'exams', 'label' => 'All Exams', 'icon' => 'file-text'],
            ['id' => 'proctoring', 'label' => 'Proctoring Audit', 'icon' => 'camera'],
            ['id' => 'events', 'label' => 'Events', 'icon' => 'calendar'],
            ['id' => 'settings', 'label' => 'Admin Profile', 'icon' => 'settings'],
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
          </a>
        <?php endforeach; ?>
      </nav>
    </div>

    <!-- Bottom User Card & Logout -->
    <div class="p-4 border-t border-slate-200 bg-slate-50/50 space-y-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3 overflow-hidden">
          <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-sm">
            A
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
          if ($activeTab === 'users') echo 'User Governance & Faculty Approvals';
          elseif ($activeTab === 'exams') echo 'System-Wide Examination Oversight';
          elseif ($activeTab === 'proctoring') echo 'Global AI Proctoring Audit Logs';
          elseif ($activeTab === 'events') echo 'System Events & Hackathons';
          elseif ($activeTab === 'settings') echo 'Administrator Profile & Security';
          else echo 'System Overview & Analytics';
        ?>
      </h2>
      <span class="text-xs font-bold px-3 py-1.5 rounded-xl bg-slate-900 text-white flex items-center gap-1.5 shadow-sm">
        <i data-lucide="shield" class="w-3.5 h-3.5 text-rose-400"></i>
        <span>Root Administrator</span>
      </span>
    </div>

    <!-- Alert / Toast Messages -->
    <?php if ($feedback): ?>
      <div class="p-4 rounded-2xl text-xs font-semibold mb-6 flex items-center gap-2 animate-fade-in shadow-sm <?= $feedbackType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' ?>">
        <i data-lucide="<?= $feedbackType === 'success' ? 'check-circle-2' : 'alert-circle' ?>" class="w-4 h-4"></i>
        <span><?= htmlspecialchars($feedback) ?></span>
      </div>
    <?php endif; ?>

    <!-- WelcomeHeader (rendered on overview tab) -->
    <?php if ($activeTab === 'overview'): ?>
      <div class="relative overflow-hidden bg-gradient-to-r from-slate-900 to-slate-800 rounded-3xl py-8 px-6 md:px-8 mb-6 shadow-md text-white border border-slate-800 animate-fade-in">
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 rounded-full bg-white opacity-5 blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-6">
          <div>
            <h1 class="text-2xl md:text-3xl font-black flex items-center gap-2 tracking-tight">
              System Administration, <span class="text-rose-400 underline decoration-rose-400/40 decoration-wavy underline-offset-4">Control Hub</span> 🛡️
            </h1>
            <p class="text-slate-300 mt-2 text-xs md:text-sm font-medium">Institution-wide identity, examination security, and computer vision oversight active.</p>
            <div class="mt-4 flex flex-wrap items-center gap-2.5">
              <span class="inline-flex items-center gap-1.5 bg-white/10 px-3 py-1.5 rounded-xl text-[11px] font-semibold text-emerald-400 border border-white/10">
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Platform Guard Online
              </span>
              <span class="inline-flex items-center gap-1.5 bg-white/10 px-3 py-1.5 rounded-xl text-[11px] font-semibold text-rose-400 border border-white/10">
                <i data-lucide="activity" class="w-3.5 h-3.5"></i> <?= $totalIncidents ?> Demerits Enforced
              </span>
            </div>
          </div>
          <!-- Live Date & Clock -->
          <div class="bg-white/10 backdrop-blur-md px-5 py-3.5 rounded-2xl border border-white/20 shrink-0 text-center sm:text-right">
            <div class="flex items-center gap-2 text-slate-200 text-xs font-semibold">
              <i data-lucide="calendar" class="w-4 h-4"></i>
              <span><?= date('l, F j, Y') ?></span>
            </div>
            <div class="flex items-center gap-2 text-white text-lg font-black mt-1">
              <i data-lucide="clock" class="w-4 h-4 text-rose-400"></i>
              <span id="liveClock" class="font-mono"><?= date('h:i:s A') ?></span>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 1: OVERVIEW -->
    <?php if ($activeTab === 'overview'): ?>
      <div class="space-y-6 animate-fade-in">
        <!-- 4 KPI Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
              <i data-lucide="users" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total Students</p>
              <h4 class="text-2xl font-black text-slate-900"><?= $totalStudents ?></h4>
            </div>
          </div>

          <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
              <i data-lucide="graduation-cap" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Faculty Members</p>
              <h4 class="text-2xl font-black text-slate-900"><?= $totalTeachers ?></h4>
            </div>
          </div>

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
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
              <i data-lucide="shield-alert" class="w-6 h-6"></i>
            </div>
            <div>
              <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Proctor Incidents</p>
              <h4 class="text-2xl font-black text-slate-900"><?= $totalIncidents ?></h4>
            </div>
          </div>
        </div>

        <!-- Quick Links Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <a href="?tab=users" class="p-6 bg-white hover:bg-slate-50 border border-slate-200 rounded-3xl shadow-sm transition-all text-left">
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3">
              <i data-lucide="user-check" class="w-5 h-5"></i>
            </div>
            <h4 class="font-bold text-sm text-slate-900">Manage Faculty & Students</h4>
            <p class="text-xs text-slate-500 mt-1">Approve pending faculty, suspend or remove student accounts</p>
          </a>

          <a href="?tab=exams" class="p-6 bg-white hover:bg-slate-50 border border-slate-200 rounded-3xl shadow-sm transition-all text-left">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mb-3">
              <i data-lucide="layers" class="w-5 h-5"></i>
            </div>
            <h4 class="font-bold text-sm text-slate-900">All Institutional Exams</h4>
            <p class="text-xs text-slate-500 mt-1">Bulk delete stale exams with live examination protection</p>
          </a>

          <a href="?tab=proctoring" class="p-6 bg-white hover:bg-slate-50 border border-slate-200 rounded-3xl shadow-sm transition-all text-left">
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-3">
              <i data-lucide="activity" class="w-5 h-5"></i>
            </div>
            <h4 class="font-bold text-sm text-slate-900">Global Proctor Audit Feed</h4>
            <p class="text-xs text-slate-500 mt-1">Audit cell phone detections, multi-face alerts, and tab switches</p>
          </a>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 2: MANAGE USERS -->
    <?php if ($activeTab === 'users'): ?>
      <div class="space-y-8 animate-fade-in">
        <!-- Faculty Table -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <h3 class="text-base font-black text-slate-900 mb-1">Faculty & Teacher Accounts</h3>
          <p class="text-xs text-slate-500 mb-6">Review pending registrations and authorize faculty privileges</p>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                  <th class="py-3 px-3">Name</th>
                  <th class="py-3 px-3">Email Address</th>
                  <th class="py-3 px-3 text-center">Status</th>
                  <th class="py-3 px-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach ($facultyList as $fac): ?>
                  <tr class="hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($fac['name']) ?></td>
                    <td class="py-3 px-3 text-slate-600"><?= htmlspecialchars($fac['email']) ?></td>
                    <td class="py-3 px-3 text-center">
                      <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $fac['status'] === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($fac['status'] === 'pending' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200') ?>">
                        <?= htmlspecialchars($fac['status']) ?>
                      </span>
                    </td>
                    <td class="py-3 px-3 text-right space-x-1.5">
                      <?php if ($fac['status'] === 'pending'): ?>
                        <button onclick="setUserStatus(<?= $fac['id'] ?>, 'approved')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition-all">
                          Approve
                        </button>
                      <?php endif; ?>
                      <?php if ($fac['status'] !== 'suspended'): ?>
                        <button onclick="setUserStatus(<?= $fac['id'] ?>, 'suspended')" class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 rounded-lg text-xs font-bold transition-all">
                          Suspend
                        </button>
                      <?php else: ?>
                        <button onclick="setUserStatus(<?= $fac['id'] ?>, 'approved')" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-bold transition-all">
                          Reactivate
                        </button>
                      <?php endif; ?>
                      <button onclick="deleteUser(<?= $fac['id'] ?>, '<?= htmlspecialchars(addslashes($fac['name'])) ?>')" class="p-1 text-slate-400 hover:text-rose-600 transition-colors">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Student Directory Table -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <h3 class="text-base font-black text-slate-900 mb-1">Enrolled Students Directory</h3>
          <p class="text-xs text-slate-500 mb-6">Manage student access and academic integrity standings</p>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                  <th class="py-3 px-3">Student Name</th>
                  <th class="py-3 px-3">Student ID</th>
                  <th class="py-3 px-3">Email Address</th>
                  <th class="py-3 px-3 text-center">Status</th>
                  <th class="py-3 px-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach ($studentList as $stu): ?>
                  <tr class="hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($stu['name']) ?></td>
                    <td class="py-3 px-3 font-mono font-bold text-slate-600"><?= htmlspecialchars($stu['student_id_str'] ?: 'N/A') ?></td>
                    <td class="py-3 px-3 text-slate-600"><?= htmlspecialchars($stu['email']) ?></td>
                    <td class="py-3 px-3 text-center">
                      <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $stu['status'] === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?>">
                        <?= htmlspecialchars($stu['status']) ?>
                      </span>
                    </td>
                    <td class="py-3 px-3 text-right space-x-1.5">
                      <?php if ($stu['status'] !== 'suspended'): ?>
                        <button onclick="setUserStatus(<?= $stu['id'] ?>, 'suspended')" class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 rounded-lg text-xs font-bold transition-all">
                          Suspend
                        </button>
                      <?php else: ?>
                        <button onclick="setUserStatus(<?= $stu['id'] ?>, 'approved')" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-bold transition-all">
                          Reactivate
                        </button>
                      <?php endif; ?>
                      <button onclick="deleteUser(<?= $stu['id'] ?>, '<?= htmlspecialchars(addslashes($stu['name'])) ?>')" class="p-1 text-slate-400 hover:text-rose-600 transition-colors">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 3: ALL EXAMS (with Select-to-Delete Bulk Delete & Live Protection) -->
    <?php if ($activeTab === 'exams'): ?>
      <div class="space-y-6 animate-fade-in">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h3 class="text-lg font-black text-slate-900 tracking-tight">Institutional Examinations Directory</h3>
              <p class="text-xs text-slate-500 mt-0.5">Global oversight of all teacher-created exams with bulk actions</p>
            </div>
            <span class="text-xs font-bold text-slate-400"><?= count($allExamsList) ?> Examinations</span>
          </div>

          <!-- Bulk Action Bar -->
          <div id="bulkActionBar" class="hidden mb-4 p-3.5 bg-slate-900 text-white rounded-2xl flex items-center justify-between animate-fade-in shadow-md">
            <span class="text-xs font-bold" id="bulkSelectedCount">0 exams selected</span>
            <button onclick="performBulkDeleteExams()" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
              <span>Delete Selected</span>
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                  <th class="py-3 px-3 w-10">
                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAllExams()" class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500 cursor-pointer">
                  </th>
                  <th class="py-3 px-3">Title & Subject</th>
                  <th class="py-3 px-3">Faculty Instructor</th>
                  <th class="py-3 px-3 text-center">Questions</th>
                  <th class="py-3 px-3 text-center">Submissions</th>
                  <th class="py-3 px-3 text-center">Live Status</th>
                  <th class="py-3 px-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach ($allExamsList as $ex): ?>
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
                      <span class="text-[11px] text-slate-500"><?= htmlspecialchars($ex['subject'] ?: 'General') ?> &bull; <?= $ex['duration_minutes'] ?> mins</span>
                    </td>
                    <td class="py-3 px-3 text-slate-700 font-medium"><?= htmlspecialchars($ex['teacher_name']) ?></td>
                    <td class="py-3 px-3 text-center font-bold text-slate-700"><?= $ex['question_count'] ?></td>
                    <td class="py-3 px-3 text-center font-bold text-slate-700"><?= $ex['submission_count'] ?></td>
                    <td class="py-3 px-3 text-center">
                      <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $ex['is_live'] ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-slate-100 text-slate-500' ?>">
                        <?= $ex['is_live'] ? '● Live' : 'Offline' ?>
                      </span>
                    </td>
                    <td class="py-3 px-3 text-right">
                      <button onclick="deleteSingleExam(<?= $ex['id'] ?>, '<?= htmlspecialchars(addslashes($ex['title'])) ?>', <?= $ex['is_live'] ? 'true' : 'false' ?>)" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition-colors" title="Delete Exam">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 4: PROCTORING AUDIT -->
    <?php if ($activeTab === 'proctoring'): ?>
      <div class="space-y-6 animate-fade-in">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h3 class="text-lg font-black text-slate-900 tracking-tight">Global AI Edge Proctoring Telemetry Feed</h3>
              <p class="text-xs text-slate-500 mt-0.5">Complete chronological trail of all candidate infractions</p>
            </div>
            <span class="text-xs font-bold text-slate-400"><?= count($globalLogs) ?> Incidents Logged</span>
          </div>

          <?php if (count($globalLogs) === 0): ?>
            <p class="text-xs text-slate-400 py-12 text-center font-medium">Zero incidents recorded across the institution.</p>
          <?php else: ?>
            <div class="space-y-3">
              <?php foreach ($globalLogs as $log): ?>
                <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center font-black text-xs">
                      +<?= $log['demerit_points'] ?>
                    </div>
                    <div>
                      <h5 class="font-bold text-xs text-slate-900"><?= htmlspecialchars($log['violation_type']) ?></h5>
                      <p class="text-[11px] text-slate-500"><?= htmlspecialchars($log['student_name']) ?> &bull; <?= htmlspecialchars($log['exam_title']) ?></p>
                    </div>
                  </div>
                  <span class="text-[11px] text-slate-400 font-mono"><?= date('M d, Y h:i:s A', strtotime($log['timestamp'])) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 5: EVENTS -->
    <?php if ($activeTab === 'events'): ?>
      <div class="space-y-6 animate-fade-in">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <h3 class="text-lg font-black text-slate-900 tracking-tight mb-6">Institution-Wide Events</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($allEvents as $ev): ?>
              <div class="p-6 rounded-3xl border border-slate-200 bg-slate-50/50">
                <div class="flex items-center justify-between mb-3">
                  <span class="text-xs font-semibold text-slate-400"><?= date('M d, Y', strtotime($ev['start_time'])) ?></span>
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700"><?= $ev['registered_count'] ?> Attendees</span>
                </div>
                <h4 class="font-bold text-sm text-slate-900 mb-1"><?= htmlspecialchars($ev['title']) ?></h4>
                <p class="text-xs text-slate-500 mb-3"><?= htmlspecialchars($ev['description']) ?></p>
                <p class="text-[11px] text-slate-400">Created by: <strong><?= htmlspecialchars($ev['creator_name']) ?></strong></p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- TAB 6: SETTINGS -->
    <?php if ($activeTab === 'settings'): ?>
      <div class="max-w-2xl mx-auto space-y-6 animate-fade-in">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-8 shadow-sm">
          <h3 class="text-lg font-black text-slate-900 tracking-tight mb-4">Administrator Security Credentials</h3>
          <p class="text-xs text-slate-500 mb-6">Root access account details for system maintenance and oversight.</p>

          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs space-y-2">
            <div>Email: <strong class="text-slate-800"><?= htmlspecialchars($user['email']) ?></strong></div>
            <div>Role: <strong class="text-slate-800 uppercase"><?= htmlspecialchars($user['role']) ?></strong></div>
            <div>Account Created: <strong class="text-slate-800"><?= date('M d, Y', strtotime($user['created_at'])) ?></strong></div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </main>

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

    // Set User Status (Approve / Suspend)
    function setUserStatus(userId, status) {
      customConfirm({
        title: 'Confirm Status Change',
        message: 'Change this user account status to "' + status + '"?',
        confirmText: 'Confirm Update',
        type: 'info',
        onConfirm: () => {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '?tab=users';
          form.innerHTML = '<input type="hidden" name="action" value="set_status"><input type="hidden" name="target_user_id" value="' + userId + '"><input type="hidden" name="new_status" value="' + status + '">';
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    // Delete User
    function deleteUser(userId, userName) {
      customConfirm({
        title: 'Delete User Account',
        message: 'Are you sure you want to permanently delete ' + userName + '? All associated records will be removed.',
        confirmText: 'Delete User',
        type: 'danger',
        onConfirm: () => {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '?tab=users';
          form.innerHTML = '<input type="hidden" name="action" value="delete_user"><input type="hidden" name="target_user_id" value="' + userId + '">';
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    // Single Delete Exam
    function deleteSingleExam(examId, examTitle, isLive) {
      if (isLive) {
        customAlert({
          title: 'Protected Live Exam',
          message: 'Active live examinations cannot be deleted. Deactivate live status first.',
          type: 'error'
        });
        return;
      }

      customConfirm({
        title: 'Delete Examination',
        message: 'Delete "' + examTitle + '"? This will remove all associated submissions and incident logs.',
        confirmText: 'Delete Exam',
        type: 'danger',
        onConfirm: () => {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '?tab=exams';
          form.innerHTML = '<input type="hidden" name="action" value="delete_exam"><input type="hidden" name="exam_id" value="' + examId + '">';
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    // Select All & Bulk Delete
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

          const res = await fetch('?tab=exams', { method: 'POST', body: fd });
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
  </script>
</body>
</html>
