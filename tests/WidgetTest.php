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

    public function testdisplayNoCommentsUsingDefaultSettings() {
        $html = $this->getWidgetHTML();
        $this->assertNoBooksHaveComment($html);
    }

    public function testSetting_displayReviewExcerptCurrentlyReadingShelf() {
        $html = $this->getWidgetHTML(['displayReviewExcerptCurrentlyReadingShelf' => true]);
        // primary shelf
        $this->assertBookHasNoComment("The Lord of the Rings", $html);
        $this->assertBookHasComment("A Game of Thrones", "First line.", $html);
        $this->assertBookHasComment("The Chronicles of Narnia", "Only line, with", $html);
        $this->assertBookHasNoComment("Harry Potter and the Sorcerer", $html);
        // secondary shelf
        $this->assertBookHasNoComment("The Name of the Wind", $html);
        $this->assertBookHasNoComment("The Eye of the World", $html);
        $this->assertBookHasNoComment("His Dark Materials", $html);
        $this->assertBookHasNoComment("The Lightning Thief", $html);
        $this->assertBookHasNoComment("Mistborn", $html);
        $this->assertBookHasNoComment("City of Bones", $html);
        $this->assertBookHasNoComment("The Way of Kings", $html);
        $this->assertBookHasNoComment("The Gunslinger", $html);
        $this->assertBookHasNoComment("The Color of Magic", $html);
        $this->assertBookHasNoComment("Artemis Fowl", $html);
    }

    public function testSetting_displayReviewExcerptAdditionalShelf() {
        $html = $this->getWidgetHTML(['displayReviewExcerptAdditionalShelf' => true]);
        // primary shelf
        $this->assertBookHasNoComment("The Lord of the Rings", $html);
        $this->assertBookHasNoComment("A Game of Thrones", $html);
        $this->assertBookHasNoComment("The Chronicles of Narnia", $html);
        $this->assertBookHasNoComment("Harry Potter and the Sorcerer", $html);
        // secondary shelf
        $this->assertBookHasComment("The Name of the Wind", "Sounds interesting!", $html);
        $this->assertBookHasComment("The Eye of the World", "Recommended by John.", $html);
        $this->assertBookHasNoComment("His Dark Materials", $html);
        $this->assertBookHasNoComment("The Lightning Thief", $html);
        $this->assertBookHasNoComment("Mistborn", $html);
        $this->assertBookHasNoComment("City of Bones", $html);
        $this->assertBookHasNoComment("The Way of Kings", $html);
        $this->assertBookHasNoComment("The Gunslinger", $html);
        $this->assertBookHasNoComment("The Color of Magic", $html);
        $this->assertBookHasNoComment("Artemis Fowl", $html);
    }

    public function testAllBooksHaveCoverImage() {
        $html = $this->getWidgetHTML();
        $dom = str_get_html($html);
        foreach ($dom->find(".book") as $book) {
            $img = $book->find("img", 0);
            $bookTitle = $book->find(".bookTitle", 0)->plaintext;
            $this->assertNotEmpty($img->src, "Missing cover on book $bookTitle");
        }
    }

    public function testAllBooksHaveCoverImage_bugBecauseBookOrderingIsIncorrectWhenGettingHTMLShelf() {
        $html = $this->getWidgetHTML(['userid' => 17334072, 'additionalShelfSortBy' => 'position', 'additionalShelfSortOrder' => 'a',]);
        $dom = str_get_html($html);
        foreach ($dom->find(".book") as $book) {
            $img = $book->find("img", 0);
            $bookTitle = $book->find(".bookTitle", 0)->plaintext;
            $this->assertNotEmpty($img->src, "Missing cover on book $bookTitle");
        }
    }

    public function testProgressDefaultSettings() {
        $html = $this->getWidgetHTML();
        // primary shelf
        $this->assertBookHasProgress("The Lord of the Rings", "20", $html);
        $this->assertBookHasProgress("A Game of Thrones", "20", $html);
        $this->assertBookHasNoProgress("The Chronicles of Narnia", $html);
        $this->assertBookHasProgress("Harry Potter and the Sorcerer", "30", $html);
        // secondary shelf
        $this->assertBookHasNoProgress("The Name of the Wind", $html);
        $this->assertBookHasNoProgress("The Eye of the World", $html);
        $this->assertBookHasNoProgress("His Dark Materials", $html);
        $this->assertBookHasNoProgress("The Lightning Thief", $html);
        $this->assertBookHasNoProgress("Mistborn", $html);
        $this->assertBookHasNoProgress("City of Bones", $html);
        $this->assertBookHasNoProgress("The Way of Kings", $html);
        $this->assertBookHasNoProgress("The Gunslinger", $html);
        $this->assertBookHasNoProgress("The Color of Magic", $html);
        $this->assertBookHasNoProgress("Artemis Fowl", $html);
    }

}
