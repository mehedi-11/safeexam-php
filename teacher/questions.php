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

// Verify teacher owns this exam
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
$stmt->execute([$examId, $teacherId]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Examination not found or unauthorized. <a href='/safeexam/teacher/dashboard.php'>Return to Dashboard</a>");
}

// Handle Questions Actions (Add, Delete, Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_question') {
        $qText = trim($_POST['question_text'] ?? '');
        $type = $_POST['question_type'] ?? 'mcq';
        $optA = trim($_POST['option_a'] ?? '');
        $optB = trim($_POST['option_b'] ?? '');
        $optC = trim($_POST['option_c'] ?? '');
        $optD = trim($_POST['option_d'] ?? '');
        $correct = trim($_POST['correct_option'] ?? 'A');
        $marks = (int)($_POST['marks'] ?? 1);

        if (!empty($qText)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO exam_questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_option, marks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$examId, $qText, $type, $optA, $optB, $optC, $optD, $correct, $marks]);
        }
        header("Location: /safeexam/teacher/questions.php?exam_id={$examId}&added=1");
        exit;
    }

    if ($action === 'delete_question') {
        $qId = (int)$_POST['question_id'];
        $stmt = $pdo->prepare("DELETE FROM exam_questions WHERE id = ? AND exam_id = ?");
        $stmt->execute([$qId, $examId]);
        header("Location: /safeexam/teacher/questions.php?exam_id={$examId}&deleted=1");
        exit;
    }

    if ($action === 'bulk_delete') {
        $qIds = $_POST['question_ids'] ?? [];
        if (!empty($qIds) && is_array($qIds)) {
            $inClause = implode(',', array_map('intval', $qIds));
            $pdo->query("DELETE FROM exam_questions WHERE id IN ($inClause) AND exam_id = $examId");
        }
        header("Location: /safeexam/teacher/questions.php?exam_id={$examId}&bulk_deleted=1");
        exit;
    }
}

// Fetch all questions for this exam
$qStmt = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_num ASC, id ASC");
$qStmt->execute([$examId]);
$questions = $qStmt->fetchAll();

$pageTitle = 'Question Bank — ' . htmlspecialchars($exam['title']);
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
        <span>Question Bank: <?= htmlspecialchars($exam['title']) ?></span>
      </h1>
      <p class="text-xs text-slate-500 mt-0.5">Total Questions: <?= count($questions) ?> • Total Marks: <?= (int)$exam['total_marks'] ?></p>
    </div>

    <button 
      onclick="openAddQuestionModal()"
      class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition flex items-center gap-1.5 cursor-pointer"
    >
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>Add Question</span>
    </button>
  </div>

  <!-- Bulk Action Bar -->
  <div id="bulkActionBar" class="hidden items-center justify-between bg-indigo-50 border border-indigo-100 rounded-2xl px-5 py-3 text-xs animate-fade-in">
    <div class="flex items-center gap-2">
      <span class="font-bold text-indigo-950" id="selectedCountText">0 questions selected</span>
      <button type="button" onclick="deselectAllQuestions()" class="text-indigo-600 hover:underline text-xs ml-2 cursor-pointer">Deselect all</button>
    </div>
    <button 
      type="button" 
      onclick="confirmBulkDeleteQuestions()"
      class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl transition flex items-center gap-1 cursor-pointer"
    >
      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
      <span id="bulkDeleteBtnText">Delete Selected</span>
    </button>
  </div>

  <!-- Questions List -->
  <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-700">
            <th class="py-3.5 px-4 text-center w-12">
              <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAllQuestions(this.checked)" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
            </th>
            <th class="py-3.5 px-4 w-12 text-center">#</th>
            <th class="py-3.5 px-4">Question Text</th>
            <th class="py-3.5 px-4">Type</th>
            <th class="py-3.5 px-4">Correct Key</th>
            <th class="py-3.5 px-4 text-center">Marks</th>
            <th class="py-3.5 px-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
          <?php if (empty($questions)): ?>
            <tr>
              <td colspan="7" class="py-12 text-center text-slate-400 font-medium">
                No questions added yet. Click "Add Question" above to configure your question set!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($questions as $index => $q): ?>
              <tr class="hover:bg-slate-50/70 transition-colors">
                <td class="py-4 px-4 text-center">
                  <input 
                    type="checkbox" 
                    value="<?= $q['id'] ?>" 
                    onchange="onQuestionCheckboxChange()" 
                    class="question-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                  >
                </td>
                <td class="py-4 px-4 font-bold text-slate-400 text-center"><?= $index + 1 ?></td>
                <td class="py-4 px-4 font-semibold text-slate-900 max-w-md">
                  <?= nl2br(htmlspecialchars($q['question_text'])) ?>
                  <?php if ($q['question_type'] === 'mcq'): ?>
                    <div class="mt-2 text-[11px] text-slate-500 font-normal grid grid-cols-2 gap-1.5 bg-slate-50 p-2 rounded-xl border border-slate-100">
                      <div><strong>A:</strong> <?= htmlspecialchars($q['option_a']) ?></div>
                      <div><strong>B:</strong> <?= htmlspecialchars($q['option_b']) ?></div>
                      <div><strong>C:</strong> <?= htmlspecialchars($q['option_c']) ?></div>
                      <div><strong>D:</strong> <?= htmlspecialchars($q['option_d']) ?></div>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4 uppercase text-[10px] font-bold">
                  <span class="px-2 py-0.5 rounded-md <?= $q['question_type'] === 'mcq' ? 'bg-indigo-50 text-indigo-700' : 'bg-amber-50 text-amber-700' ?>">
                    <?= $q['question_type'] ?>
                  </span>
                </td>
                <td class="py-4 px-4 font-bold text-emerald-600">
                  <?= $q['question_type'] === 'mcq' ? 'Option ' . htmlspecialchars($q['correct_option']) : '—' ?>
                </td>
                <td class="py-4 px-4 text-center font-bold text-slate-800">
                  <?= (int)$q['marks'] ?>
                </td>
                <td class="py-4 px-4 text-right">
                  <button 
                    type="button" 
                    onclick="confirmDeleteQuestion(<?= $q['id'] ?>)" 
                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                    title="Delete Question"
                  >
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
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

<!-- Hidden Delete Forms -->
<form id="singleDeleteForm" method="POST" class="hidden">
  <input type="hidden" name="action" value="delete_question">
  <input type="hidden" name="question_id" id="singleDeleteQId">
</form>
<form id="bulkDeleteForm" method="POST" class="hidden">
  <input type="hidden" name="action" value="bulk_delete">
  <div id="bulkDeleteInputs"></div>
</form>

<!-- Add Question Modal -->
<div id="addQuestionModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-lg w-full p-6 sm:p-8 animate-fade-in overflow-y-auto max-h-[90vh]">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold text-slate-900">Add New Question</h3>
      <button onclick="closeAddQuestionModal()" class="text-slate-400 hover:text-slate-600 p-1">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="add_question">

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Question Type</label>
        <select name="question_type" id="questionTypeSelect" onchange="toggleQuestionType(this.value)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-600">
          <option value="mcq">Multiple Choice (MCQ)</option>
          <option value="written">Descriptive / Written</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Question Description</label>
        <textarea name="question_text" required rows="3" placeholder="Enter question text here..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-600"></textarea>
      </div>

      <!-- MCQ Options Section -->
      <div id="mcqOptionsBlock" class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Option A</label>
            <input type="text" name="option_a" placeholder="First choice" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Option B</label>
            <input type="text" name="option_b" placeholder="Second choice" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Option C</label>
            <input type="text" name="option_c" placeholder="Third choice" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
          </div>
          <div>
            <label class="block text-[11px] font-bold text-slate-600 mb-1">Option D</label>
            <input type="text" name="option_d" placeholder="Fourth choice" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Correct Answer</label>
          <select name="correct_option" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-indigo-700">
            <option value="A">Option A</option>
            <option value="B">Option B</option>
            <option value="C">Option C</option>
            <option value="D">Option D</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Marks</label>
        <input type="number" name="marks" value="1" min="1" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm">
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button type="button" onclick="closeAddQuestionModal()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
        <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md">Save Question</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddQuestionModal() {
  document.getElementById('addQuestionModal').classList.remove('hidden');
  document.getElementById('addQuestionModal').classList.add('flex');
}
function closeAddQuestionModal() {
  document.getElementById('addQuestionModal').classList.add('hidden');
  document.getElementById('addQuestionModal').classList.remove('flex');
}

function toggleQuestionType(type) {
  const block = document.getElementById('mcqOptionsBlock');
  if (type === 'mcq') {
    block.classList.remove('hidden');
  } else {
    block.classList.add('hidden');
  }
}

async function confirmDeleteQuestion(qId) {
  const confirmed = await customConfirm('Are you sure you want to delete this question?', {
    title: 'Delete Question',
    confirmText: 'Delete',
    type: 'danger'
  });
  if (confirmed) {
    document.getElementById('singleDeleteQId').value = qId;
    document.getElementById('singleDeleteForm').submit();
  }
}

function toggleSelectAllQuestions(checked) {
  document.querySelectorAll('.question-checkbox').forEach(cb => cb.checked = checked);
  onQuestionCheckboxChange();
}

function deselectAllQuestions() {
  document.querySelectorAll('.question-checkbox').forEach(cb => cb.checked = false);
  document.getElementById('selectAllCheckbox').checked = false;
  onQuestionCheckboxChange();
}

function onQuestionCheckboxChange() {
  const selected = Array.from(document.querySelectorAll('.question-checkbox:checked'));
  const bar = document.getElementById('bulkActionBar');
  if (selected.length > 0) {
    bar.classList.remove('hidden');
    bar.classList.add('flex');
    document.getElementById('selectedCountText').textContent = `${selected.length} question(s) selected`;
    document.getElementById('bulkDeleteBtnText').textContent = `Delete Selected (${selected.length})`;
  } else {
    bar.classList.add('hidden');
    bar.classList.remove('flex');
  }
}

async function confirmBulkDeleteQuestions() {
  const selected = Array.from(document.querySelectorAll('.question-checkbox:checked'));
  if (selected.length === 0) return;

  const confirmed = await customConfirm(`Are you sure you want to delete ${selected.length} selected question(s)?`, {
    title: 'Bulk Delete Questions',
    confirmText: `Delete (${selected.length})`,
    type: 'danger'
  });

  if (confirmed) {
    const container = document.getElementById('bulkDeleteInputs');
    container.innerHTML = '';
    selected.forEach(cb => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'question_ids[]';
      input.value = cb.value;
      container.appendChild(input);
    });
    document.getElementById('bulkDeleteForm').submit();
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

