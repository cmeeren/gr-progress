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

    /**
     * Returns the HTML used for rendering the widget.
     * @param array $overrideSettings Key-value pairs of settings to override
     * @return string
     */
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

    /**
     * Asserts that input string is valid HTML.
     * @param string $html
     */
    public function assertIsValidHTML($html) {
        $validator = new HTML5Validate();
        $result = $validator->Assert($html);
        $this->assertTrue($result, $validator->message);
    }

    /**
     * Asserts that the book titles on the primary shelf contains the substrings
     * given in $bookTitles, in order.
     * @param string[] $bookTitles
     * @param string $html
     */
    public function assertOrderedBookTitlesOnPrimaryShelfContains($bookTitles, $html) {
        $dom = str_get_html($html);
        $primaryShelf = $dom->find('.currently-reading-shelf', 0);
        $this->assertOrderedBookTitlesContains($bookTitles, $primaryShelf);
    }

    /**
     * Asserts that the book titles on the secondary shelf contains the
     * substrings given in $bookTitles, in order.
     * @param string[] $bookTitles
     * @param string $html
     */
    public function assertOrderedBookTitlesOnSecondaryShelfContains($bookTitles, $html) {
        $dom = str_get_html($html);
        $secondaryShelf = $dom->find('.additional-shelf', 0);
        $this->assertOrderedBookTitlesContains($bookTitles, $secondaryShelf);
    }

    /**
     * Asserts that the book titles in the given dom element contains the
     * substrings given in $bookTitlesExpected, in order.
     * @param string[] $bookTitlesExpected
     * @param simple_html_dom_node $domElement
     */
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
    
    /**
     * Asserts that there is no primary shelf in the input string.
     * @param string $html
     */
    public function assertNoPrimaryShelf($html) {
        $dom = str_get_html($html);
        $this->assertCount(0, $dom->find('.currently-reading-shelf'), "Found primary shelf but expected none");
    }
    
    /**
     * Asserts that there is no secondary shelf in the input string.
     * @param string $html
     */
    public function assertNoSecondaryShelf($html) {
        $dom = str_get_html($html);
        $this->assertCount(0, $dom->find('.additional-shelf'), "Found secondary shelf but expected none");
    }
    
    /**
     * Asserts that the primary shelf contains the books for the default settings.
     * @param string $html
     */
    public function assertDefaultBooksOnPrimaryShelf($html) {
        $this->assertOrderedBookTitlesOnPrimaryShelfContains(["The Lord of the Rings", "A Game of Thrones", "The Chronicles of Narnia"], $html);
    }
    
    /**
     * Asserts that the secondary shelf contains the books for the default settings.
     * @param string $html
     */
    public function assertDefaultBooksOnSecondaryShelf($html) {
        $this->assertOrderedBookTitlesOnSecondaryShelfContains(["The Name of the Wind", "The Eye of the World", "His Dark Materials"], $html);
    }

}
