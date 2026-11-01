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

// Verify teacher owns exam
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
$stmt->execute([$examId, $teacherId]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Exam not found or unauthorized. <a href='/safeexam/teacher/dashboard.php'>Return to Dashboard</a>");
}

// Handle Manual Marks Submission for Written Questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_marks') {
    $studentExamId = (int)$_POST['student_exam_id'];
    $marksArray = $_POST['marks'] ?? [];

    foreach ($marksArray as $ansId => $marks) {
        $m = (int)$marks;
        $upAns = $pdo->prepare("UPDATE student_answers SET marks_obtained = ? WHERE id = ? AND student_exam_id = ?");
        $upAns->execute([$m, (int)$ansId, $studentExamId]);
    }

    // Re-sum total score
    $sumStmt = $pdo->prepare("SELECT SUM(marks_obtained) FROM student_answers WHERE student_exam_id = ?");
    $sumStmt->execute([$studentExamId]);
    $newTotal = (int)$sumStmt->fetchColumn();

    $upEx = $pdo->prepare("UPDATE student_exams SET total_score = ? WHERE id = ?");
    $upEx->execute([$newTotal, $studentExamId]);

    header("Location: /safeexam/teacher/grading.php?exam_id={$examId}&graded=1");
    exit;
}

// Fetch submissions
$subStmt = $pdo->prepare("
    SELECT se.*, u.name as student_name, u.email as student_email, u.student_id_str
    FROM student_exams se
    JOIN users u ON se.student_id = u.id
    WHERE se.exam_id = ? AND se.status = 'completed'
    ORDER BY se.id DESC
");
$subStmt->execute([$examId]);
$submissions = $subStmt->fetchAll();

$pageTitle = 'Submissions & Grading — ' . htmlspecialchars($exam['title']);
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
        <span>Submissions & Answersheets: <?= htmlspecialchars($exam['title']) ?></span>
      </h1>
      <p class="text-xs text-slate-500 mt-0.5">Total Submissions: <?= count($submissions) ?> • Exam Total Marks: <?= (int)$exam['total_marks'] ?></p>
    </div>

    <div class="flex items-center gap-2">
      <form method="POST" action="/safeexam/teacher/dashboard.php">
        <input type="hidden" name="action" value="toggle_publish">
        <input type="hidden" name="exam_id" value="<?= $examId ?>">
        <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl border transition shadow-sm cursor-pointer <?= $exam['results_published'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200' ?>">
          <i data-lucide="<?= $exam['results_published'] ? 'eye' : 'eye-off' ?>" class="w-3.5 h-3.5 inline mr-1"></i>
          <span><?= $exam['results_published'] ? 'Results Published (Visible to Students)' : 'Results Hidden' ?></span>
        </button>
      </form>
    </div>
  </div>

  <!-- Submissions Table -->
  <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-700">
            <th class="py-3.5 px-6">Student Name</th>
            <th class="py-3.5 px-6">Roll / Student ID</th>
            <th class="py-3.5 px-6 text-center">Score / Total</th>
            <th class="py-3.5 px-6 text-center">Demerit Points</th>
            <th class="py-3.5 px-6">Submission Type</th>
            <th class="py-3.5 px-6">Submitted At</th>
            <th class="py-3.5 px-6 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
          <?php if (empty($submissions)): ?>
            <tr>
              <td colspan="7" class="py-12 text-center text-slate-400 font-medium">
                No student submissions yet for this exam.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($submissions as $sub): ?>
              <?php 
                $score = (int)$sub['total_score'];
                $total = (int)$exam['total_marks'];
                $demerits = (int)$sub['demerit_points'];
              ?>
              <tr class="hover:bg-slate-50/70 transition-colors">
                <td class="py-4 px-6 font-bold text-slate-900">
                  <?= htmlspecialchars($sub['student_name']) ?>
                  <span class="block text-[10px] font-normal text-slate-400"><?= htmlspecialchars($sub['student_email']) ?></span>
                </td>
                <td class="py-4 px-6 font-mono text-slate-600 font-bold">
                  <?= htmlspecialchars($sub['student_id_str'] ?? 'N/A') ?>
                </td>
                <td class="py-4 px-6 text-center font-bold text-slate-900">
                  <span class="px-2.5 py-1 rounded-xl bg-slate-100 border border-slate-200">
                    <?= sprintf('%02d', $score) ?> / <?= sprintf('%02d', $total) ?>
                  </span>
                </td>
                <td class="py-4 px-6 text-center">
                  <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= $demerits > 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                    <?= $demerits ?>
                  </span>
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <?= htmlspecialchars($sub['submission_reason'] ?? 'Submitted Manually') ?>
                </td>
                <td class="py-4 px-6 text-slate-500 text-[11px]">
                  <?= date('M d, Y • h:i A', strtotime($sub['completed_at'] ?? $sub['start_time'])) ?>
                </td>
                <td class="py-4 px-6 text-right">
                  <button 
                    type="button" 
                    onclick="openGradingModal(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['student_name'])) ?>')"
                    class="px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-xl border border-indigo-200 transition text-xs inline-flex items-center gap-1 cursor-pointer"
                  >
                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                    <span>Grade / View</span>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Answersheet & Manual Grading Modal -->
<div id="gradingModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-fade-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50">
      <div>
        <h3 class="text-base font-bold text-slate-900" id="gradingModalTitle">Review Answersheet</h3>
        <p class="text-xs text-slate-500">Color-coded answers with manual marks input for written responses.</p>
      </div>
      <button onclick="closeGradingModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-200 transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form method="POST" class="flex-1 flex flex-col overflow-hidden">
      <input type="hidden" name="action" value="save_marks">
      <input type="hidden" name="student_exam_id" id="modalStudentExamId">

      <div id="gradingModalBody" class="p-6 overflow-y-auto space-y-5 flex-1 text-xs">
        <div class="text-center py-8 text-slate-400">Loading student answers...</div>
      </div>

      <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
        <button type="button" onclick="closeGradingModal()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Close</button>
        <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md">Update & Save Marks</button>
      </div>
    </form>
  </div>
</div>

<script>
async function openGradingModal(studentExamId, studentName) {
  document.getElementById('modalStudentExamId').value = studentExamId;
  document.getElementById('gradingModalTitle').textContent = `Review Answersheet: ${studentName}`;
  const container = document.getElementById('gradingModalBody');
  container.innerHTML = '<div class="text-center py-8 text-slate-400">Loading student answers...</div>';

  document.getElementById('gradingModal').classList.remove('hidden');
  document.getElementById('gradingModal').classList.add('flex');

  try {
    const res = await fetch('/safeexam/api/get_answersheet.php?id=' + studentExamId);
    const data = await res.json();
    if (!data.success) {
      container.innerHTML = '<div class="text-center py-8 text-rose-500">' + (data.message || 'Error loading') + '</div>';
      return;
    }

    let html = '';
    data.questions.forEach((q, idx) => {
      const isMcq = (q.question_type === 'mcq');
      const isCorrect = (q.is_correct == 1);
      const studentAns = q.selected_option || q.written_answer || 'No Answer Provided';
      const ansId = q.id; // answer row id can be populated or question id

      html += `
        <div class="border rounded-2xl p-4 ${isMcq ? (isCorrect ? 'bg-emerald-50/40 border-emerald-200' : 'bg-rose-50/30 border-rose-200') : 'bg-slate-50 border-slate-200'}">
          <div class="flex items-center justify-between mb-2">
            <span class="font-bold text-slate-700">Question ${idx + 1} (${q.question_type.toUpperCase()})</span>
            <div class="flex items-center gap-2">
              <span class="font-bold text-slate-500">Max: ${q.marks} Marks</span>
              ${!isMcq ? `
                <div class="flex items-center gap-1 font-bold">
                  <span>Award:</span>
                  <input type="number" name="marks[${q.id}]" value="${q.marks_obtained || 0}" min="0" max="${q.marks}" class="w-14 px-2 py-1 text-center bg-white border border-slate-300 rounded-lg text-xs font-bold text-indigo-700">
                </div>
              ` : `
                <span class="font-bold ${isCorrect ? 'text-emerald-600' : 'text-rose-600'}">
                  Score: ${q.marks_obtained} / ${q.marks}
                </span>
              `}
            </div>
          </div>
          <p class="text-sm font-semibold text-slate-900 mb-3">${q.question_text}</p>
      `;

      if (isMcq) {
        html += `
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <div class="p-2.5 rounded-xl border ${isCorrect ? 'bg-emerald-100/70 border-emerald-300 text-emerald-900 font-bold' : 'bg-rose-100/70 border-rose-300 text-rose-900 font-bold line-through'}">
              Student Selected: ${studentAns} ${isCorrect ? '✓ (Correct)' : '✗ (Wrong)'}
            </div>
            ${!isCorrect ? `
              <div class="p-2.5 rounded-xl border bg-emerald-100/70 border-emerald-300 text-emerald-900 font-bold">
                Correct Answer: Option ${q.correct_option}
              </div>
            ` : ''}
          </div>
        `;
      } else {
        html += `
          <div class="p-3 bg-white border border-slate-200 rounded-xl">
            <span class="block text-[11px] font-bold text-slate-500 mb-1">Student's Written Response:</span>
            <p class="text-slate-800 whitespace-pre-line">${studentAns}</p>
          </div>
        `;
      }

      html += `</div>`;
    });

    container.innerHTML = html;
  } catch (e) {
    container.innerHTML = '<div class="text-center py-8 text-rose-500">Failed to load answersheet.</div>';
  }
}

function closeGradingModal() {
  document.getElementById('gradingModal').classList.add('hidden');
  document.getElementById('gradingModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

