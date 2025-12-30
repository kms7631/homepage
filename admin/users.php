<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$db = db();

function make_temp_password(): string {
  $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
  $lower = 'abcdefghijkmnopqrstuvwxyz';
  $digits = '23456789';
  $all = $upper . $lower . $digits;

  $chars = [];
  $chars[] = $upper[random_int(0, strlen($upper) - 1)];
  $chars[] = $lower[random_int(0, strlen($lower) - 1)];
  $chars[] = $digits[random_int(0, strlen($digits) - 1)];

  $len = 12;
  while (count($chars) < $len) {
    $chars[] = $all[random_int(0, strlen($all) - 1)];
  }

  // 안전한 셔플
  for ($i = count($chars) - 1; $i > 0; $i--) {
    $j = random_int(0, $i);
    $tmp = $chars[$i];
    $chars[$i] = $chars[$j];
    $chars[$j] = $tmp;
  }

  return implode('', $chars);
}

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? ''));

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      throw new RuntimeException('삭제할 사용자가 올바르지 않습니다.');
    }

    $me = current_user();
    if ($me && (int)$me['id'] === $id) {
      if ($action === 'delete') {
        throw new RuntimeException('현재 로그인한 계정은 삭제할 수 없습니다.');
      }
      if ($action === 'issue_temp_password') {
        throw new RuntimeException('현재 로그인한 계정은 임시 비밀번호로 초기화할 수 없습니다.');
      }
    }

    if ($action === 'delete') {
      User::delete($db, $id);
      flash_set('success', '사용자가 삭제되었습니다.');
      redirect('/admin/users.php');
    }

    if ($action === 'issue_temp_password') {
      $u = User::findById($db, $id);
      if (!$u) {
        throw new RuntimeException('사용자를 찾을 수 없습니다.');
      }
      $temp = make_temp_password();
      $hash = password_hash($temp, PASSWORD_DEFAULT);
      $st = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
      $st->execute([$hash, $id]);
      flash_set('success', '임시 비밀번호가 발급되었습니다. 사용자에게 전달하세요: ' . (string)$u['email'] . ' / 임시 비밀번호: ' . $temp);
      redirect('/admin/users.php');
    }

    throw new RuntimeException('잘못된 요청입니다.');
  } catch (PDOException $e) {
    // FK 제약(발주/입고 등)으로 삭제가 막힐 수 있음
    error_log('[admin.users.delete] PDOException: ' . $e->getMessage());
    flash_set('error', '삭제할 수 없습니다. 해당 사용자가 발주/입고 등 데이터에 연결되어 있을 수 있습니다.');
  } catch (Throwable $e) {
    flash_set('error', $e->getMessage());
  }
}

$users = User::listAll($db);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h1 class="h1">사용자 관리</h1>
  <div class="small">권한(role)은 초기 데이터(seed) 기준으로 제공합니다.</div>

  <div style="margin-top:12px"></div>

  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>이메일</th>
        <th>이름</th>
        <th>연락처</th>
        <th>거래처</th>
        <th>권한</th>
        <th>생성일</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e((string)$u['id']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['name']) ?></td>
          <td><?= e(($u['phone'] ?? '') ?: '-') ?></td>
          <td><?= e($u['supplier_name'] ?: '-') ?></td>
          <td><span class="badge"><?= e($u['role']) ?></span></td>
          <td><?= e($u['created_at']) ?></td>
          <td style="white-space:nowrap">
            <?php if ((int)$u['id'] === (int)current_user()['id']): ?>
              <span class="muted">-</span>
            <?php else: ?>
              <form method="post" action="<?= e(url('/admin/users.php')) ?>" style="display:inline" onsubmit="return confirm('임시 비밀번호를 발급하여 즉시 초기화합니다. 진행할까요?');">
                <input type="hidden" name="action" value="issue_temp_password" />
                <input type="hidden" name="id" value="<?= e((string)$u['id']) ?>" />
                <button class="btn danger" type="submit">임시비번</button>
              </form>
              <form method="post" action="<?= e(url('/admin/users.php')) ?>" style="display:inline" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?= e((string)$u['id']) ?>" />
                <button class="btn secondary" type="submit">삭제</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:10px">
    <a class="btn secondary" href="<?= e(url('/admin/password_resets.php')) ?>">비밀번호 재설정 요청</a>
    <a class="btn secondary" href="<?= e(url('/admin/suppliers.php')) ?>">거래처</a>
    <a class="btn secondary" href="<?= e(url('/admin/items.php')) ?>">품목</a>
    <a class="btn secondary" href="<?= e(url('/admin/inventory.php')) ?>">재고</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
