<?php

namespace relativisticramblings\gr_progress;

require_once("Book.php");
require_once("GoodreadsFetcher.php");

class Shelf {

    private $widgetData;
    private $books = [];

    function __construct($widgetData) {
        $this->widgetData = $widgetData;
        $this->fetchBooksFromGoodreadsUsingAPI();
        $this->loadCachedCoverURLs();
        if ($this->widgetData['progressType'] !== Progress::DISABLED) {
            $this->updateProgress();
        }
        $this->fetchCoverURLsIfMissing();
    }

    private function fetchBooksFromGoodreadsUsingAPI() {
        $xml = str_get_html(GoodreadsFetcher::fetch(
                        "http://www.goodreads.com/review/list/"
                        . "{$this->widgetData['userid']}.xml"
                        . "?v=2"
                        . "&key={$this->widgetData['apiKey']}"
                        . "&shelf={$this->widgetData['shelfName']}"
                        . "&per_page={$this->getMaxBooks()}"
                        . "&sort={$this->widgetData['sortBy']}"
                        . "&order={$this->widgetData['sortOrder']}"));

        if ($xml === false) {
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

            $reviewBodyFirstLine = $this->getReviewBodyFirstLine($reviewElement);

            $this->books[$id] = new Book($id, $title, $authors, $reviewBodyFirstLine, $this->widgetData);
        }
    }

    private function getMaxBooks() {
        if ($this->widgetData['sortByReadingProgress']) {
            return 100;
        } else {
            return $this->widgetData['maxBooks'];
        }
    }

    private function getReviewBodyFirstLine($reviewElement) {
        $reviewBodyFirstLine = null;
        if ($this->widgetData['displayReviewExcerpt']) {
            $reviewBody = $reviewElement->find("body", 0)->plaintext;
            $re_CDATA = "/^\s*(?:\/\/)?<!\[CDATA\[([\s\S]*)(?:\/\/)?\]\]>\s*\z/";
            if (preg_match($re_CDATA, $reviewBody)) {
                $reviewBody = preg_replace($re_CDATA, '$1', $reviewBody);
            } else {
                $reviewBody = html_entity_decode($reviewBody);  // to fix GR bug
            }

            // for some reason, if the first line is empty, Goodreads may
            // return &lt;br /&gt; instead of <br />, so split by that too
            $reviewBodySplit = preg_split("/(<|&lt;)br/", $reviewBody, 2);
            if (!empty($reviewBodySplit)) {
                $reviewBodyFirstLine = trim($reviewBodySplit[0]);
            }
        }

        return $reviewBodyFirstLine;
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
                $this->fetchAllCoverURLsUsingRSS();
                break;
            }
        }
    }

    private function fetchAllCoverURLsUsingRSS() {
        $xml = str_get_html(GoodreadsFetcher::fetch(
                        "http://www.goodreads.com/review/list_rss/"
                        . "{$this->widgetData['userid']}"
                        . "?shelf={$this->widgetData['shelfName']}"));

        if ($xml === false) {
            return;
        }

        foreach ($xml->find("item") as $item) {
            $bookID = $item->find("book_id", 0)->plaintext;
            $srcWithCDATA = $item->find("book_large_image_url", 0)->plaintext;
            $src = preg_replace('/^\s*(?:\/\/)?<!\[CDATA\[([\s\S]*)(?:\/\/)?\]\]>\s*\z/', '$1', $srcWithCDATA);
            if (array_key_exists($bookID, $this->books)) {
                $this->books[$bookID]->setCoverURL($src);
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

    public function getBooks() {
        return $this->books;
    }

    public function isEmpty() {
        return count($this->books) == 0;
    }

    public function updateProgress() {
        foreach ($this->books as $book) {
            $book->fetchProgressUsingAPI($book);
        }
        $this->sortBooksByReadingProgressIfRelevant();
    }

    private function sortBooksByReadingProgressIfRelevant() {
        if ($this->widgetData['sortByReadingProgress']) {

            $progress = [];
            $ids = [];
            foreach ($this->books as $bookID => $book) {
                $progress[] = $book->hasProgress() ? $book->getProgressInPercent() : "0";
                $ids[] = $bookID;
            }
            $arrayToMakeSortStable = range(0, count($progress) - 1);
            array_multisort($progress, SORT_DESC, $arrayToMakeSortStable, $ids);

            $sortedBooks = [];
            for ($i = 0; $i < count($progress); $i++) {
                $bookID = $ids[$i];
                $sortedBooks[$bookID] = $this->books[$bookID];
            }

            $this->books = $sortedBooks;

            // All books on shelf were fetched previously in order to sort them by reading progerss.
            // Only keep maxBooks number of books now after they've been sorted.
            $this->books = array_slice($this->books, 0, $this->widgetData['maxBooks'], true);
        }
    }

}
