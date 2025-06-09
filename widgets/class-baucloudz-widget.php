<?php
namespace BauCloudz;

use Elementor\Widget_Base;

class BauCloudz_Widget extends Widget_Base {
    public function get_name() {
        return 'baucloudz_widget';
    }

    public function get_title() {
        return __( 'BauCloudz Widget', 'baucloudz' );
    }

    public function get_icon() {
        return 'eicon-code';
    }

    public function get_categories() {
        return [ 'basic' ];
    }

    protected function _register_controls() {
        // Registra i controlli del widget se necessario
    }

    protected function render() {
        // Includi il template HTML
        include plugin_dir_path( __FILE__ ) . '/templates/widget-template.php';
    }
}
?>
