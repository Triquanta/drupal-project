<?php

/**
 * @file
 * Dummy settings file to prevent the drupal installation screen when drupal is
 * bootstrapped from an unknown URL.
 *
 * @see default.settings.php
 */

if (php_sapi_name() == 'cli') {
  $error_prefix = "Error from " . __FILE__;
}
else {
  $error_prefix = "Error";
}

exit(sprintf($error_prefix . ': Host <em>%s</em> is not configured.' . PHP_EOL, $_SERVER['HTTP_HOST']));


// If you see this message, check if the host name is configured in
// sites/sites.php or a directory in sites/ exists with this name.
