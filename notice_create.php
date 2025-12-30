<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$db = db();

$supportsPriority = Notice::supportsPriority($db);
$supportsAuthor = Notice::supportsAuthor($db);

$title = trim((string)($_POST['title'] ?? ''));
$body = trim((string)($_POST['body'] ?? ''));
$priority = (int)($_POST['priority'] ?? 0);

if (is_post()) {
  try {
    if (!$supportsPriority && $priority === 1) {
      throw new RuntimeException('중요 공지를 사용하려면 DB 마이그레이션(migrate_add_notice_priority.sql)을 먼저 실행하세요.');
    }
    $me = current_user();
    $authorId = ($supportsAuthor && $me) ? (int)($me['id'] ?? 0) : null;
    $id = Notice::create($db, $title, $body, $priority, true, $authorId);
    flash_set('success', '공지사항이 등록되었습니다.');
    redirect('/notice.php?id=' . $id);
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">공지사항 작성</h1>
      <div class="muted">관리자만 작성할 수 있습니다.</div>
    </div>
    <div>
      <a class="btn secondary" href="<?= e(url('/notice.php')) ?>">목록</a>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <form method="post" action="<?= e(url('/notice_create.php')) ?>">
    <div class="form-row">
      <div class="field" style="min-width:320px;flex:1">
        <div class="label">제목</div>
        <input class="input" type="text" name="title" value="<?= e($title) ?>" required />
      </div>
      <div class="field" style="min-width:160px">
        <div class="label">구분</div>
        <select name="priority" class="input" <?= $supportsPriority ? '' : 'disabled' ?>>
          <option value="0" <?= $priority === 0 ? 'selected' : '' ?>>일반</option>
          <option value="1" <?= $priority === 1 ? 'selected' : '' ?>>중요</option>
        </select>
        <?php if (!$supportsPriority): ?>
          <div class="small">중요/일반 구분을 사용하려면 DB에 priority 컬럼 추가가 필요합니다.</div>
          <div class="small">`migrate_add_notice_priority.sql` 실행 후 활성화됩니다.</div>
          <input type="hidden" name="priority" value="0" />
        <?php endif; ?>
      </div>
    </div>
    <div style="margin-top:10px"></div>
    <div class="field">
      <div class="label">내용</div>
      <textarea name="body" required><?= e($body) ?></textarea>
    </div>
    <div style="margin-top:10px"></div>
    <button class="btn" type="submit">등록</button>
    <a class="btn secondary" href="<?= e(url('/notice.php')) ?>">취소</a>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
