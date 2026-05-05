<?php
/**
 * Meta Post Title Widget for Elementor
 *
 * @package SUZ Control Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class MetaPostTitleWidget extends \Elementor\Widget_Base {

    /**
     * Ensure tooltip CSS is printed once per request.
     *
     * @var bool
     */
    private static $tooltip_assets_printed = false;

    public function get_name() {
        return 'meta_post_title';
    }

    public function get_title() {
        return esc_html__( 'SUZ Meta Post Title', 'suz-control-panel' );
    }

    public function get_icon() {
        return 'eicon-post-title';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    protected function _register_controls() {
        // Content Tab.
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__( 'Content', 'suz-control-panel' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'key',
            [
                'label'       => esc_html__( 'Meta Key', 'suz-control-panel' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'description' => esc_html__( 'Enter the meta key to retrieve the post ID.', 'suz-control-panel' ),
            ]
        );

        $this->add_control(
            'enable_tooltip',
            [
                'label'        => esc_html__( 'Enable Tooltip', 'suz-control-panel' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'On', 'suz-control-panel' ),
                'label_off'    => esc_html__( 'Off', 'suz-control-panel' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'popup_template_id',
            [
                'label'       => esc_html__( 'Tooltip Content Template ID', 'suz-control-panel' ),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'min'         => 1,
                'default'     => 9118,
                'description' => esc_html__( 'Elementor template/page ID rendered inside tooltip.', 'suz-control-panel' ),
                'condition'   => [
                    'enable_tooltip' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'fallback_popup_template_id',
            [
                'label'       => esc_html__( 'Fallback Tooltip Template ID', 'suz-control-panel' ),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'min'         => 1,
                'default'     => '',
                'description' => esc_html__( 'Optional: Elementor template/page ID used when main tooltip data/template is unavailable.', 'suz-control-panel' ),
                'condition'   => [
                    'enable_tooltip' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'tooltip_panel_width',
            [
                'label'       => esc_html__( 'Tooltip Width (px)', 'suz-control-panel' ),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'min'         => 220,
                'max'         => 1200,
                'step'        => 10,
                'default'     => 560,
                'condition'   => [
                    'enable_tooltip' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'tooltip_panel_height',
            [
                'label'       => esc_html__( 'Tooltip Max Height (px)', 'suz-control-panel' ),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'min'         => 180,
                'max'         => 1200,
                'step'        => 10,
                'default'     => 560,
                'condition'   => [
                    'enable_tooltip' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab.
        $this->start_controls_section(
            'style_section',
            [
                'label' => esc_html__( 'Style', 'suz-control-panel' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'link_color',
            [
                'label'     => esc_html__( 'Link Color', 'suz-control-panel' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} a'                    => 'color: {{VALUE}};',
                    '{{WRAPPER}} .meta-value'          => 'color: {{VALUE}};',
                    '{{WRAPPER}} .suz-tooltip-trigger' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'typography',
                'selector' => '{{WRAPPER}} a, {{WRAPPER}} .meta-value, {{WRAPPER}} .suz-tooltip-trigger',
            ]
        );

        $this->end_controls_section();
    }

    private function print_tooltip_assets() {
        if ( self::$tooltip_assets_printed ) {
            return;
        }

        self::$tooltip_assets_printed = true;
        ?>
        <style id="suz-inline-tooltip-style">
            .suz-tooltip-item {
                position: relative;
                display: inline-block;
                align-items: baseline;
                vertical-align: baseline;
            }
            .suz-tooltip-trigger {
                background: transparent;
                border: 0;
                padding: 0;
                cursor: pointer;
                text-decoration: underline;
                text-underline-offset: 3px;
                text-decoration-thickness: 1px;
                font: inherit;
                color: inherit;
                line-height: inherit;
            }
            .suz-tooltip-item .suz-tooltip-trigger:hover {
                background: transparent;
                background-color: transparent;
            }
            .suz-tooltip-source {
                display: none !important;
            }
            .suz-global-tooltip {
                position: fixed;
                top: 0;
                left: 0;
                width: min(460px, calc(100vw - 20px));
                max-width: calc(100vw - 20px);
                max-height: 60vh;
                overflow: auto;
                padding: 16px;
                border: 1px solid #d8e0ea;
                border-radius: 14px;
                background: #ffffff;
                box-shadow: 0 20px 48px rgba(15, 23, 42, 0.18);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                z-index: 2147483000;
                transition: opacity 0.14s ease;
            }
            .suz-global-tooltip.is-visible {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }
            .suz-global-tooltip::before {
                content: '';
                position: absolute;
                top: -8px;
                left: var(--suz-arrow-left, 50%);
                width: 14px;
                height: 14px;
                background: #ffffff;
                border-top: 1px solid #d8e0ea;
                border-left: 1px solid #d8e0ea;
                transform: translateX(-50%) rotate(45deg);
            }
            .suz-global-tooltip.is-above::before {
                top: auto;
                bottom: -8px;
                border-top: 0;
                border-left: 0;
                border-right: 1px solid #d8e0ea;
                border-bottom: 1px solid #d8e0ea;
            }
            @media (max-width: 767px) {
                .suz-global-tooltip {
                    padding: 14px;
                }
            }
        </style>
        <script id="suz-inline-tooltip-script">
            (function() {
                if (window.suzInlineTooltipBound) {
                    return;
                }
                window.suzInlineTooltipBound = true;

                var hideTimer = null;
                var activeTrigger = null;
                var activeWidth = 560;
                var activeMaxHeight = 560;
                var tooltip = document.createElement('div');
                tooltip.className = 'suz-global-tooltip';
                tooltip.setAttribute('role', 'tooltip');
                tooltip.setAttribute('aria-hidden', 'true');
                var content = document.createElement('div');
                content.className = 'suz-global-tooltip__content';
                tooltip.appendChild(content);
                document.body.appendChild(tooltip);

                function clearHideTimer() {
                    if (!hideTimer) {
                        return;
                    }
                    clearTimeout(hideTimer);
                    hideTimer = null;
                }

                function hideTooltip() {
                    clearHideTimer();
                    if (activeTrigger) {
                        activeTrigger.setAttribute('aria-expanded', 'false');
                    }
                    activeTrigger = null;
                    tooltip.classList.remove('is-visible');
                    tooltip.classList.remove('is-above');
                    tooltip.setAttribute('aria-hidden', 'true');
                }

                function scheduleHide() {
                    clearHideTimer();
                    hideTimer = setTimeout(hideTooltip, 260);
                }

                function clamp(value, min, max) {
                    return Math.min(Math.max(value, min), max);
                }

                function positionTooltip(trigger) {
                    if (!trigger || !document.body.contains(trigger)) {
                        return;
                    }

                    var rect = trigger.getBoundingClientRect();
                    var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                    var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                    var sideGap = 10;
                    var offset = 8;
                    var desiredWidth = clamp(parseInt(activeWidth, 10) || 560, 220, 1200);
                    var desiredMaxHeight = clamp(parseInt(activeMaxHeight, 10) || 560, 180, 1200);

                    tooltip.style.width = Math.max(220, Math.min(desiredWidth, viewportWidth - (sideGap * 2))) + 'px';
                    tooltip.style.maxHeight = Math.max(180, Math.min(desiredMaxHeight, viewportHeight - (sideGap * 2))) + 'px';
                    tooltip.style.visibility = 'hidden';
                    tooltip.classList.add('is-visible');

                    var tooltipWidth = tooltip.offsetWidth;
                    var tooltipHeight = tooltip.offsetHeight;

                    var left = clamp(rect.left + (rect.width / 2) - (tooltipWidth / 2), sideGap, viewportWidth - tooltipWidth - sideGap);
                    var arrowLeft = clamp((rect.left + (rect.width / 2)) - left, 16, tooltipWidth - 16);
                    tooltip.style.setProperty('--suz-arrow-left', arrowLeft + 'px');

                    var top = rect.bottom + offset;
                    var placeAbove = false;

                    if ((top + tooltipHeight) > (viewportHeight - sideGap) && (rect.top - tooltipHeight - offset) >= sideGap) {
                        top = rect.top - tooltipHeight - offset;
                        placeAbove = true;
                    }

                    top = clamp(top, sideGap, Math.max(sideGap, viewportHeight - tooltipHeight - sideGap));

                    tooltip.style.left = left + 'px';
                    tooltip.style.top = top + 'px';
                    tooltip.classList.toggle('is-above', placeAbove);
                    tooltip.style.visibility = '';
                }

                function getSourceFromTrigger(trigger) {
                    if (!trigger) {
                        return null;
                    }

                    var item = trigger.closest('.suz-tooltip-item');
                    if (!item) {
                        return null;
                    }

                    return item.querySelector('.suz-tooltip-source');
                }

                function showTooltip(trigger) {
                    var source = getSourceFromTrigger(trigger);
                    if (!source) {
                        return;
                    }

                    clearHideTimer();
                    content.innerHTML = source.innerHTML;
                    if (activeTrigger && activeTrigger !== trigger) {
                        activeTrigger.setAttribute('aria-expanded', 'false');
                    }
                    activeTrigger = trigger;
                    activeWidth = parseInt(trigger.getAttribute('data-tooltip-width'), 10) || 560;
                    activeMaxHeight = parseInt(trigger.getAttribute('data-tooltip-height'), 10) || 560;
                    activeTrigger.setAttribute('aria-expanded', 'true');
                    tooltip.setAttribute('aria-hidden', 'false');
                    positionTooltip(trigger);
                }

                function maybeHideFromTriggerMouseOut(event) {
                    if (!activeTrigger) {
                        return;
                    }

                    var fromTrigger = event.target.closest('.suz-tooltip-trigger');
                    if (!fromTrigger || fromTrigger !== activeTrigger) {
                        return;
                    }

                    var related = event.relatedTarget;
                    if (related && (tooltip.contains(related) || activeTrigger.contains(related))) {
                        clearHideTimer();
                        return;
                    }

                    scheduleHide();
                }

                document.addEventListener('mouseover', function(event) {
                    var trigger = event.target.closest('.suz-tooltip-trigger');
                    if (!trigger) {
                        return;
                    }
                    showTooltip(trigger);
                });

                document.addEventListener('mouseout', maybeHideFromTriggerMouseOut);

                document.addEventListener('focusin', function(event) {
                    var trigger = event.target.closest('.suz-tooltip-trigger');
                    if (!trigger) {
                        return;
                    }
                    showTooltip(trigger);
                });

                document.addEventListener('focusout', function(event) {
                    var trigger = event.target.closest('.suz-tooltip-trigger');
                    if (!trigger || trigger !== activeTrigger) {
                        return;
                    }
                    scheduleHide();
                });

                tooltip.addEventListener('mouseenter', clearHideTimer);
                tooltip.addEventListener('mouseleave', scheduleHide);

                window.addEventListener('scroll', function() {
                    if (activeTrigger) {
                        positionTooltip(activeTrigger);
                    }
                }, true);

                window.addEventListener('resize', function() {
                    if (activeTrigger) {
                        positionTooltip(activeTrigger);
                    }
                });

                document.addEventListener('keydown', function(event) {
                    if ('Escape' === event.key) {
                        hideTooltip();
                    }
                });
            })();
        </script>
        <?php
    }

    private function has_displayable_content( $content ) {
        $content = (string) $content;

        if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
            return true;
        }

        return false !== stripos( $content, '<img' );
    }

    private function render_tooltip_template_content( $post_id, $template_id ) {
        if ( ! $template_id || ! class_exists( '\\Elementor\\Plugin' ) ) {
            return '';
        }

        $target_post = get_post( $post_id );

        if ( ! $target_post ) {
            return '';
        }

        global $post, $wp_query;
        $previous_post = $post;
        $previous_wp_query_post = null;
        $previous_queried_object = null;
        $previous_queried_object_id = 0;
        $previous_popup_speaker_id = isset( $GLOBALS['suz_current_popup_speaker_id'] ) ? absint( $GLOBALS['suz_current_popup_speaker_id'] ) : 0;

        if ( $wp_query instanceof \WP_Query ) {
            $previous_wp_query_post = isset( $wp_query->post ) ? $wp_query->post : null;
            $previous_queried_object = isset( $wp_query->queried_object ) ? $wp_query->queried_object : null;
            $previous_queried_object_id = isset( $wp_query->queried_object_id ) ? absint( $wp_query->queried_object_id ) : 0;
        }

        $GLOBALS['suz_current_popup_speaker_id'] = $target_post->ID;
        $post = $target_post;
        setup_postdata( $post );
        if ( $wp_query instanceof \WP_Query ) {
            $wp_query->post = $target_post;
            $wp_query->queried_object = $target_post;
            $wp_query->queried_object_id = $target_post->ID;
        }

        \Elementor\Plugin::$instance->frontend->enqueue_styles();
        $content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id, true );

        wp_reset_postdata();
        $post = $previous_post;
        if ( $wp_query instanceof \WP_Query ) {
            $wp_query->post = $previous_wp_query_post;
            $wp_query->queried_object = $previous_queried_object;
            $wp_query->queried_object_id = $previous_queried_object_id;
        }

        if ( $previous_popup_speaker_id ) {
            $GLOBALS['suz_current_popup_speaker_id'] = $previous_popup_speaker_id;
        } else {
            unset( $GLOBALS['suz_current_popup_speaker_id'] );
        }

        return (string) $content;
    }

    private function get_tooltip_content( $post_id, $popup_template_id, $fallback_popup_template_id = 0, $force_fallback = false ) {
        $popup_template_id = absint( $popup_template_id );
        $fallback_popup_template_id = absint( $fallback_popup_template_id );

        if ( $force_fallback ) {
            if ( $fallback_popup_template_id ) {
                $fallback_template_content = $this->render_tooltip_template_content( $post_id, $fallback_popup_template_id );
                if ( $this->has_displayable_content( $fallback_template_content ) ) {
                    return $fallback_template_content;
                }
            }
            return '';
        }

        if ( $popup_template_id ) {
            $content = $this->render_tooltip_template_content( $post_id, $popup_template_id );
            if ( $this->has_displayable_content( $content ) ) {
                return $content;
            }
        }

        if ( $fallback_popup_template_id ) {
            $fallback_template_content = $this->render_tooltip_template_content( $post_id, $fallback_popup_template_id );
            if ( $this->has_displayable_content( $fallback_template_content ) ) {
                return $fallback_template_content;
            }
        }

        return '';
    }

    private function build_tooltip_item( $label, $tooltip_content, $tooltip_width, $tooltip_height ) {
        $tooltip_width = max( 220, min( 1200, absint( $tooltip_width ) ) );
        $tooltip_height = max( 180, min( 1200, absint( $tooltip_height ) ) );

        return '<span class="suz-tooltip-item">'
            . '<button type="button" class="suz-tooltip-trigger meta-value" aria-haspopup="true" aria-expanded="false" data-tooltip-width="' . esc_attr( $tooltip_width ) . '" data-tooltip-height="' . esc_attr( $tooltip_height ) . '">' . esc_html( $label ) . '</button>'
            . '<div class="suz-tooltip-source" aria-hidden="true">' . $tooltip_content . '</div>'
            . '</span>';
    }

    protected function render() {
        $settings          = $this->get_settings_for_display();
        $key               = isset( $settings['key'] ) ? $settings['key'] : '';
        $enable_tooltip    = isset( $settings['enable_tooltip'] ) && 'yes' === $settings['enable_tooltip'];
        $post_id           = get_the_ID();
        $popup_template_id = isset( $settings['popup_template_id'] ) ? absint( $settings['popup_template_id'] ) : 0;
        $fallback_popup_template_id = isset( $settings['fallback_popup_template_id'] ) ? absint( $settings['fallback_popup_template_id'] ) : 0;
        $tooltip_width     = isset( $settings['tooltip_panel_width'] ) ? absint( $settings['tooltip_panel_width'] ) : 560;
        $tooltip_height    = isset( $settings['tooltip_panel_height'] ) ? absint( $settings['tooltip_panel_height'] ) : 560;

        $users    = get_post_meta( $post_id, $key, true );
        $suz_lsc  = get_post_meta( $post_id, 'suz_lecture_speaker_company', true );
        $suz_lsr  = get_post_meta( $post_id, 'suz_lecture_speaker_role', true );
        $suz_lsp  = get_post_meta( $post_id, 'suz_lecture_speaker_photo', true );
        $suz_lscl = get_post_meta( $post_id, 'suz_lecture_speaker_company_logo', true );
        $suz_lsb  = get_post_meta( $post_id, 'suz_lecture_speaker_bio', true );

        $has_fallback_data = ( '' !== trim( (string) $suz_lsc ) ) ||
            ( '' !== trim( (string) $suz_lsr ) ) ||
            ( '' !== trim( (string) $suz_lsp ) ) ||
            ( '' !== trim( (string) $suz_lscl ) ) ||
            ( '' !== trim( (string) $suz_lsb ) );

        if ( ! $enable_tooltip ) {
            if ( ! empty( $users ) ) {
                if ( ! is_array( $users ) ) {
                    $users = [ $users ];
                }

                $labels = [];

                foreach ( $users as $user_id ) {
                    if ( ! is_numeric( $user_id ) || ! get_post( $user_id ) ) {
                        continue;
                    }

                    $labels[] = get_the_title( absint( $user_id ) );
                }

                if ( ! empty( $labels ) ) {
                    echo '<span class="meta-value">' . esc_html( implode( ', ', $labels ) ) . '</span>';
                    return;
                }
            }

            if ( $has_fallback_data ) {
                $fallback_label = $suz_lsc ? $suz_lsc : __( 'Speaker details', 'suz-control-panel' );
                echo '<span class="meta-value">' . esc_html( $fallback_label ) . '</span>';
            } else {
                echo '<span class="meta-value">' . esc_html( $suz_lsc ) . '</span>';
            }
            return;
        }

        $should_print_tooltip_assets = false;

        if ( ! empty( $users ) ) {
            if ( ! is_array( $users ) ) {
                $users = [ $users ];
            }

            $items = [];

            foreach ( $users as $user_id ) {
                if ( ! is_numeric( $user_id ) || ! get_post( $user_id ) ) {
                    continue;
                }

                $user_id         = absint( $user_id );
                $tooltip_content = $this->get_tooltip_content( $user_id, $popup_template_id, $fallback_popup_template_id, false );

                if ( ! $this->has_displayable_content( $tooltip_content ) ) {
                    continue;
                }

                $items[] = $this->build_tooltip_item( get_the_title( $user_id ), $tooltip_content, $tooltip_width, $tooltip_height );
            }

            if ( ! empty( $items ) ) {
                echo '<div class="suz-user-list">' . implode( ', ', $items ) . '</div>';
                $should_print_tooltip_assets = true;
            } elseif ( $has_fallback_data ) {
                $fallback_label   = $suz_lsc ? $suz_lsc : __( 'Speaker details', 'suz-control-panel' );
                $tooltip_content  = $this->get_tooltip_content( $post_id, $popup_template_id, $fallback_popup_template_id, true );

                if ( $this->has_displayable_content( $tooltip_content ) ) {
                    echo $this->build_tooltip_item( $fallback_label, $tooltip_content, $tooltip_width, $tooltip_height );
                    $should_print_tooltip_assets = true;
                }
            }
        } else {
            if ( $has_fallback_data ) {
                $fallback_label  = $suz_lsc ? $suz_lsc : __( 'Speaker details', 'suz-control-panel' );
                $tooltip_content = $this->get_tooltip_content( $post_id, $popup_template_id, $fallback_popup_template_id, true );

                if ( $this->has_displayable_content( $tooltip_content ) ) {
                    echo $this->build_tooltip_item( $fallback_label, $tooltip_content, $tooltip_width, $tooltip_height );
                    $should_print_tooltip_assets = true;
                }
            } else {
                echo '<span class="meta-value">' . esc_html( $suz_lsc ) . '</span>';
            }
        }

        if ( $should_print_tooltip_assets ) {
            $this->print_tooltip_assets();
        }
    }
}
