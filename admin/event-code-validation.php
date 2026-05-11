<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'acf/validate_value/name=suz_event_code', 'suz_validate_unique_event_code', 10, 4 );
function suz_validate_unique_event_code( $valid, $value, $field, $input ) {
    if ( true !== $valid ) {
        return $valid;
    }

    $event_code = trim( sanitize_text_field( (string) $value ) );
    if ( '' === $event_code ) {
        return $valid;
    }

    $post_id = suz_get_event_code_validation_post_id();
    if ( ! $post_id || 'suz_event' !== get_post_type( $post_id ) ) {
        return $valid;
    }

    if ( suz_get_duplicate_event_code_post_id( $event_code, $post_id ) ) {
        return __( 'This Event Code is already used by another event. Please enter a unique Event Code.', 'suz-control-panel' );
    }

    return $valid;
}

add_filter( 'acf/update_value/name=suz_event_code', 'suz_normalize_event_code_value', 10, 3 );
function suz_normalize_event_code_value( $value, $post_id, $field ) {
    return trim( sanitize_text_field( (string) $value ) );
}

function suz_get_duplicate_event_code_post_id( $event_code, $post_id = 0 ) {
    global $wpdb;

    $event_code = trim( sanitize_text_field( (string) $event_code ) );
    if ( '' === $event_code ) {
        return 0;
    }

    return absint(
        $wpdb->get_var(
            $wpdb->prepare(
                "SELECT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                    AND p.ID <> %d
                    AND pm.meta_key = %s
                    AND LOWER(TRIM(pm.meta_value)) = LOWER(%s)
                LIMIT 1",
                'suz_event',
                absint( $post_id ),
                'suz_event_code',
                $event_code
            )
        )
    );
}

function suz_get_event_code_validation_post_id() {
    $post_id = 0;

    if ( isset( $_POST['post_ID'] ) ) {
        $post_id = absint( wp_unslash( $_POST['post_ID'] ) );
    } elseif ( isset( $_POST['_acf_post_id'] ) ) {
        $post_id = absint( wp_unslash( $_POST['_acf_post_id'] ) );
    }

    return $post_id;
}
