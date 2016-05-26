Contributing
============

How to set up development environment
-------------------------------------

Linux required (you may get it working on Windows, but you're on your own).

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
   to add new tests for all the functionality you add
9. Push your changes to your fork on GitHub
10. Make a pull request

How to set up testing environment
---------------------------------

1. Install [PHPUnit](https://phpunit.de)
2. Go to the source folder (containing `phpunit.xml`)
3. Run `bash bin/install-wp-tests.sh wordpress_test root '' localhost latest`, replacing `root`, `''` and `localhost`
   with the database user, password and hostname (you can find the values in your `wp-config.php`)
   * If you get a `database exists` error at the end, that's probably fine.
4. Run `phpunit`
