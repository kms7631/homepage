<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$db = db();
$me = current_user();
$meId = (int)($me['id'] ?? 0);

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
  flash_set('error', '잘못된 접근입니다.');
  redirect('/inquiry_list.php');
}

$row = Inquiry::findForUser($db, $id, $meId);
if (!$row) {
  flash_set('error', '문의 내역을 찾을 수 없습니다.');
  redirect('/inquiry_list.php');
}

$title = trim((string)($_POST['title'] ?? (string)($row['title'] ?? '')));
$body = trim((string)($_POST['body'] ?? (string)($row['body'] ?? '')));

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? 'update'));
    if ($action === 'delete') {
      Inquiry::delete($db, $id, $meId);
      flash_set('success', '문의가 삭제되었습니다.');
      redirect('/inquiry_list.php');
    }

    Inquiry::update($db, $id, $meId, $title, $body);
    flash_set('success', '문의가 수정되었습니다.');
    redirect('/inquiry_list.php?id=' . $id);
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">1:1 문의 수정</h1>
      <div class="muted">보낸 사람/받는 사람 모두 수정·삭제할 수 있습니다.</div>
    </div>
    <div style="display:flex;gap:8px">
      <a class="btn secondary" href="<?= e(url('/inquiry_view.php?id=' . $id)) ?>">취소</a>
      <form method="post" action="<?= e(url('/inquiry_edit.php')) ?>" onsubmit="return confirm('정말 이 문의를 삭제할까요?')">
        <input type="hidden" name="id" value="<?= e((string)$id) ?>" />
        <input type="hidden" name="action" value="delete" />
        <button class="btn danger" type="submit">삭제</button>
      </form>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <form method="post" action="<?= e(url('/inquiry_edit.php')) ?>">
    <input type="hidden" name="id" value="<?= e((string)$id) ?>" />
    <input type="hidden" name="action" value="update" />

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

    <button class="btn" type="submit">저장</button>
    <a class="btn secondary" href="<?= e(url('/inquiry_view.php?id=' . $id)) ?>">취소</a>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
