<?php

namespace relativisticramblings\gr_progress;

require_once('GR_Progress_UnitTestCase.php');

/**
 * Tests values of saved settings based on user input in admin panel
 */
class SettingsTest extends GR_Progress_UnitTestCase {

    public function setUp() {
        GoodreadsFetcher::$test_local = true;
        GoodreadsFetcher::$test_fail = false;
        Shelf::$test_disableCoverFetching = false;
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

    public function test_displayReviewExcerpt() {
        $this->assertCheckedSettingCorrectlyUpdated('displayReviewExcerpt');
    }

    public function test_sortByReadingProgress() {
        $this->assertCheckedSettingCorrectlyUpdated('sortByReadingProgress');
    }

    public function test_displayProgressUpdateTime() {
        $this->assertCheckedSettingCorrectlyUpdated('displayProgressUpdateTime');
    }

    public function test_intervalTemplate() {
        $this->assertPassesAllTextInputTests('intervalTemplate');
        $this->assertSettingSavedAs('intervalTemplate', '{num} {period} ago', '{num} {period} ago');
    }

    public function test_intervalSingular_intervalPlural() {
        $intervalsInput = [' foobar1 ', ' foobar2', '<p>foobar3</p>', 'foobar4', '', 'foobar6', 'foobar7'];
        $intervalsExpectedSingular = ['foobar1', 'foobar2', '&lt;p&gt;foobar3&lt;/p&gt;', 'foobar4', 'hour', 'foobar6', 'foobar7'];
        $intervalsExpectedPlural = ['foobar1', 'foobar2', '&lt;p&gt;foobar3&lt;/p&gt;', 'foobar4', 'hours', 'foobar6', 'foobar7'];

        $newSettings = $this->getNewSettings(['intervalSingular' => $intervalsInput, 'intervalPlural' => $intervalsInput]);
        $this->assertEquals($newSettings['intervalSingular'], $intervalsExpectedSingular);
        $this->assertEquals($newSettings['intervalPlural'], $intervalsExpectedPlural);
    }
    
    public function test_useProgressBar() {
        // FIXME: implement
    }
    
    // FIXME: implement other tests

}
