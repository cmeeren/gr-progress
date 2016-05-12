<?php

require_once('HTML5Validate.php');
require_once('simple_html_dom.php');

class GR_Progress_UnitTestCase extends WP_UnitTestCase {

    private $DEFAULT_SETTINGS = [
        'title' => 'Currently reading',
        'goodreadsAttribution' => 'Data from Goodreads',
        'userid' => '55769144',
        'apiKey' => 'HAZB53duIFj5Ur87DiOW7Q',
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
    private $DEFAULT_ARGS = [
        'before_widget' => '',
        'after_widget' => '',
        'before_title' => '<h2>',
        'after_title' => '</h2>',
    ];

    public function getWidgetHTML($overrideSettings = []) {
        $settings = $this->DEFAULT_SETTINGS;
        foreach ($overrideSettings as $k => $v) {
            $settings[$k] = $v;
        }

        $widget = new gr_progress_cvdm_widget();

        ob_start();
        $widget->widget($this->DEFAULT_ARGS, $settings);
        $html = ob_get_clean();
        return $html;
    }

    public function assertIsValidHTML($html) {
        $validator = new HTML5Validate();
        $result = $validator->Assert($html);
        $this->assertTrue($result, $validator->message);
    }

    public function assertOrderedBookTitlesOnPrimaryShelfContains($bookTitles, $html) {
        $dom = str_get_html($html);
        $primaryShelf = $dom->find('.currently-reading-shelf', 0);
        $this->assertOrderedBookTitlesContains($bookTitles, $primaryShelf);
    }

    public function assertOrderedBookTitlesOnSecondaryShelfContains($bookTitles, $html) {
        $dom = str_get_html($html);
        $secondaryShelf = $dom->find('.additional-shelf', 0);
        $this->assertOrderedBookTitlesContains($bookTitles, $secondaryShelf);
    }

    private function assertOrderedBookTitlesContains($bookTitlesExpected, $domElement) {
        $bookTitlesActual = [];
        foreach ($domElement->find(".bookTitle") as $bookTitleElement) {
            $bookTitlesActual[] = $bookTitleElement->plaintext;
        }
        
        $this->assertCount(count($bookTitlesExpected), $bookTitlesActual, "Shelf does not contain expected number of books");
        
        for ($i = 0; $i < count($bookTitlesExpected); $i++) {
            $this->assertContains($bookTitlesExpected[$i], $bookTitlesActual[$i], "Wrong book on index " . $i);
        }
    }

}
