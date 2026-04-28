<?php
/**
 * Plugin Name: SUZ Control Panel
 * Plugin URI:  https://magicmedia.sk/ 
 * Description: Central dashboard to manage SUZ-related plugins & features.
 * Version:     1.2.3
 * Author:      Magicmedia
 * Text Domain: suz-control-panel
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly for security
}

add_filter( 'woocommerce_registration_auth_new_customer', '__return_false' );

// Include and control wp forms or any form easily
require_once plugin_dir_path( __FILE__ ) . 'includes/config.php';

// Include helper functions once
require_once plugin_dir_path( __FILE__ ) . 'includes/helpers.php';

// Include admin pages and logger functions
require_once plugin_dir_path( __FILE__ ) . 'admin/admin-pages.php';   // Admin page renderers including IČO Verification Tracker page

// Admin page renderers including Ticket Control page
require_once plugin_dir_path(__FILE__) . 'includes/ticket-control.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/suz-logger.php';    // Logger functions

require_once plugin_dir_path( __FILE__ ) . 'includes/woocommerce-checkout.php';  // Checkout Process Control

require_once plugin_dir_path( __FILE__ ) . 'includes/role-manager.php'; // Role management

require_once plugin_dir_path( __FILE__ ) . 'includes/profile-completion.php';  //Profile completiong & update management 

require_once plugin_dir_path( __FILE__ ) . 'includes/account-update-handler.php';  //Profile completiong & update management 

require_once plugin_dir_path( __FILE__ ) . 'includes/eve-woo-combo.php'; // Eventin Woocommerce Combo

 // Customer profile decoram
require_once plugin_dir_path( __FILE__ ) . 'includes/customers-profile.php'; //

 // Mailchimp tag sync
require_once plugin_dir_path( __FILE__ ) . 'includes/mailchimp-tag-sync.php'; //

 // Login Registration & Profile Condition Set
require_once plugin_dir_path( __FILE__ ) . 'includes/log-reg-profile.php'; //

$suz_konferencie_file = plugin_dir_path( __FILE__ ) . 'suz-konferencie/suz-konferencie.php';
if ( file_exists( $suz_konferencie_file ) ) {
    require_once $suz_konferencie_file;
}


// Load admin notifications
if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'admin/admin-notifications.php';
    require_once plugin_dir_path( __FILE__ ) . 'admin/admin-columns.php'; // Task 7: Admin List Columns + Filtering
}

require_once plugin_dir_path( __FILE__ ) . 'import-users-email-passwords.php';

add_action( 'plugins_loaded', 'suz_load_textdomain' );
function suz_load_textdomain() {
    load_plugin_textdomain(
        'suz-control-panel',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}

// Hook into WordPress admin menu to register SUZ Control Panel menus and pages
add_action( 'admin_menu', 'suz_panel_add_admin_menu' );

/**
 * Register the main menu and submenu pages for SUZ Control Panel
 */
function suz_panel_add_admin_menu() {
    add_menu_page(
        esc_html__( 'SUZ Control Panel', 'suz-control-panel' ),
        esc_html__( 'SUZ Control Panel', 'suz-control-panel' ),
        'manage_options',
        'suz-control-panel',
        'suz_panel_render_dashboard_page', // Callback from admin-pages.php
        'dashicons-admin-generic',
        100
    );

    add_submenu_page(
        'suz-control-panel',
        esc_html__( 'Dashboard', 'suz-control-panel' ),
        esc_html__( 'Dashboard', 'suz-control-panel' ),
        'manage_options',
        'suz-control-panel',
        'suz_panel_render_dashboard_page'
    );

    add_submenu_page(
        'suz-control-panel',
        esc_html__( 'SUZ Settings', 'suz-control-panel' ),
        esc_html__( 'SUZ Settings', 'suz-control-panel' ),
        'manage_options',
        'suz-settings',
        'suz_panel_render_settings_page'
    );

    add_submenu_page(
        'suz-control-panel',
        esc_html__( 'IČO Verification Tracker', 'suz-control-panel' ),
        esc_html__( 'IČO vTracker', 'suz-control-panel' ),
        'manage_options',
        'suz-ico-verification-tracker',
        'suz_panel_render_ico_verification_page'
    );
    
        add_submenu_page(
        'suz-control-panel',
        esc_html__( 'Ticket Control', 'suz-control-panel' ),
        esc_html__( 'Ticket Control', 'suz-control-panel' ),
        'manage_options',
        'suz-ticket-control',
        'suz_panel_render_ticket_control_page'
    );

    add_submenu_page(
        'suz-control-panel',
        esc_html__( 'Debug Logs', 'suz-control-panel' ),
        esc_html__( 'Debug Logs', 'suz-control-panel' ),
        'manage_options',
        'suz-debug-logs',
        'suz_panel_render_debug_logs_page'
    );

    add_submenu_page(
        'suz-control-panel',
        esc_html__( 'Important Links', 'suz-control-panel' ),
        esc_html__( 'Important Links', 'suz-control-panel' ),
        'manage_options',
        'suz-important-links',
        'suz_panel_render_important_links_page'
    );
}

// Register Elementor widgets
add_action( 'elementor/widgets/register', function() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/elementor-widgets/class-meta-post-title-widget.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/elementor-widgets/class-event-faq-widget.php';
    \Elementor\Plugin::instance()->widgets_manager->register( new MetaPostTitleWidget() );
    \Elementor\Plugin::instance()->widgets_manager->register( new SUZ_Event_FAQ_Widget() );
} );

add_action( 'elementor/frontend/after_register_styles', function() {
    $style_path = plugin_dir_path( __FILE__ ) . 'assets/css/elementor-event-faq-widget.css';

    if ( file_exists( $style_path ) ) {
        wp_register_style(
            'suz-event-faq-widget',
            plugin_dir_url( __FILE__ ) . 'assets/css/elementor-event-faq-widget.css',
            [],
            filemtime( $style_path )
        );
    }
} );

add_action( 'elementor/query/event_lectures', function( $query ) {

    $lecture_ids = get_post_meta( get_the_ID(), 'suz_event_lectures', true );

    if ( ! empty( $lecture_ids ) ) {

        if ( ! is_array( $lecture_ids ) ) {
            $lecture_ids = array( $lecture_ids );
        }

        $query->set( 'post_type', 'suz_lecture' );
        $query->set( 'post__in', $lecture_ids );
        $query->set( 'orderby', 'post__in' );

    } else {
        $query->set( 'post__in', array(0) );
    }

});

// add_action( 'elementor/query/speaker_card', function( $query ) {
//     $speaker_id = 0;

//     if ( isset( $GLOBALS['suz_current_popup_speaker_id'] ) ) {
//         $speaker_id = absint( $GLOBALS['suz_current_popup_speaker_id'] );
//     }

//     if ( ! $speaker_id ) {
//         suz_start_session();
//         $speaker_id = isset( $_SESSION['suz_speaker_id'] ) ? absint( $_SESSION['suz_speaker_id'] ) : 0;
//     }

//     if ( $speaker_id ) {
//         $query->set( 'post_type', 'suz_speaker' );
//         $query->set( 'post__in', array( $speaker_id ) );
//         $query->set( 'posts_per_page', 1 );
//         $query->set( 'orderby', 'post__in' );
//         $query->set( 'ignore_sticky_posts', true );
//     } else {
//         $query->set( 'post__in', array( 0 ) );
//     }
// } );

add_action('wp_ajax_load_popup_content', 'load_popup_content');
add_action('wp_ajax_nopriv_load_popup_content', 'load_popup_content');

function suz_popup_image_url_from_meta( $post_id, $meta_key, $size = 'large' ) {
    $image = get_post_meta( $post_id, $meta_key, true );

    if ( empty( $image ) ) {
        return '';
    }

    if ( is_numeric( $image ) ) {
        $url = wp_get_attachment_image_url( absint( $image ), $size );
        return $url ? $url : '';
    }

    if ( is_array( $image ) ) {
        if ( ! empty( $image['url'] ) ) {
            return esc_url_raw( $image['url'] );
        }
        if ( ! empty( $image['ID'] ) ) {
            $url = wp_get_attachment_image_url( absint( $image['ID'] ), $size );
            return $url ? $url : '';
        }
        if ( ! empty( $image['id'] ) ) {
            $url = wp_get_attachment_image_url( absint( $image['id'] ), $size );
            return $url ? $url : '';
        }
    }

    if ( is_string( $image ) ) {
        if ( is_numeric( $image ) ) {
            $url = wp_get_attachment_image_url( absint( $image ), $size );
            return $url ? $url : '';
        }
        return esc_url_raw( $image );
    }

    return '';
}

function suz_render_fallback_popup_content( $post_id ) {
    $company = trim( (string) get_post_meta( $post_id, 'suz_lecture_speaker_company', true ) );
    $role = trim( (string) get_post_meta( $post_id, 'suz_lecture_speaker_role', true ) );
    $bio = trim( (string) get_post_meta( $post_id, 'suz_lecture_speaker_bio', true ) );
    $speaker_photo = suz_popup_image_url_from_meta( $post_id, 'suz_lecture_speaker_photo', 'large' );
    $company_logo = suz_popup_image_url_from_meta( $post_id, 'suz_lecture_speaker_company_logo', 'medium' );

    if ( '' === $company && '' === $role && '' === $bio && '' === $speaker_photo && '' === $company_logo ) {
        return '<div class="suz-fallback-speaker-popup"><p class="suz-fallback-speaker-popup__empty">' .
            esc_html__( 'Speaker information is currently unavailable.', 'suz-control-panel' ) .
        '</p></div>';
    }

    ob_start();
    ?>
    <style id="suz-fallback-speaker-popup-style">
        .suz-fallback-speaker-popup {
            display: grid;
            gap: 18px;
            color: #0f172a;
        }
        .suz-fallback-speaker-popup__image img {
            width: 100%;
            max-height: 340px;
            object-fit: cover;
            border-radius: 14px;
            display: block;
        }
        .suz-fallback-speaker-popup__logo img {
            max-height: 52px;
            max-width: 200px;
            width: auto;
            display: block;
        }
        .suz-fallback-speaker-popup__company {
            margin: 0;
            font-size: 26px;
            line-height: 1.2;
        }
        .suz-fallback-speaker-popup__role {
            margin: 8px 0 0;
            font-size: 16px;
            color: #334155;
        }
        .suz-fallback-speaker-popup__bio {
            margin-top: 12px;
            color: #1e293b;
            line-height: 1.6;
        }
        .suz-fallback-speaker-popup__empty {
            margin: 0;
            color: #334155;
        }
        @media (max-width: 767px) {
            .suz-fallback-speaker-popup__company {
                font-size: 22px;
            }
        }
    </style>
    <div class="suz-fallback-speaker-popup">
        <?php if ( $speaker_photo ) : ?>
            <div class="suz-fallback-speaker-popup__image">
                <img src="<?php echo esc_url( $speaker_photo ); ?>" alt="<?php echo esc_attr( $company ? $company : __( 'Speaker', 'suz-control-panel' ) ); ?>">
            </div>
        <?php endif; ?>

        <div class="suz-fallback-speaker-popup__content">
            <?php if ( $company_logo ) : ?>
                <div class="suz-fallback-speaker-popup__logo">
                    <img src="<?php echo esc_url( $company_logo ); ?>" alt="<?php echo esc_attr( $company ? $company : __( 'Company logo', 'suz-control-panel' ) ); ?>">
                </div>
            <?php endif; ?>

            <?php if ( $company ) : ?>
                <h3 class="suz-fallback-speaker-popup__company"><?php echo esc_html( $company ); ?></h3>
            <?php endif; ?>

            <?php if ( $role ) : ?>
                <p class="suz-fallback-speaker-popup__role"><?php echo esc_html( $role ); ?></p>
            <?php endif; ?>

            <?php if ( $bio ) : ?>
                <div class="suz-fallback-speaker-popup__bio"><?php echo wpautop( esc_html( $bio ) ); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function load_popup_content() {
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $popup_template_id = isset( $_POST['popup_template_id'] ) ? absint( $_POST['popup_template_id'] ) : 0;
    $is_fallback = isset( $_POST['is_fallback'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['is_fallback'] ) );

    if ( ! $post_id ) {
        wp_die();
    }

    if ( $is_fallback ) {
        echo suz_render_fallback_popup_content( $post_id );
        wp_die();
    }

    if ( ! $popup_template_id ) {
        wp_die();
    }

    global $post;
    $post = get_post($post_id);
    if ( ! $post ) {
        wp_die();
    }

    setup_postdata($post);

    \Elementor\Plugin::$instance->frontend->enqueue_styles();

    echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $popup_template_id, true );

    wp_reset_postdata();
    wp_die();
}

function suz_get_last_lecture_post() {
    $args = array(
        'post_type'      => 'suz_lecture',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $posts = get_posts($args);
    return !empty($posts) ? $posts[0] : false;
}

function suz_add_minutes_to_time($time, $minutes) {

    if (!$time || !$minutes) return $time;

    $timestamp = strtotime($time);
    if (!$timestamp) return $time;

    return date('H:i', strtotime("+{$minutes} minutes", $timestamp));
}

add_action('load-post-new.php', 'suz_prefill_lecture_meta');

function suz_prefill_lecture_meta() {
    global $pagenow;

    if ($pagenow !== 'post-new.php') return;

    if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'suz_lecture') return;

    $last_post = suz_get_last_lecture_post();

    if ( ! $last_post ) return;

    add_filter('acf/load_value/name=suz_time_from', 'suz_prefill_time_from', 10, 3);
    add_filter('acf/load_value/name=suz_time_to', 'suz_prefill_time_to', 10, 3);
    add_filter('acf/load_value/name=suz_lecture_duration', 'suz_prefill_lecture_duration', 10, 3);
}

function suz_prefill_time_from($value, $post_id, $field) {

    if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'suz_lecture') return $value;

    $last_post = suz_get_last_lecture_post();
    if (!$last_post) return $value;

    $last_time_from = get_post_meta($last_post->ID, 'suz_time_from', true);
    $duration       = get_post_meta($last_post->ID, 'suz_lecture_duration', true);

    return suz_add_minutes_to_time($last_time_from, $duration);
}

function suz_prefill_time_to($value, $post_id, $field) {

    if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'suz_lecture') return $value;

    $last_post = suz_get_last_lecture_post();
    if (!$last_post) return $value;

    $last_time_from = get_post_meta($last_post->ID, 'suz_time_from', true);
    $duration       = get_post_meta($last_post->ID, 'suz_lecture_duration', true);

    $new_time_from = suz_add_minutes_to_time($last_time_from, $duration);
    
    return suz_add_minutes_to_time($new_time_from, $duration);
}

function suz_prefill_lecture_duration($value, $post_id, $field) {

    if ( !isset($_GET['post_type']) || $_GET['post_type'] !== 'suz_lecture' ) return $value;

    $last_post = suz_get_last_lecture_post();

    if ( !$last_post ) return $value;

    return get_post_meta($last_post->ID, 'suz_lecture_duration', true);
}

add_action('load-post-new.php', 'suz_prefill_all_taxonomies');

function suz_prefill_all_taxonomies() {

    if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'suz_lecture') return;

    $last_post = suz_get_last_lecture_post();

    if (!$last_post) return;

    $tags = wp_get_post_terms($last_post->ID, 'suz_event_tag', ['fields' => 'ids']);

    $days = wp_get_post_terms($last_post->ID, 'suz_lecture_day', ['fields' => 'ids']);

    add_action('admin_footer', function() use ($tags, $days) {
        ?>
        <script>
        (function(){

            const tags = <?php echo json_encode($tags); ?>;
            const days = <?php echo json_encode($days); ?>;

            function setTaxonomies(){

                if (typeof wp === 'undefined' || !wp.data) return;

                const editor = wp.data.dispatch('core/editor');

                if (!editor) return;

                editor.editPost({
                    suz_event_tag: tags,
                    suz_lecture_day: days
                });
            }

            setTimeout(setTaxonomies, 1000);
            setTimeout(setTaxonomies, 2000);
            setTimeout(setTaxonomies, 3000);

        })();
        </script>
        <?php
    });
}