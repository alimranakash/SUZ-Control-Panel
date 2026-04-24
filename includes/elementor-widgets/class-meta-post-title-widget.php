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
        $key      = $settings['key'];
        $popup_id = 9097;

        $users = get_post_meta( get_the_ID(), $key, true );

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
            jQuery(function($){
                var popupId = <?php echo (int) $popup_id; ?>;
                var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
                var nonce = '<?php echo esc_js( wp_create_nonce( 'suz_set_speaker_id_nonce' ) ); ?>';
                var modalId = 'suz-simple-speaker-popup';
                var styleId = 'suz-simple-speaker-popup-style';

                function ensureModal() {
                    if (!document.getElementById(styleId)) {
                        var style = document.createElement('style');
                        style.id = styleId;
                        style.textContent =
                            '#'+modalId+'{display:none;position:fixed;inset:0;z-index:999999;}' +
                            '#'+modalId+'.is-active{display:block;}' +
                            '#'+modalId+' .suz-simple-popup-overlay{position:absolute;inset:0;background:rgba(0,0,0,.7);}' +
                            '#'+modalId+' .suz-simple-popup-dialog{position:relative;max-width:980px;max-height:90vh;margin:4vh auto;background:#fff;overflow:auto;border-radius:10px;padding:20px;box-sizing:border-box;z-index:2;}' +
                            '#'+modalId+' .suz-simple-popup-close{position:absolute;right:10px;top:8px;border:0;background:transparent;font-size:28px;line-height:1;cursor:pointer;padding:0 6px;color:#000;}' +
                            '#'+modalId+' .suz-simple-popup-body{padding-top:10px;}' +
                            '#'+modalId+' .suz-popup-loading,#'+modalId+' .suz-popup-empty{text-align:center;padding:32px 16px;}';
                        document.head.appendChild(style);
                    }

                    if (!document.getElementById(modalId)) {
                        var modalHtml = '' +
                            '<div id="'+modalId+'" aria-hidden="true">' +
                                '<div class="suz-simple-popup-overlay"></div>' +
                                '<div class="suz-simple-popup-dialog" role="dialog" aria-modal="true">' +
                                    '<button type="button" class="suz-simple-popup-close" aria-label="Close">&times;</button>' +
                                    '<div class="suz-simple-popup-body"></div>' +
                                '</div>' +
                            '</div>';
                        document.body.insertAdjacentHTML('beforeend', modalHtml);
                    }
                }

                function openModal(contentHtml) {
                    ensureModal();
                    var modal = document.getElementById(modalId);
                    if (!modal) {
                        return;
                    }
                    var body = modal.querySelector('.suz-simple-popup-body');
                    if (body) {
                        body.innerHTML = contentHtml || '<div class="suz-popup-empty">No content found.</div>';
                    }
                    modal.classList.add('is-active');
                    document.body.style.overflow = 'hidden';

                    // Re-run Elementor frontend handlers for injected markup.
                    if (typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler && body) {
                        try {
                            elementorFrontend.elementsHandler.runReadyTrigger($(body));
                        } catch (e) {}
                    }
                }

                function closeModal() {
                    var modal = document.getElementById(modalId);
                    if (!modal) {
                        return;
                    }
                    modal.classList.remove('is-active');
                    document.body.style.overflow = '';
                }

                function filterSpeakerCard(html, speakerId) {
                    if (!html) {
                        return '';
                    }

                    var $wrap = $('<div>').html(html);
                    var selectors = [
                        '[data-post-id="'+speakerId+'"]',
                        '[data-id="'+speakerId+'"]',
                        '.post-'+speakerId,
                        '.elementor-post-'+speakerId,
                        '[class*="post-'+speakerId+'"]',
                        'a[href*="p='+speakerId+'"]'
                    ];

                    for (var i = 0; i < selectors.length; i++) {
                        var $match = $wrap.find(selectors[i]).first();
                        if ($match.length) {
                            var $card = $match.closest('article, .elementor-post, .e-loop-item, .swiper-slide, .slick-slide, .speaker-card, .etn-speaker-item, .elementor-widget-container');
                            if (!$card.length) {
                                $card = $match;
                            }

                            if ($card.length) {
                                return $('<div>').append($card.first().clone()).html();
                            }
                        }
                    }

                    return html;
                }

                function isMeaningfulHtml(html, speakerName) {
                    if (!html) {
                        return false;
                    }

                    var $tmp = $('<div>').html(html);
                    $tmp.find('script,style,noscript,link').remove();

                    var text = ($tmp.text() || '').replace(/\s+/g, ' ').trim();
                    var hasMedia = $tmp.find('img,video,iframe,svg').length > 0;

                    if (speakerName && text.toLowerCase().indexOf(String(speakerName).toLowerCase()) !== -1) {
                        return true;
                    }

                    return text.length > 30 || hasMedia;
                }

                function matchesTargetSpeaker(html, speakerId, speakerName) {
                    if (!html) {
                        return false;
                    }

                    var $tmp = $('<div>').html(html);
                    var hasIdMarker = $tmp.find(
                        '[data-post-id="'+speakerId+'"], ' +
                        '[data-id="'+speakerId+'"], ' +
                        '.post-'+speakerId+', ' +
                        '.elementor-post-'+speakerId+', ' +
                        '[class*="post-'+speakerId+'"], ' +
                        'a[href*="p='+speakerId+'"]'
                    ).length > 0;

                    if (hasIdMarker) {
                        return true;
                    }

                    if (!speakerName) {
                        return false;
                    }

                    var text = ($tmp.text() || '').replace(/\s+/g, ' ').trim().toLowerCase();
                    return text.indexOf(String(speakerName).toLowerCase()) !== -1;
                }

                function pickBestPopupHtml(data, speakerId, speakerName) {
                    var elementHtml = (data && data.element_html ? data.element_html : '').toString();
                    var fallbackHtml = (data && data.fallback_html ? data.fallback_html : '').toString();
                    var filteredHtml = filterSpeakerCard(elementHtml, speakerId);

                    if (matchesTargetSpeaker(filteredHtml, speakerId, speakerName) && isMeaningfulHtml(filteredHtml, speakerName)) {
                        return filteredHtml;
                    }
                    if (isMeaningfulHtml(fallbackHtml, speakerName)) {
                        return fallbackHtml;
                    }
                    if (matchesTargetSpeaker(elementHtml, speakerId, speakerName) && isMeaningfulHtml(elementHtml, speakerName)) {
                        return elementHtml;
                    }

                    return fallbackHtml || filteredHtml || elementHtml || '';
                }

                $(document)
                    .off('click.suzSpeakerPopup', '.suz-popup-btn')
                    .on('click.suzSpeakerPopup', '.suz-popup-btn', function(e){
                        e.preventDefault();

                        var speakerId = parseInt($(this).data('post-id'), 10) || 0;
                        var speakerName = $.trim($(this).text());
                        if (!speakerId) {
                            return;
                        }

                        openModal('<div class="suz-popup-loading">Loading speaker...</div>');

                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'set_speaker_id',
                                speaker_id: speakerId,
                                popup_id: popupId,
                                nonce: nonce
                            }
                        }).done(function(res){
                            if (!res || !res.success || !res.data) {
                                openModal('<div class="suz-popup-empty">Unable to load speaker details.</div>');
                                return;
                            }

                            var html = pickBestPopupHtml(res.data, speakerId, speakerName);
                            openModal(html);
                        }).fail(function(){
                            openModal('<div class="suz-popup-empty">Request failed. Please try again.</div>');
                        });
                    });

                $(document)
                    .off('click.suzSpeakerPopupClose', '#'+modalId+' .suz-simple-popup-close, #'+modalId+' .suz-simple-popup-overlay')
                    .on('click.suzSpeakerPopupClose', '#'+modalId+' .suz-simple-popup-close, #'+modalId+' .suz-simple-popup-overlay', function(){
                        closeModal();
                    });

                $(document)
                    .off('keydown.suzSpeakerPopupEsc')
                    .on('keydown.suzSpeakerPopupEsc', function(e){
                        if (e.key === 'Escape') {
                            closeModal();
                        }
                    });
            });
            </script>
            <?php
        } else {
            echo '<span class="meta-value">No data found</span>';
        }
    }
}
