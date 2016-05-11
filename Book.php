<?php

class Book {

    private $id;
    private $title;
    private $authors;
    private $comment;
    private $coverURL;
    private $progressInPercent;
    private $progressStatusUpdateTime;
    private $widgetData;
    public $retrievalError = false;

    public function Book($id, $title, $authors, $comment, $widgetData) {
        $this->id = $id;
        $this->title = $title;
        $this->authors = $authors;
        $this->comment = $comment;
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
    
    public function hasComment() {
        return !empty($this->comment);
    }
    
    public function getComment() {
        return $this->comment;
    }

    public function fetchProgressUsingAPI() {
        $allStatusUpdates = [];

        $xml = file_get_html(
                "http://www.goodreads.com/review/show_by_user_and_book.xml"
                . "?key={$this->widgetData['apiKey']}"
                . "&book_id={$this->id}"
                . "&user_id={$this->widgetData['userid']}");

        if ($xml === false) {
            $this->retrievalError = true;
            return;
        }

        foreach ($xml->find("user_status") as $status) {
            $statusTimestamp = strtotime($status->find("created_at", 0)->plaintext);
            $percent = $status->find("percent", 0)->plaintext;
            $allStatusUpdates[$statusTimestamp] = $percent;
        }

        krsort($allStatusUpdates);
        if (empty($allStatusUpdates)) {
            $percent = "";
            $latestStatusTimestamp = 0;
        } else {
            $percent = reset($allStatusUpdates);
            $latestStatusTimestamp = key($allStatusUpdates);
        }

        $this->progressInPercent = $percent;
        $this->progressStatusUpdateTime = $latestStatusTimestamp;
        $this->retrievalError = false;
    }

}
