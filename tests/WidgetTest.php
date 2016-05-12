<?php

require_once('HTML5Validate.php');
require_once('GR_Progress_UnitTestCase.php');

define("GR_PROGRESS_TESTING", true);

class WidgetTest extends GR_Progress_UnitTestCase {

    public function testValidHTML() {
        $html = $this->getWidgetHTML();
        $this->assertIsValidHTML($html);
    }

    public function testTitle() {
        $html = $this->getWidgetHTML(['title' => 'CUSTOM_TITLE_FOOBAR']);
        $this->assertRegExp("/CUSTOM_TITLE_FOOBAR/", $html);
    }

    public function testBooksDefault() {
        $html = $this->getWidgetHTML();
        $this->assertOrderedBookTitlesOnPrimaryShelfContains(["The Lord of the Rings", "A Game of Thrones", "The Chronicles of Narnia"], $html);
    }

}
