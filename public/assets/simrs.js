/* SIMRS RSUD NTB — interaksi & animasi ringan (tanpa library). */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var body = document.body;

  /* ---------- Laci menu (mobile) ---------- */
  var toggle = document.querySelector('.nav-toggle');
  var backdrop = document.querySelector('.backdrop');
  function setNav(open) {
    body.classList.toggle('nav-open', open);
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (toggle) toggle.addEventListener('click', function () { setNav(!body.classList.contains('nav-open')); });
  if (backdrop) backdrop.addEventListener('click', function () { setNav(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });
  document.querySelectorAll('.sidebar .nav a').forEach(function (a) {
    a.addEventListener('click', function () { setNav(false); });
  });
  // geser dari tepi kiri untuk membuka, geser ke kiri untuk menutup
  var sx = null, sy = null;
  document.addEventListener('touchstart', function (e) { var t = e.touches[0]; sx = t.clientX; sy = t.clientY; }, { passive: true });
  document.addEventListener('touchend', function (e) {
    if (sx === null || !toggle || getComputedStyle(toggle).display === 'none') return;
    var t = e.changedTouches[0], dx = t.clientX - sx, dy = Math.abs(t.clientY - sy);
    if (dy < 50) {
      if (sx < 24 && dx > 60) setNav(true);
      if (body.classList.contains('nav-open') && dx < -60) setNav(false);
    }
    sx = null;
  }, { passive: true });

  /* ---------- Topbar berbayang saat digulir ---------- */
  var topbar = document.querySelector('.topbar');
  if (topbar) {
    var onScroll = function () { topbar.classList.toggle('scrolled', window.scrollY > 4); };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  }

  /* ---------- Jam & sapaan ---------- */
  var clock = document.querySelector('[data-clock]');
  if (clock) {
    var hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    var bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    var tick = function () {
      var d = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
      clock.querySelector('b').textContent = p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
      clock.querySelector('span').textContent = hari[d.getDay()] + ', ' + d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear();
    };
    tick(); setInterval(tick, 1000);
  }
  var greet = document.querySelector('[data-greet]');
  if (greet) {
    var h = new Date().getHours();
    greet.textContent = h < 11 ? 'Selamat pagi' : h < 15 ? 'Selamat siang' : h < 18 ? 'Selamat sore' : 'Selamat malam';
  }

  /* ---------- Satu momen masuk: konten tersusun bertahap ---------- */
  var content = document.querySelector('.content');
  if (content && !reduce) {
    Array.prototype.forEach.call(content.children, function (el, i) { el.style.setProperty('--i', Math.min(i, 8)); });
    content.classList.add('enter');
  }

  /* ---------- Flash → toast ---------- */
  var stack = document.createElement('div');
  stack.className = 'toast-stack'; stack.setAttribute('aria-live', 'polite');
  body.appendChild(stack);
  function dismiss(t) {
    if (t.classList.contains('out')) return;
    t.classList.add('out');
    setTimeout(function () { t.remove(); }, 380);
  }
  function toast(message, type) {
    var t = document.createElement('div');
    t.className = 'toast-item ' + (type || 'info');
    t.setAttribute('role', type === 'error' ? 'alert' : 'status');
    var icon = { success: '✓', error: '!', warning: '!', info: 'i' }[type] || 'i';
    var life = type === 'error' ? 0 : 6000;
    t.innerHTML = '<span class="t-ico" aria-hidden="true">' + icon + '</span><div class="t-msg"></div>' +
      '<button class="t-close" type="button" aria-label="Tutup">×</button>' +
      (life ? '<span class="t-bar" style="animation-duration:' + life + 'ms"></span>' : '');
    t.querySelector('.t-msg').textContent = message;
    t.querySelector('.t-close').addEventListener('click', function () { dismiss(t); });
    stack.appendChild(t);
    if (life) {
      var timer = setTimeout(function () { dismiss(t); }, life);
      t.addEventListener('mouseenter', function () { clearTimeout(timer); var b = t.querySelector('.t-bar'); if (b) b.style.animationPlayState = 'paused'; });
      t.addEventListener('mouseleave', function () { timer = setTimeout(function () { dismiss(t); }, 2500); });
    }
  }
  window.simrsToast = toast;
  document.querySelectorAll('.content .flash').forEach(function (f) {
    var type = f.classList.contains('error') ? 'error' : f.classList.contains('warning') ? 'warning' : f.classList.contains('success') ? 'success' : 'info';
    var msg = f.textContent.trim();
    if (!msg) return;
    // pesan error juga tetap terlihat di halaman agar tidak terlewat
    if (type !== 'error') f.remove();
    toast(msg, type);
  });

  /* ---------- Ripple pada tombol & menu ---------- */
  document.addEventListener('pointerdown', function (e) {
    if (reduce) return;
    var el = e.target.closest('.btn, .wfx-btn, .nav a');
    if (!el || el.disabled) return;
    var r = el.getBoundingClientRect(), size = Math.max(r.width, r.height);
    var s = document.createElement('span');
    s.className = 'ripple';
    s.style.width = s.style.height = size + 'px';
    s.style.left = (e.clientX - r.left - size / 2) + 'px';
    s.style.top = (e.clientY - r.top - size / 2) + 'px';
    el.appendChild(s);
    setTimeout(function () { s.remove(); }, 650);
  });

  /* ---------- Tombol kirim: tampilkan proses ---------- */
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return; // mis. confirm() dibatalkan
    var form = e.target;
    var btn = e.submitter || form.querySelector('button[type="submit"], button:not([type])');
    if (!btn || !btn.matches('.btn, .wfx-btn')) return;
    if (form.dataset.sent) { e.preventDefault(); return; } // cegah kirim ganda
    form.dataset.sent = '1';
    btn.classList.add('is-loading');
    setTimeout(function () { form.dataset.sent = ''; btn.classList.remove('is-loading'); }, 15000);
  });
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); });
    document.querySelectorAll('form[data-sent]').forEach(function (f) { f.dataset.sent = ''; });
  });

  /* ---------- Angka statistik menghitung naik ---------- */
  function countUp(el) {
    var raw = el.textContent.trim();
    var m = raw.match(/^(\D*?)([\d.]+)(\D*)$/);
    if (!m) return;
    var target = parseInt(m[2].replace(/\./g, ''), 10);
    if (!isFinite(target) || target === 0 || reduce) return;
    var fmt = function (n) { return m[1] + n.toLocaleString('id-ID') + m[3]; };
    var start = null, dur = Math.min(1400, 500 + target * 20);
    el.textContent = fmt(0);
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / dur), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(Math.round(target * eased));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  document.querySelectorAll('.stat .value, .wfx-metric strong').forEach(countUp);

  /* ---------- Warna status otomatis ---------- */
  var ok = /(completed|paid|validated|signed|approved|lunas|selesai|aktif|diberikan|tersedia)/i;
  var bad = /(rejected|cancel|ditolak|batal|need_revision|revision|tidak_diberikan|nonaktif)/i;
  var wait = /(ordered|draft|menunggu|needs|await|partially|submitted|terdaftar|ditunda)/i;
  var live = /(in_progress|proses|result_entered|reviewed)/i;
  document.querySelectorAll('.wfx-status').forEach(function (s) {
    var t = s.textContent.replace(/\s+/g, '_');
    if (bad.test(t)) s.classList.add('s-bad');
    else if (ok.test(t)) s.classList.add('s-ok');
    else if (live.test(t)) s.classList.add('s-live');
    else if (wait.test(t)) s.classList.add('s-wait');
  });

  /* ---------- Tabel: label kartu mobile + pencarian cepat ---------- */
  document.querySelectorAll('.table-wrap, .wfx-table-wrap').forEach(function (wrap) {
    var table = wrap.querySelector('table');
    if (!table) return;
    var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
    rows.forEach(function (tr) {
      Array.prototype.forEach.call(tr.children, function (td, i) {
        if (!td.hasAttribute('data-label') && heads[i] !== undefined && !td.hasAttribute('colspan')) td.setAttribute('data-label', heads[i]);
      });
    });
    wrap.classList.add('stack');
    var dataRows = rows.filter(function (tr) { return !tr.querySelector('.empty, [colspan]'); });
    if (dataRows.length < 6) return;
    var bar = document.createElement('label');
    bar.className = 'table-search';
    bar.innerHTML = '<input type="search" placeholder="Cari di tabel ini" aria-label="Cari di tabel ini"><span class="count"></span>';
    wrap.parentNode.insertBefore(bar, wrap);
    var input = bar.querySelector('input'), count = bar.querySelector('.count');
    var update = function () {
      var q = input.value.trim().toLowerCase(), shown = 0;
      dataRows.forEach(function (tr) {
        var hit = !q || tr.textContent.toLowerCase().indexOf(q) !== -1;
        tr.classList.toggle('row-hidden', !hit); if (hit) shown++;
      });
      count.textContent = q ? shown + ' dari ' + dataRows.length + ' baris' : '';
    };
    input.addEventListener('input', update);
  });

  /* ---------- Textarea menyesuaikan tinggi isi ---------- */
  document.querySelectorAll('textarea').forEach(function (ta) {
    var fit = function () { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight + 2, 480) + 'px'; };
    ta.addEventListener('input', fit);
    if (ta.value) fit();
  });

  /* ---------- Login: tampilkan password & goyang saat gagal ---------- */
  document.querySelectorAll('.pw-toggle').forEach(function (b) {
    b.addEventListener('click', function () {
      var input = b.parentNode.querySelector('input');
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.textContent = show ? 'Sembunyikan' : 'Lihat';
      b.setAttribute('aria-pressed', show ? 'true' : 'false');
      input.focus();
    });
  });
  var loginErr = document.querySelector('.login-body .notice.error');
  if (loginErr && !reduce) document.querySelector('.login-card').classList.add('shake');
})();
