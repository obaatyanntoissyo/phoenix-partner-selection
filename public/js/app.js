// 見積入力の合計表示（表示用。合計は必ずサーバーで再計算する）と、二重送信の防止。
(function () {
  'use strict';

  function toNumber(value) {
    var v = String(value || '').replace(/[０-９]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); }).replace(/[\s,，円]/g, '');
    return /^\d+$/.test(v) ? parseInt(v, 10) : null;
  }

  var total = document.getElementById('total');
  var inputs = document.querySelectorAll('[data-amount]');
  function updateTotal() {
    var sum = 0, valid = true;
    inputs.forEach(function (input) {
      var n = toNumber(input.value);
      if (input.value.trim() === '') return;
      if (n === null) { valid = false; return; }
      sum += n;
    });
    total.textContent = valid ? sum.toLocaleString('ja-JP') : '数値でない入力があります';
  }
  if (total && inputs.length) {
    inputs.forEach(function (input) { input.addEventListener('input', updateTotal); });
    updateTotal();
  }

  // 送信中はボタンを無効にする（サーバー側でも冪等キーで重複を防ぐ）
  document.querySelectorAll('form[data-once], #estimate-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (form.dataset.sending === '1') { e.preventDefault(); return; }
      form.dataset.sending = '1';
      var clicked = e.submitter;
      if (clicked && clicked.name) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = clicked.name; hidden.value = clicked.value;
        form.appendChild(hidden);
      }
      form.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = true; });
    });
  });

  // エラーがあるときは一覧へフォーカスを移す
  var summary = document.getElementById('error-summary');
  if (summary) summary.focus();
})();
