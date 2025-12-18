<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$db = db();

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action !== 'delete') {
      throw new RuntimeException('잘못된 요청입니다.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      throw new RuntimeException('삭제할 사용자가 올바르지 않습니다.');
    }

    $me = current_user();
    if ($me && (int)$me['id'] === $id) {
      throw new RuntimeException('현재 로그인한 계정은 삭제할 수 없습니다.');
    }

    User::delete($db, $id);
    flash_set('success', '사용자가 삭제되었습니다.');
    redirect('/admin/users.php');
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
    <a class="btn secondary" href="<?= e(url('/admin/suppliers.php')) ?>">거래처</a>
    <a class="btn secondary" href="<?= e(url('/admin/items.php')) ?>">품목</a>
    <a class="btn secondary" href="<?= e(url('/admin/inventory.php')) ?>">재고</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
