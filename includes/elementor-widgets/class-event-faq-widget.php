<?php
/**
 * Event FAQ Widget for Elementor
 *
 * @package SUZ_Control_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class SUZ_Event_FAQ_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'suz_event_faq';
    }

    public function get_title() {
        return esc_html__( 'SUZ Event FAQ', 'suz-control-panel' );
    }

    public function get_icon() {
        return 'eicon-accordion';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    public function get_style_depends() {
        return [ 'suz-event-faq-widget' ];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__( 'Content', 'suz-control-panel' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'faq_meta_key',
            [
                'label' => esc_html__( 'ACF Repeater Meta Key', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'suz_event_faq',
                'placeholder' => 'suz_event_faq',
            ]
        );

        $this->add_control(
            'question_sub_field',
            [
                'label' => esc_html__( 'Question (Title) Sub Field Name', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'suz_faq_question',
                'placeholder' => 'suz_faq_question',
            ]
        );

        $this->add_control(
            'answer_sub_field',
            [
                'label' => esc_html__( 'Answer (Editor) Sub Field Name', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'suz_faq_answer',
                'placeholder' => 'suz_faq_answer',
            ]
        );

        $this->add_control(
            'open_first_item',
            [
                'label' => esc_html__( 'Open First Item', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'question',
            [
                'label' => esc_html__( 'Question (Title)', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'Sample FAQ question', 'suz-control-panel' ),
            ]
        );

        $repeater->add_control(
            'answer',
            [
                'label' => esc_html__( 'Answer (Editor)', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::WYSIWYG,
                'default' => esc_html__( 'Sample FAQ answer.', 'suz-control-panel' ),
            ]
        );

        $this->add_control(
            'fallback_faq_items',
            [
                'label' => esc_html__( 'Fallback FAQ Items (If ACF Empty)', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'title_field' => '{{{ question }}}',
            ]
        );

        $this->add_control(
            'empty_message',
            [
                'label' => esc_html__( 'Empty Message', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'No FAQs found.', 'suz-control-panel' ),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'style_wrapper_section',
            [
                'label' => esc_html__( 'FAQ Items', 'suz-control-panel' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'item_background',
            [
                'label' => esc_html__( 'Item Background', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-item' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'item_border',
                'selector' => '{{WRAPPER}} .suz-faq-item',
            ]
        );

        $this->add_control(
            'item_border_radius',
            [
                'label' => esc_html__( 'Item Border Radius', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'item_gap',
            [
                'label' => esc_html__( 'Items Gap', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 60,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-item + .suz-faq-item' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'style_question_section',
            [
                'label' => esc_html__( 'Question', 'suz-control-panel' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'question_color',
            [
                'label' => esc_html__( 'Text Color', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-question-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'question_background',
            [
                'label' => esc_html__( 'Background', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-question' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'question_typography',
                'selector' => '{{WRAPPER}} .suz-faq-question-text',
            ]
        );

        $this->add_control(
            'question_padding',
            [
                'label' => esc_html__( 'Padding', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-question' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'style_answer_section',
            [
                'label' => esc_html__( 'Answer', 'suz-control-panel' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'answer_color',
            [
                'label' => esc_html__( 'Text Color', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-answer' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'answer_background',
            [
                'label' => esc_html__( 'Background', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-answer' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'answer_typography',
                'selector' => '{{WRAPPER}} .suz-faq-answer',
            ]
        );

        $this->add_control(
            'answer_padding',
            [
                'label' => esc_html__( 'Padding', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-answer' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'style_icon_section',
            [
                'label' => esc_html__( 'Toggle Icon', 'suz-control-panel' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'icon_color',
            [
                'label' => esc_html__( 'Icon Color', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-icon' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'icon_background',
            [
                'label' => esc_html__( 'Icon Background', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-icon' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'icon_size',
            [
                'label' => esc_html__( 'Icon Size', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range' => [
                    'px' => [
                        'min' => 14,
                        'max' => 40,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: calc({{SIZE}}{{UNIT}} + 8px); height: calc({{SIZE}}{{UNIT}} + 8px);',
                ],
            ]
        );

        $this->add_control(
            'icon_radius',
            [
                'label' => esc_html__( 'Icon Border Radius', 'suz-control-panel' ),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .suz-faq-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();

        if ( ! $post_id ) {
            $post_id = get_queried_object_id();
        }

        if ( ! $post_id ) {
            return;
        }

        $meta_key = ! empty( $settings['faq_meta_key'] ) ? sanitize_key( $settings['faq_meta_key'] ) : 'suz_event_faq';
        $question_sub_field = ! empty( $settings['suz_faq_question'] ) ? sanitize_key( $settings['suz_faq_question'] ) : 'suz_faq_question';
        $answer_sub_field = ! empty( $settings['suz_faq_answer'] ) ? sanitize_key( $settings['suz_faq_answer'] ) : 'suz_faq_answer';

        $faq_items = $this->get_acf_repeater_faq_items( $post_id, $meta_key, $question_sub_field, $answer_sub_field );

        if ( empty( $faq_items ) && ! empty( $settings['fallback_faq_items'] ) && is_array( $settings['fallback_faq_items'] ) ) {
            $faq_items = $this->normalize_faq_items( $settings['fallback_faq_items'] );
        }

        if ( empty( $faq_items ) ) {
            if ( ! empty( $settings['empty_message'] ) ) {
                echo '<p class="suz-faq-empty">' . esc_html( $settings['empty_message'] ) . '</p>';
            }
            return;
        }

        $open_first_item = ( isset( $settings['open_first_item'] ) && 'yes' === $settings['open_first_item'] );

        echo '<div class="suz-faq-widget">';

        foreach ( $faq_items as $index => $item ) {
            $question = ! empty( $item['question'] ) ? wp_strip_all_tags( $item['question'] ) : esc_html__( 'FAQ', 'suz-control-panel' );
            $answer = ! empty( $item['answer'] ) ? $this->prepare_answer_markup( $item['answer'] ) : '';
            $open = ( $open_first_item && 0 === $index ) ? ' open' : '';

            echo '<details class="suz-faq-item"' . $open . '>';
            echo '<summary class="suz-faq-question">';
            echo '<span class="suz-faq-question-text">' . esc_html( $question ) . '</span>';
            echo '<span class="suz-faq-icon" aria-hidden="true"></span>';
            echo '</summary>';
            echo '<div class="suz-faq-answer">' . $answer . '</div>';
            echo '</details>';
        }

        echo '</div>';
    }

    /**
     * Build FAQ rows from ACF repeater data.
     *
     * @param int    $post_id Post ID.
     * @param string $meta_key ACF repeater meta key.
     * @param string $question_sub_field Question sub field key.
     * @param string $answer_sub_field Answer sub field key.
     * @return array
     */
    private function get_acf_repeater_faq_items( $post_id, $meta_key, $question_sub_field, $answer_sub_field ) {
        $items = [];

        if ( function_exists( 'have_rows' ) ) {
            $question_candidates = array_unique(
                array_filter(
                    [
                        $question_sub_field,
                        'suz_faq_question',
                        'question',
                        'title',
                    ]
                )
            );

            $answer_candidates = array_unique(
                array_filter(
                    [
                        $answer_sub_field,
                        'suz_faq_answer',
                        'answer',
                        'content',
                    ]
                )
            );

            if ( have_rows( $meta_key, $post_id ) ) {
                while ( have_rows( $meta_key, $post_id ) ) {
                    the_row();

                    $question = '';
                    $answer = '';

                    foreach ( $question_candidates as $candidate ) {
                        $value = get_sub_field( $candidate );
                        if ( '' !== trim( wp_strip_all_tags( (string) $value ) ) ) {
                            $question = $value;
                            break;
                        }
                    }

                    foreach ( $answer_candidates as $candidate ) {
                        $value = get_sub_field( $candidate );
                        if ( '' !== trim( wp_strip_all_tags( (string) $value ) ) ) {
                            $answer = $value;
                            break;
                        }
                    }

                    $items[] = [
                        'question' => $question,
                        'answer' => $answer,
                    ];
                }

                $items = $this->normalize_faq_items( $items );
            }
        }

        if ( ! empty( $items ) ) {
            return $items;
        }

        if ( function_exists( 'get_field' ) ) {
            $acf_rows = get_field( $meta_key, $post_id );

            if ( is_array( $acf_rows ) ) {
                foreach ( $acf_rows as $row ) {
                    if ( ! is_array( $row ) ) {
                        continue;
                    }

                    $items[] = [
                        'question' => isset( $row[ $question_sub_field ] ) ? $row[ $question_sub_field ] : '',
                        'answer' => isset( $row[ $answer_sub_field ] ) ? $row[ $answer_sub_field ] : '',
                    ];
                }
            }
        }

        $items = $this->normalize_faq_items( $items );

        if ( ! empty( $items ) ) {
            return $items;
        }

        $row_count = (int) get_post_meta( $post_id, $meta_key, true );

        if ( $row_count <= 0 ) {
            return [];
        }

        $fallback_items = [];

        for ( $i = 0; $i < $row_count; $i++ ) {
            $fallback_items[] = [
                'question' => get_post_meta( $post_id, $meta_key . '_' . $i . '_' . $question_sub_field, true ),
                'answer' => get_post_meta( $post_id, $meta_key . '_' . $i . '_' . $answer_sub_field, true ),
            ];
        }

        return $this->normalize_faq_items( $fallback_items );
    }

    /**
     * Remove empty FAQ rows and normalize data shape.
     *
     * @param array $items FAQ rows.
     * @return array
     */
    private function normalize_faq_items( $items ) {
        $normalized = [];

        if ( ! is_array( $items ) ) {
            return $normalized;
        }

        foreach ( $items as $item ) {
            $question = isset( $item['question'] ) ? (string) $item['question'] : '';
            $answer = isset( $item['answer'] ) ? (string) $item['answer'] : '';

            if ( '' === trim( wp_strip_all_tags( $question ) ) && '' === trim( wp_strip_all_tags( $answer ) ) ) {
                continue;
            }

            $normalized[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $normalized;
    }

    /**
     * Make safe and readable answer markup.
     *
     * @param string $answer Answer string.
     * @return string
     */
    private function prepare_answer_markup( $answer ) {
        $answer = (string) $answer;

        if ( '' === trim( $answer ) ) {
            return '';
        }

        if ( preg_match( '/<[^>]+>/', $answer ) ) {
            return wp_kses_post( $answer );
        }

        return wp_kses_post( wpautop( $answer ) );
    }
}
