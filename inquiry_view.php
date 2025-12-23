<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$db = db();
$me = current_user();
$meId = (int)($me['id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('error', '잘못된 접근입니다.');
  redirect('/inquiry_list.php');
}

$row = Inquiry::findForUser($db, $id, $meId);
if (!$row) {
  flash_set('error', '문의 내역을 찾을 수 없습니다.');
  redirect('/inquiry_list.php');
}

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action !== 'reply') {
      throw new RuntimeException('잘못된 요청입니다.');
    }
    $body = (string)($_POST['reply_body'] ?? '');
    Inquiry::addMessage($db, $id, $meId, $meId, $body);
    flash_set('success', '답변이 등록되었습니다.');
    redirect('/inquiry_view.php?id=' . $id);
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    redirect('/inquiry_view.php?id=' . $id);
  }
}

$messages = Inquiry::listMessages($db, $id, $meId, 300);

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">1:1 문의</h1>
      <div class="muted"><?= e((string)($row['created_at'] ?? '')) ?></div>
    </div>
    <div style="display:flex;gap:8px">
      <a class="btn secondary" href="<?= e(url('/inquiry_list.php')) ?>">목록</a>
      <a class="btn secondary" href="<?= e(url('/inquiry_edit.php?id=' . $id)) ?>">수정</a>
      <form method="post" action="<?= e(url('/inquiry_list.php')) ?>" style="display:inline" onsubmit="return confirm('정말 이 문의를 삭제할까요?')">
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" name="id" value="<?= e((string)$id) ?>" />
        <button class="btn secondary" type="submit">삭제</button>
      </form>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <div style="font-weight:800;margin-bottom:10px;word-break:keep-all;overflow-wrap:normal">
    <?= e((string)($row['title'] ?? '')) ?>
  </div>

  <div class="small" style="margin-bottom:10px">
    보낸 사람: <?= e((string)($row['sender_name'] ?? '')) ?> (<?= e((string)($row['sender_email'] ?? '')) ?>)
    <span class="muted">·</span>
    받는 사람: <?= e((string)($row['receiver_name'] ?? '')) ?> (<?= e((string)($row['receiver_email'] ?? '')) ?>)
  </div>

  <div style="white-space:pre-wrap;line-height:1.55">
    <?= e((string)($row['body'] ?? '')) ?>
  </div>

  <div style="margin-top:16px"></div>

  <h2 class="h1" style="margin-bottom:8px">답변</h2>
  <?php if (!$messages): ?>
    <div class="muted">등록된 답변이 없습니다.</div>
  <?php else: ?>
    <table class="table table-compact">
      <thead>
        <tr>
          <th>작성자</th>
          <th>내용</th>
          <th>작성일</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($messages as $m): ?>
          <tr>
            <td style="white-space:nowrap">
              <?= e((string)($m['sender_name'] ?? '')) ?>
              <div class="small"><?= e((string)($m['sender_email'] ?? '')) ?></div>
            </td>
            <td style="white-space:pre-wrap;line-height:1.55"><?= e((string)($m['body'] ?? '')) ?></td>
            <td style="white-space:nowrap"><?= e((string)($m['created_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div style="margin-top:12px"></div>

  <form method="post" action="<?= e(url('/inquiry_view.php?id=' . $id)) ?>">
    <input type="hidden" name="action" value="reply" />
    <div class="field">
      <div class="label">답변 작성</div>
      <textarea name="reply_body" required></textarea>
    </div>
    <div style="margin-top:10px"></div>
    <button class="btn" type="submit">답변 등록</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
