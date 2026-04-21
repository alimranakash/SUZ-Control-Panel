<?php
/**
 * Custom Member Status page in WooCommerce My Account.
 * Auto-update user role based on company ICO field.
 *
 * @package SUZ_Control_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register a new endpoint for Member Status in My Account menu.
 */
function suz_add_member_status_endpoint() {
    add_rewrite_endpoint( 'member-status', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'suz_add_member_status_endpoint' );

/**
 * Flush rewrite rules on plugin activation.
 */
function suz_flush_rewrite_rules_on_activation() {
    suz_add_member_status_endpoint();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'suz_flush_rewrite_rules_on_activation' );

/**
 * Redirect users after logout.
 */
function suz_redirect_after_logout() {
    wp_safe_redirect( site_url( '/registration/' ) );
    exit;
}
add_action( 'wp_logout', 'suz_redirect_after_logout' );

/**
 * Add Member Status tab to WooCommerce My Account menu.
 */
function suz_add_member_status_menu_item( $items ) {
    $new_items = [];

    foreach ( $items as $key => $item ) {
        if ( 'customer-logout' === $key ) {
            $new_items['member-status'] = esc_html__( 'Account Status', 'suz-control-panel' );
        }
        $new_items[ $key ] = $item;
    }

    return $new_items;
}
add_filter( 'woocommerce_account_menu_items', 'suz_add_member_status_menu_item' );

/**
 * Display content for the Member Status endpoint.
 */
function suz_member_status_content() {
    if ( ! is_user_logged_in() ) {
        echo '<p>' . esc_html__( 'Please log in to view your membership status.', 'suz-control-panel' ) . '</p>';
        return;
    }

    $user  = wp_get_current_user();
    $roles = $user->roles;
    $ico   = get_user_meta( $user->ID, 'company_ico', true );

    // Ensure SUZ role exists; fallback to suz_non_member if not
    $suz_roles = array( 'suz_member', 'suz_non_member', 'suz_representative', 'vip' );
    $has_suz_role = false;

    foreach ( $suz_roles as $suz_role ) {
        if ( in_array( $suz_role, $roles, true ) ) {
            $has_suz_role = true;
            break;
        }
    }

    if ( ! $has_suz_role ) {
        $user->add_role( 'suz_non_member' );
        $roles[] = 'suz_non_member';
        update_user_meta( $user->ID, 'suz_assigned_role', 'suz_non_member' );
    }

    // if ( ! in_array( 'etn-customer', $roles, true ) ) {
    //     $user->add_role( 'etn-customer' );
    //     $roles[] = 'etn-customer';
    // }

    if ( ! in_array( 'customer', $roles, true ) ) {
        $user->add_role( 'customer' );
        $roles[] = 'customer';
    }

    echo '<h3>' . esc_html__( 'Your Account Status', 'suz-control-panel' ) . '</h3>';
    echo '<p><strong>' . esc_html__( 'Username:', 'suz-control-panel' ) . '</strong> ' . esc_html( $user->user_login ) . '</p>';
    if($ico){
        echo '<p><strong>' . esc_html__( 'Company ICO:', 'suz-control-panel' ) . '</strong> ' . esc_html( $ico ) . '</p>';
    }
    // echo '<p><strong>' . esc_html__( 'Role(s):', 'suz-control-panel' ) . '</strong> ' . esc_html( implode( ', ', array_map( 'ucfirst', $roles ) ) ) . '</p>';

    if ( in_array( 'suz_representative', $roles, true ) ) {
        echo '<p><strong>' . esc_html__( 'Status:', 'suz-control-panel' ) . '</strong> <span style="color: green;">' . esc_html__( 'SUZ Member (Representative)', 'suz-control-panel' ) . '</span></p>';
    } elseif ( in_array( 'suz_member', $roles, true ) ) {
        echo '<p><strong>' . esc_html__( 'Status:', 'suz-control-panel' ) . '</strong> <span style="color: blue;">' . esc_html__( 'SUZ Member', 'suz-control-panel' ) . '</span></p>';
    } elseif ( in_array( 'vip', $roles, true ) ) {
        echo '<p><strong>' . esc_html__( 'Status:', 'suz-control-panel' ) . '</strong> <span style="color: purple;">' . esc_html__( 'VIP Member', 'suz-control-panel' ) . '</span></p>';
    } else {
        echo '<p><strong>' . esc_html__( 'Status:', 'suz-control-panel' ) . '</strong> <span style="color: gray;">' . esc_html__( 'SUZ Non Member', 'suz-control-panel' ) . '</span></p>';
    }
}
add_action( 'woocommerce_account_member-status_endpoint', 'suz_member_status_content' );

/**
 * Common SUZ role assignment logic for backend/frontend.
 */
function suz_update_suz_roles( $user_id, $ico ) {
    $ico  = sanitize_text_field( $ico );
    $user = get_userdata( $user_id );
    if ( ! $user ) return;

    update_user_meta( $user_id, 'company_ico', $ico );

    $is_member = function_exists( 'suz_check_ico_in_member_list' ) ? suz_check_ico_in_member_list( $ico ) : false;
    $new_role  = $is_member ? 'suz_representative' : 'suz_non_member';

    if ( function_exists( 'suz_get_custom_roles' ) ) {
        $suz_roles = array_keys( suz_get_custom_roles() );
        foreach ( $suz_roles as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) {
                $user->remove_role( $role );
            }
        }
    }

    if ( ! in_array( $new_role, (array) $user->roles, true ) ) {
        $user->add_role( $new_role );
    }

    // if ( ! in_array( 'etn-customer', (array) $user->roles, true ) ) {
    //     $user->add_role( 'etn-customer' );
    // }

    if ( ! in_array( 'customer', (array) $user->roles, true ) ) {
        $user->add_role( 'customer' );
    }

    update_user_meta( $user_id, 'suz_assigned_role', $new_role );

    if ( function_exists( 'suz_sync_mailchimp_tag' ) ) {
        suz_sync_mailchimp_tag( $user_id, $new_role );
    }
}

/**
 * Update SUZ role when ICO is changed in admin backend.
 */
function suz_update_role_on_ico_change( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) return;

    if ( isset( $_POST['company_ico'] ) ) {
        suz_update_suz_roles( $user_id, $_POST['company_ico'] );
    }
}
add_action( 'personal_options_update', 'suz_update_role_on_ico_change' );
add_action( 'edit_user_profile_update', 'suz_update_role_on_ico_change' );

/**
 * Update SUZ role when ICO is changed from frontend.
 */
function suz_update_role_on_ico_change_frontend( $user_id ) {
    if ( isset( $_POST['company_ico'] ) ) {
        suz_update_suz_roles( $user_id, $_POST['company_ico'] );
    }
}
add_action( 'woocommerce_save_account_details', 'suz_update_role_on_ico_change_frontend' );