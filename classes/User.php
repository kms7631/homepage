<?php
declare(strict_types=1);

final class User {
  public static function findById(PDO $db, int $id): ?array {
    $st = $db->prepare('SELECT u.id, u.email, u.name, u.role, u.supplier_id, u.created_at,
                               sp.name AS supplier_name
                        FROM users u
                        LEFT JOIN suppliers sp ON sp.id = u.supplier_id
                        WHERE u.id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function findByEmail(PDO $db, string $email): ?array {
    $st = $db->prepare('SELECT u.*, sp.name AS supplier_name
                        FROM users u
                        LEFT JOIN suppliers sp ON sp.id = u.supplier_id
                        WHERE u.email = ?');
    $st->execute([$email]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function create(PDO $db, string $email, string $name, string $password, string $role = 'user'): int {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = $db->prepare('INSERT INTO users (email, password_hash, name, role, supplier_id) VALUES (?, ?, ?, ?, ?)');
    $st->execute([$email, $hash, $name, $role, null]);
    return (int)$db->lastInsertId();
  }

  public static function createWithSupplier(PDO $db, string $email, string $name, string $password, int $supplierId): int {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = $db->prepare('INSERT INTO users (email, password_hash, name, role, supplier_id) VALUES (?, ?, ?, \'user\', ?)');
    $st->execute([$email, $hash, $name, $supplierId]);
    return (int)$db->lastInsertId();
  }

  public static function updateProfile(PDO $db, int $userId, string $name, ?int $supplierId, ?string $newPassword): void {
    if ($newPassword !== null && $newPassword !== '') {
      $hash = password_hash($newPassword, PASSWORD_DEFAULT);
      $st = $db->prepare('UPDATE users SET name = ?, supplier_id = ?, password_hash = ? WHERE id = ?');
      $st->execute([$name, $supplierId, $hash, $userId]);
      return;
    }
    $st = $db->prepare('UPDATE users SET name = ?, supplier_id = ? WHERE id = ?');
    $st->execute([$name, $supplierId, $userId]);
  }

  public static function verifyLogin(PDO $db, string $email, string $password): ?array {
    $user = self::findByEmail($db, $email);
    if (!$user) {
      return null;
    }
    if (!password_verify($password, $user['password_hash'])) {
      return null;
    }
    return $user;
  }

  public static function listAll(PDO $db): array {
    $st = $db->query('SELECT u.id, u.email, u.name, u.role, u.supplier_id, u.created_at, sp.name AS supplier_name
                      FROM users u
                      LEFT JOIN suppliers sp ON sp.id = u.supplier_id
                      ORDER BY u.id DESC');
    return $st->fetchAll();
  }

  public static function delete(PDO $db, int $id): void {
    $st = $db->prepare('DELETE FROM users WHERE id = ?');
    $st->execute([$id]);
  }
}
