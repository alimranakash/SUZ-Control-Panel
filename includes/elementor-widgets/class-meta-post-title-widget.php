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

        $this->add_control(
            'popup_id',
            [
                'label' => 'Popup ID',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'default' => 9094,
                'description' => 'Elementor Popup ID (opened via showPopup).',
            ]
        );

        $this->add_control(
            'popup_template_id',
            [
                'label' => 'Popup Content Template ID',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'default' => 9118,
                'description' => 'Elementor template/page ID rendered inside #popup-content.',
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
        $settings   = $this->get_settings_for_display();
        $key        = $settings['key'];
        $post_id    = get_the_ID();
        $popup_id   = isset( $settings['popup_id'] ) ? absint( $settings['popup_id'] ) : 0;
        $popup_template_id = isset( $settings['popup_template_id'] ) ? absint( $settings['popup_template_id'] ) : 0;

        $users = get_post_meta( $post_id, $key, true );
        $suz_lsc = get_post_meta( $post_id, 'suz_lecture_speaker_company', true );
        $suz_lsr = get_post_meta( $post_id, 'suz_lecture_speaker_role', true );
        $suz_lsp = get_post_meta( $post_id, 'suz_lecture_speaker_photo', true );
        $suz_lscl = get_post_meta( $post_id, 'suz_lecture_speaker_company_logo', true );
        $suz_lsb = get_post_meta( $post_id, 'suz_lecture_speaker_bio', true );
        $has_fallback_data = ( '' !== trim( (string) $suz_lsc ) ) ||
            ( '' !== trim( (string) $suz_lsr ) ) ||
            ( '' !== trim( (string) $suz_lsp ) ) ||
            ( '' !== trim( (string) $suz_lscl ) ) ||
            ( '' !== trim( (string) $suz_lsb ) );
        $should_print_popup_script = false;

        if ( ! empty( $users ) ) {
            // Convert single value to array for consistent rendering.
            if ( ! is_array( $users ) ) {
                $users = array( $users );
            }

            echo '<div class="suz-user-list">';

            if ( is_array( $users ) ) {
                $items = [];

                foreach ( $users as $user_id ) {
                    if ( is_numeric( $user_id ) && get_post( $user_id ) ) {
                        $items[] = '<a href="#"
                            class="suz-popup-btn"
                            data-post_id="' . esc_attr( $user_id ) . '"
                            data-popup_id="' . esc_attr( $popup_id ) . '"
                            data-popup_template_id="' . esc_attr( $popup_template_id ) . '">'
                            . esc_html( get_the_title( $user_id ) ) .
                        '</a>';
                    }
                }

                if ( ! empty( $items ) ) {
                    echo implode( ', ', $items );
                    $should_print_popup_script = true;
                } elseif ( $has_fallback_data && $popup_id ) {
                    $fallback_label = $suz_lsc ? $suz_lsc : __( 'Speaker details', 'suz-control-panel' );
                    echo '<a href="#"
                        class="suz-popup-btn meta-value"
                        data-post_id="' . esc_attr( $post_id ) . '"
                        data-popup_id="' . esc_attr( $popup_id ) . '"
                        data-popup_template_id="' . esc_attr( $popup_template_id ) . '"
                        data-is_fallback="1">'
                        . esc_html( $fallback_label ) .
                    '</a>';
                    $should_print_popup_script = true;
                }
            }

            echo '</div>';
        } else {
            if ( $has_fallback_data && $popup_id ) {
                $fallback_label = $suz_lsc ? $suz_lsc : __( 'Speaker details', 'suz-control-panel' );
                echo '<a href="#"
                    class="suz-popup-btn meta-value"
                    data-post_id="' . esc_attr( $post_id ) . '"
                    data-popup_id="' . esc_attr( $popup_id ) . '"
                    data-popup_template_id="' . esc_attr( $popup_template_id ) . '"
                    data-is_fallback="1">'
                    . esc_html( $fallback_label ) .
                '</a>';
                $should_print_popup_script = true;
            } else {
                echo '<span class="meta-value">' . esc_html( $suz_lsc ) . '</span>';
            }
        }

        if ( $should_print_popup_script ) {
            ?>
            <script>
                var suzPopupLoaderStyleId = 'suz-popup-loader-style';

                if (!document.getElementById(suzPopupLoaderStyleId)) {
                    const loaderStyle = document.createElement('style');
                    loaderStyle.id = suzPopupLoaderStyleId;
                    loaderStyle.textContent = `
                        .suz-popup-loader {
                            display: flex;
                            flex-direction: column;
                            gap: 14px;
                            padding: 18px;
                            border: 1px solid #e5e7eb;
                            border-radius: 14px;
                            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                        }
                        .suz-popup-loader__top {
                            display: flex;
                            align-items: center;
                            gap: 12px;
                        }
                        .suz-popup-loader__spinner {
                            width: 26px;
                            height: 26px;
                            border: 3px solid #dbe3ec;
                            border-top-color: #0ea5e9;
                            border-radius: 50%;
                            animation: suzLoaderSpin 0.85s linear infinite;
                            flex-shrink: 0;
                        }
                        .suz-popup-loader__title {
                            font-size: 15px;
                            font-weight: 600;
                            color: #0f172a;
                            line-height: 1.3;
                        }
                        .suz-popup-loader__subtitle {
                            font-size: 13px;
                            color: #475569;
                            margin-top: 2px;
                            line-height: 1.35;
                        }
                        .suz-popup-loader__skeleton {
                            display: flex;
                            flex-direction: column;
                            gap: 8px;
                        }
                        .suz-popup-loader__skeleton span {
                            display: block;
                            height: 10px;
                            border-radius: 999px;
                            background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
                            background-size: 220% 100%;
                            animation: suzLoaderShimmer 1.2s ease-in-out infinite;
                        }
                        .suz-popup-loader__skeleton span:nth-child(1) { width: 100%; }
                        .suz-popup-loader__skeleton span:nth-child(2) { width: 88%; }
                        .suz-popup-loader__skeleton span:nth-child(3) { width: 64%; }
                        @keyframes suzLoaderSpin {
                            to { transform: rotate(360deg); }
                        }
                        @keyframes suzLoaderShimmer {
                            0% { background-position: 100% 0; }
                            100% { background-position: -100% 0; }
                        }
                        @media (prefers-reduced-motion: reduce) {
                            .suz-popup-loader__spinner,
                            .suz-popup-loader__skeleton span {
                                animation: none;
                            }
                        }
                    `;
                    document.head.appendChild(loaderStyle);
                }

                function getPopupLoaderMarkup() {
                    return `
                        <div class="suz-popup-loader" role="status" aria-live="polite" aria-busy="true">
                            <div class="suz-popup-loader__top">
                                <span class="suz-popup-loader__spinner" aria-hidden="true"></span>
                                <div>
                                    <div class="suz-popup-loader__title">Loading content</div>
                                    <div class="suz-popup-loader__subtitle">Please wait, preparing speaker data...</div>
                                </div>
                            </div>
                            <div class="suz-popup-loader__skeleton" aria-hidden="true">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    `;
                }

                if (!window.suzPopupHandlerBound) {
                    window.suzPopupHandlerBound = true;
                    window.suzPopupIsLoading = false;

                    jQuery(document).on('mouseenter', '.suz-popup-btn', function(e) {
                        e.preventDefault();

                        if (window.suzPopupIsLoading) return;

                        window.suzPopupIsLoading = true;

                        var post_id = parseInt(jQuery(this).data('post_id'), 10) || 0;
                        var popup_id = parseInt(jQuery(this).data('popup_id'), 10) || 0;
                        var popup_template_id = parseInt(jQuery(this).data('popup_template_id'), 10) || 0;
                        var is_fallback = parseInt(jQuery(this).data('is_fallback'), 10) === 1 ? 1 : 0;

                        if (!post_id || !popup_id || (!is_fallback && !popup_template_id)) {
                            window.suzPopupIsLoading = false;
                            return;
                        }

                        elementorProFrontend.modules.popup.showPopup({ id: popup_id });

                        jQuery('#popup-content').html(getPopupLoaderMarkup());

                        jQuery.ajax({
                            url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
                            type: 'POST',
                            data: {
                                action: 'load_popup_content',
                                post_id: post_id,
                                popup_template_id: popup_template_id,
                                is_fallback: is_fallback
                            },
                            success: function(response) {
                                jQuery('#popup-content').html(response);
                            },
                            complete: function() {
                                window.suzPopupIsLoading = false;
                            }
                        });
                    });
                }
            </script>
            <?php
        }
    }
}
