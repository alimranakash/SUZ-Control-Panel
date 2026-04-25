<?php
/**
 * Plugin Name: SUZ Control Panel
 * Plugin URI:  https://magicmedia.sk/ 
 * Description: Central dashboard to manage SUZ-related plugins & features.
 * Version:     1.2.0
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
    require_once plugin_dir_path( __FILE__ ) . 'admin/lecture-autoprefill.php'; // Auto-prefill for new suz_lecture
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

function load_popup_content() {
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $popup_template_id = isset( $_POST['popup_template_id'] ) ? absint( $_POST['popup_template_id'] ) : 0;

    if ( ! $post_id || ! $popup_template_id ) {
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
