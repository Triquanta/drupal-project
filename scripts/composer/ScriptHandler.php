<?php

/**
 * @file
 * Contains \DrupalProject\composer\ScriptHandler.
 */

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use Drupal\Component\Utility\Crypt;
use Symfony\Component\Filesystem\Filesystem;

class ScriptHandler {

  protected static function getDrupalRoot($project_root = NULL) {
    if (is_null($project_root)) {
      $project_root = getcwd();
    }
    return $project_root .  '/docroot';
  }

  /**
   * A method that will read a file, run a strtr to replace placeholders with
   * values from our replace array and write it back to the file.
   *
   * @param string $target the filename of the target
   * @param array $replaces the replaces to be applied to this target
   */
  protected static function fileSearchReplace($target, $replaces)
  {
    file_put_contents(
      $target,
      strtr(
        file_get_contents($target),
        $replaces
      )
    );
  }

  /**
   * Installs a fresh Drupal site.
   *
   * We asume that the database credentials can be read from the settings file.
   *
   * @param \Composer\Script\Event $event
   */
  public static function installDrupal(Event $event) {
    $io = $event->getIO();
    $docroot = static::getDrupalRoot(getcwd());

    // First prepare our file structure.
    static::prepareDrupal($event);

    $account_name = $io->askAndValidate('Enter the administrator name (Default: gebruikereen): ', 'DrupalProject\composer\ScriptHandler::validateGenericName', NULL, 'gebruikereen');
    $account_pass = $io->ask('Enter the administrator password (Default: 123456): ', '123456');
    $account_mail = $io->askAndValidate('Enter the administrator mail (Default: beheer@triquanta.nl): ', 'DrupalProject\composer\ScriptHandler::validateMail', NULL, 'beheer@triquanta.nl');
    $site_name = $io->askAndValidate('Enter the site name: ', 'DrupalProject\composer\ScriptHandler::validateGenericName');
    $site_mail = $io->askAndValidate('Enter the site mail (Default: beheer@triquanta.nl): ', 'DrupalProject\composer\ScriptHandler::validateMail', NULL, 'beheer@triquanta.nl');

    exec("vendor/drush/drush/drush --account-mail=$account_mail --account-name='$account_name' --account-pass='$account_pass' --site-mail=$site_mail --site-name='$site_name' --root='$docroot' --yes site-install standard install_configure_form.update_status_module='array\(FALSE,FALSE\)'", $output);

  }

  /**
   * Prepares a Drupal project.
   *
   * This is often only needed once.
   *
   * @todo add force overwrite arg or something
   *
   * @param \Composer\Script\Event $event
   */
  public static function prepareDrupal(Event $event) {
    $io = $event->getIO();
    $args = [];
    $args_raw = $event->getArguments();
    $fs = new Filesystem();
    $project_root = getcwd();
    $docroot = static::getDrupalRoot($project_root);

    // Process arguments. (Need to be entered in the form --arg=value or --arg).
    // @todo load args from build file also.
    foreach ($args_raw as $arg_raw) {
      $parts = explode('=', $arg_raw);
      if (count($parts) == 1 || count($parts) == 2) {
        $arg_key = trim($parts[0], '-');
        $args[$arg_key] = isset($parts[1]) ? $parts[1] : TRUE;
      }
    }

    // All multisite sites folder names.
    $sites_directories = [];

    // Map of placeholders
    $replaces = [];

    // Get available sites directories.
    foreach(glob(static::getDrupalRoot() . '/sites/*', GLOB_ONLYDIR) as $dir) {
      $basename = basename($dir);
      $sites_directories[] = $basename;
    }

    natsort($sites_directories);

    // Add a 'new' site option.
    $add_new_site_option = '- Add new site -';
    $sites_directories = array_merge($sites_directories, [$add_new_site_option]);

    // If there are multiple site directories and 'default' is available,
    // move default to the bottom.
    $default_site_key = array_search('default', $sites_directories);
    if ($default_site_key !== FALSE) {
      unset($sites_directories[$default_site_key]);
      $sites_directories = array_values($sites_directories);
      $sites_directories[] = 'default';
    }

    if (!empty($args['site_name']) && static::validateGenericName($args['site_name'])){
      $site_name = $args['site_name'];
      $io->write("Using <info>$site_name</info> as site.");
    }
    // If there is only one multi site folder, use that folder name as site_name.
    elseif (($default_site_key !== FALSE && count($sites_directories) == 3) || ($default_site_key === FALSE && count($sites_directories) == 2))  {
      $site_name = reset($sites_directories);
      $io->write("Found $site_name directory in docroot/sites, using <info>$site_name</info> as site.");
    }

    // If no site_name is set we need some manual input.
    if (empty($site_name)) {
      // Highlight the default selection.
      $sites_directories_options = $sites_directories;
      $sites_directories_options[0] = '<question>' . $sites_directories[0] . ' (Default, press enter to continue) </question>';

      $site_name_key = $io->select('Select the site to install or update: ',  $sites_directories_options, 0);

      // If new is selected, ask for a new site name.
      $install_new = array_search($add_new_site_option, $sites_directories);
      if ($site_name_key == $install_new) {
        $site_name = $io->askAndValidate('Enter the site name: ', 'DrupalProject\composer\ScriptHandler::validateGenericName');
      }
      else {
        $site_name = $sites_directories[$site_name_key];
      }
    }

    // Add site name result to replaces map.
    $replaces += ['{{ site_name }}' => $site_name];

    $environments = $environment_options = ['prod', 'acc', 'test', 'dev'];

    // Get the default environment, we can only check this based on available
    // environment specific settings files.
    foreach ($environments as $environment_search_name) {
      $result_path = $docroot . '/sites/' . $site_name . '/settings.' . $environment_search_name . '.php';
      if ($fs->exists($result_path)) {
        $environment_name = $environment_search_name;
        $io->write("Found file $result_path, using <info>$environment_name</info> as environment.");
        break;
      }
    }

    // Check if we have an argument with the environment set.
    if (!empty($args['env']) && in_array($args['env'], $environments)) {
      $environment_name = $args['env'];
      $io->write("Using <info>$environment_name</info> as environment.");
    }
    // Otherwise ask the environment if no environment specific settings file is found.
    elseif (empty($environment_name)) {
      // Mark default choice.
      $environment_options[0] = '<question>prod (Default, press enter to continue)</question>';
      $environment_key = $io->select('Select the environment: ', $environment_options, 0);
      $environment_name = $environments[$environment_key];
    }

    // Add environment result to replaces map.
    $replaces += ['{{ environment_name }}' => $environment_name];

    // Required directories (for unit testing).
    $dirs = [
      'private_files/' . $site_name,
      $docroot . '/'. 'modules',
      $docroot . '/'. 'profiles',
      $docroot . '/'. 'themes',
    ];

    foreach ($dirs as $dir) {
      if (!$fs->exists($dir)) {
        $fs->mkdir($dir);
        $fs->touch($dir . '/.gitkeep');
      }
    }

    // Cleanup.
    $cleanup_files = [
      $docroot . '/sites/development.services.yml',
      $docroot . '/sites/example.settings.local.php',
      $docroot . '/core/CHANGELOG.txt',
      $docroot . '/core/COPYRIGHT.txt',
      $docroot . '/core/INSTALL.mysql.txt',
      $docroot . '/core/INSTALL.pgsql.txt',
      $docroot . '/core/INSTALL.sqlite.txt',
      $docroot . '/core/INSTALL.txt',
      $docroot . '/core/LICENSE.txt',
      $docroot . '/core/MAINTAINERS.txt',
      $docroot . '/core/UPDATE.txt',
    ];

    foreach ($cleanup_files as $file) {
      if ($fs->exists($file)) {
        $fs->remove($file);
      }
    }

    // Prepare the sites.php file.
    $result_path = $docroot . '/sites/sites.php';
    $example_path = $docroot . '/sites/example_template.sites.php';
    if (!$fs->exists($result_path) and $fs->exists($example_path)) {
      $fs->copy($example_path, $result_path);
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $io->write("Created a $result_path file with chmod 0640.");
      $io->write("<warning>Review and update sites.php later, to make sure all domain names will work.</warning>");
    }

    // Prepare the settings file.
    $result_path = $docroot . '/sites/' . $site_name . '/settings.php';
    $example_path = $docroot . '/sites/default/example_template.settings.php';
    if (!$fs->exists($result_path) and $fs->exists($example_path)) {
      $fs->copy($example_path, $result_path);
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $io->write("Created a $result_path file with chmod 0640.");
      $io->write("<warning>Review and update the trusted_host_patterns in settings.php later, to make sure your domain name will work.</warning>");
    }

    // Prepare the database settings file.
    // This step can be skipped by giving the argument --skip_db.
    // This is useful for continuous integration and build servers.
    $result_path = $project_root . '/settings/settings.' . $site_name . '.database.php';
    $example_path = $project_root . '/settings/example_template.settings.database.php';
    if (!$fs->exists($result_path) and $fs->exists($example_path) && empty($args['skip_db'])) {
      $replaces += ['{{ db_name }}' => $io->askAndValidate('Enter the database name (Default: ' . $site_name . '): ', 'DrupalProject\composer\ScriptHandler::validateGenericName', NULL, $site_name)];
      $replaces += ['{{ db_user }}' => $io->askAndValidate('Enter the database user: ', 'DrupalProject\composer\ScriptHandler::validateGenericName')];
      $replaces += ['{{ db_password }}' => $io->askAndHideAnswer('Enter the database password (hidden): ')];
      $replaces += ['{{ hash_salt }}' => Crypt::hashBase64($site_name + date('dDjzWL T', time()) + $replaces['{{ db_password }}'])];
      $fs->copy($example_path, $result_path);
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $io->write("Created a $result_path file with chmod 0640.");
    }

    // Prepare the drush aliases file.
    $result_path = $project_root . '/drush/' . $site_name . '.aliases.drushrc.php';
    $example_path = $project_root . '/drush/example_template.aliases.drushrc.php';
    if (!$fs->exists($result_path) and $fs->exists($example_path)) {
      $fs->copy($example_path, $result_path);
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $io->write("Created a $result_path file with chmod 0640.");
      $io->write("<warning>Review and update the aliases file, to make sure all aliases will work.</warning>");
    }

    // Prepare the drushrc.php settings file for installation
    // This step can be skipped by giving the argument --skip_drushrc.
    // This is useful for continuous integration and build servers.
    $result_path = $project_root . '/drush/drushrc.php';
    $example_path = $project_root . '/drush/example_template.drushrc.php';
    if (!$fs->exists($result_path) and $fs->exists($example_path) && empty($args['skip_drushrc'])) {
      // Ask the domain name.
      $domain_name = $io->askAndValidate('Enter the domain name for the site on this environment, press <enter> to use: http://' . $site_name . '.localhost: ', 'DrupalProject\composer\ScriptHandler::validateDomainName', NULL, 'http://' . $site_name . '.localhost');
      // Add domain name result to replaces map.
      $replaces += ['{{ domain_name }}' => $domain_name];
      $fs->copy($example_path, $result_path);
      // Replace the domain name placeholders.
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $io->write("Created a $result_path file with chmod 0640.");
    }
    // A drushrc.php is already present.
    elseif ($fs->exists($result_path)) {
      $drushrc_file = file_get_contents($result_path);
      // Find domain name (commented lines are not taken into account).
      preg_match_all('/\s*\$options\[[\'\"]l[\'\"]\]\s*=\s*[\'\"](.*)[\'\"];/', $drushrc_file, $matches);
      $domain_name = isset($matches[1]) ? end($matches[1]) : FALSE;
      if ($domain_name) {
        $io->write("Found file $result_path, (probably) using <info>$domain_name</info> as domain name.");
      }
      else {
        $io->write("<warning>No domain name found in the file $result_path!");
      }
    }

    // Prepare the settings and services file for installation
    if ($environment_name != 'prod') {
      $result_path = $docroot . '/sites/' . $site_name . '/settings.' . $environment_name . '.php';
      $example_path = $docroot . '/sites/default/example_template.settings.' . $environment_name . '.php';
      if (!$fs->exists($result_path) and $fs->exists($example_path)) {
        $fs->copy($example_path, $result_path);
        $fs->chmod($result_path, 0640);
        $io->write("Created a $result_path file with chmod 0640.");
      }
      $result_path = $docroot . '/sites/' . $site_name . '/services.' . $environment_name . '.yml';
      $example_path = $docroot . '/sites/default/example_template.services.' . $environment_name . '.yml';
      if ($environment_name == 'dev' && !$fs->exists($result_path) and $fs->exists($example_path)) {
        $fs->copy($example_path, $result_path);
        $fs->chmod($result_path, 0640);
        $io->write("Created a $result_path file with chmod 0640.");
      }
    }

    // Create the files directory with chmod 0755
    $result_path = $docroot . '/sites/' . $site_name . '/files';
    if (!$fs->exists($result_path)) {
      $oldmask = umask(0);
      $fs->mkdir($result_path, 0755);
      umask($oldmask);
      $io->write("Create a $result_path directory with chmod 0755");
    }

    $io->write("<info>Install/Update script done</info>");

  }

  public static function validateGenericName($input) {
    if (empty($input)) {
      throw new \InvalidArgumentException('Input can\'t be empty');
    }
    elseif (preg_match('/^[a-z0-9_]{2,32}$/', $input)) {
      return $input;
    }
    throw new \InvalidArgumentException('Invalid input. Only lowercase alphanumeric characters and underscores are allowed and the input must be between 2 and 32 characters');
  }

  public static function validateMail($input) {
    if (empty($input)) {
      throw new \InvalidArgumentException('Email can\'t be empty');
    }
    elseif (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Invalid email.');
    }
    return $input;
  }

  public static function validateDomainName($input) {

    if (empty($input)) {
      throw new \InvalidArgumentException('Domain name can\'t be empty');
    }
    // Validate url
    elseif (!filter_var($input, FILTER_VALIDATE_URL) === false) {
      return $input;
    }
    else {
      throw new \InvalidArgumentException('Invalid url');
    }
  }

  /**
   * Checks if the installed version of Composer is compatible.
   *
   * Composer 1.0.0 and higher consider a `composer install` without having a
   * lock file present as equal to `composer update`. We do not ship with a lock
   * file to avoid merge conflicts downstream, meaning that if a project is
   * installed with an older version of Composer the scaffolding of Drupal will
   * not be triggered. We check this here instead of in drupal-scaffold to be
   * able to give immediate feedback to the end user, rather than failing the
   * installation after going through the lengthy process of compiling and
   * downloading the Composer dependencies.
   *
   * @see https://github.com/composer/composer/pull/5035
   */
  public static function checkComposerVersion(Event $event) {
    $composer = $event->getComposer();
    $io = $event->getIO();

    $version = $composer::VERSION;

    // If Composer is installed through git we have no easy way to determine if
    // it is new enough, just display a warning.
    if ($version === '@package_version@') {
      $io->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
    }
    elseif (Comparator::lessThan($version, '1.0.0')) {
      $io->writeError('<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.');
      exit(1);
    }
  }

}
