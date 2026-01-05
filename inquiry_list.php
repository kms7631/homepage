<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$db = db();
$me = current_user();
$meId = (int)($me['id'] ?? 0);

$supportsStatus = Inquiry::supportsStatus($db);

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

$statusTab = strtoupper(trim((string)($_GET['status'] ?? 'NEW')));
if (!$supportsStatus) {
  $statusTab = 'ALL';
} else {
  if (!in_array($statusTab, ['NEW', 'IN_PROGRESS', 'RESOLVED'], true)) {
    $statusTab = 'NEW';
  }
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
      redirect($supportsStatus ? ('/inquiry_list.php?status=' . urlencode($statusTab)) : '/inquiry_list.php');
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

      if ($supportsStatus) {
        redirect('/inquiry_list.php?id=' . $id . '&status=' . urlencode($statusTab));
      }
      redirect('/inquiry_list.php?id=' . $id);
    }

    if ($action === 'resolve') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        throw new RuntimeException('잘못된 요청입니다.');
      }

      $actorRole = is_admin() ? 'ADMIN' : 'USER';
      $reason = null;
      $note = null;
      if ($actorRole === 'ADMIN') {
        $reason = (string)($_POST['reason'] ?? '');
        $note = (string)($_POST['note'] ?? '');
      }

      Inquiry::resolve($db, $id, $meId, $actorRole, $reason, $note);
      if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
      }
      flash_set('success', '문의가 해결 처리되었습니다.');
      redirect($supportsStatus ? ('/inquiry_list.php?id=' . $id . '&status=' . urlencode($statusTab)) : ('/inquiry_list.php?id=' . $id));
    }

    if ($action === 'reopen') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        throw new RuntimeException('잘못된 요청입니다.');
      }

      Inquiry::reopen($db, $id, $meId);
      if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
      }
      flash_set('success', '문의가 다시 열렸습니다.');
      redirect($supportsStatus ? ('/inquiry_list.php?id=' . $id . '&status=' . urlencode($statusTab)) : ('/inquiry_list.php?id=' . $id));
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
  if ($supportsStatus) {
    $qs = 'status=' . urlencode($statusTab);
    if ($selectedId > 0) {
      $qs .= '&id=' . $selectedId;
    }
    redirect('/inquiry_list.php?' . $qs);
  }
  redirect('/inquiry_list.php' . ($selectedId > 0 ? ('?id=' . $selectedId) : ''));
}

$counts = $supportsStatus ? Inquiry::countThreadsByStatusForUser($db, $meId) : ['NEW' => 0, 'IN_PROGRESS' => 0, 'RESOLVED' => 0];
$rows = $supportsStatus ? Inquiry::listThreadsForUserByStatus($db, $meId, $statusTab, 200) : Inquiry::listThreadsForUser($db, $meId, 200);

$selected = null;
$messages = [];
if ($selectedId > 0) {
  $selected = Inquiry::findForUser($db, $selectedId, $meId);
  if ($selected) {
    $messages = Inquiry::listMessages($db, $selectedId, $meId, 300);
    if ($supportsStatus) {
      Inquiry::markRead($db, $selectedId, $meId);
    }
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

  <?php if ($supportsStatus): ?>
  <div class="inquiry-tabs" role="tablist" aria-label="문의 상태 필터">
    <?php
      $baseIdPart = $selectedId > 0 ? ('&id=' . $selectedId) : '';
      $tabUrlNew = url('/inquiry_list.php?status=NEW' . $baseIdPart);
      $tabUrlProg = url('/inquiry_list.php?status=IN_PROGRESS' . $baseIdPart);
      $tabUrlRes = url('/inquiry_list.php?status=RESOLVED' . $baseIdPart);
    ?>
    <a class="btn secondary <?= $statusTab === 'NEW' ? 'active' : '' ?>" role="tab" aria-selected="<?= $statusTab === 'NEW' ? 'true' : 'false' ?>" href="<?= e($tabUrlNew) ?>">새 문의(<?= e((string)($counts['NEW'] ?? 0)) ?>)</a>
    <a class="btn secondary <?= $statusTab === 'IN_PROGRESS' ? 'active' : '' ?>" role="tab" aria-selected="<?= $statusTab === 'IN_PROGRESS' ? 'true' : 'false' ?>" href="<?= e($tabUrlProg) ?>">진행중(<?= e((string)($counts['IN_PROGRESS'] ?? 0)) ?>)</a>
    <a class="btn secondary <?= $statusTab === 'RESOLVED' ? 'active' : '' ?>" role="tab" aria-selected="<?= $statusTab === 'RESOLVED' ? 'true' : 'false' ?>" href="<?= e($tabUrlRes) ?>">문의 해결(<?= e((string)($counts['RESOLVED'] ?? 0)) ?>)</a>
  </div>
  <?php endif; ?>

  <div class="inquiry-split">
    <section class="inquiry-list" aria-label="문의 목록">
      <div class="inquiry-list-meta">
        <div class="muted">총 <?= e((string)count($rows)) ?>건</div>
      </div>

      <?php if (!$rows): ?>
        <div class="inquiry-empty">
          <?php if ($supportsStatus): ?>
            <?php if ($statusTab === 'NEW'): ?>
              <div style="font-weight:900;margin-bottom:6px">새 문의가 없습니다.</div>
              <div class="muted">진행중(<?= e((string)($counts['IN_PROGRESS'] ?? 0)) ?>) 또는 문의 해결(<?= e((string)($counts['RESOLVED'] ?? 0)) ?>) 탭을 확인하세요.</div>
            <?php elseif ($statusTab === 'IN_PROGRESS'): ?>
              <div style="font-weight:900;margin-bottom:6px">진행중 문의가 없습니다.</div>
              <div class="muted">새 문의(<?= e((string)($counts['NEW'] ?? 0)) ?>) 탭 또는 문의 해결(<?= e((string)($counts['RESOLVED'] ?? 0)) ?>) 탭을 확인하세요.</div>
            <?php else: ?>
              <div style="font-weight:900;margin-bottom:6px">해결된 문의가 없습니다.</div>
              <div class="muted">새 문의(<?= e((string)($counts['NEW'] ?? 0)) ?>) 또는 진행중(<?= e((string)($counts['IN_PROGRESS'] ?? 0)) ?>) 탭을 확인하세요.</div>
            <?php endif; ?>
          <?php else: ?>
            <div style="font-weight:900;margin-bottom:6px">문의 내역이 없습니다.</div>
            <div class="muted">오른쪽 상단의 “문의 보내기”로 새 문의를 시작할 수 있습니다.</div>
          <?php endif; ?>
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
              $lastAt = (string)($r['last_activity_at'] ?? $r['last_message_at'] ?? $r['created_at'] ?? '');
              $active = ($selectedId > 0 && $rid === $selectedId);
              $st = strtoupper(trim((string)($r['status'] ?? 'IN_PROGRESS')));
              if (!in_array($st, ['NEW', 'IN_PROGRESS', 'RESOLVED'], true)) {
                $st = 'IN_PROGRESS';
              }
              $unread = (int)($r['unread_for_admin'] ?? 0);
            ?>
            <?php
              $href = $supportsStatus
                ? url('/inquiry_list.php?id=' . $rid . '&status=' . urlencode($statusTab))
                : url('/inquiry_list.php?id=' . $rid);
            ?>
            <a class="inquiry-row <?= $active ? 'active' : '' ?>" data-inquiry-id="<?= e((string)$rid) ?>" href="<?= e($href) ?>">
              <div class="inquiry-row-top">
                <div class="inquiry-title">
                  <span class="inquiry-title-text"><?= e((string)($r['title'] ?? '')) ?></span>
                  <?php if ($supportsStatus): ?>
                    <?php if ($st === 'NEW'): ?>
                      <span class="badge accent">NEW</span>
                    <?php elseif ($st === 'IN_PROGRESS'): ?>
                      <span class="badge">진행중</span>
                    <?php else: ?>
                      <span class="badge ok">해결</span>
                    <?php endif; ?>

                    <?php if (is_admin() && $unread > 0): ?>
                      <span class="badge danger">미읽음 <?= e((string)$unread) ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
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
          $selStatus = strtoupper(trim((string)($selected['status'] ?? 'IN_PROGRESS')));
          if (!in_array($selStatus, ['NEW', 'IN_PROGRESS', 'RESOLVED'], true)) {
            $selStatus = 'IN_PROGRESS';
          }
        ?>
        <div class="inquiry-chat-head" id="inquiryChatHead">
          <div>
            <div class="inquiry-chat-peer"><?= e($otherLabel) ?></div>
            <?php if ($supportsStatus): ?>
              <div class="small" style="margin-top:6px">
                상태:
                <?php if ($selStatus === 'NEW'): ?>
                  <span class="badge accent">NEW</span>
                <?php elseif ($selStatus === 'IN_PROGRESS'): ?>
                  <span class="badge">진행중</span>
                <?php else: ?>
                  <span class="badge ok">해결</span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="inquiry-chat-actions">
            <?php if ($supportsStatus && $selStatus !== 'RESOLVED'): ?>
              <?php if (is_admin()): ?>
                <button class="btn" type="button" id="btnResolve">해결 처리</button>
              <?php else: ?>
                <button class="btn" type="button" id="btnResolve">끝내기</button>
              <?php endif; ?>
            <?php elseif ($supportsStatus && $selStatus === 'RESOLVED'): ?>
              <form method="post" action="<?= e(url('/inquiry_list.php?id=' . (int)($selected['id'] ?? 0) . '&status=' . urlencode($statusTab))) ?>" style="display:inline" onsubmit="return confirm('문의를 다시 열까요?')">
                <input type="hidden" name="action" value="reopen" />
                <input type="hidden" name="id" value="<?= e((string)($selected['id'] ?? '')) ?>" />
                <button class="btn" type="submit">다시 열기</button>
              </form>
            <?php endif; ?>

            <a class="btn secondary" href="<?= e(url('/inquiry_edit.php?id=' . (int)($selected['id'] ?? 0))) ?>">수정</a>
            <form method="post" action="<?= e($supportsStatus ? url('/inquiry_list.php?status=' . urlencode($statusTab)) : url('/inquiry_list.php')) ?>" id="inquiryDeleteForm">
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

        <?php if ($supportsStatus && $selStatus === 'RESOLVED'): ?>
          <div class="inquiry-chat-input" aria-disabled="true">
            <div class="muted" style="flex:1">해결된 문의입니다. “다시 열기” 후 메시지를 보낼 수 있습니다.</div>
          </div>
        <?php else: ?>
          <?php
            $sendAction = $supportsStatus
              ? url('/inquiry_list.php?id=' . (int)$selected['id'] . '&status=' . urlencode($statusTab))
              : url('/inquiry_list.php?id=' . (int)$selected['id']);
          ?>
          <form class="inquiry-chat-input" id="inquirySendForm" method="post" action="<?= e($sendAction) ?>">
            <input type="hidden" name="action" value="send_message" />
            <input type="hidden" name="id" value="<?= e((string)($selected['id'] ?? '')) ?>" />
            <textarea class="input" name="body" id="inquirySendBody" rows="2" placeholder="메시지를 입력하세요" required></textarea>
            <button class="btn" type="submit" id="inquirySendBtn">보내기</button>
          </form>
        <?php endif; ?>

        <?php if ($supportsStatus): ?>
          <form method="post" action="<?= e(url('/inquiry_list.php?id=' . (int)($selected['id'] ?? 0) . '&status=' . urlencode($statusTab))) ?>" id="resolveSubmitForm" style="display:none">
            <input type="hidden" name="action" value="resolve" />
            <input type="hidden" name="id" value="<?= e((string)($selected['id'] ?? '')) ?>" />
            <input type="hidden" name="reason" id="resolveReason" value="" />
            <input type="hidden" name="note" id="resolveNote" value="" />
          </form>

          <?php if (is_admin()): ?>
            <dialog class="inquiry-modal" id="resolveDialog">
              <form method="dialog" class="inquiry-modal-card">
                <div class="inquiry-modal-title">문의 해결 처리</div>
                <div class="field">
                  <div class="label">사유(필수)</div>
                  <select id="resolveReasonSelect" required>
                    <option value="">선택...</option>
                    <option value="해결 완료">해결 완료</option>
                    <option value="사용자 응답 없음">사용자 응답 없음</option>
                    <option value="중복 문의">중복 문의</option>
                    <option value="안내 완료">안내 완료</option>
                  </select>
                </div>
                <div style="margin-top:10px"></div>
                <div class="field">
                  <div class="label">상세 메모(선택)</div>
                  <textarea id="resolveNoteInput" rows="3" placeholder="(선택) 메모를 입력하세요"></textarea>
                </div>
                <div class="inquiry-modal-actions">
                  <button class="btn secondary" value="cancel" type="button" id="resolveCancelBtn">취소</button>
                  <button class="btn" value="default" type="button" id="resolveConfirmBtn">해결 처리</button>
                </div>
              </form>
            </dialog>
          <?php else: ?>
            <dialog class="inquiry-modal" id="resolveDialog">
              <form method="dialog" class="inquiry-modal-card">
                <div class="inquiry-modal-title">문의 끝내기</div>
                <div class="muted" style="line-height:1.55">문의를 해결 처리하시겠습니까?</div>
                <div class="inquiry-modal-actions">
                  <button class="btn secondary" value="cancel" type="button" id="resolveCancelBtn">취소</button>
                  <button class="btn" value="default" type="button" id="resolveConfirmBtn">확인</button>
                </div>
              </form>
            </dialog>
          <?php endif; ?>
        <?php endif; ?>

        <script>
        (function () {
          var listUrl = <?= json_encode($supportsStatus ? url('/inquiry_list.php?status=' . urlencode($statusTab)) : url('/inquiry_list.php')) ?>;

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

          if (!window.fetch) return;

          var form = document.getElementById('inquirySendForm');
          var input = document.getElementById('inquirySendBody');
          var btn = document.getElementById('inquirySendBtn');

          if (form && input && btn) form.addEventListener('submit', function (e) {
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

          // 해결 처리 모달
          var btnResolve = document.getElementById('btnResolve');
          var dlg = document.getElementById('resolveDialog');
          var cancelBtn = document.getElementById('resolveCancelBtn');
          var confirmBtn = document.getElementById('resolveConfirmBtn');
          var submitForm = document.getElementById('resolveSubmitForm');
          if (btnResolve && dlg && submitForm && confirmBtn) {
            btnResolve.addEventListener('click', function () {
              if (typeof dlg.showModal === 'function') {
                dlg.showModal();
              } else {
                // fallback: 관리자 사유 필요
                var reasonEl = document.getElementById('resolveReasonSelect');
                if (reasonEl) {
                  var r = prompt('해결 사유를 입력하세요.\n- 해결 완료\n- 사용자 응답 없음\n- 중복 문의\n- 안내 완료', '해결 완료');
                  r = (r || '').trim();
                  if (!r) return;
                  var hiddenReason = document.getElementById('resolveReason');
                  if (hiddenReason) hiddenReason.value = r;
                  var n = prompt('상세 메모(선택)', '');
                  var hiddenNote = document.getElementById('resolveNote');
                  if (hiddenNote) hiddenNote.value = (n || '');
                  submitForm.submit();
                  return;
                }

                if (confirm('문의를 해결 처리하시겠습니까?')) {
                  submitForm.submit();
                }
              }
            });

            if (cancelBtn) cancelBtn.addEventListener('click', function () {
              try { dlg.close('cancel'); } catch (e) {}
            });

            confirmBtn.addEventListener('click', function () {
              var reasonEl = document.getElementById('resolveReasonSelect');
              var noteEl = document.getElementById('resolveNoteInput');
              var hiddenReason = document.getElementById('resolveReason');
              var hiddenNote = document.getElementById('resolveNote');

              if (reasonEl) {
                var v = (reasonEl.value || '').trim();
                if (!v) {
                  alert('해결 사유를 선택하세요.');
                  return;
                }
                if (hiddenReason) hiddenReason.value = v;
                if (hiddenNote) hiddenNote.value = (noteEl && noteEl.value ? noteEl.value : '');
              }

              try { dlg.close('ok'); } catch (e) {}
              submitForm.submit();
            });
          }

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
