<?php

require_once("simple_html_dom.php");  // FIXME: needed?

class GoodreadsFetcher {

    private $DEFAULT_TIMEOUT_IN_SECONDS = 5;
    public static $test_local = false;
    public static $test_fail = false;

    public function fetch($url) {
        if (self::$test_fail) {
            return false;
        } elseif (self::$test_local) {
            return $this->fetchFromFile($url);
        } else {
            return $this->fetchFromGoodreads($url);
        }
    }

    private function fetchFromGoodreads($url) {
        $ctx = stream_context_create(['http' => ['timeout' => $this->DEFAULT_TIMEOUT_IN_SECONDS]]);
        return file_get_contents($url, false, $ctx);
    }

    private function fetchFromFile($url) {
        $file = $this->getPath() . $this->getSafeFilename($url);
        if (is_file($file)) {
            return file_get_contents($file);
        } else {
            $html = $this->fetchFromGoodreads($url);
            file_put_contents($file, $html);
            return $html;
        }
    }

    private function getPath() {
        return dirname(__FILE__) . '/tests/responses/';
    }

    private function getSafeFilename($str) {
        $str = mb_ereg_replace("([^\w\d\-_])", '', $str);
        $str = mb_ereg_replace("([\.]{2,})", '', $str);
        return $str;
    }

}
