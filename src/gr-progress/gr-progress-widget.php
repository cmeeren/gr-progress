<?php

namespace relativisticramblings\gr_progress;

require_once('includes/gr_progress_cvdm_backend.php');

class gr_progress_cvdm_widget extends \WP_Widget {

    public $widget;

    function __construct() {
        parent::__construct(
                'gr_progress_cvdm_widget', 'GR Progress', ['description' => 'Displays reading progress and shelves from Goodreads.']
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

/**
 * @codeCoverageIgnore
 */
function load_widget() {
    register_widget('relativisticramblings\gr_progress\gr_progress_cvdm_widget');
    wp_enqueue_style('gr-progress-cvdm-style-default', plugin_dir_url(__FILE__) . 'css/style.css');
}

add_action('widgets_init', 'relativisticramblings\gr_progress\load_widget');
