<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('student');

$user = getCurrentUser();
$studentId = $user['id'];
$tab = $_GET['tab'] ?? 'all';

// Handle Registration Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $eventId = (int)$_POST['event_id'];
    
    // Check if already registered
    $cStmt = $pdo->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND student_id = ?");
    $cStmt->execute([$eventId, $studentId]);
    if (!$cStmt->fetch()) {
        $regStmt = $pdo->prepare("
            INSERT INTO event_registrations (event_id, student_id, student_name, student_email, student_roll)
            VALUES (?, ?, ?, ?, ?)
        ");
        $regStmt->execute([
            $eventId, 
            $studentId, 
            $user['name'], 
            $user['email'], 
            $user['student_id_str']
        ]);
    }
    header("Location: /safeexam/student/events.php?tab=registered&registered=1");
    exit;
}

// Fetch All Live Events
$allEventsStmt = $pdo->prepare("
    SELECT e.*, u.name as organizer_name,
           (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id AND er.student_id = ?) as is_registered
    FROM events e
    JOIN users u ON e.created_by = u.id
    WHERE e.status = 'live'
    ORDER BY e.event_date DESC
");
$allEventsStmt->execute([$studentId]);
$allEvents = $allEventsStmt->fetchAll();

// Fetch Registered Events for this Student
$regEventsStmt = $pdo->prepare("
    SELECT er.*, e.title, e.event_date, e.description,
           ex.id as exam_id, ex.title as exam_title, ex.exam_password, ex.is_live as exam_is_live
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    LEFT JOIN exams ex ON ex.event_id = e.id
    WHERE er.student_id = ?
    ORDER BY er.id DESC
");
$regEventsStmt->execute([$studentId]);
$registeredEvents = $regEventsStmt->fetchAll();

$pageTitle = 'Student Events & Contests — SafeExam.AI';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 animate-fade-in">
  
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
          <i data-lucide="calendar" class="w-5 h-5"></i>
        </div>
        <span>Events & Hackathons</span>
      </h1>
      <p class="text-xs text-slate-500 mt-1">Register for university contests, retrieve secret keys, and enter live competitions.</p>
    </div>

    <!-- Dual Tabs Switcher -->
    <div class="flex bg-slate-100 p-1 rounded-2xl text-xs font-bold">
      <a href="?tab=all" class="px-4 py-2 rounded-xl transition-all <?= $tab === 'all' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-900' ?>">
        All Events
      </a>
      <a href="?tab=registered" class="px-4 py-2 rounded-xl transition-all <?= $tab === 'registered' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-900' ?>">
        Registered Events (<?= count($registeredEvents) ?>)
      </a>
    </div>
  </div>

  <?php if ($tab === 'all'): ?>
    <!-- ALL EVENTS TAB -->
    <?php if (empty($allEvents)): ?>
      <div class="bg-white border border-slate-200 rounded-3xl p-12 text-center text-slate-500">
        <i data-lucide="calendar-x" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
        <h3 class="text-base font-bold text-slate-800">No Events Scheduled</h3>
        <p class="text-xs text-slate-500 mt-1">There are no live university competitions right now.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($allEvents as $evt): ?>
          <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm flex flex-col justify-between">
            <div>
              <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-400 flex items-center gap-1.5">
                  <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                  <?= date('M d, Y • h:i A', strtotime($evt['event_date'])) ?>
                </span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  Active
                </span>
              </div>

              <h3 class="text-base font-bold text-slate-900 mb-1.5"><?= htmlspecialchars($evt['title']) ?></h3>
              <p class="text-xs text-slate-500 line-clamp-3 mb-4"><?= strip_tags($evt['description']) ?></p>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
              <span class="text-[11px] text-slate-400">By <?= htmlspecialchars($evt['organizer_name']) ?></span>
              
              <?php if ($evt['is_registered']): ?>
                <span class="px-3 py-1.5 bg-emerald-50 text-emerald-700 font-bold rounded-xl text-xs border border-emerald-200 flex items-center gap-1">
                  <i data-lucide="check" class="w-3.5 h-3.5"></i> Registered
                </span>
              <?php else: ?>
                <form method="POST">
                  <input type="hidden" name="action" value="register">
                  <input type="hidden" name="event_id" value="<?= $evt['id'] ?>">
                  <button type="submit" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-md shadow-indigo-600/20 transition cursor-pointer">
                    Register Now
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- REGISTERED EVENTS TAB -->
    <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-700">
              <th class="py-3.5 px-6">Event Name</th>
              <th class="py-3.5 px-6">Date</th>
              <th class="py-3.5 px-6 text-center">Exam Secret Code</th>
              <th class="py-3.5 px-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <?php if (empty($registeredEvents)): ?>
              <tr>
                <td colspan="4" class="py-12 text-center text-slate-400 font-medium">
                  You haven't registered for any events yet. Check the "All Events" tab to join!
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($registeredEvents as $reg): ?>
                <?php 
                  $hasExam = !empty($reg['exam_id']);
                  $isLive = ($hasExam && (int)$reg['exam_is_live'] === 1);
                ?>
                <tr class="hover:bg-slate-50/70 transition-colors">
                  <td class="py-4 px-6 font-bold text-slate-900">
                    <?= htmlspecialchars($reg['title']) ?>
                  </td>
                  <td class="py-4 px-6 text-slate-500">
                    <?= date('M d, Y • h:i A', strtotime($reg['event_date'])) ?>
                  </td>
                  <td class="py-4 px-6 text-center">
                    <?php if (!empty($reg['exam_password'])): ?>
                      <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-100 border border-slate-200 font-mono font-bold text-slate-800">
                        <span><?= htmlspecialchars($reg['exam_password']) ?></span>
                        <button onclick="copyToClipboard('<?= htmlspecialchars($reg['exam_password']) ?>')" title="Copy Key" class="hover:text-indigo-600">
                          <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                        </button>
                      </div>
                    <?php else: ?>
                      <span class="text-slate-400 italic">No Key Required / Not Set</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 px-6 text-right">
                    <button 
                      type="button" 
                      onclick="handleGoForExam(<?= $hasExam ? 'true' : 'false' ?>, <?= $isLive ? 'true' : 'false' ?>, <?= (int)($reg['exam_id'] ?? 0) ?>)"
                      class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-md shadow-indigo-600/20 transition inline-flex items-center gap-1.5 cursor-pointer"
                    >
                      <i data-lucide="play" class="w-3.5 h-3.5"></i>
                      <span>Go for Exam</span>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
function copyToClipboard(text) {
  navigator.clipboard.writeText(text);
  customAlert('Exam secret code copied to clipboard: ' + text, 'Key Copied', 'success');
}

function handleGoForExam(hasExam, isLive, examId) {
  if (!hasExam) {
    customAlert('The exam for this event has not been created yet by the organizer.', 'Exam Not Created', 'info');
    return;
  }
  if (!isLive) {
    customAlert('The exam for this event is either not yet live or has already ended.', 'Exam Inactive', 'warning');
    return;
  }
  window.location.href = '/safeexam/student/exam.php?id=' + examId;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

