<?php
/**
 * Plugin Name: SUZ Control Panel
 * Plugin URI:  https://magicmedia.sk/ 
 * Description: Central dashboard to manage SUZ-related plugins & features.
 * Version:     1.3.6
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
    require_once plugin_dir_path( __FILE__ ) . 'admin/event-related-metabox.php';
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

function suz_get_current_event_id_for_query() {
    $event_id = absint( get_queried_object_id() );

    if ( ! $event_id ) {
        $event_id = absint( get_the_ID() );
    }

    return $event_id;
}

function suz_get_post_ids_by_csv_meta_code( $post_type, $meta_key, $event_code ) {
    $event_code = trim( sanitize_text_field( (string) $event_code ) );
    if ( '' === $event_code ) {
        return array();
    }

    global $wpdb;

    $normalized_code = str_replace( ' ', '', $event_code );

    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status = %s
              AND pm.meta_key = %s
              AND FIND_IN_SET( %s, REPLACE( pm.meta_value, ' ', '' ) ) > 0
            ORDER BY p.ID ASC
            ",
            $post_type,
            'publish',
            $meta_key,
            $normalized_code
        )
    );

    return array_values( array_unique( array_map( 'absint', (array) $ids ) ) );
}

function suz_order_ids_by_saved_order( $ids, $saved_order_ids ) {
    if ( empty( $ids ) || ! is_array( $ids ) ) {
        return array();
    }

    if ( ! is_array( $saved_order_ids ) ) {
        $saved_order_ids = array();
    }

    $id_map     = array_fill_keys( $ids, true );
    $ordered    = array();

    foreach ( $saved_order_ids as $saved_id ) {
        $saved_id = absint( $saved_id );
        if ( isset( $id_map[ $saved_id ] ) ) {
            $ordered[] = $saved_id;
            unset( $id_map[ $saved_id ] );
        }
    }

    if ( ! empty( $id_map ) ) {
        $ordered = array_merge( $ordered, array_map( 'absint', array_keys( $id_map ) ) );
    }

    return $ordered;
}

function suz_query_event_lectures( $query ) {
    $event_id = suz_get_current_event_id_for_query();

    if ( ! $event_id ) {
        $query->set( 'post__in', array( 0 ) );
        return;
    }

    $event_code  = get_post_meta( $event_id, 'suz_event_code', true );
    $matched_ids = suz_get_post_ids_by_csv_meta_code( 'suz_lecture', 'suz_lecture_event_code', $event_code );

    if ( empty( $matched_ids ) ) {
        $query->set( 'post__in', array( 0 ) );
        return;
    }

    $saved_order = get_post_meta( $event_id, 'suz_related_order_lecture', true );
    $ordered_ids = suz_order_ids_by_saved_order( $matched_ids, $saved_order );

    $query->set( 'post_type', 'suz_lecture' );
    $query->set( 'post_status', 'publish' );
    $query->set( 'post__in', $ordered_ids );
    $query->set( 'posts_per_page', count( $ordered_ids ) );
    $query->set( 'orderby', 'post__in' );
}

add_action( 'elementor/query/event_lectures', 'suz_query_event_lectures' );

function suz_query_event_speakers( $query ) {
    $event_id = suz_get_current_event_id_for_query();

    if ( ! $event_id ) {
        $query->set( 'post__in', array( 0 ) );
        return;
    }

    $event_code  = get_post_meta( $event_id, 'suz_event_code', true );
    $matched_ids = suz_get_post_ids_by_csv_meta_code( 'suz_speaker', 'suz_speaker_event_code', $event_code );

    if ( empty( $matched_ids ) ) {
        $query->set( 'post__in', array( 0 ) );
        return;
    }

    $saved_order = get_post_meta( $event_id, 'suz_related_order_speaker', true );
    $ordered_ids = suz_order_ids_by_saved_order( $matched_ids, $saved_order );

    $query->set( 'post_type', 'suz_speaker' );
    $query->set( 'post_status', 'publish' );
    $query->set( 'post__in', $ordered_ids );
    $query->set( 'posts_per_page', count( $ordered_ids ) );
    $query->set( 'orderby', 'post__in' );
}

add_action( 'elementor/query/event_speakers', 'suz_query_event_speakers' );

add_action( 'elementor/query/upcoming_events', function( $query ) {

    $current_datetime = current_time( 'Y-m-d H:i:s' );

    $meta_query = array(
        array(
            'key'     => 'suz_end_date',
            'value'   => $current_datetime,
            'compare' => '>=',
            'type'    => 'DATETIME',
        ),
    );

    $query->set( 'post_type', 'suz_event' );
    $query->set( 'post_status', 'publish' );
    $query->set( 'meta_query', $meta_query );

    // earliest upcoming first
    $query->set( 'orderby', 'meta_value' );
    $query->set( 'meta_key', 'suz_start_date' );
    $query->set( 'order', 'ASC' );

});

add_action( 'elementor/query/running_events', function( $query ) {

    $current_datetime = current_time( 'Y-m-d H:i:s' );

    $meta_query = array(
        'relation' => 'AND',

        array(
            'key'     => 'suz_start_date',
            'value'   => $current_datetime,
            'compare' => '<=',
            'type'    => 'DATETIME',
        ),

        array(
            'key'     => 'suz_end_date',
            'value'   => $current_datetime,
            'compare' => '>=',
            'type'    => 'DATETIME',
        ),
    );

    $query->set( 'post_type', 'suz_event' );
    $query->set( 'post_status', 'publish' );
    $query->set( 'meta_query', $meta_query );

    $query->set( 'orderby', 'meta_value' );
    $query->set( 'meta_key', 'suz_start_date' );
    $query->set( 'order', 'ASC' );

});

add_action( 'elementor/query/future_events', function( $query ) {

    $current_datetime = current_time( 'Y-m-d H:i:s' );

    $meta_query = array(
        array(
            'key'     => 'suz_start_date',
            'value'   => $current_datetime,
            'compare' => '>',
            'type'    => 'DATETIME',
        ),
    );

    $query->set( 'post_type', 'suz_event' );
    $query->set( 'post_status', 'publish' );
    $query->set( 'meta_query', $meta_query );

    $query->set( 'orderby', 'meta_value' );
    $query->set( 'meta_key', 'suz_start_date' );
    $query->set( 'order', 'ASC' );

});

add_action( 'elementor/query/expired_events', function( $query ) {

    $current_datetime = current_time( 'Y-m-d H:i:s' );

    $meta_query = array(
        array(
            'key'     => 'suz_end_date',
            'value'   => $current_datetime,
            'compare' => '<',
            'type'    => 'DATETIME',
        ),
    );

    $query->set( 'post_type', 'suz_event' );
    $query->set( 'post_status', 'publish' );
    $query->set( 'meta_query', $meta_query );

    $query->set( 'orderby', 'meta_value' );
    $query->set( 'meta_key', 'suz_end_date' );
    $query->set( 'order', 'DESC' );

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
    $fallback_popup_template_id = isset( $_POST['fallback_popup_template_id'] ) ? absint( $_POST['fallback_popup_template_id'] ) : 0;
    if ( ! $fallback_popup_template_id && isset( $_POST['fallback_template_id'] ) ) {
        $fallback_popup_template_id = absint( $_POST['fallback_template_id'] );
    }
    $is_fallback = isset( $_POST['is_fallback'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['is_fallback'] ) );
    $template_id_to_render = $is_fallback ? $fallback_popup_template_id : $popup_template_id;

    if ( ! $post_id ) {
        wp_die();
    }

    if ( ! $template_id_to_render ) {
        wp_die();
    }

    global $post;
    $post = get_post($post_id);
    if ( ! $post ) {
        wp_die();
    }

    setup_postdata($post);

    \Elementor\Plugin::$instance->frontend->enqueue_styles();

    echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id_to_render, true );

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

    if ( $pagenow !== 'post-new.php' ) return;

    if ( !isset($_GET['post_type']) || $_GET['post_type'] !== 'suz_lecture' ) return;

    $last_post = suz_get_last_lecture_post();

    if ( ! $last_post ) return;

    add_filter('acf/load_value/name=suz_time_from', 'suz_prefill_time_from', 10, 3);
    add_filter('acf/load_value/name=suz_time_to', 'suz_prefill_time_to', 10, 3);
    add_filter('acf/load_value/name=suz_lecture_duration', 'suz_prefill_lecture_duration', 10, 3);

    $tags = wp_get_post_terms( $last_post->ID, 'suz_event_tag', ['fields' => 'ids'] );

    $days = wp_get_post_terms( $last_post->ID, 'suz_lecture_day', ['fields' => 'ids'] );

    add_action('admin_footer', function() use ($tags, $days) {
        ?>
        <script>
        (function(){

            const tags = <?php echo json_encode($tags); ?>;
            const days = <?php echo json_encode($days); ?>;

            function setTaxonomies() {

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

if ( ! function_exists( 'suz_meta_shortcode' ) ) {
    function suz_meta_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'key'     => '',
            'post_id' => get_the_ID(),
        ), $atts );

        if ( empty( $atts['key'] ) ) {
            return '';
        }

        $value = get_post_meta( $atts['post_id'], $atts['key'], true );

        if ( empty( $value ) ) {
            return '';
        }

        if ( is_array( $value ) ) {
            $output = array();

            foreach ( $value as $val ) {
                if ( is_numeric( $val ) ) {
                    $title = get_the_title( $val );
                    $output[] = $title ? $title : $val;
                } else {
                    $output[] = $val;
                }
            }

            return implode( ', ', $output );
        }

        if ( is_numeric( $value ) ) {
            $title = get_the_title( $value );
            return $title ? $title : $value;
        }

        return esc_html( $value );
    }
}

if ( ! shortcode_exists( 'suz_meta' ) ) {
    add_shortcode( 'suz_meta', 'suz_meta_shortcode' );
}
