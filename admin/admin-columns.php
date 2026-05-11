<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// =============================================================================
// 7. ADMIN LIST COLUMNS + FILTERING
// suz_event | suz_speaker | suz_lecture
// =============================================================================


// =============================================================================
// 7.1 suz_event – Conferences
// Columns: Title | Event Code | Start Date | End Date | Type | Venue | Status |
//          Categories | Tags | Topics | Ticket | Date Created
// Filters: status, category, topic
// Sortable: start date
// =============================================================================

add_filter( 'manage_suz_event_posts_columns', 'suz_event_admin_columns' );
function suz_event_admin_columns( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        if ( strpos( $key, 'taxonomy-' ) === 0 ) {
            continue;
        }
        $new[ $key ] = $label;
        if ( $key === 'title' ) {
            $new['suz_event_code']     = __( 'Event Code', 'suz-control-panel' );
            $new['suz_start_date']     = __( 'Start Date', 'suz-control-panel' );
            $new['suz_end_date']       = __( 'End Date', 'suz-control-panel' );
            $new['suz_event_type']     = __( 'Type', 'suz-control-panel' );
            $new['suz_location_name']  = __( 'Venue', 'suz-control-panel' );
            $new['suz_event_status']   = __( 'Status', 'suz-control-panel' );
            $new['suz_event_category'] = __( 'Categories', 'suz-control-panel' );
            $new['suz_event_tag']      = __( 'Tags', 'suz-control-panel' );
            $new['suz_event_topic']    = __( 'Topics', 'suz-control-panel' );
            $new['suz_ticket']         = __( 'Ticket', 'suz-control-panel' );
        }
    }
    return $new;
}

add_action( 'manage_suz_event_posts_custom_column', 'suz_event_admin_column_content', 10, 2 );
function suz_event_admin_column_content( $column, $post_id ) {
    switch ( $column ) {

        case 'suz_event_code':
            $val = get_post_meta( $post_id, 'suz_event_code', true );
            echo $val ? esc_html( $val ) : '—';
            break;

        case 'suz_start_date':
            $val = get_field( 'suz_start_date', $post_id );
            echo $val ? esc_html( $val ) : '—';
            break;

        case 'suz_end_date':
            $val = get_field( 'suz_end_date', $post_id );
            echo $val ? esc_html( $val ) : '—';
            break;

        case 'suz_event_type':
            $val = get_field( 'suz_event_type', $post_id );
            $labels = [
                'offline' => __( 'Offline', 'suz-control-panel' ),
                'online'  => __( 'Online', 'suz-control-panel' ),
                'hybrid'  => __( 'Hybrid', 'suz-control-panel' ),
            ];
            echo isset( $labels[ $val ] ) ? esc_html( $labels[ $val ] ) : '—';
            break;

        case 'suz_location_name':
            $val = get_field( 'suz_location_name', $post_id );
            echo $val ? esc_html( $val ) : '—';
            break;

        case 'suz_event_status':
            $val = get_field( 'suz_event_status', $post_id );
            $colors = [
                'scheduled'  => '#2196F3',
                'cancelled'  => '#f44336',
                'postponed'  => '#FF9800',
                'completed'  => '#4CAF50',
            ];
            $labels = [
                'scheduled'  => __( 'Scheduled', 'suz-control-panel' ),
                'cancelled'  => __( 'Cancelled', 'suz-control-panel' ),
                'postponed'  => __( 'Postponed', 'suz-control-panel' ),
                'completed'  => __( 'Completed', 'suz-control-panel' ),
            ];
            if ( $val && isset( $labels[ $val ] ) ) {
                printf(
                    '<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:%s;color:#fff;font-size:11px;">%s</span>',
                    esc_attr( $colors[ $val ] ),
                    esc_html( $labels[ $val ] )
                );
            } else {
                echo '—';
            }
            break;

        case 'suz_event_category':
            $terms = get_the_terms( $post_id, 'suz_event_category' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $links = array_map( function( $t ) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url( add_query_arg( [ 'post_type' => 'suz_event', 'suz_event_category' => $t->slug ] ) ),
                        esc_html( $t->name )
                    );
                }, $terms );
                echo implode( ', ', $links );
            } else {
                echo '—';
            }
            break;

        case 'suz_event_tag':
            $terms = get_the_terms( $post_id, 'suz_event_tag' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $links = array_map( function( $t ) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url( add_query_arg( [ 'post_type' => 'suz_event', 'suz_event_tag' => $t->slug ] ) ),
                        esc_html( $t->name )
                    );
                }, $terms );
                echo implode( ', ', $links );
            } else {
                echo '—';
            }
            break;

        case 'suz_event_topic':
            $terms = get_the_terms( $post_id, 'suz_event_topic' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $links = array_map( function( $t ) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url( add_query_arg( [ 'post_type' => 'suz_event', 'suz_event_topic' => $t->slug ] ) ),
                        esc_html( $t->name )
                    );
                }, $terms );
                echo implode( ', ', $links );
            } else {
                echo '—';
            }
            break;

        case 'suz_ticket':
            $product = get_field( 'suz_event_ticket_product', $post_id );
            if ( $product ) {
                echo '<span style="color:#4CAF50;">&#10003; ' . esc_html__( 'Yes', 'suz-control-panel' ) . '</span>';
            } else {
                echo '<span style="color:#aaa;">&#10007; ' . esc_html__( 'No', 'suz-control-panel' ) . '</span>';
            }
            break;
    }
}

// Sortable columns – suz_event
add_filter( 'manage_edit-suz_event_sortable_columns', 'suz_event_sortable_columns' );
function suz_event_sortable_columns( $cols ) {
    $cols['suz_start_date'] = 'suz_start_date';
    return $cols;
}

// Filters – suz_event: status, category, topic
add_action( 'restrict_manage_posts', 'suz_event_admin_filters', 10, 2 );
function suz_event_admin_filters( $post_type, $which ) {
    if ( 'suz_event' !== $post_type || 'top' !== $which ) {
        return;
    }

    // Filter: Event Status (ACF meta)
    $current_status = isset( $_GET['suz_filter_status'] ) ? sanitize_text_field( $_GET['suz_filter_status'] ) : '';
    $statuses = [
        ''           => __( 'All Statuses', 'suz-control-panel' ),
        'scheduled'  => __( 'Scheduled', 'suz-control-panel' ),
        'cancelled'  => __( 'Cancelled', 'suz-control-panel' ),
        'postponed'  => __( 'Postponed', 'suz-control-panel' ),
        'completed'  => __( 'Completed', 'suz-control-panel' ),
    ];
    echo '<select name="suz_filter_status">';
    foreach ( $statuses as $val => $label ) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr( $val ),
            selected( $current_status, $val, false ),
            esc_html( $label )
        );
    }
    echo '</select>';

    // Filter: Category taxonomy
    $current_cat = isset( $_GET['suz_event_category'] ) ? sanitize_text_field( $_GET['suz_event_category'] ) : '';
    wp_dropdown_categories( [
        'show_option_all' => __( 'All Categories', 'suz-control-panel' ),
        'taxonomy'        => 'suz_event_category',
        'name'            => 'suz_event_category',
        'orderby'         => 'name',
        'selected'        => $current_cat,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ] );

    // Filter: Topic taxonomy
    $current_topic = isset( $_GET['suz_event_topic'] ) ? sanitize_text_field( $_GET['suz_event_topic'] ) : '';
    wp_dropdown_categories( [
        'show_option_all' => __( 'All Topics', 'suz-control-panel' ),
        'taxonomy'        => 'suz_event_topic',
        'name'            => 'suz_event_topic',
        'orderby'         => 'name',
        'selected'        => $current_topic,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ] );
}

// Apply suz_event filters + sort to query
add_action( 'pre_get_posts', 'suz_event_admin_filter_query' );
function suz_event_admin_filter_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->get( 'post_type' ) !== 'suz_event' ) {
        return;
    }

    // Status filter (ACF meta)
    if ( ! empty( $_GET['suz_filter_status'] ) ) {
        $meta = $query->get( 'meta_query' ) ?: [];
        $meta[] = [
            'key'     => 'suz_event_status',
            'value'   => sanitize_text_field( $_GET['suz_filter_status'] ),
            'compare' => '=',
        ];
        $query->set( 'meta_query', $meta );
    }

    // Sortable: start date
    if ( 'suz_start_date' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', 'suz_start_date' );
        $query->set( 'orderby', 'meta_value' );
    }
}


// =============================================================================
// 7.2 suz_speaker – Speakers
// Columns: Name | Working Position | Company | Email | Category |
//          Conference Tags | SUZ Relation | Date Created
// Filters: category, conference tag, SUZ relation
// Sortable: name (title), company
// =============================================================================

add_filter( 'manage_suz_speaker_posts_columns', 'suz_speaker_admin_columns' );
function suz_speaker_admin_columns( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        if ( strpos( $key, 'taxonomy-' ) === 0 ) {
            continue;
        }
        $new[ $key ] = $label;
        if ( $key === 'title' ) {
            $new['suz_speaker_designation'] = __( 'Working Position', 'suz-control-panel' );
            $new['suz_speaker_company']     = __( 'Company', 'suz-control-panel' );
            $new['suz_speaker_email']       = __( 'Email', 'suz-control-panel' );
            $new['suz_speaker_category']    = __( 'Category', 'suz-control-panel' );
            $new['suz_event_tag_speaker']   = __( 'Speaker Tags', 'suz-control-panel' );
            $new['suz_speaker_suz_relation']= __( 'SUZ Relation', 'suz-control-panel' );
        }
    }
    return $new;
}

add_action( 'manage_suz_speaker_posts_custom_column', 'suz_speaker_admin_column_content', 10, 2 );
function suz_speaker_admin_column_content( $column, $post_id ) {
    switch ( $column ) {

        case 'suz_speaker_designation':
            $val = get_field( 'suz_speaker_designation', $post_id );
            echo $val ? esc_html( $val ) : '—';
            break;

        case 'suz_speaker_company':
            $val = get_field( 'suz_speaker_company', $post_id );
            echo $val ? esc_html( $val ) : '—';
            break;

        case 'suz_speaker_email':
            $val = get_field( 'suz_speaker_email', $post_id );
            if ( $val ) {
                echo '<a href="mailto:' . esc_attr( $val ) . '">' . esc_html( $val ) . '</a>';
            } else {
                echo '—';
            }
            break;

        case 'suz_speaker_category':
            $terms = get_the_terms( $post_id, 'suz_speaker_category' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $links = array_map( function( $t ) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url( add_query_arg( [ 'post_type' => 'suz_speaker', 'suz_speaker_category' => $t->slug ] ) ),
                        esc_html( $t->name )
                    );
                }, $terms );
                echo implode( ', ', $links );
            } else {
                echo '—';
            }
            break;

        case 'suz_event_tag_speaker':
            $terms = get_the_terms( $post_id, 'suz_event_tag' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $links = array_map( function( $t ) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        esc_url( add_query_arg( [ 'post_type' => 'suz_speaker', 'suz_event_tag' => $t->slug ] ) ),
                        esc_html( $t->name )
                    );
                }, $terms );
                echo implode( ', ', $links );
            } else {
                echo '—';
            }
            break;

        case 'suz_speaker_suz_relation':
            $val = get_field( 'suz_speaker_suz_relation', $post_id );
            $labels = [
                'member'     => __( 'Member', 'suz-control-panel' ),
                'non_member' => __( 'Non-member', 'suz-control-panel' ),
                'other'      => __( 'Other', 'suz-control-panel' ),
            ];
            echo isset( $labels[ $val ] ) ? esc_html( $labels[ $val ] ) : ( $val ? esc_html( $val ) : '—' );
            break;
    }
}

// Sortable columns – suz_speaker
add_filter( 'manage_edit-suz_speaker_sortable_columns', 'suz_speaker_sortable_columns' );
function suz_speaker_sortable_columns( $cols ) {
    $cols['title']               = 'title';
    $cols['suz_speaker_company'] = 'suz_speaker_company';
    return $cols;
}

// Filters – suz_speaker: category, conference tag, SUZ relation
add_action( 'restrict_manage_posts', 'suz_speaker_admin_filters', 10, 2 );
function suz_speaker_admin_filters( $post_type, $which ) {
    if ( 'suz_speaker' !== $post_type || 'top' !== $which ) {
        return;
    }

    // Filter: Speaker category taxonomy
    $current_cat = isset( $_GET['suz_speaker_category'] ) ? sanitize_text_field( $_GET['suz_speaker_category'] ) : '';
    wp_dropdown_categories( [
        'show_option_all' => __( 'All Categories', 'suz-control-panel' ),
        'taxonomy'        => 'suz_speaker_category',
        'name'            => 'suz_speaker_category',
        'orderby'         => 'name',
        'selected'        => $current_cat,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ] );

    // Filter: Conference tag taxonomy
    $current_tag = isset( $_GET['suz_event_tag'] ) ? sanitize_text_field( $_GET['suz_event_tag'] ) : '';
    wp_dropdown_categories( [
        'show_option_all' => __( 'All Conference Tags', 'suz-control-panel' ),
        'taxonomy'        => 'suz_event_tag',
        'name'            => 'suz_event_tag',
        'orderby'         => 'name',
        'selected'        => $current_tag,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ] );

    // Filter: SUZ Relation (ACF meta)
    $current_rel = isset( $_GET['suz_filter_relation'] ) ? sanitize_text_field( $_GET['suz_filter_relation'] ) : '';
    $relations = [
        ''           => __( 'All SUZ Relations', 'suz-control-panel' ),
        'member'     => __( 'Member', 'suz-control-panel' ),
        'non_member' => __( 'Non-member', 'suz-control-panel' ),
        'other'      => __( 'Other', 'suz-control-panel' ),
    ];
    echo '<select name="suz_filter_relation">';
    foreach ( $relations as $val => $label ) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr( $val ),
            selected( $current_rel, $val, false ),
            esc_html( $label )
        );
    }
    echo '</select>';
}

// Apply suz_speaker filters + sort to query
add_action( 'pre_get_posts', 'suz_speaker_admin_filter_query' );
function suz_speaker_admin_filter_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->get( 'post_type' ) !== 'suz_speaker' ) {
        return;
    }

    // SUZ Relation filter (ACF meta)
    if ( ! empty( $_GET['suz_filter_relation'] ) ) {
        $meta = $query->get( 'meta_query' ) ?: [];
        $meta[] = [
            'key'     => 'suz_speaker_suz_relation',
            'value'   => sanitize_text_field( $_GET['suz_filter_relation'] ),
            'compare' => '=',
        ];
        $query->set( 'meta_query', $meta );
    }

    // Sortable: company (ACF meta)
    if ( 'suz_speaker_company' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', 'suz_speaker_company' );
        $query->set( 'orderby', 'meta_value' );
    }
}


// =============================================================================
// 7.3 suz_lecture – Lectures
// Columns: Title | Conference Tag | Time (from–to) | Speaker | Day |
//          Block | Type | Room | Date Created
// Filters: conference tag (default current), day, block, type
// Sortable: time from
// =============================================================================

add_filter( 'manage_suz_lecture_posts_columns', 'suz_lecture_admin_columns' );
function suz_lecture_admin_columns( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        // Strip all auto-added taxonomy columns — we control them manually
        if ( strpos( $key, 'taxonomy-' ) === 0 ) {
            continue;
        }
        $new[ $key ] = $label;
        if ( $key === 'title' ) {
            $new['suz_lecture_tag']   = __( 'Conference Tag', 'suz-control-panel' );
            $new['suz_lecture_time']  = __( 'Time', 'suz-control-panel' );
            $new['suz_lecture_spkr']  = __( 'Speaker', 'suz-control-panel' );
            $new['suz_lecture_day']   = __( 'Day', 'suz-control-panel' );
            $new['suz_lecture_block'] = __( 'Block', 'suz-control-panel' );
            $new['suz_lecture_type']  = __( 'Type', 'suz-control-panel' );
            $new['suz_lecture_room']  = __( 'Room', 'suz-control-panel' );
        }
    }
    return $new;
}

add_action( 'manage_suz_lecture_posts_custom_column', 'suz_lecture_admin_column_content', 10, 2 );
function suz_lecture_admin_column_content( $column, $post_id ) {
    switch ( $column ) {

        case 'suz_lecture_tag':
            $terms = get_the_terms( $post_id, 'suz_event_tag' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $links = array_map( function( $t ) {
                    return sprintf(
                        '<a href="%s"><strong>%s</strong></a>',
                        esc_url( add_query_arg( [ 'post_type' => 'suz_lecture', 'suz_event_tag' => $t->slug ] ) ),
                        esc_html( $t->name )
                    );
                }, $terms );
                echo implode( ', ', $links );
            } else {
                echo '—';
            }
            break;

        case 'suz_lecture_time':
            $from = get_field( 'suz_time_from', $post_id );
            $to   = get_field( 'suz_time_to', $post_id );
            if ( $from || $to ) {
                echo esc_html( ( $from ?: '?' ) . ' – ' . ( $to ?: '?' ) );
            } else {
                echo '—';
            }
            break;

        case 'suz_lecture_spkr':
            $speakers = get_field( 'suz_lecture_speaker', $post_id );
            if ( $speakers ) {
                if ( ! is_array( $speakers ) ) {
                    $speakers = [ $speakers ];
                }
                $names = array_map( function( $s ) {
                    if ( is_object( $s ) && isset( $s->ID ) ) {
                        return sprintf(
                            '<a href="%s">%s</a>',
                            esc_url( get_edit_post_link( $s->ID ) ),
                            esc_html( $s->post_title )
                        );
                    }
                    return '';
                }, $speakers );
                $names = array_filter( $names );
                echo $names ? implode( ', ', $names ) : '—';
            } else {
                // Fallback: speaker company text
                $fallback = get_field( 'suz_lecture_speaker_company', $post_id );
                echo $fallback ? esc_html( $fallback ) : '—';
            }
            break;

        case 'suz_lecture_day':
            $terms = get_the_terms( $post_id, 'suz_lecture_day' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
            } else {
                echo '—';
            }
            break;

        case 'suz_lecture_block':
            $terms = get_the_terms( $post_id, 'suz_lecture_block' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
            } else {
                echo '—';
            }
            break;

        case 'suz_lecture_type':
            $terms = get_the_terms( $post_id, 'suz_lecture_type_tax' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
            } else {
                echo '—';
            }
            break;

        case 'suz_lecture_room':
            $val = get_field( 'suz_lecture_place', $post_id );
            echo $val ? esc_html( $val ) : '—';
            break;
    }
}

// Sortable columns – suz_lecture
add_filter( 'manage_edit-suz_lecture_sortable_columns', 'suz_lecture_sortable_columns' );
function suz_lecture_sortable_columns( $cols ) {
    $cols['suz_lecture_time'] = 'suz_time_from';
    return $cols;
}

// Filters – suz_lecture: conference tag (default current), day, block, type
add_action( 'restrict_manage_posts', 'suz_lecture_admin_filters', 10, 2 );
function suz_lecture_admin_filters( $post_type, $which ) {
    if ( 'suz_lecture' !== $post_type || 'top' !== $which ) {
        return;
    }

    // Default conference tag: most recently created suz_event_tag term
    $current_tag = isset( $_GET['suz_event_tag'] ) ? sanitize_text_field( $_GET['suz_event_tag'] ) : '';
    if ( '' === $current_tag ) {
        // Auto-default to most recent tag (by term_id desc)
        $latest_terms = get_terms( [
            'taxonomy'   => 'suz_event_tag',
            'orderby'    => 'term_id',
            'order'      => 'DESC',
            'number'     => 1,
            'hide_empty' => false,
        ] );
        if ( ! is_wp_error( $latest_terms ) && ! empty( $latest_terms ) ) {
            $current_tag = $latest_terms[0]->slug;
        }
    }

    // Filter: Conference tag
    wp_dropdown_categories( [
        'show_option_all' => __( 'All Conferences', 'suz-control-panel' ),
        'taxonomy'        => 'suz_event_tag',
        'name'            => 'suz_event_tag',
        'orderby'         => 'name',
        'selected'        => $current_tag,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ] );

    // Filter: Day
    $current_day = isset( $_GET['suz_lecture_day'] ) ? sanitize_text_field( $_GET['suz_lecture_day'] ) : '';
    wp_dropdown_categories( [
        'show_option_all' => __( 'All Days', 'suz-control-panel' ),
        'taxonomy'        => 'suz_lecture_day',
        'name'            => 'suz_lecture_day',
        'orderby'         => 'name',
        'selected'        => $current_day,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ] );

    // Filter: Block
    $current_block = isset( $_GET['suz_lecture_block'] ) ? sanitize_text_field( $_GET['suz_lecture_block'] ) : '';
    wp_dropdown_categories( [
        'show_option_all' => __( 'All Blocks', 'suz-control-panel' ),
        'taxonomy'        => 'suz_lecture_block',
        'name'            => 'suz_lecture_block',
        'orderby'         => 'name',
        'selected'        => $current_block,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ] );

    // Filter: Lecture type
    $current_type = isset( $_GET['suz_lecture_type_tax'] ) ? sanitize_text_field( $_GET['suz_lecture_type_tax'] ) : '';
    wp_dropdown_categories( [
        'show_option_all' => __( 'All Types', 'suz-control-panel' ),
        'taxonomy'        => 'suz_lecture_type_tax',
        'name'            => 'suz_lecture_type_tax',
        'orderby'         => 'name',
        'selected'        => $current_type,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ] );
}

// Apply suz_lecture filters + sort to query
add_action( 'pre_get_posts', 'suz_lecture_admin_filter_query' );
function suz_lecture_admin_filter_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->get( 'post_type' ) !== 'suz_lecture' ) {
        return;
    }

    // Default conference tag when no explicit filter set
    if ( empty( $_GET['suz_event_tag'] ) && empty( $_GET['post_status'] ) ) {
        $latest_terms = get_terms( [
            'taxonomy'   => 'suz_event_tag',
            'orderby'    => 'term_id',
            'order'      => 'DESC',
            'number'     => 1,
            'hide_empty' => false,
        ] );
        if ( ! is_wp_error( $latest_terms ) && ! empty( $latest_terms ) ) {
            $tax_query = $query->get( 'tax_query' ) ?: [];
            $tax_query[] = [
                'taxonomy' => 'suz_event_tag',
                'field'    => 'slug',
                'terms'    => $latest_terms[0]->slug,
            ];
            $query->set( 'tax_query', $tax_query );
        }
    }

    // Sortable: time from (ACF meta)
    if ( 'suz_time_from' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', 'suz_time_from' );
        $query->set( 'orderby', 'meta_value' );
    }
}
