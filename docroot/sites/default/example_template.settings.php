<?php

/**
 * @file
 * Drupal site-specific configuration file.
 *
 * IMPORTANT NOTE:
 * This file may have been set to read-only by the Drupal installation program.
 * If you make changes to this file, be sure to protect it again after making
 * your modifications. Failure to remove write permissions to this file is a
 * security risk.
 *
 * In order to use the selection rules below the multisite aliasing file named
 * sites/sites.php must be present. Its optional settings will be loaded, and
 * the aliases in the array $sites will override the default directory rules
 * below. See sites/example.sites.php for more information about aliases.
 *
 * The configuration directory will be discovered by stripping the website's
 * hostname from left to right and pathname from right to left. The first
 * configuration file found will be used and any others will be ignored. If no
 * other configuration file is found then the default configuration file at
 * 'sites/default' will be used.
 *
 * For example, for a fictitious site installed at
 * https://www.drupal.org:8080/mysite/test/, the 'settings.php' file is searched
 * for in the following directories:
 *
 * - sites/8080.www.drupal.org.mysite.test
 * - sites/www.drupal.org.mysite.test
 * - sites/drupal.org.mysite.test
 * - sites/org.mysite.test
 *
 * - sites/8080.www.drupal.org.mysite
 * - sites/www.drupal.org.mysite
 * - sites/drupal.org.mysite
 * - sites/org.mysite
 *
 * - sites/8080.www.drupal.org
 * - sites/www.drupal.org
 * - sites/drupal.org
 * - sites/org
 *
 * - sites/default
 *
 * Note that if you are installing on a non-standard port number, prefix the
 * hostname with that number. For example,
 * https://www.drupal.org:8080/mysite/test/ could be loaded from
 * sites/8080.www.drupal.org.mysite.test/.
 *
 * @see example.sites.php
 * @see \Drupal\Core\DrupalKernel::getSitePath()
 *
 * In addition to customizing application settings through variables in
 * settings.php, you can create a services.yml file in the same directory to
 * register custom, site-specific service definitions and/or swap out default
 * implementations with custom ones.
 */

global $config;

/*
 * Database settings.
 * Loads the external settings.*.database.php from a path outside the Drupal root.
 * If not found, we terminate and display a message.
 */
if (file_exists(DRUPAL_ROOT . '/../settings/settings.{{ site_name }}.database.php')) {
  include DRUPAL_ROOT . '/../settings/settings.{{ site_name }}.database.php';
}
if (empty($databases)) {
  exit(sprintf('Error: No database configuration found for <em>%s</em>.', $_SERVER['HTTP_HOST']));
}

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Public files path.
 */
$settings['file_public_path'] = 'sites/{{ site_name }}/files';

/**
 * Private files outside the docroot.
 */
$settings['file_private_path'] = '../private_files/{{ site_name }}';

/**
 * Temporary files directory outside the docroot.
 *
 * Note: this is not visible from the UI.
 */
$config['system.file']['path']['temporary'] = '/tmp';

/**
 * Translation files outside the docroot.
 *
 * Note: this is not visible from the UI.
 */
$config['locale.settings']['translation']['path'] = '../translations';

/**
 * Install profile.
 */
$settings['install_profile'] = 'standard';


/**
 * Disable update via UI.
 */
$settings['update_free_access'] = FALSE;

/**
 * Trusted host settings
 **/
$settings['trusted_host_patterns'] = array(
  '^.+\.localhost$',
  '^.+\.local$',
  '^.+\.dev$',
  '^.+\.xip.io$',
  '^.+\.triquanta.nl$',
  '^.+\.{{ site_name_uri }}.nl$',
  '^{{ site_name_uri }}.nl$',
);

/**
 * Configuration management
 */
$config_directories[CONFIG_SYNC_DIRECTORY] = DRUPAL_ROOT . '/../config';

/**
 * Load environment specific override configuration, if available.
 *
 * These overrides are only necessary for non-production environments.
 * It is advised to make use of the example.settings.[ENVIRONMENT].php files.
 * These files are kept in git and used on the actual environments.
 * Just copy them and remove the 'example' prefix to activate them.
 * Keep this code block at the end of this file to take full effect.
 */
$environments = ['acc', 'test', 'dev'];
foreach ($environments as $environment) {
  if (file_exists(__DIR__ . '/settings.' . $environment . '.php')) {
    include __DIR__ . '/settings.' . $environment . '.php';
    break;
  }
}

if (file_exists(__DIR__ . '/settings.local.php')) {
  include __DIR__ . '/settings.local.php';
}
