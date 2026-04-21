<?php
/**
 * Autofill WooCommerce checkout fields from Eventin form using localStorage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function ewa_output_autofill_script() {
	?>
	<script>
	// Save Eventin form data to localStorage
	document.addEventListener('input', function (e) {
		if (e.target && e.target.id === 'customer_fname') {
			localStorage.setItem('eventin_fname', e.target.value);
		}
		if (e.target && e.target.id === 'customer_lname') {
			localStorage.setItem('eventin_lname', e.target.value);
		}
		if (e.target && e.target.id === 'customer_email') {
			localStorage.setItem('eventin_email', e.target.value);
		}
	});

	// Autofill WooCommerce checkout fields from localStorage
	document.addEventListener('DOMContentLoaded', function () {
		setTimeout(function () {
			const fname = localStorage.getItem('eventin_fname');
			const lname = localStorage.getItem('eventin_lname');
			const email = localStorage.getItem('eventin_email');

			if (fname) {
				const fnameInput = document.querySelector('#billing_first_name');
				if (fnameInput) fnameInput.value = fname;
			}

			if (lname) {
				const lnameInput = document.querySelector('#billing_last_name');
				if (lnameInput) lnameInput.value = lname;
			}

			if (email) {
				const emailInput = document.querySelector('#billing_email');
				if (emailInput) emailInput.value = email;
			}
		}, 1000);
	});

	// Clear localStorage after checkout is submitted
	document.addEventListener('click', function (e) {
		const placeOrderBtn = e.target.closest('#place_order');
		if (placeOrderBtn) {
			localStorage.removeItem('eventin_fname');
			localStorage.removeItem('eventin_lname');
			localStorage.removeItem('eventin_email');
		}
	});
	</script>
	<?php
}
// add_action( 'wp_footer', 'ewa_output_autofill_script', 100 );


/** Get ONE latest wpforms_entries.fields row for this user and map to WC billing_* */
function suz_wpforms_billing_from_single_entry( int $user_id ): array {
    global $wpdb;
    if ( $user_id <= 0 ) return [];

    $table = $wpdb->prefix . 'wpforms_entries';
    $row   = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT fields FROM {$table}
             WHERE user_id = %d
             ORDER BY date_modified DESC, entry_id DESC
             LIMIT 1",
            $user_id
        ),
        ARRAY_A
    );
    if ( ! $row || empty( $row['fields'] ) ) return [];

    $f = json_decode( $row['fields'], true );
    if ( ! is_array( $f ) ) return [];


    $out = [
        'billing_first_name' => '',
        'billing_last_name'  => '',
        'billing_email'      => '',
        'billing_company'    => '',
        'billing_phone'      => '',
        'billing_address_1'  => '',
        'billing_address_2'  => '',
        'billing_city'       => '',
        'billing_postcode'   => '',
        'billing_country'    => '',
        'billing_state'      => '',
        'billing_ico'      => '',
        'billing_vat_id'      => '',
        'billing_tax_id'      => '',
    ];

    // id 2: Name
    if ( isset( $f['2'] ) && is_array( $f['2'] ) ) {
        if ( ! empty( $f['2']['first'] ) ) {
            $first = trim( (string) $f['2']['first'] );
            $parts = preg_split( '/\s+/', $first );
            $out['billing_first_name'] = $parts[0] ?? $first; // first word only
        }
        if ( ! empty( $f['2']['last'] ) ) {
            $out['billing_last_name'] = (string) $f['2']['last'];
        }
    }

    // id 3: Company
    if ( isset( $f['3']['value'] ) && $f['3']['value'] !== '' ) {
        $out['billing_company'] = (string) $f['3']['value'];
    }

    // id 5: Address
    if ( isset( $f['5'] ) && is_array( $f['5'] ) ) {
        $addr = $f['5'];
        $out['billing_address_1'] = (string) ( $addr['address1'] ?? '' );
        $out['billing_address_2'] = (string) ( $addr['address2'] ?? '' );
        $out['billing_city']      = (string) ( $addr['city']     ?? '' );
        $out['billing_state']     = (string) ( $addr['state']    ?? '' );
        $out['billing_postcode']  = (string) ( $addr['postal']   ?? '' );
        $out['billing_country']   = (string) ( $addr['country']  ?? '' );

        // If city empty but multiline value has lines, try to rescue city/country
        if ( empty($out['billing_city']) && ! empty($addr['value']) ) {
            $lines = preg_split("/\r\n|\n|\r/", trim((string)$addr['value']));
            if (!empty($lines[1]) && empty($out['billing_city']))    $out['billing_city']    = trim($lines[1]);
            if (!empty($lines[2]) && empty($out['billing_country'])) $out['billing_country'] = trim($lines[2]);
        }
        // If postcode is a word (e.g., "Sala") and city still empty, treat it as city
        if ($out['billing_city'] === '' && $out['billing_postcode'] !== '' && !preg_match('/\d/', $out['billing_postcode'])) {
            $out['billing_city']     = $out['billing_postcode'];
            $out['billing_postcode'] = '';
        }
    }

    // id 8: Email
    if ( isset( $f['8']['value'] ) && $f['8']['value'] !== '' ) {
        $out['billing_email'] = (string) $f['8']['value'];
    }
    // id 6: ico
    if ( isset( $f['6']['value'] ) && $f['6']['value'] !== '' ) {
        $out['billing_ico'] = (string) $f['6']['value'];
    }
    // id 12: vat id
    if ( isset( $f['12']['value'] ) && $f['12']['value'] !== '' ) {
        $out['billing_vat_id'] = (string) $f['12']['value'];
    }
    // id 10: tax id
    if ( isset( $f['10']['value'] ) && $f['10']['value'] !== '' ) {
        $out['billing_tax_id'] = (string) $f['10']['value'];
    }

    // id 9: Phone
    if ( isset( $f['9']['value'] ) && $f['9']['value'] !== '' ) {
        $out['billing_phone'] = (string) $f['9']['value'];
    }
	// var_dump(array_filter( $out, static fn($v) => $v !== '' ));
	// var_dump(suz_check_ico_in_member_list('123456789'));
    return array_filter( $out, static fn($v) => $v !== '' );
}

/** Server-side defaults on checkout: Woo meta first → WPForms single-entry fallback (per field). */
add_filter( 'woocommerce_checkout_get_value', function( $value, $input ) {
    if ( ! is_user_logged_in() ) return $value;

    $uid = get_current_user_id();

    // Woo meta (priority 1)
    $wc = [
        'billing_first_name' => (string) get_user_meta( $uid, 'billing_first_name', true ),
        'billing_last_name'  => (string) get_user_meta( $uid, 'billing_last_name', true ),
        'billing_email'      => (string) get_userdata( $uid )->user_email,
        'billing_company'    => (string) get_user_meta( $uid, 'billing_company', true ),
        'billing_phone'      => (string) get_user_meta( $uid, 'billing_phone', true ),
        'billing_address_1'  => (string) get_user_meta( $uid, 'billing_address_1', true ),
        'billing_address_2'  => (string) get_user_meta( $uid, 'billing_address_2', true ),
        'billing_city'       => (string) get_user_meta( $uid, 'billing_city', true ),
        'billing_postcode'   => (string) get_user_meta( $uid, 'billing_postcode', true ),
        'billing_country'    => (string) get_user_meta( $uid, 'billing_country', true ),
        'billing_state'      => (string) get_user_meta( $uid, 'billing_state', true ),
        'billing_ico'      => (string) get_user_meta( $uid, 'billing_ico', true ),
        'billing_vat_id'      => (string) get_user_meta( $uid, 'billing_vat_id', true ),
        'billing_tax_id'      => (string) get_user_meta( $uid, 'billing_tax_id', true ),
    ];

    // WPForms fallback (priority 2) – one row only
    static $wpforms = null;
    if ( $wpforms === null ) {
        $wpforms = suz_wpforms_billing_from_single_entry( $uid );

        // Final safety for names if both sources empty
        if ( empty($wc['billing_first_name']) && empty($wpforms['billing_first_name']) ) {
            $fr = (string) get_user_meta($uid, 'first_name', true);
            if ($fr !== '') {
                $parts = preg_split('/\s+/', trim($fr));
                $wpforms['billing_first_name'] = $parts[0] ?? $fr;
            }
        }
        if ( empty($wc['billing_last_name']) && empty($wpforms['billing_last_name']) ) {
            $wpforms['billing_last_name'] = (string) get_user_meta($uid, 'last_name', true);
        }
    }

    if ( array_key_exists( $input, $wc ) ) {
        if ( $wc[$input] !== '' )              return $wc[$input];      // Woo wins
        if ( ! empty($wpforms[$input]) )       return $wpforms[$input]; // else WPForms
    }

    return $value;
}, 10, 2 );

/** Tiny delayed JS top-up (fills only empty visible inputs, helpful for block checkout). */
add_action( 'wp_footer', function () {
    if ( ! is_user_logged_in() ) return;

    $uid    = get_current_user_id();
    $wc     = [
        'billing_first_name' => (string) get_user_meta( $uid, 'billing_first_name', true ),
        'billing_last_name'  => (string) get_user_meta( $uid, 'billing_last_name', true ),
        'billing_email'      => (string) get_userdata( $uid )->user_email,
        'billing_company'    => (string) get_user_meta( $uid, 'billing_company', true ),
        'billing_phone'      => (string) get_user_meta( $uid, 'billing_phone', true ),
        'billing_address_1'  => (string) get_user_meta( $uid, 'billing_address_1', true ),
        'billing_address_2'  => (string) get_user_meta( $uid, 'billing_address_2', true ),
        'billing_city'       => (string) get_user_meta( $uid, 'billing_city', true ),
        'billing_postcode'   => (string) get_user_meta( $uid, 'billing_postcode', true ),
        'billing_country'    => (string) get_user_meta( $uid, 'billing_country', true ),
        'billing_state'      => (string) get_user_meta( $uid, 'billing_state', true ),
        'billing_ico'      => (string) get_user_meta( $uid, 'billing_ico', true ),
        'billing_vat_id'      => (string) get_user_meta( $uid, 'billing_vat_id', true ),
        'billing_tax_id'      => (string) get_user_meta( $uid, 'billing_tax_id', true ),
    ];
    $wp     = suz_wpforms_billing_from_single_entry( $uid );
    $final  = $wc;
    foreach ( $wp as $k => $v ) { if ( $final[$k] === '' ) $final[$k] = $v; }
    foreach ( $final as $k => $v ) { $final[$k] = esc_js( (string) $v ); }
    ?>
    <script>
    (function(){
      var vals = <?php echo json_encode($final, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
      var setInput  = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value').set;
      var setSelect = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype,'value').set;

      function setIfEmpty(id, v){
        if (!v) return false;
        var el = document.getElementById(id);
        if (!el) return false;
        if (el.value && el.value.trim() !== '') return false;
        try {
          if (el.tagName === 'SELECT' && setSelect) setSelect.call(el, v);
          else if (setInput) setInput.call(el, v);
          else el.value = v;
          el.dispatchEvent(new Event('input',{bubbles:true}));
          el.dispatchEvent(new Event('change',{bubbles:true}));
          return true;
        } catch(e){ return false; }
      }
      function run(){ Object.keys(vals).forEach(function(k){ setIfEmpty(k, vals[k]); }); }

      document.addEventListener('DOMContentLoaded', function(){
        setTimeout(function(){ run(); }, 600); // small delay; try once
      });
    })();
    </script>
    <?php
}, 100 );

// added by hafij start
add_action('wp_footer', function () { ?>
	<script>
	document.addEventListener("DOMContentLoaded", function() {
	const suggestion = document.getElementById("wpforms-1985-field_1_suggestion");
	if(suggestion) suggestion.style.display = "none";
	
	// Keep hiding it if WPForms tries to regenerate
	const observer = new MutationObserver(() => {
		const el = document.getElementById("wpforms-1985-field_1_suggestion");
		if(el) el.style.display = "none";
	});
	observer.observe(document.body, { childList: true, subtree: true });
	});
	</script>
<?php });

// Show custom billing meta in Admin → Order → Billing
// add_filter('woocommerce_admin_billing_fields', function ($fields) {
//     $fields['ico'] = [
//         'label' => __('IČO', 'suz-control-panel'),
//         'show'  => true,         // show in the billing box
// 		'wrapper_class' => 'form-field-wide',  
//     ];
//     $fields['tax_id'] = [
//         'label' => __('Tax ID', 'suz-control-panel'),
//         'show'  => true,
// 		'wrapper_class' => 'form-field-wide',  
//     ];
//     $fields['vat_id'] = [
//         'label' => __('VAT ID', 'suz-control-panel'),
//         'show'  => true,
// 		'wrapper_class' => 'form-field-wide',  
//     ];
//     $fields['company'] = [
//         'label' => __('Company Name', 'suz-control-panel'),
//         'show'  => true,
// 		'wrapper_class' => 'form-field-wide',  
//     ];
//     return $fields;
// });

// added by hafij end


// added by hafij 2025-08-21


add_filter('wc_order_statuses', function($s){
    if(isset($s['wc-on-hold'])) $s['wc-on-hold'] = 'Waiting on payment';
    return $s;
});
add_action('init', function(){
    global $wp_post_statuses;
    if(isset($wp_post_statuses['wc-on-hold'])){
        $wp_post_statuses['wc-on-hold']->label = 'Waiting on payment';
        $wp_post_statuses['wc-on-hold']->label_count = _n_noop('Waiting on payment (%s)','Waiting on payment (%s)');
    }
});

add_filter('woocommerce_bacs_process_payment_order_status', fn($s,$o)=>'on-hold',10,2);

add_filter('woocommerce_mail_callback', function($callback,$email){
    if(is_object($email)){
        $id = property_exists($email,'id') ? $email->id : '';
        $order = (property_exists($email,'object') && $email->object instanceof WC_Order) ? $email->object : null;
        // if($id==='customer_on_hold_order') return function(){ return true; };
        if($id==='customer_invoice' && $order && !$order->has_status('processing')) return function(){ return true; };
    }
    return $callback;
},10,2);

add_action('woocommerce_order_status_processing', function($order_id){
    $emails = WC()->mailer()->get_emails();
    if(isset($emails['WC_Email_Customer_Invoice'])) $emails['WC_Email_Customer_Invoice']->trigger($order_id);
},10);

function etn_sync($order_id,$new=null){
    $o = wc_get_order($order_id); if(!$o) return;
    $etn_id = (int)$o->get_meta('eventin_order_id', true); if($etn_id<=0) return;
    $s = $new ?: $o->get_status();
    update_post_meta($etn_id,'status',$s==='on-hold' ? 'w-on-payment' : $s);
}
add_action('woocommerce_thankyou', fn($id)=>etn_sync($id),20);
add_action('woocommerce_order_status_changed', fn($id,$old,$new,$o)=>etn_sync($id,$new),999,4);




// add_action('woocommerce_payment_complete', function($order_id){
//     $o = wc_get_order($order_id); if(!$o) return;
//     if($o->has_status(['pending','on-hold','failed'])) $o->update_status('processing');
// },10);

// add_action('woocommerce_thankyou', function($order_id){
//     $o = wc_get_order($order_id); if(!$o) return;
//     if($o->has_status(['pending','on-hold']) && ($o->is_paid() || $o->get_transaction_id())) {
//         $o->update_status('processing');
//     }
// },20);

add_action('woocommerce_payment_complete', function($order_id){
    $o = wc_get_order($order_id); if(!$o) return;
    if($o->get_transaction_id() && $o->has_status(['pending','on-hold','failed'])){
        $o->update_status('processing');
    }
},9);

add_action('template_redirect', function(){
    if(function_exists('is_order_received_page') && is_order_received_page() && isset($_GET['key'])){
        $order_id = wc_get_order_id_by_order_key(wc_clean(wp_unslash($_GET['key'])));
        if($order_id){
            $o = wc_get_order($order_id); if(!$o) return;
            if($o->get_transaction_id() && $o->has_status(['pending','on-hold'])){
                $o->update_status('processing');
            }
        }
    }
},1);

add_action('woocommerce_thankyou', function($order_id){
    if(!$order_id) return;
    if(!wp_next_scheduled('mm_verify_txid', [$order_id])){
        wp_schedule_single_event(time()+120, 'mm_verify_txid', [$order_id]);
    }
},10);

add_action('mm_verify_txid', function($order_id){
    $o = wc_get_order($order_id); if(!$o) return;
    if($o->get_transaction_id() && $o->has_status(['pending','on-hold'])){
        $o->update_status('processing');
    }
},10);

// added by hafij 2025-08-21


/**
 * Woo order pages visibility tweaks
 * - Hide "Order again" + "Proforma" when order is NOT completed (both Thank-You + View-Order)
 * - Hide ticket details on Thank-You (the <h2> + table pair) when NOT completed
 * - Hide the nested ticket section + proforma paragraph on View-Order when NOT completed
 * - Always hide the "Edit ticket" link on View-Order
 */

/* ----------------------------------------------------------
 * A) Buttons (CSS in <head>): hide when NOT completed
 * ---------------------------------------------------------- */
add_action('wp_head', function () {
  // Which page are we on?
  $on_thankyou  = function_exists('is_order_received_page') && is_order_received_page();
  $on_vieworder = function_exists('is_account_page') && is_account_page()
                  && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('view-order');

  if ( ! $on_thankyou && ! $on_vieworder ) return;

  // Get current order ID
  $order_id = 0;
  if ( $on_thankyou ) {
    $order_id = absint( get_query_var('order-received') );
    if ( ! $order_id && isset($_GET['key']) && function_exists('wc_get_order_id_by_order_key') ) {
      $order_id = wc_get_order_id_by_order_key( wc_clean( wp_unslash($_GET['key']) ) );
    }
  } else { // view-order
    $order_id = absint( get_query_var('view-order') );
  }

  // Hide only when NOT completed
  $hide = true;
  if ( $order_id && function_exists('wc_get_order') ) {
    $order = wc_get_order( $order_id );
    if ( $order ) {
      $hide = ( $order->get_status() !== 'completed' );
    }
  }

  if ( $hide ) {
    // Hide ONLY the two buttons inside the Woo order area
    echo '<style>
      .woocommerce-order p.order-again,
      .woocommerce-order p.invoice_proforma { display:none !important; }
    </style>';
  }
});

/* ----------------------------------------------------------
 * B) Thank-You page: hide ticket details (when NOT completed)
 *    Targets: <h2.woocommerce-column__title> + <table.order_details>
 * ---------------------------------------------------------- */
add_action('wp_footer', function () {
  if ( function_exists('is_order_received_page') && is_order_received_page() ) {

    // Get order ID from query vars (order-received) or key fallback
    $order_id = absint( get_query_var('order-received') );
    if ( ! $order_id && isset($_GET['key']) && function_exists('wc_get_order_id_by_order_key') ) {
      $order_id = wc_get_order_id_by_order_key( wc_clean( wp_unslash($_GET['key']) ) );
    }

    if ( $order_id && function_exists('wc_get_order') ) {
      $order = wc_get_order( $order_id );
      if ( $order && $order->get_status() !== 'completed' ) { ?>
        <script>
        // Hide only the "ticket details" pair on Thank You when NOT completed
        document.addEventListener('DOMContentLoaded',function(){
          document.querySelectorAll('h2.woocommerce-column__title + table.order_details')
            .forEach(function(tbl){
              var h2 = tbl.previousElementSibling;
              tbl.remove();
              if (h2) h2.remove();
            });
        });
        </script>
      <?php }
    }
  }
});

/* ----------------------------------------------------------
 * C) View-Order page: hide nested ticket section + proforma (NOT completed)
 *    Targets nested: section.woocommerce-order-details > section.woocommerce-order-details
 *    and: p.invoice_proforma
 * ---------------------------------------------------------- */
add_action('wp_footer', function () {
  if ( function_exists('is_account_page') && is_account_page()
       && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('view-order') ) {

    $order_id = absint( get_query_var('view-order') );
    if ( $order_id && function_exists('wc_get_order') ) {
      $order = wc_get_order( $order_id );
      if ( $order && $order->get_status() !== 'completed' ) { ?>
        <script>
        (function(){
          function hideViewOrderExtras(){
            // Remove ONLY the nested ticket section (keeps the outer "order details")
            document.querySelectorAll('section.woocommerce-order-details section.woocommerce-order-details')
              .forEach(function(sec){ sec.remove(); });

            // Remove the proforma / Advance invoice paragraph
            document.querySelectorAll('p.invoice_proforma').forEach(function(p){ p.remove(); });
          }
          document.addEventListener('DOMContentLoaded', hideViewOrderExtras);
          // In case content is injected after load
          new MutationObserver(hideViewOrderExtras).observe(document.body, { childList:true, subtree:true });
        })();
        </script>
      <?php }
    }
  }
});

/* ----------------------------------------------------------
 * D) View-Order page: ALWAYS hide "Edit ticket" link (+ clean up the "|" pipe)
 *    Target anchor contains: etn_action=edit_information
 * ---------------------------------------------------------- */
add_action('wp_footer', function () {
  if ( function_exists('is_account_page') && is_account_page()
       && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('view-order') ) { ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('a[href*="etn_action=edit_information"]').forEach(function(a){
    const isPipe = n => n && n.nodeType === Node.TEXT_NODE && n.textContent.trim() === '|';
    if (isPipe(a.nextSibling))     a.nextSibling.remove();
    if (isPipe(a.previousSibling)) a.previousSibling.remove();
    a.remove();
  });
});
</script>
<?php }
});

/* ----------------------------------------------------------
 * E) THANK-YOU + VIEW-ORDER: ALWAYS remove the three link <p> blocks
 *    Targets: p.order-again, p.invoice_proforma, p.invoice (ALL statuses)
 * ---------------------------------------------------------- */
add_action('wp_footer', function () {
  $on_thankyou  = function_exists('is_order_received_page') && is_order_received_page();
  $on_vieworder = function_exists('is_account_page') && is_account_page()
                  && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('view-order');
  if ( ! $on_thankyou && ! $on_vieworder ) return; ?>
<script>
(function(){
  function removeOrderLinks(){
    var root = document.querySelector('.woocommerce-order') || document;
    root.querySelectorAll('p.order-again, p.invoice_proforma, p.invoice').forEach(function(el){
      el.remove();
    });
  }
  document.addEventListener('DOMContentLoaded', removeOrderLinks);
  new MutationObserver(removeOrderLinks).observe(document.body, { childList:true, subtree:true });
})();
</script>
<?php
});

//change button color
function custom_button_color_script() {
    echo '<style>
        .css-1adptvt, .css-ftv64b, .wc-block-components-button {
            background-color: #10659C !important;
            border-color: #10659C !important;
        }
        .css-1spii6f {
            color: #10659C !important;
        }
        .wc-block-components-button{
            color: #fff !important;
            border-radius: 4px;
            padding: 0.8em !important;
            border:none;
            font-weight: bold;
        }
    </style>';
}
add_action('wp_footer', 'custom_button_color_script', 999);



add_action('wp_footer', function () {
  echo '<style id="etn-step-svg-fix">
    /* active + finished only */
    #eventin-checkout .ant-steps-item-process .ant-steps-item-icon svg [fill^="url("],
    #eventin-checkout .ant-steps-item-finish  .ant-steps-item-icon svg [fill^="url("],
    #eventin-checkout .ant-steps-item-process .ant-steps-item-icon svg [fill],
    #eventin-checkout .ant-steps-item-finish  .ant-steps-item-icon svg [fill]{
      fill:#10659C !important;           /* replace violet gradient */
    }
    #eventin-checkout .ant-steps-item-process .ant-steps-item-icon svg [stroke],
    #eventin-checkout .ant-steps-item-finish  .ant-steps-item-icon svg [stroke]{
      stroke:#fff !important;            /* keep check/number white */
    }
  </style>';
}, 9999);


add_action('admin_footer', function () {
  global $wpdb;
  // IDs with status = w-on-payment / w-for-payment
  $ids = array_map('intval', $wpdb->get_col("
    SELECT post_id FROM {$wpdb->postmeta}
    WHERE meta_key='status' AND meta_value IN ('w-on-payment','w-for-payment')
  "));
  ?>
  <script>
  (function(){
    var WANT = new Set(<?php echo wp_json_encode($ids); ?>);

    function patchOne(modal){
      if (!modal) return;
      // read "Booking ID - 3969" (be tolerant of separators)
      var title = modal.querySelector('.ant-modal-title');
      var m = title && (title.textContent||'').match(/Booking\s*ID[^\d#]*#?(\d+)/i);
      if (!m) return;
      var id = parseInt(m[1], 10);
      if (!WANT.has(id)) return;

      // find a tag that says "Failed" and replace its text
      var tags = modal.querySelectorAll('.ant-tag, [class*="ant-tag"]');
      for (var i=0;i<tags.length;i++){
        var node = tags[i].querySelector('.ant-tag-text, span') || tags[i];
        if (node && /^\s*failed\s*$/i.test(node.textContent)) {
          node.textContent = 'w-on-payment';
          // optional: remove red styling
          tags[i].classList.remove('ant-tag-error');
          break;
        }
      }
    }

    function patchAll(){
      document.querySelectorAll('.ant-modal-root .ant-modal-content').forEach(patchOne);
    }

    // run on load and on any DOM change (covers every popup open)
    window.addEventListener('load', patchAll);
    new MutationObserver(patchAll).observe(document.body, {childList:true, subtree:true});
  })();
  </script>
  <?php
}, 9999);

add_filter('wp_nav_menu_items', function ($items, $args) {
  if ($args->theme_location !== 'primary') return $items;

  if (is_user_logged_in()) {
    $url   = wp_logout_url(home_url('/'));
    $label = 'Odhlásiť sa';
  } else {
    $url   = '/prihlasenie';
    $label = 'Prihlásenie';
  }

  // note: mobile-only class makes it appear only on small screens
  $items .= '<li class="menu-item cta-btn mobile-only"><a href="'.esc_url($url).'">'.esc_html($label).'</a></li>';
  return $items;
}, 10, 2);


// To show actual time in speaker page


add_action('wp_footer', function () {
    ?>
    <script>
    (function () {
      function relax() {
        var form = document.getElementById('wpforms-form-1991');
        if (!form) return;
        var input = form.querySelector('input[name="wpforms[fields][5][state]"]');
        if (!input) return;
        input.removeAttribute('required');
        input.removeAttribute('aria-required');
        input.classList.remove('wpforms-field-required');
      }
      document.addEventListener('DOMContentLoaded', relax);
      document.addEventListener('wpformsReady', relax);
      // In case the form is injected/updated by a builder (Elementor, AJAX), watch for changes.
      new MutationObserver(relax).observe(document.documentElement, {childList:true, subtree:true});
    })();
    </script>
    <?php
}, 100);

/* ==== 2) Server-side: allow empty Region/State but keep others required ==== */
add_action('wpforms_process_validate_address', function ($field_id, $field_submit, $form_data) {
    // Target form #1991, address field #5
    if ((int)$form_data['id'] !== 1991 || (int)$field_id !== 5) return;

    // If Address is marked Required in the builder, WPForms errors when ANY subfield is empty.
    // We want Region/State to be optional but still require the core parts.
    $address1 = isset($field_submit['address1']) ? trim($field_submit['address1']) : '';
    $city     = isset($field_submit['city'])     ? trim($field_submit['city'])     : '';
    $postal   = isset($field_submit['postal'])   ? trim($field_submit['postal'])   : '';

    // When these are present, clear the error even if 'state' is empty.
    if ($address1 !== '' && $city !== '' && $postal !== '') {
        $process = wpforms()->process;
        if (isset($process->errors[$form_data['id']][$field_id])) {
            unset($process->errors[$form_data['id']][$field_id]);
        }
    }
}, 20, 3);

add_action('admin_init', function () {
    // Limit to Eventin admin pages (e.g., Event Settings, Speakers, etc.)
    $is_eventin_screen = isset($_GET['page']) && strpos($_GET['page'], 'eventin') !== false;

    if ($is_eventin_screen) {
        // Stop "your password was changed" to the user
        add_filter('send_password_change_email', '__return_false', 99);

        // Stop admin notification about password changes
        add_filter('wp_password_change_notification', '__return_false', 99);

        // Stop "your email was changed" to the user (in case Eventin updates email)
        add_filter('send_email_change_email', '__return_false', 99);
    }
});




//111 Stop canonical redirects on author pages
add_action('template_redirect', function () {
    if (!is_author()) return;
    remove_all_actions('template_redirect'); // nukes other redirects only for author pages
}, 0);

// 2) Ensure author archives actually have content (include Eventin speakers).
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query() || !$q->is_author()) return;
    $q->set('post_type', ['etn-speaker', 'post']);
    $q->set('post_status', ['publish']);
}, 999);





// Remove "Downloads" from WooCommerce My Account menu
add_filter('woocommerce_account_menu_items', function ($items) {
    unset($items['downloads']);   // hides the sidebar item
    return $items;
}, 99);

add_action('template_redirect', function () {
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('downloads')) {
        wp_safe_redirect(wc_get_account_endpoint_url('orders'));
        exit;
    }
});



// for resend new link
const WPF_RESEND_HOOK = 'wpforms_resend_activation_if_expired';

/**
 * Schedule the hourly job on activation.
 */
register_activation_hook( __FILE__, function () {
    // Requires WooCommerce's Action Scheduler.
    if ( function_exists('as_schedule_recurring_action') && ! as_next_scheduled_action( WPF_RESEND_HOOK ) ) {
        as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, WPF_RESEND_HOOK );
    }
});

/**
 * Clean up scheduled jobs on deactivation.
 */
register_deactivation_hook( __FILE__, function () {
    if ( function_exists('as_unschedule_all_actions') ) {
        as_unschedule_all_actions( WPF_RESEND_HOOK );
    }
});

/**
 * Safety net: ensure the job is scheduled even after updates.
 */
add_action( 'plugins_loaded', function () {
    if ( function_exists('as_schedule_recurring_action') && ! as_next_scheduled_action( WPF_RESEND_HOOK ) ) {
        as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, WPF_RESEND_HOOK );
    }
}, 20 );

/**
 * Hourly worker:
 * - Finds users still marked as pending by WPForms
 * - Resends a NEW activation email after 24h have passed
 * - Throttles per user to once/hour
 */
add_action( WPF_RESEND_HOOK, function () {

    // Must have WPForms User Registration (Elite).
    if ( ! function_exists('wpforms_user_registration')
        || ! class_exists('\WPFormsUserRegistration\SmartTags\Helpers\Helper') ) {
        return;
    }

    // Get unverified users.
    $pending = get_users([
        'meta_key'   => 'wpforms-pending',
        'meta_value' => true,
        'fields'     => ['ID','user_registered'],
    ]);

    if ( empty( $pending ) ) {
        return;
    }

    // Treat 24h as the original link lifetime; adjust if needed.
    $EXPIRY_SECONDS = DAY_IN_SECONDS;

    foreach ( $pending as $user ) {
        // How long since they registered?
        $registered_at = strtotime( $user->user_registered );
        if ( ! $registered_at ) {
            continue;
        }
        $age_seconds = time() - $registered_at;

        // Only after expiry.
        if ( $age_seconds < $EXPIRY_SECONDS ) {
            continue;
        }

        // Per-user hourly throttle.
        $last = (int) get_user_meta( $user->ID, 'wpforms_last_resend_ts', true );
        if ( $last && ( time() - $last ) < HOUR_IN_SECONDS ) {
            continue;
        }

        // Send a NEW activation email (fresh link).
        \WPFormsUserRegistration\SmartTags\Helpers\Helper::set_user( $user );
        wpforms_user_registration()->get( 'email_notifications' )->resend_activation( $user->ID );

        // Mark last-resend time.
        update_user_meta( $user->ID, 'wpforms_last_resend_ts', time() );
    }
});




