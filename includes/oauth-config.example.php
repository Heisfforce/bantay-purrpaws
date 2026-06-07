<?php
/**
 * Optional site hints (copy to oauth-config.php on the server).
 *
 * The live oauth-config.php reads APP_URL / GOOGLE_REDIRECT_URI from the
 * environment automatically. You usually only need .env or hosting Variables.
 * Open /auth/oauth-setup.php on each environment to verify alignment.
 */
declare(strict_types=1);

return [
    'app_url' => 'https://yourdomain.example.com',
];
