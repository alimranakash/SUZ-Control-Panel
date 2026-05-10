<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function suz_get_last_lecture_post() {
    $args = array(
        'post_type'      => 'suz_lecture',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $posts = get_posts( $args );
    return ! empty( $posts ) ? $posts[0] : false;
}

function suz_add_minutes_to_time( $time, $minutes ) {

    if ( ! $time || ! $minutes ) {
        return $time;
    }

    $timestamp = strtotime( $time );
    if ( ! $timestamp ) {
        return $time;
    }

    return date( 'H:i', strtotime( "+{$minutes} minutes", $timestamp ) );
}

add_action( 'load-post-new.php', 'suz_prefill_lecture_meta' );

function suz_prefill_lecture_meta() {
    global $pagenow;

    if ( $pagenow !== 'post-new.php' ) {
        return;
    }

    if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'suz_lecture' ) {
        return;
    }

    $last_post = suz_get_last_lecture_post();

    if ( ! $last_post ) {
        return;
    }

    add_filter( 'acf/load_value/name=suz_time_from', 'suz_prefill_time_from', 10, 3 );
    add_filter( 'acf/load_value/name=suz_time_to', 'suz_prefill_time_to', 10, 3 );
    add_filter( 'acf/load_value/name=suz_lecture_duration', 'suz_prefill_lecture_duration', 10, 3 );

    $tags = wp_get_post_terms( $last_post->ID, 'suz_event_tag', array( 'fields' => 'ids' ) );

    $days = wp_get_post_terms( $last_post->ID, 'suz_lecture_day', array( 'fields' => 'ids' ) );

    add_action( 'admin_footer', function() use ( $tags, $days ) {
        ?>
        <script>
        (function(){

            const tags = <?php echo json_encode( $tags ); ?>;
            const days = <?php echo json_encode( $days ); ?>;

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
    } );
}

function suz_prefill_time_from( $value, $post_id, $field ) {

    if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'suz_lecture' ) {
        return $value;
    }

    $last_post = suz_get_last_lecture_post();
    if ( ! $last_post ) {
        return $value;
    }

    $last_time_from = get_post_meta( $last_post->ID, 'suz_time_from', true );
    $duration       = get_post_meta( $last_post->ID, 'suz_lecture_duration', true );

    return suz_add_minutes_to_time( $last_time_from, $duration );
}

function suz_prefill_time_to( $value, $post_id, $field ) {

    if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'suz_lecture' ) {
        return $value;
    }

    $last_post = suz_get_last_lecture_post();

    if ( ! $last_post ) {
        return $value;
    }

    $last_time_from = get_post_meta( $last_post->ID, 'suz_time_from', true );
    $duration       = get_post_meta( $last_post->ID, 'suz_lecture_duration', true );

    $new_time_from = suz_add_minutes_to_time( $last_time_from, $duration );

    return suz_add_minutes_to_time( $new_time_from, $duration );
}

function suz_prefill_lecture_duration( $value, $post_id, $field ) {

    if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'suz_lecture' ) {
        return $value;
    }

    $last_post = suz_get_last_lecture_post();

    if ( ! $last_post ) {
        return $value;
    }

    return get_post_meta( $last_post->ID, 'suz_lecture_duration', true );
}
