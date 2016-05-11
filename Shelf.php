<?php

require_once("Book.php");

class Shelf {

    private $shelfName;
    private $widgetData;
    private $books = [];
    private $bookRetrievalTimestamp;
    private $lastProgressRetrievalTimestamp = 0;
    public $retrievalError = false;

    function Shelf($shelfName, $widgetData) {
        $this->shelfName = $shelfName;
        $this->widgetData = $widgetData;
        $this->fetchBooksFromGoodreads();
        $this->loadCachedCoverURLs();
        $this->fetchCoverURLsIfMissing();
    }

    private function fetchBooksFromGoodreads() {
        $this->fetchBooksFromGoodreadsUsingAPI();
        $this->bookRetrievalTimestamp = time();
    }

    private function fetchBooksFromGoodreadsUsingAPI() {
        $xml = file_get_html(
                "http://www.goodreads.com/review/list/"
                . "{$this->widgetData['userid']}.xml"
                . "?v=2"
                . "&key={$this->widgetData['apiKey']}"
                . "&shelf={$this->shelfName}"
                . "&per_page={$this->getMaxBooks()}"
                . "&sort={$this->getSortBy()}"
                . "&order={$this->getSortOrder()}");

        if ($xml === false) {
            $this->retrievalError = true;
            return;
        }

        foreach ($xml->find("reviews", 0)->find("review") as $reviewElement) {
            $bookElement = $reviewElement->find("book", 0);
            $id = $bookElement->find("id", 0)->plaintext;
            $title = $bookElement->find("title", 0)->plaintext;
            $authors = [];
            foreach ($bookElement->find("author") as $author) {
                $authors[] = $author->find("name", 0)->plaintext;
            }

            $reviewBodyFirstLine = null;
            $showBookComment = $this->isCurrentlyReadingShelf() ? $this->widgetData['displayReviewExcerptCurrentlyReadingShelf'] : $this->widgetData['displayReviewExcerptAdditionalShelf'];
            if ($showBookComment) {
                $reviewBody = $reviewElement->find("body", 0)->plaintext;
                $reviewBody =  preg_replace('/^\s*(?:\/\/)?<!\[CDATA\[([\s\S]*)(?:\/\/)?\]\]>\s*\z/', '$1', $reviewBody);
                $reviewBodySplit = explode("<br", $reviewBody, 2);
                if (!empty($reviewBodySplit)) {
                    $reviewBodyFirstLine = trim($reviewBodySplit[0]);
                }
            }

            $this->books[$id] = new Book($id, $title, $authors, $reviewBodyFirstLine, $this->widgetData);
        }
    }

    private function getMaxBooks() {
        if ($this->isCurrentlyReadingShelf()) {
            return $this->widgetData['maxBooksCurrentlyReadingShelf'];
        } else {
            return $this->widgetData['maxBooksAdditionalShelf'];
        }
    }

    private function loadCachedCoverURLs() {
        $cachedCoverURLs = get_option("gr_progress_cvdm_coverURLs", []);
        foreach ($this->books as $bookID => $book) {
            if (isset($cachedCoverURLs[$bookID])) {
                $book->setCoverURL($cachedCoverURLs[$bookID]);
            }
        }
    }

    private function fetchCoverURLsIfMissing() {
        foreach ($this->books as $book) {
            if (!$book->hasCover()) {
                $this->fetchAllCoverURLs();
                break;
            }
        }
    }

    private function fetchAllCoverURLs() {
        $html = file_get_html(
                "http://www.goodreads.com/review/list/"
                . "{$this->widgetData['userid']}"
                . "?shelf={$this->shelfName}"
                . "&per_page={$this->getMaxBooks()}"
                . "&sort={$this->getSortBy()}"
                . "&order={$this->getSortOrder()}");

        if ($html === false) {
            $this->retrievalError = true;
            return;
        }

        $tableWrapper = $html->find("table#books", 0);
        if ($tableWrapper !== null) {
            $covers = $tableWrapper->find("td.cover");
            foreach ($covers as $cover) {
                $src = $cover->find("img", 0)->src;
                preg_match("/.*\/(\d+)\./", $src, $matches);
                $bookID = $matches[1];
                $largeImageSrc = preg_replace("/(.*\/\d*)[sm](\/.*)/", "$1l$2", $src);
                if (array_key_exists($bookID, $this->books)) {
                    $this->books[$bookID]->setCoverURL($largeImageSrc);
                }
            }
        }

        $this->addCoverURLsToCache();
    }

    private function addCoverURLsToCache() {
        $cachedCoverURLs = get_option("gr_progress_cvdm_coverURLs", []);
        foreach ($this->books as $bookID => $book) {
            $cachedCoverURLs[$bookID] = $book->getCoverURL();
        }
        update_option("gr_progress_cvdm_coverURLs", $cachedCoverURLs);
    }

    private function getSortBy() {
        if ($this->isCurrentlyReadingShelf()) {
            return $this->widgetData['currentlyReadingShelfSortBy'];
        } else {
            return $this->widgetData['additionalShelfSortBy'];
        }
    }

    private function getSortOrder() {
        if ($this->isCurrentlyReadingShelf()) {
            return $this->widgetData['currentlyReadingShelfSortOrder'];
        } else {
            return $this->widgetData['additionalShelfSortOrder'];
        }
    }

    private function isCurrentlyReadingShelf() {
        return $this->shelfName == $this->widgetData['currentlyReadingShelfName'];
    }

    public function getBooks() {
        return $this->books;
    }

    public function isEmpty() {
        return count($this->books) == 0;
    }

    public function bookCacheOutOfDate() {
        $bookDataAgeInSeconds = time() - $this->bookRetrievalTimestamp;
        $bookCacheTTLInSeconds = $this->widgetData['bookCacheHours'] * 3600;
        return $bookDataAgeInSeconds > $bookCacheTTLInSeconds;
    }

    public function progressCacheOutOfDate() {
        $progressDataAgeInSeconds = time() - $this->lastProgressRetrievalTimestamp;
        $progressCacheTTLInSeconds = $this->widgetData['progressCacheHours'] * 3600;
        return $progressDataAgeInSeconds > $progressCacheTTLInSeconds;
    }

    public function updateProgress() {
        $progressFetchOk = true;
        foreach ($this->books as $book) {
            $book->fetchProgressUsingAPI($book);
            if ($book->retrievalError) {
                $progressFetchOk = false;
                update_option("gr_progress_cvdm_lastRetrievalErrorTime", time());
            }
        }

        if ($progressFetchOk) {
            $this->lastProgressRetrievalTimestamp = time();
        }
    }

}
