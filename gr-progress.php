<?php
/* Plugin Name: GR Progress
  Description: Displays shelves and reading progress from Goodreads.
  Version: 1.0.0
  Author: Christer van der Meeren
  Author URI: http://relativisticramblings.com
  License: MIT
 */

require_once("simple_html_dom.php");
require_once("Shelf.php");

// Creating the widget
class gr_progress_cvdm_widget extends WP_Widget {

    private $shelves;
    private $widgetData;
    private $defaults;
    private $sortByOptions;
    private $sortOrderOptions;
    private $cacheNeedsUpdate = false;
    private $CURRENTLY_READING_SHELF_KEY = 'currentlyReadingShelf';
    private $ADDITIONAL_SHELF_KEY = 'additionalShelf';
    private $DEFAULT_TIMEOUT_IN_SECONDS = 5;
    private $SECONDS_TO_WAIT_AFTER_FAILED_FETCH = 3600;

    function __construct() {
        parent::__construct(
                'gr_progress_cvdm_widget', 'GR progress', ['description' => 'Displays reading progress and shelves from Goodreads.']
        );

        $this->initializeMembers();
    }

    function initializeMembers() {
        $this->shelves = [
            $this->CURRENTLY_READING_SHELF_KEY => null,
            $this->ADDITIONAL_SHELF_KEY => null];

        $this->defaults = [
            'title' => 'Currently reading',
            'userid' => '',
            'apiKey' => '',
            'currentlyReadingShelfName' => 'currently-reading',
            'emptyMessage' => 'Not currently reading anything.',
            'displayProgressUpdateTime' => true,
            'useProgressBar' => true,
            'currentlyReadingShelfSortBy' => 'date_updated',
            'currentlyReadingShelfSortOrder' => 'd',
            'maxBooksCurrentlyReadingShelf' => 10,
            'additionalShelfName' => 'to-read',
            'additionalShelfHeading' => 'Reading soon',
            'emptyMessageAdditional' => "Nothing planned at the moment.",
            'additionalShelfSortBy' => 'position',
            'additionalShelfSortOrder' => 'a',
            'maxBooksAdditionalShelf' => 10,
            'progressCacheHours' => 24,
            'bookCacheHours' => 24,
            'regenerateCacheOnSave' => false,
            'deleteCoverURLCacheOnSave' => false,
        ];

        $this->sortByOptions = [
            'title' => 'Title',
            'author' => 'Author',
            'position' => 'Shelf position',
            'date_added' => 'Date added',
            'date_purchased' => 'Date purchased',
            'date_started' => 'Date started',
            'date_updated' => 'Date updated',
            'date_read' => 'Date read',
            'rating' => 'Rating',
            'avg_rating' => 'Average rating',
            'read_count' => 'Read count',
            'num_pages' => 'Number of pages',
            'random' => 'Random',
        ];

        $this->sortOrderOptions = [
            'a' => 'Ascending',
            'd' => 'Descending',
        ];
    }

    public function widget($args, $instance) {

        $this->widgetData = $instance;

        echo $args['before_widget'];  // defined by themes

        $title = apply_filters('widget_title', $this->widgetData['title']);
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        $this->loadWidgetData();
        $this->printWidgetContents();

        echo $args['after_widget'];  // defined by themes
    }

    private function loadWidgetData() {
        $this->loadCachedShelves();
        if ($this->sufficientTimeSinceLastRetrievalError()) {
            ini_set('default_socket_timeout', $this->DEFAULT_TIMEOUT_IN_SECONDS);
            $this->fetchNewShelvesIfNeeded();
            $this->updateProgressIfNeeded();
            $this->saveCacheIfNeeded();
        }
    }

    private function loadCachedShelves() {
        $shelves = get_option("gr_progress_cvdm_shelves", null);
        if ($shelves !== null) {
            $this->shelves = $shelves;
        }
    }
    
    private function sufficientTimeSinceLastretrievalError() {
        return time() - get_option("gr_progress_cvdm_lastRetrievalErrorTime", 0) > $this->SECONDS_TO_WAIT_AFTER_FAILED_FETCH;
    }

    private function fetchNewShelvesIfNeeded() {
        foreach ($this->shelves as $shelfKey => $shelfObject) {
            if ($this->shouldUseShelf($shelfKey) && ($shelfObject === null || $shelfObject->bookCacheOutOfDate())) {
                $this->fetchNewShelf($shelfKey);
                $this->cacheNeedsUpdate = true;
            }
        }
    }

    private function shouldUseShelf($shelfKey) {
        return !empty($this->getShelfName($shelfKey));
    }

    private function fetchNewShelf($shelfKey) {
        $shelf = new Shelf($this->getShelfName($shelfKey), $this->widgetData);
        if (!$shelf->retrievalError) {
            $this->shelves[$shelfKey] = $shelf;
        } else {
            update_option("gr_progress_cvdm_lastRetrievalErrorTime", time());
        }
    }

    private function getShelfName($shelfKey) {
        if ($shelfKey == $this->CURRENTLY_READING_SHELF_KEY) {
            return $this->widgetData['currentlyReadingShelfName'];
        } else {
            return $this->widgetData['additionalShelfName'];
        }
    }

    private function updateProgressIfNeeded() {
        $currentlyReadingShelf = $this->shelves[$this->CURRENTLY_READING_SHELF_KEY];
        if ($currentlyReadingShelf !== null && $currentlyReadingShelf->progressCacheOutOfDate()) {
            $currentlyReadingShelf->updateProgress();
            $this->cacheNeedsUpdate = true;
        }
    }

    private function saveCacheIfNeeded() {
        if ($this->cacheNeedsUpdate == true) {
            update_option("gr_progress_cvdm_shelves", $this->shelves);
            $this->cacheNeedsUpdate = false;
        }
    }

    private function printWidgetContents() {
        $this->printGoodreadsAttribution();
        $this->printCurrentlyReadingShelf();
        if ($this->shouldUseShelf($this->ADDITIONAL_SHELF_KEY)) {
            $this->printAdditionalShelfHeading();
            $this->printAdditionalShelf();
        }
    }
    
    private function printGoodreadsAttribution() {
        echo "<p class='goodreads-attribution'>Data from Goodreads</p>";
    }

    private function printCurrentlyReadingShelf() {
        $currentlyReadingShelf = $this->shelves[$this->CURRENTLY_READING_SHELF_KEY];
        if ($currentlyReadingShelf === null) {
            $secondsLeftUntilRetry = get_option("gr_progress_cvdm_lastRetrievalErrorTime", 0) + $this->SECONDS_TO_WAIT_AFTER_FAILED_FETCH - time();
            $retryMessage = "";
            if ($secondsLeftUntilRetry > 0) {
                $retryMessage = " Will retry in " . intval(ceil($secondsLeftUntilRetry/60)) . " min.";
            }
            echo "<p class='emptyShelfMessage'>Error retrieving data from Goodreads.$retryMessage</p>";
        } elseif ($currentlyReadingShelf->isEmpty()) {
            echo "<p class='emptyShelfMessage'>{$this->widgetData['emptyMessage']}</p>";
        } else {
            echo "<ul class='bookshelf currently-reading-shelf'>";
            $this->printBooksOnShelf($this->shelves[$this->CURRENTLY_READING_SHELF_KEY]);
            echo "</ul>";
        }
    }

    private function printBooksOnShelf($shelf) {
        foreach ($shelf->getBooks() as $book) {
            echo "<li>";
            echo "<img src='{$book->getCoverURL()}' />";
            echo "<div class='desc'>";
            echo "<h3>{$book->getTitle()}</h3>";
            echo "<p class='author'>{$book->getAuthor()}</p>";

            if ($book->hasProgress()) {
                if ($this->widgetData['useProgressBar']) {
                    $this->printProgressBar($book);
                } else {
                    $this->printProgressString($book);
                }
            }
            echo "</div>";
            echo "</li>";
        }
    }

    private function printProgressBar($book) {
        $percent = $book->getProgressInPercent();
        $progressStatusUpdateTime = $book->getProgressStatusUpdateTime();
        $time = $this->widgetData['displayProgressUpdateTime'] ? " (" . time_elapsed_string("@" . strval($progressStatusUpdateTime)) . ")" : "";
        ?>
        <div class="progress progress-bar-wrapper">
            <div class="progress-bar" style="width: <?php echo $percent ?>%;">
                <span><?php echo $percent ?>&thinsp;%<?php echo $time ?></span>
            </div>
            <span><?php echo $percent ?>&thinsp;%<?php echo $time ?></span>
        </div>
        <?php
    }

    private function printProgressString($book) {
        $percent = $book->getProgressInPercent();
        $progressStatusUpdateTime = $book->getProgressStatusUpdateTime();
        $time = $this->widgetData['displayProgressUpdateTime'] ? " (" . time_elapsed_string("@" . strval($progressStatusUpdateTime)) . ")" : "";
        echo "<p class='progress progress-string'>$percent&thinsp;%$time</p>";
    }

    private function printAdditionalShelfHeading() {
        $heading = $this->widgetData['additionalShelfHeading'];
        if (!empty($heading)) {
            echo "<h2>{$this->widgetData['additionalShelfHeading']}</h2>";
        }
    }

    private function printAdditionalShelf() {
        $additionalShelf = $this->shelves[$this->ADDITIONAL_SHELF_KEY];
        if ($additionalShelf === null) {
            $secondsLeftUntilRetry = get_option("gr_progress_cvdm_lastRetrievalErrorTime", 0) + $this->SECONDS_TO_WAIT_AFTER_FAILED_FETCH - time();
            $retryMessage = "";
            if ($secondsLeftUntilRetry > 0) {
                $retryMessage = " Will retry in " . intval(ceil($secondsLeftUntilRetry/60)) . " min.";
            }
            echo "<p class='emptyShelfMessage'>Error retrieving data from Goodreads.$retryMessage</p>";
        } elseif ($additionalShelf->isEmpty()) {
            $messageIfEmpty = $this->widgetData['emptyMessageAdditional'];
            if (!empty($messageIfEmpty)) {
                echo "<p class='emptyShelfMessage'>$messageIfEmpty</p>";
            }
        } else {
            echo "<ul class='bookshelf additional-shelf'>";
            $this->printBooksOnShelf($this->shelves[$this->ADDITIONAL_SHELF_KEY]);
            echo "</ul>";
        }
    }

    public function form($instance) {

        foreach ($this->defaults as $setting => $defaultValue) {
            if (!isset($instance[$setting])) {
                $instance[$setting] = $defaultValue;
            }
        }
        ?>

        <p style="text-align: center; color: #31708f; background-color: #d9edf7; border: 1px solid #bce8f1; border-radius: 4px; padding: 15px;"><strong>Important!</strong> Remember to make your Goodreads profile public, otherwise no books will be visible.</p>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">
                Title:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->get_field_id('title'); ?>"
                name="<?php echo $this->get_field_name('title'); ?>"
                value="<?php echo esc_attr($instance['title']); ?>"
                />
        </p>
        <h3 style="margin-top: 2.5rem;">Goodreads configuration</h3>
        <p>
            <label for="<?php echo $this->get_field_id('userid'); ?>">
                Goodreads user ID or profile URL:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->get_field_id('userid'); ?>"
                name="<?php echo $this->get_field_name('userid'); ?>"
                value="<?php echo esc_attr($instance['userid']); ?>"
                />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('apiKey'); ?>">
                Goodreads API key (get one <a target="_blank" href="https://www.goodreads.com/api/keys">here</a>):
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->get_field_id('apiKey'); ?>"
                name="<?php echo $this->get_field_name('apiKey'); ?>"
                value="<?php echo esc_attr($instance['apiKey']); ?>"
                />
        </p>

        <h3 style="margin-top: 2.5rem;">"Currently reading" shelf</h3>
        <p>This is the shelf where reading progress will be displayed.</p>
        <p>
            <label for="<?php echo $this->get_field_id('currentlyReadingShelfName'); ?>">
                Name of shelf on Goodreads:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->get_field_id('currentlyReadingShelfName'); ?>"
                name="<?php echo $this->get_field_name('currentlyReadingShelfName'); ?>"
                value="<?php echo esc_attr($instance['currentlyReadingShelfName']); ?>"
                />
            <br />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('emptyMessage'); ?>">
                Message to display when no books are found:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->get_field_id('emptyMessage'); ?>"
                name="<?php echo $this->get_field_name('emptyMessage'); ?>"
                value="<?php echo esc_attr($instance['emptyMessage']); ?>"
                />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('displayProgressUpdateTime'); ?>">
                <input
                    id="<?php echo $this->get_field_id('displayProgressUpdateTime'); ?>"
                    name="<?php echo $this->get_field_name('displayProgressUpdateTime'); ?>"
        <?php echo $instance['displayProgressUpdateTime'] ? "checked" : ""; ?>
                    type="checkbox">
                Display time since last progress update (e.g. "2 days ago")
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('useProgressBar'); ?>">
                <input
                    id="<?php echo $this->get_field_id('useProgressBar'); ?>"
                    name="<?php echo $this->get_field_name('useProgressBar'); ?>"
        <?php echo $instance['useProgressBar'] ? "checked" : ""; ?>
                    value="useProgressBar"
                    type="radio">
                Display progress bar
            </label>
            <br />
            <label for="<?php echo $this->get_field_id('useProgressText'); ?>">
                <input
                    id="<?php echo $this->get_field_id('useProgressText'); ?>"
                    name="<?php echo $this->get_field_name('useProgressBar'); ?>"
        <?php echo!$instance['useProgressBar'] ? "checked" : ""; ?>
                    value="useProgressText"
                    type="radio">
                Display progress as text (no progress bar)
            </label>
            <br />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('currentlyReadingShelfSortBy'); ?>">
                Sort by:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->get_field_id('currentlyReadingShelfSortBy'); ?>"
                name="<?php echo $this->get_field_name('currentlyReadingShelfSortBy'); ?>"
                >
        <?php $this->makeHTMLSelectOptions($this->sortByOptions, $instance['currentlyReadingShelfSortBy']); ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('currentlyReadingShelfSortOrder'); ?>">
                Sort order:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->get_field_id('currentlyReadingShelfSortOrder'); ?>"
                name="<?php echo $this->get_field_name('currentlyReadingShelfSortOrder'); ?>"
                >
        <?php $this->makeHTMLSelectOptions($this->sortOrderOptions, $instance['currentlyReadingShelfSortOrder']); ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('maxBooksCurrentlyReadingShelf'); ?>">
                Display at most
                <input
                    id="<?php echo $this->get_field_id('maxBooksCurrentlyReadingShelf'); ?>"
                    name="<?php echo $this->get_field_name('maxBooksCurrentlyReadingShelf'); ?>"
                    step="1"
                    min="1"
                    value="<?php echo $instance['maxBooksCurrentlyReadingShelf']; ?>"
                    style="width: 5em;"
                    type="number">
                books from this shelf
            </label>
        </p>

        <h3 style="margin-top: 2.5rem;">Additional shelf</h3>
        <p>Here you can choose to display e.g. books you intend to read soon.</p>
        <p>
            <label for="<?php echo $this->get_field_id('additionalShelfName'); ?>">
                Name of shelf on Goodreads (leave blank to disable):
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->get_field_id('additionalShelfName'); ?>"
                name="<?php echo $this->get_field_name('additionalShelfName'); ?>"
                value="<?php echo esc_attr($instance['additionalShelfName']); ?>"
                />
            <br />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('additionalShelfHeading'); ?>">
                Heading to display above additional shelf:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->get_field_id('additionalShelfHeading'); ?>"
                name="<?php echo $this->get_field_name('additionalShelfHeading'); ?>"
                value="<?php echo esc_attr($instance['additionalShelfHeading']); ?>"
                />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('emptyMessageAdditional'); ?>">
                Message to display when no books are found:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->get_field_id('emptyMessageAdditional'); ?>"
                name="<?php echo $this->get_field_name('emptyMessageAdditional'); ?>"
                value="<?php echo esc_attr($instance['emptyMessageAdditional']); ?>"
                />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('additionalShelfSortBy'); ?>">
                Sort by:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->get_field_id('additionalShelfSortBy'); ?>"
                name="<?php echo $this->get_field_name('additionalShelfSortBy'); ?>"
                >
        <?php $this->makeHTMLSelectOptions($this->sortByOptions, $instance['additionalShelfSortBy']); ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('additionalShelfSortOrder'); ?>">
                Sort order:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->get_field_id('additionalShelfSortOrder'); ?>"
                name="<?php echo $this->get_field_name('additionalShelfSortOrder'); ?>"
                >
        <?php $this->makeHTMLSelectOptions($this->sortOrderOptions, $instance['additionalShelfSortOrder']); ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('maxBooksAdditionalShelf'); ?>">
                Display at most
                <input
                    id="<?php echo $this->get_field_id('maxBooksAdditionalShelf'); ?>"
                    name="<?php echo $this->get_field_name('maxBooksAdditionalShelf'); ?>"
                    step="1"
                    min="1"
                    value="<?php echo $instance['maxBooksAdditionalShelf']; ?>"
                    style="width: 5em;"
                    type="number">
                books from this shelf
            </label>
        </p>

        <h3 style="margin-top: 2.5rem;">Caching</h3>
        <p>Getting your data from Goodreads takes time. If you set the values below to 0 (bad idea!), <em>every single</em> page load will fetch new data from Goodreads. That's totally unnecessary because the books on your shelves and your reading progress probably doesn't change <em>that</em> often.</p>
        <p>
            <label for="<?php echo $this->get_field_id('progressCacheHours'); ?>">
                Update progress every 
                <input
                    id="<?php echo $this->get_field_id('progressCacheHours'); ?>"
                    name="<?php echo $this->get_field_name('progressCacheHours'); ?>"
                    step="1"
                    min="0"
                    value="<?php echo $instance['progressCacheHours']; ?>"
                    style="width: 5em;"
                    type="number">
                hours (default 24)
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('bookCacheHours'); ?>">
                Update book lists every 
                <input
                    id="<?php echo $this->get_field_id('bookCacheHours'); ?>"
                    name="<?php echo $this->get_field_name('bookCacheHours'); ?>"
                    step="1"
                    min="0"
                    value="<?php echo $instance['bookCacheHours']; ?>"
                    style="width: 5em;"
                    type="number">
                hours (default 24)
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('deleteCacheOnSave'); ?>">
                <input
                    id="<?php echo $this->get_field_id('deleteCacheOnSave'); ?>"
                    name="<?php echo $this->get_field_name('regenerateCacheOnSave'); ?>"
        <?php echo!$instance['regenerateCacheOnSave'] ? "checked" : ""; ?>
                    value="noRegenerateCache"
                    type="radio">
                Delete cache when saving widget (first visitor will regenerate)
            </label>
            <br />
            <label for="<?php echo $this->get_field_id('regenerateCacheOnSave'); ?>">
                <input
                    id="<?php echo $this->get_field_id('regenerateCacheOnSave'); ?>"
                    name="<?php echo $this->get_field_name('regenerateCacheOnSave'); ?>"
        <?php echo $instance['regenerateCacheOnSave'] ? "checked" : ""; ?>
                    value="regenerateCache"
                    type="radio">
                Regenerate cache when saving widget (visitors will not notice any slowdown)
            </label>
            <br />
            <small>Please be patient when cache is regenerating.</small>
        </p>
        <p>
            Cover URLs take several seconds to fetch from Goodreads and are cached separately. If you experience trouble with cover images, you can delete the cache by ticking the box below.<br />
            <label for="<?php echo $this->get_field_id('deleteCoverURLCacheOnSave'); ?>">
                <input
                    id="<?php echo $this->get_field_id('deleteCoverURLCacheOnSave'); ?>"
                    name="<?php echo $this->get_field_name('deleteCoverURLCacheOnSave'); ?>"
        <?php // Don't set "checked" attribute - this should be reset to unchecked/false on each save    ?>
                    type="checkbox">
                Delete the cover URL cache the next time you save these settings.
            </label>
        </p>

        <?php
    }

    private function makeHTMLSelectOptions($options, $selectedOption) {
        foreach ($options as $value => $description) {
            $selected = $selectedOption == $value ? 'selected="selected"' : '';
            echo "<option value='$value' $selected>";
            echo $description;
            echo "</option>";
        }
    }

    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);

        preg_match("/\d+/", $new_instance['userid'], $matches_userid);
        $instance['userid'] = count($matches_userid) > 0 ? $matches_userid[0] : "";
        $instance['apiKey'] = strip_tags($new_instance['apiKey']);
        $instance['currentlyReadingShelfName'] = strip_tags($new_instance['currentlyReadingShelfName']);
        $instance['emptyMessage'] = strip_tags($new_instance['emptyMessage']);
        $instance['displayProgressUpdateTime'] = isset($new_instance['displayProgressUpdateTime']) ? true : false;
        $instance['useProgressBar'] = $new_instance['useProgressBar'] == 'useProgressBar' ? true : false;
        $instance['currentlyReadingShelfSortBy'] = array_key_exists($new_instance['currentlyReadingShelfSortBy'], $this->sortByOptions) ? $new_instance['currentlyReadingShelfSortBy'] : $this->defaults['currentlyReadingShelfSortBy'];
        $instance['currentlyReadingShelfSortOrder'] = array_key_exists($new_instance['currentlyReadingShelfSortOrder'], $this->sortOrderOptions) ? $new_instance['currentlyReadingShelfSortOrder'] : $this->defaults['currentlyReadingShelfSortOrder'];
        $instance['maxBooksCurrentlyReadingShelf'] = preg_match("/\d+/", $new_instance['maxBooksCurrentlyReadingShelf']) ? intval($new_instance['maxBooksCurrentlyReadingShelf']) : 10;
        $instance['additionalShelfName'] = strip_tags($new_instance['additionalShelfName']);
        $instance['additionalShelfHeading'] = strip_tags($new_instance['additionalShelfHeading']);
        $instance['emptyMessageAdditional'] = strip_tags($new_instance['emptyMessageAdditional']);
        $instance['additionalShelfSortBy'] = array_key_exists($new_instance['additionalShelfSortBy'], $this->sortByOptions) ? $new_instance['additionalShelfSortBy'] : $this->defaults['additionalShelfSortBy'];
        $instance['additionalShelfSortOrder'] = array_key_exists($new_instance['additionalShelfSortOrder'], $this->sortOrderOptions) ? $new_instance['additionalShelfSortOrder'] : $this->defaults['additionalShelfSortOrder'];
        $instance['maxBooksAdditionalShelf'] = preg_match("/\d+/", $new_instance['maxBooksAdditionalShelf']) ? intval($new_instance['maxBooksAdditionalShelf']) : 10;
        $instance['progressCacheHours'] = preg_match("/\d+/", $new_instance['progressCacheHours']) ? intval($new_instance['progressCacheHours']) : 24;
        $instance['bookCacheHours'] = preg_match("/\d+/", $new_instance['bookCacheHours']) ? intval($new_instance['bookCacheHours']) : 24;
        $instance['regenerateCacheOnSave'] = $new_instance['regenerateCacheOnSave'] == 'regenerateCache' ? true : false;

        $this->widgetData = $instance;

        if (isset($new_instance['deleteCoverURLCacheOnSave'])) {
            delete_option("gr_progress_cvdm_coverURLs");
        }

        if ($instance['regenerateCacheOnSave']) {
            $this->regenerateCache($instance);
        } else {
            $this->deleteCache();
        }

        return $instance;
    }

    private function regenerateCache() {
        $this->fetchNewShelvesIfNeeded();
        $this->updateProgressIfNeeded();
        $this->saveCacheIfNeeded();
    }

    private function deleteCache() {
        delete_option("gr_progress_cvdm_shelves");
        delete_option("gr_progress_cvdm_lastRetrievalErrorTime");
    }

}

// Register and load the widget
function gr_progress_cvdm_load_widget() {
    register_widget('gr_progress_cvdm_widget');
    wp_enqueue_style('gr-progress-cvdm-style-default', plugin_dir_url(__FILE__) . 'style.css');
}

add_action('widgets_init', 'gr_progress_cvdm_load_widget');

function time_elapsed_string($datetime, $full = false) {
    // from http://stackoverflow.com/a/18602474/2978652
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
