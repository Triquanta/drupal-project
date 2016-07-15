<?php

/**
 * @file
 * Dummy settings file to prevent the drupal installation screen when drupal is
 * bootstrapped from an unknown URL.
 *
 * @see default.settings.php
 */
exit(sprintf('Error: Host <em>%s</em> is not configured.', $_SERVER['HTTP_HOST']));

// If you see this message, check if the host name is configured in
// sites/sites.php or a directory in sites/ exists with this name.
