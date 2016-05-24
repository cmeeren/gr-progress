<?php

namespace relativisticramblings\gr_progress;

require_once("Book.php");
require_once("GoodreadsFetcher.php");

class Shelf {

    private $widgetData;
    private $books = [];
    public static $test_disableCoverFetching = false;

    function __construct($widgetData) {
        $this->widgetData = $widgetData;
        $this->fetchBooksFromGoodreadsUsingAPI();
        $this->loadCachedCoverURLs();
        if ($this->widgetData['progressType'] !== Progress::DISABLED) {
            $this->updateProgress();
        }
        if (!self::$test_disableCoverFetching) {
            $this->fetchCoverURLsIfMissing();
        }
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

    /**
     * Unused - suffers from a bug. It appears that only the 'shelf' parameter
     * is taken into account. A combination of missing per_page, sort, and order
     * parameters may cause fewer/different books to be returned as compared to
     * when we got the shelf via the API. Thus, some books might not get a cover.
     * Solve this by getting cover images from RSS feed instead of HTML
     * (see fetchAllCoverURLsUsingRSS). Keeping the function here until
     * the RSS method has proven problem-free in the wild.
     * @codeCoverageIgnore
     */
    private function fetchAllCoverURLsUsingHTML() {
        $html = str_get_html(GoodreadsFetcher::fetch(
                        "http://www.goodreads.com/review/list/"
                        . "{$this->widgetData['userid']}"
                        . "?shelf={$this->widgetData['shelfName']}"
                        . "&per_page={$this->getMaxBooks()}"
                        . "&sort={$this->widgetData['sortBy']}"
                        . "&order={$this->widgetData['sortOrder']}"));

        if ($html === false) {
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
            mergesort($this->books, '\relativisticramblings\gr_progress\compareBookProgress');
            // All books on shelf were fetched previously in order to sort them by reading progerss.
            // Only keep maxBooks number of books now after they've been sorted.
            $this->books = array_slice($this->books, 0, $this->widgetData['maxBooks'], true);
        }
    }

}

/**
 * sorting function from http://php.net/manual/en/function.usort.php#38827
 * preserves ordering if elements compare as equal
 * @codeCoverageIgnore
 */
function mergesort(&$array, $cmp_function = 'strcmp') {
    // Arrays of size < 2 require no action.
    if (count($array) < 2) {
        return;
    }
    // Split the array in half
    $halfway = count($array) / 2;
    $array1 = array_slice($array, 0, $halfway);
    $array2 = array_slice($array, $halfway);
    // Recurse to sort the two halves
    mergesort($array1, $cmp_function);
    mergesort($array2, $cmp_function);
    // If all of $array1 is <= all of $array2, just append them.
    if (call_user_func($cmp_function, end($array1), $array2[0]) < 1) {
        $array = array_merge($array1, $array2);
        return;
    }
    // Merge the two sorted arrays into a single sorted array
    $array = array();
    $ptr1 = $ptr2 = 0;
    while ($ptr1 < count($array1) && $ptr2 < count($array2)) {
        if (call_user_func($cmp_function, $array1[$ptr1], $array2[$ptr2]) < 1) {
            $array[] = $array1[$ptr1++];
        } else {
            $array[] = $array2[$ptr2++];
        }
    }
    // Merge the remainder
    while ($ptr1 < count($array1))
        $array[] = $array1[$ptr1++];
    while ($ptr2 < count($array2))
        $array[] = $array2[$ptr2++];
    return;
}

function compareBookProgress($book1, $book2) {
    // return negative number if progress of book 1 is less than progress of book 2
    // return positive number if progress of book 1 is larger than progress of book 2
    // return zero if progress is equal
    $progress1 = $book1->hasProgress() ? $book1->getProgressInPercent() : 0;
    $progress2 = $book2->hasProgress() ? $book2->getProgressInPercent() : 0;
    return $progress2 - $progress1;
}
