/* Onboarding: category search/filter, slug preview, template selection. */
(function () {
  'use strict';

  const grid = document.getElementById('category-grid');
  if (grid) {
    const options = Array.prototype.slice.call(grid.querySelectorAll('.category-option'));
    const search = document.getElementById('category-search');
    const group = document.getElementById('category-group-filter');
    const count = document.getElementById('category-count');

    function filter() {
      const term = (search ? search.value : '').trim().toLowerCase();
      const groupId = group ? group.value : '';
      let visible = 0;
      options.forEach((option) => {
        const matchesTerm = !term || option.dataset.name.indexOf(term) !== -1;
        const matchesGroup = !groupId || option.dataset.group === groupId;
        const show = matchesTerm && matchesGroup;
        option.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      if (count) count.textContent = visible + ' categor' + (visible === 1 ? 'y' : 'ies') + ' available';
    }
    if (search) search.addEventListener('input', filter);
    if (group) group.addEventListener('change', filter);

    options.forEach((option) => {
      const input = option.querySelector('input[type="radio"]');
      if (!input) return;
      input.addEventListener('change', () => {
        options.forEach((item) => item.classList.remove('is-selected'));
        option.classList.add('is-selected');
      });
    });
    filter();
  }

  const nameInput = document.getElementById('name');
  const slugPreview = document.getElementById('slug-preview');
  if (nameInput && slugPreview) {
    nameInput.addEventListener('input', () => {
      slugPreview.textContent = nameInput.value
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'your-business';
    });
  }

  const templateForm = document.getElementById('template-form');
  if (templateForm) {
    const cards = Array.prototype.slice.call(templateForm.querySelectorAll('.template-card'));
    cards.forEach((card) => {
      const input = card.querySelector('input[type="radio"]');
      if (!input || input.disabled) return;
      input.addEventListener('change', () => {
        cards.forEach((item) => item.classList.remove('is-selected'));
        card.classList.add('is-selected');
      });
    });
  }
})();
