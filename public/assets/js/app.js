// منصة التحقيق الإداري — تفاعلات بسيطة بدون مكتبات خارجية
(function () {
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

  // إظهار/إخفاء عناصر حسب قيمة: <div data-show-when="name=value">
  function syncConditional() {
    document.querySelectorAll('[data-show-when]').forEach(function (el) {
      var parts = el.getAttribute('data-show-when').split('=');
      var input = document.querySelector('[name="' + parts[0] + '"]:checked') || document.querySelector('select[name="' + parts[0] + '"]');
      var show = input && input.value === parts[1];
      el.hidden = !show;
    });
  }
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

  // تمرير المحادثة للأسفل
  document.querySelectorAll('.chat').forEach(function (c) { c.scrollTop = c.scrollHeight; });
})();
