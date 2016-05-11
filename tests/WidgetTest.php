<?php

class WidgetTest extends WP_UnitTestCase {
    private $widget;
    
    public function setUp() {
        $this->widget = new gr_progress_cvdm_widget();
    }
    
    public function testGetBooks()
    {
        // Remove the following lines when you implement this test.
        $this->assertTrue(true);
    }
}