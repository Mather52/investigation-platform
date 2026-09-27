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

  // نسخ نص إلى محرر: <button data-copy-from="id" data-copy-to="id">
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy-from]');
    if (!btn) { return; }
    e.preventDefault();
    var from = document.getElementById(btn.getAttribute('data-copy-from'));
    var to = document.getElementById(btn.getAttribute('data-copy-to'));
    if (!from || !to) { return; }
    if (to.value.trim() !== '' && !window.confirm('سيُستبدل النص الموجود في محرر الرد. هل تريد المتابعة؟')) { return; }
    to.value = from.textContent.trim();
    to.focus();
  });

  // إجابات مشابهة أثناء الكتابة: <div data-similar="url" data-source="inputId">
  document.querySelectorAll('[data-similar]').forEach(function (box) {
    var input = document.getElementById(box.getAttribute('data-source'));
    var list = box.querySelector('[data-similar-list]');
    var tpl = box.querySelector('template');
    var empty = box.querySelector('[data-similar-empty]');
    if (!input || !list || !tpl || !window.fetch) { return; }
    var timer, last = input.value.trim();
    function render(rows) {
      list.textContent = '';
      rows.forEach(function (r) {
        var node = tpl.content.cloneNode(true);
        node.querySelector('[data-f=subject]').textContent = r.subject;
        node.querySelector('[data-f=ref]').textContent = r.ref_no;
        node.querySelector('[data-f=category]').textContent = r.category || '';
        node.querySelector('[data-f=answer]').textContent = r.answer;
        list.appendChild(node);
      });
      if (empty) { empty.hidden = rows.length > 0; }
    }
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        var q = input.value.trim();
        if (q === last) { return; }
        last = q;
        if (q.length < 3) { render([]); return; }
        fetch(box.getAttribute('data-similar') + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function (res) { return res.ok ? res.json() : []; })
          .then(function (rows) { if (q === last) { render(Array.isArray(rows) ? rows : []); } })
          .catch(function () {});
      }, 400);
    });
  });

  // تمرير المحادثة للأسفل
  document.querySelectorAll('.chat').forEach(function (c) { c.scrollTop = c.scrollHeight; });
})();
