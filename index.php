<?php
/**
 * Legacy entry point.
 *
 * The live application is served from `/public/index.php`. This file used to
 * be an older front controller that used `$_GET['filename']` to `include()`
 * template files, which is a Local File Inclusion hazard. It now simply
 * redirects to the real controller so that web roots pointing at the project
 * root still work.
 */

declare(strict_types=1);

header('Location: /public/');
http_response_code(301);
exit;
