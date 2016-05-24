<?php

namespace relativisticramblings\gr_progress;

class GoodreadsFetcher {

    private $DEFAULT_TIMEOUT_IN_SECONDS = 3;
    private $SECONDS_TO_WAIT_AFTER_FAILED_FETCH = 3600;
    public static $test_local = false;
    public static $test_fail = false;

    public function fetch($url) {

        if (get_transient('cvdm_gr_progress_goodreadsFetchFail') !== false) {
            return false;
        }

        if (self::$test_fail) {
            $result = false;
        } elseif (self::$test_local) {
            $result = $this->fetchFromFile($url);
        } else {
            $result = $this->fetchFromGoodreads($url);
        }

        if ($result === false) {
            set_transient('cvdm_gr_progress_goodreadsFetchFail', time() + $this->SECONDS_TO_WAIT_AFTER_FAILED_FETCH);
        }

        return $result;
    }

    private function fetchFromGoodreads($url) {
        $ctx = stream_context_create(['http' => ['timeout' => $this->DEFAULT_TIMEOUT_IN_SECONDS]]);
        return file_get_contents($url, false, $ctx);
    }

    private function fetchFromFile($url) {
        $file = $this->getTestStoragePath() . $this->getSafeTestStorageFilename($url);
        if (is_file($file)) {
            return file_get_contents($file);
        } else {
            $html = $this->fetchFromGoodreads($url);
            file_put_contents($file, $html);
            return $html;
        }
    }

    private function getTestStoragePath() {
        return dirname(__FILE__) . '/../../../tests/responses/';
    }

    private function getSafeTestStorageFilename($str) {
        $str = mb_ereg_replace("([^\w\d\-_])", '', $str);
        $str = mb_ereg_replace("([\.]{2,})", '', $str);
        return $str;
    }

}
