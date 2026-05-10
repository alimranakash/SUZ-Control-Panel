<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

    $id_map  = array_fill_keys( $ids, true );
    $ordered = array();

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
