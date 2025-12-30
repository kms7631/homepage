<?php
declare(strict_types=1);

final class Notice {
  private static ?bool $hasPriorityCol = null;
  private static ?bool $hasAuthorCol = null;

  private static function hasPriority(PDO $db): bool {
    if (self::$hasPriorityCol !== null) {
      return self::$hasPriorityCol;
    }
    try {
      $st = $db->prepare("SHOW COLUMNS FROM notices LIKE 'priority'");
      $st->execute();
      $row = $st->fetch();
      self::$hasPriorityCol = $row ? true : false;
    } catch (Throwable $e) {
      self::$hasPriorityCol = false;
    }
    return self::$hasPriorityCol;
  }

  public static function supportsPriority(PDO $db): bool {
    return self::hasPriority($db);
  }

  private static function hasAuthor(PDO $db): bool {
    if (self::$hasAuthorCol !== null) {
      return self::$hasAuthorCol;
    }
    try {
      $st = $db->prepare("SHOW COLUMNS FROM notices LIKE 'author_id'");
      $st->execute();
      $row = $st->fetch();
      self::$hasAuthorCol = $row ? true : false;
    } catch (Throwable $e) {
      self::$hasAuthorCol = false;
    }
    return self::$hasAuthorCol;
  }

  public static function supportsAuthor(PDO $db): bool {
    return self::hasAuthor($db);
  }

  private static function normalizePriority(int $priority): int {
    return $priority === 1 ? 1 : 0;
  }

  private static function normalizeSort(string $sort): string {
    $sort = strtolower(trim($sort));
    return ($sort === 'oldest') ? 'oldest' : 'latest';
  }

  /**
   * @param int[] $excludeIds
   */
  public static function listPaged(PDO $db, int $limit, int $offset, string $q = '', string $sort = 'latest', array $excludeIds = [], ?int $priorityFilter = null): array {
    $limit = max(1, min(50, $limit));
    $offset = max(0, $offset);
    $sort = self::normalizeSort($sort);

    $where = 'active = 1';
    $params = [];

    if ($priorityFilter !== null) {
      $priorityFilter = self::normalizePriority($priorityFilter);
      if (self::hasPriority($db)) {
        $where .= ' AND priority = ?';
        $params[] = $priorityFilter;
      } else {
        // priority 컬럼이 없는 스키마에서는 모두 일반(0)로 취급
        if ($priorityFilter === 1) {
          return [];
        }
      }
    }

    $q = trim($q);
    if ($q !== '') {
      $where .= ' AND (title LIKE ? OR body LIKE ?)';
      $like = '%' . $q . '%';
      $params[] = $like;
      $params[] = $like;
    }

    $excludeIds = array_values(array_filter(array_map('intval', $excludeIds), fn($v) => $v > 0));
    if ($excludeIds) {
      $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
      $where .= ' AND id NOT IN (' . $placeholders . ')';
      foreach ($excludeIds as $id) {
        $params[] = $id;
      }
    }

    $order = ($sort === 'oldest')
      ? 'created_at ASC, id ASC'
      : 'created_at DESC, id DESC';

    $select = self::hasPriority($db)
      ? 'SELECT id, title, priority, created_at'
      : 'SELECT id, title, 0 AS priority, created_at';
    $sql = $select . ' FROM notices WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?';
    $st = $db->prepare($sql);

    $i = 1;
    foreach ($params as $p) {
      $st->bindValue($i, $p);
      $i++;
    }
    $st->bindValue($i, $limit, PDO::PARAM_INT);
    $st->bindValue($i + 1, $offset, PDO::PARAM_INT);

    $st->execute();
    return $st->fetchAll();
  }

  public static function count(PDO $db, string $q = '', array $excludeIds = [], ?int $priorityFilter = null): int {
    $where = 'active = 1';
    $params = [];

    if ($priorityFilter !== null) {
      $priorityFilter = self::normalizePriority($priorityFilter);
      if (self::hasPriority($db)) {
        $where .= ' AND priority = ?';
        $params[] = $priorityFilter;
      } else {
        if ($priorityFilter === 1) {
          return 0;
        }
      }
    }

    $q = trim($q);
    if ($q !== '') {
      $where .= ' AND (title LIKE ? OR body LIKE ?)';
      $like = '%' . $q . '%';
      $params[] = $like;
      $params[] = $like;
    }

    $excludeIds = array_values(array_filter(array_map('intval', $excludeIds), fn($v) => $v > 0));
    if ($excludeIds) {
      $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
      $where .= ' AND id NOT IN (' . $placeholders . ')';
      foreach ($excludeIds as $id) {
        $params[] = $id;
      }
    }

    $st = $db->prepare('SELECT COUNT(*) AS c FROM notices WHERE ' . $where);
    $st->execute($params);
    $row = $st->fetch();
    return (int)($row['c'] ?? 0);
  }

  public static function listPinned(PDO $db, int $limit = 3): array {
    if (!self::hasPriority($db)) {
      return [];
    }
    $limit = max(1, min(3, $limit));
    $st = $db->prepare('SELECT id, title, priority, created_at FROM notices WHERE active = 1 AND priority = 1 ORDER BY created_at DESC, id DESC LIMIT ?');
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  public static function find(PDO $db, int $id): ?array {
    $hasPriority = self::hasPriority($db);
    $hasAuthor = self::hasAuthor($db);

    if ($hasAuthor) {
      $sql = $hasPriority
        ? "SELECT n.id, n.title, n.body, n.priority, n.author_id, n.created_at, n.updated_at, u.name AS author_name, u.role AS author_role
            FROM notices n
            LEFT JOIN users u ON u.id = n.author_id
            WHERE n.id = ? AND n.active = 1"
        : "SELECT n.id, n.title, n.body, 0 AS priority, n.author_id, n.created_at, n.updated_at, u.name AS author_name, u.role AS author_role
            FROM notices n
            LEFT JOIN users u ON u.id = n.author_id
            WHERE n.id = ? AND n.active = 1";
    } else {
      $sql = $hasPriority
        ? 'SELECT id, title, body, priority, NULL AS author_id, NULL AS author_name, NULL AS author_role, created_at, updated_at FROM notices WHERE id = ? AND active = 1'
        : 'SELECT id, title, body, 0 AS priority, NULL AS author_id, NULL AS author_name, NULL AS author_role, created_at, updated_at FROM notices WHERE id = ? AND active = 1';
    }
    $st = $db->prepare($sql);
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function create(PDO $db, string $title, string $body, int $priority = 0, bool $active = true, ?int $authorId = null): int {
    $title = trim($title);
    $body = trim($body);
    $priority = self::normalizePriority($priority);
    if ($title === '') {
      throw new RuntimeException('제목을 입력하세요.');
    }
    if ($body === '') {
      throw new RuntimeException('내용을 입력하세요.');
    }

    $hasPriority = self::hasPriority($db);
    $hasAuthor = self::hasAuthor($db);
    $authorId = ($authorId !== null && $authorId > 0) ? $authorId : null;

    if ($hasPriority && $hasAuthor) {
      $st = $db->prepare('INSERT INTO notices (title, body, priority, author_id, active) VALUES (?, ?, ?, ?, ?)');
      $st->execute([$title, $body, $priority, $authorId, $active ? 1 : 0]);
    } elseif ($hasPriority) {
      $st = $db->prepare('INSERT INTO notices (title, body, priority, active) VALUES (?, ?, ?, ?)');
      $st->execute([$title, $body, $priority, $active ? 1 : 0]);
    } elseif ($hasAuthor) {
      $st = $db->prepare('INSERT INTO notices (title, body, author_id, active) VALUES (?, ?, ?, ?)');
      $st->execute([$title, $body, $authorId, $active ? 1 : 0]);
    } else {
      $st = $db->prepare('INSERT INTO notices (title, body, active) VALUES (?, ?, ?)');
      $st->execute([$title, $body, $active ? 1 : 0]);
    }
    return (int)$db->lastInsertId();
  }

  public static function update(PDO $db, int $id, string $title, string $body, int $priority = 0, bool $active = true): void {
    if ($id <= 0) {
      throw new RuntimeException('수정할 공지사항이 올바르지 않습니다.');
    }
    $title = trim($title);
    $body = trim($body);
    $priority = self::normalizePriority($priority);
    if ($title === '') {
      throw new RuntimeException('제목을 입력하세요.');
    }
    if ($body === '') {
      throw new RuntimeException('내용을 입력하세요.');
    }

    if (self::hasPriority($db)) {
      $st = $db->prepare('UPDATE notices SET title = ?, body = ?, priority = ?, active = ? WHERE id = ?');
      $st->execute([$title, $body, $priority, $active ? 1 : 0, $id]);
    } else {
      $st = $db->prepare('UPDATE notices SET title = ?, body = ?, active = ? WHERE id = ?');
      $st->execute([$title, $body, $active ? 1 : 0, $id]);
    }
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
