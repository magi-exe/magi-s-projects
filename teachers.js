/* ============================================================
   teachers.js – Teachers' Log page
   ============================================================ */
'use strict';

document.addEventListener('DOMContentLoaded', async function() {
  const { sidebar, topbar, unsavedModal } = buildChrome("Teachers' Log");
  document.getElementById('sidebar-root').outerHTML = sidebar;
  document.getElementById('topbar-root').outerHTML  = topbar;
  document.getElementById('modal-root').insertAdjacentHTML('beforeend', unsavedModal);
  setActiveNav(); initDateDisplay(); UnsavedTracker.init();
  
  Auth.check(false).then(function(r){ if(r) TeacherLog.loadCards(); });
});

const TeacherLog = {
  currentTeacher: null,
  records: [],
  total: 0,
  page: 0,
  limit: 25,
  sortCol: 'date_taken',
  sortDir: 'desc',
  selected: new Set(),

  /* ── Teacher Cards ──────────────────────────────────────────── */
  async loadCards() {
    try {
      const teachers = await API.get('api/teacher.php');
      const grid = document.getElementById('teacher-grid');
      grid.innerHTML = `<div class="teacher-card-add" onclick="TeacherLog.openAddTeacher()">
        <span class="material-icons">person_add</span>Add New Teacher</div>`;
      teachers.forEach(t => {
        const card = document.createElement('div');
        card.className = 'teacher-card';
        card.dataset.id = t.id;
        card.innerHTML = `
          <div class="tc-avatar">${initials(t.name)}</div>
          <div class="tc-name">${t.name}</div>
          <div class="tc-dept">${t.department || '—'}</div>
          <div class="tc-stats">
            <div class="tc-stat"><strong>${t.current_borrows}</strong>Current</div>
            <div class="tc-stat"><strong>${t.total_borrows}</strong>Total</div>
          </div>`;
        card.addEventListener('click', () => this.selectTeacher(t));
        grid.appendChild(card);
      });
    } catch {}
  },

  selectTeacher(t) {
    this.currentTeacher = t;
    this.page = 0;
    document.querySelectorAll('.teacher-card').forEach(c =>
      c.classList.toggle('selected', +c.dataset.id === +t.id));
    document.getElementById('detail-title').innerHTML =
      `<span class="material-icons" style="color:var(--blue-600)">school</span> ${t.name}'s Borrow Records`;
    document.getElementById('teacher-detail').style.display = '';
    this.loadRecords();
  },

  closeDetail() {
    this.currentTeacher = null;
    document.getElementById('teacher-detail').style.display = 'none';
    document.querySelectorAll('.teacher-card').forEach(c => c.classList.remove('selected'));
  },

  applyFilters() { this.page = 0; this.loadRecords(); },
  clearFilters() {
    ['f-status','f-date-from','f-date-to','f-book'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    this.applyFilters();
  },

  buildParams() {
    const p = {
      borrower_type: 'teacher',
      limit: this.limit,
      offset: this.page * this.limit,
      sort: this.sortCol,
      dir: this.sortDir,
    };
    if (this.currentTeacher) p.borrower_id = this.currentTeacher.id;
    const status = document.getElementById('f-status')?.value;
    const from   = document.getElementById('f-date-from')?.value;
    const to     = document.getElementById('f-date-to')?.value;
    const book   = document.getElementById('f-book')?.value;
    if (status) p.status    = status;
    if (from)   p.date_from = from;
    if (to)     p.date_to   = to;
    if (book)   p.book_title = book;
    return p;
  },

  async loadRecords() {
    const tbody = document.getElementById('teacher-tbody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="padding:30px"><span class="spinner"></span></td></tr>`;
    try {
      const data = await API.get('api/borrow.php', this.buildParams());
      this.records = data.data;
      this.total   = data.total;
      this.renderTable();
    } catch {}
  },

  renderTable() {
    const tbody = document.getElementById('teacher-tbody');
    if (!this.records.length) {
      tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state">
        <span class="material-icons">inbox</span><p>No records found</p></div></td></tr>`;
      document.getElementById('table-info').textContent = '';
      return;
    }
    tbody.innerHTML = this.records.map(r => this.renderRow(r)).join('');
    const start = this.page * this.limit + 1;
    const end   = Math.min(start + this.records.length - 1, this.total);
    document.getElementById('table-info').textContent = `Showing ${start}–${end} of ${this.total}`;
    document.getElementById('prev-btn').disabled = this.page === 0;
    document.getElementById('next-btn').disabled = end >= this.total;
    this.updateBulkBar();
  },

  renderRow(r) {
    const overdue = r.status === 'taken' && r.days_stayed > 14;
    return `<tr data-id="${r.id}" class="${r.deleted_at ? 'deleted' : ''}">
      <td class="checkbox-cell"><input type="checkbox" class="row-check" value="${r.id}"
          ${this.selected.has(String(r.id)) ? 'checked' : ''} onchange="TeacherLog.onCheckbox(this)"></td>
      <td>${r.borrower_name}</td>
      <td>${DateFmt.fmtDT(r.date_taken)}</td>
      <td>${r.book_title}</td>
      <td class="code-cell">${r.book_code}</td>
      <td>${statusBadge(r.status)}</td>
      <td>${overdue ? `<span class="badge badge-overdue">${r.days_stayed}d</span>` : `${r.days_stayed}d`}</td>
      <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${r.notes || '—'}</td>
      <td>
        <div class="row-actions">
          ${r.status === 'taken' ? `<button class="btn btn-sm btn-success" onclick="TeacherLog.openReturn(${r.id})" title="Mark Returned">
            <span class="material-icons">check_circle</span></button>` : ''}
          <button class="btn btn-sm btn-secondary" onclick="TeacherLog.openEdit(${r.id})" title="Edit">
            <span class="material-icons">edit</span></button>
          <button class="btn btn-sm btn-secondary" onclick="TeacherLog.showAudit(${r.id})" title="View History">
            <span class="material-icons">history</span></button>
          <button class="btn btn-sm btn-danger" onclick="TeacherLog.deleteRecord(${r.id})" title="Delete">
            <span class="material-icons">delete</span></button>
        </div>
      </td>
    </tr>`;
  },

  sort(col) {
    if (this.sortCol === col) {
      this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortCol = col; this.sortDir = 'asc';
    }
    document.querySelectorAll('thead th.sortable').forEach(th => {
      th.classList.toggle('sorted', th.dataset.col === col);
      if (th.dataset.col === col) {
        th.querySelector('.sort-icon').textContent = this.sortDir === 'asc' ? 'arrow_upward' : 'arrow_downward';
      } else {
        th.querySelector('.sort-icon').textContent = 'unfold_more';
      }
    });
    this.page = 0; this.loadRecords();
  },

  prevPage() { if (this.page > 0) { this.page--; this.loadRecords(); } },
  nextPage() { this.page++; this.loadRecords(); },

  onCheckbox(el) {
    el.checked ? this.selected.add(el.value) : this.selected.delete(el.value);
    this.updateBulkBar();
  },

  toggleSelectAll(el) {
    document.querySelectorAll('.row-check').forEach(c => {
      c.checked = el.checked;
      el.checked ? this.selected.add(c.value) : this.selected.delete(c.value);
    });
    this.updateBulkBar();
  },

  clearSelection() {
    this.selected.clear();
    document.querySelectorAll('.row-check').forEach(c => c.checked = false);
    document.getElementById('select-all').checked = false;
    this.updateBulkBar();
  },

  updateBulkBar() {
    const bar = document.getElementById('bulk-bar');
    bar.classList.toggle('visible', this.selected.size > 0);
    document.getElementById('bulk-count').textContent = `${this.selected.size} selected`;
  },

  async bulkReturn() {
    if (!this.selected.size) return;
    const confirmed = await confirmAction(`Mark ${this.selected.size} records as returned?`);
    if (!confirmed) return;
    const returnDate = new Date().toISOString().slice(0,16);
    for (const id of this.selected) {
      await API.put('api/borrow.php', { status: 'returned', return_date: returnDate }, { id });
    }
    Toast.show(`${this.selected.size} records marked returned`, 'success');
    this.clearSelection(); this.loadRecords();
  },

  bulkExport() {
    const ids = [...this.selected].join(',');
    window.location.href = `api/export.php?ids=${ids}`;
  },

  exportSelected() {
    const p = this.buildParams();
    const qs = new URLSearchParams(p).toString();
    window.location.href = `api/export.php?${qs}`;
  },

  openReturn(id) {
    document.getElementById('return-record-id').value = id;
    document.getElementById('return-date-input').value = DateFmt.nowLocal();
    document.getElementById('return-notes-input').value = '';
    Modal.open('return-modal');
  },

  async confirmReturn() {
    const id    = document.getElementById('return-record-id').value;
    const date  = document.getElementById('return-date-input').value;
    const notes = document.getElementById('return-notes-input').value;
    try {
      await API.put('api/borrow.php', { status: 'returned', return_date: date, return_notes: notes }, { id });
      Modal.close('return-modal');
      Toast.show('Marked as returned', 'success');
      this.loadRecords();
    } catch {}
  },

  openEdit(id) {
    const r = this.records.find(r => r.id == id);
    if (!r) return;
    Modal.create({
      id: 'edit-record-modal',
      title: 'Edit Borrow Record',
      icon: 'edit',
      body: `
        <div class="form-row">
          <div class="form-group full"><label>Book Title</label>
            <input class="form-control" id="er-title" value="${r.book_title}"></div>
          <div class="form-group"><label>Book Code</label>
            <input class="form-control mono" id="er-code" value="${r.book_code}"></div>
          <div class="form-group"><label>Date Taken</label>
            <input type="datetime-local" class="form-control" id="er-date" value="${r.date_taken?.slice(0,16)}"></div>
          <div class="form-group full"><label>Notes</label>
            <textarea class="form-control" id="er-notes" rows="2">${r.notes || ''}</textarea></div>
        </div>`,
      onConfirm: async () => {
        await API.put('api/borrow.php', {
          book_title: document.getElementById('er-title').value,
          book_code:  document.getElementById('er-code').value,
          date_taken: document.getElementById('er-date').value,
          notes:      document.getElementById('er-notes').value,
        }, { id });
        Toast.show('Record updated', 'success');
        this.loadRecords();
      },
      confirmLabel: '<span class="material-icons">save</span>Save Changes',
    });
    Modal.open('edit-record-modal');
  },

  async showAudit(id) {
    const logs = await API.get('api/audit.php', { record_id: id });
    const body = document.getElementById('audit-body');
    if (!logs.length) {
      body.innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:20px">No edit history</p>';
    } else {
      body.innerHTML = logs.map(l => `
        <div class="audit-entry">
          <span class="audit-field">${l.field_changed}</span> changed by <strong>${l.editor_name}</strong>
          <div class="audit-change">
            <del>${l.old_value || '(empty)'}</del>
            <span class="material-icons" style="font-size:14px">arrow_forward</span>
            <ins>${l.new_value || '(empty)'}</ins>
          </div>
          <div style="font-size:.72rem;color:var(--gray-400)">${DateFmt.fmtDT(l.timestamp)}</div>
        </div>`).join('');
    }
    Modal.open('audit-modal');
  },

  async deleteRecord(id) {
    const confirmed = await confirmAction('Soft-delete this record? It will be hidden but not permanently removed.');
    if (!confirmed) return;
    await API.delete('api/borrow.php', { id });
    Toast.show('Record deleted', 'success');
    this.loadRecords();
  },

  openAddTeacher() {
    document.getElementById('nt-name').value  = '';
    document.getElementById('nt-dept').value  = '';
    document.getElementById('nt-phone').value = '';
    Modal.open('add-teacher-modal');
  },

  async saveTeacher() {
    const name = document.getElementById('nt-name').value.trim();
    if (!name) { Toast.show('Name required', 'error'); return; }
    try {
      await API.post('api/teacher.php', {
        name, department: document.getElementById('nt-dept').value,
        phone: document.getElementById('nt-phone').value,
      });
      Toast.show('Teacher added', 'success');
      Modal.close('add-teacher-modal');
      this.loadCards();
    } catch {}
  },
};

// ── Status Toggle: returned → taken ──────────────────────────
TeacherLog.openRetake = async function(id) {
  if (!await confirmAction('Change status back to "Taken"?')) return;
  try {
    await API.put('api/borrow.php', {status:'taken', return_date:null, return_notes:null}, {id:id});
    Toast.show('Status changed to Taken', 'success');
    TeacherLog.loadRecords();
  } catch(e) {}
};

// Patch renderRow to include retake button and show history
var _origRenderRow = TeacherLog.renderRow.bind(TeacherLog);
TeacherLog.renderRow = function(r) {
  var html = _origRenderRow(r);
  // Add retake button for returned records
  html = html.replace(
    '</div>\n      </td>',
    (r.status==='returned' ? '<button class="btn btn-sm btn-secondary" onclick="TeacherLog.openRetake('+r.id+')" title="Mark Taken"><span class="material-icons">replay</span></button>' : '') +
    '</div>\n      </td>'
  );
  return html;
};
