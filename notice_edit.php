<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$db = db();
$supportsPriority = Notice::supportsPriority($db);
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
  flash_set('error', '잘못된 접근입니다.');
  redirect('/notice.php');
}

$notice = Notice::find($db, $id);
if (!$notice) {
  flash_set('error', '공지사항을 찾을 수 없습니다.');
  redirect('/notice.php');
}

$title = trim((string)($_POST['title'] ?? (string)($notice['title'] ?? '')));
$body = trim((string)($_POST['body'] ?? (string)($notice['body'] ?? '')));
$priority = (int)($_POST['priority'] ?? (int)($notice['priority'] ?? 0));

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? 'update'));
    if ($action === 'delete') {
      Notice::delete($db, $id);
      flash_set('success', '공지사항이 삭제되었습니다.');
      redirect('/notice.php');
    }

    if (!$supportsPriority && $priority === 1) {
      throw new RuntimeException('중요 공지를 사용하려면 DB 마이그레이션(migrate_add_notice_priority.sql)을 먼저 실행하세요.');
    }

    Notice::update($db, $id, $title, $body, $priority, true);
    flash_set('success', '공지사항이 수정되었습니다.');
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
      <h1 class="h1" style="margin-bottom:4px">공지사항 수정</h1>
      <div class="muted">관리자만 수정/삭제할 수 있습니다.</div>
    </div>
    <div style="display:flex;gap:8px">
      <a class="btn secondary" href="<?= e(url('/notice_view.php?id=' . $id)) ?>">취소</a>
      <form method="post" action="<?= e(url('/notice_edit.php')) ?>" onsubmit="return confirm('정말 이 공지사항을 삭제할까요?')">
        <input type="hidden" name="id" value="<?= e((string)$id) ?>" />
        <input type="hidden" name="action" value="delete" />
        <button class="btn danger" type="submit">삭제</button>
      </form>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <form method="post" action="<?= e(url('/notice_edit.php')) ?>">
    <input type="hidden" name="id" value="<?= e((string)$id) ?>" />
    <input type="hidden" name="action" value="update" />

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
    <button class="btn" type="submit">저장</button>
    <a class="btn secondary" href="<?= e(url('/notice_view.php?id=' . $id)) ?>">취소</a>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
