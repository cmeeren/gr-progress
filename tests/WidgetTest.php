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

    public function testValidHTML() {
        $html = $this->getWidgetHTML();
        $this->assertIsValidHTML($html);
    }

    public function testTitle() {
        $html = $this->getWidgetHTML(['title' => 'CUSTOM_TITLE_FOOBAR']);
        $this->assertRegExp("/CUSTOM_TITLE_FOOBAR/", $html);
    }

    public function testGoodreadsAttribution() {
        $html = $this->getWidgetHTML(['goodreadsAttribution' => 'GOODREADS_ATTRIBUTION_FOOBAR']);
        $this->assertRegExp("/GOODREADS_ATTRIBUTION_FOOBAR/", $html);
    }

    public function testErrorMessage() {
        GoodreadsFetcher::$test_fail = true;
        $html = $this->getWidgetHTML();
        $this->assertRegExp("/Error retrieving data from Goodreads\. Will retry in/", $html);
    }

    public function testBooksDefault() {
        $html = $this->getWidgetHTML();
        $this->assertOrderedBookTitlesOnPrimaryShelfContains(["The Lord of the Rings", "A Game of Thrones", "The Chronicles of Narnia"], $html);
    }

}
