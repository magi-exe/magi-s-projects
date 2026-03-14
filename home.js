/* home.js v5 – Merged Home with Borrow Log */
'use strict';

document.addEventListener('DOMContentLoaded', async function() {
  var chrome = buildChrome('Home');
  document.getElementById('sidebar-root').outerHTML = chrome.sidebar;
  document.getElementById('topbar-root').outerHTML  = chrome.topbar;
  document.getElementById('modal-root').insertAdjacentHTML('beforeend', chrome.unsavedModal);
  setActiveNav(); initDateDisplay(); UnsavedTracker.init();

  var role = await Auth.check(true);
  if (!role) return;

  var ok = await checkConn();
  if (ok) {
    BF.init();
    Activity.load('all', 0, true);
    BorrowLog.load();
  }
});

async function checkConn() {
  var banner = document.getElementById('conn-banner');
  if (location.protocol === 'file:') {
    showBanner(banner,'error','⚠ Opened as a file. Use <code>http://localhost/comboni/</code> via XAMPP.');
    return false;
  }
  try {
    var res  = await fetch('api/settings.php');
    var text = await res.text();
    var data = JSON.parse(text);
    if (data.error) { showBanner(banner,'error','⚠ DB error: '+data.error+' — Import schema.sql in phpMyAdmin.'); return false; }
    if (banner) banner.style.display = 'none';
    return true;
  } catch(e) {
    showBanner(banner,'error','⚠ Cannot reach Apache. Start XAMPP → Apache + MySQL.');
    return false;
  }
}
function showBanner(el,type,html) {
  if(!el) return;
  el.style.display='flex'; el.className='conn-banner '+type;
  el.innerHTML='<span class="material-icons">'+(type==='error'?'error':'check_circle')+'</span><span>'+html+'</span>';
}

/* ── Borrow Form ─────────────────────────────────────────────── */
var BF = {
  mode: 'teacher',

  init() {
    document.getElementById('date-taken-input').value = DateFmt.nowLocal();
    document.getElementById('bulk-date-input').value  = DateFmt.nowLocal();
    this.setupBorrowerAC();
    this.setupBookAC();
    this.setupBulkBorrowerAC();
    this.addBulkRow();
    this.addBulkRow();
  },

  setMode(m) {
    this.mode = m;
    document.getElementById('mode-teacher').classList.toggle('active', m==='teacher');
    document.getElementById('mode-student').classList.toggle('active', m==='student');
    document.getElementById('mode-reading_club').classList.toggle('active', m==='reading_club');
    document.getElementById('mode-bulk').classList.toggle('active',    m==='bulk');
    document.getElementById('borrow-form').style.display   = m==='bulk' ? 'none' : '';
    document.getElementById('bulk-panel').style.display    = m==='bulk' ? '' : 'none';
    var labels = {teacher: i18n.t('lbl_teacher'), student: i18n.t('lbl_student'), reading_club: i18n.t('mode_reading_club')};
    document.getElementById('borrower-label').textContent  = labels[m] || labels.teacher;
    document.getElementById('borrower-input').placeholder  = m==='student' ? 'Type student name…' : m==='reading_club' ? 'Type member name…' : 'Type teacher name…';
    var lnk = document.getElementById('add-borrower-link');
    lnk.style.display = m==='teacher' ? '' : 'none';
    document.getElementById('borrower-input').value = '';
    document.getElementById('borrower-id').value    = '';
  },

  setupBorrowerAC() {
    var self = this;
    setupAutocomplete({
      input: document.getElementById('borrower-input'),
      dropdown: document.getElementById('borrower-dropdown'),
      fetchFn: function(q) {
        if (self.mode==='teacher') return API.get('api/teacher.php',{q:q});
        return API.get('api/student.php',{q:q});
      },
      renderItem: function(it) {
        if (self.mode==='teacher')
          return '<div class="avatar teacher" style="width:24px;height:24px;font-size:.65rem;flex-shrink:0">'+initials(it.name)+'</div><div><strong>'+it.name+'</strong><div class="item-sub">'+(it.department||'')+'</div></div>';
        return '<div style="flex:1"><strong>'+it.name+'</strong><span class="item-sub"> · '+(it.class||'')+'</span></div><span class="freq-badge">'+it.borrow_count+'×</span>';
      },
      onSelect: function(it) {
        document.getElementById('borrower-input').value = it.name;
        if (self.mode==='teacher') document.getElementById('borrower-id').value = it.id;
        document.getElementById('borrower-error').classList.add('hidden');
        document.getElementById('borrower-input').classList.remove('error');
      }
    });
  },

  setupBookAC() {
    setupAutocomplete({
      input: document.getElementById('book-title-input'),
      dropdown: document.getElementById('book-dropdown'),
      fetchFn: function(q){ return API.get('api/book.php',{q:q}); },
      renderItem: function(it) {
        return '<div style="flex:1"><strong>'+it.title+'</strong><div class="item-sub">'+(it.author||'')+'</div></div><span class="mono" style="font-size:.72rem;color:var(--blue-600)">'+it.code+'</span>';
      },
      onSelect: function(it) {
        document.getElementById('book-title-input').value = it.title;
        document.getElementById('book-code-input').value  = it.code;
        document.getElementById('book-id').value          = it.id;
        document.getElementById('book-title-error').classList.add('hidden');
      }
    });
  },

  setupBulkBorrowerAC() {
    setupAutocomplete({
      input: document.getElementById('bulk-borrower-input'),
      dropdown: document.getElementById('bulk-borrower-dropdown'),
      fetchFn: function(q) {
        var t = document.getElementById('bulk-borrower-type').value;
        return t==='teacher' ? API.get('api/teacher.php',{q:q}) : API.get('api/student.php',{q:q});
      },
      renderItem: function(it){ return '<strong>'+it.name+'</strong><span class="item-sub"> '+(it.department||it.class||'')+'</span>'; },
      onSelect: function(it) {
        document.getElementById('bulk-borrower-input').value = it.name;
        document.getElementById('bulk-borrower-id').value   = it.id || '';
      }
    });
  },

  setBulkType(t) { document.getElementById('bulk-borrower-type').value = t; document.getElementById('bulk-borrower-input').value=''; document.getElementById('bulk-borrower-id').value=''; },

  bulkRowCount: 0,
  addBulkRow() {
    var n = ++this.bulkRowCount;
    var wrap = document.getElementById('bulk-rows');
    var div = document.createElement('div');
    div.className = 'bulk-row'; div.id = 'bulk-row-'+n;
    div.innerHTML = [
      '<span class="bulk-row-num">'+n+'</span>',
      '<div style="position:relative;flex:1">',
        '<input type="text" class="form-control" id="br-title-'+n+'" placeholder="Book title…" autocomplete="off">',
        '<div class="autocomplete-dropdown" id="br-drop-'+n+'" style="display:none"></div>',
        '<input type="hidden" id="br-code-'+n+'">',
      '</div>',
      '<input type="text" class="form-control mono" id="br-codevis-'+n+'" placeholder="Code" style="width:100px">',
      '<button class="btn btn-sm btn-danger btn-icon" onclick="BF.removeBulkRow('+n+')" title="Remove">',
        '<span class="material-icons">close</span>',
      '</button>'
    ].join('');
    wrap.appendChild(div);
    setupAutocomplete({
      input: document.getElementById('br-title-'+n),
      dropdown: document.getElementById('br-drop-'+n),
      fetchFn: function(q){ return API.get('api/book.php',{q:q}); },
      renderItem: function(it){ return '<strong>'+it.title+'</strong><span class="item-sub"> '+it.code+'</span>'; },
      onSelect: function(it) {
        document.getElementById('br-title-'+n).value   = it.title;
        document.getElementById('br-codevis-'+n).value = it.code;
        document.getElementById('br-code-'+n).value    = it.code;
      }
    });
    document.getElementById('br-codevis-'+n).addEventListener('input', function() {
      document.getElementById('br-code-'+n).value = this.value;
    });
  },

  removeBulkRow(n) {
    var el = document.getElementById('bulk-row-'+n);
    if (el) el.remove();
  },

  async submitBulk() {
    var borrowerName = document.getElementById('bulk-borrower-input').value.trim();
    var borrowerId   = document.getElementById('bulk-borrower-id').value;
    var borrowerType = document.getElementById('bulk-borrower-type').value;
    var date         = document.getElementById('bulk-date-input').value;
    if (!borrowerName) { Toast.show('Enter a borrower name', 'error'); return; }
    if (borrowerType==='teacher' && !borrowerId) { Toast.show('Select teacher from dropdown', 'error'); return; }

    var rows = document.querySelectorAll('.bulk-row');
    var toSave = [];
    rows.forEach(function(row) {
      var n     = row.id.replace('bulk-row-','');
      var title = document.getElementById('br-title-'+n)?.value.trim();
      var code  = document.getElementById('br-codevis-'+n)?.value.trim();
      if (title && code) toSave.push({title:title, code:code});
    });
    if (!toSave.length) { Toast.show('Add at least one book row', 'error'); return; }

    var btn = document.getElementById('bulk-save-btn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Saving '+toSave.length+'…';

    var saved = 0, failed = 0;
    for (var i=0; i<toSave.length; i++) {
      try {
        await API.post('api/borrow.php', {
          borrower_type: borrowerType, borrower_id: borrowerId || null,
          borrower_name: borrowerName, book_title: toSave[i].title,
          book_code: toSave[i].code, date_taken: date
        });
        saved++;
      } catch(e) { failed++; }
    }
    Toast.show('Saved '+saved+' borrows'+(failed?' ('+failed+' failed)':''), failed?'warning':'success');
    document.getElementById('bulk-rows').innerHTML = '';
    BF.bulkRowCount = 0; BF.addBulkRow(); BF.addBulkRow();
    document.getElementById('bulk-borrower-input').value = '';
    document.getElementById('bulk-borrower-id').value    = '';
    Activity.load('all', 0, true);
    BorrowLog.load();
    btn.disabled = false; btn.innerHTML = '<span class="material-icons">save</span><span data-i18n="bulk_submit">Save All Borrows</span>';
  },

  validate() {
    var ok = true;
    var borrower = document.getElementById('borrower-input').value.trim();
    var title    = document.getElementById('book-title-input').value.trim();
    var code     = document.getElementById('book-code-input').value.trim();
    if (!borrower) { show('borrower-error','Required'); ok=false; }
    else if (this.mode==='teacher' && !document.getElementById('borrower-id').value) {
      show('borrower-error', i18n.t('err_select_teacher')); ok=false;
    }
    if (!title) { document.getElementById('book-title-error').classList.remove('hidden'); document.getElementById('book-title-input').classList.add('error'); ok=false; }
    if (!code)  { document.getElementById('book-code-error').classList.remove('hidden');  document.getElementById('book-code-input').classList.add('error');  ok=false; }
    function show(id, msg) {
      document.getElementById(id).classList.remove('hidden');
      var msgEl = document.getElementById(id.replace('-error','-err-msg')||id);
      if (msgEl && msg) msgEl.textContent = msg;
      var inputId = id.replace('-error','-input');
      var inp = document.getElementById(inputId)||document.getElementById('borrower-input');
      if(inp) inp.classList.add('error');
    }
    return ok;
  },

  async submit(e) {
    e.preventDefault(); if (!this.validate()) return;
    var btn = document.getElementById('save-btn');
    btn.disabled=true; btn.innerHTML='<span class="spinner"></span>';
    try {
      await API.post('api/borrow.php', {
        borrower_type: this.mode,
        borrower_id:   document.getElementById('borrower-id').value || null,
        borrower_name: document.getElementById('borrower-input').value.trim(),
        book_id:       document.getElementById('book-id').value || null,
        book_title:    document.getElementById('book-title-input').value.trim(),
        book_code:     document.getElementById('book-code-input').value.trim(),
        date_taken:    document.getElementById('date-taken-input').value,
        notes:         document.getElementById('notes-input').value.trim(),
      });
      Toast.show('Borrow saved!', 'success');
      this.clear();
      Activity.load(Activity.filter, 0, true);
      BorrowLog.load();
    } catch(e) {}
    btn.disabled=false; btn.innerHTML='<span class="material-icons">save</span><span data-i18n="btn_save">Save</span>';
  },

  clear() {
    ['borrower-input','book-title-input','book-code-input','notes-input'].forEach(function(id){ var el=document.getElementById(id); if(el){el.value='';el.classList.remove('error');} });
    ['borrower-id','book-id'].forEach(function(id){ var el=document.getElementById(id); if(el) el.value=''; });
    document.getElementById('date-taken-input').value = DateFmt.nowLocal();
    ['borrower-error','book-title-error','book-code-error'].forEach(function(id){ document.getElementById(id)?.classList.add('hidden'); });
  },

  openAddBorrower() {
    document.getElementById('nt-name').value  = document.getElementById('borrower-input').value;
    document.getElementById('nt-dept').value  = '';
    document.getElementById('nt-phone').value = '';
    Modal.open('add-teacher-modal');
  },

  async saveNewTeacher() {
    var name = document.getElementById('nt-name').value.trim();
    if (!name) { Toast.show('Name required','error'); return; }
    try {
      var t = await API.post('api/teacher.php',{name:name,department:document.getElementById('nt-dept').value,phone:document.getElementById('nt-phone').value});
      document.getElementById('borrower-input').value = t.name;
      document.getElementById('borrower-id').value    = t.id;
      Modal.close('add-teacher-modal'); Toast.show('Teacher added','success');
    } catch(e) {}
  },

  openAddBook() {
    document.getElementById('nb-title').value  = document.getElementById('book-title-input').value;
    document.getElementById('nb-code').value   = document.getElementById('book-code-input').value;
    document.getElementById('nb-author').value = '';
    Modal.open('add-book-modal');
  },

  async saveNewBook() {
    var title = document.getElementById('nb-title').value.trim();
    var code  = document.getElementById('nb-code').value.trim();
    if (!title||!code) { Toast.show('Title and code required','error'); return; }
    try {
      await API.post('api/book.php',{title:title,code:code,author:document.getElementById('nb-author').value});
      document.getElementById('book-title-input').value = title;
      document.getElementById('book-code-input').value  = code;
      Modal.close('add-book-modal'); Toast.show('Book added','success');
    } catch(e) {}
  }
};

/* ── Activity Feed ───────────────────────────────────────────── */
var Activity = {
  filter:'all', offset:0, limit:20,

  setFilter(f) {
    this.filter=f;
    var filters=['all','taken','returned','teacher','student','reading_club'];
    document.querySelectorAll('#activity-tabs .filter-tab').forEach(function(el,i){ el.classList.toggle('active',filters[i]===f); });
    this.load(f,0,true);
  },

  async load(filter, offset, reset) {
    filter=filter||'all'; offset=offset||0;
    if(reset) this.offset=0;
    try {
      var data = await API.get('api/activity.php',{filter:filter,limit:this.limit,offset:this.offset});
      document.getElementById('stat-total').textContent = data.total_taken;
      document.getElementById('stat-today').textContent = data.taken_today;
      // Load overdue count
      try {
        var od = await API.get('api/overdue.php',{limit:1,offset:0});
        document.getElementById('stat-overdue').textContent = (od.stats.teacher + od.stats.student + od.stats.reading_club);
      } catch(e2) { document.getElementById('stat-overdue').textContent = '—'; }
      var list = document.getElementById('activity-list');
      if(reset) list.innerHTML='';
      if(!data.events.length&&reset) {
        list.innerHTML='<div class="empty-state"><span class="material-icons">history_toggle_off</span><p>No activity yet</p></div>';
        document.getElementById('load-more-btn').style.display='none'; return;
      }
      var isAdmin = Auth.role==='admin';
      data.events.forEach(function(ev){
        list.insertAdjacentHTML('beforeend', Activity.renderCard(ev, isAdmin));
      });
      document.getElementById('load-more-btn').style.display = data.events.length<Activity.limit?'none':'';
    } catch(e) {}
  },

  loadMore() { this.offset+=this.limit; this.load(this.filter,this.offset,false); },

  renderCard(ev, isAdmin) {
    var type=ev.borrower_type, avInit=initials(ev.borrower_name), ago=DateFmt.timeAgo(ev.updated_at), badge=statusBadge(ev.status);
    var undoBtn = (isAdmin && ev.status==='taken')
      ? '<button class="btn btn-sm btn-success" onclick="Activity.undo('+ev.id+')" title="Mark Returned"><span class="material-icons" style="font-size:13px">undo</span></button>'
      : '';
    return '<div class="activity-card">'+
      '<div class="avatar '+type+'">'+avInit+'</div>'+
      '<div class="activity-info">'+
        '<div class="activity-name">'+ev.borrower_name+'</div>'+
        '<div class="activity-book">'+ev.book_title+' <span class="book-code">'+ev.book_code+'</span></div>'+
        '<div class="activity-meta">'+badge+'<span class="activity-time">'+ago+'</span></div>'+
      '</div>'+
      '<div class="activity-actions">'+undoBtn+'</div>'+
    '</div>';
  },

  async undo(id) {
    try {
      await API.put('api/borrow.php',{status:'returned',return_date:new Date().toISOString().slice(0,16)},{id:id});
      Toast.show('Marked as returned','success');
      this.load(this.filter,0,true);
      BorrowLog.load();
    } catch(e) {}
  }
};

/* ── Merged Borrow Log ───────────────────────────────────────── */
var BorrowLog = {
  type: 'all',
  sortCol: 'date_taken', sortDir: 'desc',
  page: 0, limit: 50,
  selected: new Set(),
  filterTimer: null,
  data: [],

  setType(t) {
    this.type = t; this.page = 0;
    var types = ['all','teacher','student','reading_club'];
    document.querySelectorAll('#log-type-tabs .filter-tab').forEach(function(el,i){ el.classList.toggle('active',types[i]===t); });
    this.load();
  },

  debounceFilter() {
    clearTimeout(this.filterTimer);
    var self = this;
    this.filterTimer = setTimeout(function(){ self.applyFilters(); }, 300);
  },

  applyFilters() { this.page = 0; this.load(); },

  clearFilters() {
    document.getElementById('log-status').value = '';
    document.getElementById('log-date-from').value = '';
    document.getElementById('log-date-to').value = '';
    document.getElementById('log-search').value = '';
    this.page = 0; this.load();
  },

  sort(col) {
    if (this.sortCol === col) this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    else { this.sortCol = col; this.sortDir = 'asc'; }
    this.page = 0;
    // Update header visuals
    document.querySelectorAll('#log-table thead th').forEach(function(th){
      th.classList.remove('sorted');
    });
    var th = document.querySelector('#log-table th[data-col="'+col+'"]');
    if (th) {
      th.classList.add('sorted');
      var icon = th.querySelector('.sort-icon');
      if (icon) icon.textContent = this.sortDir === 'asc' ? 'expand_less' : 'expand_more';
    }
    this.load();
  },

  async load() {
    var params = {
      sort: this.sortCol, dir: this.sortDir,
      limit: this.limit, offset: this.page * this.limit
    };
    if (this.type !== 'all') params.borrower_type = this.type;
    var st = document.getElementById('log-status').value;
    if (st) params.status = st;
    var df = document.getElementById('log-date-from').value;
    var dt = document.getElementById('log-date-to').value;
    if (df) params.date_from = df;
    if (dt) params.date_to = dt;
    var search = document.getElementById('log-search').value.trim();
    if (search) {
      params.borrower_name = search;
      params.book_title = search;
    }

    try {
      var res = await API.get('api/borrow.php', params);
      this.data = res.data || [];
      var total = res.total || 0;
      this.renderTable(this.data, total);
    } catch(e) {}
  },

  renderTable(rows, total) {
    var tbody = document.getElementById('log-tbody');
    var isAdmin = Auth.role === 'admin';
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px;color:var(--gray-400)"><span class="material-icons" style="font-size:36px;display:block;margin-bottom:8px;opacity:.4">inbox</span>No records found</td></tr>';
    } else {
      var typeLabels = {teacher: i18n.t('lbl_teacher'), student: i18n.t('lbl_student'), reading_club: i18n.t('mode_reading_club')};
      tbody.innerHTML = rows.map(function(r) {
        var chk = isAdmin ? '<input type="checkbox" data-id="'+r.id+'" onchange="BorrowLog.onCheck()"'+(BorrowLog.selected.has(String(r.id))?' checked':'')+'>' : '';
        var typeBadge = '<span class="type-badge '+r.borrower_type+'">'+(typeLabels[r.borrower_type]||r.borrower_type)+'</span>';
        var actions = '';
        if (isAdmin) {
          if (r.status === 'taken') {
            actions += '<button class="btn btn-sm btn-success" onclick="BorrowLog.openReturn('+r.id+')" title="Mark Returned"><span class="material-icons" style="font-size:13px">check</span></button>';
          } else {
            actions += '<button class="btn btn-sm btn-secondary" onclick="BorrowLog.markTaken('+r.id+')" title="Mark Taken"><span class="material-icons" style="font-size:13px">undo</span></button>';
          }
          actions += '<button class="btn btn-sm btn-ghost" onclick="BorrowLog.showAudit('+r.id+')" title="History"><span class="material-icons" style="font-size:13px">history</span></button>';
          actions += '<button class="btn btn-sm btn-danger" onclick="BorrowLog.deleteRecord('+r.id+')" title="Delete"><span class="material-icons" style="font-size:13px">delete</span></button>';
        }
        return '<tr>'+
          '<td class="checkbox-cell">'+chk+'</td>'+
          '<td><strong>'+r.borrower_name+'</strong></td>'+
          '<td>'+typeBadge+'</td>'+
          '<td>'+DateFmt.fmt(r.date_taken)+'</td>'+
          '<td>'+r.book_title+'</td>'+
          '<td class="code-cell">'+r.book_code+'</td>'+
          '<td>'+statusBadge(r.status)+'</td>'+
          '<td>'+(r.days_stayed||'—')+'</td>'+
          '<td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+(r.notes||'')+'">'+(r.notes||'—')+'</td>'+
          '<td><div class="row-actions">'+actions+'</div></td>'+
        '</tr>';
      }).join('');
    }
    // Pagination info
    var start = this.page * this.limit + 1;
    var end = Math.min(start + rows.length - 1, total);
    document.getElementById('log-table-info').textContent = rows.length ? (start+' – '+end+' of '+total) : '0 records';
    document.getElementById('log-prev-btn').disabled = this.page === 0;
    document.getElementById('log-next-btn').disabled = end >= total;
    // Reset select all
    var selAll = document.getElementById('log-select-all');
    if (selAll) selAll.checked = false;
  },

  prevPage() { if(this.page>0) { this.page--; this.load(); } },
  nextPage() { this.page++; this.load(); },

  // Selection
  onCheck() {
    var checks = document.querySelectorAll('#log-tbody input[type=checkbox]');
    this.selected.clear();
    checks.forEach(function(c) { if(c.checked) BorrowLog.selected.add(c.dataset.id); });
    this.updateBulkBar();
  },

  toggleSelectAll(el) {
    var checks = document.querySelectorAll('#log-tbody input[type=checkbox]');
    var self = this;
    this.selected.clear();
    checks.forEach(function(c) { c.checked = el.checked; if(el.checked) self.selected.add(c.dataset.id); });
    this.updateBulkBar();
  },

  clearSelection() {
    this.selected.clear();
    var checks = document.querySelectorAll('#log-tbody input[type=checkbox]');
    checks.forEach(function(c) { c.checked = false; });
    var selAll = document.getElementById('log-select-all');
    if (selAll) selAll.checked = false;
    this.updateBulkBar();
  },

  updateBulkBar() {
    var bar = document.getElementById('log-bulk-bar');
    if (this.selected.size > 0) {
      bar.classList.add('visible');
      document.getElementById('log-bulk-count').textContent = this.selected.size + ' selected';
    } else {
      bar.classList.remove('visible');
    }
  },

  // Return
  openReturn(id) {
    document.getElementById('return-record-id').value = id;
    document.getElementById('return-date-input').value = DateFmt.nowLocal();
    document.getElementById('return-notes-input').value = '';
    Modal.open('return-modal');
  },

  async confirmReturn() {
    var id = document.getElementById('return-record-id').value;
    var date = document.getElementById('return-date-input').value;
    var notes = document.getElementById('return-notes-input').value;
    try {
      await API.put('api/borrow.php', {status:'returned', return_date:date, return_notes:notes}, {id:id});
      Toast.show('Marked as returned','success');
      Modal.close('return-modal');
      this.load();
      Activity.load(Activity.filter, 0, true);
    } catch(e) {}
  },

  async markTaken(id) {
    try {
      await API.put('api/borrow.php', {status:'taken', return_date:null, return_notes:null}, {id:id});
      Toast.show('Marked as taken','success');
      this.load();
      Activity.load(Activity.filter, 0, true);
    } catch(e) {}
  },

  async deleteRecord(id) {
    var ok = await confirmAction('Are you sure you want to delete this record?');
    if (!ok) return;
    try {
      await API.delete('api/borrow.php', {id:id});
      Toast.show('Record deleted','success');
      this.load();
      Activity.load(Activity.filter, 0, true);
    } catch(e) {}
  },

  async bulkReturn() {
    if (!this.selected.size) return;
    var ids = Array.from(this.selected);
    var ok = 0;
    for (var i=0; i<ids.length; i++) {
      try {
        await API.put('api/borrow.php', {status:'returned',return_date:new Date().toISOString().slice(0,16)}, {id:ids[i]});
        ok++;
      } catch(e) {}
    }
    Toast.show(ok+' records marked returned','success');
    this.clearSelection();
    this.load();
    Activity.load(Activity.filter, 0, true);
  },

  // Audit
  async showAudit(id) {
    try {
      var data = await API.get('api/audit.php', {record_id:id});
      var body = document.getElementById('audit-body');
      if (!data.length) {
        body.innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:20px">No edit history</p>';
      } else {
        body.innerHTML = data.map(function(e) {
          return '<div style="padding:10px 0;border-bottom:1px solid var(--gray-100)">'+
            '<div style="font-size:.8rem;color:var(--gray-400)">'+DateFmt.fmtDT(e.timestamp)+' by '+e.editor_name+'</div>'+
            '<div style="font-size:.875rem;margin-top:4px"><strong>'+e.field_changed+'</strong>: <del style="color:var(--red-500)">'+
            (e.old_value||'—')+'</del> → <ins style="color:var(--green-600);text-decoration:none">'+(e.new_value||'—')+'</ins></div></div>';
        }).join('');
      }
      Modal.open('audit-modal');
    } catch(e) {}
  },

  // Export
  exportVisible() {
    if (!this.data.length) { Toast.show('No data to export','warning'); return; }
    var csv = 'borrower_type,borrower_name,book_title,book_code,date_taken,status,return_date,notes,return_notes\n';
    this.data.forEach(function(r) {
      csv += [r.borrower_type,'"'+r.borrower_name+'"','"'+r.book_title+'"',r.book_code,
              r.date_taken,r.status,r.return_date||'','"'+(r.notes||'')+'"','"'+(r.return_notes||'')+'"'].join(',') + '\n';
    });
    var blob = new Blob([csv], {type:'text/csv'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'borrow_records_'+new Date().toISOString().slice(0,10)+'.csv';
    a.click();
  },

  bulkExport() {
    if (!this.selected.size) return;
    var ids = this.selected;
    var rows = this.data.filter(function(r){ return ids.has(String(r.id)); });
    if (!rows.length) { Toast.show('No matching data','warning'); return; }
    var csv = 'borrower_type,borrower_name,book_title,book_code,date_taken,status,return_date,notes\n';
    rows.forEach(function(r) {
      csv += [r.borrower_type,'"'+r.borrower_name+'"','"'+r.book_title+'"',r.book_code,
              r.date_taken,r.status,r.return_date||'','"'+(r.notes||'')+'"'].join(',') + '\n';
    });
    var blob = new Blob([csv], {type:'text/csv'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'selected_records_'+new Date().toISOString().slice(0,10)+'.csv';
    a.click();
  }
};
