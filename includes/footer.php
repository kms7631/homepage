<?php
$me = current_user();
$isAuthPage = (defined('LAYOUT_AUTH_PAGE') && LAYOUT_AUTH_PAGE);
?>
  <?php if (!$isAuthPage): ?>
    </div>
  <?php endif; ?>
  <?php if ($me): ?>
      </main>
    </div>
  <?php endif; ?>
  <?php if (isset($extraBodyEndHtml) && is_string($extraBodyEndHtml) && $extraBodyEndHtml !== ''): ?>
    <?= $extraBodyEndHtml ?>
  <?php endif; ?>

  <script>
    (function () {
      function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
      }

      function applyTheme(next) {
        if (next === 'light') {
          document.documentElement.setAttribute('data-theme', 'light');
        } else {
          document.documentElement.removeAttribute('data-theme');
        }
        try {
          localStorage.setItem('theme', next);
        } catch (e) {
          // ignore
        }
      }

      function updateButtons() {
        var isLight = currentTheme() === 'light';
        var label = isLight ? '다크 모드' : '라이트 모드';
        document.querySelectorAll('.js-theme-toggle').forEach(function (btn) {
          btn.textContent = label;
        });
      }

      document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.js-theme-toggle') : null;
        if (!btn) return;
        e.preventDefault();
        var next = currentTheme() === 'light' ? 'dark' : 'light';
        applyTheme(next);
        updateButtons();
      });

      updateButtons();
    })();
  </script>
</body>
</html>
