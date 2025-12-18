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
</body>
</html>
