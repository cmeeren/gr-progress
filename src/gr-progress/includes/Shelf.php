<?php

namespace relativisticramblings\gr_progress;

require_once("Book.php");
require_once("GoodreadsFetcher.php");

class Shelf {

    private $widgetData;
    private $books = [];

    function __construct($widgetData) {
        $this->widgetData = $widgetData;
        $this->fetchBooksFromGoodreads();
        $this->loadCachedCoverURLs();
        $this->fetchCoverURLsIfMissing();
        if ($this->widgetData['progressType'] !== Progress::DISABLED) {
            $this->updateProgress();
        }
        if ($this->widgetData['sortByReadingProgress']) {
            $this->sortBooksByReadingProgress();
        }
    }

    private function fetchBooksFromGoodreads() {
        $xml = str_get_html(GoodreadsFetcher::fetch(
                        "https://www.goodreads.com/review/list/"
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
        
        $reviews = $xml->find("reviews", 0);
        $this->books_total = $reviews->total;

        foreach ($xml->find("review") as $reviewElement) {
            $rating = $reviewElement->find("rating", 0)->plaintext;
            $bookElement = $reviewElement->find("book", 0);
            $id = $bookElement->find("id", 0)->plaintext;
            $title = $bookElement->find("title", 0)->plaintext;
            $authors = [];
            foreach ($bookElement->find("author name") as $name) {
                $authors[] = $name->plaintext;
            }

            $reviewBodyFirstLine = $this->widgetData['displayReviewExcerpt'] ? $this->getReviewBodyFirstLine($reviewElement) : null;

            $link = $bookElement->find("link text", 0)->plaintext;

            $this->books[$id] = new Book($id, $title, $authors, $reviewBodyFirstLine, $rating, $link, $this->widgetData);
        }
    }

    private function getMaxBooks() {
        if ($this->widgetData['sortByReadingProgress']) {
            // If sorting by reading progress, we need to fetch all books
            // so that we can sort them ourselves.
            return 100;
        } else {
            return $this->widgetData['maxBooks'];
        }
    }

    private function getReviewBodyFirstLine($reviewElement) {
        $reviewBodyFirstLine = null;
        $reviewBody = $reviewElement->find("body", 0)->plaintext;
        $re_CDATA = "/^\s*(?:\/\/)?<!\[CDATA\[([\s\S]*)(?:\/\/)?\]\]>\s*\z/";
        if (preg_match($re_CDATA, $reviewBody)) {
            $reviewBody = preg_replace($re_CDATA, '$1', $reviewBody);
        } else {
            $reviewBody = html_entity_decode($reviewBody);  // to fix Goodreads bug
        }

        $reviewBodySplit = preg_split("/<br/", $reviewBody, 2);
        if (!empty($reviewBodySplit)) {
            $reviewBodyFirstLine = trim($reviewBodySplit[0]);
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
                $this->fetchAllCoverURLs();
                break;
            }
        }
    }

    private function fetchAllCoverURLs() {
        $xml = str_get_html(GoodreadsFetcher::fetch(
                        "https://www.goodreads.com/review/list_rss/"
                        . "{$this->widgetData['userid']}"
                        . "?shelf={$this->widgetData['shelfName']}"));

        if ($xml === false) {
            return;
        }
        
        // We want to know how many books a shelf contains in total, regardless of how many books are returned by our query.
        $reviews = $xml->find("reviews", 0);
        $this->books_total = $reviews->total;

        foreach ($xml->find("item") as $item) {
            $bookID = $item->find("book_id", 0)->plaintext;
            $srcWithCDATA = $item->find("book_small_image_url", 0)->plaintext;
            $src = preg_replace('/^\s*(?:\/\/)?<!\[CDATA\[([\s\S]*)(?:\/\/)?\]\]>\s*\z/', '$1', $srcWithCDATA);
            $src = preg_replace('/^https?:/', '', $src);  // remove http: or https: to use the same protocol as the page
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

    public function isEmpty() {
        return count($this->books) == 0;
    }

    public function getBooks() {
        return $this->books;
    }

    public function getBooksTotal() {
        return $this->books_total;
    }

    public function updateProgress() {
        foreach ($this->books as $book) {
            $book->fetchProgress($book);
        }
    }

    private function sortBooksByReadingProgress() {
        // This is rather messy. Ideally we would simply use a stable user sort maintaining (numeric) keys,
        // but such a sort doesn't exist.

        // First, get all the progresses and associated book IDs. These need to be
        // in separate arrays, because array_multisort (used later) doesn't preserve
        // numeric keys (otherwise we could index $progress using the book IDs).
        $progresses = [];
        $ids = [];
        foreach ($this->books as $bookID => $book) {
            $progresses[] = $book->hasProgress() ? $book->getProgressInPercent() : 0;
            $ids[] = $bookID;
        }

        // Now, sort the progresses. We use a simple [1, 2, 3, ...] array as secondary
        // array to make the sort stable (i.e. preserve the existing order of identical
        // progresses). We also sort $ids so it matches with $progresses.
        $arrayToMakeSortStable = range(0, count($progresses) - 1);
        array_multisort($progresses, SORT_DESC, $arrayToMakeSortStable, $ids);

        // Create a new array which we fill with all the Book objects in the correct order
        $sortedBooks = [];
        for ($i = 0; $i < count($progresses); $i++) {
            $bookID = $ids[$i];
            $sortedBooks[$bookID] = $this->books[$bookID];
        }

        // Finally, set the internal book list to this new sorted list
        $this->books = $sortedBooks;

        // All books on shelf were fetched previously in order to sort them by reading progress.
        // Now that they've been sorted, only keep the first maxBooks.
        $this->books = array_slice($this->books, 0, $this->widgetData['maxBooks'], true);
    }

}
