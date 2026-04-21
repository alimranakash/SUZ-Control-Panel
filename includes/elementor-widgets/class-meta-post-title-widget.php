<?php
/**
 * Meta Post Title Widget for Elementor
 *
 * @package SUZ Control Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class MetaPostTitleWidget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'meta_post_title';
    }

    public function get_title() {
        return 'Meta Post Title';
    }

    public function get_icon() {
        return 'eicon-post-title';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        // Content Tab
        $this->start_controls_section(
            'content_section',
            [
                'label' => 'Content',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'key',
            [
                'label' => 'Meta Key',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'description' => 'Enter the meta key to retrieve the post ID.',
            ]
        );

        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section(
            'style_section',
            [
                'label' => 'Style',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'link_color',
            [
                'label' => 'Link Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} a' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .meta-value' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'typography',
                'selector' => '{{WRAPPER}} a, {{WRAPPER}} .meta-value',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $key = $settings['key'];

        $value = get_post_meta( get_the_ID(), $key, true );

        if ( $value && is_numeric( $value ) && get_post( $value ) ) {
            // It's a post ID
            echo '<a href="' . esc_url( get_permalink( $value ) ) . '">' . esc_html( get_the_title( $value ) ) . '</a>';
        } elseif ( $value ) {
            // It's a direct value
            echo '<span class="meta-value">' . esc_html( $value ) . '</span>';
        }
    }
}