/* NAi sign-in: decorative neural mesh + form affordances.
   No frameworks, no network calls, no tracking. Degrades to a plain form without JS. */
(function () {
  'use strict';
  document.documentElement.classList.add('js');

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- neural mesh ---------- */
  var canvas = document.getElementById('loginMesh');
  if (canvas && canvas.getContext) {
    var ctx = canvas.getContext('2d');
    var nodes = [];
    var w = 0, h = 0, dpr = 1;
    var pointer = { x: -9999, y: -9999 };
    var running = true;
    var LINK_DIST = 148;

    function resize() {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = canvas.clientWidth;
      h = canvas.clientHeight;
      canvas.width = Math.floor(w * dpr);
      canvas.height = Math.floor(h * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      seed();
    }

    function seed() {
      // Density scales with area but stays bounded for low-power devices.
      var count = Math.max(26, Math.min(78, Math.round((w * h) / 22000)));
      nodes = [];
      for (var i = 0; i < count; i++) {
        nodes.push({
          x: Math.random() * w,
          y: Math.random() * h,
          vx: (Math.random() - 0.5) * 0.22,
          vy: (Math.random() - 0.5) * 0.22,
          r: Math.random() * 1.6 + 0.9
        });
      }
    }

    function hue(x) {
      // cyan -> indigo -> pink across the canvas, matching the NAi ribbon
      var t = Math.max(0, Math.min(1, x / Math.max(1, w)));
      if (t < 0.5) return [56 + (129 - 56) * (t * 2), 189 + (140 - 189) * (t * 2), 248];
      return [129 + (236 - 129) * ((t - 0.5) * 2), 140 + (72 - 140) * ((t - 0.5) * 2), 248 + (153 - 248) * ((t - 0.5) * 2)];
    }

    function frame() {
      ctx.clearRect(0, 0, w, h);

      for (var i = 0; i < nodes.length; i++) {
        var n = nodes[i];
        if (!reduceMotion) {
          n.x += n.vx;
          n.y += n.vy;
          if (n.x < -20) n.x = w + 20; else if (n.x > w + 20) n.x = -20;
          if (n.y < -20) n.y = h + 20; else if (n.y > h + 20) n.y = -20;
        }

        var dxp = n.x - pointer.x, dyp = n.y - pointer.y;
        var near = Math.sqrt(dxp * dxp + dyp * dyp) < 170;
        var c = hue(n.x);

        for (var j = i + 1; j < nodes.length; j++) {
          var m = nodes[j];
          var dx = n.x - m.x, dy = n.y - m.y;
          var d = Math.sqrt(dx * dx + dy * dy);
          if (d < LINK_DIST) {
            var alpha = (1 - d / LINK_DIST) * 0.22;
            ctx.strokeStyle = 'rgba(' + Math.round(c[0]) + ',' + Math.round(c[1]) + ',' + Math.round(c[2]) + ',' + alpha.toFixed(3) + ')';
            ctx.lineWidth = 0.7;
            ctx.beginPath();
            ctx.moveTo(n.x, n.y);
            ctx.lineTo(m.x, m.y);
            ctx.stroke();
          }
        }

        ctx.beginPath();
        ctx.fillStyle = 'rgba(' + Math.round(c[0]) + ',' + Math.round(c[1]) + ',' + Math.round(c[2]) + ',' + (near ? 0.95 : 0.55) + ')';
        ctx.arc(n.x, n.y, near ? n.r * 1.7 : n.r, 0, Math.PI * 2);
        ctx.fill();

        if (near) {
          ctx.strokeStyle = 'rgba(' + Math.round(c[0]) + ',' + Math.round(c[1]) + ',' + Math.round(c[2]) + ',0.18)';
          ctx.lineWidth = 0.7;
          ctx.beginPath();
          ctx.moveTo(n.x, n.y);
          ctx.lineTo(pointer.x, pointer.y);
          ctx.stroke();
        }
      }
      if (running && !reduceMotion) requestAnimationFrame(frame);
    }

    resize();
    frame();

    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () { resize(); if (reduceMotion) frame(); }, 180);
    });

    window.addEventListener('pointermove', function (e) {
      var rect = canvas.getBoundingClientRect();
      pointer.x = e.clientX - rect.left;
      pointer.y = e.clientY - rect.top;
    }, { passive: true });
    window.addEventListener('pointerleave', function () { pointer.x = -9999; pointer.y = -9999; });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { running = false; }
      else if (!reduceMotion) { running = true; requestAnimationFrame(frame); }
    });
  }

  /* ---------- password visibility ---------- */
  var pw = document.getElementById('password');
  var toggle = document.getElementById('pwToggle');
  var EYE = '<svg class="icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg class="icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.2A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-2.4 3.3"/><path d="M6.2 6.7A16.6 16.6 0 0 0 2 12s3.5 7 10 7c1.6 0 3-.4 4.3-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
  if (pw && toggle) {
    toggle.addEventListener('click', function () {
      var shown = pw.type === 'text';
      pw.type = shown ? 'password' : 'text';
      toggle.innerHTML = shown ? EYE : EYE_OFF;
      toggle.setAttribute('aria-pressed', shown ? 'false' : 'true');
      toggle.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
      pw.focus();
    });
  }

  /* ---------- caps lock warning ---------- */
  var caps = document.getElementById('capsHint');
  if (pw && caps) {
    var checkCaps = function (e) {
      var on = typeof e.getModifierState === 'function' && e.getModifierState('CapsLock');
      caps.hidden = !on;
    };
    pw.addEventListener('keydown', checkCaps);
    pw.addEventListener('keyup', checkCaps);
    pw.addEventListener('blur', function () { caps.hidden = true; });
  }

  /* ---------- access help disclosure ---------- */
  var help = document.getElementById('accessHelp');
  var helpNote = document.getElementById('accessHelpNote');
  if (help && helpNote) {
    help.addEventListener('click', function () {
      var open = helpNote.hidden;
      helpNote.hidden = !open;
      help.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------- after a failed attempt, put the cursor where the fix is ---------- */
  var emailField = document.getElementById('email');
  if (document.getElementById('loginError') && pw && emailField && emailField.value) {
    pw.focus();
  }

  /* ---------- submit feedback (prevents double posts) ---------- */
  var form = document.getElementById('loginForm');
  var submit = document.getElementById('loginSubmit');
  if (form && submit) {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) {
        e.preventDefault();
        var firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) { firstInvalid.setAttribute('aria-invalid', 'true'); firstInvalid.focus(); }
        return;
      }
      if (submit.disabled) { e.preventDefault(); return; }
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      submit.innerHTML = '<span class="login-spinner"></span><span>Signing in…</span>';
      // Safety net: re-enable if the browser restores the page from cache.
      window.addEventListener('pageshow', function () {
        submit.disabled = false;
        submit.removeAttribute('aria-busy');
      });
    });
  }
})();
