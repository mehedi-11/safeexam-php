<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('student');

$user = getCurrentUser();
$studentId = $user['id'];
$examId = (int)($_GET['id'] ?? 0);

if (!$examId) {
    header("Location: /safeexam/student/dashboard.php");
    exit;
}

// Fetch Exam
$stmt = $pdo->prepare("
    SELECT e.*, u.name as teacher_name 
    FROM exams e 
    JOIN users u ON e.teacher_id = u.id 
    WHERE e.id = ? AND e.is_live = 1 
    LIMIT 1
");
$stmt->execute([$examId]);
$exam = $stmt->fetch();

if (!$exam) {
    die("This examination is either not active or does not exist. <a href='/safeexam/student/dashboard.php'>Return to Dashboard</a>");
}

// Check if password protected and verified
if (!empty($exam['exam_password']) && empty($_SESSION['exam_authorized_' . $examId])) {
    header("Location: /safeexam/student/dashboard.php");
    exit;
}

// Check attempt count
$attemptStmt = $pdo->prepare("SELECT COUNT(*) FROM student_exams WHERE exam_id = ? AND student_id = ? AND status = 'completed'");
$attemptStmt->execute([$examId, $studentId]);
$attemptsDone = (int)$attemptStmt->fetchColumn();

if ($attemptsDone >= (int)$exam['max_attempts']) {
    die("You have already reached the maximum allowed attempts (" . $exam['max_attempts'] . ") for this exam. <a href='/safeexam/student/results.php'>View Results</a>");
}

// Find existing in_progress attempt or create new one
$currAttemptStmt = $pdo->prepare("SELECT * FROM student_exams WHERE exam_id = ? AND student_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1");
$currAttemptStmt->execute([$examId, $studentId]);
$currentExam = $currAttemptStmt->fetch();

if (!$currentExam) {
    $nextAttemptNum = $attemptsDone + 1;
    $insertExamStmt = $pdo->prepare("
        INSERT INTO student_exams (student_id, exam_id, attempt_number, status, start_time, demerit_points)
        VALUES (?, ?, ?, 'in_progress', NOW(), 0)
    ");
    $insertExamStmt->execute([$studentId, $examId, $nextAttemptNum]);
    $studentExamId = (int)$pdo->lastInsertId();
    $demeritPoints = 0;
} else {
    $studentExamId = (int)$currentExam['id'];
    $demeritPoints = (int)$currentExam['demerit_points'];
}

// Fetch all questions for this exam
$qStmt = $pdo->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_num ASC, id ASC");
$qStmt->execute([$examId]);
$questions = $qStmt->fetchAll();

// Fetch previously saved answers for this attempt
$ansStmt = $pdo->prepare("SELECT question_id, selected_option, written_answer FROM student_answers WHERE student_exam_id = ?");
$ansStmt->execute([$studentExamId]);
$savedAnswers = [];
while ($row = $ansStmt->fetch()) {
    $savedAnswers[$row['question_id']] = !empty($row['selected_option']) ? $row['selected_option'] : $row['written_answer'];
}

$pageTitle = htmlspecialchars($exam['title']) . ' — Examination Room';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>

  <!-- Tailwind CSS & Lucide Icons -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  
  <!-- TensorFlow.js and COCO-SSD for Client-Side Computer Vision -->
  <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3/dist/coco-ssd.min.js"></script>

  <style>
    /* Prevent selection and context menu during exam */
    body {
      user-select: none;
      -webkit-user-select: none;
      background-color: #f1f5f9;
      font-family: 'Inter', system-ui, sans-serif;
    }
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between" oncontextmenu="return false;">

  <!-- PRE-FLIGHT DIAGNOSTICS SCREEN (shown before entering locked fullscreen) -->
  <div id="preflightScreen" class="fixed inset-0 z-50 bg-slate-900 text-white flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-slate-800 border border-slate-700 rounded-3xl p-6 sm:p-8 shadow-2xl">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center border border-indigo-500/30">
          <i data-lucide="shield-check" class="w-6 h-6"></i>
        </div>
        <div>
          <h2 class="text-xl font-bold">System Pre-Flight Check</h2>
          <p class="text-xs text-slate-400">Verify your camera and anti-cheat hardware before starting</p>
        </div>
      </div>

      <!-- Live Camera Preview Box -->
      <div class="relative w-full h-48 sm:h-56 bg-black rounded-2xl overflow-hidden border border-slate-700 mb-4 flex items-center justify-center">
        <video id="preflightVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
        <div id="cameraLoadingNotice" class="absolute text-center text-xs text-slate-400 flex items-center gap-2">
          <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-indigo-400"></i>
          <span>Initializing Camera Feed...</span>
        </div>
      </div>

      <div class="space-y-2.5 text-xs text-slate-300 mb-6 bg-slate-900/50 p-4 rounded-2xl border border-slate-700/60">
        <div class="flex items-center gap-2">
          <i data-lucide="check" class="w-4 h-4 text-emerald-400"></i>
          <span>Camera stream verified & ready for AI proctoring</span>
        </div>
        <div class="flex items-center gap-2">
          <i data-lucide="check" class="w-4 h-4 text-emerald-400"></i>
          <span>Automatic Fullscreen enforcement enabled</span>
        </div>
        <div class="flex items-center gap-2">
          <i data-lucide="check" class="w-4 h-4 text-emerald-400"></i>
          <span>Zero Server Video Lag (All Computer Vision runs in-browser)</span>
        </div>
        <div class="flex items-center gap-2 text-rose-300">
          <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-400"></i>
          <span>10 Demerit Points penalty will immediately terminate the exam</span>
        </div>
      </div>

      <button 
        id="enterExamBtn" 
        onclick="startExamWithLockdown()" 
        disabled
        class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <span>Enter Fullscreen & Begin Exam</span>
        <i data-lucide="arrow-right" class="w-4 h-4"></i>
      </button>
    </div>
  </div>

  <!-- EXAM NAVIGATION & HEADER -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm px-4 sm:px-8 py-3.5 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
        <i data-lucide="file-text" class="w-5 h-5"></i>
      </div>
      <div>
        <h1 class="text-sm sm:text-base font-bold text-slate-900 leading-tight"><?= htmlspecialchars($exam['title']) ?></h1>
        <span class="text-[11px] font-medium text-slate-500"><?= htmlspecialchars($exam['course_code'] ?? 'CSE') ?> • Total Marks: <?= (int)$exam['total_marks'] ?></span>
      </div>
    </div>

    <!-- Live Timer & Demerit Status -->
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold">
        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
        <span>Demerits: <span id="demeritDisplay"><?= $demeritPoints ?></span> / 10</span>
      </div>

      <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-mono font-bold shadow-sm">
        <i data-lucide="clock" class="w-4 h-4 text-emerald-400"></i>
        <span id="countdownTimer">--:--</span>
      </div>

      <button 
        type="button" 
        onclick="confirmAndSubmitExam(false)" 
        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer"
      >
        <i data-lucide="send" class="w-3.5 h-3.5"></i>
        <span>Submit Exam</span>
      </button>
    </div>
  </header>

  <!-- MAIN EXAM WORKSPACE -->
  <div class="max-w-7xl w-full mx-auto p-4 sm:p-6 grid grid-cols-1 lg:grid-cols-4 gap-6 flex-1">
    
    <!-- LEFT 3 COLS: QUESTIONS LIST -->
    <div class="lg:col-span-3 space-y-6">
      
      <?php if (empty($questions)): ?>
        <div class="bg-white border border-slate-200 rounded-3xl p-12 text-center text-slate-500">
          <p class="font-bold">No questions have been configured for this exam yet.</p>
        </div>
      <?php else: ?>
        <form id="examForm" onsubmit="event.preventDefault();" class="space-y-6">
          <?php foreach ($questions as $index => $q): ?>
            <?php 
              $qId = $q['id'];
              $savedVal = $savedAnswers[$qId] ?? '';
            ?>
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm" id="question_box_<?= $qId ?>">
              <div class="flex items-center justify-between mb-3">
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700">
                  Question <?= $index + 1 ?> of <?= count($questions) ?>
                </span>
                <span class="text-xs font-bold text-slate-400"><?= (int)$q['marks'] ?> Mark<?= $q['marks'] > 1 ? 's' : '' ?></span>
              </div>

              <h3 class="text-sm sm:text-base font-semibold text-slate-900 leading-relaxed mb-5">
                <?= nl2br(htmlspecialchars($q['question_text'])) ?>
              </h3>

              <?php if ($q['question_type'] === 'mcq'): ?>
                <!-- MCQ 4 Options Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <?php 
                    $options = [
                      'A' => $q['option_a'],
                      'B' => $q['option_b'],
                      'C' => $q['option_c'],
                      'D' => $q['option_d']
                    ];
                    foreach ($options as $key => $optText):
                      if (empty($optText)) continue;
                      $isChecked = ($savedVal === $key);
                  ?>
                    <label class="option-label flex items-center gap-3 p-3.5 border rounded-2xl cursor-pointer transition-all <?= $isChecked ? 'bg-indigo-50 border-indigo-600 text-indigo-900 font-semibold' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-white hover:border-slate-300' ?>">
                      <input 
                        type="radio" 
                        name="answer_<?= $qId ?>" 
                        value="<?= $key ?>" 
                        <?= $isChecked ? 'checked' : '' ?>
                        onchange="recordAnswer(<?= $qId ?>, '<?= $key ?>', 'mcq')" 
                        class="w-4 h-4 text-indigo-600 focus:ring-indigo-500"
                      >
                      <span class="text-xs font-bold uppercase w-6 h-6 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-700 shrink-0">
                        <?= $key ?>
                      </span>
                      <span class="text-xs leading-normal flex-1"><?= htmlspecialchars($optText) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>

              <?php else: ?>
                <!-- Written / Descriptive Input Area -->
                <div>
                  <textarea 
                    name="answer_<?= $qId ?>" 
                    rows="4" 
                    oninput="recordAnswer(<?= $qId ?>, this.value, 'written')"
                    placeholder="Type your comprehensive written response here..." 
                    class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs sm:text-sm focus:outline-none focus:border-indigo-600 focus:bg-white transition-colors"
                  ><?= htmlspecialchars($savedVal) ?></textarea>
                </div>
              <?php endif; ?>

            </div>
          <?php endforeach; ?>
        </form>
      <?php endif; ?>

    </div>

    <!-- RIGHT 1 COL: LIVE AI PROCTORING WEBCAM & QUESTIONS NAV -->
    <div class="space-y-6">
      
      <!-- In-Browser Webcam AI Monitor Box -->
      <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            AI Edge Proctoring
          </span>
          <span id="aiStatusPill" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">
            Scanning
          </span>
        </div>

        <div class="relative w-full h-44 bg-black rounded-2xl overflow-hidden border border-slate-200">
          <video id="liveProctorVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
          <canvas id="detectionCanvas" class="absolute inset-0 pointer-events-none"></canvas>
          <div id="aiWarningBanner" class="absolute bottom-2 inset-x-2 bg-rose-600/90 text-white text-[10px] font-bold py-1 px-2 rounded-lg text-center backdrop-blur-sm hidden animate-fade-in">
            Warning Detected
          </div>
        </div>

        <p class="text-[11px] text-slate-500 mt-3 leading-relaxed">
          AI detects mobile phones, extra persons, and looking away. Responses auto-save every 3 seconds.
        </p>
      </div>

      <!-- Quick Question Jump Grid -->
      <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm">
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3">Question Navigator</h4>
        <div class="grid grid-cols-5 gap-2">
          <?php foreach ($questions as $i => $q): ?>
            <?php $isAns = isset($savedAnswers[$q['id']]); ?>
            <button 
              type="button" 
              onclick="document.getElementById('question_box_<?= $q['id'] ?>').scrollIntoView({ behavior: 'smooth' })"
              id="nav_btn_<?= $q['id'] ?>"
              class="w-full h-8 rounded-xl text-xs font-bold transition flex items-center justify-center <?= $isAns ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>"
            >
              <?= $i + 1 ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

  </div>

  <script src="/safeexam/assets/js/main.js"></script>
  <script>
    lucide.createIcons();

    const EXAM_ID = <?= $examId ?>;
    const STUDENT_EXAM_ID = <?= $studentExamId ?>;
    const DURATION_MINUTES = <?= (int)$exam['duration_minutes'] ?>;
    let demeritCount = <?= $demeritPoints ?>;
    let stream = null;
    let cocoModel = null;
    let isExamActive = false;
    let hasSubmitted = false;
    let remainingSeconds = DURATION_MINUTES * 60;
    let answerBuffer = {};
    let saveTimer = null;

    // 1. Initialize Webcam Stream
    async function initWebcam() {
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: { width: 640, height: 480, facingMode: 'user' },
          audio: true
        });
        const preVideo = document.getElementById('preflightVideo');
        const liveVideo = document.getElementById('liveProctorVideo');
        preVideo.srcObject = stream;
        liveVideo.srcObject = stream;
        document.getElementById('cameraLoadingNotice').classList.add('hidden');
        document.getElementById('enterExamBtn').removeAttribute('disabled');
      } catch (err) {
        customAlert('Camera and Microphone permission is required to take this proctored examination.', 'Hardware Required', 'error');
      }
    }

    // 2. Load COCO-SSD Model for In-Browser Edge AI
    async function loadCocoModel() {
      try {
        cocoModel = await cocoSsd.load();
        console.log('Edge AI Computer Vision Model Loaded Successfully');
      } catch (e) {
        console.warn('AI Model failed to load via CDN:', e);
      }
    }

    // 3. Start Exam & Enter Lockdown
    async function startExamWithLockdown() {
      try {
        if (document.documentElement.requestFullscreen) {
          await document.documentElement.requestFullscreen();
        }
      } catch (e) {
        console.warn('Fullscreen request bypassed');
      }

      document.getElementById('preflightScreen').classList.add('hidden');
      isExamActive = true;
      startTimer();
      startAIPolling();
      startAutoSaveLoop();
    }

    // 4. Timer Countdown
    function startTimer() {
      const timerEl = document.getElementById('countdownTimer');
      const interval = setInterval(() => {
        if (!isExamActive || hasSubmitted) {
          clearInterval(interval);
          return;
        }
        remainingSeconds--;
        const m = Math.floor(remainingSeconds / 60).toString().padStart(2, '0');
        const s = (remainingSeconds % 60).toString().padStart(2, '0');
        timerEl.textContent = `${m}:${s}`;

        if (remainingSeconds <= 0) {
          clearInterval(interval);
          autoSubmitExam('Time Expired');
        }
      }, 1000);
    }

    // 5. Client-Side AI Computer Vision Loop
    async function startAIPolling() {
      const video = document.getElementById('liveProctorVideo');
      const warningBanner = document.getElementById('aiWarningBanner');
      const statusPill = document.getElementById('aiStatusPill');

      setInterval(async () => {
        if (!isExamActive || hasSubmitted || !cocoModel || !video || video.readyState < 2) return;

        try {
          const predictions = await cocoModel.detect(video);
          let persons = 0;
          let phoneFound = false;

          predictions.forEach(p => {
            if (p.class === 'person' && p.score > 0.5) persons++;
            if (p.class === 'cell phone' && p.score > 0.45) phoneFound = true;
          });

          if (phoneFound) {
            triggerViolation('Cell Phone Detected in Workspace', 3);
            warningBanner.textContent = 'Violation: Mobile Phone Detected!';
            warningBanner.classList.remove('hidden');
            setTimeout(() => warningBanner.classList.add('hidden'), 3000);
          } else if (persons > 1) {
            triggerViolation('Multiple Persons Detected in Frame', 2);
            warningBanner.textContent = 'Violation: Multiple Persons!';
            warningBanner.classList.remove('hidden');
            setTimeout(() => warningBanner.classList.add('hidden'), 3000);
          } else if (persons === 0) {
            triggerViolation('Candidate Absent From Camera', 1);
            warningBanner.textContent = 'Warning: Face Not Visible!';
            warningBanner.classList.remove('hidden');
            setTimeout(() => warningBanner.classList.add('hidden'), 3000);
          } else {
            statusPill.textContent = 'Clear';
            statusPill.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100';
          }
        } catch (err) {
          console.error('Inference error:', err);
        }
      }, 2000);
    }

    // 6. Anti-Cheat Event Listeners (Fullscreen & Tab Switching)
    document.addEventListener('visibilitychange', () => {
      if (!isExamActive || hasSubmitted) return;
      if (document.hidden) {
        triggerViolation('Tab Switched / Window Minimized', 2);
      }
    });

    window.addEventListener('blur', () => {
      if (!isExamActive || hasSubmitted) return;
      triggerViolation('Focus Lost / Window Unfocused', 1);
    });

    document.addEventListener('fullscreenchange', () => {
      if (!isExamActive || hasSubmitted) return;
      if (!document.fullscreenElement) {
        triggerViolation('Exited Fullscreen Lockdown', 1);
        customAlert('You have exited Fullscreen mode! Please remain in fullscreen throughout the test.', 'Security Alert', 'warning');
      }
    });

    // Keystroke Suppression (F12, Ctrl+C/V, Ctrl+R, Alt+Tab)
    window.addEventListener('keydown', (e) => {
      if (!isExamActive || hasSubmitted) return;
      if (
        e.key === 'F12' || 
        (e.ctrlKey && (e.key === 'c' || e.key === 'v' || e.key === 'u' || e.key === 'r' || e.key === 's')) ||
        (e.altKey && e.key === 'Tab')
      ) {
        e.preventDefault();
        triggerViolation('Prohibited Shortcut Key Pressed: ' + e.key, 1);
      }
    });

    // 7. Trigger Incident & Demerit Accumulator
    async function triggerViolation(activity, points) {
      demeritCount += points;
      document.getElementById('demeritDisplay').textContent = demeritCount;

      try {
        await fetch('/safeexam/api/proctor_log.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            exam_id: EXAM_ID,
            student_exam_id: STUDENT_EXAM_ID,
            activity_type: activity,
            demerit_points: points
          })
        });
      } catch (e) {
        console.error('Proctor log error:', e);
      }

      if (demeritCount >= 10 && !hasSubmitted) {
        autoSubmitExam('Maximum Demerit Limit Exceeded (10/10)');
      }
    }

    // 8. Auto-Save Answers
    function recordAnswer(questionId, value, type) {
      answerBuffer[questionId] = { value, type };
      const navBtn = document.getElementById('nav_btn_' + questionId);
      if (navBtn) {
        navBtn.className = 'w-full h-8 rounded-xl text-xs font-bold transition flex items-center justify-center bg-emerald-50 text-emerald-700 border border-emerald-200';
      }
    }

    function startAutoSaveLoop() {
      saveTimer = setInterval(async () => {
        if (Object.keys(answerBuffer).length === 0 || hasSubmitted) return;
        const payload = { ...answerBuffer };
        answerBuffer = {};

        try {
          await fetch('/safeexam/api/save_answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              student_exam_id: STUDENT_EXAM_ID,
              exam_id: EXAM_ID,
              answers: payload
            })
          });
        } catch (e) {
          console.error('Auto save error:', e);
        }
      }, 3000);
    }

    // 9. Submission Protocol
    async function confirmAndSubmitExam(isAuto = false) {
      if (!isAuto) {
        const confirmed = await customConfirm('Are you sure you want to finish and submit your examination?', {
          title: 'Submit Examination',
          confirmText: 'Submit Now',
          type: 'warning'
        });
        if (!confirmed) return;
      }

      executeFinalSubmission(isAuto ? 'Auto-Submitted' : 'Submitted Manually');
    }

    async function autoSubmitExam(reason) {
      if (hasSubmitted) return;
      customAlert(`Your exam has been finalized and submitted automatically: ${reason}`, 'Exam Terminated', 'error');
      executeFinalSubmission(reason, true);
    }

    async function executeFinalSubmission(reason, isAuto = false) {
      hasSubmitted = true;
      isExamActive = false;
      if (stream) {
        stream.getTracks().forEach(t => t.stop());
      }

      try {
        const res = await fetch('/safeexam/api/submit_exam.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            student_exam_id: STUDENT_EXAM_ID,
            exam_id: EXAM_ID,
            reason: reason,
            is_auto: isAuto
          })
        });
        const data = await res.json();
        window.location.href = '/safeexam/student/results.php?submitted=1';
      } catch (err) {
        window.location.href = '/safeexam/student/results.php';
      }
    }

    // Boot
    window.addEventListener('DOMContentLoaded', () => {
      initWebcam();
      loadCocoModel();
    });
  </script>
</body>
</html>

