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
   * Cleanup Drupal after a composer install/update.
   *
   * These files shouldn't be publicaly accessible.
   *
   * @param \Composer\Script\Event $event
   */
  public static function cleanupDrupal(Event $event) {
    $io = $event->getIO();
    $fs = new Filesystem();
    $docroot = static::getDrupalRoot(getcwd());

    $cleanup_files = [
      'core/CHANGELOG.txt',
      'core/COPYRIGHT.txt',
      'core/INSTALL.mysql.txt',
      'core/INSTALL.pgsql.txt',
      'core/INSTALL.sqlite.txt',
      'core/INSTALL.txt',
      'core/LICENSE.txt',
      'core/MAINTAINERS.txt',
      'core/UPDATE.txt',
      'sites/development.services.yml',
    ];

    foreach ($cleanup_files as $file) {
      if ($fs->exists($docroot . '/' . $file)) {
        $fs->remove($docroot . '/' . $file);
      }
    }

    // Disconnect from the Triquanta/drupal-project repository.
    if ($fs->exists('.git')) {
      $git_origin_url = exec("git config --get remote.origin.url");
      if ($git_origin_url === 'https://github.com/Triquanta/drupal-project.git' || $git_origin_url === 'git@github.com:Triquanta/drupal-project.git') {
        $remove_git = $io->askConfirmation('Disconnect from the github.com/Triquanta/drupal-project repository? [Y/n]');
        if ($remove_git) {
          $fs->remove('.git');
        }
      }
    }
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

    // Available profiles.
    $profiles = array(
      'minimal',
      'standard',
      'testing',
    );

    // Human readable profile selection options.
    $profiles_options = array(
      'minimal',
      '<question>standard (Default, press enter to continue)</question>',
      'testing',
      'manually enter (a profile as defined and downloaded via the composer.json)',
    );

    // First prepare our file structure.
    static::prepareDrupal($event);

    $account_name = $io->askAndValidate('Choose and enter an administrator user name (Default: gebruikereen): ', 'DrupalProject\composer\ScriptHandler::validateGenericName', NULL, 'gebruikereen');
    $account_pass = $io->ask('Choose and enter the administrators password (Default: 123456): ', '123456');
    $account_mail = $io->askAndValidate('Enter the administrator users mail (Default: beheer@triquanta.nl): ', 'DrupalProject\composer\ScriptHandler::validateMail', NULL, 'beheer@triquanta.nl');
    $site_hrn = addslashes($io->ask('Choose and enter a human readable site name: '));
    $site_mail = $io->askAndValidate('Enter the sitewide mail (Default: beheer@triquanta.nl): ', 'DrupalProject\composer\ScriptHandler::validateMail', NULL, 'beheer@triquanta.nl');
    $profile_key = $io->select('Select the install profile: ', $profiles_options, 1);

    // If the 'manual enter' option is chosen present a new input.
    if ($profile_key == 3) {
      $selected_profile = $io->askAndValidate('Choose and enter a profile name: ', 'DrupalProject\composer\ScriptHandler::validateGenericName', NULL, '');
    }
    else {
      $selected_profile = $profiles[$profile_key];
    }

    $io->write('Your Drupal site is being installed, please wait ...');

    // Execute Drush site install.
    // Prepend $site_name with a $ to allow for single quotes in the name.
    exec("vendor/drush/drush/drush --account-mail=$account_mail --account-name='$account_name' --account-pass='$account_pass' --site-mail=$site_mail --site-name='$site_hrn' --root='$docroot' --yes site-install $selected_profile install_configure_form.update_status_module='array\(FALSE,FALSE\)'", $output);

    $io->write('Your new Drupal site will now open in your browser using a one time login link.');

    exec("vendor/drush/drush/drush uli --root='$docroot'");
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
    $permissions_changed = FALSE;

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
      $sites_directories_options[0] = '<question>' . $sites_directories[0] . ' (Press enter to continue) </question>';

      $site_name_key = $io->select('Select the (multi) site to install or update: ',  $sites_directories_options, 0);

      // If new is selected, ask for a new site name.
      $install_new = array_search($add_new_site_option, $sites_directories);
      if ($site_name_key == $install_new) {
        $site_name = $io->askAndValidate('Choose a system site name (short): ', 'DrupalProject\composer\ScriptHandler::validateGenericName');
      }
      else {
        $site_name = $sites_directories[$site_name_key];
      }
    }

    // Add site name result to replaces map.
    $replaces += ['{{ site_name }}' => $site_name];

    // Add site name which can be used for uri's (replace underscores with dashes).
    $site_name_uri = str_replace('_', '-', $site_name);
    $replaces += ['{{ site_name_uri }}' => $site_name_uri];

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
      'config',
      'private_files/' . $site_name,
      $docroot . '/'. 'modules',
      $docroot . '/'. 'profiles',
      $docroot . '/'. 'themes',
      $docroot . '/'. 'libraries',
    ];

    foreach ($dirs as $dir) {
      if (!$fs->exists($dir)) {
        $fs->mkdir($dir);
        $permissions_changed = TRUE;
        $fs->touch($dir . '/.gitkeep');
      }
    }

    // Prepare the sites.php file.
    $result_path = $docroot . '/sites/sites.php';
    $example_path = $docroot . '/sites/example_template.sites.php';
    $result_exists = $fs->exists($result_path);
    if (!$result_exists && $fs->exists($example_path)) {
      $fs->copy($example_path, $result_path);
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $permissions_changed = TRUE;
      $io->write("Created a $result_path file with mode 0640.");
      $io->write("<warning>Review and update sites.php later, to make sure all domain names will work.</warning>");
    }
    elseif ($result_exists) {
      $io->write("Found existing sites.php file: <info>$result_path</info>.");
    }

    // Prepare the settings file.
    $result_path = $docroot . '/sites/' . $site_name . '/settings.php';
    $example_path = $docroot . '/sites/default/example_template.settings.php';
    $result_exists = $fs->exists($result_path);
    if (!$result_exists && $fs->exists($example_path)) {
      $fs->copy($example_path, $result_path);
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $permissions_changed = TRUE;
      $io->write("Created a $result_path file with mode 0640.");
      $io->write("<warning>Review and update the trusted_host_patterns in settings.php later, to make sure your domain name will work.</warning>");
    }
    elseif ($result_exists) {
      $io->write("Found existing settings file: <info>$result_path</info>.");
    }
    else {
      $io->write("<error>Couldn't prepare settings. Did you remove './docroot/sites/default/example_template.settings.php'?</error>.");
    }

    // Prepare the database settings file.
    // This step can be skipped by giving the argument --skip_db.
    // This is useful for continuous integration and build servers.
    $result_path = $project_root . '/settings/settings.' . $site_name . '.database.php';
    $example_path = $project_root . '/settings/example_template.settings.database.php';
    $result_exists = $fs->exists($result_path);
    if (!$result_exists && $fs->exists($example_path) && empty($args['skip_db'])) {
      // Check if we have an argument with the database info set.
      // Database info format: 'mysql://[db_user]:[db_pass]@[host]]:[port]/[db_name]'
      // Note: prefix is not supported, although you can use underscores in
      // your database name.
      if (!empty($args['db-url'])) {
        $url = parse_url($args['db-url']);

        if ($url) {
          $url = (object)array_map('urldecode', array_filter($url));

          $replaces += [
            '{{ db_driver }}' => 'mysql',
            '{{ db_host }}' => '127.0.0.1',
            '{{ db_port }}' => '3306',
            '{{ db_prefix }}' => '',
          ];

          $required_parts = [
            'path',
            'user',
            'pass',
          ];

          foreach ($required_parts as $part) {
            if (empty($url->{$part})) {
              $io->write("<error>Invalid database url: '$part' not found. </error>");
              exit();
            }
          }

          $replaces += ['{{ db_name }}' => ltrim($url->path, '/')];
          $replaces += ['{{ db_user }}' => $url->user];
          $replaces += ['{{ db_password }}' => str_replace(array("\r", "\n"), '', $url->pass)];

          if (!empty($url->driver)) {
            $replaces += ['{{ db_driver }}' => $url->scheme];
          }
          if (!empty($url->host)) {
            $replaces += ['{{ db_host }}' => $url->host];
          }
          if (!empty($url->port)) {
            $replaces += ['{{ db_port }}' => $url->port];
          }

          $io->write(strtr("Using <info>{{ db_driver }}://{{ db_user }}:[db_pass_hidden]@{{ db_host }}:{{ db_port }}/{{ db_name }}</info> as database connection info.", $replaces));
        }
        else {
          $io->write("<error>Invalid database url.</error>");
          exit();
        }
      }
      else {
        $replaces += ['{{ db_driver }}' => $io->askAndValidate('Enter the database driver (Default: mysql): ', 'DrupalProject\composer\ScriptHandler::validateGenericName', NULL, 'mysql')];
        $replaces += ['{{ db_host }}' => $io->ask('Enter the database host (Default: 127.0.0.1): ', '127.0.0.1')];
        $replaces += ['{{ db_port }}' => $io->ask('Enter the database port (Default: 3306): ', '3306')];
        $replaces += ['{{ db_prefix }}' => $io->ask('Enter the database prefix (Default is empty): ', '')];
        $replaces += ['{{ db_name }}' => $io->askAndValidate('Enter the database name (Default: ' . $site_name . '): ', 'DrupalProject\composer\ScriptHandler::validateGenericName', NULL, $site_name)];
        $replaces += ['{{ db_user }}' => $io->askAndValidate('Enter the database user (Default: ' . $site_name . '): ', 'DrupalProject\composer\ScriptHandler::validateGenericName', NULL, $site_name)];
        $replaces += ['{{ db_password }}' => $io->askAndHideAnswer('Enter the database password (hidden): ')];
      }

      if (isset($replaces['{{ db_password }}'])) {
        $replaces += ['{{ hash_salt }}' => Crypt::hashBase64($site_name . date('dDjzWL T', time()) . $replaces['{{ db_password }}'])];
      }

      $fs->copy($example_path, $result_path);
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $permissions_changed = TRUE;
      $io->write("Created a $result_path file with mode 0640.");
    }
    elseif (!empty($args['skip_db'])) {
      $io->write("<info>Skipping database settings file setup.</info>.");
    }
    elseif ($result_exists) {
      $io->write("Found existing database settings file: <info>$result_path</info>.");
    }
    else {
      $io->write("<error>Couldn't prepare database settings file.</error>.");
    }

    // Prepare the drush aliases file.
    $result_path = $project_root . '/drush/' . $site_name . '.aliases.drushrc.php';
    $example_path = $project_root . '/drush/aliases.drushrc.example_template.php';
    $result_exists = $fs->exists($result_path);
    if (!$result_exists && $fs->exists($example_path)) {
      $fs->copy($example_path, $result_path);
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $permissions_changed = TRUE;
      $io->write("Created a $result_path file with mode 0640.");
      $io->write("<warning>Review and update the aliases file, to make sure all aliases will work.</warning>");
    }
    elseif ($result_exists) {
      $io->write("Found existing Drush alias file: <info>$result_path</info>.");
    }
    else {
      $io->write("<error>Couldn't prepare Drush alias file.</error>.");
    }

    // Prepare the drushrc.php settings file for installation
    // This step can be skipped by giving the argument --skip_drushrc.
    // This is useful for continuous integration and build servers.
    $result_path = $project_root . '/drush/drushrc.php';
    $example_path = $project_root . '/drush/drushrc.example_template.php';
    $result_exists = $fs->exists($result_path);
    if (!$result_exists && $fs->exists($example_path) && empty($args['skip_drushrc'])) {
      if (!empty($args['url'])) {
        $domain_name = $args['url'];
      }
      else {
        // Ask the domain name.
        $domain_name = $io->askAndValidate('Enter the domain name for the site on this environment, press <enter> to use: http://' . $site_name_uri . '.localhost: ', 'DrupalProject\composer\ScriptHandler::validateDomainName', NULL, 'http://' . $site_name_uri . '.localhost');
      }
      // Add domain name result to replaces map.
      $replaces += ['{{ domain_name }}' => $domain_name];
      $fs->copy($example_path, $result_path);
      // Replace the domain name placeholders.
      static::fileSearchReplace($result_path, $replaces);
      $fs->chmod($result_path, 0640);
      $permissions_changed = TRUE;
      $io->write("Created a $result_path file with mode 0640.");
    }
    // A drushrc.php is already present.
    elseif ($result_exists) {
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
    elseif (!empty($args['skip_drushrc'])) {
      $io->write("<info>Skipping drushrc.php file setup.</info>.");
    }
    else {
      $io->write("<error>Couldn't prepare database settings file.</error>.");
    }

    // Remind the user to register dev-dependency modules in drushrc.
    static::excludeDevDependencies($event);

    // Prepare the environment specific settings and services files.
    if ($environment_name != 'prod') {
      $result_path = $docroot . '/sites/' . $site_name . '/settings.' . $environment_name . '.php';
      $example_path = $docroot . '/sites/default/example_template.settings.' . $environment_name . '.php';
      $result_exists = $fs->exists($result_path);
      if (!$result_exists && $fs->exists($example_path)) {
        $fs->copy($example_path, $result_path);
        static::fileSearchReplace($result_path, $replaces);
        $fs->chmod($result_path, 0640);
        $permissions_changed = TRUE;
        $io->write("Created a $result_path file with mode 0640.");
      }
      elseif ($result_exists) {
        $io->write("Found existing environment specific settings file: <info>$result_path</info>.");
      }

      $result_path = $docroot . '/sites/' . $site_name . '/services.' . $environment_name . '.yml';
      $example_path = $docroot . '/sites/default/example_template.services.' . $environment_name . '.yml';
      $result_exists = $fs->exists($result_path);
      if ($environment_name == 'dev' && !$result_exists && $fs->exists($example_path)) {
        $fs->copy($example_path, $result_path);
        static::fileSearchReplace($result_path, $replaces);
        $fs->chmod($result_path, 0640);
        $permissions_changed = TRUE;
        $io->write("Created a $result_path file with mode 0640.");
      }
      elseif ($result_exists) {
        $io->write("Found existing services file: <info>$result_path</info>.");
      }
    }
    else {
      $io->write("<info>No environment specific settings and services needed (default is production).</info>");
    }

    // Create the files directory with mode 0775.
    $result_path = $docroot . '/sites/' . $site_name . '/files';
    if (!$fs->exists($result_path)) {
      $oldmask = umask(0);
      $fs->mkdir($result_path, 0775);
      $permissions_changed = TRUE;
      umask($oldmask);
      $io->write("Created a $result_path directory with mode 0775");
    }


    // Check if the config dir is writable; minimum permissions are 0775.
    // See http://php.net/manual/en/function.fileperms.php for info about the
    // decimal->octal conversion.
    $config_dir = 'config';
    if ($fs->exists($config_dir) && decoct(fileperms($config_dir) & 0777) < 775) {
      $fs->chmod($config_dir, 0775);
      $permissions_changed = TRUE;
      $io->write("Made the config directory writable with mode 0775");
    }

    if ($permissions_changed) {
      $io->write("<warning>File permissions should be correct now. Please make sure that all files / directories belong to the same group as your webserver user.</warning>");
    }

    $io->write("<info>Preparation logic done.</info>");
  }

  public static function excludeDevDependencies(Event $event) {
    $io = $event->getIO();
    $drush_skip_modules = [];
    // Loop over the dev requirements for this project.
    foreach ($event->getComposer()->getPackage()->getDevRequires() as $key => $info) {
      // Retrieve the package metadata for the package.
      $package = $event->getComposer()->getRepositoryManager()->findPackage($info->getTarget(), $info->getConstraint());
      // Check if this is a drupal-module.
      if ($package->getType() === 'drupal-module') {
        $drush_skip_modules[] = $key;
      }
    }
    if (!empty($drush_skip_modules)) {
      // Notify the user that they need to add all modules contained in the
      // package to the skip-modules option in drushrc.php.
      // @todo: It would be nice to automate this, but there are 2 difficulties:
      //   - package name is not always equal to module name, and a package can contain multiple modules (use drush to retrieve module names?);
      //   - if we automatically generate $options['skip-modules'], we need to provide a way to add custom additions and not override them on every composer install.
      $io->write([
        "<warning>The following dev-dependencies contain Drupal modules. Make sure to add them to the \$options['skip-modules'] variable in drushrc.php.</warning>",
        "<warning>" . implode(', ', $drush_skip_modules) . "</warning>",
      ]);
    }
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
