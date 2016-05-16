<?php

namespace relativisticramblings\gr_progress;

require_once('GR_Progress_UnitTestCase.php');

class WidgetTest extends GR_Progress_UnitTestCase {

    public function setUp() {
        GoodreadsFetcher::$test_local = true;
        GoodreadsFetcher::$test_fail = false;
        Shelf::$test_disableCoverFetching = false;
        delete_option("gr_progress_cvdm_shelves");
        delete_option("gr_progress_cvdm_lastRetrievalErrorTime");
        delete_option("gr_progress_cvdm_coverURLs");
    }

    public function testWidgetOutputIsValidHTML() {
        $html = $this->getWidgetHTML();
        $this->assertIsValidHTML($html);
    }

    public function testWidgetFormIsValidHTML() {
        $html = $this->getWidgetForm();
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

    public function testSetting_additionalShelfHeading() {
        $html = $this->getWidgetHTML(['title' => 'CUSTOM_SECONDARY_TITLE_FOOBAR']);
        $this->assertContains("CUSTOM_SECONDARY_TITLE_FOOBAR", $html);
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
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testUseCachedCovers() {
        $this->getWidgetHTML();  // saves books and covers to cache
        Shelf::$test_disableCoverFetching = true;
        // delete cache so shelves will be rebuilt.
        delete_option("gr_progress_cvdm_shelves");
        // rebuild shelves - since cover fetching is disabled,
        // using cached covers is the only option
        $html = $this->getWidgetHTML();
        $this->assertAllBooksHaveCoverImage($html);
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
        $this->assertBookHasComment("A Game of Thrones", "First line. &lt;3", $html);
        $this->assertBookHasComment("The Chronicles of Narnia", "Only line, with &lt;3 and <a", $html);
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
        $this->assertBookHasComment("The Name of the Wind", "Sounds interesting! &lt;3", $html);
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
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testProgressDefaultSettings() {
        $html = $this->getWidgetHTML();
        // primary shelf
        $this->assertBookProgressContains("The Lord of the Rings", "20", $html);
        $this->assertBookProgressContains("A Game of Thrones", "20", $html);
        $this->assertBookHasNoProgress("The Chronicles of Narnia", $html);
        $this->assertBookProgressContains("Harry Potter and the Sorcerer", "30", $html);
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

    /**
     * Tests that "Artemis Fowl" on the to-read shelf shows its progress
     * when to-read is selected as the primary shelf
     */
    public function testProgressInvertedShelves() {
        $html = $this->getWidgetHTML(['currentlyReadingShelfName' => 'to-read', 'additionalShelfName' => 'currently-reading']);
        // primary shelf
        $this->assertBookHasNoProgress("The Name of the Wind", $html);
        $this->assertBookHasNoProgress("The Eye of the World", $html);
        $this->assertBookHasNoProgress("His Dark Materials", $html);
        $this->assertBookHasNoProgress("The Lightning Thief", $html);
        $this->assertBookHasNoProgress("Mistborn", $html);
        $this->assertBookHasNoProgress("City of Bones", $html);
        $this->assertBookHasNoProgress("The Way of Kings", $html);
        $this->assertBookHasNoProgress("The Gunslinger", $html);
        $this->assertBookHasNoProgress("The Color of Magic", $html);
        $this->assertBookProgressContains("Artemis Fowl", "10", $html);
        // secondary shelf
        $this->assertBookHasNoProgress("The Lord of the Rings", $html);
        $this->assertBookHasNoProgress("A Game of Thrones", $html);
        $this->assertBookHasNoProgress("The Chronicles of Narnia", $html);
        $this->assertBookHasNoProgress("Harry Potter and the Sorcerer", $html);
    }

    public function testSortPrimary_author_a() {
        $books = [
            'The Chronicles of Narnia',
            'A Game of Thrones',
            'Harry Potter and the Sorcerer',
            'The Lord of the Rings',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'author', 'currentlyReadingShelfSortOrder' => 'a']);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSortPrimary_author_d() {
        $books = [
            'The Lord of the Rings',
            'Harry Potter and the Sorcerer',
            'A Game of Thrones',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'author', 'currentlyReadingShelfSortOrder' => 'd']);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSortPrimary_title_a() {
        $books = [
            'The Chronicles of Narnia',
            'A Game of Thrones',
            'Harry Potter and the Sorcerer',
            'The Lord of the Rings',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'title', 'currentlyReadingShelfSortOrder' => 'a']);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSortPrimary_title_d() {
        $books = [
            'The Lord of the Rings',
            'Harry Potter and the Sorcerer',
            'A Game of Thrones',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'title', 'currentlyReadingShelfSortOrder' => 'd']);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSortSecondary_position_a() {
        $books = [
            "The Name of the Wind",
            "The Eye of the World",
            "His Dark Materials",
            "The Lightning Thief",
            "Mistborn",
            "City of Bones",
            "The Way of Kings",
            "The Gunslinger",
            "The Color of Magic",
            "Artemis Fowl",
        ];
        $html = $this->getWidgetHTML(['additionalShelfSortBy' => 'position', 'additionalShelfSortOrder' => 'a']);
        $this->assertOrderedBookTitlesOnSecondaryShelfContains($books, $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
    }

    public function testSortSecondary_position_d() {
        $books = [
            "Artemis Fowl",
            "The Color of Magic",
            "The Gunslinger",
            "The Way of Kings",
            "City of Bones",
            "Mistborn",
            "The Lightning Thief",
            "His Dark Materials",
            "The Eye of the World",
            "The Name of the Wind",
        ];
        $html = $this->getWidgetHTML(['additionalShelfSortBy' => 'position', 'additionalShelfSortOrder' => 'd']);
        $this->assertOrderedBookTitlesOnSecondaryShelfContains($books, $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
    }

    public function testSSortSecondary_title_a() {
        $books = [
            "Artemis Fowl",
            "City of Bones",
            "The Color of Magic",
            "The Eye of the World",
            "The Gunslinger",
            "His Dark Materials",
            "The Lightning Thief",
            "Mistborn",
            "The Name of the Wind",
            "The Way of Kings",
        ];
        $html = $this->getWidgetHTML(['additionalShelfSortBy' => 'title', 'additionalShelfSortOrder' => 'a']);
        $this->assertOrderedBookTitlesOnSecondaryShelfContains($books, $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
    }

    public function testSortSecondary_title_d() {
        $books = [
            "The Way of Kings",
            "The Name of the Wind",
            "Mistborn",
            "The Lightning Thief",
            "His Dark Materials",
            "The Gunslinger",
            "The Eye of the World",
            "The Color of Magic",
            "City of Bones",
            "Artemis Fowl",
        ];
        $html = $this->getWidgetHTML(['additionalShelfSortBy' => 'title', 'additionalShelfSortOrder' => 'd']);
        $this->assertOrderedBookTitlesOnSecondaryShelfContains($books, $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
    }

    public function testSortPrimary_author_a_maxBooks() {
        $books = [
            'The Chronicles of Narnia',
            'A Game of Thrones',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'author', 'currentlyReadingShelfSortOrder' => 'a', 'maxBooksCurrentlyReadingShelf' => 2]);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortPrimary_author_d_maxBooks() {
        $books = [
            'The Lord of the Rings',
            'Harry Potter and the Sorcerer',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'author', 'currentlyReadingShelfSortOrder' => 'd', 'maxBooksCurrentlyReadingShelf' => 2]);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortPrimary_title_a_maxBooks() {
        $books = [
            'The Chronicles of Narnia',
            'A Game of Thrones',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'title', 'currentlyReadingShelfSortOrder' => 'a', 'maxBooksCurrentlyReadingShelf' => 2]);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortPrimary_title_d_maxBooks() {
        $books = [
            'The Lord of the Rings',
            'Harry Potter and the Sorcerer',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'title', 'currentlyReadingShelfSortOrder' => 'd', 'maxBooksCurrentlyReadingShelf' => 2]);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortSecondary_position_a_maxBooks() {
        $books = [
            "The Name of the Wind",
            "The Eye of the World",
        ];
        $html = $this->getWidgetHTML(['additionalShelfSortBy' => 'position', 'additionalShelfSortOrder' => 'a', 'maxBooksAdditionalShelf' => 2]);
        $this->assertOrderedBookTitlesOnSecondaryShelfContains($books, $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortSecondary_position_d_maxBooks() {
        $books = [
            "Artemis Fowl",
            "The Color of Magic",
        ];
        $html = $this->getWidgetHTML(['additionalShelfSortBy' => 'position', 'additionalShelfSortOrder' => 'd', 'maxBooksAdditionalShelf' => 2]);
        $this->assertOrderedBookTitlesOnSecondaryShelfContains($books, $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSSortSecondary_title_a_maxBooks() {
        $books = [
            "Artemis Fowl",
            "City of Bones",
        ];
        $html = $this->getWidgetHTML(['additionalShelfSortBy' => 'title', 'additionalShelfSortOrder' => 'a', 'maxBooksAdditionalShelf' => 2]);
        $this->assertOrderedBookTitlesOnSecondaryShelfContains($books, $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortSecondary_title_d_maxBooks() {
        $books = [
            "The Way of Kings",
            "The Name of the Wind",
        ];
        $html = $this->getWidgetHTML(['additionalShelfSortBy' => 'title', 'additionalShelfSortOrder' => 'd', 'maxBooksAdditionalShelf' => 2]);
        $this->assertOrderedBookTitlesOnSecondaryShelfContains($books, $html);
        $this->assertDefaultBooksOnPrimaryShelf($html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortByProgress_title_a() {
        $books = [
            'Harry Potter and the Sorcerer',
            'A Game of Thrones',
            'The Lord of the Rings',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'title', 'currentlyReadingShelfSortOrder' => 'a', 'sortByReadingProgress' => true]);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSortByProgress_title_d() {
        $books = [
            'Harry Potter and the Sorcerer',
            'The Lord of the Rings',
            'A Game of Thrones',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'title', 'currentlyReadingShelfSortOrder' => 'd', 'sortByReadingProgress' => true]);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSortByProgress_author_a() {
        $books = [
            'Harry Potter and the Sorcerer',
            'A Game of Thrones',
            'The Lord of the Rings',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'author', 'currentlyReadingShelfSortOrder' => 'a', 'sortByReadingProgress' => true]);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testSortByProgress_author_d() {
        $books = [
            'Harry Potter and the Sorcerer',
            'The Lord of the Rings',
            'A Game of Thrones',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'author', 'currentlyReadingShelfSortOrder' => 'd', 'sortByReadingProgress' => true]);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains($books, $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    /**
     * Tests that the books with most progress are returned even when they would not
     * normally appear due to being excluded by the queried per_page and sort order
     */
    public function testSortByProgress_maxBooks() {
        $html = $this->getWidgetHTML(['currentlyReadingShelfSortBy' => 'title', 'currentlyReadingShelfSortOrder' => 'a', 'sortByReadingProgress' => true, 'maxBooksCurrentlyReadingShelf' => 1]);
        $this->assertOrderedBookTitlesOnPrimaryShelfContains(['Harry Potter and the Sorcerer'], $html);
        $this->assertDefaultBooksOnSecondaryShelf($html);
    }

    public function testProgressUpdateTimeEnabled() {
        $html = $this->getWidgetHTML([
            'displayProgressUpdateTime' => true,
            'intervalTemplate' => '{num} {period} since update',
            'intervalSingular' => ['foobars', 'foobars', 'foobars', 'foobars', 'foobars', 'foobars', 'foobars'],
            'intervalPlural' => ['foobars', 'foobars', 'foobars', 'foobars', 'foobars', 'foobars', 'foobars']
        ]);
        $this->assertBookProgressContains("The Lord of the Rings", "foobars since update", $html);
        $this->assertBookProgressContains("A Game of Thrones", "foobars since update", $html);
        $this->assertBookHasNoProgress("The Chronicles of Narnia", $html);
        $this->assertBookProgressContains("Harry Potter and the Sorcerer", "foobars since update", $html);
    }

    public function testProgressUpdateTimeDisabled() {
        $html = $this->getWidgetHTML([
            'displayProgressUpdateTime' => false,
            'intervalTemplate' => '{num} {period} since update',
            'intervalSingular' => ['foobars', 'foobars', 'foobars', 'foobars', 'foobars', 'foobars', 'foobars'],
            'intervalPlural' => ['foobars', 'foobars', 'foobars', 'foobars', 'foobars', 'foobars', 'foobars']
        ]);
        $this->assertBookProgressNotContains("The Lord of the Rings", "foobars since update", $html);
        $this->assertBookProgressNotContains("A Game of Thrones", "foobars since update", $html);
        $this->assertBookHasNoProgress("The Chronicles of Narnia", $html);
        $this->assertBookProgressNotContains("Harry Potter and the Sorcerer", "foobars since update", $html);
    }

    public function testProgressBarEnabled() {
        $html = $this->getWidgetHTML(['useProgressBar' => true]);
        $dom = str_get_html($html);
        $numProgress = count($dom->find(".progress"));
        $numProgressBar = count($dom->find(".progress-bar"));
        $this->assertEquals($numProgress, $numProgressBar, "Number of progress bars ($numProgressBar) expected to match number of progress elements ($numProgress)");
    }

    public function testProgressBarDisabled() {
        $html = $this->getWidgetHTML(['useProgressBar' => false]);
        $dom = str_get_html($html);
        $numProgress = count($dom->find(".progress"));
        $numProgressBar = count($dom->find(".progress-bar"));
        $this->assertNotEquals(0, $numProgress, "Expected to find non-zero number of progress elements");
        $this->assertEquals(0, $numProgressBar, "Expected no progress bars but found $numProgressBar");
    }

}
