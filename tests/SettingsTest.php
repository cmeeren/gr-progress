<?php

namespace relativisticramblings\gr_progress;

require_once('GR_Progress_UnitTestCase.php');

/**
 * Tests values of saved settings based on user input in admin panel
 */
class SettingsTest extends GR_Progress_UnitTestCase {

    public function setUp() {
        GoodreadsFetcher::$test_local = true;
        GoodreadsFetcher::$fail_if_url_matches = null;
        delete_transient('cvdm_gr_progress_disableFetchingUntil');
        delete_option("gr_progress_cvdm_coverURLs");
    }

    public function test_title() {
        $this->assertPassesAllTextInputTests('title');
    }

    public function test_goodreadsAttribution() {
        $this->assertPassesAllTextInputTests('goodreadsAttribution');
        $this->assertSettingSavedAs('goodreadsAttribution', '', 'Data from Goodreads');
    }

    public function test_userid_numeric() {
        $this->assertSettingSavedAs('userid', '55769144', '55769144');
        $this->assertSettingSavedAs('userid', '7691', '7691');
    }

    public function test_userid_numericAndName() {
        $this->assertSettingSavedAs('userid', '55769144-abcd', '55769144');
    }

    public function test_userid_profileURLNoName() {
        $this->assertSettingSavedAs('userid', 'https://www.goodreads.com/user/show/55769144', '55769144');
    }

    public function test_userid_profileURLWithName() {
        $this->assertSettingSavedAs('userid', 'https://www.goodreads.com/user/show/55769144-abcd', '55769144');
    }

    public function test_userid_profileURLWithNumericName() {
        $this->assertSettingSavedAs('userid', 'https://www.goodreads.com/user/show/55769144-1234', '55769144');
    }

    public function test_userid_profileURLIncomplete() {
        $this->assertSettingSavedAs('userid', 'goodreads.com/user/show/55769144', '55769144');
    }

    public function test_userid_invalid() {
        $this->assertSettingSavedAs('userid', 'foobar', '');
    }

    public function test_apiKey() {
        $this->assertPassesAllTextInputTests('apiKey');
    }

    public function test_shelfName() {
        $this->assertPassesAllTextInputTests('shelfName');
    }

    public function test_emptyMessage() {
        $this->assertPassesAllTextInputTests('emptyMessage');
    }

    public function test_coverSize() {
        $this->assertSettingSavedAs("coverSize", (string) CoverSize::SMALL, CoverSize::SMALL);
        $this->assertSettingSavedAs("coverSize", (string) CoverSize::LARGE, CoverSize::LARGE);
    }

    public function test_displayReviewExcerpt() {
        $this->assertCheckedSettingCorrectlyUpdated('displayReviewExcerpt');
    }

    public function test_maxBooks() {
        $this->assertSettingSavedAs("maxBooks", "1", 1);
        $this->assertSettingSavedAs("maxBooks", "20", 20);
        $this->assertSettingSavedAs("maxBooks", "200", 200);
        $this->assertSettingSavedAs("maxBooks", "0", 1);

        // invalid values revert to default setting, might change in the future
        $this->assertSettingSavedAs("maxBooks", "-1", 3);
        $this->assertSettingSavedAs("maxBooks", "foo", 3);
    }

    public function test_sortByReadingProgress() {
        $this->assertCheckedSettingCorrectlyUpdated('sortByReadingProgress');
    }

    public function test_sortOrder() {
        $this->assertSettingSavedAs('sortBy', 'date_updated', 'date_updated');
        $this->assertSettingSavedAs('sortBy', 'position', 'position');
        $this->assertSettingSavedAs('sortBy', 'foobar', 'date_updated');
    }

    public function test_sortBy() {
        $this->assertSettingSavedAs('sortOrder', 'a', 'a');
        $this->assertSettingSavedAs('sortOrder', 'd', 'd');
        $this->assertSettingSavedAs('sortOrder', 'foobar', 'd');
    }

    public function test_progressType() {
        $this->assertSettingSavedAs("progressType", (string) Progress::DISABLED, Progress::DISABLED);
        $this->assertSettingSavedAs("progressType", (string) Progress::PROGRESSBAR, Progress::PROGRESSBAR);
        $this->assertSettingSavedAs("progressType", (string) Progress::TEXT, Progress::TEXT);
    }

    public function test_displayProgressUpdateTime() {
        $this->assertCheckedSettingCorrectlyUpdated('displayProgressUpdateTime');
    }

    public function test_intervalTemplate() {
        $this->assertPassesAllTextInputTests('intervalTemplate');
        $this->assertSettingSavedAs('intervalTemplate', 'updated {num} {period} ago', 'updated {num} {period} ago');
        $this->assertSettingSavedAs('intervalTemplate', ' ', $this->DEFAULT_SETTINGS['intervalTemplate']);
    }

    public function test_intervalSingular_intervalPlural() {
        $intervalsInput = [' foobar1 ', ' foobar2', '<p>foobar3</p>', 'foobar4', '', 'foobar6', 'foobar7'];
        $intervalsExpectedSingular = ['foobar1', 'foobar2', '&lt;p&gt;foobar3&lt;/p&gt;', 'foobar4', 'hour', 'foobar6', 'foobar7'];
        $intervalsExpectedPlural = ['foobar1', 'foobar2', '&lt;p&gt;foobar3&lt;/p&gt;', 'foobar4', 'hours', 'foobar6', 'foobar7'];

        $newSettings = $this->getNewSettings(['intervalSingular' => $intervalsInput, 'intervalPlural' => $intervalsInput]);
        $this->assertEquals($newSettings['intervalSingular'], $intervalsExpectedSingular);
        $this->assertEquals($newSettings['intervalPlural'], $intervalsExpectedPlural);
    }

    public function test_cacheTimeInHours() {
        $this->assertSettingSavedAs("cacheTimeInHours", "1", 1);
        $this->assertSettingSavedAs("cacheTimeInHours", "20", 20);
        $this->assertSettingSavedAs("cacheTimeInHours", "200", 200);
        $this->assertSettingSavedAs("cacheTimeInHours", "0", 0);

        $this->assertSettingSavedAs("cacheTimeInHours", "-1", $this->DEFAULT_SETTINGS['cacheTimeInHours']);
        $this->assertSettingSavedAs("cacheTimeInHours", "foo", $this->DEFAULT_SETTINGS['cacheTimeInHours']);
        $this->assertSettingSavedAs("cacheTimeInHours", "", $this->DEFAULT_SETTINGS['cacheTimeInHours']);
    }

    public function test_cacheDeletedAfterSavingSettings() {
        $settings = $this->DEFAULT_SETTINGS;
        $settings['title'] = rand(0, 10000) . microtime();

        $widget = new gr_progress_cvdm_widget();

        // get parsed settings
        $parsedSettings = $widget->update($settings, $settings);

        // cache widget HTML
        ob_start();
        $widget->widget($this->DEFAULT_ARGS, $parsedSettings);
        ob_end_clean();

        // check that widget HTML is cached
        $key1 = $widget->widget->getWidgetKey();
        $this->assertTrue(get_transient($key1) !== false, 'Expected to find cached HTML but found none');

        // update widget settings (cache should then be deleted)
        $widget->update($parsedSettings, $parsedSettings);

        // we should not have the transient anymore
        $key2 = $widget->widget->getWidgetKey();
        $this->assertEquals($key1, $key2, 'Widget keys unexpectedly differ; cannot proceed with testing');
        $this->assertFalse(get_transient($key2), 'Found unexpected cached HTML');
    }

    public function test_deleteCoverURLCacheOnSave() {
        $settings = $this->DEFAULT_SETTINGS;
        $settings['title'] = rand(0, 10000) . microtime();
        $settings['deleteCoverURLCacheOnSave'] = true;

        $widget = new gr_progress_cvdm_widget();

        // get parsed settings
        $parsedSettings = $widget->update($settings, $settings);

        // cache covers and check that all books have covers
        ob_start();
        $widget->widget($this->DEFAULT_ARGS, $parsedSettings);
        $html1 = ob_get_clean();
        $this->assertAllBooksHaveCoverImage($html1);

        // disable cover fetching
        GoodreadsFetcher::$fail_if_url_matches = $this->RE_FAIL_FETCH_COVER;

        // covers should still be present since they are cached
        ob_start();
        $widget->widget($this->DEFAULT_ARGS, $parsedSettings);
        $html2 = ob_get_clean();
        $this->assertAllBooksHaveCoverImage($html2);
    }

}
