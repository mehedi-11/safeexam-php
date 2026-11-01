/**
 * SafeExam.AI - Global Utility & Modal Alert System
 * Completely replaces native browser alert() and confirm() with modern animated modals.
 */

let modalResolve = null;

// Ensure modal markup exists in DOM
function ensureModalDOM() {
  if (document.getElementById('customModalBackdrop')) return;

  const modalHtml = `
    <div id="customModalBackdrop" class="fixed inset-0 z-[99999] hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity duration-200 opacity-0">
      <div id="customModalBox" class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-md w-full overflow-hidden transform scale-95 transition-all duration-200">
        <div class="p-6">
          <div class="flex items-start gap-4">
            <div id="modalIconContainer" class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm bg-indigo-100 text-indigo-600">
              <svg id="modalIcon" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"></svg>
            </div>
            <div class="flex-1 min-w-0 pt-0.5">
              <div class="flex items-center justify-between">
                <h3 id="modalTitle" class="text-base font-bold text-gray-900 tracking-tight">Notification</h3>
                <button type="button" onclick="closeCustomModal(false)" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
              </div>
              <p id="modalMessage" class="mt-2 text-sm text-gray-600 leading-relaxed whitespace-pre-line font-normal"></p>
            </div>
          </div>
        </div>
        <div id="modalFooter" class="bg-gray-50/90 px-6 py-3.5 border-t border-gray-100 flex items-center justify-end gap-2.5">
          <button id="modalCancelBtn" type="button" onclick="closeCustomModal(false)" class="px-4 py-2 text-xs font-bold text-gray-700 bg-white hover:bg-gray-100 border border-gray-200 rounded-xl transition shadow-sm cursor-pointer hidden">Cancel</button>
          <button id="modalConfirmBtn" type="button" onclick="closeCustomModal(true)" class="px-5 py-2 text-xs font-bold text-white bg-gray-900 hover:bg-black rounded-xl transition shadow-sm cursor-pointer">OK</button>
        </div>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML('beforeend', modalHtml);
}

// Icons SVG definitions
const icons = {
  success: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>`,
  error: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>`,
  warning: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>`,
  info: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>`,
  help: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>`
};

function customAlert(message, title = 'Notification', type = 'info') {
  ensureModalDOM();
  const backdrop = document.getElementById('customModalBackdrop');
  const box = document.getElementById('customModalBox');
  const iconContainer = document.getElementById('modalIconContainer');
  const icon = document.getElementById('modalIcon');
  const titleEl = document.getElementById('modalTitle');
  const messageEl = document.getElementById('modalMessage');
  const cancelBtn = document.getElementById('modalCancelBtn');
  const confirmBtn = document.getElementById('modalConfirmBtn');

  // Auto-detect type if generic
  let detectedType = type;
  if (type === 'info') {
    if (/success|deleted|updated|added|created|saved/i.test(message)) detectedType = 'success';
    else if (/error|failed|cannot|invalid|denied/i.test(message)) detectedType = 'error';
  }

  // Set colors and icon
  if (detectedType === 'success') {
    iconContainer.className = 'w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm bg-emerald-100 text-emerald-600';
    icon.innerHTML = icons.success;
    confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition shadow-sm cursor-pointer';
  } else if (detectedType === 'error' || detectedType === 'danger') {
    iconContainer.className = 'w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm bg-rose-100 text-rose-600';
    icon.innerHTML = icons.error;
    confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition shadow-sm cursor-pointer';
  } else {
    iconContainer.className = 'w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm bg-indigo-100 text-indigo-600';
    icon.innerHTML = icons.info;
    confirmBtn.className = 'px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-sm cursor-pointer';
  }

  titleEl.textContent = title;
  messageEl.textContent = message;
  cancelBtn.classList.add('hidden');
  confirmBtn.textContent = 'OK';

  backdrop.classList.remove('hidden');
  backdrop.classList.add('flex');
  setTimeout(() => {
    backdrop.classList.remove('opacity-0');
    box.classList.remove('scale-95');
  }, 10);
}

function customConfirm(message, options = {}) {
  ensureModalDOM();
  const {
    title = 'Please Confirm',
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    type = 'warning'
  } = typeof options === 'string' ? { title: options } : options;

  const backdrop = document.getElementById('customModalBackdrop');
  const box = document.getElementById('customModalBox');
  const iconContainer = document.getElementById('modalIconContainer');
  const icon = document.getElementById('modalIcon');
  const titleEl = document.getElementById('modalTitle');
  const messageEl = document.getElementById('modalMessage');
  const cancelBtn = document.getElementById('modalCancelBtn');
  const confirmBtn = document.getElementById('modalConfirmBtn');

  if (type === 'danger') {
    iconContainer.className = 'w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm bg-red-100 text-red-600';
    icon.innerHTML = icons.error;
    confirmBtn.className = 'px-4.5 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl transition shadow-sm cursor-pointer';
  } else {
    iconContainer.className = 'w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm bg-amber-100 text-amber-600';
    icon.innerHTML = icons.help;
    confirmBtn.className = 'px-4.5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-sm cursor-pointer';
  }

  titleEl.textContent = title;
  messageEl.textContent = message;
  cancelBtn.classList.remove('hidden');
  cancelBtn.textContent = cancelText;
  confirmBtn.textContent = confirmText;

  backdrop.classList.remove('hidden');
  backdrop.classList.add('flex');
  setTimeout(() => {
    backdrop.classList.remove('opacity-0');
    box.classList.remove('scale-95');
  }, 10);

  return new Promise((resolve) => {
    modalResolve = resolve;
  });
}

function closeCustomModal(result) {
  const backdrop = document.getElementById('customModalBackdrop');
  const box = document.getElementById('customModalBox');
  if (!backdrop) return;

  backdrop.classList.add('opacity-0');
  box.classList.add('scale-95');
  setTimeout(() => {
    backdrop.classList.add('hidden');
    backdrop.classList.remove('flex');
    if (modalResolve) {
      modalResolve(result);
      modalResolve = null;
    }
  }, 150);
}

// Global window interceptor
window.alert = function(msg) {
  customAlert(msg);
};

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeCustomModal(false);
});

