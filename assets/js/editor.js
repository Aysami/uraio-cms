/* Website section editor: repeatable rows and live preview refresh. */
(function () {
  'use strict';

  document.querySelectorAll('[data-repeater]').forEach((repeater) => {
    const rows = repeater.querySelector('[data-repeater-rows]') || repeater.querySelector('.repeater-rows');
    const template = repeater.querySelector('[data-repeater-template]');
    const add = repeater.querySelector('[data-repeater-add]');
    if (!rows || !template || !add) return;

    add.addEventListener('click', () => {
      const index = rows.querySelectorAll('.repeater-row').length;
      const wrapper = document.createElement('div');
      wrapper.innerHTML = template.innerHTML.replace(/__i__/g, String(index));
      const row = wrapper.firstElementChild;
      if (row) {
        rows.appendChild(row);
        const first = row.querySelector('input, textarea');
        if (first) first.focus();
      }
    });

    repeater.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-repeater-remove]');
      if (!remove) return;
      const row = remove.closest('.repeater-row');
      if (row) row.remove();
    });
  });

  const iframe = document.querySelector('.editor-preview iframe');
  if (iframe) {
    // Refresh the preview after a section is saved via the normal redirect.
    iframe.addEventListener('load', () => {
      iframe.style.opacity = '1';
    });
  }
})();
