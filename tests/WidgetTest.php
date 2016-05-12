<?php

require_once('GoodreadsFetcher.php');
require_once('HTML5Validate.php');
require_once('GR_Progress_UnitTestCase.php');

class WidgetTest extends GR_Progress_UnitTestCase {

    public function setUp() {
        GoodreadsFetcher::$test_local = true;
        GoodreadsFetcher::$test_fail = false;
        delete_option("gr_progress_cvdm_shelves");
        delete_option("gr_progress_cvdm_lastRetrievalErrorTime");
        delete_option("gr_progress_cvdm_coverURLs");
    }

    public function testWidgetOutputIsValidHTML() {
        $html = $this->getWidgetHTML();
        $this->assertIsValidHTML($html);
    }

    public function testCorrectBooksUsingDefaultSettings() {
        $html = $this->getWidgetHTML();
        $this->assertDefaultBooksOnPrimaryShelf($html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSetting_title() {
        $html = $this->getWidgetHTML(['title' => 'CUSTOM_TITLE_FOOBAR']);
        $this->assertContains("CUSTOM_TITLE_FOOBAR", $html);
    }

    public function testSetting_goodreadsAttribution() {
        $html = $this->getWidgetHTML(['goodreadsAttribution' => 'GOODREADS_ATTRIBUTION_FOOBAR']);
        $this->assertContains("GOODREADS_ATTRIBUTION_FOOBAR", $html);
    }

    public function testErrorMessageOnFailedFetch() {
        GoodreadsFetcher::$test_fail = true;
        $html = $this->getWidgetHTML();
        $this->assertContains("Error retrieving data from Goodreads. Will retry in", $html);
    }

    public function testUseCacheOnFailedFetch() {
        $this->getWidgetHTML();  // saves books to cache
        GoodreadsFetcher::$test_fail = true;
        $html = $this->getWidgetHTML();  // fetch fails, should use cached data
        $this->assertNotContains("Error retrieving data", $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSetting_currentlyReadingShelfNameAndEmptyMessage() {
        $html = $this->getWidgetHTML(['currentlyReadingShelfName' => 'empty-shelf', 'emptyMessage' => 'CUSTOM_EMPTY_MESSAGE_PRIMARY_SHELF']);
        $this->assertContains("CUSTOM_EMPTY_MESSAGE_PRIMARY_SHELF", $html);
        $this->assertNoPrimaryShelf($html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSetting_additionalShelfNameAndEmptyMessage() {
        $html = $this->getWidgetHTML(['additionalShelfName' => 'empty-shelf', 'emptyMessageAdditional' => 'CUSTOM_EMPTY_MESSAGE_SECONDARY_SHELF']);
        $this->assertContains("CUSTOM_EMPTY_MESSAGE_SECONDARY_SHELF", $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
        $this->assertNoSecondaryShelf($html);
    }
    
    public function testSetting_displayReviewExcerptCurrentlyReadingShelf() {
        $html = $this->getWidgetHTML(['displayReviewExcerptCurrentlyReadingShelf' => true]);
        $this->assertBookHasNoComment("The Lord of the Rings", $html);
        $this->assertBookHasComment("A Game of Thrones", "First line.", $html);
        
    }

}
