<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();

function qs_keep(array $overrides = []): string {
  $base = [
    'q' => (string)($_GET['q'] ?? ''),
    'sort' => (string)($_GET['sort'] ?? 'latest'),
    'prio' => (string)($_GET['prio'] ?? ''),
    'page' => (string)($_GET['page'] ?? '1'),
    'id' => (string)($_GET['id'] ?? ''),
  ];
  $merged = array_merge($base, $overrides);
  foreach ($merged as $k => $v) {
    if ($v === '' || $v === null) {
      unset($merged[$k]);
    }
  }
  $q = http_build_query($merged);
  return $q ? ('?' . $q) : '';
}

function contains_ci(string $haystack, string $needle): bool {
  if ($needle === '') {
    return true;
  }
  if (function_exists('mb_stripos')) {
    return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
  }
  return stripos($haystack, $needle) !== false;
}

if (is_post()) {
  try {
    if (!is_admin()) {
      throw new RuntimeException('접근 권한이 없습니다.');
    }
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action !== 'delete') {
      throw new RuntimeException('잘못된 요청입니다.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      throw new RuntimeException('삭제할 공지사항이 올바르지 않습니다.');
    }
    Notice::delete($db, $id);
    flash_set('success', '공지사항이 삭제되었습니다.');
    redirect('/notice.php' . qs_keep(['id' => '']));
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    redirect('/notice.php' . qs_keep(['id' => '']));
  }
}

$q = trim((string)($_GET['q'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'latest'));
$prioRaw = trim((string)($_GET['prio'] ?? ''));
$priorityFilter = null;
if ($prioRaw === '1' || strtolower($prioRaw) === 'important') {
  $priorityFilter = 1;
} elseif ($prioRaw === '0' || strtolower($prioRaw) === 'normal') {
  $priorityFilter = 0;
}
$page = (int)($_GET['page'] ?? 1);
if ($page <= 0) {
  $page = 1;
}
$pageSize = 15;

$selectedId = (int)($_GET['id'] ?? 0);
if ($selectedId < 0) {
  $selectedId = 0;
}

$pinned = [];
$excludeIds = [];
if ($priorityFilter === null && $q === '' && $page === 1) {
  $pinned = Notice::listPinned($db, 3);
  $excludeIds = array_map(fn($r) => (int)($r['id'] ?? 0), $pinned);
  $excludeIds = array_values(array_filter($excludeIds, fn($v) => $v > 0));
}

$total = Notice::count($db, $q, $excludeIds, $priorityFilter);
$pageCount = max(1, (int)ceil($total / $pageSize));
if ($page > $pageCount) {
  $page = $pageCount;
}

$offset = ($page - 1) * $pageSize;
$rows = Notice::listPaged($db, $pageSize, $offset, $q, $sort, $excludeIds, $priorityFilter);

$selected = null;
$selectedExcluded = false;
if ($selectedId > 0) {
  $selected = Notice::find($db, $selectedId);

  if ($selected) {
    if ($priorityFilter !== null && (int)($selected['priority'] ?? 0) !== $priorityFilter) {
      $selectedExcluded = true;
      $selected = null;
    } elseif ($q !== '') {
      $hay = (string)($selected['title'] ?? '') . "\n" . (string)($selected['body'] ?? '');
      if (!contains_ci($hay, $q)) {
        $selectedExcluded = true;
        $selected = null;
      }
    }
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card notice-board">
  <div class="notice-top">
    <div>
      <h1 class="h1" style="margin-bottom:4px;white-space:normal">공지사항</h1>
    </div>
    <div class="notice-top-actions">
      <?php if (is_admin()): ?>
        <a class="btn" href="<?= e(url('/notice_create.php')) ?>">공지 작성</a>
      <?php endif; ?>
    </div>
  </div>

  <form class="notice-toolbar" method="get" action="<?= e(url('/notice.php')) ?>">
    <input type="hidden" name="page" value="1" />
    <?php if ($priorityFilter !== null): ?>
      <input type="hidden" name="prio" value="<?= e((string)$priorityFilter) ?>" />
    <?php endif; ?>
    <div class="notice-toolbar-left">
      <input class="input" type="search" name="q" placeholder="제목 또는 본문 검색" value="<?= e($q) ?>" />
    </div>
    <div class="notice-toolbar-right">
      <select name="sort" class="input" aria-label="정렬">
        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>최신순</option>
        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>오래된순</option>
      </select>
      <button class="btn secondary" type="submit">검색</button>
      <?php if ($q !== '' || $priorityFilter !== null): ?>
        <a class="btn secondary" href="<?= e(url('/notice.php')) ?>">초기화</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="notice-split">
    <section class="notice-list" aria-label="공지 목록">
      <div class="notice-list-meta">
        <div class="notice-list-meta-left">
          <div class="muted">총 <?= e((string)$total) ?>건</div>
        </div>
        <div class="notice-list-meta-right">
          <div class="small">페이지 <?= e((string)$page) ?>/<?= e((string)$pageCount) ?></div>
          <div class="notice-filter-buttons" aria-label="공지 구분 필터">
            <a class="<?= $priorityFilter === 1 ? 'btn' : 'btn secondary' ?>" href="<?= e(url('/notice.php' . qs_keep(['prio' => '1', 'page' => '1', 'id' => '']))) ?>">중요</a>
            <a class="<?= $priorityFilter === 0 ? 'btn' : 'btn secondary' ?>" href="<?= e(url('/notice.php' . qs_keep(['prio' => '0', 'page' => '1', 'id' => '']))) ?>">일반</a>
          </div>
        </div>
      </div>

      <?php if ($pinned): ?>
        <div class="notice-list-section-title">중요 공지</div>
        <div class="notice-rows">
          <?php foreach ($pinned as $r): ?>
            <?php
              $rid = (int)($r['id'] ?? 0);
              $created = (string)($r['created_at'] ?? '');
              $isNew = false;
              if ($created !== '') {
                $ts = strtotime($created);
                $isNew = ($ts !== false) && ((time() - $ts) <= 86400);
              }
              $isActive = ($selectedId > 0 && $rid === $selectedId);
            ?>
            <a class="notice-row <?= $isActive ? 'active' : '' ?>" href="<?= e(url('/notice.php' . qs_keep(['id' => (string)$rid]))) ?>">
              <div class="notice-row-title">
                <span class="badge danger">중요</span>
                <span class="notice-title-text"><?= e((string)($r['title'] ?? '')) ?></span>
                <?php if ($isNew): ?>
                  <span class="badge accent">NEW</span>
                <?php endif; ?>
              </div>
              <div class="notice-row-meta">
                <span class="muted"><?= e($created) ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="notice-list-section-title"><?= $pinned ? '전체 공지' : '공지 목록' ?></div>

      <?php if (!$rows): ?>
        <div class="notice-empty">
          <?php if ($q !== ''): ?>
            <div style="font-weight:800;margin-bottom:6px">검색 결과가 없습니다.</div>
            <div class="muted">다른 키워드로 다시 검색해보세요.</div>
          <?php else: ?>
            <div style="font-weight:800;margin-bottom:6px">등록된 공지사항이 없습니다.</div>
            <div class="muted">관리자가 공지를 등록하면 여기에 표시됩니다.</div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="notice-rows">
          <?php foreach ($rows as $r): ?>
            <?php
              $rid = (int)($r['id'] ?? 0);
              $priority = (int)($r['priority'] ?? 0);
              $created = (string)($r['created_at'] ?? '');
              $isNew = false;
              if ($created !== '') {
                $ts = strtotime($created);
                $isNew = ($ts !== false) && ((time() - $ts) <= 86400);
              }
              $isActive = ($selectedId > 0 && $rid === $selectedId);
            ?>
            <a class="notice-row <?= $isActive ? 'active' : '' ?>" href="<?= e(url('/notice.php' . qs_keep(['id' => (string)$rid]))) ?>">
              <div class="notice-row-title">
                <?php if ($priority === 1): ?>
                  <span class="badge danger">중요</span>
                <?php else: ?>
                  <span class="badge">일반</span>
                <?php endif; ?>
                <span class="notice-title-text"><?= e((string)($r['title'] ?? '')) ?></span>
                <?php if ($isNew): ?>
                  <span class="badge accent">NEW</span>
                <?php endif; ?>
              </div>
              <div class="notice-row-meta">
                <span class="muted"><?= e($created) ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($pageCount > 1): ?>
        <div class="notice-pager" aria-label="페이지네이션">
          <a class="btn secondary" href="<?= e(url('/notice.php' . qs_keep(['page' => (string)max(1, $page - 1)]))) ?>" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>이전</a>
          <div class="notice-pager-pages">
            <?php
              $start = max(1, $page - 2);
              $end = min($pageCount, $page + 2);
              if ($start > 1) {
                echo '<a class="btn secondary" href="' . e(url('/notice.php' . qs_keep(['page' => '1']))) . '">1</a>';
                if ($start > 2) {
                  echo '<span class="muted" style="padding:0 6px">…</span>';
                }
              }
              for ($p = $start; $p <= $end; $p++) {
                $cls = ($p === $page) ? 'btn' : 'btn secondary';
                echo '<a class="' . $cls . '" href="' . e(url('/notice.php' . qs_keep(['page' => (string)$p]))) . '">' . e((string)$p) . '</a>';
              }
              if ($end < $pageCount) {
                if ($end < $pageCount - 1) {
                  echo '<span class="muted" style="padding:0 6px">…</span>';
                }
                echo '<a class="btn secondary" href="' . e(url('/notice.php' . qs_keep(['page' => (string)$pageCount]))) . '">' . e((string)$pageCount) . '</a>';
              }
            ?>
          </div>
          <a class="btn secondary" href="<?= e(url('/notice.php' . qs_keep(['page' => (string)min($pageCount, $page + 1)]))) ?>" <?= $page >= $pageCount ? 'aria-disabled="true" tabindex="-1"' : '' ?>>다음</a>
        </div>
      <?php endif; ?>
    </section>

    <aside class="notice-detail" aria-label="공지 상세">
      <?php if (!$selected): ?>
        <div class="notice-detail-empty">
          <?php if ($selectedExcluded): ?>
            <div style="font-weight:900;font-size:16px;margin-bottom:6px">현재 조건에 포함되지 않는 공지입니다</div>
            <div class="muted">필터/검색을 초기화하거나, 목록에서 다시 선택하세요.</div>
          <?php else: ?>
            <div style="font-weight:900;font-size:16px;margin-bottom:6px">공지를 선택하세요</div>
            <div class="muted">왼쪽 목록에서 공지 제목을 클릭하면 상세가 표시됩니다.</div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php
          $priority = (int)($selected['priority'] ?? 0);
          $created = (string)($selected['created_at'] ?? '');
          $authorRole = (string)($selected['author_role'] ?? '');
          $authorName = (string)($selected['author_name'] ?? '');
          // 공지 작성은 관리자만 가능하므로, 작성자 정보가 없을 때도 기본값은 '관리자'
          $authorLabel = '관리자';
          if ($authorRole !== '') {
            if ($authorRole === 'admin') {
              $authorLabel = '관리자';
            } else {
              $authorLabel = ($authorName !== '') ? $authorName : '-';
            }
          } elseif ($authorName !== '') {
            $authorLabel = $authorName;
          }
        ?>
        <div class="notice-detail-head">
          <div>
            <div class="notice-detail-title">
              <?= e((string)($selected['title'] ?? '')) ?>
            </div>
            <div class="notice-detail-meta">
              <?php if ($priority === 1): ?>
                <span class="badge danger">중요</span>
              <?php else: ?>
                <span class="badge">일반</span>
              <?php endif; ?>
              <span class="muted">작성일 <?= e($created) ?></span>
              <span class="muted">작성자: <?= e($authorLabel) ?></span>
            </div>
          </div>
          <?php if (is_admin()): ?>
            <div class="notice-detail-actions">
              <a class="btn secondary" href="<?= e(url('/notice_edit.php?id=' . (int)($selected['id'] ?? 0))) ?>">수정</a>
              <form method="post" action="<?= e(url('/notice.php' . qs_keep(['id' => (string)$selectedId]))) ?>" onsubmit="return confirm('정말 이 공지사항을 삭제할까요?')">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?= e((string)($selected['id'] ?? '')) ?>" />
                <button class="btn secondary" type="submit">삭제</button>
              </form>
            </div>
          <?php endif; ?>
        </div>

        <div class="notice-detail-body">
          <?= nl2br(e((string)($selected['body'] ?? '')))
          ?>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
