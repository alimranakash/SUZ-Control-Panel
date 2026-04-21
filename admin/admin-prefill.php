<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// =============================================================================
// 6. ADMIN JS – AUTO-PREFILL LOGIC  (suz_lecture / post-new.php only)
// Fields: suz_event_tag, suz_time_from, suz_time_to, suz_lecture_day
// =============================================================================


// -----------------------------------------------------------------------------
// Enqueue JS + pass server-side data
// -----------------------------------------------------------------------------
add_action( 'admin_enqueue_scripts', 'suz_prefill_enqueue' );
function suz_prefill_enqueue( $hook ) {
    // Only on new-lecture screen
    if ( 'post-new.php' !== $hook ) {
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( empty( $_GET['post_type'] ) || 'suz_lecture' !== $_GET['post_type'] ) {
        return;
    }

    wp_enqueue_script(
        'suz-lecture-prefill',
        plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/lecture-prefill.js',
        [ 'jquery', 'acf-input' ],
        '1.0.0',
        true
    );

    $user_id  = get_current_user_id();
    $last_tag = get_user_meta( $user_id, 'suz_last_conference_tag', true );

    // If no user-meta yet, fall back to most recently created suz_event_tag term
    if ( ! $last_tag ) {
        $latest = get_terms( [
            'taxonomy'   => 'suz_event_tag',
            'orderby'    => 'term_id',
            'order'      => 'DESC',
            'number'     => 1,
            'hide_empty' => false,
        ] );
        if ( ! is_wp_error( $latest ) && ! empty( $latest ) ) {
            $last_tag = $latest[0]->slug;
        }
    }

    // Default lecture day = first term in suz_lecture_day (term_id ASC)
    $default_day = '';
    $day_terms   = get_terms( [
        'taxonomy'   => 'suz_lecture_day',
        'orderby'    => 'term_id',
        'order'      => 'ASC',
        'number'     => 1,
        'hide_empty' => false,
    ] );
    if ( ! is_wp_error( $day_terms ) && ! empty( $day_terms ) ) {
        $default_day = $day_terms[0]->slug;
    }

    /*
     * ACF field keys: retrieve them from registered field groups so we don't
     * hard-code keys that may differ between environments.
     * Falls back to empty string — JS skips gracefully when key is falsy.
     */
    wp_localize_script(
        'suz-lecture-prefill',
        'suzPrefill',
        [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'suz_prefill_nonce' ),
            'lastTag'   => $last_tag ?: '',
            'defaultDay' => $default_day,
            'fieldKeys' => [
                'event_tag'   => suz_get_acf_field_key( 'suz_event_tag',          'suz_lecture' ),
                'time_from'   => suz_get_acf_field_key( 'suz_time_from',           'suz_lecture' ),
                'time_to'     => suz_get_acf_field_key( 'suz_time_to',             'suz_lecture' ),
                'duration'    => suz_get_acf_field_key( 'suz_lecture_duration',    'suz_lecture' ),
                'lecture_day' => suz_get_acf_field_key( 'suz_lecture_day_tax',     'suz_lecture' ),
            ],
        ]
    );
}

/**
 * Resolve ACF field key by field name within a given post type's field groups.
 *
 * @param string $field_name  ACF field name (e.g. "suz_time_from").
 * @param string $post_type   Post type slug used to locate the field group.
 * @return string             ACF field key (e.g. "field_abc123") or empty string.
 */
function suz_get_acf_field_key( $field_name, $post_type ) {
    if ( ! function_exists( 'acf_get_field_groups' ) ) {
        return '';
    }

    $groups = acf_get_field_groups( [ 'post_type' => $post_type ] );
    foreach ( $groups as $group ) {
        $fields = acf_get_fields( $group['key'] );
        if ( ! $fields ) {
            continue;
        }
        foreach ( $fields as $field ) {
            if ( isset( $field['name'] ) && $field['name'] === $field_name ) {
                return $field['key'];
            }
        }
    }

    return '';
}


// -----------------------------------------------------------------------------
// Save last-used conference tag to user meta after saving a lecture
// -----------------------------------------------------------------------------
add_action( 'acf/save_post', 'suz_save_last_conference_tag', 20 );
function suz_save_last_conference_tag( $post_id ) {
    if ( get_post_type( $post_id ) !== 'suz_lecture' ) {
        return;
    }

    $terms = get_the_terms( $post_id, 'suz_event_tag' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        update_user_meta( get_current_user_id(), 'suz_last_conference_tag', $terms[0]->slug );
    }
}


// -----------------------------------------------------------------------------
// AJAX endpoint: return suz_time_to of the last lecture in given conference tag
// -----------------------------------------------------------------------------
add_action( 'wp_ajax_suz_get_last_lecture', 'suz_ajax_get_last_lecture' );
function suz_ajax_get_last_lecture() {
    check_ajax_referer( 'suz_prefill_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }

    $tag_slug = isset( $_POST['tag_slug'] ) ? sanitize_text_field( $_POST['tag_slug'] ) : '';
    if ( ! $tag_slug ) {
        wp_send_json_error( [ 'message' => 'Missing tag_slug' ] );
    }

    $query = new WP_Query( [
        'post_type'      => 'suz_lecture',
        'post_status'    => [ 'publish', 'draft', 'pending' ],
        'posts_per_page' => 1,
        'meta_key'       => 'suz_time_from',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
        'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery
            [
                'taxonomy' => 'suz_event_tag',
                'field'    => 'slug',
                'terms'    => $tag_slug,
            ],
        ],
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );

    if ( empty( $query->posts ) ) {
        wp_send_json_success( [ 'time_to' => null ] );
    }

    $last_id = $query->posts[0];
    $time_to = get_post_meta( $last_id, 'suz_time_to', true );

    wp_send_json_success( [ 'time_to' => $time_to ?: null ] );
}
