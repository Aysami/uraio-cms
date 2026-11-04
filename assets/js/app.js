/* Uraio Business OS — shared front-end behaviour. */
(function () {
  'use strict';

  const $ = (selector, root) => (root || document).querySelector(selector);
  const $$ = (selector, root) => Array.prototype.slice.call((root || document).querySelectorAll(selector));

  /* Sidebar */
  const sidebar = $('#sidebar') || $('#admin-sidebar');
  const backdrop = $('.sidebar-backdrop') || $('.admin-sidebar-backdrop');
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('is-open');
    if (backdrop) backdrop.classList.remove('is-open');
    document.body.style.overflow = '';
  }
  $$('[data-sidebar-open]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (sidebar) sidebar.classList.add('is-open');
      if (backdrop) backdrop.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    });
  });
  $$('[data-sidebar-close]').forEach((btn) => btn.addEventListener('click', closeSidebar));

  /* Modals */
  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-open');
    const focusable = modal.querySelector('input, select, textarea, button, a[href]');
    if (focusable) focusable.focus();
  }
  function closeModals() {
    $$('.modal-backdrop.is-open').forEach((modal) => modal.classList.remove('is-open'));
  }
  $$('[data-open-modal]').forEach((btn) => {
    btn.addEventListener('click', () => openModal(btn.getAttribute('data-open-modal')));
  });
  $$('[data-close-modal]').forEach((btn) => btn.addEventListener('click', closeModals));
  $$('.modal-backdrop').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeModals();
    });
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModals();
      closeSidebar();
    }
  });

  /* Tabs */
  $$('.tab[data-tab-group]').forEach((tab) => {
    tab.addEventListener('click', () => {
      const group = tab.getAttribute('data-tab-group');
      const target = tab.getAttribute('data-tab-target');
      const panel = document.getElementById(target);
      if (!panel) return;
      $$('.tab[data-tab-group="' + group + '"]').forEach((item) => item.classList.toggle('is-active', item === tab));
      panel.parentElement.querySelectorAll('.tab-panel').forEach((item) => item.classList.toggle('is-active', item === panel));
    });
  });

  /* Confirm destructive actions */
  $$('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

  /* Copy buttons */
  $$('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const value = btn.getAttribute('data-copy');
      try {
        await navigator.clipboard.writeText(value);
        const original = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => { btn.textContent = original; }, 1600);
      } catch (error) {
        window.prompt('Copy this value', value);
      }
    });
  });

  /* Auto-dismiss flash messages */
  setTimeout(() => {
    $$('.alert-success, .alert-info').forEach((alert) => {
      alert.style.transition = 'opacity .4s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 450);
    });
  }, 6500);

  /* Toasts for fetch responses */
  window.ubosToast = function (message, type) {
    const stack = $('#toast-stack');
    if (!stack) return;
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + (type || 'info');
    toast.textContent = message;
    stack.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  };

  /* Service worker + install prompt */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      const swUrl = document.body.dataset.sw || './sw.js';
      const version = document.body.dataset.version || '';
      const register = () => navigator.serviceWorker.register(version ? swUrl + '?v=' + encodeURIComponent(version) : swUrl).catch(() => {});
      // When a new worker takes over, reload once so the fresh assets apply.
      let reloading = false;
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloading) return;
        reloading = true;
        window.location.reload();
      });
      register();
    });
  }

  let installEvent = null;
  const banner = $('#install-banner');
  const dismissed = localStorage.getItem('ubos_install_dismissed') === '1';
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installEvent = event;
    if (banner && !dismissed && !window.matchMedia('(display-mode: standalone)').matches) {
      banner.classList.add('is-visible');
    }
  });
  const installBtn = $('#install-app');
  if (installBtn) {
    installBtn.addEventListener('click', async () => {
      if (!installEvent) {
        window.alert('Use your browser menu and choose “Add to Home Screen” to install Uraio.');
        return;
      }
      installEvent.prompt();
      await installEvent.userChoice;
      installEvent = null;
      if (banner) banner.classList.remove('is-visible');
    });
  }
  const dismissBtn = $('#install-dismiss');
  if (dismissBtn) {
    dismissBtn.addEventListener('click', () => {
      localStorage.setItem('ubos_install_dismissed', '1');
      if (banner) banner.classList.remove('is-visible');
    });
  }
  window.addEventListener('appinstalled', () => {
    if (banner) banner.classList.remove('is-visible');
    localStorage.setItem('ubos_install_dismissed', '1');
  });

  /* Lightweight AJAX helper used by editor and notifications. */
  window.ubosFetch = async function (url, options) {
    const opts = Object.assign({ headers: {} }, options || {});
    opts.headers['X-Requested-With'] = 'XMLHttpRequest';
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    const response = await fetch(url, opts);
    const type = response.headers.get('content-type') || '';
    const data = type.indexOf('application/json') !== -1 ? await response.json() : await response.text();
    if (!response.ok) throw new Error((data && data.error) || 'Request failed.');
    return data;
  };
})();
