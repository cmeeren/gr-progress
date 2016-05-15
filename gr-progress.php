<?php

/* Plugin Name: GR Progress
  Description: Displays shelves and reading progress from Goodreads.
  Version: 1.0.0
  Author: Christer van der Meeren
  Author URI: http://relativisticramblings.com
  License: MIT
 */

require_once('gr_progress_cvdm_backend.php');

class gr_progress_cvdm_widget extends WP_Widget {

    private $widget;

    function __construct() {
        parent::__construct(
                'gr_progress_cvdm_widget', 'GR progress', ['description' => 'Displays reading progress and shelves from Goodreads.']
        );
        $this->widget = new gr_progress_cvdm_backend($this);
    }

    public function widget($args, $instance) {
        $this->widget->printWidget($args, $instance);
    }

    public function form($instance) {
        $this->widget->printForm($instance);
    }

    public function update($new_instance, $old_instance) {
        return $this->widget->getNewWidgetSettings($new_instance, $old_instance);
    }

}

// Register and load the widget
function gr_progress_cvdm_load_widget() {
    register_widget('gr_progress_cvdm_widget');
    wp_enqueue_style('gr-progress-cvdm-style-default', plugin_dir_url(__FILE__) . 'style.css');
}

add_action('widgets_init', 'gr_progress_cvdm_load_widget');
