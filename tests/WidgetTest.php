<?php

namespace relativisticramblings\gr_progress;

require_once('GR_Progress_UnitTestCase.php');

class WidgetTest extends GR_Progress_UnitTestCase {

    public function setUp() {
        GoodreadsFetcher::$test_local = true;
        GoodreadsFetcher::$fail_if_url_matches = null;
        delete_transient('cvdm_gr_progress_disableFetchingUntil');
        delete_option('cvdm_gr_progress_shelves');
        delete_option("gr_progress_cvdm_coverURLs");
    }

    public function testWidgetOutputIsValidHTML() {
        $html = $this->getWidgetHTML();
        $this->assertIsValidHTML($html);
    }

    public function testWidgetFormIsValidHTML() {
        $widget = new gr_progress_cvdm_widget();

        ob_start();
        $widget->form([]);
        $html = ob_get_clean();
        $this->assertIsValidHTML($html);
    }

    public function testWidgetArgsCorrectlyIncluded() {
        $title = rand(0, 10000) . microtime();
        $html = $this->getWidgetHTML(['title' => $title]);
        $this->assertStringStartsWith('BEFORE_WIDGET_FOOBAR', $html);
        $this->assertStringEndsWith('AFTER_WIDGET_FOOBAR', $html);
        $this->assertContains("BEFORE_TITLE_FOOBAR{$title}AFTER_TITLE_FOOBAR", $html);
    }

    public function testCorrectBooksOnShelfusingDefaultSettings() {
        $html = $this->getWidgetHTML();
        $this->assertBooksOnShelf($this->DEFAULT_BOOKS_CURRENTLY_READING, $html);
    }

    public function testCorrectAuthorsOnShelf() {
        $html = $this->getWidgetHTML();
        $this->assertAuthorsOnShelf(['Martin', 'Lewis', 'Tolkien', 'Rowling'], $html);
    }

    public function testCorrectBooksOnTwoSimultaneousShelves() {
        $html_widget1 = $this->getWidgetHTML();
        $this->assertBooksOnShelf($this->DEFAULT_BOOKS_CURRENTLY_READING, $html_widget1);

        $html_widget2 = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'position', 'sortOrder' => 'a']);
        $this->assertBooksOnShelf($this->DEFAULT_BOOKS_TO_READ, $html_widget2);
    }

    public function testSetting_title() {
        $html = $this->getWidgetHTML(['title' => 'CUSTOM_TITLE_FOOBAR']);
        $this->assertContains("CUSTOM_TITLE_FOOBAR", $html);
    }

    public function testSetting_goodreadsAttribution() {
        $html = $this->getWidgetHTML(['goodreadsAttribution' => 'GOODREADS_ATTRIBUTION_FOOBAR']);
        $this->assertContains("GOODREADS_ATTRIBUTION_FOOBAR", $html);
    }

    public function testErrorMessageOnFailedBookshelfFetch() {
        GoodreadsFetcher::$fail_if_url_matches = $this->RE_FAIL_FETCH_BOOKSHELF;
        $html = $this->getWidgetHTML();
        $this->assertContains("Error retrieving data from Goodreads. Retrying in 60 minutes.", $html);
        $this->assertNoShelf($html);
    }

    public function testErrorMessageOnFailedCoverFetch() {
        GoodreadsFetcher::$fail_if_url_matches = $this->RE_FAIL_FETCH_COVER;
        $html = $this->getWidgetHTML();
        $this->assertContains("Error retrieving data from Goodreads. Retrying in 60 minutes.", $html);
        $this->assertNoShelf($html);
    }

    public function testErrorMessageOnFailedProgressFetch() {
        GoodreadsFetcher::$fail_if_url_matches = $this->RE_FAIL_FETCH_PROGRESS;
        $html = $this->getWidgetHTML(['progressType' => Progress::PROGRESSBAR]);
        $this->assertContains("Error retrieving data from Goodreads. Retrying in 60 minutes.", $html);
        $this->assertNoShelf($html);
    }

    public function testErrorMessageOnWidget2AfterFailedBookshelfFetchOnWidget1() {
        GoodreadsFetcher::$fail_if_url_matches = $this->RE_FAIL_FETCH_BOOKSHELF;
        $html_widget1 = $this->getWidgetHTML();
        $this->assertContains("Error retrieving data from Goodreads. Retrying in 60 minutes.", $html_widget1);
        $this->assertNoShelf($html_widget1);

        GoodreadsFetcher::$fail_if_url_matches = null;
        $html_widget2 = $this->getWidgetHTML();
        $this->assertContains("Error retrieving data from Goodreads. Retrying in 60 minutes.", $html_widget2);
        $this->assertNoShelf($html_widget2);
    }

    public function testErrorMessageIfNoUserid() {
        $html = $this->getWidgetHTML(['userid' => '']);
        $this->assertContains("Widget not configured correctly.", $html);
        $this->assertNoShelf($html);
    }

    public function testErrorMessageIfNoApiKey() {
        $html = $this->getWidgetHTML(['apiKey' => '']);
        $this->assertContains("Widget not configured correctly.", $html);
        $this->assertNoShelf($html);
    }

    public function testErrorMessageIfNoShelfName() {
        $html = $this->getWidgetHTML(['shelfName' => '']);
        $this->assertContains("Widget not configured correctly.", $html);
        $this->assertNoShelf($html);
    }

    public function testUseCachedHTML() {
        // use the same title for both widget instances so the same cache is used
        $title = rand(0, 10000) . microtime();

        // cache widget HTML and check books to be safe
        // (don't want a false positive if the fetch fails both times)
        $html_uncached = $this->getWidgetHTML(['title' => $title]);
        $this->assertBooksOnShelf($this->DEFAULT_BOOKS_CURRENTLY_READING, $html_uncached);
        $this->assertAllBooksHaveCoverImage($html_uncached);

        // make fetching fail - shouldn't matter, because nothing should be fetched in the first place
        GoodreadsFetcher::$fail_if_url_matches = $this->RE_FAIL_FETCH_BOOKSHELF;

        // check that the cached HTML is returned
        $html_cached = $this->getWidgetHTML(['title' => $title]);
        $this->assertEquals($html_uncached, $html_cached);

        // make sure there wasn't a fetch that failed
        $this->assertFalse(get_transient('cvdm_gr_progress_disableFetchingUntil'),
                'Did not expect to find transient cvdm_gr_progress_disableFetchingUntil');
    }

    public function testUseCachedOptionAfterTransientExpiresIfFetchFails() {
        // use the same title for both widget instances so the same cache is used
        $title = rand(0, 10000) . microtime();

        // cache widget HTML (deleting transient cache) and check books/failed fetch to be safe
        // (don't want a false positive if the fetch fails both times)
        $html_uncached = $this->getWidgetHTML(['title' => $title], true);
        $this->assertBooksOnShelf($this->DEFAULT_BOOKS_CURRENTLY_READING, $html_uncached);
        $this->assertAllBooksHaveCoverImage($html_uncached);
        $this->assertFalse(get_transient('cvdm_gr_progress_disableFetchingUntil'),
                'Did not expect to find transient cvdm_gr_progress_disableFetchingUntil');

        // make fetching fail
        GoodreadsFetcher::$fail_if_url_matches = $this->RE_FAIL_FETCH_BOOKSHELF;

        // check that the cached HTML is returned even when transient has been deleted
        $html_cached = $this->getWidgetHTML(['title' => $title]);
        $this->assertEquals($html_uncached, $html_cached);

        // make sure there actually was a fetch that failed
        $this->assertTrue(get_transient('cvdm_gr_progress_disableFetchingUntil') !== false,
                'Expected transient not found: cvdm_gr_progress_disableFetchingUntil');
    }

    public function testUseCachedCovers() {
        $this->getWidgetHTML();  // saves books and covers to cache
        GoodreadsFetcher::$fail_if_url_matches = $this->RE_FAIL_FETCH_COVER;
        // rebuild shelves - use slightly different settings so
        // previous cached results won't be used. Since cover
        // fetching is disabled, using cached covers is the only option.
        $html = $this->getWidgetHTML(['title' => rand(0, 10000) . microtime()]);
        $this->assertAllBooksHaveCoverImage($html);

        // Also check that cvdm_gr_progress_disableFetchingUntil hasn't been set,
        // because no covers should have been fetched in the first place
        $this->assertFalse(get_transient('cvdm_gr_progress_disableFetchingUntil'),
                'Did not expect to find transient cvdm_gr_progress_disableFetchingUntil');
    }

    public function testSetting_shelfNameAndSorting() {
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'position', 'sortOrder' => 'a']);
        $this->assertBooksOnShelf($this->DEFAULT_BOOKS_TO_READ, $html);
    }

    public function testSetting_emptyMessage() {
        $html = $this->getWidgetHTML(['shelfName' => 'empty-shelf', 'emptyMessage' => 'CUSTOM_EMPTY_MESSAGE_PRIMARY_SHELF']);
        $this->assertContains("CUSTOM_EMPTY_MESSAGE_PRIMARY_SHELF", $html);
        $this->assertNoShelf($html);
    }

    public function testSetting_emptyMessage_empty() {
        $html = $this->getWidgetHTML(['shelfName' => 'empty-shelf', 'emptyMessage' => '']);
        $this->assertNotContains('emptyShelfMessage', $html);
        $this->assertNoShelf($html);
    }

    public function testdisplayNoCommentsUsingDefaultSettings() {
        $html = $this->getWidgetHTML();
        $this->assertNoBooksHaveComment($html);
    }

    public function testSetting_coverSizeSmall() {
        $html = $this->getWidgetHTML(['coverSize' => CoverSize::SMALL]);
        $dom = str_get_html($html);
        $this->assertNotEmpty($dom->find('.small-cover'), 'Expected small covers but found none');
        $this->assertEmpty($dom->find('.large-cover'), 'Found unexpected large covers');
    }

    public function testSetting_coverSizeLarge() {
        $html = $this->getWidgetHTML(['coverSize' => CoverSize::LARGE]);
        $dom = str_get_html($html);
        $this->assertNotEmpty($dom->find('.large-cover'), 'Expected large covers but found none');
        $this->assertEmpty($dom->find('.small-cover'), 'Found unexpected small covers');
    }

    public function testSetting_displayReviewExcerpt() {
        $html = $this->getWidgetHTML(['displayReviewExcerpt' => true]);
        $this->assertBookHasNoComment("The Lord of the Rings", $html);
        $this->assertBookHasComment("A Game of Thrones", "First line. &lt;3", $html);
        $this->assertBookHasComment("The Chronicles of Narnia", "Only line, with &lt;3 and <a", $html);
        $this->assertBookHasNoComment("Harry Potter and the Sorcerer", $html);
    }
    
    public function testSetting_bookLink_false() {
        $html = $this->getWidgetHTML(['bookLink' => false]);
        $dom = str_get_html($html);
        foreach ($dom->find(".bookTitle") as $bookTitle) {
            $this->assertNotContains("<a href", $bookTitle->innertext);
        }
    }
    
    public function testSetting_bookLink_true() {
        $html = $this->getWidgetHTML(['bookLink' => true, 'bookLinkNewTab' => false]);
        $dom = str_get_html($html);
        foreach ($dom->find(".bookTitle") as $bookTitle) {
            $this->assertContains("<a href='http://", $bookTitle->innertext);
        }
    }
    
    public function testSetting_bookLinkNewTab() {
        $html = $this->getWidgetHTML(['bookLink' => true, 'bookLinkNewTab' => true]);
        $dom = str_get_html($html);
        foreach ($dom->find(".bookTitle") as $bookTitle) {
            $this->assertContains("target='_blank'", $bookTitle->innertext);
        }
    }

    public function testAllBooksHaveCoverImage() {
        $html = $this->getWidgetHTML();
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testProgressDisabled() {
        $html = $this->getWidgetHTML(['progressType' => Progress::DISABLED]);
        $this->assertBookHasNoProgress("The Lord of the Rings", $html);
        $this->assertBookHasNoProgress("A Game of Thrones", $html);
        $this->assertBookHasNoProgress("The Chronicles of Narnia", $html);
        $this->assertBookHasNoProgress("Harry Potter and the Sorcerer", $html);
    }

    public function testProgressBar() {
        $html = $this->getWidgetHTML(['progressType' => Progress::PROGRESSBAR]);
        $this->assertBooksHaveProgressBar($html);
        $this->assertBookProgressContains("The Lord of the Rings", "20", $html);
        $this->assertBookProgressContains("A Game of Thrones", "20", $html);
        $this->assertBookHasNoProgress("The Chronicles of Narnia", $html);
        $this->assertBookProgressContains("Harry Potter and the Sorcerer", "30", $html);
    }

    public function testProgressText() {
        $html = $this->getWidgetHTML(['progressType' => Progress::TEXT]);
        $this->assertBooksHaveProgressText($html);
        $this->assertBookProgressContains("The Lord of the Rings", "20", $html);
        $this->assertBookProgressContains("A Game of Thrones", "20", $html);
        $this->assertBookHasNoProgress("The Chronicles of Narnia", $html);
        $this->assertBookProgressContains("Harry Potter and the Sorcerer", "30", $html);
    }

    public function testProgressTextToRead() {
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'progressType' => Progress::TEXT]);
        foreach ($this->DEFAULT_BOOKS_TO_READ as $book) {
            if ($book == 'Artemis Fowl') {
                $this->assertBookProgressContains($book, "10", $html);
            } else {
                $this->assertBookHasNoProgress($book, $html);
            }
        }
    }

    public function testSortCurrentlyReading_author_a() {
        $books = [
            'The Chronicles of Narnia',
            'A Game of Thrones',
            'Harry Potter and the Sorcerer',
            'The Lord of the Rings',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'author', 'sortOrder' => 'a']);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortCurrentlyReading_author_d() {
        $books = [
            'The Lord of the Rings',
            'Harry Potter and the Sorcerer',
            'A Game of Thrones',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'author', 'sortOrder' => 'd']);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortCurrentlyReading_title_a() {
        $books = [
            'The Chronicles of Narnia',
            'A Game of Thrones',
            'Harry Potter and the Sorcerer',
            'The Lord of the Rings',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'title', 'sortOrder' => 'a']);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortCurrentlyReading_title_d() {
        $books = [
            'The Lord of the Rings',
            'Harry Potter and the Sorcerer',
            'A Game of Thrones',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'title', 'sortOrder' => 'd']);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortToRead_position_a() {
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
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'position', 'sortOrder' => 'a']);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortToRead_position_d() {
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
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'position', 'sortOrder' => 'd']);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortToRead_title_a() {
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
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'title', 'sortOrder' => 'a']);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortToRead_title_d() {
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
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'title', 'sortOrder' => 'd']);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortCurrentlyReading_author_a_maxBooks() {
        $books = [
            'The Chronicles of Narnia',
            'A Game of Thrones',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'author', 'sortOrder' => 'a', 'maxBooks' => 2]);
        $this->assertBooksOnShelf($books, $html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortCurrentlyReading_author_d_maxBooks() {
        $books = [
            'The Lord of the Rings',
            'Harry Potter and the Sorcerer',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'author', 'sortOrder' => 'd', 'maxBooks' => 2]);
        $this->assertBooksOnShelf($books, $html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortCurrentlyReading_title_a_maxBooks() {
        $books = [
            'The Chronicles of Narnia',
            'A Game of Thrones',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'title', 'sortOrder' => 'a', 'maxBooks' => 2]);
        $this->assertBooksOnShelf($books, $html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortCurrentlyReading_title_d_maxBooks() {
        $books = [
            'The Lord of the Rings',
            'Harry Potter and the Sorcerer',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'title', 'sortOrder' => 'd', 'maxBooks' => 2]);
        $this->assertBooksOnShelf($books, $html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortToRead_position_a_maxBooks() {
        $books = [
            "The Name of the Wind",
            "The Eye of the World",
        ];
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'position', 'sortOrder' => 'a', 'maxBooks' => 2]);
        $this->assertBooksOnShelf($books, $html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortToRead_position_d_maxBooks() {
        $books = [
            "Artemis Fowl",
            "The Color of Magic",
        ];
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'position', 'sortOrder' => 'd', 'maxBooks' => 2]);
        $this->assertBooksOnShelf($books, $html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSSortSecondary_title_a_maxBooks() {
        $books = [
            "Artemis Fowl",
            "City of Bones",
        ];
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'title', 'sortOrder' => 'a', 'maxBooks' => 2]);
        $this->assertBooksOnShelf($books, $html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortToRead_title_d_maxBooks() {
        $books = [
            "The Way of Kings",
            "The Name of the Wind",
        ];
        $html = $this->getWidgetHTML(['shelfName' => 'to-read', 'sortBy' => 'title', 'sortOrder' => 'd', 'maxBooks' => 2]);
        $this->assertBooksOnShelf($books, $html);
        $this->assertAllBooksHaveCoverImage($html);
    }

    public function testSortByProgress_title_a() {
        $books = [
            'Harry Potter and the Sorcerer',
            'A Game of Thrones',
            'The Lord of the Rings',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'title', 'sortOrder' => 'a', 'progressType' => Progress::PROGRESSBAR, 'sortByReadingProgress' => true]);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortByProgress_title_d() {
        $books = [
            'Harry Potter and the Sorcerer',
            'The Lord of the Rings',
            'A Game of Thrones',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'title', 'sortOrder' => 'd', 'progressType' => Progress::PROGRESSBAR, 'sortByReadingProgress' => true]);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortByProgress_author_a() {
        $books = [
            'Harry Potter and the Sorcerer',
            'A Game of Thrones',
            'The Lord of the Rings',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'author', 'sortOrder' => 'a', 'progressType' => Progress::PROGRESSBAR, 'sortByReadingProgress' => true]);
        $this->assertBooksOnShelf($books, $html);
    }

    public function testSortByProgress_author_d() {
        $books = [
            'Harry Potter and the Sorcerer',
            'The Lord of the Rings',
            'A Game of Thrones',
            'The Chronicles of Narnia',
        ];
        $html = $this->getWidgetHTML(['sortBy' => 'author', 'sortOrder' => 'd', 'progressType' => Progress::PROGRESSBAR, 'sortByReadingProgress' => true]);
        $this->assertBooksOnShelf($books, $html);
    }

    // FIXME: why is  'progressType' => Progress::PROGRESSBAR   needed in the above functions?

    public function testAllBooksHaveCoverImageWhenSortByProgress() {
        $html = $this->getWidgetHTML(['progressType' => Progress::PROGRESSBAR, 'sortByReadingProgress' => true]);
        $this->assertAllBooksHaveCoverImage($html);
    }

    /**
     * Tests that the books with most progress are returned even when they would not
     * normally appear due to being excluded by the queried per_page and sort order
     */
    public function testSortByProgress_maxBooks() {
        $html = $this->getWidgetHTML([
            'sortBy' => 'title', 'sortOrder' => 'a', 'progressType' => Progress::PROGRESSBAR,
            'sortByReadingProgress' => true, 'maxBooks' => 1]);
        $this->assertBooksOnShelf(['Harry Potter and the Sorcerer'], $html);
    }

    public function testProgressUpdateTimeEnabled() {
        $html = $this->getWidgetHTML([
            'progressType' => Progress::PROGRESSBAR,
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
            'progressType' => Progress::PROGRESSBAR,
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

}
