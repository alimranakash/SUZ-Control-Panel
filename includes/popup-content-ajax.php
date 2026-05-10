<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_load_popup_content', 'load_popup_content' );
add_action( 'wp_ajax_nopriv_load_popup_content', 'load_popup_content' );

function load_popup_content() {
    $post_id                   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $popup_template_id         = isset( $_POST['popup_template_id'] ) ? absint( $_POST['popup_template_id'] ) : 0;
    $fallback_popup_template_id = isset( $_POST['fallback_popup_template_id'] ) ? absint( $_POST['fallback_popup_template_id'] ) : 0;

    if ( ! $fallback_popup_template_id && isset( $_POST['fallback_template_id'] ) ) {
        $fallback_popup_template_id = absint( $_POST['fallback_template_id'] );
    }

    $is_fallback           = isset( $_POST['is_fallback'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['is_fallback'] ) );
    $template_id_to_render = $is_fallback ? $fallback_popup_template_id : $popup_template_id;

    if ( ! $post_id ) {
        wp_die();
    }

    if ( ! $template_id_to_render ) {
        wp_die();
    }

    global $post;
    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_die();
    }

    setup_postdata( $post );

    \Elementor\Plugin::$instance->frontend->enqueue_styles();

    echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id_to_render, true );

    wp_reset_postdata();
    wp_die();
}
