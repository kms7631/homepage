<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">일정관리</h1>
      <div class="muted">날짜를 클릭하면 발주/입고 내역을 확인하고, 항목 클릭 시 상세로 이동합니다.</div>
    </div>
  </div>

  <div class="schedule-layout" style="margin-top:12px">
    <div class="schedule-calendar">
      <div class="card" style="padding:12px" id="sidebarSchedule" data-api-url="<?= e(url('/api_schedule_po.php')) ?>" data-po-view-base="<?= e(url('/po_view.php')) ?>" data-receipt-view-base="<?= e(url('/receipt_view.php')) ?>">
        <div class="schedule-panel-head" style="margin-bottom:10px">
          <div>
            <div class="schedule-panel-title">발주/입고 캘린더</div>
            <div class="muted" id="sidebarScheduleMonth" style="margin-top:2px">-</div>
          </div>
          <div class="schedule-actions">
            <button type="button" class="btn secondary" id="sidebarSchedulePrev" aria-label="이전 달">◀</button>
            <button type="button" class="btn secondary" id="sidebarScheduleNext" aria-label="다음 달">▶</button>
          </div>
        </div>

        <div class="schedule-cal" aria-label="발주/입고 캘린더">
          <div class="schedule-weekdays">
            <div class="schedule-weekday">일</div>
            <div class="schedule-weekday">월</div>
            <div class="schedule-weekday">화</div>
            <div class="schedule-weekday">수</div>
            <div class="schedule-weekday">목</div>
            <div class="schedule-weekday">금</div>
            <div class="schedule-weekday">토</div>
          </div>
          <div class="schedule-grid" id="sidebarScheduleGrid"></div>
        </div>
      </div>
    </div>

    <div class="schedule-panel">
      <div class="schedule-panel-head">
        <div>
          <div class="schedule-panel-title" id="sidebarScheduleDayTitle">-</div>
          <div class="muted" style="margin-top:2px">발주/입고 항목을 클릭하면 상세로 이동합니다.</div>
        </div>
      </div>

      <div style="margin-top:10px"></div>

      <div class="schedule-event-list" id="sidebarScheduleDayList">
        <div class="muted">날짜를 선택하면 내역이 표시됩니다.</div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
