/*
 * NAi browser: an external site framed inside the NAi shell.
 * The frame is a direct browser-to-site connection. NAi does not proxy,
 * intercept, or store anything typed into it.
 */
(function () {
  const root = document.getElementById('arenaBrowser');
  if (!root) return;

  const frame = document.getElementById('arenaFrame');
  const address = document.getElementById('browserAddress');
  const form = document.getElementById('browserAddressForm');
  const loading = document.getElementById('browserLoading');
  const blocked = document.getElementById('browserBlocked');
  const blockedReason = document.getElementById('browserBlockedReason');
  const blockedOpen = document.getElementById('browserBlockedOpen');
  const hint = document.getElementById('browserHint');
  const hintText = document.getElementById('browserHintText');
  const hintLink = document.getElementById('browserHintLink');
  const openTab = document.getElementById('browserOpenTab');
  const backBtn = document.getElementById('browserBack');
  const forwardBtn = document.getElementById('browserForward');
  const home = root.dataset.home;
  const probeUrl = root.dataset.probe;

  // History of addresses NAi itself navigated to. A cross-origin frame does not
  // expose its internal history, so back/forward walk this list only.
  const stack = [home];
  let cursor = 0;
  let loadTimer = null;
  let probeToken = 0;

  function setHint(text, tone) {
    if (!hint) return;
    hintText.textContent = text;
    hint.dataset.tone = tone || 'info';
    hint.hidden = false;
  }

  function syncButtons() {
    backBtn.disabled = cursor <= 0;
    forwardBtn.disabled = cursor >= stack.length - 1;
    const url = stack[cursor];
    address.value = url;
    openTab.href = url;
    if (hintLink) hintLink.href = url;
    if (blockedOpen) {
      blockedOpen.href = url;
      const label = blockedOpen.querySelector('span');
      let host = url;
      try { host = new URL(url).host; } catch (e) {}
      if (label) label.textContent = 'Open ' + host + ' in a tab';
    }
  }

  function showLoading() {
    if (loading) loading.hidden = false;
    clearTimeout(loadTimer);
    loadTimer = setTimeout(function () {
      if (loading) loading.hidden = true;
      setHint('This page is taking a while to appear. It may be blocking embedding, or your browser may be blocking third-party cookies — “Open in tab” always works.', 'warn');
    }, 12000);
  }

  function hideLoading() {
    clearTimeout(loadTimer);
    if (loading) loading.hidden = true;
  }

  function showBlocked(reason) {
    hideLoading();
    if (blockedReason) blockedReason.textContent = reason || 'This site refuses to be embedded in another site.';
    if (blocked) blocked.hidden = false;
    // Drop the src so the browser's broken-page icon is never what the user sees.
    frame.removeAttribute('src');
    setHint((reason || 'This site refuses to be embedded.') + ' Browsers enforce this, so NAi cannot override it — use “Open in tab” to sign in and work.', 'warn');
  }

  function clearBlocked() {
    if (blocked) blocked.hidden = true;
  }

  // Render the frame for a URL we believe is embeddable.
  function loadFrame(url) {
    clearBlocked();
    showLoading();
    frame.src = url;
  }

  function navigate(url, addToHistory) {
    if (addToHistory !== false) {
      stack.splice(cursor + 1);
      stack.push(url);
      cursor = stack.length - 1;
    }
    syncButtons();
    check(url, { load: true });
  }

  // Ask the server whether the destination allows framing. `load` decides if an
  // embeddable answer should actually render the frame.
  function check(url, opts) {
    opts = opts || {};
    if (!probeUrl) { if (opts.load) loadFrame(url); return; }

    const token = ++probeToken;
    if (opts.load) showLoading();

    const qs = '?url=' + encodeURIComponent(url) + (opts.recheck ? '&recheck=1' : '');
    fetch(probeUrl + qs, { headers: { Accept: 'application/json' } })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (token !== probeToken) return; // a newer navigation won
        if (!data) { if (opts.load) loadFrame(url); return; }

        if (data.status === 'blocked') {
          showBlocked(data.reason);
          return;
        }
        if (data.status === 'allowed') {
          setHint('Loading inside NAi. Sign in on the framed page itself — NAi never sees your credentials.', 'ok');
        }
        // 'unknown' (offline/timeout) still attempts the frame: the browser is
        // the final authority and the load timeout explains a blank panel.
        if (opts.load) loadFrame(data.final_url || url);
      })
      .catch(function () {
        if (token !== probeToken) return;
        if (opts.load) loadFrame(url);
      });
  }

  frame.addEventListener('load', function () {
    // A frame with no src fires load in some browsers; ignore those.
    if (!frame.getAttribute('src')) return;
    hideLoading();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    let value = address.value.trim();
    if (!value) return;
    if (!/^https?:\/\//i.test(value)) value = 'https://' + value;
    try {
      const parsed = new URL(value);
      if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') throw new Error('scheme');
      navigate(parsed.toString());
    } catch (err) {
      setHint('That is not a valid http(s) address.', 'warn');
    }
  });

  backBtn.addEventListener('click', function () {
    if (cursor <= 0) return;
    cursor -= 1;
    navigate(stack[cursor], false);
  });

  forwardBtn.addEventListener('click', function () {
    if (cursor >= stack.length - 1) return;
    cursor += 1;
    navigate(stack[cursor], false);
  });

  document.getElementById('browserReload').addEventListener('click', function () {
    check(stack[cursor], { load: true, recheck: true });
  });

  document.getElementById('browserHome').addEventListener('click', function () {
    navigate(home);
  });

  const retryBtn = document.getElementById('browserBlockedRetry');
  if (retryBtn) retryBtn.addEventListener('click', function () {
    retryBtn.disabled = true;
    check(stack[cursor], { load: true, recheck: true });
    setTimeout(function () { retryBtn.disabled = false; }, 1500);
  });

  // Escape hatch: the probe can be wrong (HEAD blocked, GET differs, bot wall).
  // Let the user attempt the embed and see the browser's own verdict.
  const forceBtn = document.getElementById('browserBlockedForce');
  if (forceBtn) forceBtn.addEventListener('click', function () {
    setHint('Trying the frame anyway. If it stays blank, the refusal is real — “Open in tab” always works.', 'info');
    loadFrame(stack[cursor]);
  });

  const focusBtn = document.getElementById('browserFocus');
  const exitFocus = document.getElementById('browserExitFocus');
  function toggleFocus(on) {
    document.body.classList.toggle('browser-focus', on);
  }
  focusBtn.addEventListener('click', function () { toggleFocus(!document.body.classList.contains('browser-focus')); });
  if (exitFocus) exitFocus.addEventListener('click', function () { toggleFocus(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') toggleFocus(false);
  });

  const fullscreenBtn = document.getElementById('browserFullscreen');
  fullscreenBtn.addEventListener('click', function () {
    if (document.fullscreenElement) {
      document.exitFullscreen();
    } else if (root.requestFullscreen) {
      root.requestFullscreen().catch(function () {
        setHint('Fullscreen was blocked by the browser. Focus mode still hides the NAi navigation.', 'warn');
      });
    }
  });

  const hintClose = document.getElementById('browserHintClose');
  if (hintClose) hintClose.addEventListener('click', function () { hint.hidden = true; });

  syncButtons();

  // The server already probed before first paint. Only start the spinner when a
  // frame is actually loading; a known refusal must not show a loading state.
  if (root.dataset.status === 'blocked') {
    hideLoading();
  } else {
    showLoading();
  }
})();
