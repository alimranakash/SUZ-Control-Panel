<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if IČo exists in SUZ member companies table (MySQL).
 */
function suz_check_ico_in_member_list( $ico ) {
    global $wpdb;
    $ico         = sanitize_text_field( $ico );
    $table_name  = $wpdb->prefix . 'suz_member_companies';

    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE ico = %s",
        $ico
    ) );

    return $exists > 0;
}

/**
 * ico verified by hafij start
 */
// function suz_check_ico_in_member_list( $ico ) {
//     global $wpdb;

//     $ico = trim( sanitize_text_field( $ico ) );
//     if ( $ico === '' ) return false;

//     $usermeta_table = $wpdb->usermeta;                 // e.g. wp_usermeta
//     $cap_key        = $wpdb->prefix . 'capabilities';  // e.g. wp_capabilities

//     // Count users who have this IČO AND have either suz_member or suz_representative in their caps
//     $sql = "
//         SELECT COUNT(DISTINCT um_ico.user_id)
//         FROM {$usermeta_table} AS um_ico
//         INNER JOIN {$usermeta_table} AS um_caps
//             ON um_caps.user_id = um_ico.user_id
//            AND um_caps.meta_key = %s
//         WHERE um_ico.meta_key = %s
//           AND um_ico.meta_value = %s
//           AND (um_caps.meta_value LIKE %s OR um_caps.meta_value LIKE %s)
//     ";

//     $count = (int) $wpdb->get_var( $wpdb->prepare(
//         $sql,
//         $cap_key,                  // %s for meta_key (caps key)
//         'company_ico',             // %s for meta_key (our ico key)
//         $ico,                      // %s for meta_value (the ico)
//         '%"suz_member"%',          // LIKE pattern in serialized caps
//         '%"suz_representative"%'   // LIKE pattern in serialized caps
//     ) );

//     return $count > 0;
// }
/**
 * ico verified by hafij end
 */

/**
 * Sync user Mailchimp tag based on role.
 */
function suz_sync_mailchimp_tag( $user_id, $role ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }

    $email = $user->user_email;
    $tag   = '';

    switch ( $role ) {
        case 'suz_member':
            $tag = 'suz_clen';
            break;
        case 'suz_non_member':
            $tag = 'suz_neclen';
            break;
        case 'suz_representative':
            $tag = 'suz_zastupca';
            break;
        case 'vip':
            $tag = 'suz_vip';
            break;
    }

    // TODO: Implement Mailchimp API call here.
}

/**
 * Admin page for IČo verification with search, filter, export.
 */
function suz_panel_render_ico_verification_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Permission denied', 'suz' ) );
    }

    $role_filter = isset( $_GET['role'] ) ? sanitize_text_field( $_GET['role'] ) : '';
    $email_query = isset( $_GET['s'] ) ? sanitize_email( $_GET['s'] ) : '';

    $args = array(
        'meta_key'     => 'company_ico',
        'meta_compare' => 'EXISTS',
        'number'       => 500,
    );

    if ( ! empty( $email_query ) ) {
        $args['search'] = "*{$email_query}*";
        $args['search_columns'] = array( 'user_email' );
    }

    $users = ( new WP_User_Query( $args ) )->get_results();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'IČo Verification Tracker', 'suz' ) . '</h1>';

    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="suz-ico-tracker">';
    echo '<input type="text" name="s" value="' . esc_attr( $email_query ) . '" placeholder="' . esc_attr__( 'Search by email', 'suz' ) . '">';
    echo '<select name="role">';
    echo '<option value="">' . esc_html__( 'All Roles', 'suz' ) . '</option>';
    $roles = array( 'suz_member', 'suz_non_member', 'suz_representative', 'vip' );
    foreach ( $roles as $role ) {
        echo '<option value="' . esc_attr( $role ) . '"' . selected( $role_filter, $role, false ) . '>' . esc_html( $role ) . '</option>';
    }
    echo '</select> <button class="button">' . esc_html__( 'Filter', 'suz' ) . '</button>';
    echo '</form>';

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>' . esc_html__( 'User', 'suz' ) . '</th><th>' . esc_html__( 'Email', 'suz' ) . '</th><th>' . esc_html__( 'IČo', 'suz' ) . '</th><th>' . esc_html__( 'Role', 'suz' ) . '</th><th>' . esc_html__( 'Status', 'suz' ) . '</th><th>' . esc_html__( 'Action', 'suz' ) . '</th></tr></thead><tbody>';

    if ( $users ) {
        foreach ( $users as $user ) {
            $ico       = get_user_meta( $user->ID, 'company_ico', true );
            $ico_valid = suz_check_ico_in_member_list( $ico );
            $role      = implode( ', ', $user->roles );

            if ( $role_filter && strpos( $role, $role_filter ) === false ) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html( $user->display_name ) . '</td>';
            echo '<td>' . esc_html( $user->user_email ) . '</td>';
            echo '<td>' . esc_html( $ico ) . '</td>';
            echo '<td>' . esc_html( $role ) . '</td>';
            echo '<td>' . ( $ico_valid ? '<span style="color:green;">✔ ' . esc_html__( 'verified', 'suz' ) . '</span>' : '<span style="color:red;">✘ ' . esc_html__( 'Not Verified', 'suz' ) . '</span>' ) . '</td>';
            echo '<td>';
            echo '<form method="post">';
            wp_nonce_field( 'suz_ico_role_update', 'suz_ico_nonce' );
            echo '<input type="hidden" name="user_id" value="' . esc_attr( $user->ID ) . '" />';
            echo '<select name="new_role">';
            foreach ( $roles as $r ) {
                echo '<option value="' . esc_attr( $r ) . '"' . selected( in_array( $r, $user->roles, true ), true, false ) . '>' . esc_html( ucfirst( str_replace( 'suz_', '', $r ) ) ) . '</option>';
            }
            echo '</select>';
            echo '<input type="submit" name="update_role" class="button button-small" value="' . esc_attr__( 'Update Role', 'suz' ) . '">';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">' . esc_html__( 'No users with IČo found.', 'suz' ) . '</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

/**
 * Handle role update from admin panel.
 */
add_action( 'admin_init', 'suz_handle_ico_role_update' );
function suz_handle_ico_role_update() {
    if ( ! isset( $_POST['update_role'], $_POST['user_id'] ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! isset( $_POST['suz_ico_nonce'] ) || ! wp_verify_nonce( $_POST['suz_ico_nonce'], 'suz_ico_role_update' ) ) {
        wp_die( __( 'Security check failed.', 'suz' ) );
    }

    $user_id  = absint( $_POST['user_id'] );
    $new_role = sanitize_text_field( $_POST['new_role'] );
    $user     = get_userdata( $user_id );

    if ( $user ) {
        $roles_to_remove = array( 'suz_member', 'suz_non_member', 'suz_representative', 'vip' );
        foreach ( $roles_to_remove as $role ) {
            $user->remove_role( $role );
        }

        $user->add_role( $new_role );
        suz_sync_mailchimp_tag( $user_id, $new_role );

        add_action( 'admin_notices', function() use ( $user_id, $new_role ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'User #%d role updated to %s successfully.', 'suz' ), $user_id, $new_role ) . '</p></div>';
        } );
    }
}

/**
 * Add SUZ ICO Tracker page to admin menu.
 */
add_action( 'admin_menu', function() {
    add_menu_page(
        __( 'SUZ ICO Tracker', 'suz' ),
        __( 'SUZ ICO Tracker', 'suz' ),
        'manage_options',
        'suz-ico-tracker',
        'suz_panel_render_ico_verification_page',
        'dashicons-id-alt',
        56
    );
} );