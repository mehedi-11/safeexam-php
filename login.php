<?php
// login.php — SafeExam.AI Dedicated Standalone Authentication Portal (Clean URL / Zero Query Strings)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    if ($role === 'admin') header("Location: /safeexam/admin/dashboard.php");
    elseif ($role === 'teacher') header("Location: /safeexam/teacher/dashboard.php");
    else header("Location: /safeexam/student/dashboard.php");
    exit;
}

$error = '';
$selectedRole = $_POST['role'] ?? ($_GET['role'] ?? 'student');
if (!in_array($selectedRole, ['student', 'teacher', 'admin'])) {
    $selectedRole = 'student';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $selectedRole = $role;

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both your credentials and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR student_id_str = ?) AND role = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier, $role]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended' || $user['status'] === 'blocked') {
                $error = 'Your account has been suspended or blocked. Please contact system administrator.';
            } elseif ($role === 'teacher' && $user['status'] === 'pending') {
                $error = 'Your faculty registration is currently pending admin approval.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_status'] = $user['status'];
                $_SESSION['student_id_str'] = $user['student_id_str'] ?? '';

                if ($user['role'] === 'admin') header("Location: /safeexam/admin/dashboard.php");
                elseif ($user['role'] === 'teacher') header("Location: /safeexam/teacher/dashboard.php");
                else header("Location: /safeexam/student/dashboard.php");
                exit;
            }
        } else {
            $error = 'Invalid credentials for ' . ucfirst($role) . ' portal. Please verify and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — SafeExam.AI</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- Google Fonts: Inter -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    .animate-fade-in { animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body class="min-h-screen bg-slate-50 flex flex-col justify-between text-slate-800">

  <!-- Main Standalone Login Card (Zero Top Navbar, Zero URL Changes) -->
  <div class="flex-grow flex items-center justify-center px-4 py-12">
    <div class="bg-white p-8 rounded-3xl shadow-xl shadow-slate-200/60 w-full max-w-md border border-slate-200/80 relative overflow-hidden animate-fade-in">
      
      <!-- Top Accent Bar -->
      <div class="absolute top-0 left-0 right-0 h-1.5 bg-slate-900"></div>

      <!-- Brand Logo Header -->
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-2xl bg-slate-900 flex items-center justify-center text-white shadow-md shadow-slate-900/20">
          <i data-lucide="book-open" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="font-black text-xl tracking-tight text-slate-900">
            SExam<span class="text-rose-500">.AI</span>
          </span>
          <span class="text-[10px] text-slate-400 block font-bold tracking-widest uppercase -mt-0.5">Secure AI Assessment</span>
        </div>
      </div>

      <!-- Role Tabs (Pure Client-side, NO Page Reload, NO URL Change) -->
      <div class="flex bg-slate-100 p-1 rounded-xl mb-6">
        <button
          type="button"
          id="tabAdmin"
          onclick="switchRole('admin')"
          class="flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all <?= $selectedRole === 'admin' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' ?>"
        >
          Admin
        </button>
        <button
          type="button"
          id="tabTeacher"
          onclick="switchRole('teacher')"
          class="flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all <?= $selectedRole === 'teacher' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' ?>"
        >
          Teacher
        </button>
        <button
          type="button"
          id="tabStudent"
          onclick="switchRole('student')"
          class="flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all <?= $selectedRole === 'student' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' ?>"
        >
          Student
        </button>
      </div>

      <!-- Role Title & Subtitle -->
      <h2 id="roleTitle" class="text-2xl font-black text-slate-900 mb-1">
        <?= $selectedRole === 'admin' ? 'System Admin' : ($selectedRole === 'teacher' ? 'Teacher Console' : 'Student Portal') ?>
      </h2>
      <p id="roleSubtitle" class="text-xs text-slate-500 mb-6">Enter your authorized credentials to gain portal access.</p>

      <!-- Error Alert -->
      <div id="errorAlertBox" class="<?= $error ? '' : 'hidden' ?> bg-rose-50 border border-rose-200 text-rose-700 p-3.5 rounded-2xl text-xs flex items-start gap-2.5 mb-6 animate-fade-in">
        <i data-lucide="shield-alert" class="w-4 h-4 mt-0.5 shrink-0 text-rose-600"></i>
        <span id="errorAlertText" class="font-medium leading-relaxed"><?= htmlspecialchars($error) ?></span>
      </div>

      <!-- Login Form (Action is always clean login.php) -->
      <form method="POST" action="login.php" class="space-y-4">
        <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($selectedRole) ?>">

        <!-- Identifier Input -->
        <div>
          <label id="identifierLabel" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
            <?= $selectedRole === 'student' ? 'Student ID / Email' : 'Email Address' ?> <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400" id="identifierIconContainer">
              <i data-lucide="<?= $selectedRole === 'student' ? 'user-square' : 'mail' ?>" id="identifierIcon" class="w-4 h-4"></i>
            </span>
            <input
              type="text"
              name="identifier"
              id="identifierInput"
              required
              placeholder="<?= $selectedRole === 'student' ? 'e.g. 232004048 or student@safeexam.ai' : ($selectedRole === 'teacher' ? 'teacher@safeexam.ai' : 'mehedimridul1919@gmail.com') ?>"
              class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-800 focus:ring-1 focus:ring-slate-800 transition-all"
            />
          </div>
        </div>

        <!-- Password Input with Show/Hide Toggle -->
        <div>
          <div class="flex justify-between items-center mb-2">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Password <span class="text-rose-500">*</span></label>
            <button type="button" onclick="showForgotPasswordModal()" class="text-xs font-semibold text-rose-500 hover:text-rose-600 transition-colors">
              Forgot Password?
            </button>
          </div>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
              <i data-lucide="key-round" class="w-4 h-4"></i>
            </span>
            <input
              type="password"
              name="password"
              id="passwordInput"
              required
              placeholder="••••••••"
              class="w-full pl-10 pr-10 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-800 focus:ring-1 focus:ring-slate-800 transition-all"
            />
            <button
              type="button"
              onclick="togglePasswordVisibility()"
              class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 transition-colors"
            >
              <i data-lucide="eye" id="eyeIcon" class="w-4 h-4"></i>
            </button>
          </div>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          class="w-full py-3 mt-4 bg-slate-900 hover:bg-black text-white font-bold text-sm rounded-xl shadow-lg shadow-slate-900/10 hover:shadow-slate-900/20 active:scale-[0.99] transition-all flex items-center justify-center gap-2"
        >
          <i data-lucide="log-in" class="w-4 h-4"></i>
          <span id="submitBtnText">Sign In to <?= ucfirst($selectedRole) ?></span>
        </button>
      </form>

      <!-- Account / Registration Info -->
      <div class="text-center mt-6" id="accountFooter">
        <?php if ($selectedRole === 'admin'): ?>
          <span class="text-xs text-slate-500">
            Don't have an account? <span class="font-bold text-slate-700">Contact system administrator</span>
          </span>
        <?php else: ?>
          <span class="text-xs text-slate-500">Don't have an account? </span>
          <a href="register.php" class="text-xs font-bold text-rose-500 hover:text-rose-600 transition-colors">
            Register as <?= ucfirst($selectedRole) ?>
          </a>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- Minimal Bottom Copyright Footer -->
  <footer class="py-4 text-center text-xs text-slate-400">
    SafeExam.AI &copy; <?= date('Y') ?> &bull; Engineered by Dr. MD Mehedi Hasan &bull; All Rights Reserved.
  </footer>

  <!-- FORGOT PASSWORD POPUP MODAL (LIVE GMAIL SMTP) -->
  <div id="forgotPasswordModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 md:p-8 shadow-2xl border border-slate-200 animate-fade-in relative">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
            <i data-lucide="key-round" class="w-5 h-5"></i>
          </div>
          <div>
            <h3 class="text-base font-black text-slate-900">Reset Password</h3>
            <p class="text-xs text-slate-500">Live Gmail OTP Verification</p>
          </div>
        </div>
        <button onclick="closeForgotPasswordModal()" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <div id="fpAlertBox" class="hidden mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium"></div>

      <!-- FP Step 1: Request Code -->
      <div id="fpStep1">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Registered Email or Student ID</label>
            <input type="text" id="fpIdentifierInput" placeholder="name@gmail.com or 232004048" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800">
          </div>
          <button onclick="submitSendResetOtp()" id="btnFpSend" class="w-full py-3 bg-slate-900 hover:bg-black text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-2">
            <i data-lucide="send" class="w-4 h-4"></i>
            <span id="btnFpSendText">Send Reset Code to Gmail</span>
          </button>
        </div>
      </div>

      <!-- FP Step 2: Verify Code & Set New Password -->
      <div id="fpStep2" class="hidden space-y-4">
        <p class="text-xs text-slate-500">
          Enter the 6-digit code dispatched to: <strong id="fpTargetEmail" class="text-slate-800"></strong>
        </p>
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">6-Digit Code</label>
          <input type="text" id="fpOtpInput" maxlength="6" placeholder="••••••" class="w-full text-center tracking-[8px] font-mono font-bold px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-800">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">New Password</label>
          <input type="password" id="fpNewPasswordInput" minlength="6" placeholder="••••••••" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-800">
        </div>
        <button onclick="submitResetPassword()" id="btnFpReset" class="w-full py-3 bg-slate-900 hover:bg-black text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-2">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span id="btnFpResetText">Reset & Update Password</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Reusable Custom Alert / Modal JS -->
  <script src="/safeexam/assets/js/main.js"></script>
  <script>
    lucide.createIcons();

    // Clean any query string from URL bar immediately so URL remains strictly /safeexam/login.php
    if (window.location.search) {
      window.history.replaceState({}, document.title, window.location.pathname);
    }

    const roleConfig = {
      admin: {
        title: 'System Admin',
        label: 'Email Address',
        icon: 'mail',
        placeholder: 'mehedimridul1919@gmail.com',
        btnText: 'Sign In to Admin',
        footerHtml: `<span class="text-xs text-slate-500">Don't have an account? <span class="font-bold text-slate-700">Contact system administrator</span></span>`,
        defaultUser: 'mehedimridul1919@gmail.com',
        defaultPass: 'Admin@129221'
      },
      teacher: {
        title: 'Teacher Console',
        label: 'Email Address',
        icon: 'mail',
        placeholder: 'teacher@safeexam.ai',
        btnText: 'Sign In to Teacher',
        footerHtml: `<span class="text-xs text-slate-500">Don't have an account? </span><a href="register.php" class="text-xs font-bold text-rose-500 hover:text-rose-600 transition-colors">Register as Teacher</a>`,
        defaultUser: 'teacher@safeexam.ai',
        defaultPass: 'teacher123'
      },
      student: {
        title: 'Student Portal',
        label: 'Student ID / Email',
        icon: 'user-square',
        placeholder: 'e.g. 232004048 or student@safeexam.ai',
        btnText: 'Sign In to Student',
        footerHtml: `<span class="text-xs text-slate-500">Don't have an account? </span><a href="register.php" class="text-xs font-bold text-rose-500 hover:text-rose-600 transition-colors">Register as Student</a>`,
        defaultUser: 'student@safeexam.ai',
        defaultPass: 'student123'
      }
    };

    function switchRole(role) {
      const cfg = roleConfig[role];
      if (!cfg) return;

      // Update Tab Styles
      ['admin', 'teacher', 'student'].forEach(r => {
        const tab = document.getElementById('tab' + r.charAt(0).toUpperCase() + r.slice(1));
        if (r === role) {
          tab.className = 'flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all bg-white text-slate-900 shadow-sm';
        } else {
          tab.className = 'flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all text-slate-500 hover:text-slate-900';
        }
      });

      // Update Form State & UI (Without changing URL or reloading page)
      document.getElementById('roleInput').value = role;
      document.getElementById('roleTitle').innerText = cfg.title;
      document.getElementById('identifierLabel').innerHTML = cfg.label + ' <span class="text-rose-500">*</span>';
      
      const iconContainer = document.getElementById('identifierIconContainer');
      iconContainer.innerHTML = `<i data-lucide="${cfg.icon}" class="w-4 h-4"></i>`;

      const idInput = document.getElementById('identifierInput');
      idInput.placeholder = cfg.placeholder;
      idInput.value = cfg.defaultUser;

      const pwdInput = document.getElementById('passwordInput');
      pwdInput.value = cfg.defaultPass;

      document.getElementById('submitBtnText').innerText = cfg.btnText;
      document.getElementById('accountFooter').innerHTML = cfg.footerHtml;

      // Hide previous error message
      const alertBox = document.getElementById('errorAlertBox');
      if (alertBox) alertBox.classList.add('hidden');

      // Keep address bar clean
      window.history.replaceState({}, document.title, window.location.pathname);

      lucide.createIcons();
    }

    function togglePasswordVisibility() {
      const pwd = document.getElementById('passwordInput');
      const icon = document.getElementById('eyeIcon');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
      } else {
        pwd.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
      }
      lucide.createIcons();
    }

    // Auto-fill on initial page load
    window.addEventListener('DOMContentLoaded', () => {
      const idInput = document.getElementById('identifierInput');
      const pwdInput = document.getElementById('passwordInput');
      const currentRole = document.getElementById('roleInput').value || 'student';
      const cfg = roleConfig[currentRole];
      if (cfg && !idInput.value) {
        idInput.value = cfg.defaultUser;
        pwdInput.value = cfg.defaultPass;
      }
    });

    // Forgot Password Flow
    let fpCurrentEmail = '';

    function showForgotPasswordModal() {
      document.getElementById('fpStep1').classList.remove('hidden');
      document.getElementById('fpStep2').classList.add('hidden');
      document.getElementById('fpAlertBox').classList.add('hidden');
      document.getElementById('fpIdentifierInput').value = document.getElementById('identifierInput').value;
      document.getElementById('forgotPasswordModal').classList.remove('hidden');
      lucide.createIcons();
    }

    function closeForgotPasswordModal() {
      document.getElementById('forgotPasswordModal').classList.add('hidden');
    }

    async function submitSendResetOtp() {
      const id = document.getElementById('fpIdentifierInput').value.trim();
      const alertBox = document.getElementById('fpAlertBox');
      const btn = document.getElementById('btnFpSend');
      const btnText = document.getElementById('btnFpSendText');

      if (!id) {
        alertBox.innerText = 'Please enter your registered email or student ID.';
        alertBox.className = 'mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium';
        alertBox.classList.remove('hidden');
        return;
      }

      btn.disabled = true;
      btnText.innerText = 'Sending Gmail OTP...';

      try {
        const fd = new FormData();
        fd.append('identifier', id);
        const res = await fetch('/safeexam/api/send_reset_otp.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          fpCurrentEmail = data.email;
          document.getElementById('fpTargetEmail').innerText = data.email;
          document.getElementById('fpStep1').classList.add('hidden');
          document.getElementById('fpStep2').classList.remove('hidden');
          alertBox.innerText = data.message;
          alertBox.className = 'mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-medium';
          alertBox.classList.remove('hidden');
        } else {
          alertBox.innerText = data.message;
          alertBox.className = 'mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium';
          alertBox.classList.remove('hidden');
        }
      } catch (e) {
        alertBox.innerText = 'Failed to connect to server.';
        alertBox.className = 'mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium';
        alertBox.classList.remove('hidden');
      } finally {
        btn.disabled = false;
        btnText.innerText = 'Send Reset Code to Gmail';
      }
    }

    async function submitResetPassword() {
      const otp = document.getElementById('fpOtpInput').value.trim();
      const newPwd = document.getElementById('fpNewPasswordInput').value;
      const alertBox = document.getElementById('fpAlertBox');
      const btn = document.getElementById('btnFpReset');
      const btnText = document.getElementById('btnFpResetText');

      if (!otp || !newPwd) {
        alertBox.innerText = 'Please enter both the OTP and your new password.';
        alertBox.className = 'mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium';
        alertBox.classList.remove('hidden');
        return;
      }

      btn.disabled = true;
      btnText.innerText = 'Verifying & Updating...';

      try {
        const fd = new FormData();
        fd.append('email', fpCurrentEmail);
        fd.append('otp', otp);
        fd.append('new_password', newPwd);

        const res = await fetch('/safeexam/api/reset_password.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          closeForgotPasswordModal();
          customAlert({
            title: 'Password Updated',
            message: data.message,
            type: 'success'
          });
          document.getElementById('passwordInput').value = newPwd;
        } else {
          alertBox.innerText = data.message;
          alertBox.className = 'mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium';
          alertBox.classList.remove('hidden');
        }
      } catch (e) {
        alertBox.innerText = 'Error resetting password.';
        alertBox.className = 'mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium';
        alertBox.classList.remove('hidden');
      } finally {
        btn.disabled = false;
        btnText.innerText = 'Reset & Update Password';
      }
    }
  </script>
</body>
</html>
