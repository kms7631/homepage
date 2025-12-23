<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();

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
    redirect('/notice.php');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    redirect('/notice.php');
  }
}

$rows = Notice::list($db, 100);

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">공지사항</h1>
      <div class="muted">중요 안내사항을 확인합니다.</div>
    </div>
    <?php if (is_admin()): ?>
      <div>
        <a class="btn" href="<?= e(url('/notice_create.php')) ?>">공지 작성</a>
      </div>
    <?php endif; ?>
  </div>

  <div style="margin-top:12px"></div>

  <table class="table">
    <thead>
      <tr>
        <th>제목</th>
        <th>작성일</th>
        <?php if (is_admin()): ?>
          <th></th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= is_admin() ? '3' : '2' ?>" class="muted">등록된 공지사항이 없습니다.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <a href="<?= e(url('/notice_view.php?id=' . (int)$r['id'])) ?>">
                <?= e((string)($r['title'] ?? '')) ?>
              </a>
            </td>
            <td><?= e((string)($r['created_at'] ?? '')) ?></td>
            <?php if (is_admin()): ?>
              <td style="white-space:nowrap">
                <a class="btn secondary" href="<?= e(url('/notice_edit.php?id=' . (int)$r['id'])) ?>">수정</a>
                <form method="post" action="<?= e(url('/notice.php')) ?>" style="display:inline" onsubmit="return confirm('정말 이 공지사항을 삭제할까요?')">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>" />
                  <button class="btn secondary" type="submit">삭제</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
