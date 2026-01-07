<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">일정관리 (발주 신청일 · 입고일)</h1>
      <div class="muted">날짜를 선택하면 발주신청/입고완료 이벤트를 확인할 수 있습니다.</div>
    </div>
    <div class="schedule-actions" aria-label="월 이동">
      <button type="button" class="btn secondary" id="schedulePrev" aria-label="이전 달">◀</button>
      <div class="schedule-ym" aria-label="년도/월 선택">
        <button type="button" class="btn secondary schedule-ym-btn" id="scheduleYearBtn" aria-haspopup="listbox" aria-expanded="false">-</button>
        <div class="schedule-menu" id="scheduleYearMenu" role="listbox" aria-label="년도 선택" style="display:none"></div>

        <button type="button" class="btn secondary schedule-ym-btn" id="scheduleMonthBtn" aria-haspopup="listbox" aria-expanded="false">-</button>
        <div class="schedule-menu" id="scheduleMonthMenu" role="listbox" aria-label="월 선택" style="display:none"></div>
      </div>
      <button type="button" class="btn secondary" id="scheduleNext" aria-label="다음 달">▶</button>
    </div>
  </div>

  <div style="margin-top:10px" class="form-row">
    <span class="badge accent">발주신청</span>
    <span class="badge ok">입고완료</span>
  </div>

  <div class="schedule-layout" style="margin-top:12px">
    <div class="schedule-calendar">
      <div class="card" style="padding:12px" id="scheduleRoot" data-api-url="<?= e(url('/api_schedule_events.php')) ?>" data-po-view-base="<?= e(url('/po_view.php')) ?>" data-receipt-view-base="<?= e(url('/receipt_view.php')) ?>" data-receipt-create-base="<?= e(url('/receipt_create.php')) ?>">
        <div class="schedule-cal" aria-label="월간 캘린더">
          <div class="schedule-weekdays">
            <div class="schedule-weekday">일</div>
            <div class="schedule-weekday">월</div>
            <div class="schedule-weekday">화</div>
            <div class="schedule-weekday">수</div>
            <div class="schedule-weekday">목</div>
            <div class="schedule-weekday">금</div>
            <div class="schedule-weekday">토</div>
          </div>
          <div class="schedule-grid" id="scheduleGrid"></div>
        </div>
      </div>
    </div>

    <div class="schedule-panel">
      <div class="schedule-panel-head">
        <div>
          <div class="schedule-panel-title" id="scheduleDayTitle">선택 날짜: -</div>
          <div class="muted" style="margin-top:2px">이벤트를 클릭하면 상세 정보가 표시됩니다.</div>
        </div>
      </div>

      <div style="margin-top:10px"></div>

      <div class="schedule-event-list" id="scheduleDayList">
        <div class="muted">날짜를 선택하면 내역이 표시됩니다.</div>
      </div>

      <div style="margin-top:12px"></div>

      <div class="schedule-event-detail" id="scheduleEventDetail" style="display:none">
        <div class="schedule-panel-section-title">선택 일정</div>
        <div class="schedule-event-title" id="scheduleEventTitle">-</div>
        <div class="schedule-event-meta" id="scheduleEventMeta" style="margin-top:6px">-</div>
        <div class="schedule-actions" style="margin-top:10px">
          <a class="btn secondary" id="scheduleGoPo" href="#" aria-disabled="true">발주 상세</a>
          <a class="btn" id="scheduleGoReceiptCreate" href="#" aria-disabled="true">입고등록</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$extraBodyEndHtml = <<<'HTML'
<script>
(() => {
  const root = document.getElementById('scheduleRoot');
  if (!root) return;

  const apiUrl = root.getAttribute('data-api-url');
  const poViewBase = root.getAttribute('data-po-view-base');
  const receiptViewBase = root.getAttribute('data-receipt-view-base');
  const receiptCreateBase = root.getAttribute('data-receipt-create-base');

  const grid = document.getElementById('scheduleGrid');
  const yearBtn = document.getElementById('scheduleYearBtn');
  const monthBtn = document.getElementById('scheduleMonthBtn');
  const yearMenu = document.getElementById('scheduleYearMenu');
  const monthMenu = document.getElementById('scheduleMonthMenu');
  const prevBtn = document.getElementById('schedulePrev');
  const nextBtn = document.getElementById('scheduleNext');

  const dayTitle = document.getElementById('scheduleDayTitle');
  const dayList = document.getElementById('scheduleDayList');
  const detailBox = document.getElementById('scheduleEventDetail');
  const detailTitle = document.getElementById('scheduleEventTitle');
  const detailMeta = document.getElementById('scheduleEventMeta');
  const goPo = document.getElementById('scheduleGoPo');
  const goReceiptCreate = document.getElementById('scheduleGoReceiptCreate');

  const defaultPrimaryLabel = goPo ? (goPo.textContent || '발주 상세') : '발주 상세';

  const pad2 = (n) => String(n).padStart(2, '0');
  const formatYmd = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
  const parseYmd = (s) => {
    const m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(s);
    if (!m) return null;
    const dt = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    if (Number.isNaN(dt.getTime())) return null;
    return dt;
  };

  const todayYmd = formatYmd(new Date());
  let current = new Date();
  current = new Date(current.getFullYear(), current.getMonth(), 1);

  let selectedYmd = null;
  let monthEventsByDate = new Map();

  function closeMenus() {
    if (yearMenu) yearMenu.style.display = 'none';
    if (monthMenu) monthMenu.style.display = 'none';
    if (yearBtn) yearBtn.setAttribute('aria-expanded', 'false');
    if (monthBtn) monthBtn.setAttribute('aria-expanded', 'false');
  }

  function openMenu(which) {
    closeMenus();
    if (which === 'year') {
      if (yearMenu) yearMenu.style.display = '';
      if (yearBtn) yearBtn.setAttribute('aria-expanded', 'true');
    } else {
      if (monthMenu) monthMenu.style.display = '';
      if (monthBtn) monthBtn.setAttribute('aria-expanded', 'true');
    }
  }

  document.addEventListener('click', (e) => {
    const t = e.target;
    const inYear = yearBtn && (t === yearBtn || (t.closest && t.closest('#scheduleYearBtn')));
    const inMonth = monthBtn && (t === monthBtn || (t.closest && t.closest('#scheduleMonthBtn')));
    const inMenu = (yearMenu && yearMenu.contains(t)) || (monthMenu && monthMenu.contains(t));
    if (!inYear && !inMonth && !inMenu) closeMenus();
  });

  function setLinkDisabled(a, disabled) {
    if (!a) return;
    if (disabled) {
      a.setAttribute('aria-disabled', 'true');
      a.setAttribute('tabindex', '-1');
      a.href = '#';
    } else {
      a.removeAttribute('aria-disabled');
      a.removeAttribute('tabindex');
    }
  }

  function monthRange(year, monthIndex) {
    const start = new Date(year, monthIndex, 1);
    const end = new Date(year, monthIndex + 1, 0);
    return { start, end };
  }

  function gridStartDate(year, monthIndex) {
    const first = new Date(year, monthIndex, 1);
    const dayOfWeek = first.getDay();
    const start = new Date(year, monthIndex, 1 - dayOfWeek);
    return start;
  }

  async function fetchEventsForMonth(year, monthIndex) {
    monthEventsByDate = new Map();

    const { start, end } = monthRange(year, monthIndex);
    const qs = new URLSearchParams({ start: formatYmd(start), end: formatYmd(end) });
    const url = `${apiUrl}?${qs.toString()}`;

    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    if (!res.ok) {
      const msg = (data && data.error) ? data.error : '일정 데이터를 불러오지 못했습니다.';
      throw new Error(msg);
    }

    const events = Array.isArray(data.events) ? data.events : [];
    for (const ev of events) {
      if (!ev || typeof ev.date !== 'string') continue;
      if (!monthEventsByDate.has(ev.date)) monthEventsByDate.set(ev.date, []);
      monthEventsByDate.get(ev.date).push(ev);
    }

    for (const [date, list] of monthEventsByDate.entries()) {
      list.sort((a, b) => String(a.type).localeCompare(String(b.type)));
    }
  }

  function renderYmLabel(year, monthIndex) {
    if (yearBtn) yearBtn.textContent = `${year}년`;
    if (monthBtn) monthBtn.textContent = `${monthIndex + 1}월`;
  }

  function buildYearMenu(year) {
    if (!yearMenu) return;
    yearMenu.innerHTML = '';
    const start = year - 5;
    const end = year + 5;
    for (let y = start; y <= end; y++) {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'schedule-menu-item';
      if (y === year) item.classList.add('active');
      item.textContent = `${y}년`;
      item.addEventListener('click', async () => {
        closeMenus();
        current = new Date(y, current.getMonth(), 1);
        try {
          await render();
        } catch (err) {
          dayList.innerHTML = `<div class="muted">${escapeHtml(err.message || '에러가 발생했습니다.')}</div>`;
        }
      });
      yearMenu.appendChild(item);
    }
  }

  function buildMonthMenu(monthIndex) {
    if (!monthMenu) return;
    monthMenu.innerHTML = '';
    for (let m = 1; m <= 12; m++) {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'schedule-menu-item';
      if ((m - 1) === monthIndex) item.classList.add('active');
      item.textContent = `${m}월`;
      item.addEventListener('click', async () => {
        closeMenus();
        current = new Date(current.getFullYear(), m - 1, 1);
        try {
          await render();
        } catch (err) {
          dayList.innerHTML = `<div class="muted">${escapeHtml(err.message || '에러가 발생했습니다.')}</div>`;
        }
      });
      monthMenu.appendChild(item);
    }
  }

  function renderGrid(year, monthIndex) {
    grid.innerHTML = '';
    renderYmLabel(year, monthIndex);
    buildYearMenu(year);
    buildMonthMenu(monthIndex);

    const start = gridStartDate(year, monthIndex);
    const thisMonth = monthIndex;

    for (let i = 0; i < 42; i++) {
      const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
      const ymd = formatYmd(d);

      const cell = document.createElement('button');
      cell.type = 'button';
      cell.className = 'schedule-day';
      cell.setAttribute('data-date', ymd);

      if (d.getMonth() !== thisMonth) cell.classList.add('out');
      if (ymd === todayYmd) cell.classList.add('today');
      if (selectedYmd && ymd === selectedYmd) cell.classList.add('selected');

      const head = document.createElement('div');
      head.className = 'schedule-day-head';

      const dayNum = document.createElement('div');
      dayNum.className = 'schedule-day-num';
      dayNum.textContent = String(d.getDate());

      head.appendChild(dayNum);
      cell.appendChild(head);

      const eventsWrap = document.createElement('div');
      eventsWrap.className = 'schedule-events';
      const list = monthEventsByDate.get(ymd) || [];

      if (list.length) cell.classList.add('has-events');
      const hasPo = list.some((e) => e && e.type === 'po');
      const hasReceipt = list.some((e) => e && e.type === 'receipt');
      if (hasPo) cell.classList.add('has-po');
      if (hasReceipt) cell.classList.add('has-receipt');

      const maxInline = 3;
      for (let j = 0; j < Math.min(list.length, maxInline); j++) {
        const ev = list[j];
        const evtBtn = document.createElement('button');
        evtBtn.type = 'button';
        evtBtn.className = 'schedule-evt';
        const kind = ev.type === 'po' ? '발주' : (ev.type === 'receipt' ? '입고' : '일정');
        const badge = document.createElement('span');
        badge.className = ev.type === 'receipt' ? 'badge ok' : 'badge accent';
        badge.textContent = kind;
        const text = document.createElement('span');
        text.className = 'schedule-evt-text';
        text.textContent = (ev.title ? String(ev.title) : '').trim();
        evtBtn.appendChild(badge);
        evtBtn.appendChild(text);
        evtBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          selectDay(ymd);
          showEventDetail(ymd, ev);
        });
        eventsWrap.appendChild(evtBtn);
      }
      if (list.length > maxInline) {
        const more = document.createElement('div');
        more.className = 'schedule-more muted';
        more.textContent = `+${list.length - maxInline} 더보기`;
        eventsWrap.appendChild(more);
      }
      cell.appendChild(eventsWrap);

      cell.addEventListener('click', () => selectDay(ymd));
      grid.appendChild(cell);
    }
  }

  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

  function buildEventLink(ev) {
    if (!ev) return '#';
    if (ev.type === 'po') return `${poViewBase}?id=${encodeURIComponent(ev.ref_id)}`;
    if (ev.type === 'receipt') return `${receiptViewBase}?id=${encodeURIComponent(ev.ref_id)}`;
    return '#';
  }

  function selectDay(ymd) {
    selectedYmd = ymd;

    for (const btn of grid.querySelectorAll('.schedule-day.selected')) {
      btn.classList.remove('selected');
    }
    const selectedBtn = grid.querySelector(`.schedule-day[data-date="${CSS.escape(ymd)}"]`);
    if (selectedBtn) selectedBtn.classList.add('selected');

    dayTitle.textContent = `선택 날짜: ${ymd}`;
    const list = monthEventsByDate.get(ymd) || [];
    if (list.length === 0) {
      dayList.innerHTML = '<div class="muted">해당 날짜의 일정이 없습니다.</div>';
      detailBox.style.display = 'none';
      setLinkDisabled(goPo, true);
      setLinkDisabled(goReceiptCreate, true);
      if (goPo) goPo.textContent = defaultPrimaryLabel;
      return;
    }

    const items = list.map((ev, idx) => {
      const kind = ev.type === 'po' ? '발주신청' : (ev.type === 'receipt' ? '입고완료' : '일정');
      const short = ev.type === 'po' ? '발주' : (ev.type === 'receipt' ? '입고' : '일정');
      const title = escapeHtml(ev.title || kind);
      const supplier = ev && ev.supplier_name ? escapeHtml(String(ev.supplier_name)) : '';
      const meta = supplier ? `${kind} · ${supplier}` : kind;
      const badgeClass = ev.type === 'receipt' ? 'ok' : 'accent';
      return `
        <button type="button" class="schedule-event-row" data-date="${escapeHtml(ymd)}" data-index="${idx}">
          <div class="schedule-event-row-left">
            <div class="schedule-event-title">${title}</div>
            <div class="schedule-event-meta">${escapeHtml(meta)}</div>
          </div>
          <div class="schedule-event-row-right"><span class="badge ${badgeClass}">${escapeHtml(short)}</span></div>
        </button>
      `;
    }).join('');
    dayList.innerHTML = items;

    detailBox.style.display = 'none';
    setLinkDisabled(goPo, true);
    setLinkDisabled(goReceiptCreate, true);
    if (goPo) goPo.textContent = defaultPrimaryLabel;

    for (const btn of dayList.querySelectorAll('.schedule-event-row')) {
      btn.addEventListener('click', () => {
        const index = Number(btn.getAttribute('data-index'));
        const ev = list[index];
        showEventDetail(ymd, ev);
      });
    }
  }

  function showEventDetail(ymd, ev) {
    if (!ev) return;
    const kind = ev.type === 'po' ? '발주신청' : (ev.type === 'receipt' ? '입고완료' : '일정');
    detailTitle.textContent = `${kind}: ${ev.title || ''}`.trim();
    detailMeta.textContent = `일자: ${ymd}`;
    detailBox.style.display = '';

    if (ev.type === 'po') {
      if (goPo) goPo.textContent = '발주 상세';
      setLinkDisabled(goPo, false);
      goPo.href = buildEventLink(ev);

      setLinkDisabled(goReceiptCreate, false);
      goReceiptCreate.href = `${receiptCreateBase}?purchase_order_id=${encodeURIComponent(ev.ref_id)}`;
    } else if (ev.type === 'receipt') {
      if (goPo) goPo.textContent = '입고 상세';
      setLinkDisabled(goPo, false);
      goPo.href = buildEventLink(ev);

      setLinkDisabled(goReceiptCreate, true);
    } else {
      if (goPo) goPo.textContent = defaultPrimaryLabel;
      setLinkDisabled(goPo, true);
      setLinkDisabled(goReceiptCreate, true);
    }
  }

  async function render() {
    const year = current.getFullYear();
    const monthIndex = current.getMonth();

    dayList.innerHTML = '<div class="muted">불러오는 중...</div>';
    detailBox.style.display = 'none';
    setLinkDisabled(goPo, true);
    setLinkDisabled(goReceiptCreate, true);

    await fetchEventsForMonth(year, monthIndex);
    renderGrid(year, monthIndex);

    if (!selectedYmd) {
      // 기본 선택: 오늘이 현재 월이면 오늘, 아니면 1일
      const firstYmd = formatYmd(new Date(year, monthIndex, 1));
      const today = parseYmd(todayYmd);
      const isTodayInMonth = today && today.getFullYear() === year && today.getMonth() === monthIndex;
      selectDay(isTodayInMonth ? todayYmd : firstYmd);
    } else {
      // 다른 월로 이동 시: 해당 월 1일 선택
      const dt = parseYmd(selectedYmd);
      if (!dt || dt.getFullYear() !== year || dt.getMonth() !== monthIndex) {
        selectDay(formatYmd(new Date(year, monthIndex, 1)));
      } else {
        selectDay(selectedYmd);
      }
    }
  }

  prevBtn.addEventListener('click', async () => {
    current = new Date(current.getFullYear(), current.getMonth() - 1, 1);
    try {
      await render();
    } catch (e) {
      dayList.innerHTML = `<div class="muted">${escapeHtml(e.message || '에러가 발생했습니다.')}</div>`;
    }
  });

  nextBtn.addEventListener('click', async () => {
    current = new Date(current.getFullYear(), current.getMonth() + 1, 1);
    try {
      await render();
    } catch (e) {
      dayList.innerHTML = `<div class="muted">${escapeHtml(e.message || '에러가 발생했습니다.')}</div>`;
    }
  });

  if (yearBtn) {
    yearBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const open = yearMenu && yearMenu.style.display !== 'none';
      if (open) closeMenus();
      else {
        buildYearMenu(current.getFullYear());
        openMenu('year');
      }
    });
  }

  if (monthBtn) {
    monthBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const open = monthMenu && monthMenu.style.display !== 'none';
      if (open) closeMenus();
      else {
        buildMonthMenu(current.getMonth());
        openMenu('month');
      }
    });
  }

  render().catch((e) => {
    dayList.innerHTML = `<div class="muted">${escapeHtml(e.message || '에러가 발생했습니다.')}</div>`;
  });
})();
</script>
HTML;

require_once __DIR__ . '/includes/footer.php';
?>
