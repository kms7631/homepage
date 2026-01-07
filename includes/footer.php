<?php
$me = current_user();
$isAuthPage = (defined('LAYOUT_AUTH_PAGE') && LAYOUT_AUTH_PAGE);
?>
  <?php if (!$isAuthPage): ?>
    </div>
  <?php endif; ?>
  <?php if ($me): ?>
      </main>
    </div>
  <?php endif; ?>
  <?php if (isset($extraBodyEndHtml) && is_string($extraBodyEndHtml) && $extraBodyEndHtml !== ''): ?>
    <?= $extraBodyEndHtml ?>
  <?php endif; ?>

  <script>
    (function () {
      function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
      }

      function applyTheme(next) {
        if (next === 'light') {
          document.documentElement.setAttribute('data-theme', 'light');
        } else {
          document.documentElement.removeAttribute('data-theme');
        }
        try {
          localStorage.setItem('theme', next);
        } catch (e) {
          // ignore
        }
      }

      function updateButtons() {
        var isLight = currentTheme() === 'light';
        var label = isLight ? '다크 모드' : '라이트 모드';
        document.querySelectorAll('.js-theme-toggle').forEach(function (btn) {
          btn.textContent = label;
        });
      }

      document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.js-theme-toggle') : null;
        if (!btn) return;
        e.preventDefault();
        var next = currentTheme() === 'light' ? 'dark' : 'light';
        applyTheme(next);
        updateButtons();
      });

      updateButtons();
    })();
  </script>

  <script>
    (function () {
      var root = document.getElementById('sidebarSchedule');
      if (!root) return;

      var apiUrl = root.getAttribute('data-api-url') || '';
      var poViewBase = root.getAttribute('data-po-view-base') || '';
      var receiptViewBase = root.getAttribute('data-receipt-view-base') || '';
      if (!apiUrl) return;

      var monthLabel = document.getElementById('sidebarScheduleMonth');
      var grid = document.getElementById('sidebarScheduleGrid');
      var dayTitle = document.getElementById('sidebarScheduleDayTitle');
      var dayList = document.getElementById('sidebarScheduleDayList');
      var btnPrev = document.getElementById('sidebarSchedulePrev');
      var btnNext = document.getElementById('sidebarScheduleNext');

      function pad2(n) {
        return String(n).padStart(2, '0');
      }

      function ymd(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
      }

      function ym(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1);
      }

      function formatKoreanDateStr(dateStr) {
        // YYYY-MM-DD -> YYYY.MM.DD
        return dateStr.replace(/-/g, '.');
      }

      function fetchJson(url) {
        return fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
      }

      var state = {
        cursor: new Date(),
        monthDays: {},
        selected: null
      };
      state.cursor.setDate(1);

      function clearNode(n) {
        while (n.firstChild) n.removeChild(n.firstChild);
      }

      function eventType(evt) {
        var t = evt && evt.type ? String(evt.type) : '';
        if (t) return t;
        // backwards compatibility
        if (evt && (evt.po_no || evt.status)) return 'po';
        if (evt && evt.receipt_no) return 'receipt';
        return 'po';
      }

      function eventTitle(evt) {
        var t = eventType(evt);
        if (t === 'receipt') {
          return (evt.receipt_no ? String(evt.receipt_no) : ('입고 #' + String(evt.id || '')));
        }
        return (evt.po_no ? String(evt.po_no) : ('발주 #' + String(evt.id || '')));
      }

      function eventMeta(evt) {
        var t = eventType(evt);
        var s = evt && evt.supplier_name ? String(evt.supplier_name) : '';
        var status = '';
        if (t === 'receipt') {
          status = '입고확인';
        } else {
          status = evt && evt.status ? String(evt.status) : '';
        }
        return (s ? (s + ' · ') : '') + status;
      }

      function eventHref(evt) {
        var t = eventType(evt);
        var id = evt && evt.id ? String(evt.id) : '';
        if (!id) return '#';
        if (t === 'receipt') {
          var base = receiptViewBase || '/receipt_view.php';
          return base + '?id=' + encodeURIComponent(id);
        }
        var base = poViewBase || '/po_view.php';
        return base + '?id=' + encodeURIComponent(id);
      }

      function renderDayList(dateStr) {
        state.selected = dateStr;
        if (dayTitle) {
          dayTitle.textContent = formatKoreanDateStr(dateStr) + ' 발주';
        }
        if (!dayList) return;
        clearNode(dayList);

        var rows = state.monthDays[dateStr] || [];
        if (!rows.length) {
          var empty = document.createElement('div');
          empty.className = 'muted';
          empty.textContent = '해당 날짜의 발주가 없습니다.';
          dayList.appendChild(empty);
          return;
        }

        rows.slice(0, 10).forEach(function (evt) {
          var a = document.createElement('a');
          a.className = 'schedule-event-row';
          a.href = eventHref(evt);

          var left = document.createElement('div');
          left.className = 'schedule-event-row-left';

          var title = document.createElement('div');
          title.className = 'schedule-event-title';
          title.textContent = eventTitle(evt);

          var meta = document.createElement('div');
          meta.className = 'schedule-event-meta';
          meta.textContent = eventMeta(evt);

          left.appendChild(title);
          left.appendChild(meta);
          a.appendChild(left);
          dayList.appendChild(a);
        });

        if (rows.length > 10) {
          var more = document.createElement('div');
          more.className = 'muted';
          more.textContent = '표시 제한: 10건 (총 ' + rows.length + '건)';
          dayList.appendChild(more);
        }
      }

      function renderGrid(monthStr, fromStr, toStr) {
        if (!grid) return;
        clearNode(grid);

        // 달력 시작: 해당 월 1일의 요일 기준으로 앞을 채움
        var first = new Date(fromStr + 'T00:00:00');
        var start = new Date(first);
        start.setDate(first.getDate() - first.getDay());

        var today = new Date();
        var todayStr = ymd(today);

        for (var i = 0; i < 42; i++) {
          var d = new Date(start);
          d.setDate(start.getDate() + i);
          var dateStr = ymd(d);

          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'schedule-day';
          btn.setAttribute('data-date', dateStr);

          if (dateStr.substr(0, 7) !== monthStr) {
            btn.className += ' out';
          }
          if (dateStr === todayStr) {
            btn.className += ' today';
          }
          if (state.selected && dateStr === state.selected) {
            btn.className += ' selected';
          }

          var head = document.createElement('div');
          head.className = 'schedule-day-head';

          var num = document.createElement('div');
          num.className = 'schedule-day-num';
          num.textContent = String(d.getDate());
          head.appendChild(num);
          btn.appendChild(head);

          var events = document.createElement('div');
          events.className = 'schedule-events';
          var rows = state.monthDays[dateStr] || [];
          if (rows.length) {
            rows.slice(0, 2).forEach(function (evt) {
              var e = document.createElement('div');
              e.className = 'schedule-evt muted';
              var t = eventType(evt);
              e.textContent = (t === 'receipt' ? '입고 ' : '') + eventTitle(evt);
              events.appendChild(e);
            });
            if (rows.length > 2) {
              var more = document.createElement('div');
              more.className = 'schedule-more muted';
              more.textContent = '+' + String(rows.length - 2) + '건';
              events.appendChild(more);
            }
          }
          btn.appendChild(events);

          btn.addEventListener('click', function (e) {
            var ds = e.currentTarget.getAttribute('data-date');
            if (!ds) return;
            state.selected = ds;
            // selected CSS 갱신
            Array.prototype.slice.call(grid.querySelectorAll('.schedule-day.selected')).forEach(function (n) {
              n.classList.remove('selected');
            });
            e.currentTarget.classList.add('selected');
            renderDayList(ds);
          });

          grid.appendChild(btn);
        }
      }

      function loadMonth() {
        var monthStr = ym(state.cursor);
        if (monthLabel) {
          monthLabel.textContent = monthStr;
        }

        var u = new URL(apiUrl, location.origin);
        u.searchParams.set('month', monthStr);

        return fetchJson(u.toString()).then(function (data) {
          if (!data || !data.ok) {
            throw new Error((data && data.error) ? data.error : '캘린더 데이터를 불러오지 못했습니다.');
          }
          state.monthDays = data.days || {};

          // 기본 선택: 오늘(해당 월이면), 아니면 1일
          var today = new Date();
          var defaultSel = null;
          if (ym(today) === monthStr) {
            defaultSel = ymd(today);
          } else {
            defaultSel = monthStr + '-01';
          }
          state.selected = defaultSel;

          renderGrid(monthStr, data.from, data.to);
          renderDayList(defaultSel);
        }).catch(function (err) {
          if (dayTitle) dayTitle.textContent = '오류';
          if (dayList) {
            clearNode(dayList);
            var div = document.createElement('div');
            div.className = 'muted';
            div.textContent = err && err.message ? err.message : '오류가 발생했습니다.';
            dayList.appendChild(div);
          }
        });
      }

      if (btnPrev) {
        btnPrev.addEventListener('click', function () {
          state.cursor = new Date(state.cursor.getFullYear(), state.cursor.getMonth() - 1, 1);
          loadMonth();
        });
      }
      if (btnNext) {
        btnNext.addEventListener('click', function () {
          state.cursor = new Date(state.cursor.getFullYear(), state.cursor.getMonth() + 1, 1);
          loadMonth();
        });
      }

      loadMonth();
    })();
  </script>
</body>
</html>
