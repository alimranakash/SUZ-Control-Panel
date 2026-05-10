<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'suz_meta_shortcode' ) ) {
    function suz_meta_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'key'     => '',
                'post_id' => get_the_ID(),
            ),
            $atts
        );

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
                    $title    = get_the_title( $val );
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
