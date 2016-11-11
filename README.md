# Composer template for Drupal projects

## What does the template do?

When installing the given `composer.json` some tasks are taken care of:

* Drupal will be installed in the `docroot`-directory.
* A site folder and files structure will be generated following Drupal's multi-site setup.
* Access to Drupal's `default` site is blocked.
* Autoloader is implemented to use the generated composer autoloader in `vendor/autoload.php`,
  instead of the one provided by Drupal (`docroot/vendor/autoload.php`).
* Modules (packages of type `drupal-module`) will be placed in `docroot/modules/contrib/`
* Theme (packages of type `drupal-theme`) will be placed in `docroot/themes/contrib/`
* Javascript libraries (packages of type `drupal-library`) will be placed in `docroot/libraries/`
* Profiles (packages of type `drupal-profile`) will be placed in `docroot/profiles/contrib/`
* Creates a default writable version of `settings.php` suitable for a production environment.
* Creates `docroot/sites/{{ site_name }}/files`-directory.
* Creates environment specific `settings` and `services` files in the `docroot/sites/{{ site_name }}`-directory.
* Creates site specific database settings outside the `docroot`-directory in the `settings`-directory.
* Creates a site specific `sites.php` file in the `docrtoos/sites`-directory.
* Creates `config`-directory.
* Latest version of drush is installed locally for use at `vendor/bin/drush`.
* Creates a `drushrc.php` file with a default Drush `-l` argument to simplify Drush usage for a single site using a Drupal multi-site setup.
* Creates a site specific `aliases` file in the `drush`-directory.
* Removes `.txt` files in the `docroot/core`-directory.
* Latest version of DrupalConsole is installed locally for use at `vendor/bin/drupal`.

## Usage, start here!

First you need to [install composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).

> Note: The instructions below refer to the [global composer installation](https://getcomposer.org/doc/00-intro.md#globally).
You might need to replace `composer` with `php composer.phar` (or similar) 
for your setup.

After that you can install all vendor packages (this includes Drupal Core and Contrib modules). 

```
git clone https://github.com/Triquanta/drupal-project.git <project_name>
cd <project_name>
composer install
```

Note, this will by default also install all development dependencies. To skip development dependencies append `--no-dev` to the `composer install` command.

After first install, composer will clean up unnecessary Drupal file (eg. CHANTELOG.txt).

Answer `Y` when asked to delete the .git directory, which will effectively disconnect your project from its Triquanta/drupal-project origin. Next, initialize a new git repository for this specific project:
```
git init
git add .
git commit -m "Initial commit based on github.com/Triquanta/drupal-project"
```
Now you can do whatever is appropriate for your git work flow, like initialize git flow (`git flow init`), link your new git repo to a new remote origin (`git remote add ...`), and pushing to origin.

## Install a website

First make sure you already have a working local empty MySql database prepared.

Then a fresh standard Drupal site can then be installed by executing:

```
composer drupal-install
```

First the project file structure and configuration files will be prepared according to the environment type you choose (dev, test, acc, prod).
The wizard will ask your input, but will skip parts of the setup that are detected to be completed earlier (generated files are already present).

**Note**: Database credentials will be asked during setup, if you have non standard database settings manually prepare a `settings.SITE_NAME.database.php` file in the `settings` folder.  
**Note 2**: Also read `Prepare a site specific codebase` below, for more info.

## Prepare a site specific codebase

This step is also performed during a `composer drupal-install`, so can be skipped if you have used, or will use, that command.

To only prepare the Drupal project file structure and configuration files (and not install a fresh site), use the following command:   

```
composer drupal-prepare
```

This will do the part of the magic mentioned in `What does the template do`, which is not covered by a plain `composer install`.

**Note**: if you want to install a clean Drupal site as well, use the `composer drupal-install` command instead.  
**Note 2**: Drupal's multi-site setup is used, even for a single site, the script doesn't work for additional sites on the same code base, you will have to configure them manually after preparing the first automatically.

@todo Explanation per environment type.

### Development environment

If you choose `dev` as environment during `composer drupal-install` or `composer drupal-prepare`, the following tasks will be performed:

1. Enable the services in `services.dev.yml`.
1. Show all error messages with backtrace information.
1. Disable CSS and JS aggregation.
1. Disable the render cache.
1. Allow test modules and themes to be installed.
1. Enable access to `rebuild.php`.
1. @todo Configure Behat.
1. @todo Configure PHP CodeSniffer.
1. @todo Enable development modules.
1. @todo Create a demo user for each user role.

## Adding and installing new modules

All modules available on drupal.org can be easily added via the following procedure. For modules and libraries not found on drupal.org see the FAQ below.

1. From the repository root use the folling command:  
   `composer require "drupal/module_name:^x.y"`  
   Replace module_name with the modules system name. And replace x.y with the semantic version number you want (major.minor).
2. Then go to the docroot and enable the module.  
   `cd docroot; drush en module_name`
3. Make sure you commit the changes to the composer.json and composer.lock files.

## Updating Drupal Core

This project will attempt to keep all of your Drupal Core files up-to-date; the 
project [drupal-composer/drupal-scaffold](https://github.com/drupal-composer/drupal-scaffold) 
is used to ensure that your scaffold files are updated every time drupal/core is 
updated. If you customize any of the "scaffolding" files (commonly .htaccess), 
you may need to merge conflicts if any of your modfied files are updated in a 
new release of Drupal core.

Follow the steps below to update your core files.

1. Run `composer update drupal/core`.
1. Run `git diff` to determine if any of the scaffolding files have changed. 
   Review the files for any changes and restore any customizations to 
  `.htaccess` or `robots.txt`.
1. Commit everything all together in a single commit, so `docroot` will remain in
   sync with the `core` when checking out branches or running `git bisect`.
1. In the event that there are non-trivial conflicts in step 2, you may wish 
   to perform these steps on a branch, and use `git merge` to combine the 
   updated core files with your customized files. This facilitates the use 
   of a [three-way merge tool such as kdiff3](http://www.gitshah.com/2010/12/how-to-setup-kdiff-as-diff-tool-for-git.html). This setup is not necessary if your changes are simple; 
   keeping all of your modifications at the beginning or end of the file is a 
   good strategy to keep merges easy.

## Importing configuration in a freshly installed site

If you want to import config, but you did a fresh install you'll have to execute the following steps first.

1. Copy the the `uuid` value from `config/system.site.yml`.
1. Execute the following commands, replace `<uuid>` for the copied version.
    ```
    cd docroot
    drush config-set system.site uuid <uuid>
    ```
1. Remove the shortcut entities which where created during the clean install.
    ```
    drush php-eval '\Drupal::entityManager()->getStorage("shortcut_set")->load("default")->delete();'
    ```
1. Then finally you can do:
    ```
    drush config-import
    ```

## Running Behat tests

The Behat test suite is located in the `tests/` folder. The easiest way to run
them is by going into this folder and executing the following command:

```
cd tests/
./behat
```

If you want to execute a single test, just provide the path to the test as an
argument. The tests are located in `tests/features/`:

```
cd tests/
./behat features/authentication.feature
```

If you want to run the tests from a different folder, then provide the path to
`tests/behat.yml` with the `-c` option:

```
# Run the tests from the root folder of the project.
./vendor/bin/behat -c tests/behat.yml
```


## Checking for coding standards violations

@todo This section is not functional!

### Set up PHP CodeSniffer

PHP CodeSniffer is included to do coding standards checks of PHP and JS files.
In the default configuration it will scan all files in the following folders:
- `docroot/modules` (excluding `docroot/modules/contrib`)
- `docroot/profiles`
- `docroot/themes`

First you'll need to setup a `dev` environment using `composer install/update` or by running:
 
 ```
 composer run-script post-install-cmd
 ```

This will generate a `phpcs.xml` file containing settings specific to your local
environment. Make sure to never commit this file.

### Run coding standards checks

#### Run checks manually

The coding standards checks can then be run as follows:

```
# Scan all files for coding standards violations.
./vendor/bin/phpcs

# Scan only a single folder.
./vendor/bin/phpcs docroot/modules/custom/mymodule
```

#### Run checks automatically when pushing

@todo


### Customize configuration

@todo

For more information on configuring the ruleset see [Annotated ruleset](http://pear.php.net/manual/en/package.php.php-codesniffer.annotated-ruleset.php).


## FAQ

### Should I commit the contrib modules I download

Composer recommends **no**. They provide [argumentation against but also 
workrounds if a project decides to do it anyway](https://getcomposer.org/doc/faqs/should-i-commit-the-dependencies-in-my-vendor-directory.md).

### How can I apply patches to downloaded modules?

If you need to apply patches (depending on the project being modified, a pull 
request is often a better solution), you can do so with the 
[composer-patches](https://github.com/cweagans/composer-patches) plugin.

To add a patch to drupal module foobar insert the patches section in the extra 
section of composer.json:

```json
"extra": {
    "patches": {
        "drupal/foobar": {
            "You own patch description": "URL to the patch file"
        }
    }
}
```

### How can I add Javascript libraries?

Although composer is not meant for handling non-php packages, we can use it to
manage external Javascript libraries. But note that it is a bit more elaborate
to setup.

This template can handle packages of the type `drupal-library` and will place
the packages in `docroot/libraries`, because for most contrib modules this is
one of the folders that will be searched. A sub-folder `contrib` is often not
supported, so we also don't use it.

To add a library you need to insert a new `package` definition under `reposities`
in your composer.json file.

```json
"repositories": [
  {
    "type": "package",
    "package": {
      "name": "namespace/library_name",
      "version": "x.x.x",
      "type": "drupal-library",
      "dist": {
        "url": "URL to a zip file",
        "type": "zip"
      }
    }
  }
]
```
Change: name, version, url and zip. It is advised to always download a tagged release.
The name and version are arbitrary, but will be used in the `require` section.
If you use a tagged release of a library, just also use that tag as version.

If a zip file is not available and you need a library from source you can replace `dist`
with `source`:

```json
"source": {
  "url": "URL to a Git repository",
  "type": "git",
  "reference": "v4.3.0"
}
```

The `reference` should be a branch, hash or tag.

The above will tell composer where it can find the given package, now you can
require the defined package by executing the command:

```
composer require "namespace/library_name:^x.y"
```

### How can I add a module that is not on Drupal.org?

We can use the same strategy as for Javascript libraries, but for one changes:

1. Rename type `drupal-library` to `drupal-module`

## Notes

This template is based on: https://github.com/pfrenssen/drupal-project, but without usage of Phing and with Triquanta specific thingies.
