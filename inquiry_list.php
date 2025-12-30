<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$db = db();
$me = current_user();
$meId = (int)($me['id'] ?? 0);

function wants_json(): bool {
  $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
  $xrw = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
  return (stripos($accept, 'application/json') !== false) || (strtolower($xrw) === 'fetch');
}

function time_ago(string $dt): string {
  $ts = strtotime($dt);
  if ($ts === false) {
    return $dt;
  }
  $diff = time() - $ts;
  if ($diff < 0) {
    $diff = 0;
  }
  if ($diff < 60) {
    return '방금 전';
  }
  if ($diff < 3600) {
    return (int)floor($diff / 60) . '분 전';
  }
  if ($diff < 86400) {
    return (int)floor($diff / 3600) . '시간 전';
  }
  return date('Y-m-d', $ts);
}

$selectedId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($selectedId < 0) {
  $selectedId = 0;
}

if (is_post()) {
  try {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        throw new RuntimeException('삭제할 문의가 올바르지 않습니다.');
      }
      Inquiry::delete($db, $id, $meId);
      if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
      }
      flash_set('success', '문의가 삭제되었습니다.');
      redirect('/inquiry_list.php');
    }

    if ($action === 'send_message') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        throw new RuntimeException('잘못된 요청입니다.');
      }
      $body = (string)($_POST['body'] ?? '');
      $messageId = Inquiry::addMessage($db, $id, $meId, $meId, $body);

      if (wants_json()) {
        $msg = Inquiry::findMessageForUser($db, $messageId, $meId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => $msg]);
        exit;
      }

      redirect('/inquiry_list.php?id=' . $id);
    }

    throw new RuntimeException('잘못된 요청입니다.');
  } catch (Throwable $e) {
    if (wants_json()) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
      exit;
    }

    flash_set('error', $e->getMessage());
  }
  redirect('/inquiry_list.php' . ($selectedId > 0 ? ('?id=' . $selectedId) : ''));
}

$rows = Inquiry::listThreadsForUser($db, $meId, 200);

$selected = null;
$messages = [];
if ($selectedId > 0) {
  $selected = Inquiry::findForUser($db, $selectedId, $meId);
  if ($selected) {
    $messages = Inquiry::listMessages($db, $selectedId, $meId, 300);
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card inquiry-board">
  <div class="inquiry-top">
    <div>
      <h1 class="h1" style="margin-bottom:4px;white-space:normal">1:1 문의</h1>
    </div>
    <div>
      <a class="btn" href="<?= e(url('/inquiry_create.php')) ?>">문의 보내기</a>
    </div>
  </div>

  <div class="inquiry-split">
    <section class="inquiry-list" aria-label="문의 목록">
      <div class="inquiry-list-meta">
        <div class="muted">총 <?= e((string)count($rows)) ?>건</div>
      </div>

      <?php if (!$rows): ?>
        <div class="inquiry-empty">
          <div style="font-weight:900;margin-bottom:6px">문의 내역이 없습니다.</div>
          <div class="muted">오른쪽 상단의 “문의 보내기”로 새 문의를 시작할 수 있습니다.</div>
        </div>
      <?php else: ?>
        <div class="inquiry-rows" id="inquiryThreadList">
          <?php foreach ($rows as $r): ?>
            <?php
              $rid = (int)($r['id'] ?? 0);
              $isSent = ((int)($r['sender_id'] ?? 0) === $meId);
              $otherName = $isSent ? (string)($r['receiver_name'] ?? '') : (string)($r['sender_name'] ?? '');
              $otherEmail = $isSent ? (string)($r['receiver_email'] ?? '') : (string)($r['sender_email'] ?? '');
              $otherLabel = trim($otherName . ($otherEmail ? (' (' . $otherEmail . ')') : ''));
              $lastAt = (string)($r['last_activity_at'] ?? $r['created_at'] ?? '');
              $active = ($selectedId > 0 && $rid === $selectedId);
            ?>
            <a class="inquiry-row <?= $active ? 'active' : '' ?>" data-inquiry-id="<?= e((string)$rid) ?>" href="<?= e(url('/inquiry_list.php?id=' . $rid)) ?>">
              <div class="inquiry-row-top">
                <div class="inquiry-title"><?= e((string)($r['title'] ?? '')) ?></div>
                <div class="inquiry-last"><?= e(time_ago($lastAt)) ?></div>
              </div>
              <div class="inquiry-row-sub muted"><?= e($otherLabel) ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <aside class="inquiry-chat" aria-label="대화 내용">
      <?php if (!$selected): ?>
        <div class="inquiry-chat-empty">
          <div style="font-weight:900;font-size:16px;margin-bottom:6px">문의를 선택하세요</div>
          <div class="muted">왼쪽 목록에서 항목을 클릭하면 대화가 표시됩니다.</div>
        </div>
      <?php else: ?>
        <?php
          $isSent = ((int)($selected['sender_id'] ?? 0) === $meId);
          $otherName = $isSent ? (string)($selected['receiver_name'] ?? '') : (string)($selected['sender_name'] ?? '');
          $otherEmail = $isSent ? (string)($selected['receiver_email'] ?? '') : (string)($selected['sender_email'] ?? '');
          $otherLabel = trim($otherName . ($otherEmail ? (' (' . $otherEmail . ')') : ''));
        ?>
        <div class="inquiry-chat-head" id="inquiryChatHead">
          <div>
            <div class="inquiry-chat-peer"><?= e($otherLabel) ?></div>
          </div>

          <div class="inquiry-chat-actions">
            <a class="btn secondary" href="<?= e(url('/inquiry_edit.php?id=' . (int)($selected['id'] ?? 0))) ?>">수정</a>
            <form method="post" action="<?= e(url('/inquiry_list.php')) ?>" id="inquiryDeleteForm">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="id" value="<?= e((string)($selected['id'] ?? '')) ?>" />
              <button class="btn danger" type="submit">삭제</button>
            </form>
          </div>
        </div>

        <div class="inquiry-chat-body" id="inquiryChatBody" data-me-id="<?= e((string)$meId) ?>" data-inquiry-id="<?= e((string)($selected['id'] ?? '')) ?>">
          <?php if (!$messages): ?>
            <div class="muted">아직 등록된 메시지가 없습니다.</div>
          <?php else: ?>
            <?php foreach ($messages as $m): ?>
              <?php $mine = ((int)($m['sender_id'] ?? 0) === $meId); ?>
              <div class="chat-line <?= $mine ? 'mine' : 'theirs' ?>">
                <div class="chat-bubble">
                  <div class="chat-text"><?= nl2br(e((string)($m['body'] ?? ''))) ?></div>
                  <div class="chat-time"><?= e((string)($m['created_at'] ?? '')) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <form class="inquiry-chat-input" id="inquirySendForm" method="post" action="<?= e(url('/inquiry_list.php?id=' . (int)$selected['id'])) ?>">
          <input type="hidden" name="action" value="send_message" />
          <input type="hidden" name="id" value="<?= e((string)($selected['id'] ?? '')) ?>" />
          <textarea class="input" name="body" id="inquirySendBody" rows="2" placeholder="메시지를 입력하세요" required></textarea>
          <button class="btn" type="submit" id="inquirySendBtn">보내기</button>
        </form>

        <script>
        (function () {
          var listUrl = <?= json_encode(url('/inquiry_list.php')) ?>;

          function renderEmptyChat() {
            var chat = document.querySelector('.inquiry-chat');
            if (!chat) return;
            chat.innerHTML = ''
              + '<div class="inquiry-chat-empty">'
              + '  <div style="font-weight:900;font-size:16px;margin-bottom:6px">문의를 선택하세요</div>'
              + '  <div class="muted">왼쪽 목록에서 항목을 클릭하면 대화가 표시됩니다.</div>'
              + '</div>';
          }

          function parseJsonResponse(r) {
            var ct = (r && r.headers && r.headers.get) ? (r.headers.get('content-type') || '') : '';
            if (!r.ok) {
              throw new Error('요청에 실패했습니다. (HTTP ' + r.status + ')');
            }
            if (ct.toLowerCase().indexOf('application/json') === -1) {
              throw new Error('서버 응답이 JSON이 아닙니다. 로그인 만료 또는 서버 오류일 수 있습니다.');
            }
            return r.json();
          }

          var bodyEl = document.getElementById('inquiryChatBody');
          if (bodyEl) {
            bodyEl.scrollTop = bodyEl.scrollHeight;
          }

          var form = document.getElementById('inquirySendForm');
          var input = document.getElementById('inquirySendBody');
          var btn = document.getElementById('inquirySendBtn');
          if (!form || !input || !btn || !window.fetch) return;

          form.addEventListener('submit', function (e) {
            e.preventDefault();
            var text = (input.value || '').trim();
            if (!text) return;

            btn.disabled = true;
            var fd = new FormData(form);

            fetch(form.action, {
              method: 'POST',
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
              body: fd
            })
              .then(parseJsonResponse)
              .then(function (data) {
                if (!data || !data.ok) {
                  throw new Error((data && data.error) || '전송에 실패했습니다.');
                }

                var m = data.message || {};
                var line = document.createElement('div');
                line.className = 'chat-line mine';

                var bubble = document.createElement('div');
                bubble.className = 'chat-bubble';

                var t = document.createElement('div');
                t.className = 'chat-text';
                t.textContent = (m.body || text);

                var tm = document.createElement('div');
                tm.className = 'chat-time';
                tm.textContent = (m.created_at || '');

                bubble.appendChild(t);
                bubble.appendChild(tm);
                line.appendChild(bubble);
                if (bodyEl) bodyEl.appendChild(line);

                input.value = '';
                if (bodyEl) bodyEl.scrollTop = bodyEl.scrollHeight;
              })
              .catch(function (err) {
                // JSON 응답이 깨지는 환경(로그인 만료/에러 출력 등)에서는 일반 제출로 폴백
                try {
                  form.submit();
                  return;
                } catch (e2) {
                  alert((err && err.message) ? err.message : '전송에 실패했습니다.');
                }
              })
              .finally(function () {
                btn.disabled = false;
                input.focus();
              });
          });

          var deleteForm = document.getElementById('inquiryDeleteForm');
          if (deleteForm) {
            deleteForm.addEventListener('submit', function (e) {
              e.preventDefault();
              if (!confirm('이 문의를 삭제할까요? 삭제하면 양쪽에서 숨김 처리됩니다.')) return;

              var fd2 = new FormData(deleteForm);
              fetch(deleteForm.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
                body: fd2
              })
                .then(parseJsonResponse)
                .then(function (data) {
                  if (!data || !data.ok) {
                    throw new Error((data && data.error) || '삭제에 실패했습니다.');
                  }

                  var id = (bodyEl && bodyEl.getAttribute('data-inquiry-id')) ? bodyEl.getAttribute('data-inquiry-id') : '';
                  if (id) {
                    var row = document.querySelector('.inquiry-row[data-inquiry-id="' + String(id).replace(/"/g, '') + '"]');
                    if (row && row.parentNode) {
                      row.parentNode.removeChild(row);
                    }
                  }

                  if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', listUrl);
                  }

                  renderEmptyChat();
                })
                .catch(function (err) {
                  // 폴백: 일반 제출
                  try {
                    deleteForm.submit();
                    return;
                  } catch (e2) {
                    alert((err && err.message) ? err.message : '삭제에 실패했습니다.');
                  }
                });
            });
          }
        })();
        </script>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
