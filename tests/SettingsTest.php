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
        delete_transient('cvdm_gr_progress_goodreadsFetchFail');
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

        // invalid values revert to default setting, might change in the future
        $this->assertSettingSavedAs("cacheTimeInHours", "-1", 24);
        $this->assertSettingSavedAs("cacheTimeInHours", "foo", 24);
    }

}
