<?php
/**
 * Handle Company ICO, VAT ID, and Tax ID fields in WooCommerce Billing Address Edit form.
 *
 * This script:
 * - Adds Company ICO, VAT ID, and Tax ID fields to the billing address edit form.
 * - Saves these fields to user meta when updated.
 * - Assigns user role based on ICO status (if ICO is provided).
 * - Syncs Mailchimp tags accordingly.
 */
function suz_add_company_fields_to_billing_address( $fields ) {
    // Company Name field (custom)
    $fields['billing_company'] = array(
        'label'    => esc_html__( 'Company Name', 'suz-control-panel' ),
        'required' => false,
        'class'    => array( 'form-row-wide' ),
        'clear'    => true,
        'priority' => 115,
        'type'     => 'text',
    );

    // ICO field
    $fields['billing_ico'] = array(
        'label'    => esc_html__( 'Company ICO', 'suz-control-panel' ),
        'required' => false,
        'class'    => array( 'form-row-wide' ),
        'clear'    => true,
        'priority' => 120,
        'type'     => 'text',
    );

    // VAT ID field
    $fields['billing_vat_id'] = array(
        'label'    => esc_html__( 'VAT ID', 'suz-control-panel' ),
        'required' => false,
        'class'    => array( 'form-row-wide' ),
        'clear'    => true,
        'priority' => 125,
        'type'     => 'text',
    );

    // Tax ID field
    $fields['billing_tax_id'] = array(
        'label'    => esc_html__( 'Tax ID', 'suz-control-panel' ),
        'required' => false,
        'class'    => array( 'form-row-wide' ),
        'clear'    => true,
        'priority' => 130,
        'type'     => 'text',
    );

    return $fields;
}
// add_filter( 'woocommerce_billing_fields', 'suz_add_company_fields_to_billing_address' );

/**
 * Process and store Company Name, ICO, VAT ID, and Tax ID fields when billing address is updated.
 *
 * @param int    $user_id      The user ID.
 * @param string $load_address The address type being saved.
 */
function suz_process_company_fields_on_billing_address_update( $user_id, $load_address ) {
    if ( 'billing' !== $load_address ) {
        return;
    }

    $user = get_userdata( $user_id );

    // Prepare Mailchimp data array
    $mc_data = [
        'user_id' => $user_id,
        'email'   => $user->user_email,
        
    ];

    // Company Name field
    if ( isset( $_POST['billing_company'] ) ) {
        $company_name = sanitize_text_field( wp_unslash( $_POST['billing_company'] ) );
        update_user_meta( $user_id, 'company_name', $company_name );
    }

    // ICO field
    if ( isset( $_POST['_wc_billing/suz/ico'] ) ) {
        $ico = sanitize_text_field( wp_unslash( $_POST['_wc_billing/suz/ico'] ) );
        $old_ico = get_user_meta( $user_id, 'company_ico', true );
        if($old_ico != $ico){
            update_user_meta( $user_id, 'company_ico', $ico );
        }
        
        $mc_data['ico'] = $ico;

        // Remove existing SUZ-related roles
        if($old_ico != $ico){
            $roles_to_remove = array( 'suz_member', 'suz_non_member', 'suz_representative', 'vip' );
            foreach ( $roles_to_remove as $role ) {
                if ( in_array( $role, (array) $user->roles, true ) ) {
                    $user->remove_role( $role );
                }
            }
        }

        // Assign new role if ICO is provided
        if ( ! empty( $ico ) && $old_ico != $ico) {
            $is_member = function_exists( 'suz_check_ico_in_member_list' ) ? suz_check_ico_in_member_list( $ico ) : false;
            $new_role  = $is_member ? 'suz_member' : 'suz_non_member';
            $user->add_role( $new_role );
            update_user_meta( $user_id, 'suz_assigned_role', $new_role );
            $mc_data['role'] = $new_role;

            // // Sync Mailchimp tags if function exists
            // if ( function_exists( 'suz_sync_mailchimp_tag' ) ) {
            //     suz_sync_mailchimp_tag( $user_id, $new_role );
            // }
        }
    }

    // VAT ID field
    if ( isset( $_POST['billing_vat_id'] ) ) {
        $vat_id = sanitize_text_field( wp_unslash( $_POST['billing_vat_id'] ) );
        update_user_meta( $user_id, 'company_vat_id', $vat_id );
    }

    // Tax ID field
    if ( isset( $_POST['billing_tax_id'] ) ) {
        $tax_id = sanitize_text_field( wp_unslash( $_POST['billing_tax_id'] ) );
        update_user_meta( $user_id, 'company_tax_id', $tax_id );
    }

    // added by hafij start
    if ( isset( $_POST['billing_first_name'] ) ) {
        update_user_meta( $user_id, 'first_name', sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) );
        $mc_data['first_name'] = sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) );
    }
    if ( isset( $_POST['billing_last_name'] ) ) {
        update_user_meta( $user_id, 'last_name', sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) ) );
        $mc_data['last_name'] = sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) );
    }

    if ( isset( $_POST['billing_phone'] ) ) {
        $mc_data['phone'] = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) )??'-';
    }

    if ( isset( $_POST['_wc_billing/suz/company'] ) ) {
        $mc_data['company'] = sanitize_text_field( wp_unslash( $_POST['_wc_billing/suz/company'] ) );
    }

    // $address = '';

    // if ( isset( $_POST['billing_postcode'] ) ) {
    //     $address = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
    // }

    if ( isset( $_POST['billing_address_1'] ) ) {
        // if($address != ''){
        //     $address .= ', ' . sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ) );
        // }else{
        //     $address = sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ) );
        // }
        $mc_data['address'] = sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ) );
    }

    if ( isset( $_POST['billing_city'] ) ) {
        // if($address != ''){
        //     $address .= ', ' . sanitize_text_field( wp_unslash( $_POST['billing_city'] ) );
        // }else{
        //     $address = sanitize_text_field( wp_unslash( $_POST['billing_city'] ) );
        // }
        $mc_data['city'] = sanitize_text_field( wp_unslash( $_POST['billing_city'] ) );
    }
    if ( isset( $_POST['billing_state'] ) ) {
        // if($address != ''){
        //     $address .= ', ' . sanitize_text_field( wp_unslash( $_POST['billing_state'] ) );
        // }else{
        //     $address = sanitize_text_field( wp_unslash( $_POST['billing_state'] ) );
        // }
        $mc_data['state'] = sanitize_text_field( wp_unslash( $_POST['billing_state'] ) );
    }
    if ( isset( $_POST['billing_country'] ) ) {
        // if($address != ''){
        //     $address .= ', ' . sanitize_text_field( wp_unslash( $_POST['billing_country'] ) );
        // }else{
        //     $address = sanitize_text_field( wp_unslash( $_POST['billing_country'] ) );
        // }
        $mc_data['country'] = sanitize_text_field( wp_unslash( $_POST['billing_country'] ) );
    }

    if ( isset( $_POST['billing_postcode'] ) ) {
        $mc_data['postcode'] = sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ) );
    }

    // $mc_data['address'] = $address??'-';


    // for update mailchimp data
    if ( function_exists( 'suz_sync_mailchimp_contact' ) ) {
        error_log('mail data from profile update: '. json_encode($mc_data));
        suz_sync_mailchimp_contact( $mc_data );
    }
    if ( function_exists( 'suz_sync_mailchimp_role_tags' ) && $old_ico != $ico) {
        suz_sync_mailchimp_role_tags( $user_id, $mc_data['role'] );
    }
    

    // added by hafij end
}
add_action( 'woocommerce_customer_save_address', 'suz_process_company_fields_on_billing_address_update', 10, 2 );

/**
 * Remove default Wishlist endpoint from My Account menu.
 *
 * @param array $menu_items Existing menu items.
 * @return array Modified menu items.
 */
function suz_remove_wishlist_endpoint( $menu_items ) {
    // remove wishlist
    if ( isset( $menu_items['wishlist'] ) ) {
        unset( $menu_items['wishlist'] );
    }
    return $menu_items;
}
add_filter( 'woocommerce_account_menu_items', 'suz_remove_wishlist_endpoint', 999 );