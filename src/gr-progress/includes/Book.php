<?php

namespace relativisticramblings\gr_progress;

require_once("simple_html_dom.php");
require_once("GoodreadsFetcher.php");

class Book {

    private $id;
    private $title;
    private $authors;
    private $rating;
    private $comment;
    private $coverURL;
    private $link;
    private $progressInPercent;
    private $progressStatusUpdateTime;
    private $widgetData;

    public function __construct($id, $title, $authors, $comment, $rating, $link, $widgetData) {
        $this->id = $id;
        $this->title = $title;
        $this->authors = $authors;
        $this->rating = $rating;
        $this->comment = $comment;
        $this->link = $link;
        $this->widgetData = $widgetData;
    }

    public function hasCover() {
        return !empty($this->coverURL);
    }

    public function setCoverURL($url) {
        $this->coverURL = $url;
    }

    public function getCoverURL() {
        return $this->coverURL;
    }

    public function getLink() {
        return $this->link;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getAuthor() {
        return implode(", ", $this->authors);
    }

    public function hasProgress() {
        return !empty($this->progressInPercent);
    }

    public function getProgressInPercent() {
        return $this->progressInPercent;
    }

    public function getProgressStatusUpdateTime() {
        return $this->progressStatusUpdateTime;
    }

    public function hasRating() {
        return $this->rating != 0;
    }

    public function getRating() {
        return $this->rating;
    }

    public function hasComment() {
        return !empty($this->comment);
    }

    public function getComment() {
        return $this->comment;
    }

    public function fetchProgress() {
        $xml = str_get_html(GoodreadsFetcher::fetch(
                        "http://www.goodreads.com/review/show_by_user_and_book.xml"
                        . "?key={$this->widgetData['apiKey']}"
                        . "&book_id={$this->id}"
                        . "&user_id={$this->widgetData['userid']}"));

        if ($xml === false) {
            return;
        }

        $allProgressUpdates = [];
        foreach ($xml->find("user_status") as $status) {
            $statusTimestamp = strtotime($status->find("created_at", 0)->plaintext);
            $percent = $status->find("percent", 0)->plaintext;
            $allProgressUpdates[$statusTimestamp] = $percent;
        }

        if (empty($allProgressUpdates)) {
            $this->progressInPercent = 0;
            $this->progressStatusUpdateTime = 0;
        } else {
            krsort($allProgressUpdates);  // sort by descending status timestamp (array key)
            $this->progressInPercent = intval(reset($allProgressUpdates));
            $this->progressStatusUpdateTime = key($allProgressUpdates);
        }
    }

}
