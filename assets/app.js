(() => {
  'use strict';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const toastEl = document.getElementById('liveToast');
  const notify = (message) => {
    if (!toastEl) return;
    toastEl.querySelector('.toast-body').textContent = message;
    bootstrap.Toast.getOrCreateInstance(toastEl).show();
  };
  const post = async (action, data = {}) => {
    const body = new URLSearchParams({ csrf_token: csrf, ...data });
    const response = await fetch(`?page=ajax&action=${encodeURIComponent(action)}`, {
      method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body
    });
    const payload = await response.json().catch(() => ({ ok: false, message: 'Phản hồi máy chủ không hợp lệ.' }));
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'Không thể thực hiện yêu cầu.');
    return payload;
  };
  document.querySelectorAll('[data-register-conference]').forEach(button => {
    button.addEventListener('click', async () => {
      if (button.dataset.confirmRegistration && !window.confirm(button.dataset.confirmRegistration)) return;
      button.classList.add('loading');
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      try {
        const result = await post('toggle-registration', { conference_id: button.dataset.registerConference });
        const compact = Boolean(button.dataset.registrationStatusTarget);
        button.textContent = result.registered ? 'Hủy đăng ký' : (compact ? 'Đăng ký lại' : 'Đăng ký tham dự');
        button.classList.remove('btn-danger', 'btn-primary', 'btn-outline-danger', 'btn-outline-primary');
        button.classList.add(result.registered ? (compact ? 'btn-outline-danger' : 'btn-danger') : (compact ? 'btn-outline-primary' : 'btn-primary'));
        if (result.registered) button.dataset.confirmRegistration = 'Bạn có chắc muốn hủy đăng ký hội thảo này?';
        else delete button.dataset.confirmRegistration;
        const statusTarget = document.getElementById(button.dataset.registrationStatusTarget || '');
        if (statusTarget) statusTarget.innerHTML = result.registered
          ? '<span class="badge text-bg-warning">Chờ duyệt</span>'
          : '<span class="badge text-bg-danger">Đã hủy</span>';
        notify(result.message);
      } catch (error) { notify(error.message); }
      finally {
        button.classList.remove('loading');
        button.disabled = false;
        button.removeAttribute('aria-busy');
      }
    });
  });
  const search = document.querySelector('[data-conference-search]');
  const cards = [...document.querySelectorAll('[data-conference-card]')];
  if (search && cards.length) {
    search.addEventListener('input', () => {
      const query = search.value.toLocaleLowerCase('vi').trim();
      cards.forEach(card => card.closest('.conference-col').hidden = !card.dataset.conferenceCard.toLocaleLowerCase('vi').includes(query));
    });
  }
  document.querySelectorAll('[data-confirm]').forEach(el => el.addEventListener('click', event => {
    if (!window.confirm(el.dataset.confirm || 'Bạn chắc chắn muốn tiếp tục?')) event.preventDefault();
  }));

  document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.querySelector(button.dataset.passwordToggle || '');
      if (!input) return;
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      button.textContent = showing ? 'Hiện' : 'Ẩn';
      button.setAttribute('aria-label', showing ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
    });
  });

  document.querySelectorAll('[data-file-input]').forEach(input => {
    input.addEventListener('change', () => {
      const output = input.closest('form')?.querySelector('[data-file-selection]');
      if (!output) return;
      const file = input.files?.[0];
      output.textContent = file ? `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB` : '';
    });
  });

  document.querySelectorAll('[data-upload-form]').forEach(form => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"], button:not([type])');
      if (!button) return;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.textContent = 'Đang tải lên…';
    });
  });
})();

