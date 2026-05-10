<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'add_meta_boxes', 'suz_add_event_related_metabox' );
add_action( 'wp_ajax_suz_remove_related_event_code', 'suz_remove_related_event_code' );
add_action( 'wp_ajax_suz_save_related_order', 'suz_save_related_order' );
add_action( 'admin_enqueue_scripts', 'suz_event_related_metabox_assets' );

function suz_event_related_metabox_assets( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    $post_type = '';
    if ( isset( $_GET['post_type'] ) ) {
        $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
    } elseif ( isset( $_GET['post'] ) ) {
        $post_type = get_post_type( absint( $_GET['post'] ) );
    }

    if ( 'suz_event' !== $post_type ) {
        return;
    }

    wp_enqueue_script( 'jquery-ui-sortable' );
}

function suz_add_event_related_metabox() {

    add_meta_box(
        'suz_event_related_data',
        esc_html__( 'Related Lectures & Speakers', 'suz-control-panel' ),
        'suz_render_event_related_metabox',
        'suz_event',
        'normal',
        'default'
    );

}

/*
|--------------------------------------------------------------------------
| Main Metabox Callback
|--------------------------------------------------------------------------
*/

function suz_render_event_related_metabox( $post ) {

    $event_code = get_post_meta( $post->ID, 'suz_event_code', true );

    if ( empty( $event_code ) ) {
        echo '<p><strong>' . esc_html__( 'No Event Code Found.', 'suz-control-panel' ) . '</strong></p>';
        return;
    }

    echo '<p><strong>' . esc_html__( 'Event Code:', 'suz-control-panel' ) . '</strong> ' . esc_html( $event_code ) . '</p>';
    echo '<style>
    .suz-related-wrap{display:flex;gap:16px;align-items:flex-start}
    .suz-col{flex:1;min-width:0;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px}
    .suz-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px}
    .suz-head h3{margin:0;font-size:15px}
    .suz-hint{font-size:12px;color:#646970}
    .suz-list{margin:0;padding:0;list-style:none}
    .suz-list li{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-top:1px solid #f0f0f1}
    .suz-list li:first-child{border-top:0}
    .suz-item-title{display:flex;gap:6px;align-items:center;min-width:0}
    .suz-drag{cursor:move;color:#8c8f94}
    .suz-item-title a{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
    .suz-sort-placeholder{height:38px;border:1px dashed #c3c4c7;background:#f6f7f7}
    @media (max-width: 900px){.suz-related-wrap{flex-direction:column}}
    </style>';

    echo '<div class="suz-related-wrap" data-event-id="' . esc_attr( $post->ID ) . '" data-event-code="' . esc_attr( $event_code ) . '" data-remove-nonce="' . esc_attr( wp_create_nonce( 'suz_related_remove' ) ) . '" data-sort-nonce="' . esc_attr( wp_create_nonce( 'suz_related_sort' ) ) . '">';

    /*
    |--------------------------------------------------------------------------
    | Related Lectures
    |--------------------------------------------------------------------------
    */

    $lectures = suz_get_related_posts_by_event_code(
        'suz_lecture',
        'suz_lecture_event_code',
        $event_code
    );
    $lectures = suz_apply_related_order( $lectures, get_post_meta( $post->ID, 'suz_related_order_lecture', true ) );

    echo '<div class="suz-col">';
    suz_render_related_post_list( esc_html__( 'Related Lectures', 'suz-control-panel' ), $lectures, 'suz_lecture_event_code', 'lecture' );
    echo '</div>';

    /*
    |--------------------------------------------------------------------------
    | Related Speakers
    |--------------------------------------------------------------------------
    */

    $speakers = suz_get_related_posts_by_event_code(
        'suz_speaker',
        'suz_speaker_event_code',
        $event_code
    );
    $speakers = suz_apply_related_order( $speakers, get_post_meta( $post->ID, 'suz_related_order_speaker', true ) );

    echo '<div class="suz-col">';
    suz_render_related_post_list( esc_html__( 'Related Speakers', 'suz-control-panel' ), $speakers, 'suz_speaker_event_code', 'speaker' );
    echo '</div>';

    echo '</div>';
    ?>
    <script>
    jQuery(function($){
        var i18n = <?php echo wp_json_encode( array(
            'confirm'     => __( 'Remove event code from this item?', 'suz-control-panel' ),
            'removing'    => __( 'Removing...', 'suz-control-panel' ),
            'failed'      => __( 'Failed', 'suz-control-panel' ),
            'requestFail' => __( 'Request failed', 'suz-control-panel' ),
            'remove'      => __( 'Remove', 'suz-control-panel' ),
        ) ); ?>;

        $('.suz-list').each(function(){
            $(this).sortable({
                axis: 'y',
                handle: '.suz-drag',
                placeholder: 'suz-sort-placeholder',
                forcePlaceholderSize: true,
                update: function(){
                    var $list = $(this), $wrap = $list.closest('.suz-related-wrap');
                    $.post(ajaxurl, {
                        action: 'suz_save_related_order',
                        nonce: $wrap.data('sort-nonce'),
                        event_id: $wrap.data('event-id'),
                        order_key: $list.data('order-key'),
                        ids: $list.sortable('toArray', { attribute: 'data-id' })
                    });
                }
            });
        });

        $(document).on('click', '.suz-remove-related', function(){
            var $btn = $(this), $li = $btn.closest('li'), $wrap = $btn.closest('.suz-related-wrap');
            if (!confirm(i18n.confirm)) return;
            $btn.prop('disabled', true).text(i18n.removing);
            $.post(ajaxurl, {
                action: 'suz_remove_related_event_code',
                nonce: $wrap.data('remove-nonce'),
                related_id: $btn.data('post-id'),
                meta_key: $btn.data('meta-key'),
                event_code: $wrap.data('event-code')
            }, function(res){
                if (res && res.success) $li.fadeOut(150, function(){ $(this).remove(); });
                else { alert((res && res.data && res.data.message) ? res.data.message : i18n.failed); $btn.prop('disabled', false).text(i18n.remove); }
            }).fail(function(){ alert(i18n.requestFail); $btn.prop('disabled', false).text(i18n.remove); });
        });
    });
    </script>
    <?php

}

/*
|--------------------------------------------------------------------------
| Get Related Posts
|--------------------------------------------------------------------------
*/

function suz_get_related_posts_by_event_code( $post_type, $meta_key, $event_code ) {

    $event_code = trim( (string) $event_code );
    if ( '' === $event_code ) {
        return array();
    }

    $posts = get_posts( array(
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => -1,

        'meta_query' => array(
            array(
                'key'     => $meta_key,
                'value'   => $event_code,
                'compare' => 'LIKE',
            ),
        ),

    ) );

    $filtered = array();
    foreach ( $posts as $post ) {
        $codes = suz_parse_event_codes( get_post_meta( $post->ID, $meta_key, true ) );
        if ( in_array( $event_code, $codes, true ) ) {
            $filtered[] = $post;
        }
    }

    return $filtered;

}

/*
|--------------------------------------------------------------------------
| Render Related Post List
|--------------------------------------------------------------------------
*/

function suz_render_related_post_list( $title, $posts, $meta_key, $order_key ) {

    echo '<div class="suz-box">';
    echo '<div class="suz-head">';
    echo '<h3>' . esc_html( $title ) . '</h3>';
    echo '<span class="suz-hint">' . esc_html__( 'Drag to sort', 'suz-control-panel' ) . '</span>';
    echo '</div>';

    if ( empty( $posts ) ) {

        echo '<p>' . esc_html__( 'No items found.', 'suz-control-panel' ) . '</p>';
        echo '</div>';
        return;

    }

    echo '<ul class="suz-list" data-order-key="' . esc_attr( $order_key ) . '">';

    foreach ( $posts as $item ) {
        $title_text = get_the_title( $item->ID );

        echo '<li data-id="' . esc_attr( $item->ID ) . '">';
        echo '<div class="suz-item-title">';
        echo '<span class="suz-drag">&#9776;</span>';
        echo '<span>#' . esc_html( $item->ID ) . '</span>';
        echo '<a href="' . esc_url( get_edit_post_link( $item->ID ) ) . '" target="_blank">';
        echo esc_html( $title_text );
        echo '</a>';
        echo '</div>';

        echo '<button type="button" class="button button-small suz-remove-related" data-post-id="' . esc_attr( $item->ID ) . '" data-meta-key="' . esc_attr( $meta_key ) . '">' . esc_html__( 'Remove', 'suz-control-panel' ) . '</button>';
        echo '</li>'; 

    }

    echo '</ul>';
    echo '</div>';

}

function suz_apply_related_order( $posts, $saved_ids ) {
    if ( empty( $posts ) || ! is_array( $posts ) || ! is_array( $saved_ids ) ) {
        return $posts;
    }

    $map = array();
    foreach ( $posts as $p ) {
        $map[ (int) $p->ID ] = $p;
    }

    $ordered = array();
    foreach ( $saved_ids as $id ) {
        $id = (int) $id;
        if ( isset( $map[ $id ] ) ) {
            $ordered[] = $map[ $id ];
            unset( $map[ $id ] );
        }
    }

    return array_merge( $ordered, array_values( $map ) );
}

function suz_parse_event_codes( $raw_value ) {
    if ( is_array( $raw_value ) ) {
        $raw_value = implode( ',', $raw_value );
    }

    $raw_value = (string) $raw_value;
    if ( '' === $raw_value ) {
        return array();
    }

    $parts = explode( ',', $raw_value );
    $codes = array();

    foreach ( $parts as $part ) {
        $code = trim( sanitize_text_field( $part ) );
        if ( '' !== $code ) {
            $codes[] = $code;
        }
    }

    return array_values( array_unique( $codes ) );
}

function suz_remove_event_code_from_meta( $raw_value, $event_code ) {
    $event_code = trim( (string) $event_code );
    $codes      = suz_parse_event_codes( $raw_value );

    if ( ! in_array( $event_code, $codes, true ) ) {
        return false;
    }

    $codes = array_values(
        array_filter(
            $codes,
            static function( $code ) use ( $event_code ) {
                return $code !== $event_code;
            }
        )
    );

    return implode( ',', $codes );
}

function suz_remove_related_event_code() {
    if ( ! check_ajax_referer( 'suz_related_remove', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'suz-control-panel' ) ), 403 );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'No permission', 'suz-control-panel' ) ), 403 );
    }

    $related_id = isset( $_POST['related_id'] ) ? absint( $_POST['related_id'] ) : 0;
    $meta_key   = isset( $_POST['meta_key'] ) ? sanitize_key( wp_unslash( $_POST['meta_key'] ) ) : '';
    $event_code = isset( $_POST['event_code'] ) ? sanitize_text_field( wp_unslash( $_POST['event_code'] ) ) : '';

    if ( ! $related_id || ! $meta_key || ! $event_code ) {
        wp_send_json_error( array( 'message' => __( 'Missing data', 'suz-control-panel' ) ), 400 );
    }

    $meta_value = get_post_meta( $related_id, $meta_key, true );
    $updated    = suz_remove_event_code_from_meta( $meta_value, $event_code );

    if ( false === $updated ) {
        wp_send_json_error( array( 'message' => __( 'Event code not matched', 'suz-control-panel' ) ), 400 );
    }

    if ( '' === $updated ) {
        delete_post_meta( $related_id, $meta_key );
    } else {
        update_post_meta( $related_id, $meta_key, $updated );
    }

    wp_send_json_success();
}

function suz_save_related_order() {
    if ( ! check_ajax_referer( 'suz_related_sort', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'suz-control-panel' ) ), 403 );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'No permission', 'suz-control-panel' ) ), 403 );
    }

    $event_id  = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
    $order_key = isset( $_POST['order_key'] ) ? sanitize_key( wp_unslash( $_POST['order_key'] ) ) : '';
    $ids       = isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array();

    if ( ! $event_id || ! in_array( $order_key, array( 'lecture', 'speaker' ), true ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid data', 'suz-control-panel' ) ), 400 );
    }

    $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
    update_post_meta( $event_id, 'suz_related_order_' . $order_key, $ids );

    wp_send_json_success();
}
