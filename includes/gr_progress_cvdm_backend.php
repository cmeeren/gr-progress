<?php

namespace relativisticramblings\gr_progress;

require_once("simple_html_dom.php");
require_once("Shelf.php");

// Creating the widget
class gr_progress_cvdm_backend {

    private $widget;
    private $shelves;
    private $widgetData;
    private $cacheNeedsUpdate = false;
    private $CURRENTLY_READING_SHELF_KEY = 'currentlyReadingShelf';
    private $ADDITIONAL_SHELF_KEY = 'additionalShelf';
    private $SECONDS_TO_WAIT_AFTER_FAILED_FETCH = 3600;
    private $DEFAULT_SETTINGS = [
        'title' => 'Currently reading',
        'goodreadsAttribution' => 'Data from Goodreads',
        'userid' => '',
        'apiKey' => '',
        'currentlyReadingShelfName' => 'currently-reading',
        'emptyMessage' => 'Not currently reading anything.',
        'displayReviewExcerptCurrentlyReadingShelf' => false,
        'sortByReadingProgress' => false,
        'displayProgressUpdateTime' => true,
        'intervalTemplate' => '{num} {period} ago',
        'intervalSingular' => ['year', 'month', 'week', 'day', 'hour', 'minute', 'second'],
        'intervalPlural' => ['years', 'months', 'weeks', 'days', 'hours', 'minutes', 'seconds'],
        'useProgressBar' => true,
        'currentlyReadingShelfSortBy' => 'date_updated',
        'currentlyReadingShelfSortOrder' => 'd',
        'maxBooksCurrentlyReadingShelf' => 3,
        'additionalShelfName' => 'to-read',
        'additionalShelfHeading' => 'Reading soon',
        'emptyMessageAdditional' => "Nothing planned at the moment.",
        'displayReviewExcerptAdditionalShelf' => false,
        'additionalShelfSortBy' => 'position',
        'additionalShelfSortOrder' => 'a',
        'maxBooksAdditionalShelf' => 3,
        'progressCacheHours' => 24,
        'bookCacheHours' => 24,
        'regenerateCacheOnSave' => false,
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
        $this->initializeEmptyShelves();
    }

    function initializeEmptyShelves() {
        $this->shelves = [
            $this->CURRENTLY_READING_SHELF_KEY => null,
            $this->ADDITIONAL_SHELF_KEY => null
        ];
    }

    public function printWidget($args, $instance) {
        $this->widgetData = $instance;
        $this->printWidgetBoilerplateStart($args);
        $this->loadWidgetData();
        $this->printWidgetContents();
        $this->printWidgetBoilerplateEnd($args);
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

    private function loadWidgetData() {
        $this->loadCachedShelves();
        if ($this->sufficientTimeSinceLastRetrievalError()) {
            $this->fetchNewShelvesIfNeeded();
            $this->updateProgressIfNeeded();
            $this->saveCacheIfNeeded();
        }
    }

    private function loadCachedShelves() {
        $shelves = get_option("gr_progress_cvdm_shelves", null);
        if ($shelves !== null && isset($shelves[$this->widget->number])) {
            $this->shelves = $shelves[$this->widget->number];
        }
    }

    private function sufficientTimeSinceLastretrievalError() {
        $lastRetrievalErrorTime = get_option("gr_progress_cvdm_lastRetrievalErrorTime", 0);
        $secondsSinceLastRetrievalError = time() - $lastRetrievalErrorTime;
        return $secondsSinceLastRetrievalError > $this->SECONDS_TO_WAIT_AFTER_FAILED_FETCH;
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
            $shelves = get_option("gr_progress_cvdm_shelves", []);
            $shelves[$this->widget->number] = $this->shelves;
            update_option("gr_progress_cvdm_shelves", $shelves);
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
        echo "<p class='goodreads-attribution'>{$this->widgetData['goodreadsAttribution']}</p>";
    }

    private function printCurrentlyReadingShelf() {
        $shelf = $this->shelves[$this->CURRENTLY_READING_SHELF_KEY];
        $this->printShelf($shelf, true);
    }

    private function printAdditionalShelf() {
        $shelf = $this->shelves[$this->ADDITIONAL_SHELF_KEY];
        $this->printShelf($shelf, false);
    }

    private function printShelf($shelf, $isCurrentlyReadingShelf) {
        if ($shelf === null) {
            $secondsLeftUntilRetry = get_option("gr_progress_cvdm_lastRetrievalErrorTime", 0) + $this->SECONDS_TO_WAIT_AFTER_FAILED_FETCH - time();
            $retryMessage = "";
            if ($secondsLeftUntilRetry > 0) {
                $retryMessage = " Will retry in " . intval(ceil($secondsLeftUntilRetry / 60)) . " min.";
            }
            echo "<p class='emptyShelfMessage'>Error retrieving data from Goodreads.$retryMessage</p>";
        } elseif ($shelf->isEmpty()) {
            $messageIfEmpty = $isCurrentlyReadingShelf ? $this->widgetData['emptyMessage'] : $this->widgetData['emptyMessageAdditional'];
            if (!empty($messageIfEmpty)) {
                echo "<p class='emptyShelfMessage'>$messageIfEmpty</p>";
            }
        } else {
            $class = $isCurrentlyReadingShelf ? 'currently-reading-shelf' : 'additional-shelf';
            echo "<ul class='bookshelf $class'>";
            $this->printBooksOnShelf($shelf);
            echo "</ul>";
        }
    }

    private function printBooksOnShelf($shelf) {
        foreach ($shelf->getBooks() as $book) {
            echo "<li class='book'>";
            echo "<div class='coverImage'><img alt='Book cover' src='{$book->getCoverURL()}' /></div>";
            echo "<div class='desc'>";
            echo "<p class='bookTitle'>{$book->getTitle()}</p>";
            echo "<p class='author'>{$book->getAuthor()}</p>";

            if ($book->hasProgress()) {
                if ($this->widgetData['useProgressBar']) {
                    $this->printProgressBar($book);
                } else {
                    $this->printProgressString($book);
                }
            }

            if ($book->hasComment()) {
                echo "<p class='bookComment'>{$book->getComment()}</p>";
            }

            echo "</div>";
            echo "</li>";
        }
    }

    private function printProgressBar($book) {
        $percent = $book->getProgressInPercent();
        $progressStatusUpdateTime = $book->getProgressStatusUpdateTime();
        $time = $this->widgetData['displayProgressUpdateTime'] ? " (" . $this->time_elapsed_string("@" . strval($progressStatusUpdateTime)) . ")" : "";
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
        $time = $this->widgetData['displayProgressUpdateTime'] ? " (" . $this->time_elapsed_string("@" . strval($progressStatusUpdateTime)) . ")" : "";
        echo "<p class='progress progress-string'>$percent&thinsp;%$time</p>";
    }

    private function time_elapsed_string($datetime) {
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
                return str_replace(['{num}', '{period}'], [$numberOfIntervals, $intervalName], $this->widgetData['intervalTemplate']);
            }
        }
    }

    private function printAdditionalShelfHeading() {
        $heading = $this->widgetData['additionalShelfHeading'];
        if (!empty($heading)) {
            echo "<h3 class='additional-shelf-heading'>{$this->widgetData['additionalShelfHeading']}</h3>";
        }
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
            <small>Goodreads attribution is required per the <a target="_blank" href="https://www.goodreads.com/api/terms">Goodreads API Terms of Service</a>. This field is intended to let you change/translate it, not remove it.</small>
        </p>

        <h3 style="margin-top: 2.5rem;">Goodreads configuration</h3>
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
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('apiKey'); ?>">
                Your Goodreads API key (get one <a target="_blank" href="https://www.goodreads.com/api/keys">here</a> - doesn't matter what application/company name you write):
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('apiKey'); ?>"
                name="<?php echo $this->widget->get_field_name('apiKey'); ?>"
                value="<?php echo esc_attr($instance['apiKey']); ?>"
                />
        </p>

        <h3 style="margin-top: 2.5rem;">"Currently reading" shelf</h3>
        <p>Reading progress will be displayed for books on this shelf.</p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('currentlyReadingShelfName'); ?>">
                Name of shelf on Goodreads:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('currentlyReadingShelfName'); ?>"
                name="<?php echo $this->widget->get_field_name('currentlyReadingShelfName'); ?>"
                value="<?php echo esc_attr($instance['currentlyReadingShelfName']); ?>"
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
            <label for="<?php echo $this->widget->get_field_id('displayReviewExcerptCurrentlyReadingShelf'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('displayReviewExcerptCurrentlyReadingShelf'); ?>"
                    name="<?php echo $this->widget->get_field_name('displayReviewExcerptCurrentlyReadingShelf'); ?>"
                    <?php echo $instance['displayReviewExcerptCurrentlyReadingShelf'] ? "checked" : ""; ?>
                    type="checkbox">
                Display the first line of your Goodreads review for each book<br/>
                <small>Intended for quick notes such as "reading this together with Bob" or "recommended by Alice" or whatever else strikes you fancy.</small>
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('sortByReadingProgress'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('sortByReadingProgress'); ?>"
                    name="<?php echo $this->widget->get_field_name('sortByReadingProgress'); ?>"
                    <?php echo $instance['sortByReadingProgress'] ? "checked" : ""; ?>
                    type="checkbox">
                Sort books on this shelf by reading progress instead of the options below (which will be used if reading progress is identical or missing).
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('currentlyReadingShelfSortBy'); ?>">
                Sort by:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->widget->get_field_id('currentlyReadingShelfSortBy'); ?>"
                name="<?php echo $this->widget->get_field_name('currentlyReadingShelfSortBy'); ?>"
                >
                    <?php $this->makeHTMLSelectOptions($this->SORT_BY_OPTIONS, $instance['currentlyReadingShelfSortBy']); ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('currentlyReadingShelfSortOrder'); ?>">
                Sort order:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->widget->get_field_id('currentlyReadingShelfSortOrder'); ?>"
                name="<?php echo $this->widget->get_field_name('currentlyReadingShelfSortOrder'); ?>"
                >
                    <?php $this->makeHTMLSelectOptions($this->SORT_ORDER_OPTIONS, $instance['currentlyReadingShelfSortOrder']); ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('maxBooksCurrentlyReadingShelf'); ?>">
                Display at most
                <input
                    id="<?php echo $this->widget->get_field_id('maxBooksCurrentlyReadingShelf'); ?>"
                    name="<?php echo $this->widget->get_field_name('maxBooksCurrentlyReadingShelf'); ?>"
                    step="1"
                    min="1"
                    value="<?php echo $instance['maxBooksCurrentlyReadingShelf']; ?>"
                    style="width: 5em;"
                    type="number">
                books from this shelf
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('useProgressBar'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('useProgressBar'); ?>"
                    name="<?php echo $this->widget->get_field_name('useProgressBar'); ?>"
                    <?php echo $instance['useProgressBar'] ? "checked" : ""; ?>
                    value="useProgressBar"
                    type="radio">
                Display progress bar
            </label>
            <br />
            <label for="<?php echo $this->widget->get_field_id('useProgressText'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('useProgressText'); ?>"
                    name="<?php echo $this->widget->get_field_name('useProgressBar'); ?>"
                    <?php echo!$instance['useProgressBar'] ? "checked" : ""; ?>
                    value="useProgressText"
                    type="radio">
                Display progress as text (no progress bar)
            </label>
            <br />
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
            />
        <table>
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

        <h3 style="margin-top: 2.5rem;">Additional shelf</h3>
        <p>Here you can choose to display e.g. books you intend to read soon, or whatever you want. Feel free to create a new shelf for this on Goodreads if you want to control which books appear here.</p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('additionalShelfName'); ?>">
                Name of shelf on Goodreads (leave blank to disable):
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('additionalShelfName'); ?>"
                name="<?php echo $this->widget->get_field_name('additionalShelfName'); ?>"
                value="<?php echo esc_attr($instance['additionalShelfName']); ?>"
                />
            <br />
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('additionalShelfHeading'); ?>">
                Heading to display above additional shelf:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('additionalShelfHeading'); ?>"
                name="<?php echo $this->widget->get_field_name('additionalShelfHeading'); ?>"
                value="<?php echo esc_attr($instance['additionalShelfHeading']); ?>"
                />
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('emptyMessageAdditional'); ?>">
                Message to display when no books are found:
            </label>
            <input
                class="widefat"
                type="text"
                id="<?php echo $this->widget->get_field_id('emptyMessageAdditional'); ?>"
                name="<?php echo $this->widget->get_field_name('emptyMessageAdditional'); ?>"
                value="<?php echo esc_attr($instance['emptyMessageAdditional']); ?>"
                />
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('displayReviewExcerptAdditionalShelf'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('displayReviewExcerptAdditionalShelf'); ?>"
                    name="<?php echo $this->widget->get_field_name('displayReviewExcerptAdditionalShelf'); ?>"
                    <?php echo $instance['displayReviewExcerptAdditionalShelf'] ? "checked" : ""; ?>
                    type="checkbox">
                Display the first line of your Goodreads review for each book<br/>
                <small>Intended for quick notes such as "reading this together with Bob" or "recommended by Alice" or whatever else strikes you fancy.</small>
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('additionalShelfSortBy'); ?>">
                Sort by:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->widget->get_field_id('additionalShelfSortBy'); ?>"
                name="<?php echo $this->widget->get_field_name('additionalShelfSortBy'); ?>"
                >
                    <?php $this->makeHTMLSelectOptions($this->SORT_BY_OPTIONS, $instance['additionalShelfSortBy']); ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('additionalShelfSortOrder'); ?>">
                Sort order:
            </label>
            <select
                class="widefat"
                id="<?php echo $this->widget->get_field_id('additionalShelfSortOrder'); ?>"
                name="<?php echo $this->widget->get_field_name('additionalShelfSortOrder'); ?>"
                >
                    <?php $this->makeHTMLSelectOptions($this->SORT_ORDER_OPTIONS, $instance['additionalShelfSortOrder']); ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('maxBooksAdditionalShelf'); ?>">
                Display at most
                <input
                    id="<?php echo $this->widget->get_field_id('maxBooksAdditionalShelf'); ?>"
                    name="<?php echo $this->widget->get_field_name('maxBooksAdditionalShelf'); ?>"
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
            <label for="<?php echo $this->widget->get_field_id('progressCacheHours'); ?>">
                Update progress every 
                <input
                    id="<?php echo $this->widget->get_field_id('progressCacheHours'); ?>"
                    name="<?php echo $this->widget->get_field_name('progressCacheHours'); ?>"
                    step="1"
                    min="0"
                    value="<?php echo $instance['progressCacheHours']; ?>"
                    style="width: 5em;"
                    type="number">
                hours (default 24)
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('bookCacheHours'); ?>">
                Update book lists every 
                <input
                    id="<?php echo $this->widget->get_field_id('bookCacheHours'); ?>"
                    name="<?php echo $this->widget->get_field_name('bookCacheHours'); ?>"
                    step="1"
                    min="0"
                    value="<?php echo $instance['bookCacheHours']; ?>"
                    style="width: 5em;"
                    type="number">
                hours (default 24)
            </label>
        </p>
        <p>
            <label for="<?php echo $this->widget->get_field_id('deleteCacheOnSave'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('deleteCacheOnSave'); ?>"
                    name="<?php echo $this->widget->get_field_name('regenerateCacheOnSave'); ?>"
                    <?php echo!$instance['regenerateCacheOnSave'] ? "checked" : ""; ?>
                    value="noRegenerateCache"
                    type="radio">
                Delete cache when saving widget (first visitor will regenerate)
            </label>
            <br />
            <label for="<?php echo $this->widget->get_field_id('regenerateCacheOnSave'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('regenerateCacheOnSave'); ?>"
                    name="<?php echo $this->widget->get_field_name('regenerateCacheOnSave'); ?>"
                    <?php echo $instance['regenerateCacheOnSave'] ? "checked" : ""; ?>
                    value="regenerateCache"
                    type="radio">
                Regenerate cache when saving widget (visitors will not notice any slowdown)
            </label>
            <br />
            <small>Please be patient when cache is regenerating.</small>
        </p>
        <p>
            Cover URLs take several seconds to fetch from Goodreads and are cached separately. If you experience trouble with cover images, you can empty the cache by ticking the box below.<br />
            <label for="<?php echo $this->widget->get_field_id('deleteCoverURLCacheOnSave'); ?>">
                <input
                    id="<?php echo $this->widget->get_field_id('deleteCoverURLCacheOnSave'); ?>"
                    name="<?php echo $this->widget->get_field_name('deleteCoverURLCacheOnSave'); ?>"
                    <?php // Don't set "checked" attribute - this should be reset to unchecked/false on each save      ?>
                    type="checkbox">
                Delete the cover URL cache the next time you save these settings.
            </label>
        </p>
        <p style="text-align: center; color: #31708f; background-color: #d9edf7; border: 1px solid #bce8f1; border-radius: 4px; padding: 15px;">If you have selected "Regenerate cache when saving widget" above, please be patient while saving. If it takes more than 30 seconds, something probably went wrong.</p>

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
        $goodreadsAttribution = trim(htmlspecialchars($new_instance['goodreadsAttribution']));
        $instance['goodreadsAttribution'] = !empty($goodreadsAttribution) ? $goodreadsAttribution : $this->DEFAULT_SETTINGS['goodreadsAttribution'];

        preg_match("/\d+/", $new_instance['userid'], $matches_userid);
        $instance['userid'] = !empty($matches_userid) ? $matches_userid[0] : "";

        $instance['apiKey'] = trim(htmlspecialchars($new_instance['apiKey']));
        $instance['currentlyReadingShelfName'] = trim(htmlspecialchars($new_instance['currentlyReadingShelfName']));
        $instance['emptyMessage'] = trim(htmlspecialchars($new_instance['emptyMessage']));
        $instance['displayReviewExcerptCurrentlyReadingShelf'] = isset($new_instance['displayReviewExcerptCurrentlyReadingShelf']) ? true : false;
        $instance['sortByReadingProgress'] = isset($new_instance['sortByReadingProgress']) ? true : false;
        $instance['displayProgressUpdateTime'] = isset($new_instance['displayProgressUpdateTime']) ? true : false;
        $instance['intervalTemplate'] = trim(htmlspecialchars($new_instance['intervalTemplate']));

        foreach (['intervalSingular', 'intervalPlural'] as $intervalPluralSingular) {
            $instance[$intervalPluralSingular] = $new_instance[$intervalPluralSingular];
            foreach ($instance[$intervalPluralSingular] as $i => &$str) {
                $str = trim(htmlspecialchars($str));
                if (empty($str)) {
                    $str = $this->DEFAULT_SETTINGS[$intervalPluralSingular][$i];
                }
            }
        }

        $instance['useProgressBar'] = $new_instance['useProgressBar'] == 'useProgressBar' ? true : false;
        $instance['currentlyReadingShelfSortBy'] = array_key_exists($new_instance['currentlyReadingShelfSortBy'], $this->SORT_BY_OPTIONS) ? $new_instance['currentlyReadingShelfSortBy'] : $this->DEFAULT_SETTINGS['currentlyReadingShelfSortBy'];
        $instance['currentlyReadingShelfSortOrder'] = array_key_exists($new_instance['currentlyReadingShelfSortOrder'], $this->SORT_ORDER_OPTIONS) ? $new_instance['currentlyReadingShelfSortOrder'] : $this->DEFAULT_SETTINGS['currentlyReadingShelfSortOrder'];
        $instance['maxBooksCurrentlyReadingShelf'] = preg_match("/\d+/", $new_instance['maxBooksCurrentlyReadingShelf']) ? intval($new_instance['maxBooksCurrentlyReadingShelf']) : 10;
        $instance['additionalShelfName'] = trim(htmlspecialchars($new_instance['additionalShelfName']));
        $instance['additionalShelfHeading'] = trim(htmlspecialchars($new_instance['additionalShelfHeading']));
        $instance['emptyMessageAdditional'] = trim(htmlspecialchars($new_instance['emptyMessageAdditional']));
        $instance['displayReviewExcerptAdditionalShelf'] = isset($new_instance['displayReviewExcerptAdditionalShelf']) ? true : false;
        $instance['additionalShelfSortBy'] = array_key_exists($new_instance['additionalShelfSortBy'], $this->SORT_BY_OPTIONS) ? $new_instance['additionalShelfSortBy'] : $this->DEFAULT_SETTINGS['additionalShelfSortBy'];
        $instance['additionalShelfSortOrder'] = array_key_exists($new_instance['additionalShelfSortOrder'], $this->SORT_ORDER_OPTIONS) ? $new_instance['additionalShelfSortOrder'] : $this->DEFAULT_SETTINGS['additionalShelfSortOrder'];
        $instance['maxBooksAdditionalShelf'] = preg_match("/\d+/", $new_instance['maxBooksAdditionalShelf']) ? intval($new_instance['maxBooksAdditionalShelf']) : 10;
        $instance['progressCacheHours'] = preg_match("/\d+/", $new_instance['progressCacheHours']) ? intval($new_instance['progressCacheHours']) : 24;
        $instance['bookCacheHours'] = preg_match("/\d+/", $new_instance['bookCacheHours']) ? intval($new_instance['bookCacheHours']) : 24;
        $instance['regenerateCacheOnSave'] = $new_instance['regenerateCacheOnSave'] == 'regenerateCache' ? true : false;

        $this->widgetData = $instance;

        // FIXME: Side effects - factor out
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
