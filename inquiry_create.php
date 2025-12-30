<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$db = db();
$me = current_user();
$meId = (int)($me['id'] ?? 0);

$users = User::listAll($db);

$receiverId = (int)($_POST['receiver_id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$body = trim((string)($_POST['body'] ?? ''));

if (is_post()) {
  try {
    $id = Inquiry::create($db, $meId, $receiverId, $title, $body);
    flash_set('success', '문의가 전송되었습니다.');
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
      <h1 class="h1" style="margin-bottom:4px">1:1 문의 보내기</h1>
      <div class="muted">받는 사용자를 선택하고 제목/내용을 입력하세요.</div>
    </div>
    <div>
      <a class="btn secondary" href="<?= e(url('/inquiry_list.php')) ?>">목록</a>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <form method="post" action="<?= e(url('/inquiry_create.php')) ?>">
    <div class="form-row">
      <div class="field" style="min-width:280px">
        <div class="label">받는 사람</div>
        <select name="receiver_id" required>
          <option value="0">선택...</option>
          <?php foreach ($users as $u): ?>
            <?php $uid = (int)($u['id'] ?? 0); if ($uid <= 0 || $uid === $meId) continue; ?>
            <?php $label = (string)($u['name'] ?? '') . ' (' . (string)($u['email'] ?? '') . ')'; ?>
            <option value="<?= e((string)$uid) ?>" <?= $receiverId === $uid ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
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

    <button class="btn" type="submit">보내기</button>
    <a class="btn secondary" href="<?= e(url('/inquiry_list.php')) ?>">취소</a>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
