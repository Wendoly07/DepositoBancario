(() => {
  'use strict';
  const API = window.APP.apiBase;
  const form = document.getElementById('login-form');
  const alertBox = document.getElementById('login-alert');

  // Si ya hay sesión, ir directo al panel
  fetch(`${API}/auth/me.php`, { credentials: 'same-origin' })
    .then(r => { if (r.ok) location.replace('index.php'); })
    .catch(() => {});

  form.addEventListener('submit', async e => {
    e.preventDefault();
    alertBox.hidden = true;
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      const res = await fetch(`${API}/auth/login.php`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ username: form.username.value.trim(), password: form.password.value })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || 'Error');
      location.replace('index.php');
    } catch (err) {
      alertBox.textContent = err.message === 'Failed to fetch'
        ? 'No se pudo conectar con el servidor / Could not reach the server'
        : err.message;
      alertBox.hidden = false;
      form.password.value = '';
      form.password.focus();
    } finally {
      btn.disabled = false;
    }
  });
})();
