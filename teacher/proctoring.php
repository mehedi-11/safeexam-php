<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('teacher');

$user = getCurrentUser();
$teacherId = $user['id'];
$examId = (int)($_GET['exam_id'] ?? 0);

if (!$examId) {
    header("Location: /safeexam/teacher/dashboard.php");
    exit;
}

// Verify exam
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
$stmt->execute([$examId, $teacherId]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Exam not found or unauthorized. <a href='/safeexam/teacher/dashboard.php'>Return to Dashboard</a>");
}

// Fetch stats
$activeCountStmt = $pdo->prepare("SELECT COUNT(*) FROM student_exams WHERE exam_id = ? AND status = 'in_progress'");
$activeCountStmt->execute([$examId]);
$activeCount = (int)$activeCountStmt->fetchColumn();

$completedCountStmt = $pdo->prepare("SELECT COUNT(*) FROM student_exams WHERE exam_id = ? AND status = 'completed'");
$completedCountStmt->execute([$examId]);
$completedCount = (int)$completedCountStmt->fetchColumn();

$pageTitle = 'Live Proctoring Room — ' . htmlspecialchars($exam['title']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 animate-fade-in">
  
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <a href="/safeexam/teacher/dashboard.php" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 flex items-center gap-1">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Exams
        </a>
      </div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
        <span>Live Proctoring Console: <?= htmlspecialchars($exam['title']) ?></span>
      </h1>
      <p class="text-xs text-slate-500 mt-0.5">Real-time incident stream and active sitting monitors.</p>
    </div>

    <div class="flex items-center gap-3">
      <span class="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
        <i data-lucide="refresh-cw" class="w-3.5 h-3.5 animate-spin text-indigo-600"></i>
        Live Sync Active (5s)
      </span>
    </div>
  </div>

  <!-- KPI Metrics Banner -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Actively Taking Exam</p>
          <h3 class="text-2xl font-black text-slate-900 mt-1" id="activeCandidateCount"><?= $activeCount ?></h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
          <i data-lucide="user-check" class="w-5 h-5"></i>
        </div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Exams Completed</p>
          <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $completedCount ?></h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100">
          <i data-lucide="check-circle-2" class="w-5 h-5"></i>
        </div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Status</p>
          <h3 class="text-2xl font-black text-slate-900 mt-1">
            <?= $exam['is_live'] ? '<span class="text-emerald-600">ONLINE</span>' : '<span class="text-slate-400">OFFLINE</span>' ?>
          </h3>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-600 flex items-center justify-center border border-slate-200">
          <i data-lucide="radio" class="w-5 h-5"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Incident Log Feed -->
  <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
      <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="shield-alert" class="w-4 h-4 text-rose-500"></i>
        <span>Second-by-Second Incident Log Feed</span>
      </h3>
      <span class="text-xs text-slate-400">Updates automatically</span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50/50 text-[11px] font-bold uppercase tracking-wider text-slate-600">
            <th class="py-3 px-6">Timestamp</th>
            <th class="py-3 px-6">Student Name</th>
            <th class="py-3 px-6">Student ID</th>
            <th class="py-3 px-6">Incident / Violation Type</th>
            <th class="py-3 px-6 text-center">Demerit Points</th>
          </tr>
        </thead>
        <tbody id="logsTableBody" class="divide-y divide-slate-100 text-xs">
          <tr>
            <td colspan="5" class="py-12 text-center text-slate-400 font-medium">
              Loading incident feed...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
const EXAM_ID = <?= $examId ?>;

async function fetchLiveLogs() {
  try {
    const res = await fetch('/safeexam/api/get_live_logs.php?exam_id=' + EXAM_ID);
    const data = await res.json();
    if (!data.success) return;

    document.getElementById('activeCandidateCount').textContent = data.active_count;

    const tbody = document.getElementById('logsTableBody');
    if (data.logs.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-slate-400 font-medium">No violations detected so far. Candidate room is clean!</td></tr>`;
      return;
    }

    let rows = '';
    data.logs.forEach(log => {
      rows += `
        <tr class="hover:bg-slate-50/70 transition-colors">
          <td class="py-3.5 px-6 font-mono text-slate-500 text-[11px]">
            ${log.created_at}
          </td>
          <td class="py-3.5 px-6 font-bold text-slate-900">
            ${log.student_name}
          </td>
          <td class="py-3.5 px-6 font-mono text-slate-600">
            ${log.student_id_str || 'N/A'}
          </td>
          <td class="py-3.5 px-6 font-semibold ${log.demerit_points >= 3 ? 'text-rose-600' : 'text-slate-800'}">
            ${log.activity_type}
          </td>
          <td class="py-3.5 px-6 text-center">
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${log.demerit_points >= 2 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200'}">
              +${log.demerit_points}
            </span>
          </td>
        </tr>
      `;
    });
    tbody.innerHTML = rows;
  } catch (e) {
    console.error('Fetch live logs error:', e);
  }
}

fetchLiveLogs();
setInterval(fetchLiveLogs, 5000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

