<?php

namespace relativisticramblings\gr_progress;

class GoodreadsFetcher {

    private static $DEFAULT_TIMEOUT_IN_SECONDS = 3;
    private static $SECONDS_TO_WAIT_AFTER_FAILED_FETCH = 3600;
    public static $test_local = false;

    /**
     * Force fetch to fail when regex matches this string.
     * Used for testing
     * @var string
     */
    public static $fail_if_url_matches = null;

    public static function fetch($url) {

        if (get_transient('cvdm_gr_progress_disableFetchingUntil') !== false) {
            return false;
        }

        if (!empty(self::$fail_if_url_matches) && preg_match(self::$fail_if_url_matches, $url)) {
            $result = false;
        } elseif (self::$test_local) {
            $result = self::fetchFromFile($url);
        } else {
            $result = self::fetchFromGoodreads($url);
        }

        if ($result === false) {
            set_transient('cvdm_gr_progress_disableFetchingUntil', time() + self::$SECONDS_TO_WAIT_AFTER_FAILED_FETCH, self::$SECONDS_TO_WAIT_AFTER_FAILED_FETCH);
        }

        return $result;
    }

    private static function fetchFromGoodreads($url) {
        $ctx = stream_context_create(['http' => ['timeout' => self::$DEFAULT_TIMEOUT_IN_SECONDS]]);
        return file_get_contents($url, false, $ctx);
    }

    private static function fetchFromFile($url) {
        $file = self::getTestStoragePath() . self::getSafeTestStorageFilename($url);
        if (is_file($file)) {
            return file_get_contents($file);
        } else {
            $html = self::fetchFromGoodreads($url);
            file_put_contents($file, $html);
            return $html;
        }
    }

    private static function getTestStoragePath() {
        return dirname(__FILE__) . '/../../../tests/responses/';
    }

    private static function getSafeTestStorageFilename($str) {
        $str = mb_ereg_replace("([^\w\d\-_])", '', $str);
        $str = mb_ereg_replace("([\.]{2,})", '', $str);
        return $str;
    }

}
