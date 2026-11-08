(function () {
  'use strict';
  const maxBytes = 5 * 1024 * 1024;
  const input = document.getElementById('snapshot');
  const dropzone = document.getElementById('importDropzone');
  const status = document.getElementById('importFileStatus');
  const uploadForm = document.getElementById('importUploadForm');
  const previewButton = document.getElementById('importPreviewButton');

  function showFile(file) {
    if (!status) return;
    status.hidden = !file;
    status.classList.remove('invalid');
    if (!file) { status.querySelector('span').textContent = ''; return; }
    const valid = /\.json$/i.test(file.name) && file.size <= maxBytes && file.size > 0;
    status.querySelector('span').textContent = file.name + ' · ' + (file.size / 1024).toFixed(1) + ' KB' + (valid ? ' · ready to validate' : ' · choose a non-empty JSON file up to 5 MB');
    status.classList.toggle('invalid', !valid);
    input.setCustomValidity(valid ? '' : 'Choose a non-empty .json snapshot up to 5 MB.');
  }
  if (input) {
    input.addEventListener('change', function () {
      const file = input.files[0];
      showFile(file);
      if (file && input.validationMessage) { input.value = ''; input.reportValidity(); }
    });
  }
  if (dropzone && input) {
    ['dragenter', 'dragover'].forEach(function (name) {
      dropzone.addEventListener(name, function (event) { event.preventDefault(); dropzone.classList.add('dragging'); });
    });
    ['dragleave', 'drop'].forEach(function (name) {
      dropzone.addEventListener(name, function (event) { event.preventDefault(); dropzone.classList.remove('dragging'); });
    });
    dropzone.addEventListener('drop', function (event) {
      const files = event.dataTransfer && event.dataTransfer.files;
      if (!files || !files.length) return;
      if (files.length !== 1) {
        input.value = '';
        showFile({ name: 'Multiple files selected', size: 0 });
        return;
      }
      try {
        const transfer = new DataTransfer();
        transfer.items.add(files[0]);
        input.files = transfer.files;
        showFile(files[0]);
        if (input.validationMessage) input.value = '';
      } catch (_) {
        showFile(files[0]);
      }
    });
  }
  if (uploadForm && previewButton) {
    uploadForm.addEventListener('submit', function () {
      previewButton.disabled = true;
      previewButton.setAttribute('aria-busy', 'true');
      previewButton.querySelector('span').textContent = 'Validating snapshot…';
    });
  }
  const applyForm = document.getElementById('importApplyForm');
  const applyButton = document.getElementById('importApplyButton');
  const confirmation = applyForm ? applyForm.querySelector('input[name="confirmation"]') : null;
  const applyBlocked = applyButton ? applyButton.disabled : false;
  function syncApplyButton() {
    if (!applyButton || !confirmation) return;
    applyButton.disabled = applyBlocked || !confirmation.checked;
  }
  if (confirmation) confirmation.addEventListener('change', syncApplyButton);
  syncApplyButton();
  if (applyForm && applyButton) {
    applyForm.addEventListener('submit', function () {
      applyButton.disabled = true;
      applyButton.setAttribute('aria-busy', 'true');
      applyButton.querySelector('span').textContent = 'Applying reviewed import…';
    });
  }
  window.addEventListener('pageshow', function () {
    if (previewButton) { previewButton.disabled = false; previewButton.removeAttribute('aria-busy'); previewButton.querySelector('span').textContent = 'Validate & preview'; }
    if (applyButton) { applyButton.removeAttribute('aria-busy'); applyButton.querySelector('span').textContent = 'Apply reviewed import'; syncApplyButton(); }
  });

  const review = document.getElementById('importReview');
  if (!review) return;
  const controls = review.querySelector('.import-plan-controls');
  if (controls) controls.hidden = false;
  const rows = Array.from(review.querySelectorAll('[data-import-row]'));
  const sections = Array.from(review.querySelectorAll('[data-import-section]'));
  const filters = Array.from(review.querySelectorAll('[data-import-filter]'));
  const search = document.getElementById('importPlanSearch');
  const count = document.getElementById('importFilterCount');
  const empty = document.getElementById('importFilterEmpty');
  let filter = 'all';
  function updatePlan() {
    const query = (search ? search.value : '').trim().toLowerCase();
    let visible = 0;
    rows.forEach(function (row) {
      const match = (filter === 'all' || row.dataset.action === filter) && (!query || row.dataset.search.includes(query));
      row.hidden = !match;
      if (match) visible++;
    });
    sections.forEach(function (section) {
      const hasRows = Array.from(section.querySelectorAll('[data-import-row]')).some(function (row) { return !row.hidden; });
      section.hidden = !hasRows;
      if (hasRows && (filter !== 'all' || query)) section.open = true;
    });
    if (count) count.textContent = visible + (visible === 1 ? ' record' : ' records');
    if (empty) empty.hidden = visible !== 0;
    filters.forEach(function (button) {
      const active = button.dataset.importFilter === filter;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', String(active));
    });
  }
  filters.forEach(function (button) { button.addEventListener('click', function () { filter = button.dataset.importFilter; updatePlan(); }); });
  if (search) search.addEventListener('input', updatePlan);
})();
