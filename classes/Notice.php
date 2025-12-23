<?php
declare(strict_types=1);

final class Notice {
  public static function list(PDO $db, int $limit = 50): array {
    $limit = max(1, min(200, $limit));
    $st = $db->prepare('SELECT id, title, created_at FROM notices WHERE active = 1 ORDER BY created_at DESC, id DESC LIMIT ?');
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  public static function find(PDO $db, int $id): ?array {
    $st = $db->prepare('SELECT id, title, body, created_at FROM notices WHERE id = ? AND active = 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function create(PDO $db, string $title, string $body, bool $active = true): int {
    $title = trim($title);
    $body = trim($body);
    if ($title === '') {
      throw new RuntimeException('제목을 입력하세요.');
    }
    if ($body === '') {
      throw new RuntimeException('내용을 입력하세요.');
    }

    $st = $db->prepare('INSERT INTO notices (title, body, active) VALUES (?, ?, ?)');
    $st->execute([$title, $body, $active ? 1 : 0]);
    return (int)$db->lastInsertId();
  }

  public static function update(PDO $db, int $id, string $title, string $body, bool $active = true): void {
    if ($id <= 0) {
      throw new RuntimeException('수정할 공지사항이 올바르지 않습니다.');
    }
    $title = trim($title);
    $body = trim($body);
    if ($title === '') {
      throw new RuntimeException('제목을 입력하세요.');
    }
    if ($body === '') {
      throw new RuntimeException('내용을 입력하세요.');
    }

    $st = $db->prepare('UPDATE notices SET title = ?, body = ?, active = ? WHERE id = ?');
    $st->execute([$title, $body, $active ? 1 : 0, $id]);
  }

  public static function delete(PDO $db, int $id): void {
    if ($id <= 0) {
      throw new RuntimeException('삭제할 공지사항이 올바르지 않습니다.');
    }
    // soft delete
    $st = $db->prepare('UPDATE notices SET active = 0 WHERE id = ?');
    $st->execute([$id]);
  }
}
