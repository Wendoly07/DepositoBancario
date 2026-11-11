(() => {
  'use strict';
  const C = window.APP;
  const M = C.msgs;
  const form = document.getElementById('transfer-form');
  const bankSelect = document.getElementById('bank_id');
  const bankError = document.getElementById('bank-load-error');
  const alertBox = document.getElementById('form-alert');
  const submitBtn = document.getElementById('submit-btn');
  const fileInput = document.getElementById('receipt');
  const fileName = document.getElementById('file-name');
  const success = document.getElementById('success-panel');
  const MAX_BYTES = 10 * 1024 * 1024;

  /* ---------- Bancos ---------- */
  async function loadBanks() {
    bankSelect.disabled = true;
    bankError.hidden = true;
    try {
      const res = await fetch(`${C.apiBase}/banks.php?country=${encodeURIComponent(C.country)}`, {
        headers: { Accept: 'application/json' }
      });
      const data = await res.json();
      if (!res.ok || !data.ok || !Array.isArray(data.banks) || data.banks.length === 0) throw new Error('banks');
      bankSelect.length = 1;
      data.banks.forEach(b => bankSelect.add(new Option(b.name, b.id)));
      bankSelect.disabled = false;
    } catch (e) {
      bankError.hidden = false;
    }
  }
  document.getElementById('retry-banks').addEventListener('click', loadBanks);

  /* ---------- Archivo ---------- */
  fileInput.addEventListener('change', () => {
    const f = fileInput.files[0];
    fileName.textContent = f ? `${f.name} (${(f.size / 1048576).toFixed(2)} MB)` : M.none;
    setError('receipt', '');
  });

  /* ---------- Monto: sólo números y punto/coma decimal ---------- */
  const amount = document.getElementById('amount');
  amount.addEventListener('input', () => {
    amount.value = amount.value.replace(/[^\d.,]/g, '');
  });

  /* ---------- Errores ---------- */
  function setError(name, msg) {
    const box = form.querySelector(`[data-error-for="${name}"]`);
    const field = form.elements[name];
    if (box) box.textContent = msg || '';
    if (field && field.classList) {
      field.classList.toggle('is-invalid', !!msg);
      field.setAttribute('aria-invalid', msg ? 'true' : 'false');
    }
  }
  function clearErrors() {
    form.querySelectorAll('[data-error-for]').forEach(b => (b.textContent = ''));
    form.querySelectorAll('.is-invalid').forEach(f => f.classList.remove('is-invalid'));
    alertBox.hidden = true;
  }
  function showAlert(msg) {
    alertBox.textContent = msg;
    alertBox.hidden = false;
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  form.addEventListener('input', e => { if (e.target.name) setError(e.target.name, ''); });
  form.addEventListener('change', e => { if (e.target.name) setError(e.target.name, ''); });

  const val = n => (form.elements[n].value || '').trim();
  const normalizedAmount = () => val('amount').replace(/,/g, '');

  function validate() {
    const errs = {};
    ['email', 'full_name', 'dni', 'deposit_code', 'bank_id', 'transfer_date', 'amount', 'reference_number']
      .forEach(n => { if (!val(n)) errs[n] = M.required; });

    if (val('email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('email'))) errs.email = M.email;
    const amt = normalizedAmount();
    if (amt && (!/^\d{1,12}(\.\d{1,2})?$/.test(amt) || Number(amt) <= 0)) errs.amount = M.amount;
    if (val('transfer_date') && val('transfer_date') > C.today) errs.transfer_date = M.date;

    const f = fileInput.files[0];
    if (!f) errs.receipt = M.required;
    else if (f.size > MAX_BYTES) errs.receipt = M.file_size;
    else if (f.type && !(f.type === 'application/pdf' || f.type.startsWith('image/'))) errs.receipt = M.file_type;

    if (!form.elements.ownership.checked) errs.ownership = M.ownership;
    return errs;
  }

  /* ---------- Envío ---------- */
  form.addEventListener('submit', async e => {
    e.preventDefault();
    clearErrors();
    const errs = validate();
    if (Object.keys(errs).length) {
      Object.entries(errs).forEach(([k, m]) => setError(k, m));
      showAlert(M.fix);
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = M.sending;
    const fd = new FormData(form);
    fd.set('amount', normalizedAmount());

    try {
      const res = await fetch(`${C.apiBase}/submit.php`, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
      let data = {};
      try { data = await res.json(); } catch (_) { /* respuesta no JSON */ }
      if (!res.ok || !data.ok) {
        if (data.errors) Object.entries(data.errors).forEach(([k, m]) => setError(k, m));
        showAlert(data.error || M.network);
        return;
      }
      form.hidden = true;
      document.getElementById('success-folio').textContent = data.folio;
      success.hidden = false;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      showAlert(M.network);
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = M.submit;
    }
  });

  document.getElementById('another-btn').addEventListener('click', () => {
    form.reset();
    fileName.textContent = M.none;
    clearErrors();
    success.hidden = true;
    form.hidden = false;
  });

  loadBanks();
})();
