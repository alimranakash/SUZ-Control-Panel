<?php
/**
 * Add Company Name, ICO, VAT ID, and Tax ID fields to WooCommerce Checkout.
 */

add_filter( 'woocommerce_checkout_fields', 'suz_add_company_fields_to_checkout' );
function suz_add_company_fields_to_checkout( $fields ) {
    // Company Name field (custom)
    $fields['billing']['billing_company'] = array(
        'label'    => __( 'Company Name', 'suz' ),
        'required' => false,
        'class'    => array( 'form-row-wide' ),
        'clear'    => true,
        'priority' => 115,
    );

    // ICO field
    $fields['billing']['billing_ico'] = array(
        'label'    => __( 'Company ICO', 'suz' ),
        'required' => false,
        'class'    => array( 'form-row-wide' ),
        'clear'    => true,
        'priority' => 120,
    );

    // VAT ID field
    $fields['billing']['billing_vat_id'] = array(
        'label'    => __( 'VAT ID', 'suz' ),
        'required' => false,
        'class'    => array( 'form-row-wide' ),
        'clear'    => true,
        'priority' => 125,
    );

    // Tax ID field
    $fields['billing']['billing_tax_id'] = array(
        'label'    => __( 'Tax ID', 'suz' ),
        'required' => false,
        'class'    => array( 'form-row-wide' ),
        'clear'    => true,
        'priority' => 130,
    );

    return $fields;
}

/**
 * Prefill fields for logged-in users.
 */
add_filter( 'woocommerce_checkout_get_value', 'suz_prefill_company_fields', 10, 2 );
function suz_prefill_company_fields( $input, $key ) {
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        if ( $key === 'billing_ico' ) {
            return get_user_meta( $user_id, 'company_ico', true );
        }
        if ( $key === 'billing_vat_id' ) {
            return get_user_meta( $user_id, 'company_vat_id', true );
        }
        if ( $key === 'billing_tax_id' ) {
            return get_user_meta( $user_id, 'company_tax_id', true );
        }
    }
    return $input;
}

/**
 * Auto-login existing user by email during checkout.
 */
add_action( 'woocommerce_checkout_process', 'suz_auto_login_existing_user' );
function suz_auto_login_existing_user() {
    if ( is_user_logged_in() ) {
        return;
    }

    if ( isset( $_POST['billing_email'] ) ) {
        $email = sanitize_email( wp_unslash( $_POST['billing_email'] ) );
        $user  = get_user_by( 'email', $email );

        if ( $user ) {
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID, true );
        }
    }
}

/**
 * Process company fields and assign role after checkout.
 */
add_action( 'woocommerce_checkout_order_processed', 'suz_process_company_fields_on_checkout', 10, 3 );
function suz_process_company_fields_on_checkout( $order_id, $posted_data, $order ) {
    $user_id = $order->get_user_id();

    // Fallback if user ID not set
    if ( ! $user_id ) {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
        } elseif ( isset( $_POST['billing_email'] ) ) {
            $email         = sanitize_email( wp_unslash( $_POST['billing_email'] ) );
            $existing_user = get_user_by( 'email', $email );
            if ( $existing_user ) {
                $user_id = $existing_user->ID;
            } else {
                return;
            }
        }
    }

    if ( ! $user_id ) {
        return;
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }

    // ICO field
    $ico = '';
    $old_ico = get_user_meta( $user_id, 'company_ico', true );
    if ( isset( $_POST['billing_ico'] ) && ! empty( $_POST['billing_ico'] ) ) {
        $ico = sanitize_text_field( wp_unslash( $_POST['billing_ico'] ) );
        if($ico != $old_ico){
            update_user_meta( $user_id, 'company_ico', $ico );
        }
    } else {
        $ico = get_user_meta( $user_id, 'company_ico', true );
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

    // Assign role based on ICO
    if ( ! empty( $ico ) && $ico != $old_ico) {
        $is_member = function_exists( 'suz_check_ico_in_member_list' ) ? suz_check_ico_in_member_list( $ico ) : false;
        $new_role  = $is_member ? 'suz_member' : 'suz_non_member';

        // Remove previous SUZ-related roles
        $roles_to_remove = array( 'suz_member', 'suz_non_member', 'suz_representative', 'vip' );
        foreach ( $roles_to_remove as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) {
                $user->remove_role( $role );
            }
        }

        // Assign SUZ role
        if ( ! in_array( $new_role, (array) $user->roles, true ) ) {
            $user->add_role( $new_role );
        }

        // Save role info
        update_user_meta( $user_id, 'suz_assigned_role', $new_role );

        // Optional: Sync with Mailchimp
        if ( function_exists( 'suz_sync_mailchimp_tag' ) ) {
            suz_sync_mailchimp_tag( $user_id, $new_role );
        }
    }

    // Always assign Eventin customer role
    // if ( ! in_array( 'etn-customer', (array) $user->roles, true ) ) {
    //     $user->add_role( 'etn-customer' );
    // }
}

/**
 * Force auth cookie refresh during checkout.
 */
add_action( 'wp_authenticate_spam_check', 'suz_force_auth_cookie_for_checkout', 0, 2 );
function suz_force_auth_cookie_for_checkout( $user, $username ) {
    if ( is_user_logged_in() && $user instanceof WP_User ) {
        wp_set_auth_cookie( $user->ID, true );
    }
    return $user;
}

/**
 * Disable caching on checkout page if caching plugin is active.
 */
add_action( 'send_headers', 'suz_maybe_disable_cache_on_checkout' );
function suz_maybe_disable_cache_on_checkout() {
    if ( ! function_exists( 'is_checkout' ) ) {
        return;
    }

    $is_cache_plugin_active = (
        defined( 'WP_ROCKET_VERSION' ) ||
        defined( 'W3TC' ) ||
        defined( 'LSCACHE_ADV_CACHE' ) ||
        defined( 'SG_CACHEPRESS_VERSION' ) ||
        defined( 'WP_FASTEST_CACHE_VERSION' ) ||
        defined( 'CACHIFY_VERSION' ) ||
        defined( 'HUMMINGBIRD_VERSION' ) ||
        class_exists( 'WpSuperCache' ) ||
        function_exists( 'wp_cache_get' )
    );

    if ( $is_cache_plugin_active ) {
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
    }
}








// Blocks: register fields
add_action('woocommerce_init', function () {
    if (!function_exists('woocommerce_register_additional_checkout_field')) return;
    foreach ([
        'suz/company' => esc_html__( 'Company Name', 'suz-control-panel' ),
        'suz/ico'     => esc_html__( 'Company ICO', 'suz-control-panel' ),
        'suz/vat-id'  => esc_html__( 'VAT ID', 'suz-control-panel' ),
        'suz/tax-id'  => esc_html__( 'Tax ID', 'suz-control-panel' ),
    ] as $id => $label) {
        woocommerce_register_additional_checkout_field([
            'id'=>$id,'label'=> $label,'location'=>'address','type'=>'text','required'=>false,
            'sanitize_callback'=>'sanitize_text_field',
        ]);
    }
});

// Helper: last order
function suz_last_order_for_user($uid){
    if(!$uid) return null;
    if(function_exists('wc_get_customer_last_order')) return wc_get_customer_last_order($uid);
    $o = wc_get_orders(['customer_id'=>$uid,'limit'=>1,'orderby'=>'date','order'=>'DESC']);
    return $o ? $o[0] : null;
}

// Defaults from ORDER meta (_billing_*), then last order, then user meta
foreach ([
    'suz/company' => '_billing_company',
    'suz/ico'     => '_billing_ico',
    'suz/vat-id'  => '_billing_vat_id',
    'suz/tax-id'  => '_billing_tax_id',
] as $field_id => $order_meta_key) {
    add_filter("woocommerce_get_default_value_for_{$field_id}", function($v,$group,$obj) use($order_meta_key){
        if($group!=='billing') return $v;
        if($obj instanceof WC_Order){
            $val = (string)$obj->get_meta($order_meta_key);
            if($val!=='') return $val;
            $uid = $obj->get_user_id();
        } else {
            $uid = $obj instanceof WC_Customer ? $obj->get_id() : 0;
        }
        if($uid){
            $last = suz_last_order_for_user($uid);
            if($last instanceof WC_Order){
                $val = (string)$last->get_meta($order_meta_key);
                if($val!=='') return $val;
            }
            $user_key = ltrim($order_meta_key,'_'); // billing_company, etc.
            $val = (string)get_user_meta($uid,$user_key,true);
            if($val!=='') return $val;
        }
        return $v;
    },10,3);
}

// Save to ORDER _billing_* (optionally mirror to usermeta)
add_action('woocommerce_set_additional_field_value', function($key,$value,$group,$obj){
    if($group!=='billing') return;
    $map = [
        'suz/company' => '_billing_company',
        'suz/ico'     => '_billing_ico',
        'suz/vat-id'  => '_billing_vat_id',
        'suz/tax-id'  => '_billing_tax_id',
    ];
    if(!isset($map[$key])) return;
    $value = sanitize_text_field($value);
    if($obj instanceof WC_Order){
        $obj->update_meta_data($map[$key],$value);
        $obj->save();
        // also keep usermeta in sync (optional; uncomment if you want)
        // if($obj->get_user_id()) update_user_meta($obj->get_user_id(), ltrim($map[$key],'_'), $value);
    }
},10,4);

// Classic checkout/Edit Address fallback
// add_filter('woocommerce_billing_fields', function($f){
//     $f['billing_company'] = ['label'=>__('Company Name','suz'),'required'=>false,'class'=>['form-row-wide'],'priority'=>115];
//     $f['billing_ico']     = ['label'=>__('Company ICO','suz'),'required'=>false,'class'=>['form-row-wide'],'priority'=>120];
//     $f['billing_vat_id']  = ['label'=>__('VAT ID','suz'),'required'=>false,'class'=>['form-row-wide'],'priority'=>125];
//     $f['billing_tax_id']  = ['label'=>__('Tax ID','suz'),'required'=>false,'class'=>['form-row-wide'],'priority'=>130];
//     return $f;
// },10);

add_action('woocommerce_checkout_update_order_meta', function($order_id){
    foreach(['billing_company','billing_ico','billing_vat_id','billing_tax_id'] as $k){
        if(isset($_POST[$k]) && $_POST[$k]!==''){
            update_post_meta($order_id,'_'.$k, sanitize_text_field(wp_unslash($_POST[$k])));
        }
    }
},10);
