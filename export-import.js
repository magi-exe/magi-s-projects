/* ============================================================
   export-import.js
   ============================================================ */
'use strict';

document.addEventListener('DOMContentLoaded', async function() {
  const { sidebar, topbar, unsavedModal } = buildChrome('Export / Import');
  document.getElementById('sidebar-root').outerHTML = sidebar;
  document.getElementById('topbar-root').outerHTML  = topbar;
  document.getElementById('modal-root').insertAdjacentHTML('beforeend', unsavedModal);
  setActiveNav(); initDateDisplay(); UnsavedTracker.init();
  ExportImport.initDnD();
});

const ExportImport = {
  importKey: null,

  buildExportParams(all = false, archive = false) {
    const p = {};
    if (!all) {
      const status = document.getElementById('ex-status').value;
      const type   = document.getElementById('ex-type').value;
      const from   = document.getElementById('ex-from').value;
      const to     = document.getElementById('ex-to').value;
      if (status) p.status        = status;
      if (type)   p.borrower_type = type;
      if (from)   p.date_from     = from;
      if (to)     p.date_to       = to;
    }
    if (archive) p.archive = '1';
    return p;
  },

  exportVisible() {
    const qs = new URLSearchParams(this.buildExportParams(false)).toString();
    window.location.href = `api/export.php${qs ? '?' + qs : ''}`;
  },
  exportAll()    { window.location.href = 'api/export.php'; },
  exportArchive(){ window.location.href = 'api/export.php?archive=1'; },

  initDnD() {
    const zone = document.getElementById('drop-zone');
    zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      const file = e.dataTransfer.files[0];
      if (file) this.processFile(file);
    });
  },

  onFileSelect(input) {
    if (input.files[0]) this.processFile(input.files[0]);
  },

  async processFile(file) {
    if (!file.name.endsWith('.csv')) { Toast.show('Only CSV files are accepted', 'error'); return; }
    const formData = new FormData();
    formData.append('csv', file);
    Toast.show('Validating file…', 'info', 2000);
    try {
      const res  = await fetch('api/import.php?action=validate', { method: 'POST', body: formData });
      const data = await res.json();
      if (data.error) { Toast.show(data.error, 'error'); return; }
      this.importKey = data.import_key;
      this.showValidation(data);
    } catch (err) {
      Toast.show(err.message, 'error');
    }
  },

  showValidation(data) {
    document.getElementById('validation-result').style.display = '';
    document.getElementById('import-result').style.display     = 'none';

    const errHtml = data.errors?.length
      ? `<div style="background:var(--red-100);color:var(--red-500);padding:10px 14px;border-radius:var(--radius-md);margin-bottom:8px">
           <strong>Errors:</strong> ${data.errors.join('; ')}
         </div>` : '';

    document.getElementById('validation-summary').innerHTML = `
      ${errHtml}
      <div style="background:var(--green-100);color:var(--green-600);padding:12px 16px;border-radius:var(--radius-md);display:flex;gap:16px;align-items:center">
        <span class="material-icons">check_circle</span>
        <div>
          <strong>File validated successfully</strong>
          <div style="font-size:.82rem">${data.total} records · ${data.duplicates?.length || 0} duplicates detected</div>
        </div>
      </div>`;

    const conflictSec = document.getElementById('conflict-section');
    if (data.duplicates?.length) {
      conflictSec.style.display = '';
      document.getElementById('conflict-tbody').innerHTML = data.duplicates.map(d =>
        `<tr><td>${d.line}</td><td>${d.borrower_name}</td><td class="mono">${d.book_code}</td><td>${d.date_taken}</td></tr>`
      ).join('');
    } else {
      conflictSec.style.display = 'none';
    }
  },

  async applyImport() {
    if (!this.importKey) { Toast.show('Please select a file first', 'warning'); return; }
    const strategy = document.querySelector('input[name="conflict"]:checked')?.value || 'skip';
    const btn = document.getElementById('apply-import-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Importing…';
    try {
      const data = await API.post('api/import.php?action=apply', {
        import_key: this.importKey, strategy
      });
      document.getElementById('validation-result').style.display = 'none';
      document.getElementById('import-result').style.display     = '';
      document.getElementById('import-result').innerHTML = `
        <div style="background:var(--green-100);color:var(--green-600);padding:14px 18px;border-radius:var(--radius-md)">
          <strong><span class="material-icons" style="vertical-align:middle;font-size:18px">check_circle</span> Import Complete</strong>
          <div style="margin-top:8px;font-size:.875rem">
            Inserted: <strong>${data.inserted}</strong> &nbsp;·&nbsp;
            Skipped: <strong>${data.skipped}</strong> &nbsp;·&nbsp;
            Overwritten: <strong>${data.overwritten}</strong>
          </div>
        </div>`;
      Toast.show(`Import complete: ${data.inserted} inserted`, 'success');
      this.importKey = null;
    } catch {}
    btn.disabled = false;
    btn.innerHTML = '<span class="material-icons">check</span>Apply Import';
  },

  resetImport() {
    this.importKey = null;
    document.getElementById('validation-result').style.display = 'none';
    document.getElementById('import-result').style.display     = 'none';
    document.getElementById('csv-input').value = '';
  },
};
