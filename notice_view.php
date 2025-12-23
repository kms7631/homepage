<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_supplier();

$db = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('error', '잘못된 접근입니다.');
  redirect('/notice.php');
}

$notice = Notice::find($db, $id);
if (!$notice) {
  flash_set('error', '공지사항을 찾을 수 없습니다.');
  redirect('/notice.php');
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">공지사항</h1>
      <div class="muted"><?= e((string)($notice['created_at'] ?? '')) ?></div>
    </div>
    <div>
      <a class="btn secondary" href="<?= e(url('/notice.php')) ?>">목록</a>
      <?php if (is_admin()): ?>
        <a class="btn secondary" href="<?= e(url('/notice_edit.php?id=' . $id)) ?>">수정</a>
        <form method="post" action="<?= e(url('/notice.php')) ?>" style="display:inline" onsubmit="return confirm('정말 이 공지사항을 삭제할까요?')">
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="id" value="<?= e((string)$id) ?>" />
          <button class="btn secondary" type="submit">삭제</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <div style="font-weight:800;margin-bottom:10px;word-break:keep-all;overflow-wrap:normal">
    <?= e((string)($notice['title'] ?? '')) ?>
  </div>
  <div style="white-space:pre-wrap;line-height:1.55">
    <?= e((string)($notice['body'] ?? '')) ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
