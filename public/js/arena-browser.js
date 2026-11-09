/*
 * Arena AI browser: the real Arena AI site inside the NAi shell.
 * The frame is a direct browser-to-Arena-AI connection. NAi does not proxy,
 * intercept, or store anything typed into it.
 */
(function () {
  const root = document.getElementById('arenaBrowser');
  if (!root) return;

  const frame = document.getElementById('arenaFrame');
  const address = document.getElementById('browserAddress');
  const form = document.getElementById('browserAddressForm');
  const loading = document.getElementById('browserLoading');
  const hint = document.getElementById('browserHint');
  const hintText = document.getElementById('browserHintText');
  const hintLink = document.getElementById('browserHintLink');
  const openTab = document.getElementById('browserOpenTab');
  const backBtn = document.getElementById('browserBack');
  const forwardBtn = document.getElementById('browserForward');
  const home = root.dataset.home;

  // History of addresses NAi itself navigated to. A cross-origin frame does not
  // expose its internal history, so back/forward walk this list only.
  const stack = [home];
  let cursor = 0;
  let loadTimer = null;

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
  }

  function showLoading() {
    if (loading) loading.hidden = false;
    clearTimeout(loadTimer);
    loadTimer = setTimeout(function () {
      if (loading) loading.hidden = true;
      setHint('Arena AI is taking a while to appear. It may be blocking embedding — “Open in tab” always works.', 'warn');
    }, 12000);
  }

  function navigate(url, addToHistory) {
    showLoading();
    frame.src = url;
    if (addToHistory !== false) {
      stack.splice(cursor + 1);
      stack.push(url);
      cursor = stack.length - 1;
    }
    syncButtons();
  }

  frame.addEventListener('load', function () {
    clearTimeout(loadTimer);
    if (loading) loading.hidden = true;
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
    navigate(stack[cursor], false);
  });

  document.getElementById('browserHome').addEventListener('click', function () {
    navigate(home);
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

  // Ask the server whether Arena AI is willing to be framed, so a refusal is
  // explained instead of leaving an empty panel.
  if (root.dataset.probe) {
    fetch(root.dataset.probe, { headers: { Accept: 'application/json' } })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (!data) return;
        if (data.status === 'blocked') {
          setHint((data.reason || 'Arena AI refuses to be embedded.') + ' Use “Open in tab” to sign in and work — Arena AI stays one click away while NAi keeps your workspace around it.', 'warn');
        } else if (data.status === 'allowed') {
          setHint('Arena AI is loading inside NAi. Sign in on the Arena AI page itself — NAi never sees your credentials.', 'ok');
        }
      })
      .catch(function () { /* offline or blocked: the default hint stays */ });
  }

  syncButtons();
  showLoading();
})();
