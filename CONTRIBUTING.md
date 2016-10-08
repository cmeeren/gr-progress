Contributing
============

How to set up development environment
-------------------------------------

1. Install and configure your favorite flavor of Apache/PHP/MySQL
2. Install and configure Wordpress
3. Add `define( 'WP_DEBUG', true );` to your `wp-config.php`
4. [Fork](https://github.com/cmeeren/gr-progress/fork) and clone the
   [GR Progress GitHub repository](https://github.com/cmeeren/gr-progress)
   (required for making pull requests on GitHub)
5. Create a new branch for making your changes
6. Make a symlink to `src/gr-progress` into your `wp-content/plugins` folder
7. Make your changes
8. Make sure the tests run after you're done (see the section below), and remember
   to add new tests for all the functionality you add. If you can't run the tests locally,
   just make a pull request and they'll be run automatically in the cloud.
9. Push your changes to your fork on GitHub
10. Make a pull request

How to set up testing environment
---------------------------------

Linux required (you may get it working on Windows, but you're on your own).

1. Install [PHPUnit](https://phpunit.de)
2. Go to the source folder (containing `phpunit.xml`)
3. Run `bash bin/install-wp-tests.sh wordpress_test root '' localhost latest`, replacing `root`, `''` and `localhost`
   with the database user, password and hostname (you can find the values in your `wp-config.php`)
   * If you get a `database exists` error at the end, that's probably fine.
4. Run `phpunit`

Deployment checklist (for myself)
---------------------------------

1. Make changes
2. If possible, run unit tests locally and test manually in a local Wordpress environment.
3. Update changelog in `src/gr-progress/README.txt`
4. Update other info in `src/gr-progress/README.txt`
5. Run `bumpversion` in repo root (or manually update version numbers in `.bumpversion.cfg` and the files it references)
6. Push to GitHub and ensure tests pass on Travis
7. Check-out plugin SVN repo: `mkdir ~/gr-progress && cd ~/gr-progress && svn co https://plugins.svn.wordpress.org/gr-progress .`
8. Copy contents of git repo's `src/gr-progress` into SVN repo's `trunk` folder, overwrite
9. Tag new release: `svn cp trunk tags/2.0.0` (replace version number)
10. Commit the SVN repo: `svn ci -m "commit message"` (replace commit message)