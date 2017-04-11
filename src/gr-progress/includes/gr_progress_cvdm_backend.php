<?php

namespace relativisticramblings\gr_progress;

require_once("simple_html_dom.php");
require_once("Shelf.php");

abstract class Progress {

    const DISABLED = 0;
    const PROGRESSBAR = 1;
    const TEXT = 2;

}

abstract class CoverSize {

    const SMALL = 0;
    const LARGE = 1;

}

class gr_progress_cvdm_backend {

    private $widget;
    private $shelf;
    private $widgetData;
    private $DEFAULT_SETTINGS = [
        'title' => 'Currently reading',
        'goodreadsAttribution' => 'Data from Goodreads',
        'userid' => '',
        'apiKey' => '',
        'shelfName' => 'currently-reading',
        'emptyMessage' => 'Not currently reading anything.',
        'coverSize' => CoverSize::SMALL,
        'displayRating' => false,
        'displayReviewExcerpt' => false,
        'bookLink' => false,
        'bookLinkNewTab' => false,
        'maxBooks' => 3,
        'sortByReadingProgress' => false,
        'sortBy' => 'date_updated',
        'sortOrder' => 'd',
        'progressType' => Progress::DISABLED,
        'displayProgressUpdateTime' => true,
        'intervalTemplate' => '{num} {period} ago',
        'intervalSingular' => ['year', 'month', 'week', 'day', 'hour', 'minute', 'second'],
        'intervalPlural' => ['years', 'months', 'weeks', 'days', 'hours', 'minutes', 'seconds'],
        'cacheTimeInHours' => 24,
        'deleteCoverURLCacheOnSave' => false,
    ];
    private $SORT_BY_OPTIONS = [
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
    private $SORT_ORDER_OPTIONS = [
        'a' => 'Ascending',
        'd' => 'Descending',
    ];

    function __construct($WP_Widget_instance) {
        $this->widget = $WP_Widget_instance;
        $this->shelf = null;
    }

    /**
     * @return string a unique widget ID (md5 hash based on the widget instance data)
     */
    public function getWidgetKey() {
        return 'cvdm-' . md5(serialize($this->widgetData));
    }

    public function printWidget($args, $instance) {
        $this->widgetData = $instance;

        if ($this->hasCache()) {
            $this->shelf = $this->getCachedShelf();
        } elseif ($this->widgetProperlyConfigured()) {
            $this->fetchNewShelf();
            if (get_transient('cvdm_gr_progress_disableFetchingUntil') === false) {
                $this->saveShelfToCache();
            } else {
                // Goodreads fetch failed, so get last known shelf data from option instead
                $this->shelf = $this->getCachedShelf();
            }
        }

        echo $this->getWidgetHTML($args);
    }

    private function hasCache() {
        return $this->cacheNotExpired() && $this->getCachedShelf() !== null && !isset($_GET['force_gr_progress_update']);
    }

    private function cacheNotExpired() {
        return get_transient($this->getWidgetKey()) !== false;
    }

    private function getCachedShelf() {
        $shelves = get_option('cvdm_gr_progress_shelves', []);
        $shelfName = $this->widgetData['shelfName'];
        return array_key_exists($shelfName, $shelves) ? $shelves[$shelfName] : null;
    }

    private function saveShelfToCache() {
        set_transient($this->getWidgetKey(), true, $this->widgetData['cacheTimeInHours'] * 3600);

        // if the shelf exists in cache, we assume this is the first
        // widget instance to update, so delete the whole cache (which makes
        // the above assumption true) to force the other widgets to update
        // their cache at the same time
        if ($this->getCachedShelf() !== null) {
            delete_option('cvdm_gr_progress_shelves');
        }

        $shelves = get_option('cvdm_gr_progress_shelves', []);
        $shelves[$this->widgetData['shelfName']] = $this->shelf;
        update_option('cvdm_gr_progress_shelves', $shelves);
    }

    private function firstWidgetToUpdate() {
        $cachedShelves = get_option('cvdm_gr_progress_shelves', []);
        return array_key_exists($this->widgetData['shelfName'], $cachedShelves);
    }

    private function widgetProperlyConfigured() {
        $d = $this->widgetData;
        return !empty($d['userid']) && !empty($d['apiKey']) && !empty($d['shelfName']);
    }

    private function getWidgetHTML($args) {
        ob_start();
        $this->printWidgetBoilerplateStart($args);
        $this->printGoodreadsAttribution();
        $this->printShelf();
        $this->printWidgetBoilerplateEnd($args);
        return ob_get_clean();
    }

    private function fetchNewShelf() {
        $this->shelf = new Shelf($this->widgetData);
    }

    private function printWidgetBoilerplateStart($args) {
        echo $args['before_widget'];  // defined by themes

        $title = apply_filters('widget_title', $this->widgetData['title']);
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
    }

    private function printWidgetBoilerplateEnd($args) {
        echo $args['after_widget'];  // defined by themes
    }

    private function printGoodreadsAttribution() {
        echo "<p class='goodreads-attribution'>{$this->widgetData['goodreadsAttribution']}</p>";
    }

    private function printShelf() {
        $disableFetchingUntil = get_transient('cvdm_gr_progress_disableFetchingUntil');
        if ($this->shelf === null && $disableFetchingUntil !== false) {
            $minutesUntilRetry = ceil(($disableFetchingUntil - time()) / 60);
            echo "<p>Error retrieving data from Goodreads. Retrying in $minutesUntilRetry minutes.</p>";
        } elseif (!$this->widgetProperlyConfigured()) {
            echo "<p>Widget not configured correctly.</p>";
        } elseif ($this->shelf->isEmpty() && !empty($this->widgetData['emptyMessage'])) {
            echo "<p class='emptyShelfMessage'>{$this->widgetData['emptyMessage']}</p>";
        } elseif (!$this->shelf->isEmpty()) {
            $class = $this->widgetData['coverSize'] === CoverSize::SMALL ? 'small-cover' : 'large-cover';
            echo "<ul class='bookshelf $class'>";
            $this->printBooksOnShelf($this->shelf);
            echo "</ul>";
        }
    }

    private function printBooksOnShelf($shelf) {
        foreach ($shelf->getBooks() as $book) {
            $this->printBook($book);
        }
    }

    private function printBook($book) {
        echo "<li class='book'>";
        echo "<div class='coverImage'><img alt='Book cover' src='{$book->getCoverURL()}' /></div>";
        echo "<div class='desc'>";
        echo $this->getBookTitleHTML($book);
        echo "<p class='author'>{$book->getAuthor()}</p>";

        if ($this->widgetData['displayRating'] === true && $book->hasRating()) {
            echo $this->getBookRatingHTML($book);
        }

        if ($this->widgetData['progressType'] === Progress::PROGRESSBAR) {
            $this->printProgressBar($book);
        } elseif ($this->widgetData['progressType'] === Progress::TEXT) {
            $this->printProgressString($book);
        }

        if ($book->hasComment()) {
            echo "<p class='bookComment'>{$book->getComment()}</p>";
        }

        echo "</div>";
        echo "</li>";
    }

    private function getBookTitleHTML($book) {
        $output = "<p class='bookTitle'>";
        if ($this->widgetData['bookLink']) {
            $target = $this->widgetData['bookLinkNewTab'] ? "target='_blank'" : '';
            $output .= "<a href='{$book->getLink()}' $target>{$book->getTitle()}</a>";
        } else {
            $output .= $book->getTitle();
        }
        $output .= "</p>";
        return $output;
    }

    private function getBookRatingHTML($book) {
        $output = "<p class='bookRating'>";
        $numFilledStars = $book->getRating();
        $numEmptyStars = 5 - $numFilledStars;
        $output .= str_repeat("<span class='gr-progress-rating-star gr-progress-rating-star-filled'>&#9733;</span>", $numFilledStars);
        $output .= str_repeat("<span class='gr-progress-rating-star gr-progress-rating-star-empty'>&#9734;</span>", $numEmptyStars);
        $output .= "</p>";
        return $output;
    }

    private function printProgressBar($book) {
        if (!$book->hasProgress()) {
            return;
        }
        $percent = $book->getProgressInPercent();
        $progressStatusUpdateTime = $book->getProgressStatusUpdateTime();
        $time = $this->widgetData['displayProgressUpdateTime'] ? " (" . $this->getTimeElapsedString("@" . strval($progressStatusUpdateTime)) . ")" : "";
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
        if (!$book->hasProgress()) {
            return;
        }
        $percent = $book->getProgressInPercent();
        $progressStatusUpdateTime = $book->getProgressStatusUpdateTime();
        $time = $this->widgetData['displayProgressUpdateTime'] ? " (" . $this->getTimeElapsedString("@" . strval($progressStatusUpdateTime)) . ")" : "";
        echo "<p class='progress progress-text'>$percent&thinsp;%$time</p>";
    }

    private function getTimeElapsedString($datetime) {
        $now = new \DateTime;
        $then = new \DateTime($datetime);
        $dateInterval = $now->diff($then);

        $dateInterval->w = floor($dateInterval->d / 7);
        $dateInterval->d -= $dateInterval->w * 7;

        // make sure it's at least 1 second ago so we don't have to deal with "just now"
        // (which would complicate the widget settings)
        $dateInterval->s = max($dateInterval->s, 1);

        $intervalCodes = array('y', 'm', 'w', 'd', 'h', 'i', 's',);
        foreach ($intervalCodes as $i => $interval) {
            $numberOfIntervals = $dateInterval->$interval;
            if ($numberOfIntervals > 0) {
                $intervalName = $numberOfIntervals > 1 ? $this->widgetData['intervalPlural'][$i] : $this->widgetData['intervalSingular'][$i];
                $output = str_replace(['{num}', '{period}'], [$numberOfIntervals, $intervalName], $this->widgetData['intervalTemplate']);
                break;
            }
        }
        return $output;
    }

    public function printForm($instance) {

        foreach ($this->DEFAULT_SETTINGS as $setting => $defaultValue) {
            if (!isset($instance[$setting])) {
                $instance[$setting] = $defaultValue;
            }
        }
        ?>

        <p style="text-align: center; color: #31708f; background-color: #d9edf7; border: 1px solid #bce8f1; border-radius: 4px; padding: 15px;"><strong>Important!</strong> Remember to make your Goodreads profile public, otherwise no books will be visible.</p>
        <p style="text-align: center; color: #31708f; background-color: #d9edf7; border: 1px solid #bce8f1; border-radius: 4px; padding: 15px;">If the styling isn't working out for your theme, style the widget however you want in your theme's CSS file.</p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('title'); ?>">
                Title:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('title'); ?>"
                name="<?php echo $this->widget->get_field_name('title'); ?>"
                value="<?php echo esc_attr($instance['title']); ?>"
                />
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('goodreadsAttribution'); ?>">
                Goodreads attribution text:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('goodreadsAttribution'); ?>"
                name="<?php echo $this->widget->get_field_name('goodreadsAttribution'); ?>"
                value="<?php echo esc_attr($instance['goodreadsAttribution']); ?>"
                placeholder="<?php echo $this->DEFAULT_SETTINGS['goodreadsAttribution'] ?>"
                />
            <br />
            <small>Goodreads attribution is required per the <a target="_blank" href="https://www.goodreads.com/api/terms">Goodreads API Terms of Service</a>. This field will let you change/translate it, not remove it. You have to mention "Goodreads" in this field. Links are possible, e.g. <span style='font-family:monospace'>&lt;a href="https://www.goodreads.com/user/show/123456789-user"&gt;My Goodreads profile&lt;/a&gt;</span></small>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('userid'); ?>">
                Your Goodreads user ID or profile URL:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('userid'); ?>"
                name="<?php echo $this->widget->get_field_name('userid'); ?>"
                value="<?php echo esc_attr($instance['userid']); ?>"
                />
                <br />
                <small>If you're a Goodreads author (i.e., if your profile url contains "author" instead of "user"), then the number in your profile URL is your author ID, not your user ID. You'll have to find your user ID to use this widget. To do that, visit <strong>goodreads.com/author/show/YOUR_AUTHOR_ID?key=YOUR_API_KEY</strong> (see below for the API key). Search for <strong>&lt;user&gt;</strong> to find your user ID and enter it above.</small>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('apiKey'); ?>">
                Goodreads API key:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('apiKey'); ?>"
                name="<?php echo $this->widget->get_field_name('apiKey'); ?>"
                value="<?php echo esc_attr($instance['apiKey']); ?>"
                />
            <br />
            <small>Get one <a target="_blank" href="https://www.goodreads.com/api/keys">here</a> - doesn't matter what you write.</small>
        </p>

        <h3 style="margin-top: 2.5rem;">Shelf configuration</h3>
        <p>
            <label for="<?php echo $this->widget->get_field_id('shelfName'); ?>">
                Name of shelf on Goodreads:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('shelfName'); ?>"
                name="<?php echo $this->widget->get_field_name('shelfName'); ?>"
                value="<?php echo esc_attr($instance['shelfName']); ?>"
                />
            <br />
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('emptyMessage'); ?>">
                Message to display when no books are found:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('emptyMessage'); ?>"
                name="<?php echo $this->widget->get_field_name('emptyMessage'); ?>"
                value="<?php echo esc_attr($instance['emptyMessage']); ?>"
                />
        </p>
        <p>
            Cover image size:<br />
            <label for="<?php echo $this->widget->get_field_id('coverSize_large'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('coverSize_large'); ?>"
                    name="<?php echo $this->widget->get_field_name('coverSize'); ?>"
                    <?php checked($instance['coverSize'], CoverSize::LARGE); ?>
                    value="<?php echo CoverSize::LARGE ?>"
                    type="radio">
                Large
            </label>
            <br />
            <label for="<?php echo $this->widget->get_field_id('coverSize_small'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('coverSize_small'); ?>"
                    name="<?php echo $this->widget->get_field_name('coverSize'); ?>"
                    <?php checked($instance['coverSize'], CoverSize::SMALL); ?>
                    value="<?php echo CoverSize::SMALL ?>"
                    type="radio">
                Small
            </label>

        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('displayRating'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('displayRating'); ?>"
                    name="<?php echo $this->widget->get_field_name('displayRating'); ?>"
                    <?php checked($instance['displayRating']); ?>
                    type="checkbox">
                Display your rating of each book on this shelf<br/>
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('displayReviewExcerpt'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('displayReviewExcerpt'); ?>"
                    name="<?php echo $this->widget->get_field_name('displayReviewExcerpt'); ?>"
                    <?php checked($instance['displayReviewExcerpt']); ?>
                    type="checkbox">
                Display the first paragraph of your Goodreads review for each book<br/>
                <small>Intended for quick notes such as "reading this together with Alice" or "recommended by Bob" or whatever else strikes you fancy.</small>
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('bookLink'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('bookLink'); ?>"
                    name="<?php echo $this->widget->get_field_name('bookLink'); ?>"
                    <?php checked($instance['bookLink']); ?>
                    type="checkbox">
                Link book titles to the book pages on Goodreads<br/>
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('bookLinkNewTab'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('bookLinkNewTab'); ?>"
                    name="<?php echo $this->widget->get_field_name('bookLinkNewTab'); ?>"
                    <?php checked($instance['bookLinkNewTab']); ?>
                    type="checkbox">
                Open book links in a new tab/window<br/>
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('maxBooks'); ?>">
                Display at most
                <input
                    id="<?php echo $this->widget->get_field_id('maxBooks'); ?>"
                    name="<?php echo $this->widget->get_field_name('maxBooks'); ?>"
                    step="1"
                    min="1"
                    value="<?php echo $instance['maxBooks']; ?>"
                    style="width: 5em;"
                    type="number">
                books
            </label>
        </p>

        <h3 style="margin-top: 2.5rem;">Sorting</h3>
        <p>
            <label for="<?php echo $this->widget->get_field_id('sortByReadingProgress'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('sortByReadingProgress'); ?>"
                    name="<?php echo $this->widget->get_field_name('sortByReadingProgress'); ?>"
                    <?php echo $instance['sortByReadingProgress'] ? "checked" : ""; ?>
                    type="checkbox">
                Sort books by reading progress instead of the sorting options below (which will be used for books with identical or missing reading progress)
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('sortBy'); ?>">
                Sort by:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->widget->get_field_id('sortBy'); ?>"
                name="<?php echo $this->widget->get_field_name('sortBy'); ?>"
                >
                    <?php $this->makeHTMLSelectOptions($this->SORT_BY_OPTIONS, $instance['sortBy']); ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('sortOrder'); ?>">
                Sort order:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->widget->get_field_id('sortOrder'); ?>"
                name="<?php echo $this->widget->get_field_name('sortOrder'); ?>"
                >
                    <?php $this->makeHTMLSelectOptions($this->SORT_ORDER_OPTIONS, $instance['sortOrder']); ?>
            </select>
        </p>

        <h3 style="margin-top: 2.5rem;">Reading progress</h3>
        <p>
            <label for="<?php echo $this->widget->get_field_id('progressType_disabled'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('progressType_disabled'); ?>"
                    name="<?php echo $this->widget->get_field_name('progressType'); ?>"
                    <?php echo $instance['progressType'] === Progress::DISABLED ? "checked" : ""; ?>
                    value="<?php echo Progress::DISABLED ?>"
                    type="radio">
                Hide reading progress
            </label>
            <br />
            <label for="<?php echo $this->widget->get_field_id('progressType_progressbar'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('progressType_progressbar'); ?>"
                    name="<?php echo $this->widget->get_field_name('progressType'); ?>"
                    <?php echo $instance['progressType'] === Progress::PROGRESSBAR ? "checked" : ""; ?>
                    value="<?php echo Progress::PROGRESSBAR ?>"
                    type="radio">
                Show progress bar
            </label>
            <br />
            <label for="<?php echo $this->widget->get_field_id('progressType_text'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('progressType_text'); ?>"
                    name="<?php echo $this->widget->get_field_name('progressType'); ?>"
                    <?php echo $instance['progressType'] === Progress::TEXT ? "checked" : ""; ?>
                    value="<?php echo Progress::TEXT ?>"
                    type="radio">
                Show progress as text only
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('displayProgressUpdateTime'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('displayProgressUpdateTime'); ?>"
                    name="<?php echo $this->widget->get_field_name('displayProgressUpdateTime'); ?>"
                    <?php echo $instance['displayProgressUpdateTime'] ? "checked" : ""; ?>
                    type="checkbox">
                Display time since last progress update (e.g. "2 days ago")
            </label>
        </p>
        <p>If you need to, you can translate the time shown:</p>
        <label for="<?php echo $this->widget->get_field_id('intervalTemplate'); ?>">
            Template:
        </label>
        <input
            class="widefat"
            type="text"
            id="<?php echo $this->widget->get_field_id('intervalTemplate'); ?>"
            name="<?php echo $this->widget->get_field_name('intervalTemplate'); ?>"
            value="<?php echo esc_attr($instance['intervalTemplate']); ?>"
            placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalTemplate'] ?>"
            />
        <table>
            <tr>
                <td></td>
                <td>Singular:</td>
                <td>Plural:</td>
            </tr>
            <tr>
                <td>Year</td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalSingularYear'); ?>" name="<?php echo $this->widget->get_field_name('intervalSingular'); ?>[]" value="<?php echo esc_attr($instance['intervalSingular'][0]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalSingular'][0] ?>" /></td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalPluralYear'); ?>" name="<?php echo $this->widget->get_field_name('intervalPlural'); ?>[]" value="<?php echo esc_attr($instance['intervalPlural'][0]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalPlural'][0] ?>" /></td>
            </tr>
            <tr>
                <td>Month</td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalSingularMonth'); ?>" name="<?php echo $this->widget->get_field_name('intervalSingular'); ?>[]" value="<?php echo esc_attr($instance['intervalSingular'][1]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalSingular'][1] ?>" /></td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalPluralMonth'); ?>" name="<?php echo $this->widget->get_field_name('intervalPlural'); ?>[]" value="<?php echo esc_attr($instance['intervalPlural'][1]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalPlural'][1] ?>" /></td>
            </tr>
            <tr>
                <td>Week</td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalSingularWeek'); ?>" name="<?php echo $this->widget->get_field_name('intervalSingular'); ?>[]" value="<?php echo esc_attr($instance['intervalSingular'][2]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalSingular'][2] ?>" /></td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalPluralWeek'); ?>" name="<?php echo $this->widget->get_field_name('intervalPlural'); ?>[]" value="<?php echo esc_attr($instance['intervalPlural'][2]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalPlural'][2] ?>" /></td>
            </tr>
            <tr>
                <td>Day</td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalSingularDay'); ?>" name="<?php echo $this->widget->get_field_name('intervalSingular'); ?>[]" value="<?php echo esc_attr($instance['intervalSingular'][3]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalSingular'][3] ?>" /></td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalPluralDay'); ?>" name="<?php echo $this->widget->get_field_name('intervalPlural'); ?>[]" value="<?php echo esc_attr($instance['intervalPlural'][3]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalPlural'][3] ?>" /></td>
            </tr>
            <tr>
                <td>Hour</td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalSingularHour'); ?>" name="<?php echo $this->widget->get_field_name('intervalSingular'); ?>[]" value="<?php echo esc_attr($instance['intervalSingular'][4]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalSingular'][4] ?>" /></td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalPluralHour'); ?>" name="<?php echo $this->widget->get_field_name('intervalPlural'); ?>[]" value="<?php echo esc_attr($instance['intervalPlural'][4]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalPlural'][4] ?>" /></td>
            </tr>
            <tr>
                <td>Minute</td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalSingularMinute'); ?>" name="<?php echo $this->widget->get_field_name('intervalSingular'); ?>[]" value="<?php echo esc_attr($instance['intervalSingular'][5]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalSingular'][5] ?>" /></td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalPluralMinute'); ?>" name="<?php echo $this->widget->get_field_name('intervalPlural'); ?>[]" value="<?php echo esc_attr($instance['intervalPlural'][5]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalPlural'][5] ?>" /></td>
            </tr>
            <tr>
                <td>Second</td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalSingularSecond'); ?>" name="<?php echo $this->widget->get_field_name('intervalSingular'); ?>[]" value="<?php echo esc_attr($instance['intervalSingular'][6]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalSingular'][6] ?>" /></td>
                <td><input class="widefat" type="text" id="<?php echo $this->widget->get_field_id('intervalPluralSecond'); ?>" name="<?php echo $this->widget->get_field_name('intervalPlural'); ?>[]" value="<?php echo esc_attr($instance['intervalPlural'][6]); ?>" placeholder="<?php echo $this->DEFAULT_SETTINGS['intervalPlural'][6] ?>" /></td>
            </tr>
        </table>

        <h3 style="margin-top: 2.5rem;">Caching</h3>
        <p>
            <label for="<?php echo $this->widget->get_field_id('cacheTimeInHours'); ?>">
                Cache book and progress data for
                <input
                    id="<?php echo $this->widget->get_field_id('cacheTimeInHours'); ?>"
                    name="<?php echo $this->widget->get_field_name('cacheTimeInHours'); ?>"
                    step="1"
                    min="0"
                    value="<?php echo $instance['cacheTimeInHours']; ?>"
                    style="width: 5em;"
                    placeholder="<?php echo $this->DEFAULT_SETTINGS['cacheTimeInHours'] ?>"
                    type="number">
                hours
            </label>
            <br />
            <small>If you set it to 0 it will only be updated whenever you save the widget settings, or when you visit the page containing the widget and add the URL parameter <code>force_gr_progress_update</code>. For example, you can visit <code>http://yoursite.com/pageWithWidget/?force_gr_progress_update</code>. This can be automated by cron jobs if your host supports it, meaning that visitors will never experience slowdowns due to the widget having to fetch data from Goodreads.
            <br />
            <br />
            If you have multiple GR Progress widgets, they will update at the same time, and the shortest cache time will be used.</small>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('deleteCoverURLCacheOnSave'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('deleteCoverURLCacheOnSave'); ?>"
                    name="<?php echo $this->widget->get_field_name('deleteCoverURLCacheOnSave'); ?>"
                    <?php // Don't set "checked" attribute - this should be reset to unchecked/false on each save  ?>
                    type="checkbox">
                Reset cover URL cache upon next save
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

    public function getNewWidgetSettings($new_instance, $old_instance) {

        $instance = $old_instance;

        $instance['title'] = trim(htmlspecialchars($new_instance['title']));

        $goodreadsAttribution = trim(htmlspecialchars($new_instance['goodreadsAttribution'], $flags = ENT_NOQUOTES));
        $instance['goodreadsAttribution'] = preg_match("/goodreads/i", $goodreadsAttribution) ? $goodreadsAttribution : $this->DEFAULT_SETTINGS['goodreadsAttribution'];
        $instance['goodreadsAttribution'] = preg_replace('#&lt;a(.*?)\s*&gt;#', '<a\1>', $instance['goodreadsAttribution']);
        $instance['goodreadsAttribution'] = preg_replace('#&lt;/a\s*&gt;#', '</a>', $instance['goodreadsAttribution']);

        preg_match("/\d+/", $new_instance['userid'], $matches_userid);
        $instance['userid'] = !empty($matches_userid) ? $matches_userid[0] : $this->DEFAULT_SETTINGS['userid'];

        $instance['apiKey'] = trim(htmlspecialchars($new_instance['apiKey']));
        $instance['shelfName'] = trim(htmlspecialchars($new_instance['shelfName']));
        $instance['emptyMessage'] = trim(htmlspecialchars($new_instance['emptyMessage']));
        $instance['coverSize'] = intval($new_instance['coverSize']);
        $instance['displayRating'] = isset($new_instance['displayRating']) ? true : $this->DEFAULT_SETTINGS['displayRating'];
        $instance['displayReviewExcerpt'] = isset($new_instance['displayReviewExcerpt']) ? true : $this->DEFAULT_SETTINGS['displayReviewExcerpt'];
        $instance['bookLink'] = isset($new_instance['bookLink']) ? true : $this->DEFAULT_SETTINGS['bookLink'];
        $instance['bookLinkNewTab'] = isset($new_instance['bookLinkNewTab']) ? true : $this->DEFAULT_SETTINGS['bookLinkNewTab'];
        $instance['maxBooks'] = preg_match("/^\d+/", $new_instance['maxBooks']) ? max(1, intval($new_instance['maxBooks'])) : $this->DEFAULT_SETTINGS['maxBooks'];
        $instance['sortByReadingProgress'] = isset($new_instance['sortByReadingProgress']) ? true : false;
        $instance['sortBy'] = array_key_exists($new_instance['sortBy'], $this->SORT_BY_OPTIONS) ? $new_instance['sortBy'] : $this->DEFAULT_SETTINGS['sortBy'];
        $instance['sortOrder'] = array_key_exists($new_instance['sortOrder'], $this->SORT_ORDER_OPTIONS) ? $new_instance['sortOrder'] : $this->DEFAULT_SETTINGS['sortOrder'];
        $instance['progressType'] = intval($new_instance['progressType']);
        $instance['displayProgressUpdateTime'] = isset($new_instance['displayProgressUpdateTime']) ? true : false;

        $intervalTemplate = trim(htmlspecialchars($new_instance['intervalTemplate']));
        $instance['intervalTemplate'] = !empty($intervalTemplate) ? $intervalTemplate : $this->DEFAULT_SETTINGS['intervalTemplate'];

        foreach (['intervalSingular', 'intervalPlural'] as $intervalPluralSingular) {
            $instance[$intervalPluralSingular] = $new_instance[$intervalPluralSingular];
            foreach ($instance[$intervalPluralSingular] as $i => &$str) {
                $str = trim(htmlspecialchars($str));
                if (empty($str)) {
                    $str = $this->DEFAULT_SETTINGS[$intervalPluralSingular][$i];
                }
            }
        }

        $instance['cacheTimeInHours'] = preg_match("/^\d+/", $new_instance['cacheTimeInHours']) ? intval($new_instance['cacheTimeInHours']) : $this->DEFAULT_SETTINGS['cacheTimeInHours'];

        $this->widgetData = $instance;
        delete_transient($this->getWidgetKey());
        delete_option('cvdm_gr_progress_shelves');
        delete_transient('cvdm_gr_progress_disableFetchingUntil');

        if (isset($new_instance['deleteCoverURLCacheOnSave'])) {
            delete_option("gr_progress_cvdm_coverURLs");
        }

        return $instance;
    }

}
