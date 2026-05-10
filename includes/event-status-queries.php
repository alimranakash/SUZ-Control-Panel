<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

} );

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

} );

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

} );

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

} );

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
