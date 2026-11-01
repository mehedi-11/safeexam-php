<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole(['teacher', 'admin']);

$user = getCurrentUser();
$userId = $user['id'];
$isAdmin = ($user['role'] === 'admin');

// Handle Actions (Create Event, Toggle Status, Delete, Bulk Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_event') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $date = !empty($_POST['event_date']) ? $_POST['event_date'] : date('Y-m-d H:i:s');

        if (!empty($title)) {
            $stmt = $pdo->prepare("
                INSERT INTO events (title, description, event_date, status, created_by, created_at)
                VALUES (?, ?, ?, 'live', ?, NOW())
            ");
            $stmt->execute([$title, $desc, $date, $userId]);
        }
        header("Location: /safeexam/teacher/events.php?created=1");
        exit;
    }

    if ($action === 'toggle_status') {
        $evtId = (int)$_POST['event_id'];
        $stmt = $pdo->prepare("UPDATE events SET status = IF(status = 'live', 'closed', 'live') WHERE id = ?");
        $stmt->execute([$evtId]);
        header("Location: /safeexam/teacher/events.php?toggled=1");
        exit;
    }

    if ($action === 'delete_event') {
        $evtId = (int)$_POST['event_id'];
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?" . (!$isAdmin ? " AND created_by = $userId" : ""));
        $stmt->execute([$evtId]);
        header("Location: /safeexam/teacher/events.php?deleted=1");
        exit;
    }

    if ($action === 'bulk_delete') {
        $evtIds = $_POST['event_ids'] ?? [];
        if (!empty($evtIds) && is_array($evtIds)) {
            $inClause = implode(',', array_map('intval', $evtIds));
            $pdo->query("DELETE FROM events WHERE id IN ($inClause)" . (!$isAdmin ? " AND created_by = $userId" : ""));
        }
        header("Location: /safeexam/teacher/events.php?bulk_deleted=1");
        exit;
    }
}

// Fetch Events
$sql = "
    SELECT e.*, u.name as organizer_name,
           (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id) as reg_count
    FROM events e
    JOIN users u ON e.created_by = u.id
    " . (!$isAdmin ? "WHERE e.created_by = $userId " : "") . "
    ORDER BY e.id DESC
";
$events = $pdo->query($sql)->fetchAll();

$pageTitle = 'Event & Hackathon Manager — SafeExam.AI';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 animate-fade-in">
  
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
          <i data-lucide="calendar" class="w-5 h-5"></i>
        </div>
        <span>Event & Hackathon Management</span>
      </h1>
      <p class="text-xs text-slate-500 mt-0.5">Publish university competitions, monitor attendee registrations, and configure exams.</p>
    </div>

    <button 
      onclick="openCreateEventModal()"
      class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition flex items-center gap-1.5 cursor-pointer"
    >
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>Create New Event</span>
    </button>
  </div>

  <!-- Bulk Action Bar for Events -->
  <div id="bulkActionBar" class="hidden items-center justify-between bg-indigo-50 border border-indigo-100 rounded-2xl px-5 py-3 text-xs animate-fade-in">
    <div class="flex items-center gap-2">
      <span class="font-bold text-indigo-950" id="selectedCountText">0 events selected</span>
      <button type="button" onclick="deselectAllEvents()" class="text-indigo-600 hover:underline text-xs ml-2 cursor-pointer">Deselect all</button>
    </div>
    <button 
      type="button" 
      onclick="confirmBulkDeleteEvents()"
      class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl transition flex items-center gap-1 cursor-pointer"
    >
      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
      <span id="bulkDeleteBtnText">Delete Selected</span>
    </button>
  </div>

  <!-- Events Table -->
  <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-700">
            <th class="py-3.5 px-4 text-center w-12">
              <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAllEvents(this.checked)" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
            </th>
            <th class="py-3.5 px-4">Event Title</th>
            <th class="py-3.5 px-4">Scheduled Date</th>
            <th class="py-3.5 px-4 text-center">Registrations</th>
            <th class="py-3.5 px-4 text-center">Status</th>
            <th class="py-3.5 px-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
          <?php if (empty($events)): ?>
            <tr>
              <td colspan="6" class="py-12 text-center text-slate-400 font-medium">
                No events currently configured. Click "Create New Event" above to get started!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($events as $evt): ?>
              <?php $isLive = ($evt['status'] === 'live'); ?>
              <tr class="hover:bg-slate-50/70 transition-colors">
                <td class="py-4 px-4 text-center">
                  <input 
                    type="checkbox" 
                    value="<?= $evt['id'] ?>" 
                    onchange="onEventCheckboxChange()" 
                    class="event-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                  >
                </td>
                <td class="py-4 px-4 font-bold text-slate-900">
                  <?= htmlspecialchars($evt['title']) ?>
                  <span class="block text-[10px] font-normal text-slate-400">By <?= htmlspecialchars($evt['organizer_name']) ?></span>
                </td>
                <td class="py-4 px-4 text-slate-600">
                  <?= date('M d, Y • h:i A', strtotime($evt['event_date'])) ?>
                </td>
                <td class="py-4 px-4 text-center">
                  <button 
                    type="button" 
                    onclick="viewEventRegistrations(<?= $evt['id'] ?>, '<?= htmlspecialchars(addslashes($evt['title'])) ?>')"
                    class="inline-flex items-center gap-1 px-3 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-xl border border-indigo-200 transition cursor-pointer"
                  >
                    <i data-lucide="users" class="w-3.5 h-3.5"></i>
                    <span><?= $evt['reg_count'] ?></span>
                  </button>
                </td>
                <td class="py-4 px-4 text-center">
                  <form method="POST" class="inline-block">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="event_id" value="<?= $evt['id'] ?>">
                    <button type="submit" class="px-3 py-1 rounded-full text-[10px] font-bold border transition cursor-pointer <?= $isLive ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' ?>">
                      <?= $isLive ? '● Live' : 'Closed' ?>
                    </button>
                  </form>
                </td>
                <td class="py-4 px-4 text-right">
                  <button 
                    type="button" 
                    onclick="confirmDeleteEvent(<?= $evt['id'] ?>, '<?= htmlspecialchars(addslashes($evt['title'])) ?>')"
                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                    title="Delete Event"
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
  <input type="hidden" name="action" value="delete_event">
  <input type="hidden" name="event_id" id="singleDeleteEventId">
</form>
<form id="bulkDeleteForm" method="POST" class="hidden">
  <input type="hidden" name="action" value="bulk_delete">
  <div id="bulkDeleteInputs"></div>
</form>

<!-- Create Event Modal -->
<div id="createEventModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-lg w-full p-6 sm:p-8 animate-fade-in">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold text-slate-900">Create New Event</h3>
      <button onclick="closeCreateEventModal()" class="text-slate-400 hover:text-slate-600 p-1">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create_event">

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Event Title</label>
        <input type="text" name="title" required placeholder="e.g. National Hackathon 2026" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-600">
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Scheduled Date & Time</label>
        <input type="datetime-local" name="event_date" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-600">
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Description & Guidelines</label>
        <textarea name="description" rows="3" placeholder="Describe the hackathon rules and eligibility..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-600"></textarea>
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button type="button" onclick="closeCreateEventModal()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
        <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md">Create Event</button>
      </div>
    </form>
  </div>
</div>

<!-- Registered Students Modal -->
<div id="regModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden animate-fade-in">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50">
      <h3 class="text-base font-bold text-slate-900" id="regModalTitle">Event Registrations</h3>
      <button onclick="closeRegModal()" class="text-slate-400 hover:text-slate-600 p-1">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div id="regModalBody" class="p-6 overflow-y-auto space-y-3 flex-1 text-xs">
      Loading registrations...
    </div>
  </div>
</div>

<script>
function openCreateEventModal() {
  document.getElementById('createEventModal').classList.remove('hidden');
  document.getElementById('createEventModal').classList.add('flex');
}
function closeCreateEventModal() {
  document.getElementById('createEventModal').classList.add('hidden');
  document.getElementById('createEventModal').classList.remove('flex');
}

async function viewEventRegistrations(eventId, title) {
  document.getElementById('regModalTitle').textContent = `Attendees: ${title}`;
  const body = document.getElementById('regModalBody');
  body.innerHTML = 'Loading registrations...';
  document.getElementById('regModal').classList.remove('hidden');
  document.getElementById('regModal').classList.add('flex');

  try {
    const res = await fetch('/safeexam/api/get_event_registrations.php?event_id=' + eventId);
    const data = await res.json();
    if (!data.success || data.registrations.length === 0) {
      body.innerHTML = '<div class="text-center py-8 text-slate-400">No students registered yet.</div>';
      return;
    }
    let html = `
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px]">
            <th class="py-2">Student Name</th>
            <th class="py-2">Roll No</th>
            <th class="py-2">Email</th>
            <th class="py-2 text-right">Registered At</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
    `;
    data.registrations.forEach(r => {
      html += `
        <tr>
          <td class="py-2 font-bold text-slate-900">${r.student_name}</td>
          <td class="py-2 font-mono text-slate-600">${r.student_roll || 'N/A'}</td>
          <td class="py-2 text-slate-500">${r.student_email}</td>
          <td class="py-2 text-right text-slate-400">${r.registered_at}</td>
        </tr>
      `;
    });
    html += '</tbody></table>';
    body.innerHTML = html;
  } catch (e) {
    body.innerHTML = 'Error loading registrations.';
  }
}

function closeRegModal() {
  document.getElementById('regModal').classList.add('hidden');
  document.getElementById('regModal').classList.remove('flex');
}

async function confirmDeleteEvent(id, title) {
  const confirmed = await customConfirm(`Are you sure you want to delete event "${title}"?`, {
    title: 'Delete Event',
    confirmText: 'Delete',
    type: 'danger'
  });
  if (confirmed) {
    document.getElementById('singleDeleteEventId').value = id;
    document.getElementById('singleDeleteForm').submit();
  }
}

function toggleSelectAllEvents(checked) {
  document.querySelectorAll('.event-checkbox').forEach(cb => cb.checked = checked);
  onEventCheckboxChange();
}

function deselectAllEvents() {
  document.querySelectorAll('.event-checkbox').forEach(cb => cb.checked = false);
  document.getElementById('selectAllCheckbox').checked = false;
  onEventCheckboxChange();
}

function onEventCheckboxChange() {
  const selected = Array.from(document.querySelectorAll('.event-checkbox:checked'));
  const bar = document.getElementById('bulkActionBar');
  if (selected.length > 0) {
    bar.classList.remove('hidden');
    bar.classList.add('flex');
    document.getElementById('selectedCountText').textContent = `${selected.length} event(s) selected`;
    document.getElementById('bulkDeleteBtnText').textContent = `Delete Selected (${selected.length})`;
  } else {
    bar.classList.add('hidden');
    bar.classList.remove('flex');
  }
}

async function confirmBulkDeleteEvents() {
  const selected = Array.from(document.querySelectorAll('.event-checkbox:checked'));
  if (selected.length === 0) return;

  const confirmed = await customConfirm(`Are you sure you want to delete ${selected.length} selected event(s)?`, {
    title: 'Bulk Delete Events',
    confirmText: `Delete (${selected.length})`,
    type: 'danger'
  });

  if (confirmed) {
    const container = document.getElementById('bulkDeleteInputs');
    container.innerHTML = '';
    selected.forEach(cb => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'event_ids[]';
      input.value = cb.value;
      container.appendChild(input);
    });
    document.getElementById('bulkDeleteForm').submit();
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

