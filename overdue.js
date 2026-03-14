/* overdue.js v5 */
'use strict';

document.addEventListener('DOMContentLoaded', async function() {
  var chrome = buildChrome('Overdue');
  document.getElementById('sidebar-root').outerHTML = chrome.sidebar;
  document.getElementById('topbar-root').outerHTML  = chrome.topbar;
  document.getElementById('modal-root').insertAdjacentHTML('beforeend', chrome.unsavedModal);
  setActiveNav(); initDateDisplay(); UnsavedTracker.init();

  var role = await Auth.check(true);
  if (role) Overdue.load();
});

var Overdue = {
  type: 'all',
  sortCol: 'days_overdue', sortDir: 'desc',
  page: 0, limit: 50,
  searchTimer: null,

  setType(t) {
    this.type = t; this.page = 0;
    var types = ['all','teacher','student','reading_club'];
    document.querySelectorAll('#od-type-tabs .filter-tab').forEach(function(el,i){ el.classList.toggle('active',types[i]===t); });
    this.load();
  },

  debounceSearch() {
    clearTimeout(this.searchTimer);
    var self = this;
    this.searchTimer = setTimeout(function(){ self.page = 0; self.load(); }, 300);
  },

  sort(col) {
    if (this.sortCol === col) this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    else { this.sortCol = col; this.sortDir = 'desc'; }
    this.page = 0;
    document.querySelectorAll('#od-table thead th').forEach(function(th){ th.classList.remove('sorted'); });
    var th = document.querySelector('#od-table th[data-col="'+col+'"]');
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
    if (this.type !== 'all') params.filter = this.type;
    var search = document.getElementById('od-search')?.value.trim();
    if (search) params.search = search;

    try {
      var res = await API.get('api/overdue.php', params);
      // Update stat cards
      document.getElementById('od-teacher').textContent = res.stats.teacher;
      document.getElementById('od-student').textContent = res.stats.student;
      document.getElementById('od-club').textContent    = res.stats.reading_club;
      // Render table
      this.renderTable(res.data || [], res.total || 0, res.return_periods || {});
    } catch(e) {}
  },

  renderTable(rows, total, rp) {
    var tbody = document.getElementById('od-tbody');
    var isAdmin = Auth.role === 'admin';
    var typeLabels = {teacher: i18n.t('lbl_teacher'), student: i18n.t('lbl_student'), reading_club: i18n.t('mode_reading_club')};

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-400)">'+
        '<span class="material-icons" style="font-size:40px;display:block;margin-bottom:8px;opacity:.4">check_circle</span>'+
        '<p data-i18n="overdue_none">No overdue records!</p></td></tr>';
    } else {
      tbody.innerHTML = rows.map(function(r) {
        var daysOver = r.days_overdue || 0;
        var severity = daysOver > 30 ? 'high' : daysOver > 14 ? 'medium' : 'low';
        var typeBadge = '<span class="type-badge '+r.borrower_type+'">'+(typeLabels[r.borrower_type]||r.borrower_type)+'</span>';
        var daysBadge = '<span class="days-overdue-badge '+severity+'"><span class="material-icons" style="font-size:11px">schedule</span>'+daysOver+'d</span>';

        var actions = '';
        if (isAdmin) {
          actions = '<button class="btn btn-sm btn-success" onclick="Overdue.openReturn('+r.id+')" title="Mark Returned">'+
            '<span class="material-icons" style="font-size:13px">check</span></button>';
        }

        return '<tr class="overdue-severity-'+severity+'">'+
          '<td><strong>'+r.borrower_name+'</strong></td>'+
          '<td>'+typeBadge+'</td>'+
          '<td>'+r.book_title+'</td>'+
          '<td class="code-cell">'+r.book_code+'</td>'+
          '<td>'+DateFmt.fmt(r.date_taken)+'</td>'+
          '<td>'+daysBadge+'</td>'+
          '<td><div class="row-actions">'+actions+'</div></td>'+
        '</tr>';
      }).join('');
    }

    var start = this.page * this.limit + 1;
    var end = Math.min(start + rows.length - 1, total);
    document.getElementById('od-table-info').textContent = rows.length ? (start+' – '+end+' of '+total) : '0 records';
    document.getElementById('od-prev-btn').disabled = this.page === 0;
    document.getElementById('od-next-btn').disabled = end >= total;
  },

  prevPage() { if(this.page>0) { this.page--; this.load(); } },
  nextPage() { this.page++; this.load(); },

  openReturn(id) {
    document.getElementById('od-return-id').value = id;
    document.getElementById('od-return-date').value = DateFmt.nowLocal();
    document.getElementById('od-return-notes').value = '';
    Modal.open('od-return-modal');
  },

  async confirmReturn() {
    var id = document.getElementById('od-return-id').value;
    var date = document.getElementById('od-return-date').value;
    var notes = document.getElementById('od-return-notes').value;
    try {
      await API.put('api/borrow.php', {status:'returned', return_date:date, return_notes:notes}, {id:id});
      Toast.show('Marked as returned','success');
      Modal.close('od-return-modal');
      this.load();
    } catch(e) {}
  }
};
