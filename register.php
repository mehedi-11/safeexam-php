<?php
// register.php — SafeExam.AI Dedicated Standalone Registration Portal with Live Gmail SMTP Verification
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    if ($role === 'admin') header("Location: /safeexam/admin/dashboard.php");
    elseif ($role === 'teacher') header("Location: /safeexam/teacher/dashboard.php");
    else header("Location: /safeexam/student/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — SafeExam.AI</title>
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

  <!-- Main Standalone Registration Card (Zero Top Navbar, Zero URL Changes) -->
  <div class="flex-grow flex items-center justify-center px-4 py-10">
    <div class="bg-white p-8 rounded-3xl shadow-xl shadow-slate-200/60 w-full max-w-lg border border-slate-200/80 relative overflow-hidden animate-fade-in">
      
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
          <span class="text-[10px] text-slate-400 block font-bold tracking-widest uppercase -mt-0.5">Account Registration</span>
        </div>
      </div>

      <!-- STEP 1: Registration Form -->
      <div id="stepFormContainer">
        <!-- Role Tabs (Pure Client-side, NO URL change) -->
        <div class="flex bg-slate-100 p-1 rounded-xl mb-6">
          <button
            type="button"
            id="regTabStudent"
            onclick="switchRegRole('student')"
            class="flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all bg-white text-slate-900 shadow-sm"
          >
            Student Registration
          </button>
          <button
            type="button"
            id="regTabTeacher"
            onclick="switchRegRole('teacher')"
            class="flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all text-slate-500 hover:text-slate-900"
          >
            Faculty Registration
          </button>
        </div>

        <h2 id="regTitle" class="text-2xl font-black text-slate-900 mb-1">
          Student Sign Up
        </h2>
        <p id="regSubtitle" class="text-xs text-slate-500 mb-6">
          Register your verified student credentials to access AI examinations.
        </p>

        <!-- Feedback Alert Box -->
        <div id="regAlertBox" class="hidden p-3.5 rounded-2xl text-xs flex items-start gap-2.5 mb-6 animate-fade-in">
          <i data-lucide="shield-alert" id="regAlertIcon" class="w-4 h-4 mt-0.5 shrink-0"></i>
          <span id="regAlertText" class="font-medium leading-relaxed"></span>
        </div>

        <!-- Form -->
        <form onsubmit="handleSendOtp(event)" class="space-y-4">
          <input type="hidden" name="role" id="regRoleInput" value="student">

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Full Name <span class="text-rose-500">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                <i data-lucide="user" class="w-4 h-4"></i>
              </span>
              <input type="text" id="regName" required placeholder="e.g. Sarah Connor" class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-800 focus:ring-1 focus:ring-slate-800 transition-all">
            </div>
          </div>

          <div id="studentIdField">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Student ID <span class="text-rose-500">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                <i data-lucide="badge-check" class="w-4 h-4"></i>
              </span>
              <input type="text" id="regStudentId" required placeholder="e.g. 232004048" class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-800 focus:ring-1 focus:ring-slate-800 transition-all">
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Gmail Address <span class="text-rose-500">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                <i data-lucide="mail" class="w-4 h-4"></i>
              </span>
              <input type="email" id="regEmail" required placeholder="name@gmail.com" class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-800 focus:ring-1 focus:ring-slate-800 transition-all">
            </div>
            <p class="text-[11px] text-slate-400 mt-1">A real 6-digit verification code will be dispatched to this inbox.</p>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Password <span class="text-rose-500">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                <i data-lucide="key-round" class="w-4 h-4"></i>
              </span>
              <input type="password" id="regPassword" required minlength="6" placeholder="Minimum 6 characters" class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-800 focus:ring-1 focus:ring-slate-800 transition-all">
            </div>
          </div>

          <button type="submit" id="btnSendOtp" class="w-full py-3 mt-3 bg-slate-900 hover:bg-black text-white font-bold text-sm rounded-xl shadow-lg shadow-slate-900/10 hover:shadow-slate-900/20 active:scale-[0.99] transition-all flex items-center justify-center gap-2">
            <i data-lucide="send" class="w-4 h-4"></i>
            <span id="btnSendOtpText">Send Verification Code</span>
          </button>
        </form>

        <p class="text-center text-xs text-slate-500 mt-6">
          Already have an account? 
          <a href="login.php" class="font-bold text-rose-500 hover:text-rose-600 transition-colors">
            Sign In
          </a>
        </p>
      </div>

      <!-- STEP 2: OTP Verification Container (Hidden by default) -->
      <div id="stepOtpContainer" class="hidden animate-fade-in">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mb-4">
          <i data-lucide="mail-check" class="w-6 h-6"></i>
        </div>

        <h2 class="text-2xl font-black text-slate-900 mb-1">Verify Your Email</h2>
        <p class="text-xs text-slate-500 mb-6">
          We have dispatched a 6-digit verification code via Gmail to:  
          <strong id="displayTargetEmail" class="text-slate-900 font-semibold block mt-1 text-sm"></strong>
        </p>

        <!-- OTP Feedback Alert -->
        <div id="otpAlertBox" class="hidden p-3.5 rounded-2xl text-xs flex items-start gap-2.5 mb-6 animate-fade-in">
          <i data-lucide="shield-alert" id="otpAlertIcon" class="w-4 h-4 mt-0.5 shrink-0"></i>
          <span id="otpAlertText" class="font-medium leading-relaxed"></span>
        </div>

        <form onsubmit="handleVerifyOtp(event)" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 text-center">
              Enter 6-Digit Code <span class="text-rose-500">*</span>
            </label>
            <input
              type="text"
              id="inputOtpCode"
              maxlength="6"
              required
              placeholder="••••••"
              autocomplete="one-time-code"
              class="w-full text-center tracking-[12px] font-mono text-2xl font-black py-3 bg-slate-50 border-2 border-slate-200 rounded-2xl focus:outline-none focus:border-slate-800 focus:bg-white transition-all"
            />
          </div>

          <button type="submit" id="btnVerifyOtp" class="w-full py-3 mt-3 bg-slate-900 hover:bg-black text-white font-bold text-sm rounded-xl shadow-lg shadow-slate-900/10 hover:shadow-slate-900/20 active:scale-[0.99] transition-all flex items-center justify-center gap-2">
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i>
            <span id="btnVerifyOtpText">Verify & Complete Registration</span>
          </button>

          <div class="flex items-center justify-between pt-3 text-xs text-slate-500">
            <button type="button" onclick="backToForm()" class="font-bold text-slate-600 hover:text-slate-900 transition-colors flex items-center gap-1">
              &larr; Edit Details
            </button>
            <button type="button" id="btnResendOtp" onclick="handleResendOtp()" class="font-bold text-rose-500 hover:text-rose-600 transition-colors">
              Resend Code
            </button>
          </div>
        </form>
      </div>

      <!-- STEP 3: Success Confirmation Container (Hidden by default) -->
      <div id="stepSuccessContainer" class="hidden animate-fade-in text-center py-6">
        <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4">
          <i data-lucide="check" class="w-8 h-8"></i>
        </div>
        <h2 class="text-2xl font-black text-slate-900 mb-2">Account Created!</h2>
        <p id="successNoticeText" class="text-xs text-slate-500 mb-6 max-w-sm mx-auto leading-relaxed">
          Your account has been verified and registered successfully.
        </p>
        <a href="login.php" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-xl shadow-lg transition-all">
          <span>Proceed to Sign In</span>
          <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
      </div>

    </div>
  </div>

  <footer class="py-4 text-center text-xs text-slate-400">
    SafeExam.AI &copy; <?= date('Y') ?> &bull; Engineered by Dr. MD Mehedi Hasan &bull; All Rights Reserved.
  </footer>

  <script src="/safeexam/assets/js/main.js"></script>
  <script>
    lucide.createIcons();

    // Clean any query string from URL bar immediately so URL remains strictly /safeexam/register.php
    if (window.location.search) {
      window.history.replaceState({}, document.title, window.location.pathname);
    }

    let currentPayload = {};

    function switchRegRole(role) {
      const btnStudent = document.getElementById('regTabStudent');
      const btnTeacher = document.getElementById('regTabTeacher');
      const roleInput = document.getElementById('regRoleInput');
      const title = document.getElementById('regTitle');
      const subtitle = document.getElementById('regSubtitle');
      const idField = document.getElementById('studentIdField');
      const idInput = document.getElementById('regStudentId');

      roleInput.value = role;

      if (role === 'teacher') {
        btnTeacher.className = 'flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all bg-white text-slate-900 shadow-sm';
        btnStudent.className = 'flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all text-slate-500 hover:text-slate-900';
        title.innerText = 'Register Faculty';
        subtitle.innerText = 'Faculty accounts require institutional administrator approval.';
        idField.classList.add('hidden');
        idInput.removeAttribute('required');
      } else {
        btnStudent.className = 'flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all bg-white text-slate-900 shadow-sm';
        btnTeacher.className = 'flex-1 py-2 text-center text-xs font-bold rounded-lg transition-all text-slate-500 hover:text-slate-900';
        title.innerText = 'Student Sign Up';
        subtitle.innerText = 'Register your verified student credentials to access AI examinations.';
        idField.classList.remove('hidden');
        idInput.setAttribute('required', 'required');
      }

      window.history.replaceState({}, document.title, window.location.pathname);
      lucide.createIcons();
    }

    function showAlert(boxId, textId, msg, isSuccess = false) {
      const box = document.getElementById(boxId);
      const text = document.getElementById(textId);
      text.innerText = msg;
      box.className = isSuccess
        ? 'p-3.5 rounded-2xl text-xs flex items-start gap-2.5 mb-6 animate-fade-in bg-emerald-50 border border-emerald-200 text-emerald-800'
        : 'p-3.5 rounded-2xl text-xs flex items-start gap-2.5 mb-6 animate-fade-in bg-rose-50 border border-rose-200 text-rose-800';
      box.classList.remove('hidden');
      lucide.createIcons();
    }

    // Step 1: Send OTP to User's Gmail
    async function handleSendOtp(e) {
      e.preventDefault();
      const btn = document.getElementById('btnSendOtp');
      const btnText = document.getElementById('btnSendOtpText');
      const role = document.getElementById('regRoleInput').value;
      const name = document.getElementById('regName').value.trim();
      const email = document.getElementById('regEmail').value.trim();
      const password = document.getElementById('regPassword').value;
      const studentId = (role === 'student') ? document.getElementById('regStudentId').value.trim() : '';

      currentPayload = { role, name, email, password, student_id: studentId };

      btn.disabled = true;
      btnText.innerText = 'Sending Verification Code to Gmail...';

      try {
        const fd = new FormData();
        fd.append('name', name);
        fd.append('email', email);
        fd.append('role', role);
        fd.append('student_id', studentId);

        const res = await fetch('/safeexam/api/send_registration_otp.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();

        if (data.success) {
          document.getElementById('stepFormContainer').classList.add('hidden');
          document.getElementById('stepOtpContainer').classList.remove('hidden');
          document.getElementById('displayTargetEmail').innerText = email;
          document.getElementById('inputOtpCode').value = '';
          document.getElementById('inputOtpCode').focus();
          showAlert('otpAlertBox', 'otpAlertText', data.message, true);
        } else {
          showAlert('regAlertBox', 'regAlertText', data.message, false);
        }
      } catch (err) {
        showAlert('regAlertBox', 'regAlertText', 'Communication failure. Please verify internet and try again.', false);
      } finally {
        btn.disabled = false;
        btnText.innerText = 'Send Verification Code';
      }
    }

    // Step 2: Verify OTP and finalize
    async function handleVerifyOtp(e) {
      e.preventDefault();
      const otp = document.getElementById('inputOtpCode').value.trim();
      const btn = document.getElementById('btnVerifyOtp');
      const btnText = document.getElementById('btnVerifyOtpText');

      if (!otp || otp.length !== 6) {
        showAlert('otpAlertBox', 'otpAlertText', 'Please enter the complete 6-digit verification code.', false);
        return;
      }

      btn.disabled = true;
      btnText.innerText = 'Verifying Code with Server...';

      try {
        const fd = new FormData();
        fd.append('name', currentPayload.name);
        fd.append('email', currentPayload.email);
        fd.append('password', currentPayload.password);
        fd.append('role', currentPayload.role);
        fd.append('student_id', currentPayload.student_id);
        fd.append('otp', otp);

        const res = await fetch('/safeexam/api/complete_registration.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();

        if (data.success) {
          document.getElementById('stepOtpContainer').classList.add('hidden');
          document.getElementById('stepSuccessContainer').classList.remove('hidden');
          document.getElementById('successNoticeText').innerText = data.message;
        } else {
          showAlert('otpAlertBox', 'otpAlertText', data.message, false);
        }
      } catch (err) {
        showAlert('otpAlertBox', 'otpAlertText', 'Server verification error. Please try again.', false);
      } finally {
        btn.disabled = false;
        btnText.innerText = 'Verify & Complete Registration';
      }
    }

    function backToForm() {
      document.getElementById('stepOtpContainer').classList.add('hidden');
      document.getElementById('stepFormContainer').classList.remove('hidden');
    }

    async function handleResendOtp() {
      const btn = document.getElementById('btnResendOtp');
      btn.disabled = true;
      btn.innerText = 'Sending...';

      const fd = new FormData();
      fd.append('name', currentPayload.name);
      fd.append('email', currentPayload.email);
      fd.append('role', currentPayload.role);
      fd.append('student_id', currentPayload.student_id);

      try {
        const res = await fetch('/safeexam/api/send_registration_otp.php', { method: 'POST', body: fd });
        const data = await res.json();
        showAlert('otpAlertBox', 'otpAlertText', data.message, data.success);
      } catch (err) {
        showAlert('otpAlertBox', 'otpAlertText', 'Failed to resend code.', false);
      } finally {
        let timer = 30;
        btn.disabled = true;
        const itv = setInterval(() => {
          btn.innerText = `Resend in ${timer}s`;
          timer--;
          if (timer < 0) {
            clearInterval(itv);
            btn.disabled = false;
            btn.innerText = 'Resend Code';
          }
        }, 1000);
      }
    }
  </script>
</body>
</html>
