(() => {
  'use strict';
  const API = window.APP.apiBase;

  const I18N = {
    es: {
      panel_title: 'Panel de transferencias', logout: 'Cerrar sesión',
      stat_total: 'Total recibidas', stat_pending: 'Pendientes', stat_checked: 'Verificadas',
      f_reference: 'N.º de referencia bancaria', ph_reference: 'Buscar referencia…', f_deposit: 'Código de depósito',
      f_dni: 'DNI', f_q: 'Nombre o correo', f_folio: 'Folio', f_bank: 'Banco', f_status: 'Estado',
      f_amount: 'Monto exacto', f_from: 'Fecha transf. desde', f_to: 'Fecha transf. hasta',
      all: 'Todos', pending: 'Pendientes', checked: 'Verificadas',
      search: 'Buscar', clear: 'Limpiar', export: 'Exportar a Excel (CSV)',
      col_check: 'Check', col_folio: 'Folio', col_received: 'Recibido', col_name: 'Nombre completo',
      col_email: 'Correo', col_dni: 'DNI', col_deposit: 'Cód. depósito', col_bank: 'Banco',
      col_date: 'Fecha transf.', col_amount: 'Monto', col_reference: 'Referencia',
      col_receipt: 'Comprobante', col_status: 'Estado',
      view: 'Ver', status_pending: 'Pendiente', status_checked: 'Verificado',
      checked_by: (who, when) => `por ${who} · ${when}`,
      no_results: 'No se encontraron transferencias con esos filtros.',
      page_info: (p, ps, t) => `Página ${p} de ${ps} · ${t} registro(s)`,
      confirm_title: 'Confirmar verificación',
      confirm_text: (r) => `¿Marcar como verificada la transferencia con referencia ${r.reference_number} (${r.folio}) de ${r.full_name}?`,
      confirm_warn: '⚠ Esta acción es definitiva: una vez marcada no se puede desmarcar ni eliminar.',
      cancel: 'Cancelar', confirm_ok: 'Sí, verificar', loading: 'Cargando…',
      network: 'No se pudo conectar con el servidor.'
    },
    en: {
      panel_title: 'Transfers panel', logout: 'Sign out',
      stat_total: 'Total received', stat_pending: 'Pending', stat_checked: 'Verified',
      f_reference: 'Bank reference no.', ph_reference: 'Search reference…', f_deposit: 'Deposit code',
      f_dni: 'ID number', f_q: 'Name or email', f_folio: 'Reference ID', f_bank: 'Bank', f_status: 'Status',
      f_amount: 'Exact amount', f_from: 'Transfer date from', f_to: 'Transfer date to',
      all: 'All', pending: 'Pending', checked: 'Verified',
      search: 'Search', clear: 'Clear', export: 'Export to Excel (CSV)',
      col_check: 'Check', col_folio: 'ID', col_received: 'Received', col_name: 'Full name',
      col_email: 'Email', col_dni: 'ID number', col_deposit: 'Deposit code', col_bank: 'Bank',
      col_date: 'Transfer date', col_amount: 'Amount', col_reference: 'Reference',
      col_receipt: 'Receipt', col_status: 'Status',
      view: 'View', status_pending: 'Pending', status_checked: 'Verified',
      checked_by: (who, when) => `by ${who} · ${when}`,
      no_results: 'No transfers match these filters.',
      page_info: (p, ps, t) => `Page ${p} of ${ps} · ${t} record(s)`,
      confirm_title: 'Confirm verification',
      confirm_text: (r) => `Mark transfer with reference ${r.reference_number} (${r.folio}) from ${r.full_name} as verified?`,
      confirm_warn: '⚠ This action is final: once checked it cannot be unchecked or deleted.',
      cancel: 'Cancel', confirm_ok: 'Yes, verify', loading: 'Loading…',
      network: 'Could not reach the server.'
    }
  };

  let T = I18N.es;
  let user = null;
  let csrf = '';
  let page = 1;
  let pages = 1;
  let rowsById = new Map();

  const $ = id => document.getElementById(id);
  const tbody = $('results-body');
  const filtersForm = $('filters');
  const alertBox = $('panel-alert');
  const dialog = $('confirm-dialog');

  /* ---------- Utilidades ---------- */
  async function api(path, opts = {}) {
    let res;
    try {
      res = await fetch(API + path, {
        credentials: 'same-origin',
        ...opts,
        headers: { Accept: 'application/json', ...(opts.headers || {}) }
      });
    } catch (e) {
      throw new Error(T.network);
    }
    const data = await res.json().catch(() => ({}));
    if (res.status === 401) {
      location.replace('login.php');
      throw new Error('401');
    }
    if (!res.ok || data.ok === false) throw new Error(data.error || `Error ${res.status}`);
    return data;
  }

  function el(tag, props = {}, children = []) {
    const node = document.createElement(tag);
    Object.entries(props).forEach(([k, v]) => {
      if (k === 'class') node.className = v;
      else if (k === 'text') node.textContent = v;
      else if (k in node) node[k] = v;
      else node.setAttribute(k, v);
    });
    [].concat(children).forEach(c => c != null && node.append(c));
    return node;
  }

  function fmtDateTime(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    return isNaN(d) ? s : d.toLocaleString(user.language === 'en' ? 'en-GB' : 'es-HN', { dateStyle: 'short', timeStyle: 'short' });
  }
  function fmtDate(s) {
    if (!s) return '';
    const [y, m, d] = s.split('-');
    return `${d}/${m}/${y}`;
  }
  function fmtAmount(n) {
    try {
      return new Intl.NumberFormat(user.language === 'en' ? 'en-US' : 'es-HN', { style: 'currency', currency: user.currency }).format(Number(n));
    } catch (e) {
      return `${user.currency} ${Number(n).toFixed(2)}`;
    }
  }
  function showAlert(msg) {
    alertBox.textContent = msg;
    alertBox.hidden = !msg;
  }
  function applyI18n() {
    document.documentElement.lang = user.language;
    document.querySelectorAll('[data-i18n]').forEach(n => {
      const v = T[n.dataset.i18n];
      if (typeof v === 'string') n.textContent = v;
    });
    document.querySelectorAll('[data-i18n-ph]').forEach(n => (n.placeholder = T[n.dataset.i18nPh] || ''));
    document.title = `${T.panel_title} · ${user.country_name}`;
  }
  function currentFilters() {
    const params = new URLSearchParams();
    new FormData(filtersForm).forEach((v, k) => { if (String(v).trim() !== '') params.set(k, String(v).trim()); });
    return params;
  }

  /* ---------- Estado / check ---------- */
  function statusCell(r) {
    if (r.checked) {
      return el('div', { class: 'status status-ok' }, [
        el('strong', { text: `✔ ${T.status_checked}` }),
        el('div', { class: 'small muted', text: T.checked_by(r.checked_by_name || '—', fmtDateTime(r.checked_at)) })
      ]);
    }
    return el('span', { class: 'status status-pending', text: T.status_pending });
  }

  function checkCell(r) {
    const box = el('input', { type: 'checkbox', class: 'big-check', checked: r.checked, disabled: r.checked });
    box.setAttribute('aria-label', `${T.col_check} ${r.folio}`);
    if (!r.checked) {
      box.addEventListener('change', async () => {
        box.checked = false; // sólo se marca si el servidor confirma
        if (!(await confirmCheck(r))) return;
        box.disabled = true;
        try {
          const data = await api('/transfers/check.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ id: r.id })
          });
          showAlert('');
          replaceRow(data.data);
          loadList(false);
        } catch (e) {
          showAlert(e.message);
          box.disabled = false;
          loadList(false); // refresca por si otro usuario ya lo verificó
        }
      });
    }
    return box;
  }

  function confirmCheck(r) {
    return new Promise(resolve => {
      $('confirm-text').textContent = T.confirm_text(r);
      dialog.returnValue = '';
      dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
      dialog.showModal();
    });
  }

  function buildRow(r) {
    rowsById.set(r.id, r);
    const receipt = el('a', {
      href: `${API}/transfers/receipt.php?id=${r.id}`, target: '_blank', rel: 'noopener', class: 'btn btn-light btn-sm',
      text: `${r.receipt_mime === 'application/pdf' ? '📄' : '🖼'} ${T.view}`
    });
    return el('tr', { class: r.checked ? 'row-checked' : '', 'data-id': r.id }, [
      el('td', { class: 'center' }, checkCell(r)),
      el('td', { class: 'mono', text: r.folio }),
      el('td', { class: 'nowrap', text: fmtDateTime(r.created_at) }),
      el('td', { text: r.full_name }),
      el('td', { text: r.email }),
      el('td', { class: 'mono', text: r.dni }),
      el('td', { class: 'mono', text: r.deposit_code }),
      el('td', { text: r.bank_name }),
      el('td', { class: 'nowrap', text: fmtDate(r.transfer_date) }),
      el('td', { class: 'num nowrap', text: fmtAmount(r.amount) }),
      el('td', { class: 'mono strong', text: r.reference_number }),
      el('td', {}, receipt),
      el('td', {}, statusCell(r))
    ]);
  }

  function replaceRow(r) {
    const old = tbody.querySelector(`tr[data-id="${r.id}"]`);
    if (old) old.replaceWith(buildRow(r));
  }

  /* ---------- Listado ---------- */
  async function loadList(showLoading = true) {
    const params = currentFilters();
    params.set('page', page);
    if (showLoading) {
      tbody.replaceChildren(el('tr', {}, el('td', { colSpan: 13, class: 'center muted', text: T.loading })));
    }
    try {
      const data = await api(`/transfers/list.php?${params}`);
      rowsById = new Map();
      pages = data.pagination.pages;
      if (data.data.length === 0) {
        tbody.replaceChildren(el('tr', {}, el('td', { colSpan: 13, class: 'center muted', text: T.no_results })));
      } else {
        tbody.replaceChildren(...data.data.map(buildRow));
      }
      $('page-info').textContent = T.page_info(data.pagination.page, data.pagination.pages, data.pagination.total);
      $('prev-btn').disabled = page <= 1;
      $('next-btn').disabled = page >= pages;
      $('stat-total').textContent = data.summary.total;
      $('stat-pending').textContent = data.summary.pending;
      $('stat-checked').textContent = data.summary.checked;
    } catch (e) {
      if (e.message !== '401') showAlert(e.message);
    }
  }

  async function loadBanks() {
    try {
      const res = await fetch(`${API}/banks.php?country=${encodeURIComponent(user.country_slug)}`);
      const data = await res.json();
      const sel = $('f-bank');
      (data.banks || []).forEach(b => sel.add(new Option(b.name, b.id)));
    } catch (e) { /* el filtro de banco es opcional */ }
  }

  /* ---------- Eventos ---------- */
  filtersForm.addEventListener('submit', e => { e.preventDefault(); page = 1; loadList(); });
  $('clear-btn').addEventListener('click', () => { filtersForm.reset(); page = 1; loadList(); });
  $('prev-btn').addEventListener('click', () => { if (page > 1) { page--; loadList(); } });
  $('next-btn').addEventListener('click', () => { if (page < pages) { page++; loadList(); } });
  $('export-btn').addEventListener('click', () => {
    window.location.href = `${API}/transfers/export.php?${currentFilters()}`;
  });
  $('logout-btn').addEventListener('click', async () => {
    try { await api('/auth/logout.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf } }); } catch (e) { /* ignorar */ }
    location.replace('login.php');
  });

  /* ---------- Inicio ---------- */
  (async () => {
    try {
      const me = await api('/auth/me.php');
      user = me.user;
      csrf = me.csrf;
      T = I18N[user.language] || I18N.es;
      applyI18n();
      $('country-badge').textContent = user.country_name;
      $('user-name').textContent = user.full_name;
      document.body.hidden = false;
      await loadBanks();
      await loadList();
      $('f-reference').focus();
    } catch (e) { /* redirigido a login */ }
  })();
})();
