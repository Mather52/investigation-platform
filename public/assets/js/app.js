// منصة التحقيق الإداري — تفاعلات بسيطة بدون مكتبات خارجية
(function () {
  // القائمة الجانبية المخفية: <button data-nav-toggle> يفتحها، و [data-nav-close] أو Esc يغلقها
  var sidebar = document.getElementById('sidebar');
  function setNav(open) {
    if (!sidebar) { return; }
    var hadFocus = sidebar.contains(document.activeElement);
    document.body.classList.toggle('nav-open', open);
    if (open) { sidebar.removeAttribute('inert'); } else { sidebar.setAttribute('inert', ''); }
    document.querySelectorAll('[data-nav-toggle]').forEach(function (b) {
      b.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!open && hadFocus && b.offsetParent !== null) { b.focus(); }
    });
    if (open) { var first = sidebar.querySelector('.nav a'); if (first) { first.focus(); } }
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-nav-toggle]')) { e.preventDefault(); setNav(!document.body.classList.contains('nav-open')); return; }
    if (e.target.closest('[data-nav-close]')) { e.preventDefault(); setNav(false); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { setNav(false); }
  });

  // فتح النوافذ المنبثقة: <button data-open="dialogId">
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-open]');
    if (opener) {
      var dlg = document.getElementById(opener.getAttribute('data-open'));
      if (dlg && dlg.showModal) { e.preventDefault(); dlg.showModal(); }
      return;
    }
    var closer = e.target.closest('[data-close]');
    if (closer) {
      var d = closer.closest('dialog');
      if (d) { e.preventDefault(); d.close(); }
      return;
    }
    // إضافة حقل مكرر: <button data-clone="templateId" data-target="containerId">
    var cloner = e.target.closest('[data-clone]');
    if (cloner) {
      e.preventDefault();
      var tpl = document.getElementById(cloner.getAttribute('data-clone'));
      var box = document.getElementById(cloner.getAttribute('data-target'));
      if (tpl && box) { box.appendChild(tpl.content.cloneNode(true)); }
    }
  });

  // إظهار/إخفاء عناصر حسب قيمة: <div data-show-when="name=value"> أو "name!=value"
  // مع data-disable-hidden تُعطَّل حقول العنصر المخفي فلا تُرسل، ويصبح [data-required] إلزامياً عند ظهوره فقط
  function syncConditional() {
    document.querySelectorAll('[data-show-when]').forEach(function (el) {
      var rule = el.getAttribute('data-show-when');
      var negate = rule.indexOf('!=') > -1;
      var parts = rule.split(negate ? '!=' : '=');
      var input = document.querySelector('[name="' + parts[0] + '"]:checked') || document.querySelector('select[name="' + parts[0] + '"]');
      var show = !!input && (negate ? input.value !== parts[1] : input.value === parts[1]);
      el.hidden = !show;
      if (el.hasAttribute('data-disable-hidden')) {
        el.querySelectorAll('input, select, textarea').forEach(function (c) {
          c.disabled = !show;
          if (c.hasAttribute('data-required')) { c.required = show; }
        });
      }
    });
  }

  // بطاقات مصدر المعاملة: تحديث اسم المصدر ونصوص المساعدة حسب البطاقة المختارة
  function syncSource() {
    var picked = document.querySelector('input[name="source_code"]:checked');
    if (!picked) { return; }
    [['data-src-name', 'data-name'], ['data-ref-hint', 'data-hint-ref'], ['data-file-hint', 'data-hint-file']].forEach(function (m) {
      document.querySelectorAll('[' + m[0] + ']').forEach(function (out) { out.textContent = picked.getAttribute(m[1]) || ''; });
    });
  }
  document.addEventListener('change', function (e) {
    if (e.target.matches('input[name="source_code"]')) { syncSource(); }
  });
  syncSource();
  document.addEventListener('change', syncConditional);
  syncConditional();

  // عرض أسماء الملفات المختارة
  document.addEventListener('change', function (e) {
    if (e.target.matches('input[type=file]')) {
      var out = e.target.closest('.dropzone');
      if (out) {
        var names = Array.prototype.map.call(e.target.files, function (f) { return f.name; }).join('، ');
        var label = out.querySelector('[data-files]');
        if (label) { label.textContent = names || 'لم يتم اختيار ملفات'; }
      }
    }
  });

  // تأكيد قبل الإرسال: <form data-confirm="نص">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });

  // المحادثة: النزول لآخر رسالة، وتكبير مربع الكتابة مع النص، و Ctrl + Enter للإرسال
  document.querySelectorAll('[data-autoscroll]').forEach(function (t) { t.scrollTop = t.scrollHeight; });
  document.querySelectorAll('textarea[data-autogrow]').forEach(function (ta) {
    function grow() { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 180) + 'px'; }
    ta.addEventListener('input', grow);
    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && ta.value.trim() !== '') { e.preventDefault(); ta.form.requestSubmit ? ta.form.requestSubmit() : ta.form.submit(); }
    });
  });

  // إظهار/إخفاء كلمة المرور: <button data-pw-toggle="inputId">
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pw-toggle]');
    if (!b) { return; }
    var input = document.getElementById(b.getAttribute('data-pw-toggle'));
    if (!input) { return; }
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
  });

  // تمرير المحادثة للأسفل
  document.querySelectorAll('.chat').forEach(function (c) { c.scrollTop = c.scrollHeight; });
})();
