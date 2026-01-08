<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();
require_once __DIR__ . '/includes/dashboard.php';

$db = db();

$rangeInfo = dashboard_parse_range((string)($_GET['range'] ?? '30d'));

// admin: 선택 supplier_id 허용 / vendor: 강제
$requestedSupplierId = (int)($_GET['supplier_id'] ?? 0);
$supplierId = dashboard_effective_supplier_id($requestedSupplierId);

$status = trim((string)($_GET['status'] ?? ''));
if (!in_array($status, ['', 'complete', 'partial', 'none'], true)) {
  $status = '';
}

$itemId = (int)($_GET['item_id'] ?? 0);
if ($itemId < 0) {
  $itemId = 0;
}

$suppliers = is_admin() ? Supplier::listAll($db) : [];

$chartJs = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
$extraHeadHtml = $chartJs;

require_once __DIR__ . '/includes/header.php';
?>

<div class="card dashboard-page">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">분석 대시보드</h1>
      <div class="muted">기간별 발주/입고 현황을 요약하고, 그래프 클릭으로 하단 근거 리스트를 드릴다운합니다.</div>
    </div>
  </div>

  <form id="dashboardFilters" method="get" action="<?= e(url('/dashboard.php')) ?>" style="margin-top:12px">
    <div class="form-row">
      <div class="field" style="min-width:200px">
        <div class="label">기간</div>
        <select name="range" id="rangeSelect">
          <option value="7d" <?= $rangeInfo['range'] === '7d' ? 'selected' : '' ?>>최근 7일</option>
          <option value="30d" <?= $rangeInfo['range'] === '30d' ? 'selected' : '' ?>>최근 30일</option>
          <option value="thisMonth" <?= $rangeInfo['range'] === 'thisMonth' ? 'selected' : '' ?>>이번달</option>
          <option value="lastMonth" <?= $rangeInfo['range'] === 'lastMonth' ? 'selected' : '' ?>>지난달</option>
        </select>
      </div>

      <?php if (is_admin()): ?>
        <div class="field" style="min-width:240px">
          <div class="label">거래처(관리자)</div>
          <select name="supplier_id" id="supplierSelect">
            <option value="0">전체</option>
            <?php foreach ($suppliers as $sp): ?>
              <option value="<?= e((string)$sp['id']) ?>" <?= $supplierId === (int)$sp['id'] ? 'selected' : '' ?>>
                <?= e($sp['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
        <input type="hidden" name="supplier_id" value="<?= e((string)$supplierId) ?>" />
      <?php endif; ?>

      <div class="field" style="min-width:160px">
        <div class="label">드릴다운</div>
        <button type="button" class="btn secondary" id="clearDrilldownBtn">전체 보기</button>
      </div>

      <?php if ($status !== '' || $itemId > 0): ?>
        <input type="hidden" name="status" value="<?= e($status) ?>" />
        <input type="hidden" name="item_id" value="<?= e((string)$itemId) ?>" />
      <?php endif; ?>
    </div>
  </form>

  <div style="margin-top:12px"></div>

  <div class="grid">
    <div class="col-3" style="grid-column:span 3">
      <div class="kpi-card accent">
        <div class="kpi-title">총 발주 수량</div>
        <div class="kpi-main tight"><span id="kpiOrdered">-</span><span class="kpi-unit">EA</span></div>
        <div class="kpi-sub" id="kpiPeriod">-</div>
      </div>
    </div>
    <div class="col-3" style="grid-column:span 3">
      <div class="kpi-card ok">
        <div class="kpi-title">총 입고 수량</div>
        <div class="kpi-main tight"><span id="kpiReceived">-</span><span class="kpi-unit">EA</span></div>
        <div class="kpi-sub">누적 입고수량</div>
      </div>
    </div>
    <div class="col-3" style="grid-column:span 3">
      <div class="kpi-card warn">
        <div class="kpi-title">평균 입고율</div>
        <div class="kpi-main tight"><span id="kpiRate">-</span><span class="kpi-unit">%</span></div>
        <div class="kpi-sub">(누적 입고 / 발주) × 100</div>
      </div>
    </div>
    <div class="col-3" style="grid-column:span 3">
      <div class="kpi-card danger">
        <div class="kpi-title">미입고 잔량</div>
        <div class="kpi-main tight"><span id="kpiRemaining">-</span><span class="kpi-unit">EA</span></div>
        <div class="kpi-sub">발주 대비 미입고 합계</div>
      </div>
    </div>
  </div>

  <div style="margin-top:14px"></div>

  <div class="grid">
    <div class="col-6 dashboard-chart-col">
      <div class="card dashboard-chart-card">
        <div class="kpi dashboard-chart-head" style="align-items:flex-start">
          <div>
            <h2 class="h1" style="margin-bottom:4px">입고 상태 비율</h2>
            <div class="muted">발주 수량 기준 · 도넛 클릭 시 하단 리스트 필터링</div>
          </div>
          <div class="muted" id="donutCenterText" style="text-align:right">입고율 -%</div>
        </div>
        <div class="dashboard-donut-wrap">
          <canvas id="donutChart"></canvas>
        </div>
      </div>
    </div>

    <div class="col-6 dashboard-chart-col">
      <div class="card dashboard-chart-card">
        <div class="kpi dashboard-chart-head" style="align-items:flex-start">
          <div>
            <h2 class="h1" style="margin-bottom:4px" id="barTitle">-</h2>
            <div class="muted" id="barSubtitle">막대 클릭 시 하단 리스트 필터링</div>
          </div>
        </div>
        <div class="dashboard-bar-wrap">
          <canvas id="barChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div style="margin-top:14px"></div>

  <div class="card">
    <div class="kpi" style="align-items:flex-start">
      <div>
        <h2 class="h1" style="margin-bottom:4px" id="listTitle">-</h2>
        <div class="muted" id="listSubtitle">-</div>
      </div>
    </div>

    <div style="margin-top:10px"></div>

    <div class="table-scroll dashboard-evidence">
      <table class="table compact" id="evidenceTable">
        <thead id="evidenceHead"></thead>
        <tbody id="evidenceBody">
          <tr><td class="muted">로딩 중...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function(){
  const isAdmin = <?= json_encode(is_admin()) ?>;
  const baseUrl = <?= json_encode(url('/dashboard.php'), JSON_UNESCAPED_SLASHES) ?>;

  function cssVar(name, fallback){
    const v = getComputedStyle(document.documentElement).getPropertyValue(name);
    return (v && v.trim()) ? v.trim() : fallback;
  }

  const colors = {
    text: cssVar('--text', '#e8eefc'),
    muted: cssVar('--muted', '#a9b6d6'),
    border: cssVar('--border', 'rgba(232,238,252,.14)'),
    ok: cssVar('--ok', '#36d399'),
    danger: cssVar('--danger', '#ff6b6b'),
    accent: cssVar('--accent', '#7aa2ff')
  };

  function getParams(){
    return new URLSearchParams(location.search);
  }

  function setParams(next){
    const p = getParams();
    Object.keys(next).forEach(k => {
      const v = next[k];
      if (v === null || v === undefined || v === '' || v === 0 || v === '0') {
        p.delete(k);
      } else {
        p.set(k, String(v));
      }
    });
    const qs = p.toString();
    history.replaceState({}, '', baseUrl + (qs ? ('?' + qs) : ''));

    // 상단 필터 UI 동기화(페이지 리로드 없이 URL만 바뀌는 경우)
    const rangeSel = document.getElementById('rangeSelect');
    if (rangeSel && p.get('range')) {
      rangeSel.value = p.get('range');
    }
    const supplierSel = document.getElementById('supplierSelect');
    if (supplierSel && p.get('supplier_id') !== null) {
      supplierSel.value = p.get('supplier_id');
    }
    return p;
  }

  function fetchJson(path, params){
    const u = new URL(path, location.origin);
    Object.entries(params || {}).forEach(([k,v]) => {
      if (v !== null && v !== undefined && v !== '' && v !== 0 && v !== '0') {
        u.searchParams.set(k, String(v));
      }
    });
    return fetch(u.toString(), { headers: { 'Accept': 'application/json' }}).then(r => r.json());
  }

  function fmtInt(n){
    try { return Number(n || 0).toLocaleString('ko-KR'); } catch(e) { return String(n || 0); }
  }

  function fmtRate(n){
    const v = Number(n || 0);
    return (Math.round(v * 10) / 10).toFixed(1);
  }

  function wrapAxisLabel(s, maxLen = 10){
    const str = String(s ?? '').trim();
    if (str.includes(' ')) {
      const parts = str.split(/\s+/).filter(Boolean);
      if (parts.length >= 2) {
        const first = parts[0];
        const rest = parts.slice(1).join(' ');
        const firstLine = first.length <= maxLen ? first : (first.slice(0, Math.max(1, maxLen - 1)) + '...');
        const restLine = rest.length <= maxLen ? rest : (rest.slice(0, Math.max(1, maxLen - 1)) + '...');
        return [firstLine, restLine];
      }
    }
    if (str.length <= maxLen) return str;
    const line1 = str.slice(0, maxLen);
    const rest = str.slice(maxLen);
    if (rest.length <= maxLen) return [line1, rest];
    const line2 = rest.slice(0, Math.max(1, maxLen - 1)) + '...';
    return [line1, line2];
  }

  let donutChart = null;
  let barChart = null;

  function chartCommonOptions(){
    return {
      plugins: {
        legend: { labels: { color: colors.text } },
        tooltip: {
          titleColor: colors.text,
          bodyColor: colors.text,
          borderColor: colors.border,
          borderWidth: 1,
          backgroundColor: 'rgba(16,27,51,.92)'
        }
      }
    };
  }

  function renderDonut(data){
    const ctx = document.getElementById('donutChart');
    const labels = (data && data.donut && data.donut.labels) ? data.donut.labels : [];
    const values = (data && data.donut && data.donut.values) ? data.donut.values : [];
    const keys = (data && data.donut && data.donut.keys) ? data.donut.keys : [];

    document.getElementById('donutCenterText').textContent = '입고율 ' + fmtRate((data && data.donut && data.donut.center_rate) ? data.donut.center_rate : 0) + '%';

    const ds = {
      label: '발주 수량',
      data: values,
      backgroundColor: [colors.ok, colors.accent, colors.danger],
      borderColor: 'rgba(0,0,0,0)',
    };

    const donutRadius = '88%';
    const donutCutout = '66%';

    if (donutChart) {
      donutChart.data.labels = labels;
      donutChart.data.datasets[0].data = values;
      donutChart.options.radius = donutRadius;
      donutChart.options.cutout = donutCutout;
      donutChart.update();
      donutChart.$_keys = keys;
      return;
    }

    donutChart = new Chart(ctx, {
      type: 'doughnut',
      data: { labels, datasets: [ds] },
      options: {
        ...chartCommonOptions(),
        responsive: true,
        maintainAspectRatio: false,
        radius: donutRadius,
        cutout: donutCutout,
        plugins: {
          ...chartCommonOptions().plugins,
          legend: {
            ...chartCommonOptions().plugins.legend,
            position: 'bottom',
            labels: {
              ...chartCommonOptions().plugins.legend.labels,
              boxWidth: 14,
              boxHeight: 10,
              padding: 14,
            }
          }
        },
        onClick: (evt, elements) => {
          if (!elements || !elements.length) return;
          const idx = elements[0].index;
          const status = (donutChart.$_keys && donutChart.$_keys[idx]) ? donutChart.$_keys[idx] : '';
          setParams({ status, item_id: null });
          loadList();
        }
      }
    });
    donutChart.$_keys = keys;
  }

  function renderBar(data){
    const ctx = document.getElementById('barChart');
    const bar = data && data.bar ? data.bar : {};
    const labels = bar.labels || [];
    const values = bar.values || [];
    const ids = bar.ids || [];
    const metric = bar.metric || '';

    // 라벨이 길더라도 개수가 적으면 전부 표시(누락 방지). 개수가 많을 때만 자동 스킵.
    const shouldAutoSkipX = labels.length > 10;

    const title = isAdmin ? '거래처별 입고율 TOP' : '내 발주 품목 TOP';
    document.getElementById('barTitle').textContent = title;

    const yIsPercent = isAdmin;

    const ds = {
      label: isAdmin ? '입고율(%)' : '발주수량(EA)',
      data: values,
      backgroundColor: colors.accent,
      borderColor: 'rgba(0,0,0,0)'
    };

    const opts = {
      ...chartCommonOptions(),
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: { top: 8, right: 8, bottom: 14, left: 8 }
      },
      scales: {
        x: {
          ticks: {
            color: colors.text,
            maxRotation: 0,
            autoSkip: shouldAutoSkipX,
            autoSkipPadding: shouldAutoSkipX ? 18 : 0,
            maxTicksLimit: shouldAutoSkipX ? 8 : undefined,
            padding: 10,
            font: { size: 12, weight: '600' },
            callback: function (value) {
              return wrapAxisLabel(this.getLabelForValue(value), 8);
            },
          },
          grid: { color: 'rgba(232,238,252,.08)'}
        },
        y: {
          ticks: {
            color: colors.text,
            padding: 6,
            font: { size: 12, weight: '600' },
            callback: (val) => yIsPercent ? (val + '%') : String(val)
          },
          grid: { color: 'rgba(232,238,252,.08)' },
          suggestedMin: 0,
          suggestedMax: yIsPercent ? 100 : undefined
        }
      },
      datasets: {
        bar: {
          borderRadius: 8,
          barThickness: 30,
          maxBarThickness: 40,
          categoryPercentage: 0.6,
          barPercentage: 0.8,
        }
      },
      onClick: (evt, elements) => {
        if (!elements || !elements.length) return;
        const idx = elements[0].index;
        if (isAdmin) {
          const supplierId = (ids && ids[idx]) ? ids[idx] : null;
          setParams({ supplier_id: supplierId, item_id: null });
          // admin은 supplier_id가 상단 필터이기도 하므로 전체 재로딩
          loadAll();
        } else {
          const itemId = (ids && ids[idx]) ? ids[idx] : null;
          setParams({ item_id: itemId });
          loadList();
        }
      }
    };

    if (barChart) {
      barChart.data.labels = labels;
      barChart.data.datasets[0].data = values;
      if (barChart.options && barChart.options.scales && barChart.options.scales.x && barChart.options.scales.x.ticks) {
        barChart.options.scales.x.ticks.autoSkip = shouldAutoSkipX;
        barChart.options.scales.x.ticks.autoSkipPadding = shouldAutoSkipX ? 18 : 0;
        barChart.options.scales.x.ticks.maxTicksLimit = shouldAutoSkipX ? 8 : undefined;
        barChart.options.scales.x.ticks.padding = 10;
        barChart.options.scales.x.ticks.callback = function (value) {
          return wrapAxisLabel(this.getLabelForValue(value), 8);
        };
      }
      barChart.update();
      barChart.$_ids = ids;
      barChart.$_metric = metric;
      return;
    }

    barChart = new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets: [ds] },
      options: opts
    });
    barChart.$_ids = ids;
    barChart.$_metric = metric;
  }

  function renderKpi(summary){
    const k = summary && summary.kpi ? summary.kpi : {};
    document.getElementById('kpiOrdered').textContent = fmtInt(k.ordered_qty);
    document.getElementById('kpiReceived').textContent = fmtInt(k.received_qty);
    document.getElementById('kpiRate').textContent = fmtRate(k.avg_receive_rate);
    document.getElementById('kpiRemaining').textContent = fmtInt(k.remaining_qty);
    document.getElementById('kpiPeriod').textContent = (summary.from || '-') + ' ~ ' + (summary.to || '-');
  }

  function setListHeader(){
    const head = document.getElementById('evidenceHead');
    if (isAdmin) {
      head.innerHTML = '<tr>'
        + '<th>거래처</th>'
        + '<th>품목</th>'
        + '<th>발주수량</th>'
        + '<th>입고수량</th>'
        + '<th>입고율</th>'
        + '<th>납기(발주일)</th>'
        + '<th>마지막 입고일</th>'
        + '</tr>';
      document.getElementById('listTitle').textContent = '지연/미입고 Top 리스트';
      document.getElementById('listSubtitle').textContent = '기본: 잔량 > 0 · 도넛/막대 클릭으로 필터링';
    } else {
      head.innerHTML = '<tr>'
        + '<th>PO번호</th>'
        + '<th>품목</th>'
        + '<th>발주수량</th>'
        + '<th>입고수량</th>'
        + '<th>잔량</th>'
        + '<th>납기(발주일)</th>'
        + '</tr>';
      document.getElementById('listTitle').textContent = '내 진행중 발주';
      document.getElementById('listSubtitle').textContent = '기본: 잔량 큰 순 · 그래프 클릭으로 품목/상태 드릴다운';
    }
  }

  function renderList(resp){
    const body = document.getElementById('evidenceBody');
    const rows = (resp && resp.rows) ? resp.rows : [];
    if (!rows.length) {
      const cs = isAdmin ? 7 : 6;
      body.innerHTML = '<tr><td class="muted" colspan="' + cs + '">표시할 데이터가 없습니다.</td></tr>';
      return;
    }

    if (isAdmin) {
      body.innerHTML = rows.map(r => {
        const rate = fmtRate(r.receive_rate);
        return '<tr>'
          + '<td>' + escapeHtml(r.supplier_name) + '</td>'
          + '<td>' + escapeHtml(r.item_name) + '</td>'
          + '<td>' + fmtInt(r.ordered_qty) + '</td>'
          + '<td>' + fmtInt(r.received_qty) + '</td>'
          + '<td>' + rate + '%</td>'
          + '<td>' + escapeHtml(r.order_date) + '</td>'
          + '<td>' + escapeHtml(r.last_receipt_date || '-') + '</td>'
          + '</tr>';
      }).join('');
      return;
    }

    body.innerHTML = rows.map(r => {
      return '<tr>'
        + '<td>' + escapeHtml(r.po_no) + '</td>'
        + '<td>' + escapeHtml(r.item_name) + '</td>'
        + '<td>' + fmtInt(r.ordered_qty) + '</td>'
        + '<td>' + fmtInt(r.received_qty) + '</td>'
        + '<td>' + fmtInt(r.remaining_qty) + '</td>'
        + '<td>' + escapeHtml(r.order_date) + '</td>'
        + '</tr>';
    }).join('');
  }

  function escapeHtml(s){
    return String(s === null || s === undefined ? '' : s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function getQueryForApiBase(){
    const p = getParams();
    return {
      range: p.get('range') || '30d',
      supplier_id: p.get('supplier_id') || '0'
    };
  }

  function getQueryForList(){
    const p = getParams();
    return {
      range: p.get('range') || '30d',
      supplier_id: p.get('supplier_id') || '0',
      status: p.get('status') || '',
      item_id: p.get('item_id') || '0'
    };
  }

  function loadAll(){
    const base = getQueryForApiBase();
    setListHeader();

    return Promise.all([
      fetchJson(<?= json_encode(url('/api_dashboard_summary.php'), JSON_UNESCAPED_SLASHES) ?>, base),
      fetchJson(<?= json_encode(url('/api_dashboard_bar.php'), JSON_UNESCAPED_SLASHES) ?>, base),
    ]).then(([summary, bar]) => {
      if (!summary.ok) throw new Error(summary.error || 'summary failed');
      if (!bar.ok) throw new Error(bar.error || 'bar failed');
      renderKpi(summary);
      renderDonut(summary);
      renderBar(bar);
      return loadList();
    }).catch(err => {
      const body = document.getElementById('evidenceBody');
      body.innerHTML = '<tr><td class="muted">대시보드 로딩 실패: ' + escapeHtml(err.message || err) + '</td></tr>';
    });
  }

  function loadList(){
    const q = getQueryForList();
    return fetchJson(<?= json_encode(url('/api_dashboard_list.php'), JSON_UNESCAPED_SLASHES) ?>, q).then(resp => {
      if (!resp.ok) throw new Error(resp.error || 'list failed');
      renderList(resp);
    }).catch(err => {
      const body = document.getElementById('evidenceBody');
      body.innerHTML = '<tr><td class="muted">리스트 로딩 실패: ' + escapeHtml(err.message || err) + '</td></tr>';
    });
  }

  document.getElementById('dashboardFilters').addEventListener('change', function(){
    // 기간/거래처 변경 시 드릴다운은 초기화
    const p = getParams();
    const range = document.getElementById('rangeSelect').value;
    const supplier = document.getElementById('supplierSelect') ? document.getElementById('supplierSelect').value : (p.get('supplier_id') || '0');
    setParams({ range, supplier_id: supplier, status: null, item_id: null });
    loadAll();
  });

  document.getElementById('clearDrilldownBtn').addEventListener('click', function(){
    setParams({ status: null, item_id: null });
    loadList();
  });

  // 초기 로드
  setListHeader();
  loadAll();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
