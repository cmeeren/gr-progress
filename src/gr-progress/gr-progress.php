<?php

/* Plugin Name: GR Progress
  Description: Displays shelves and reading progress from Goodreads.
  Version: 1.5.0
  Author: Christer van der Meeren
  Author URI: http://relativisticramblings.com
  License: GPLv2 or later
 */

if (version_compare(PHP_VERSION, '5.4', '<')) {
    add_action('admin_notices',
            create_function('',
                    "echo '<div class=\"error\"><p>GR Progress requires PHP 5.4 to function properly. Please upgrade PHP or deactivate GR Progress.</p></div>';"));
    return;
} else {
    require 'gr-progress-widget.php';
}
