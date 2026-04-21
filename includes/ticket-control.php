<?php
/**
 * Ticket Control (Updated)
 * Description: Ticket visibility, discount price mapping and quota protection for Eventin + WooCommerce. Updated to include discount-quota checks on add-to-cart/checkout and order-line meta logging. Keeps existing settings UI intact.
 * Version: 1.1
 * Author: SUZ Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** --------------------------------------------------------------------------
 * Existing settings & sanitization (kept as-is)
 * -------------------------------------------------------------------------- */
add_action( 'admin_init', function () {
    register_setting( 'suz_ticket_control_settings', 'suz_ticket_role_mapping', [
        'sanitize_callback' => 'suz_sanitize_ticket_role_mapping',
    ] );

    register_setting( 'suz_ticket_control_settings', 'suz_event_discount_prices', [
        'sanitize_callback' => 'suz_sanitize_event_discount_prices',
    ] );

    add_settings_section(
        'suz_ticket_control_main',
        '',
        null,
        'suz-ticket-control'
    );
} );

function suz_sanitize_ticket_role_mapping( $input ) {
    if ( ! is_array( $input ) ) {
        return [];
    }

    $sanitized = [];
    foreach ( $input as $role_key => $indexes ) {
        $clean = preg_replace( '/[^0-9,]/', '', $indexes );
        $clean = trim( $clean, ',' );
        $sanitized[ sanitize_key( $role_key ) ] = $clean;
    }
    return $sanitized;
}

function suz_sanitize_event_discount_prices( $input ) {
    if ( ! is_array( $input ) ) {
        return [];
    }

    $sanitized = [];
    foreach ( $input as $event_id => $price ) {
        $event_id = intval( $event_id );
        $price = floatval( $price );
        if ( $price >= 0 ) {
            $sanitized[ $event_id ] = $price;
        }
    }
    return $sanitized;
}

add_action('admin_notices', function() {
    if ( isset($_GET['page'], $_GET['settings-updated']) && $_GET['page'] === 'suz-ticket-control' ) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Ticket Role Mapping settings saved successfully.', 'suz-control-panel'); ?></p>
        </div>
        <?php
    }
});

/**
 * Render SUZ Ticket Control Settings Page
 */
function suz_panel_render_ticket_control_page() {
    ?>
    <div class="wrap">
        <h1 style="text-align:center;"><?php echo esc_html__( 'Ticket Control Settings', 'suz-control-panel' ); ?></h1>
        <p style="text-align:center; max-width: 700px; margin: 0 auto 2em;">
            <?php echo esc_html__( 'Configure multiple ticket indexes for each user role (comma-separated like 1,2,3).', 'suz-control-panel' ); ?>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields( 'suz_ticket_control_settings' ); ?>

            <style>
            .centered-table-container { max-width: 900px; margin: 0 0 3em 0; }
            .ticket-role-box { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 20px; }
            .ticket-role-box h2 { text-align: center; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            th, td { padding: 10px 12px; border-bottom: 1px solid #e1e1e1; text-align: left; word-wrap: break-word; }
            .ticket-role-box input[type="text"] { width: 98%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px; box-sizing: border-box; }
            @media (max-width: 768px) { .centered-table-container { max-width: 100%; padding: 0 10px; } }
            </style>

            <!-- Ticket Index per Role Table -->
            <div class="centered-table-container ticket-role-box">
                <h2><?php echo esc_html__( 'Ticket Index per Role', 'suz-control-panel' ); ?></h2>
                <table cellspacing="0" cellpadding="0">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'User Role', 'suz-control-panel' ); ?></th>
                            <th><?php echo esc_html__( 'Ticket Indexes (comma separated)', 'suz-control-panel' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $value = get_option( 'suz_ticket_role_mapping', [] );
                        $roles = wp_roles()->roles;

                        foreach ( $roles as $role_key => $role ) :
                            $indexes = isset( $value[ $role_key ] ) ? $value[ $role_key ] : '';
                            ?>
                            <tr>
                                <td><?php echo esc_html( $role['name'] ); ?></td>
                                <td>
                                    <input 
                                        type="text"
                                        name="suz_ticket_role_mapping[<?php echo esc_attr( $role_key ); ?>]"
                                        value="<?php echo esc_attr( $indexes ); ?>"
                                        placeholder="<?php echo esc_attr__( 'e.g., 1,2,3', 'suz-control-panel' ); ?>"
                                        aria-label="<?php echo esc_attr( sprintf( __( 'Ticket indexes for %s', 'suz-control-panel' ), $role['name'] ) ); ?>"
                                    />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * --------------------------------------------------------------------------
 * SUZ: Role/booking/ICO/email safe discount enforcement
 * --------------------------------------------------------------------------
 */

/* -------------------------
 * Get ticket indexes for current logged-in user based on roles
 * ------------------------- */
function suz_get_ticket_indexes_for_current_user() {
    if ( ! is_user_logged_in() ) return [];

    $user       = wp_get_current_user();
    $all_roles  = (array) $user->roles;
    $all_mapped = get_option( 'suz_ticket_role_mapping', [] );

    $indexes = [];
    foreach ( $all_roles as $role ) {
        if ( isset( $all_mapped[ $role ] ) ) {
            foreach ( explode( ',', $all_mapped[ $role ] ) as $val ) {
                $num = absint( trim( $val ) );
                if ( $num > 0 ) $indexes[] = $num;
            }
        }
    }

    return array_unique( $indexes );
}

/* -------------------------
 * Resolve WooCommerce product to Eventin booking info
 * ------------------------- */
function suz_resolve_product_to_booking_ticket( $product_id ) {
    $product_id = intval( $product_id );

    $booking_keys = [ 'eventin_order_id', '_eventin_booking_id', 'booking_id' ];
    $ticket_keys  = [ '_etn_ticket_index', 'etn_ticket_index', '_ticket_index', 'ticket_index' ];

    $booking_id = 0;
    foreach ( $booking_keys as $key ) {
        $val = get_post_meta( $product_id, $key, true );
        if ( ! empty( $val ) ) { $booking_id = intval( $val ); break; }
    }

    $index = 0;
    foreach ( $ticket_keys as $key ) {
        $val = get_post_meta( $product_id, $key, true );
        if ( $val !== '' && $val !== false ) { $index = intval( $val ); break; }
    }

    $variations = get_post_meta( $product_id, 'etn_ticket_variations', true );
    if ( is_array( $variations ) && count( $variations ) > 0 && $index === 0 ) {
        $index = 1;
    }

    return [
        'booking_id' => $booking_id,
        'index'      => $index,
    ];
}

/* -------------------------
 * Check if a product is a discounted ticket
 * ------------------------- */
function suz_is_discount_ticket_by_product( $product_id ) {
    $map = suz_resolve_product_to_booking_ticket( $product_id );
    return ! empty( $map['booking_id'] ) && intval( $map['index'] ) === 1;
}

/* -------------------------
 * Count discounted tickets for booking + company ICO + optional billing email
 * ------------------------- */
function suz_get_discount_count_for_booking_ico_email( $booking_id, $ico, $billing_email = '' ) {
    global $wpdb;

    $booking_id    = intval( $booking_id );
    $ico           = sanitize_text_field( $ico );
    $billing_email = sanitize_email( $billing_email );

    if ( $booking_id <= 0 || empty( $ico ) ) return 0;

    $meta_table  = $wpdb->prefix . 'woocommerce_order_itemmeta';
    $items_table = $wpdb->prefix . 'woocommerce_order_items';
    $posts_table = $wpdb->posts;

    if ( empty( $billing_email ) ) {
        // Count orders by company ICO only
        $sql = "
            SELECT COUNT(DISTINCT im1.order_item_id)
            FROM {$meta_table} im1
            JOIN {$items_table} oi ON oi.order_item_id = im1.order_item_id
            JOIN {$posts_table} p ON p.ID = oi.order_id 
                AND p.post_type = 'shop_order' 
                AND p.post_status IN ('wc-completed','wc-processing')
            JOIN {$meta_table} im2 ON im2.order_item_id = oi.order_item_id 
                AND im2.meta_key = '_suz_company_ico' 
                AND im2.meta_value = %s
            JOIN {$meta_table} im3 ON im3.order_item_id = oi.order_item_id 
                AND im3.meta_key = '_suz_discount_ticket' 
                AND im3.meta_value = 'yes'
            WHERE im1.meta_key = 'eventin_order_id' AND im1.meta_value = %d
        ";
        $count = intval( $wpdb->get_var( $wpdb->prepare( $sql, $ico, $booking_id ) ) );
    } else {
        // Count orders by company ICO + billing email
        $sql = "
            SELECT COUNT(DISTINCT im1.order_item_id)
            FROM {$meta_table} im1
            JOIN {$items_table} oi ON oi.order_item_id = im1.order_item_id
            JOIN {$posts_table} p ON p.ID = oi.order_id 
                AND p.post_type = 'shop_order' 
                AND p.post_status IN ('wc-completed','wc-processing')
            JOIN {$meta_table} im2 ON im2.order_item_id = oi.order_item_id 
                AND im2.meta_key = '_suz_company_ico' 
                AND im2.meta_value = %s
            JOIN {$meta_table} im3 ON im3.order_item_id = oi.order_item_id 
                AND im3.meta_key = '_suz_discount_ticket' 
                AND im3.meta_value = 'yes'
            JOIN {$meta_table} im4 ON im4.order_item_id = oi.order_item_id 
                AND im4.meta_key = '_billing_email' 
                AND im4.meta_value = %s
            WHERE im1.meta_key = 'eventin_order_id' AND im1.meta_value = %d
        ";
        $count = intval( $wpdb->get_var( $wpdb->prepare( $sql, $ico, $billing_email, $booking_id ) ) );
    }

    return $count;
}

/* -------------------------
 * Check if a company has used discount quota
 * ------------------------- */
function suz_has_company_used_discount( $ico, $booking_id, $email = '', $limit = 1 ) {
    $count = suz_get_discount_count_for_booking_ico_email( $booking_id, $ico, $email );
    return $count >= intval( $limit );
}

/* -------------------------
 * Add front-end visibility classes for tickets
 * ------------------------- */
add_action( 'wp_footer', 'suz_add_ticket_visibility_classes' );
function suz_add_ticket_visibility_classes() {
    $role_based_indexes = suz_get_ticket_indexes_for_current_user();
    $indexes = [];
    global $post;

    if ( ! is_user_logged_in() || empty( $role_based_indexes ) ) {
        $indexes = [];
    } else {
        $user_id = get_current_user_id();
        $ico = get_user_meta( $user_id, 'company_ico', true );
        $is_member = ! empty( $ico ) && function_exists('suz_check_ico_in_member_list') && suz_check_ico_in_member_list( $ico );
        $quota = 2;

        $allowed = array_map( 'intval', $role_based_indexes );
        // if ( suz_user_has_order_for_event( $user_id, wp_get_current_user()->user_email, $post->ID ) ) {
        //     echo '<style>.etn-ticket-container{display:none!important;}</style>';
        //     return;
        // }

        // $already_purchase_message = __('')

        if ( suz_user_has_order_for_event( $user_id, wp_get_current_user()->user_email, @$post->ID ) ) {
            $message = __( 'You have already purchased a ticket for this event.', 'suz-control-panel' );
            echo '<style>
                /* hide all tickets while we swap in the message */
                .etn-ticket-container { display: none !important; }
                .suz-ticket-blocked-msg {
                    padding:16px; border:1px solid #ffcccc; background:#fff3f3;
                    color:#b10000; border-radius:8px; text-align:center; font-weight:600; margin:12px 0;
                    font-size:14px;
                }
            </style>';

            echo '<script>
            (function(){
                var translatedMsg = ' . json_encode( $message ) . ';
                function getCommonWrapper(items){
                    if (!items.length) return null;
                    var expected = items.length;
                    var node = items[0];
                    while (node && node.parentElement) {
                        node = node.parentElement;
                        if (node.querySelectorAll(".ant-card-body").length === expected) return node;
                    }
                    return null;
                }

                function inject(){
                    var items = document.querySelectorAll(".ant-card-body");
                    if (!items.length) {
                        // fallback selector used by Eventin tickets
                        items = document.querySelectorAll(".etn-ticket-container");
                    }
                    if (!items.length) return false;

                    var wrapper = getCommonWrapper(Array.from(items));
                    if (!wrapper) wrapper = items[0].parentElement;
                    if (!wrapper) return false;

                    wrapper.innerHTML = \'<div class="suz-ticket-blocked-msg">\'+ translatedMsg +\'</div>\';
                    return true;
                }

                // Try immediately, then observe for dynamic render
                if (!inject()) {
                    var tries = 0;
                    var iv = setInterval(function(){
                        tries++;
                        if (inject() || tries > 50) clearInterval(iv); // ~5s cap
                    }, 100);

                    var obs = new MutationObserver(function(){
                        if (inject()) { obs.disconnect(); clearInterval(iv); }
                    });
                    obs.observe(document.documentElement, { childList: true, subtree: true });
                }
            })();
            </script>';

            return;
        }

        if ( $is_member && isset( $post->ID ) ) {
            $user_email = wp_get_current_user()->user_email;
            // if ( suz_has_company_used_discount( $ico, intval( $post->ID ), $user_email, $quota ) ) {
            //     $allowed = array_diff( $allowed, [1] );
            // }else{
            //     $allowed = array_diff( $allowed, [2] );
            // }
            $ticket_count_under_same_ico = suz_count_orders_by_ico_for_event_strict($ico, $post->ID);
            if($ticket_count_under_same_ico < 2){
                $allowed = array_diff( $allowed, [2] );
            }else{
                $allowed = array_diff( $allowed, [1] );
            }
            // var_dump(suz_count_orders_by_ico_for_event_strict($ico, $post->ID), $post->ID);
        } elseif ( ! $is_member ) {
            $allowed = array_diff( $allowed, [1] );
        }

        $indexes = array_unique( $allowed );
    }

    ?>
    <style>
        .etn-ticket-container { display: none !important; }
        <?php foreach ( $indexes as $index ): ?>
        .ticket-visible-<?php echo intval( $index ); ?> .etn-ticket-container:nth-of-type(<?php echo intval( $index ); ?>) { display: block !important; }
        <?php endforeach; ?>
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php foreach ( $indexes as $index ): ?>
            document.body.classList.add('ticket-visible-<?php echo intval( $index ); ?>');
            <?php endforeach; ?>
        });
    </script>
    <?php
}

function suz_count_orders_by_ico_for_event_strict( $ico, $event_id ) {
    global $wpdb;

    $ico      = trim( (string) $ico );
    $event_id = (string) ( is_numeric( $event_id ) ? (int) $event_id : $event_id );
    if ( $ico === '' || $event_id === '0' ) return 0;

    $items    = $wpdb->prefix . 'woocommerce_order_items';
    $itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

    // Common event keys seen in Eventin/Woo setups (line-item level)
    $event_keys = [
        '_suz_event_id',
        '_etn_event_id',
        'etn_event_id',
        '_eventin_event_id',
        'event_id',
        '_event_id',
    ];

    // Possible ICO keys stored on line item
    $ico_keys = [
        '_suz_company_ico',
        'company_ico',
        '_billing_company',
    ];

    // (EXISTS ... OR ...) for event keys
    $event_exists_parts = [];
    $params = [];
    foreach ( $event_keys as $ek ) {
        $event_exists_parts[] = "
            EXISTS (
                SELECT 1 FROM {$itemmeta} im_e
                WHERE im_e.order_item_id = oi.order_item_id
                  AND im_e.meta_key = %s
                  AND im_e.meta_value = %s
            )";
        $params[] = $ek;
        $params[] = $event_id;
    }
    $event_exists_sql = '(' . implode( ' OR ', $event_exists_parts ) . ')';

    // (EXISTS ... OR ...) for ICO keys
    $ico_exists_parts = [];
    foreach ( $ico_keys as $ik ) {
        $ico_exists_parts[] = "
            EXISTS (
                SELECT 1 FROM {$itemmeta} im_i
                WHERE im_i.order_item_id = oi.order_item_id
                  AND im_i.meta_key = %s
                  AND im_i.meta_value = %s
            )";
        $params[] = $ik;
        $params[] = $ico;
    }
    $ico_exists_sql = '(' . implode( ' OR ', $ico_exists_parts ) . ')';

    // Status whitelist
    $allowed_legacy = [ 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-completed' ];
    $allowed_hpos   = [ // allow both prefixed & unprefixed
        'pending','on-hold','processing','completed',
        'wc-pending','wc-on-hold','wc-processing','wc-completed',
    ];

    // Detect HPOS
    $wc_orders     = $wpdb->prefix . 'wc_orders';
    $has_wc_orders = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wc_orders ) );

    if ( $has_wc_orders ) {
        $placeholders = implode( ',', array_fill( 0, count( $allowed_hpos ), '%s' ) );
        $sql = "
            SELECT COUNT(DISTINCT oi.order_id)
            FROM {$items} oi
            INNER JOIN {$wc_orders} o ON o.id = oi.order_id
            WHERE oi.order_item_type = 'line_item'
              AND {$event_exists_sql}
              AND {$ico_exists_sql}
              AND o.status IN ( {$placeholders} )
        ";
        $params = array_merge( $params, $allowed_hpos );
    } else {
        $posts       = $wpdb->posts;
        $placeholders = implode( ',', array_fill( 0, count( $allowed_legacy ), '%s' ) );
        $sql = "
            SELECT COUNT(DISTINCT oi.order_id)
            FROM {$items} oi
            INNER JOIN {$posts} p
                    ON p.ID = oi.order_id
                   AND p.post_type = 'shop_order'
            WHERE oi.order_item_type = 'line_item'
              AND {$event_exists_sql}
              AND {$ico_exists_sql}
              AND p.post_status IN ( {$placeholders} )
        ";
        $params = array_merge( $params, $allowed_legacy );
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
}

function suz_user_has_order_for_event( $user_id, $user_email, $event_id ) {
    global $wpdb;

    $user_id    = absint( $user_id );
    $user_email = sanitize_email( (string) $user_email );
    $event_id   = (string) ( is_numeric( $event_id ) ? (int) $event_id : $event_id );
    if ( ! $user_id || $user_email === '' || $event_id === '0' ) {
        return false;
    }

    $items    = $wpdb->prefix . 'woocommerce_order_items';
    $itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

    // Event meta keys commonly found on line items
    $event_keys = [ '_suz_event_id', '_etn_event_id', 'etn_event_id', '_eventin_event_id', 'event_id' ];

    // Build (EXISTS(...) OR ...) for event matching on the SAME line item
    $event_exists_parts = [];
    $params = [];
    foreach ( $event_keys as $ek ) {
        $event_exists_parts[] = "
            EXISTS (
                SELECT 1 FROM {$itemmeta} im_e
                WHERE im_e.order_item_id = oi.order_item_id
                  AND im_e.meta_key = %s
                  AND im_e.meta_value = %s
            )";
        $params[] = $ek;
        $params[] = $event_id; // meta_value stored as varchar
    }
    $event_exists_sql = '(' . implode(' OR ', $event_exists_parts) . ')';

    // Detect HPOS orders table
    $wc_orders     = $wpdb->prefix . 'wc_orders';
    $has_wc_orders = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wc_orders ) );

    if ( $has_wc_orders ) {
        // HPOS: status can be 'pending' or 'wc-pending' depending on version/settings.
        // Whitelist both forms.
        $allowed_statuses = [
            'pending','on-hold','processing','completed',
            'wc-pending','wc-on-hold','wc-processing','wc-completed',
        ];
        $placeholders = implode( ',', array_fill( 0, count( $allowed_statuses ), '%s' ) );

        $sql = "
            SELECT 1
            FROM {$items} oi
            INNER JOIN {$wc_orders} o ON o.id = oi.order_id
            WHERE oi.order_item_type = 'line_item'
              AND {$event_exists_sql}
              AND ( o.customer_id = %d OR o.billing_email = %s )
              AND o.status IN ( {$placeholders} )
            LIMIT 1
        ";
        $params[] = $user_id;
        $params[] = $user_email;
        $params   = array_merge( $params, $allowed_statuses );

    } else {
        // Legacy: posts table uses wc-* post_status
        $posts    = $wpdb->posts;
        $postmeta = $wpdb->postmeta;

        $allowed_statuses = [ 'wc-pending','wc-on-hold','wc-processing','wc-completed' ];
        $placeholders = implode( ',', array_fill( 0, count( $allowed_statuses ), '%s' ) );

        $sql = "
            SELECT 1
            FROM {$items} oi
            INNER JOIN {$posts} p
                    ON p.ID = oi.order_id
                   AND p.post_type = 'shop_order'
            LEFT JOIN {$postmeta} pm_user
                   ON pm_user.post_id = p.ID
                  AND pm_user.meta_key = '_customer_user'
            LEFT JOIN {$postmeta} pm_email
                   ON pm_email.post_id = p.ID
                  AND pm_email.meta_key = '_billing_email'
            WHERE oi.order_item_type = 'line_item'
              AND {$event_exists_sql}
              AND ( pm_user.meta_value = %d OR pm_email.meta_value = %s )
              AND p.post_status IN ( {$placeholders} )
            LIMIT 1
        ";
        $params[] = $user_id;
        $params[] = $user_email;
        $params   = array_merge( $params, $allowed_statuses );
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $found = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
    return ! empty( $found );
}

add_action('wp_footer', function () {
    if ( ! is_user_logged_in() ) return;

    $u = wp_get_current_user();
    $dat = [
        'first'   => $u->first_name,
        'last'    => $u->last_name,
        'email'   => $u->user_email,
        // change this if you store company name elsewhere (e.g. 'company_name')
        'company' => get_user_meta($u->ID, 'company_ico', true),
        'phone'   => get_user_meta($u->ID, 'billing_phone', true),
    ];
    foreach ($dat as $k => $v) { $dat[$k] = esc_js((string)$v); }

    echo '<script>
    (function(){
        var data = {
            first:  "'.$dat['first'].'",
            last:   "'.$dat['last'].'",
            email:  "'.$dat['email'].'",
            company:"'.$dat['company'].'",
            phone:  "'.$dat['phone'].'"
        };

        // Use the native value setter so React/Ant controlled inputs accept the change
        var setNative = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value").set;

        function fillIfEmptyById(id, val){
            if (!val) return false;
            var el = document.getElementById(id);
            if (!el) return false;
            var cur = (el.value || "").trim();
            if (cur) return true; // already filled
            try {
                setNative.call(el, val);
                el.dispatchEvent(new Event("input", { bubbles: true }));
                el.dispatchEvent(new Event("change", { bubbles: true }));
                return true;
            } catch(e) { return false; }
        }

        function tryFill(){
            // Adjust these IDs to exactly what your form uses
            var ok1 = fillIfEmptyById("customer_fname",   data.first);
            var ok2 = fillIfEmptyById("customer_lname",   data.last);
            var ok3 = fillIfEmptyById("customer_email",   data.email);
            var ok4 = fillIfEmptyById("customer_company", data.company);
            var ok5 = fillIfEmptyById("customer_phone",   data.phone);

            // If at least one field existed, we consider it a hit
            return (ok1 || ok2 || ok3 || ok4 || ok5);
        }

        // Try immediately, then keep trying for up to ~6s (Eventin renders late sometimes)
        if (!tryFill()) {
            var attempts = 0;
            var iv = setInterval(function(){
                attempts++;
                if (tryFill() || attempts > 60) clearInterval(iv);
            }, 100);

            // Also watch DOM in case it renders via AJAX
            var obs = new MutationObserver(function(){
                if (tryFill()) { obs.disconnect(); }
            });
            obs.observe(document.documentElement, { childList: true, subtree: true });
        }
    })();
    </script>';

    // modified by hafij start
    $first = get_user_meta( $u->ID, 'first_name', true );
    $last  = get_user_meta( $u->ID, 'last_name', true );
    $full_name  = trim( $first . ' ' . $last );
    // modified by hafij end

    echo '<script>
        (function(){
        // modified by hafij start
        var fullName = "'.esc_js( $full_name ).'";
        // modified by hafij end

        // Ant/React controlled input friendly setter
        var setNative = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value").set;

        function fillDynamicNameFields() {
            if (!fullName) return false;

            // Match ANY attendee name input with a dynamic id:
            var nodes = document.querySelectorAll("input[id*=\"dynamic_id\"][id$=\"_name\"]");

            var filled = false;
            nodes.forEach(function(el){
            var cur = (el.value || "").trim();
            if (!cur) {
                try {
                setNative.call(el, fullName);
                el.dispatchEvent(new Event("input", {bubbles:true}));
                el.dispatchEvent(new Event("change", {bubbles:true}));
                filled = true;
                } catch(e){}
            }
            });
            return filled || nodes.length > 0;
        }

        if (!fillDynamicNameFields()) {
            var tries = 0;
            var iv = setInterval(function(){
            tries++;
            if (fillDynamicNameFields() || tries > 60) clearInterval(iv);
            }, 100);

            var obs = new MutationObserver(function(){
            if (fillDynamicNameFields()) { obs.disconnect(); }
            });
            obs.observe(document.documentElement, {childList:true, subtree:true});
        }
        })();
        </script>';
});

/* -------------------------
 * Add line item meta for tickets (with email & role)
 * ------------------------- */
add_action( 'woocommerce_checkout_create_order_line_item', 'suz_add_line_item_meta_for_quota_booking', 10, 4 );
function suz_add_line_item_meta_for_quota_booking( $item, $cart_item_key, $values, $order ) {
    $product = $values['data'];
    $map = suz_resolve_product_to_booking_ticket($product->get_id());

    $user_id = get_current_user_id();
    $user_email = '';
    $user_role  = '';

    if ( $user_id ) {
        $user       = get_userdata( $user_id );
        $user_email = $user->user_email;
        $user_role  = ! empty( $user->roles ) ? $user->roles[0] : '';
    } else {
        // guest checkout
        $user_email = $order->get_billing_email();
    }

    $ico = get_user_meta($user_id, 'company_ico', true);
    if ( empty($ico) ) {
        $ico = $order->get_meta('_company_ico') ?: $order->get_billing_company();
    }

    // Add ticket info
    if ($map['booking_id'] > 0) $item->add_meta_data('eventin_order_id', $map['booking_id'], true);
    if ($map['index'] > 0) $item->add_meta_data('_suz_ticket_index', $map['index'], true);

    // Add company & user info
    if (!empty($ico)) $item->add_meta_data('_suz_company_ico', sanitize_text_field($ico), true);
    if (!empty($user_email)) $item->add_meta_data('_suz_user_email', sanitize_email($user_email), true);
    if (!empty($user_role))  $item->add_meta_data('_suz_user_role', sanitize_text_field($user_role), true);

    // Discount flag
    if ( suz_is_discount_ticket_by_product($product->get_id()) ) {
        $item->add_meta_data('_suz_discount_ticket', 'yes', true);
    }
}
/* -------------------------
 * Validate discount quota on checkout (ICO + Email + Role)
 * ------------------------- */
add_action( 'woocommerce_checkout_process', 'suz_validate_discount_quota_on_checkout' );
function suz_validate_discount_quota_on_checkout() {
    $user_email = '';
    $ico = '';
    $user_role = '';

    $user = wp_get_current_user();
    if ( $user && $user->ID ) {
        $user_email = $user->user_email;
        $ico = get_user_meta( $user->ID, 'company_ico', true );
        $user_role = ! empty( $user->roles ) ? $user->roles[0] : '';
    }

    if ( isset($_POST['billing_email']) ) $user_email = sanitize_email( wp_unslash($_POST['billing_email']) );
    if ( isset($_POST['billing_company']) ) $ico = sanitize_text_field( wp_unslash($_POST['billing_company']) );

    $quota = 2;

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];
        $map = suz_resolve_product_to_booking_ticket($product->get_id());

        if ( $map['index'] === 1 && suz_has_company_used_discount( $ico, $map['booking_id'], $user_email, $quota, $user_role ) ) {
            wc_add_notice( sprintf( __( 'Your company ICO %s or your role/email has already used the discounted ticket quota.', 'suz' ), esc_html($ico) ), 'error' );
        }
    }
}