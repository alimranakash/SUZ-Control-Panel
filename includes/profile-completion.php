<?php
/**
 * Handle ICO update and user role assignment on profile update.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle WPForms profile form submission.
 * Updates user meta, assigns SUZ role based on ICO, and syncs with Mailchimp.
 */
add_action( 'wpforms_process_complete', 'suz_handle_wpforms_profile_submission', 10, 4 );
function suz_handle_wpforms_profile_submission( $fields, $entry, $form_data, $entry_id ) {

    // Run only for specific WPForms profile form
    if ( intval( $form_data['id'] ) !== SUZ_WPFORMS_PROFILE_FORM_ID ) return;

    // Ensure the user is logged in
    if ( ! is_user_logged_in() ) return;

    $user       = wp_get_current_user();
    $user_id    = $user->ID;
    $ico        = '';
    $first_name = '';
    $last_name  = '';

    // Prepare Mailchimp data array
    $mc_data = [
        'user_id' => $user_id,
        'email'   => $user->user_email
    ];

    // Loop through submitted fields
    foreach ( $fields as $field ) {
        $field_id = intval( $field['id'] );
        $value    = isset( $field['value'] ) ? sanitize_text_field( $field['value'] ) : '';

        // ICO field
        if ( $field_id === SUZ_WPFORMS_ICO_FIELD_ID ) {
            $ico = $value;
            $mc_data['ico'] = $ico;
        }

        // Name field (first & last)
        if ( $field_id === SUZ_WPFORMS_NAME_FIELD_ID ) {
            $first_name = isset( $field['first'] ) ? sanitize_text_field( $field['first'] ) : '';
            $last_name  = isset( $field['last'] ) ? sanitize_text_field( $field['last'] ) : '';
            $mc_data['first_name'] = $first_name;
            $mc_data['last_name']  = $last_name;
        }

        // Address field (example ID: 5)
        if ( $field_id == 5 ) {
            $address_1 = sanitize_text_field( $field['address1'] ?? '' );
            $address_2 = sanitize_text_field( $field['address2'] ?? '' );
            $city      = sanitize_text_field( $field['city'] ?? '' );
            $state     = sanitize_text_field( $field['state'] ?? '' );
            $country   = sanitize_text_field( $field['country'] ?? '' );
            $postal    = sanitize_text_field( $field['postal'] ?? '' );

            update_user_meta( $user_id, 'billing_address_1', $address_1 );
            update_user_meta( $user_id, 'billing_address_2', $address_2 );
            update_user_meta( $user_id, 'billing_city', $city );
            update_user_meta( $user_id, 'billing_state', $state );
            update_user_meta( $user_id, 'billing_country', $country );
            update_user_meta( $user_id, 'billing_postcode', $postal );

            $mc_data['address'] = $address_1;
            $mc_data['postcode'] = $postal;
            $mc_data['city'] = $city;
            $mc_data['state'] = $state;
            $mc_data['country'] = $country;
        }

        // Other fields
        if ( $field_id == 12 ) update_user_meta( $user_id, 'billing_vat_id', $value );
        if ( $field_id == 10 ) update_user_meta( $user_id, 'billing_tax_id', $value );
        if ( $field_id == 9 ) {
            update_user_meta( $user_id, 'billing_phone', $value );
            $mc_data['phone'] = !empty($value) ? $value : '-';
        }
        if ( $field_id == 3 ) {
            update_user_meta( $user_id, 'billing_company', $value );
            $mc_data['company'] = $value;
        }
    }

    // Update first and last name
    if ( !empty($first_name) ) update_user_meta( $user_id, 'first_name', $first_name );
    if ( !empty($first_name) ) update_user_meta( $user_id, 'billing_first_name', $first_name );
    if ( !empty($last_name) )  update_user_meta( $user_id, 'last_name', $last_name );
    if ( !empty($last_name) )  update_user_meta( $user_id, 'billing_last_name', $last_name );


    $display_name = '';
    if(!empty($first_name)){
        $display_name = $first_name;
    }
    if(!empty($last_name)){
        $display_name .= ' ' . $last_name;
    }
    if($display_name != ''){
         wp_update_user( [
            'ID'           => $user_id,
            'display_name' => $display_name,
        ] );
    }


    // Update ICO meta
    update_user_meta( $user_id, 'company_ico', $ico );
    update_user_meta( $user_id, 'billing_ico', $ico );

    // Determine SUZ role based on ICO
    $new_role = 'suz_non_member';
    if ( !empty($ico) && function_exists('suz_check_ico_in_member_list') && suz_check_ico_in_member_list($ico) ) {
        $new_role = 'suz_member';
    }

    // Remove old SUZ roles
    $roles_to_remove = [ 'suz_member', 'suz_non_member', 'suz_representative', 'vip' ];
    foreach ( $roles_to_remove as $role ) {
        if ( in_array( $role, (array) $user->roles, true ) ) $user->remove_role($role);
    }

    // Assign new SUZ role
    $user->add_role( $new_role );
    $mc_data['role'] = $new_role;

    // Ensure 'etn-customer' role is assigned
    // if ( !in_array('etn-customer', (array)$user->roles, true) ) $user->add_role('etn-customer');

    // Update assigned role meta
    update_user_meta( $user_id, 'suz_assigned_role', $new_role );

    
    if ( function_exists( 'suz_sync_mailchimp_contact' ) ) {
        suz_sync_mailchimp_contact( $mc_data );
    }
}
