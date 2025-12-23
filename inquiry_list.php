<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$db = db();
$me = current_user();
$meId = (int)($me['id'] ?? 0);

$box = strtolower(trim((string)($_GET['box'] ?? 'sent')));
if (!in_array($box, ['sent', 'received'], true)) {
  $box = 'sent';
}

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action !== 'delete') {
      throw new RuntimeException('잘못된 요청입니다.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      throw new RuntimeException('삭제할 문의가 올바르지 않습니다.');
    }
    Inquiry::delete($db, $id, $meId);
    flash_set('success', '문의가 삭제되었습니다.');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
  redirect('/inquiry_list.php');
}

$rows = Inquiry::listForUser($db, $meId, $box, 200);

require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="kpi">
    <div>
      <h1 class="h1" style="margin-bottom:4px">1:1 문의</h1>
      <div class="muted">사용자 간 1:1 문의를 주고받습니다.</div>
    </div>
    <div>
      <a class="btn" href="<?= e(url('/inquiry_create.php')) ?>">문의 보내기</a>
    </div>
  </div>

  <div style="margin-top:10px"></div>

  <div class="form-row" style="align-items:center">
    <div class="field" style="display:flex;gap:8px;align-items:center">
      <a class="btn <?= $box === 'sent' ? '' : 'secondary' ?>" href="<?= e(url('/inquiry_list.php?box=sent')) ?>">보낸 문의</a>
      <a class="btn <?= $box === 'received' ? '' : 'secondary' ?>" href="<?= e(url('/inquiry_list.php?box=received')) ?>">받은 문의</a>
    </div>
    <div class="field" style="flex:1"></div>
    <div class="field">
      <span class="badge">현재 보기: <?= e($box === 'sent' ? '보낸 문의' : '받은 문의') ?></span>
    </div>
  </div>

  <div style="margin-top:12px"></div>

  <table class="table">
    <thead>
      <tr>
        <th>제목</th>
        <th>상대</th>
        <th>작성일</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="muted">문의 내역이 없습니다.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $isSent = ((int)$r['sender_id'] === $meId);
            $otherName = $isSent ? (string)($r['receiver_name'] ?? '') : (string)($r['sender_name'] ?? '');
            $otherEmail = $isSent ? (string)($r['receiver_email'] ?? '') : (string)($r['sender_email'] ?? '');
          ?>
          <tr>
            <td>
              <a href="<?= e(url('/inquiry_view.php?id=' . (int)$r['id'])) ?>">
                <?= e((string)($r['title'] ?? '')) ?>
              </a>
            </td>
            <td><?= e($otherName . ($otherEmail ? (' (' . $otherEmail . ')') : '')) ?></td>
            <td><?= e((string)($r['created_at'] ?? '')) ?></td>
            <td style="white-space:nowrap">
              <a class="btn secondary" href="<?= e(url('/inquiry_edit.php?id=' . (int)$r['id'])) ?>">수정</a>
              <form method="post" action="<?= e(url('/inquiry_list.php')) ?>" style="display:inline" onsubmit="return confirm('정말 이 문의를 삭제할까요?')">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>" />
                <button class="btn secondary" type="submit">삭제</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
