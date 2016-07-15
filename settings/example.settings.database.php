<?php

/**
 * @file
 * Contains security sensitive information such a database credentials
 * and Drupal hash salt. This file will be included by the site's settings.php.
 */

$databases['default']['default'] = array (
  'database' => '{{ db_name }}',
  'username' => '{{ db_user }}',
  'password' => '{{ db_password }}',
  'prefix' => '',
  'host' => '127.0.0.1',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);

/**
 * Drupal hash salt.
 *
 * You can generate a hash using:
 * drush eval "print Drupal\Component\Utility\Crypt::hashBase64('a randomly picked very long string which will surely produce a nice random hash output' . microtime()) . ' ';"
 */
$settings['hash_salt'] = '{{ hash_salt }}';
