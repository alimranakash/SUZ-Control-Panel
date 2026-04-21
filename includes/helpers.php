<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ico verified by hafij start
 */

// function suz_check_ico_in_member_list( $ico ) {
//     global $wpdb;
//     $ico         = sanitize_text_field( $ico );
//     $table_name  = $wpdb->prefix . 'suz_member_companies';

//     $exists = $wpdb->get_var( $wpdb->prepare(
//         "SELECT COUNT(*) FROM $table_name WHERE ico = %s",
//         $ico
//     ) );

//     return $exists > 0;
// }

function suz_check_ico_in_member_list( $ico ) {
    global $wpdb;

    $ico = trim( sanitize_text_field( $ico ) );
    if ( $ico === '' ) return false;

    $usermeta_table = $wpdb->usermeta;                 // e.g. wp_usermeta
    $cap_key        = $wpdb->prefix . 'capabilities';  // e.g. wp_capabilities

    // Count users who have this IČO AND have either suz_member or suz_representative in their caps
    $sql = "
        SELECT COUNT(DISTINCT um_ico.user_id)
        FROM {$usermeta_table} AS um_ico
        INNER JOIN {$usermeta_table} AS um_caps
            ON um_caps.user_id = um_ico.user_id
           AND um_caps.meta_key = %s
        WHERE um_ico.meta_key = %s
          AND um_ico.meta_value = %s
          AND (um_caps.meta_value LIKE %s OR um_caps.meta_value LIKE %s)
    ";

    $count = (int) $wpdb->get_var( $wpdb->prepare(
        $sql,
        $cap_key,                  // %s for meta_key (caps key)
        'company_ico',             // %s for meta_key (our ico key)
        $ico,                      // %s for meta_value (the ico)
        '%"suz_member"%',          // LIKE pattern in serialized caps
        '%"suz_representative"%'     // LIKE pattern in serialized caps
    ) );
    // dd($count, $ico);
    return $count > 0;
}

/**
 * ico verified by hafij end
 */