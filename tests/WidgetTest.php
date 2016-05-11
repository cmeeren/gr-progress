<?php

require 'HTML5Validate.php';

define("GR_PROGRESS_TESTING", true);

class WidgetTest extends WP_UnitTestCase {
    private $widget;
    
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
        'before_title' => '',
        'after_title' => '',
    ];
    
    public function setUp() {
        $this->widget = new gr_progress_cvdm_widget();
    }
    
    public function testValidHTML()
    {
        ob_start();
        $this->widget->widget($this->DEFAULT_ARGS, $this->DEFAULT_SETTINGS);
        $html = ob_get_clean();
        
        $validator=new HTML5Validate();
        $result=$validator->Assert($html);
        $this->assertTrue($result, $validator->message);
    }
}