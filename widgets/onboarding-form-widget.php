<?php
class Onboarding_Form_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'onboarding_form';
    }

    public function get_title() {
        return esc_html__('Onboarding Form', 'onboarding-form');
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_keywords() {
        return ['form', 'onboarding', 'contact'];
    }

    protected function register_controls() {
        // Add widget controls here if needed
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo do_shortcode('[onboarding_form]');
    }
} 