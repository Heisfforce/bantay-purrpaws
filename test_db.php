<?php
/**
 * @deprecated Use deploy/verify.php instead. Blocked on production via .htaccess.
 */
http_response_code(403);
header('Content-Type: text/plain; charset=utf-8');
echo "test_db.php is disabled. Use deploy/verify.php?key=YOUR_DEPLOY_VERIFY_KEY after deployment.\n";
