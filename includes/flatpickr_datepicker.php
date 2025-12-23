<?php
declare(strict_types=1);

function flatpickr_datepicker_head_html(): string {
  return <<<HTML
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
<style>
  .flatpickr-calendar{background:rgba(16,27,51,.98);border:1px solid var(--border);box-shadow:none}
  .flatpickr-calendar.arrowTop:before{border-bottom-color:var(--border)}
  .flatpickr-calendar.arrowTop:after{border-bottom-color:rgba(16,27,51,.98)}
  .flatpickr-calendar.arrowBottom:before{border-top-color:var(--border)}
  .flatpickr-calendar.arrowBottom:after{border-top-color:rgba(16,27,51,.98)}
  .flatpickr-months .flatpickr-month{color:var(--text)}
  .flatpickr-current-month{color:var(--text)}
  .flatpickr-current-month input.cur-year{color:var(--text)}
  .flatpickr-weekdays{background:transparent}
  span.flatpickr-weekday{color:var(--muted);background:transparent}
  .flatpickr-day{color:var(--text)}
  .flatpickr-day:hover{background:rgba(232,238,252,.06);border-color:transparent}
  .flatpickr-day.today{border-color:rgba(122,162,255,.35)}
  .flatpickr-day.selected,.flatpickr-day.startRange,.flatpickr-day.endRange{background:rgba(122,162,255,.22);border-color:rgba(122,162,255,.35);color:var(--text)}
  .flatpickr-day.inRange{background:rgba(122,162,255,.12);border-color:transparent;box-shadow:none}
  .flatpickr-day.flatpickr-disabled,.flatpickr-day.flatpickr-disabled:hover{color:rgba(169,182,214,.35)}
  .flatpickr-time input,.flatpickr-time .flatpickr-time-separator,.flatpickr-time .flatpickr-am-pm{color:var(--text)}
  .flatpickr-monthDropdown-months{background:var(--text);color:var(--bg);border-radius:8px;padding:2px 6px}
  .flatpickr-monthDropdown-months option{background:var(--text);color:var(--bg)}
</style>
HTML;
}

function flatpickr_datepicker_body_html(string $selector = 'input.js-date'): string {
  $selectorJs = json_encode($selector, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

  return <<<HTML
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ko.js"></script>
<script>
  (function(){
    if (!window.flatpickr) return;
    var selector = {$selectorJs};
    var inputs = document.querySelectorAll(selector);
    if (!inputs || !inputs.length) return;
    window.flatpickr(inputs, {
      dateFormat: 'Y-m-d',
      allowInput: true,
      disableMobile: true,
      locale: (window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.ko) ? window.flatpickr.l10ns.ko : undefined
    });
  })();
</script>
HTML;
}
