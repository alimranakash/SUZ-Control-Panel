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
        return 'SUZ Meta Post Title';
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

        $users = get_post_meta( get_the_ID(), $key, true );

        if ( ! empty( $users ) ) {

            // যদি single value হয়, array বানাই
            if ( ! is_array( $users ) ) {
                $users = array( $users );
            }

            echo '<div class="suz-user-list">';

            if ( is_array( $users ) ) {
                $items = [];

                foreach ( $users as $user_id ) {
                    if ( is_numeric( $user_id ) && get_post( $user_id ) ) {
                        $items[] = '<a href="#elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3DeyJpZCI6IjkwOTQiLCJ0b2dnbGUiOmZhbHNlfQ%3D%3D"
                            class="suz-popup-btn"
                            data-post-id="' . esc_attr( $user_id ) . '">'
                            . esc_html( get_the_title( $user_id ) ) .
                        '</a>';
                    }
                }

                echo implode( ', ', $items );
            }

            echo '</div>';


            ?>
            <script>
            jQuery(document).ready(function($){

                $('.suz-popup-btn').on('click', function(e){
                    e.preventDefault();

                    var speaker_id = $(this).data('post-id');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'set_speaker_id',
                            speaker_id: speaker_id
                        },
                        success: function(res){

                            // popup open after save
                            elementorProFrontend.modules.popup.showPopup({ id: 9094 });

                            setTimeout(function(){
                                $(document).trigger('elementor/frontend/init');
                            }, 200);

                        }
                    });

                });

            });
            </script>
            <?php

        } else {
            echo '<span class="meta-value">No data found</span>';
        }
    }
}