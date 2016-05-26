=== GR-Progress Widget ===
Contributors: cmeeren
Tags: books, goodreads, reading, reading lists
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=QW6S8UK8SETUN&lc=NO&item_name=ChristervanderMeeren¤cy_code=USD&bn=PPDonationsBFbtn_donateCC_LGgifNonHosted
Requires at least: 3.7
Tested up to: 4.5
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Widget to displays shelves and reading progress from Goodreads.

== Description ==

This is a "set it and forget it" widget which allows you to display shelves and reading progress from
your Goodreads profile. You can add multiple widgets and configure them individually to show multiple shelves.

Some configuration options:

* Large/small cover images
* Message to display if shelf is empty
* Sort books by reading progress, date updated, shelf position, author, title, etc.
* Number of books to display
* Show first line of your Goodreads review (intended for quick notes such as "reading this together with Alice"
  or "recommended by Bob" or whatever else strikes you fancy)
* Show reading progress bar, progress text (no bar) or don't show progress at all
* Display time since last update (e.g. "2 days ago")
* Custom strings for time since last update (e.g. for quick translating, or to change "2 days ago" to "2 floobargles since last update")

Almost all HTML elements have dedicated CSS classes to allow you to easily override the style.
The widget looks OK on the most popular Wordpress themes.

Requires PHP 5.4 or later (PHP 7 supported).

== Installation ==

1. Make your Goodreads profile public, otherwise no books will be visible.
2. Install and activate the plugin as you normally do.
3. Go to Appearance -> Widgets and find "GR progress". Drag it to your preferred
   sidebar or other widget area.
4. Go through all the widget settings and configure according to your preferences.
   User ID, API key, and Goodreads shelf name are mandatory. Get a Goodreads API key
   [here](https://www.goodreads.com/api/keys) (it doesn't matter what you write).

== Frequently Asked Questions ==

= Why do I have to get my own Goodreads API key? = 
Because Goodreads doesn't allow calling any given API endpoint more than
once per second. On the off-chance this plugin gets wildly popular, I don't
want Goodreads shutting down my own API key due to excessive usage.

== Screenshots ==
1. The plugin in action
2. The plugin with a bit of custom styling to make two widgets look like one
3. The widget settings

== Changelog ==

= 1.0.0 =
* Initial release.
