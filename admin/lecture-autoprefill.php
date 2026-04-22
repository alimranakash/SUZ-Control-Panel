<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'load-post-new.php', 'suz_lecture_autoprefill_bootstrap' );
function suz_lecture_autoprefill_bootstrap() {
    if ( ! suz_lecture_autoprefill_is_new_lecture_screen() ) {
        return;
    }

    add_action( 'admin_enqueue_scripts', 'suz_lecture_autoprefill_enqueue' );
    add_action( 'enqueue_block_editor_assets', 'suz_lecture_autoprefill_enqueue' );
}

function suz_lecture_autoprefill_enqueue() {
    if ( ! suz_lecture_autoprefill_is_new_lecture_screen() ) {
        return;
    }

    if ( wp_script_is( 'suz-lecture-autoprefill', 'enqueued' ) ) {
        return;
    }

    $script_path = dirname( __DIR__ ) . '/assets/js/suz-lecture-autoprefill.js';
    $script_url  = plugins_url( 'assets/js/suz-lecture-autoprefill.js', dirname( __FILE__ ) );

    $script_dependencies = [ 'jquery' ];
    if ( wp_script_is( 'acf-input', 'registered' ) ) {
        $script_dependencies[] = 'acf-input';
    }

    wp_enqueue_script(
        'suz-lecture-autoprefill',
        $script_url,
        $script_dependencies,
        file_exists( $script_path ) ? (string) filemtime( $script_path ) : '1.0.0',
        true
    );

    $current_user_id = get_current_user_id();
    $last_event_tag  = suz_lecture_autoprefill_get_last_event_tag_for_user( $current_user_id );
    $default_day     = suz_lecture_autoprefill_get_first_term( 'suz_lecture_day' );

    wp_localize_script(
        'suz-lecture-autoprefill',
        'suzLecturePrefill',
        [
            'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
            'nonce'                => wp_create_nonce( 'suz_lecture_autoprefill_nonce' ),
            'lastEventTagId'       => isset( $last_event_tag['id'] ) ? (int) $last_event_tag['id'] : 0,
            'lastEventTagSlug'     => isset( $last_event_tag['slug'] ) ? (string) $last_event_tag['slug'] : '',
            'defaultLectureDayId'  => isset( $default_day['id'] ) ? (int) $default_day['id'] : 0,
            'defaultLectureDaySlug'=> isset( $default_day['slug'] ) ? (string) $default_day['slug'] : '',
            'fieldKeys'            => [
                'eventTag'   => suz_lecture_autoprefill_get_acf_key( [ 'suz_event_tag' ], 'suz_lecture' ),
                'timeFrom'   => suz_lecture_autoprefill_get_acf_key( [ 'suz_time_from' ], 'suz_lecture' ),
                'timeTo'     => suz_lecture_autoprefill_get_acf_key( [ 'suz_time_to' ], 'suz_lecture' ),
                'duration'   => suz_lecture_autoprefill_get_acf_key( [ 'suz_lecture_duration' ], 'suz_lecture' ),
                'lectureDay' => suz_lecture_autoprefill_get_acf_key( [ 'suz_lecture_day', 'suz_lecture_day_tax' ], 'suz_lecture' ),
            ],
            'fieldNames'           => [
                'eventTag'   => 'suz_event_tag',
                'timeFrom'   => 'suz_time_from',
                'timeTo'     => 'suz_time_to',
                'duration'   => 'suz_lecture_duration',
                'lectureDay' => 'suz_lecture_day',
            ],
            'selectors'            => [
                'eventTag'   => 'input[name="tax_input[suz_event_tag][]"], input[name="tax_input[suz_event_tag]"], select[name="tax_input[suz_event_tag][]"], select[name="tax_input[suz_event_tag]"]',
                'timeFrom'   => 'input[name="suz_time_from"]',
                'timeTo'     => 'input[name="suz_time_to"]',
                'duration'   => 'input[name="suz_lecture_duration"], select[name="suz_lecture_duration"]',
                'lectureDay' => 'input[name="tax_input[suz_lecture_day][]"], input[name="tax_input[suz_lecture_day]"], select[name="tax_input[suz_lecture_day][]"], select[name="tax_input[suz_lecture_day]"]',
            ],
        ]
    );
}

function suz_lecture_autoprefill_is_new_lecture_screen() {
    if ( ! is_admin() ) {
        return false;
    }

    global $pagenow;
    if ( 'post-new.php' !== $pagenow ) {
        return false;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && ! empty( $screen->post_type ) ) {
        return 'suz_lecture' === $screen->post_type;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
    return 'suz_lecture' === $post_type;
}

function suz_lecture_autoprefill_get_last_event_tag_for_user( $user_id ) {
    $user_id = absint( $user_id );
    if ( 0 === $user_id ) {
        return [
            'id'   => 0,
            'slug' => '',
        ];
    }

    $latest_posts = get_posts(
        [
            'post_type'      => 'suz_lecture',
            'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
            'author'         => $user_id,
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]
    );

    if ( ! empty( $latest_posts ) ) {
        $terms = wp_get_post_terms( $latest_posts[0], 'suz_event_tag' );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            return [
                'id'   => (int) $terms[0]->term_id,
                'slug' => (string) $terms[0]->slug,
            ];
        }
    }

    $stored_term_id = (int) get_user_meta( $user_id, 'suz_last_lecture_event_tag_id', true );
    if ( $stored_term_id > 0 && term_exists( $stored_term_id, 'suz_event_tag' ) ) {
        $term = get_term( $stored_term_id, 'suz_event_tag' );
        if ( $term && ! is_wp_error( $term ) ) {
            return [
                'id'   => (int) $term->term_id,
                'slug' => (string) $term->slug,
            ];
        }
    }

    return [
        'id'   => 0,
        'slug' => '',
    ];
}

function suz_lecture_autoprefill_get_first_term( $taxonomy ) {
    $terms = get_terms(
        [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
            'number'     => 1,
        ]
    );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return [
            'id'   => 0,
            'slug' => '',
        ];
    }

    return [
        'id'   => (int) $terms[0]->term_id,
        'slug' => (string) $terms[0]->slug,
    ];
}

function suz_lecture_autoprefill_get_acf_key( $field_names, $post_type ) {
    if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
        return '';
    }

    $field_names = array_map( 'strval', (array) $field_names );
    if ( empty( $field_names ) ) {
        return '';
    }

    $groups = acf_get_field_groups( [ 'post_type' => $post_type ] );
    if ( empty( $groups ) ) {
        return '';
    }

    foreach ( $groups as $group ) {
        $fields = acf_get_fields( $group['key'] );
        if ( empty( $fields ) ) {
            continue;
        }

        $found_key = suz_lecture_autoprefill_find_acf_key_recursive( $fields, $field_names );
        if ( $found_key ) {
            return $found_key;
        }
    }

    return '';
}

function suz_lecture_autoprefill_find_acf_key_recursive( $fields, $field_names ) {
    foreach ( (array) $fields as $field ) {
        if ( ! empty( $field['name'] ) && in_array( $field['name'], $field_names, true ) && ! empty( $field['key'] ) ) {
            return $field['key'];
        }

        if ( ! empty( $field['sub_fields'] ) ) {
            $sub_key = suz_lecture_autoprefill_find_acf_key_recursive( $field['sub_fields'], $field_names );
            if ( $sub_key ) {
                return $sub_key;
            }
        }

        if ( ! empty( $field['layouts'] ) ) {
            foreach ( $field['layouts'] as $layout ) {
                if ( empty( $layout['sub_fields'] ) ) {
                    continue;
                }
                $layout_key = suz_lecture_autoprefill_find_acf_key_recursive( $layout['sub_fields'], $field_names );
                if ( $layout_key ) {
                    return $layout_key;
                }
            }
        }
    }

    return '';
}

add_action( 'wp_ajax_suz_lecture_autoprefill_latest_time', 'suz_lecture_autoprefill_ajax_latest_time' );
function suz_lecture_autoprefill_ajax_latest_time() {
    check_ajax_referer( 'suz_lecture_autoprefill_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }

    $event_tag_raw = isset( $_POST['event_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['event_tag'] ) ) : '';
    if ( '' === $event_tag_raw ) {
        wp_send_json_error( [ 'message' => 'Missing event_tag' ] );
    }

    $event_tag_term = null;
    if ( ctype_digit( $event_tag_raw ) ) {
        $event_tag_term = get_term( (int) $event_tag_raw, 'suz_event_tag' );
    } else {
        $event_tag_term = get_term_by( 'slug', $event_tag_raw, 'suz_event_tag' );
        if ( ! $event_tag_term ) {
            $event_tag_term = get_term_by( 'name', $event_tag_raw, 'suz_event_tag' );
        }
    }

    if ( ! $event_tag_term || is_wp_error( $event_tag_term ) ) {
        wp_send_json_success( [ 'time_to' => '' ] );
    }

    $query = new WP_Query(
        [
            'post_type'      => 'suz_lecture',
            'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
            'posts_per_page' => 10,
            'orderby'        => [
                'date' => 'DESC',
                'ID'   => 'DESC',
            ],
            'tax_query'      => [
                [
                    'taxonomy' => 'suz_event_tag',
                    'field'    => 'term_id',
                    'terms'    => (int) $event_tag_term->term_id,
                ],
            ],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]
    );

    if ( empty( $query->posts ) ) {
        wp_send_json_success(
            [
                'time_to'  => '',
                'duration' => '',
            ]
        );
    }

    foreach ( $query->posts as $lecture_id ) {
        $time_to = (string) get_post_meta( $lecture_id, 'suz_time_to', true );
        if ( '' !== trim( $time_to ) ) {
            $duration = (string) get_post_meta( $lecture_id, 'suz_lecture_duration', true );
            wp_send_json_success(
                [
                    'time_to'  => sanitize_text_field( $time_to ),
                    'duration' => sanitize_text_field( $duration ),
                ]
            );
        }
    }

    wp_send_json_success(
        [
            'time_to'  => '',
            'duration' => '',
        ]
    );
}

add_action( 'save_post_suz_lecture', 'suz_lecture_autoprefill_store_last_event_tag', 20, 2 );
function suz_lecture_autoprefill_store_last_event_tag( $post_id, $post ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }

    $term_ids = wp_get_post_terms( $post_id, 'suz_event_tag', [ 'fields' => 'ids' ] );
    if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
        return;
    }

    $term_id = (int) $term_ids[0];
    if ( $term_id <= 0 ) {
        return;
    }

    $author_id = (int) $post->post_author;
    if ( $author_id > 0 ) {
        update_user_meta( $author_id, 'suz_last_lecture_event_tag_id', $term_id );
    }

    $current_user_id = get_current_user_id();
    if ( $current_user_id > 0 && $current_user_id !== $author_id ) {
        update_user_meta( $current_user_id, 'suz_last_lecture_event_tag_id', $term_id );
    }
}

add_action( 'save_post_suz_lecture', 'suz_lecture_autoprefill_server_defaults', 15, 3 );
function suz_lecture_autoprefill_server_defaults( $post_id, $post, $update ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }

    if ( ! in_array( $post->post_status, [ 'auto-draft', 'draft', 'pending' ], true ) && ! $update ) {
        return;
    }

    $event_tag_terms = wp_get_post_terms( $post_id, 'suz_event_tag', [ 'fields' => 'ids' ] );
    $event_tag_id    = 0;

    if ( ! is_wp_error( $event_tag_terms ) && ! empty( $event_tag_terms ) ) {
        $event_tag_id = (int) $event_tag_terms[0];
    } else {
        $last_event_tag = suz_lecture_autoprefill_get_last_event_tag_for_user( (int) $post->post_author );
        if ( ! empty( $last_event_tag['id'] ) ) {
            $event_tag_id = (int) $last_event_tag['id'];
            wp_set_post_terms( $post_id, [ $event_tag_id ], 'suz_event_tag', false );
        }
    }

    $lecture_day_terms = wp_get_post_terms( $post_id, 'suz_lecture_day', [ 'fields' => 'ids' ] );
    if ( is_wp_error( $lecture_day_terms ) || empty( $lecture_day_terms ) ) {
        $default_day = suz_lecture_autoprefill_get_first_term( 'suz_lecture_day' );
        if ( ! empty( $default_day['id'] ) ) {
            wp_set_post_terms( $post_id, [ (int) $default_day['id'] ], 'suz_lecture_day', false );
        }
    }

    $time_from = trim( (string) get_post_meta( $post_id, 'suz_time_from', true ) );
    if ( '' === $time_from && $event_tag_id > 0 ) {
        $latest_time_to = suz_lecture_autoprefill_get_latest_time_to_for_event_tag( $event_tag_id, $post_id );
        if ( '' !== $latest_time_to ) {
            $time_from = $latest_time_to;
            update_post_meta( $post_id, 'suz_time_from', $time_from );
        }
    }

    $time_to   = trim( (string) get_post_meta( $post_id, 'suz_time_to', true ) );
    $duration  = trim( (string) get_post_meta( $post_id, 'suz_lecture_duration', true ) );
    $time_to_c = suz_lecture_autoprefill_calculate_time_to( $time_from, $duration );

    if ( '' === $time_to && '' !== $time_to_c ) {
        update_post_meta( $post_id, 'suz_time_to', $time_to_c );
    }
}

function suz_lecture_autoprefill_get_latest_time_to_for_event_tag( $event_tag_id, $exclude_post_id = 0 ) {
    $query = new WP_Query(
        [
            'post_type'      => 'suz_lecture',
            'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
            'posts_per_page' => 20,
            'post__not_in'   => $exclude_post_id > 0 ? [ $exclude_post_id ] : [],
            'orderby'        => [
                'date' => 'DESC',
                'ID'   => 'DESC',
            ],
            'tax_query'      => [
                [
                    'taxonomy' => 'suz_event_tag',
                    'field'    => 'term_id',
                    'terms'    => (int) $event_tag_id,
                ],
            ],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]
    );

    if ( empty( $query->posts ) ) {
        return '';
    }

    foreach ( $query->posts as $lecture_id ) {
        $time_to = trim( (string) get_post_meta( $lecture_id, 'suz_time_to', true ) );
        if ( '' !== suz_lecture_autoprefill_normalize_time( $time_to ) ) {
            return suz_lecture_autoprefill_normalize_time( $time_to );
        }
    }

    return '';
}

function suz_lecture_autoprefill_calculate_time_to( $time_from, $duration_raw ) {
    $from_minutes = suz_lecture_autoprefill_time_to_minutes( $time_from );
    $dur_minutes  = suz_lecture_autoprefill_duration_to_minutes( $duration_raw );

    if ( null === $from_minutes || null === $dur_minutes ) {
        return '';
    }

    $total = ( $from_minutes + $dur_minutes ) % 1440;
    if ( $total < 0 ) {
        $total += 1440;
    }

    return sprintf( '%02d:%02d', (int) floor( $total / 60 ), (int) ( $total % 60 ) );
}

function suz_lecture_autoprefill_duration_to_minutes( $duration_raw ) {
    $raw = trim( (string) $duration_raw );
    if ( '' === $raw ) {
        return null;
    }

    if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $raw, $match ) ) {
        $hours = (int) $match[1];
        $mins  = (int) $match[2];
        return ( $hours * 60 ) + $mins;
    }

    if ( preg_match( '/(\d+)/', $raw, $match ) ) {
        return (int) $match[1];
    }

    return null;
}

function suz_lecture_autoprefill_time_to_minutes( $time_raw ) {
    $normalized = suz_lecture_autoprefill_normalize_time( $time_raw );
    if ( '' === $normalized ) {
        return null;
    }

    list( $hours, $mins ) = array_map( 'intval', explode( ':', $normalized ) );
    return ( $hours * 60 ) + $mins;
}

function suz_lecture_autoprefill_normalize_time( $time_raw ) {
    $raw = trim( (string) $time_raw );
    if ( '' === $raw ) {
        return '';
    }

    if ( preg_match( '/(\d{1,2}):(\d{2})/', $raw, $match ) ) {
        $hours = (int) $match[1];
        $mins  = (int) $match[2];
        if ( $hours >= 0 && $hours <= 23 && $mins >= 0 && $mins <= 59 ) {
            return sprintf( '%02d:%02d', $hours, $mins );
        }
    }

    return '';
}
