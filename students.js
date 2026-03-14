/* ============================================================
   students.js – Students' Log page
   ============================================================ */
'use strict';

document.addEventListener('DOMContentLoaded', async function() {
  const { sidebar, topbar, unsavedModal } = buildChrome("Students' Log");
  document.getElementById('sidebar-root').outerHTML = sidebar;
  document.getElementById('topbar-root').outerHTML  = topbar;
  document.getElementById('modal-root').insertAdjacentHTML('beforeend', unsavedModal);
  setActiveNav(); initDateDisplay(); UnsavedTracker.init();
  StudentLog.loadFrequent();
  StudentLog.initBookAC();
});

const StudentLog = {
  currentStudent: null,
  records: [],
  total: 0,
  page: 0,
  limit: 25,
  sortCol: 'date_taken',
  sortDir: 'desc',
  selected: new Set(),
  searchTimer: null,

  async search(q) {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(async () => {
      if (!q.trim()) { this.loadFrequentIntoList(); return; }
      const results = await API.get('api/student.php', { q });
      const list = document.getElementById('student-list');
      list.innerHTML = results.length
        ? results.map(s => this.renderStudentItem(s)).join('')
        : `<div class="empty-state"><span class="material-icons">search_off</span><p>No students found</p>
           <button class="btn btn-primary btn-sm mt-1" onclick="StudentLog.quickAddStudent('${q}')">Add "${q}"</button></div>`;
    }, 200);
  },

  quickAddStudent(name) {
    this.selectStudentByName(name);
  },

  async loadFrequent() {
    const students = await API.get('api/student.php');
    const list = document.getElementById('frequent-list');
    list.innerHTML = students.length
      ? students.map(s => `
        <div class="student-list-item" onclick="StudentLog.selectStudentByName('${s.name}', ${s.id})">
          <div class="sl-avatar">${initials(s.name)}</div>
          <div class="sl-info"><div class="sl-name">${s.name}</div><div class="sl-class">${s.class || '—'}</div></div>
          <span class="sl-count">${s.borrow_count}×</span>
        </div>`).join('')
      : '<div class="empty-state"><span class="material-icons">groups</span><p>No students yet</p></div>';
    this.loadFrequentIntoList();
  },

  async loadFrequentIntoList() {
    const students = await API.get('api/student.php');
    const list = document.getElementById('student-list');
    list.innerHTML = students.map(s => this.renderStudentItem(s)).join('')
      || `<div class="empty-state"><span class="material-icons">groups</span><p>No students yet</p></div>`;
  },

  renderStudentItem(s) {
    const selected = this.currentStudent?.name === s.name;
    return `<div class="student-list-item ${selected ? 'selected' : ''}" onclick="StudentLog.selectStudentByName('${s.name.replace(/'/g,"\\'")}', ${s.id || 0})">
      <div class="sl-avatar">${initials(s.name)}</div>
      <div class="sl-info">
        <div class="sl-name">${s.name}</div>
        <div class="sl-class">${s.class || '—'} · ${s.current_borrows || 0} current</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end">
        <span class="sl-count">${s.borrow_count || 0}×</span>
        <button class="btn btn-sm btn-primary" onclick="event.stopPropagation();StudentLog.selectStudentByName('${s.name.replace(/'/g,"\\'")}',${s.id||0});StudentLog.openAddBorrow()" style="font-size:.7rem;padding:3px 8px">Quick Add</button>
      </div>
    </div>`;
  },

  selectStudentByName(name, id = 0) {
    this.currentStudent = { name, id };
    this.page = 0;
    document.getElementById('student-detail-title').innerHTML =
      `<span class="material-icons" style="color:var(--gold-500)">person</span> ${name}'s Borrow Records`;
    document.getElementById('student-detail').style.display = '';
    // Update list selection
    document.querySelectorAll('.student-list-item').forEach(el => {
      el.classList.toggle('selected', el.querySelector('.sl-name')?.textContent === name);
    });
    this.loadRecords();
  },

  applyFilters() { this.page = 0; this.loadRecords(); },

  buildParams() {
    const p = {
      borrower_type: 'student',
      limit: this.limit,
      offset: this.page * this.limit,
      sort: this.sortCol,
      dir: this.sortDir,
    };
    if (this.currentStudent) p.borrower_name = this.currentStudent.name;
    const status = document.getElementById('sf-status')?.value;
    const from   = document.getElementById('sf-date-from')?.value;
    const to     = document.getElementById('sf-date-to')?.value;
    const book   = document.getElementById('sf-book')?.value;
    if (status) p.status     = status;
    if (from)   p.date_from  = from;
    if (to)     p.date_to    = to;
    if (book)   p.book_title = book;
    return p;
  },

  async loadRecords() {
    const tbody = document.getElementById('student-tbody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="padding:30px"><span class="spinner"></span></td></tr>`;
    try {
      const data = await API.get('api/borrow.php', this.buildParams());
      this.records = data.data;
      this.total   = data.total;
      this.renderTable();
    } catch {}
  },

  renderTable() {
    const tbody = document.getElementById('student-tbody');
    if (!this.records.length) {
      tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state">
        <span class="material-icons">inbox</span><p>No records</p></div></td></tr>`;
      return;
    }
    tbody.innerHTML = this.records.map(r => `
      <tr data-id="${r.id}">
        <td class="checkbox-cell"><input type="checkbox" class="row-check" value="${r.id}"
          ${this.selected.has(String(r.id)) ? 'checked' : ''} onchange="StudentLog.onCheckbox(this)"></td>
        <td>${r.borrower_name}</td>
        <td>${DateFmt.fmtDT(r.date_taken)}</td>
        <td>${r.book_title}</td>
        <td class="code-cell">${r.book_code}</td>
        <td>${statusBadge(r.status)}</td>
        <td>${r.days_stayed}d</td>
        <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis">${r.notes || '—'}</td>
        <td>
          <div class="row-actions">
            ${r.status === 'taken' ? `<button class="btn btn-sm btn-success" onclick="StudentLog.openReturn(${r.id})"><span class="material-icons">check_circle</span></button>` : ''}
            <button class="btn btn-sm btn-secondary" onclick="StudentLog.showAudit(${r.id})"><span class="material-icons">history</span></button>
            <button class="btn btn-sm btn-danger" onclick="StudentLog.deleteRecord(${r.id})"><span class="material-icons">delete</span></button>
          </div>
        </td>
      </tr>`).join('');
    const start = this.page * this.limit + 1;
    const end   = Math.min(start + this.records.length - 1, this.total);
    document.getElementById('s-table-info').textContent = `Showing ${start}–${end} of ${this.total}`;
    document.getElementById('s-prev-btn').disabled = this.page === 0;
    document.getElementById('s-next-btn').disabled = end >= this.total;
  },

  sort(col) {
    if (this.sortCol === col) this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    else { this.sortCol = col; this.sortDir = 'asc'; }
    this.page = 0; this.loadRecords();
  },

  prevPage() { if (this.page > 0) { this.page--; this.loadRecords(); } },
  nextPage() { this.page++; this.loadRecords(); },

  onCheckbox(el) { el.checked ? this.selected.add(el.value) : this.selected.delete(el.value); },
  toggleSelectAll(el) {
    document.querySelectorAll('#student-tbody .row-check').forEach(c => {
      c.checked = el.checked;
      el.checked ? this.selected.add(c.value) : this.selected.delete(c.value);
    });
  },

  exportStudent() {
    if (!this.currentStudent) return;
    window.location.href = `api/export.php?borrower_type=student&borrower_name=${encodeURIComponent(this.currentStudent.name)}`;
  },

  openAddBorrow() {
    if (!this.currentStudent) { Toast.show('Select a student first', 'warning'); return; }
    document.getElementById('ab-student-name').textContent = this.currentStudent.name;
    document.getElementById('ab-book-title').value = '';
    document.getElementById('ab-book-code').value  = '';
    document.getElementById('ab-book-id').value    = '';
    document.getElementById('ab-notes').value      = '';
    document.getElementById('ab-class').value      = '';
    document.getElementById('ab-date').value       = DateFmt.nowLocal();
    Modal.open('add-borrow-modal');
  },

  initBookAC() {
    const input    = document.getElementById('ab-book-title');
    const dropdown = document.getElementById('ab-book-dropdown');
    if (!input) return;
    setupAutocomplete({
      input, dropdown,
      fetchFn: (q) => API.get('api/book.php', { q }),
      renderItem: (item) => `<div class="flex-between"><strong>${item.title}</strong>
        <span class="mono" style="font-size:.75rem;color:var(--blue-600)">${item.code}</span></div>`,
      onSelect: (item) => {
        input.value = item.title;
        document.getElementById('ab-book-code').value = item.code;
        document.getElementById('ab-book-id').value   = item.id;
      },
    });
  },

  async saveBorrow() {
    const title = document.getElementById('ab-book-title').value.trim();
    const code  = document.getElementById('ab-book-code').value.trim();
    if (!title || !code) { Toast.show('Book title and code required', 'error'); return; }
    try {
      await API.post('api/borrow.php', {
        borrower_type: 'student',
        borrower_name: this.currentStudent.name,
        book_title: title,
        book_code:  code,
        book_id:    document.getElementById('ab-book-id').value || null,
        date_taken: document.getElementById('ab-date').value,
        notes:      document.getElementById('ab-notes').value,
      });
      Modal.close('add-borrow-modal');
      Toast.show('Borrow saved', 'success');
      this.loadRecords();
      this.loadFrequent();
    } catch {}
  },

  openReturn(id) {
    document.getElementById('s-return-id').value    = id;
    document.getElementById('s-return-date').value  = DateFmt.nowLocal();
    document.getElementById('s-return-notes').value = '';
    Modal.open('s-return-modal');
  },

  async confirmReturn() {
    const id    = document.getElementById('s-return-id').value;
    const date  = document.getElementById('s-return-date').value;
    const notes = document.getElementById('s-return-notes').value;
    await API.put('api/borrow.php', { status: 'returned', return_date: date, return_notes: notes }, { id });
    Modal.close('s-return-modal');
    Toast.show('Marked as returned', 'success');
    this.loadRecords();
  },

  async showAudit(id) {
    const logs = await API.get('api/audit.php', { record_id: id });
    const body = document.getElementById('s-audit-body');
    body.innerHTML = logs.length
      ? logs.map(l => `<div class="audit-entry">
          <span class="audit-field">${l.field_changed}</span> · <strong>${l.editor_name}</strong>
          <div class="audit-change"><del>${l.old_value||'(empty)'}</del>
            <span class="material-icons" style="font-size:14px">arrow_forward</span>
            <ins>${l.new_value||'(empty)'}</ins></div>
          <div style="font-size:.72rem;color:var(--gray-400)">${DateFmt.fmtDT(l.timestamp)}</div>
        </div>`).join('')
      : '<p style="color:var(--gray-400);text-align:center;padding:20px">No history</p>';
    Modal.open('s-audit-modal');
  },

  async deleteRecord(id) {
    if (!await confirmAction('Delete this record?')) return;
    await API.delete('api/borrow.php', { id });
    Toast.show('Deleted', 'success');
    this.loadRecords();
  },
};

// ── Status Toggle: returned → taken ──────────────────────────
StudentLog.openRetake = async function(id) {
  if (!await confirmAction('Change status back to "Taken"?')) return;
  try {
    await API.put('api/borrow.php', {status:'taken', return_date:null, return_notes:null}, {id:id});
    Toast.show('Status changed to Taken', 'success');
    StudentLog.loadRecords();
  } catch(e) {}
};
