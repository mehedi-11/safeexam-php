<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('student');

$user = getCurrentUser();
$studentId = $user['id'];

// Fetch completed exams
$stmt = $pdo->prepare("
    SELECT se.*, e.title, e.course_name, e.course_code, e.total_marks, e.results_published, u.name as teacher_name
    FROM student_exams se
    JOIN exams e ON se.exam_id = e.id
    JOIN users u ON e.teacher_id = u.id
    WHERE se.student_id = ? AND se.status = 'completed'
    ORDER BY se.id DESC
");
$stmt->execute([$studentId]);
$results = $stmt->fetchAll();

$pageTitle = 'My Examination Results — SafeExam.AI';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 animate-fade-in">

  <!-- Header Banner -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
          <i data-lucide="award" class="w-5 h-5"></i>
        </div>
        <span>My Examination Results</span>
      </h1>
      <p class="text-xs text-slate-500 mt-1">Review your test scores, performance percentage, and verified answer keys.</p>
    </div>

    <!-- Active Search Box -->
    <div class="relative w-full sm:w-72">
      <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
      <input 
        type="text" 
        id="resultSearchInput" 
        onkeyup="filterResults()" 
        placeholder="Search results by title, course..." 
        class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-600 shadow-sm"
      >
    </div>
  </div>

  <!-- Results Table -->
  <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-700">
            <th class="py-3.5 px-6">Examination Title</th>
            <th class="py-3.5 px-6">Course</th>
            <th class="py-3.5 px-6">Faculty</th>
            <th class="py-3.5 px-6 text-center">Score / Total</th>
            <th class="py-3.5 px-6 text-center">Percentage</th>
            <th class="py-3.5 px-6">Date Completed</th>
            <th class="py-3.5 px-6 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
          <?php if (empty($results)): ?>
            <tr>
              <td colspan="7" class="py-12 text-center text-slate-400 font-medium">
                No exam results found. Take an exam from the dashboard to see your performance here!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($results as $res): ?>
              <?php 
                $score = (int)$res['total_score'];
                $total = (int)$res['total_marks'];
                $pct = $total > 0 ? round(($score / $total) * 100) : 0;
                $isPass = ($pct >= 40);
                $isPublished = (bool)$res['results_published'];
              ?>
              <tr class="result-row hover:bg-slate-50/70 transition-colors" data-search="<?= strtolower(htmlspecialchars($res['title'] . ' ' . $res['course_name'] . ' ' . $res['course_code'])) ?>">
                <td class="py-4 px-6 font-bold text-slate-900">
                  <?= htmlspecialchars($res['title']) ?>
                  <span class="block text-[10px] font-normal text-slate-400">Attempt #<?= $res['attempt_number'] ?> • Demerits: <?= $res['demerit_points'] ?></span>
                </td>
                <td class="py-4 px-6 text-slate-600 font-medium">
                  <?= htmlspecialchars($res['course_code'] ?? 'GENERAL') ?>
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <?= htmlspecialchars($res['teacher_name']) ?>
                </td>
                <td class="py-4 px-6 text-center font-bold text-slate-900">
                  <?php if ($isPublished): ?>
                    <span class="px-2.5 py-1 rounded-xl bg-slate-100 border border-slate-200">
                      <?= sprintf('%02d', $score) ?> / <?= sprintf('%02d', $total) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-[11px] text-amber-600 font-semibold italic">Grading in Progress</span>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-6 text-center">
                  <?php if ($isPublished): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?= $isPass ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                      <?= $pct ?>%
                    </span>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-6 text-slate-500 text-[11px]">
                  <?= date('M d, Y • h:i A', strtotime($res['completed_at'] ?? $res['start_time'])) ?>
                </td>
                <td class="py-4 px-6 text-right">
                  <?php if ($isPublished): ?>
                    <button 
                      type="button" 
                      onclick="viewAnswersheet(<?= $res['id'] ?>, '<?= htmlspecialchars(addslashes($res['title'])) ?>')"
                      class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-xl border border-indigo-200 transition text-xs inline-flex items-center gap-1 cursor-pointer"
                    >
                      <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                      <span>Answersheet</span>
                    </button>
                  <?php else: ?>
                    <span class="text-xs text-slate-400 italic">Not Published</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Visual Answersheet Modal -->
<div id="answersheetModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-3xl w-full max-h-[85vh] flex flex-col overflow-hidden animate-fade-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50">
      <div>
        <h3 class="text-base font-bold text-slate-900" id="modalExamTitle">Examination Answersheet</h3>
        <p class="text-xs text-slate-500">Color-Coded Review: Green indicates correct answers, Red indicates mistakes.</p>
      </div>
      <button onclick="closeAnswersheetModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-200 transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <div id="modalAnswersList" class="p-6 overflow-y-auto space-y-5 flex-1 text-xs">
      <div class="text-center py-8 text-slate-400">Loading answersheet...</div>
    </div>
  </div>
</div>

<script>
function filterResults() {
  const query = document.getElementById('resultSearchInput').value.toLowerCase();
  const rows = document.querySelectorAll('.result-row');
  rows.forEach(r => {
    const text = r.getAttribute('data-search') || '';
    r.style.display = text.includes(query) ? '' : 'none';
  });
}

async function viewAnswersheet(studentExamId, title) {
  document.getElementById('modalExamTitle').textContent = title + ' — Visual Grading';
  const container = document.getElementById('modalAnswersList');
  container.innerHTML = '<div class="text-center py-8 text-slate-400 flex items-center justify-center gap-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin text-indigo-600"></i> Loading Answersheet...</div>';
  lucide.createIcons();

  document.getElementById('answersheetModal').classList.remove('hidden');
  document.getElementById('answersheetModal').classList.add('flex');

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
      const correctAns = q.correct_option;

      html += `
        <div class="border rounded-2xl p-4 ${isMcq ? (isCorrect ? 'bg-emerald-50/40 border-emerald-200' : 'bg-rose-50/30 border-rose-200') : 'bg-slate-50 border-slate-200'}">
          <div class="flex items-center justify-between mb-2">
            <span class="font-bold text-slate-700">Question ${idx + 1}</span>
            <span class="font-bold ${isCorrect ? 'text-emerald-600' : 'text-rose-600'}">
              ${q.marks_obtained} / ${q.marks} Marks
            </span>
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
                Correct Answer: Option ${correctAns}
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
    lucide.createIcons();
  } catch (e) {
    container.innerHTML = '<div class="text-center py-8 text-rose-500">Failed to load answersheet.</div>';
  }
}

function closeAnswersheetModal() {
  document.getElementById('answersheetModal').classList.add('hidden');
  document.getElementById('answersheetModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

