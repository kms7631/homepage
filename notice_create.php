<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$db = db();

$title = trim((string)($_POST['title'] ?? ''));
$body = trim((string)($_POST['body'] ?? ''));

if (is_post()) {
  try {
    $id = Notice::create($db, $title, $body, true);
    flash_set('success', '공지사항이 등록되었습니다.');
    redirect('/notice_view.php?id=' . $id);
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
