<?php
/**
 * Mailchimp Tag Sync for SUZ
 * - Role-based tags
 * - Conference tags (WooCommerce / Eventin)
 * - WPForms integration for registration form
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load config
require_once plugin_dir_path( __FILE__ ) . 'config.php';

/**
 * ===== Helpers =====
 */
function suz_mc_require_conf() {
    // return false;
    if ( ! defined( 'MAILCHIMP_API_KEY' ) || ! defined( 'MAILCHIMP_LIST_ID' ) ) {
        error_log('[SUZ][MC] API key or LIST ID missing.');
        return false;
    }
    return true;
}

function suz_mc_dc() {
    return substr( MAILCHIMP_API_KEY, strpos( MAILCHIMP_API_KEY, '-' ) + 1 );
}

function suz_mc_hash( $email ) {
    return md5( strtolower( trim( $email ) ) );
}

function suz_mc_headers() {
    return [
        'Authorization' => 'apikey ' . MAILCHIMP_API_KEY,
        'Content-Type'  => 'application/json',
    ];
}


function suz_mc_upsert_member( $email, $merge_fields = [], $tags = [] ) {
  if ( ! suz_mc_require_conf() ) return false;

  $dc = suz_mc_dc(); $list = MAILCHIMP_LIST_ID; $hash = suz_mc_hash($email);
  $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list}/members/{$hash}";
  $status_if_new = (defined('SUZ_MC_DOUBLE_OPTIN_IF_NEW') && SUZ_MC_DOUBLE_OPTIN_IF_NEW) ? 'pending' : 'subscribed';

  $ADDRESS_TAG = 'ADDRESS';
  $addr = $merge_fields[$ADDRESS_TAG] ?? null; unset($merge_fields[$ADDRESS_TAG]);
  unset($merge_fields['newsletter']); // don’t map to status

  $body1 = [
    'email_address' => $email,
    'status_if_new' => $status_if_new,
    'merge_fields'  => $merge_fields,
  ];
  if ($tags) $body1['tags'] = array_values($tags);

  // --- PUT (create/update, no address) ---
  $req1 = [
    'method'  => 'PUT',
    'headers' => suz_mc_headers(),
    'body'    => wp_json_encode($body1, JSON_UNESCAPED_UNICODE),
    'timeout' => 20,
  ];
  error_log('[SUZ][MC][PUT body] '. $req1['body']);
  $res1 = wp_remote_request($url, $req1);
  if ( is_wp_error($res1) ) { error_log('[SUZ][MC] PUT WP_Error: '.$res1->get_error_message()); return false; }
  $c1 = wp_remote_retrieve_response_code($res1); $b1 = wp_remote_retrieve_body($res1);
  if ($c1 < 200 || $c1 >= 300) { error_log("[SUZ][MC] PUT failed HTTP $c1: $b1"); return false; }

  // --- PATCH (address) ---
  if (is_array($addr) && $addr) {
    $addr = array_filter([
      'addr1'   => $addr['addr1'] ?? null,
      'addr2'   => $addr['addr2'] ?? null,
      'city'    => $addr['city'] ?? null,
      'state'   => $addr['state'] ?? null,
      'zip'     => isset($addr['zip']) ? preg_replace('/\s+/', '', $addr['zip']) : null,
      'country' => $addr['country'] ?? null,
    ], fn($v) => $v !== '' && $v !== null);

    if ($addr) {
      $body2 = ['merge_fields' => [ $ADDRESS_TAG => $addr ]];
      $req2 = [
        'method'  => 'PATCH',
        'headers' => suz_mc_headers(),
        'body'    => wp_json_encode($body2, JSON_UNESCAPED_UNICODE),
        'timeout' => 20,
      ];
      error_log('[SUZ][MC][PATCH body] '. $req2['body']);
      $res2 = wp_remote_request($url, $req2);
      if ( is_wp_error($res2) ) { error_log('[SUZ][MC] PATCH WP_Error: '.$res2->get_error_message()); return false; }
      $c2 = wp_remote_retrieve_response_code($res2); $b2 = wp_remote_retrieve_body($res2);
      if ($c2 < 200 || $c2 >= 300) { error_log("[SUZ][MC] PATCH failed HTTP $c2: $b2"); return false; }
    }
  }

  return true;
}

/**
 * Add tags
 */
function suz_mc_add_tags_by_email( $email, array $tags ) {
    if ( ! suz_mc_require_conf() || empty( $tags ) ) return false;

    $dc      = suz_mc_dc();
    $list_id = MAILCHIMP_LIST_ID;
    $hash    = suz_mc_hash( $email );
    $url     = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$hash}/tags";

    $payload_tags = [];
    foreach ( array_filter( array_unique( $tags ) ) as $tag ) {
        $payload_tags[] = ['name' => $tag, 'status' => 'active'];
    }

    $response = wp_remote_post( $url, [
        'headers' => suz_mc_headers(),
        'body'    => wp_json_encode([ 'tags' => $payload_tags ]),
        'timeout' => 20,
    ]);

    if ( is_wp_error( $response ) ) {
        error_log('[SUZ][MC] Add tags error: '.$response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code( $response );
    return ($code >= 200 && $code < 300);
}

/**
 * Remove tags
 */
function suz_mc_remove_tags_by_email( $email, array $tags ) {
    if ( ! suz_mc_require_conf() || empty( $tags ) ) return false;

    $dc      = suz_mc_dc();
    $list_id = MAILCHIMP_LIST_ID;
    $hash    = suz_mc_hash( $email );
    $url     = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$hash}/tags";

    $payload_tags = [];
    foreach ( array_filter( array_unique($tags) ) as $tag ) {
        $payload_tags[] = ['name' => $tag, 'status' => 'inactive'];
    }

    $response = wp_remote_post( $url, [
        'headers' => suz_mc_headers(),
        'body'    => wp_json_encode([ 'tags' => $payload_tags ]),
        'timeout' => 20,
    ]);

    if ( is_wp_error( $response ) ) {
        error_log('[SUZ][MC] Remove tags error: '.$response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code( $response );
    return ($code >= 200 && $code < 300);
}

/**
 * Add tags to a user and ensure Mailchimp contact is up-to-date
 *
 * @param int   $user_id      WordPress user ID
 * @param array $tags         Array of Mailchimp tags to add
 * @param array $merge_fields Optional merge fields (FNAME, LNAME, etc.)
 * @return bool               Success or failure
 */
function suz_mc_add_tags( $user_id, array $tags, $merge_fields = [] ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return false;

    $email = $user->user_email;
    // $email = 'programmer+1@magicmedia.sk';
    // Upsert contact with merge fields and add tags
    // suz_mc_upsert_member( $user->user_email, $merge_fields, $tags );

    error_log('mail pt add: ' . json_encode($tags));
    // Add only tags separately as well (ensures tags are applied)
    return suz_mc_add_tags_by_email( $email, $tags );
}

/**
 * Remove tags from a user and optionally update Mailchimp contact
 *
 * @param int   $user_id      WordPress user ID
 * @param array $tags         Array of Mailchimp tags to remove
 * @param array $merge_fields Optional merge fields to update
 * @return bool               Success or failure
 */
function suz_mc_remove_tags( $user_id, array $tags, $merge_fields = [] ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return false;


    $email = $user->user_email;
    // $email = 'programmer+1@magicmedia.sk';

    // Upsert contact with merge fields (keeps contact updated)
    // suz_mc_upsert_member( $user->user_email, $merge_fields );

    // Remove only tags
    error_log('mail pt remove: ' . json_encode($tags));
    return suz_mc_remove_tags_by_email( $email, $tags );
}

/**
 * Role -> Tag mapping (Updated as per client requirement)
 * Maps WP user roles to Mailchimp tags
 */
function suz_role_to_tag( $role ) {
    switch ( $role ) {
        case 'suz_member': 
            return 'Zamestnanec člena SUZ';
        case 'suz_representative': 
            return 'Zástupca člena SUZ';
        case 'suz_non_member': 
            return 'NEČLEN';
        case 'vip': 
            return 'VIP';
        case 'PaDR': 
            return 'OLD USER'; // Tag for old users
        default: 
            return '';
    }
}

function suz_role_to_member_type( $role ) {
    switch ( $role ) {
        case 'suz_member': 
            return 'Member SUZ';
        case 'suz_representative': 
            return 'SUZ Representative';
        case 'suz_non_member': 
            return 'SUZ non-member';
        case 'vip': 
            return 'VIP';
        default: 
            return '';
    }
}

/**
 * Returns all role-related tags
 * Used for removing old tags during role sync
 */
function suz_all_role_tags() {
    return [
        'Zamestnanec člena SUZ',
        'Zástupca člena SUZ',
        'NEČLEN',
        'VIP',
        'OLD USER' // Include PaDR tag
    ];
}

/**
 * Assign role + Mailchimp sync
 */
function suz_assign_role_and_sync_tags( $user_id, $role ) {
    $u = new WP_User( $user_id );
    $u->set_role( $role );
    suz_sync_mailchimp_role_tags( $user_id, $role );
}

/**
 * Sync role -> tags
 */
function suz_sync_mailchimp_role_tags( $user_id, $new_role ) {
    $tag_to_add = suz_role_to_tag( $new_role );
    if ( empty($tag_to_add) ) return;

    $tags_to_remove = array_diff( suz_all_role_tags(), [$tag_to_add] );
    $user = get_userdata($user_id);
    if ( !$user ) return;

    $email = $user->user_email;
    // $email = 'programmer+1@magicmedia.sk';

    
    $mc_fields =[
        'MMERGE6' => suz_role_to_tag($new_role),
        'MMERGE7' => suz_role_to_tag($new_role),
        'MMERGE13' => suz_role_to_member_type($new_role)
    ]; 
    
    error_log('role change from-ico tracker: ' . json_encode($mc_fields) . $email . ' - '. $new_role);

    suz_mc_upsert_member($email, $mc_fields);
    suz_mc_remove_tags_by_email($email, $tags_to_remove);
    suz_mc_add_tags_by_email($email, [$tag_to_add]);
}

/**
 * Admin role change hook
 */
add_action( 'set_user_role', function( $user_id, $role, $old_roles ){
    error_log('role change: '. 'clicked');
    // suz_sync_mailchimp_role_tags( $user_id, $role );
}, 10, 3 );

/**
 * IČO verification helper
 */
if ( ! function_exists( 'suz_check_ico_in_member_list2' ) ) {
    /**
     * Checks if the given IČO exists in the SUZ member companies table.
     *
     * @param string $ico Company registration number (IČO)
     * @return bool True if IČO exists, false otherwise
     */
    function suz_check_ico_in_member_list2( $ico ) {
        global $wpdb;

        if ( empty( $ico ) ) {
            return false;
        }

        $ico = sanitize_text_field( $ico );
        $table_name = $wpdb->prefix . 'suz_member_companies';

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE ico = %s",
                $ico
            )
        );

        return ( $exists > 0 );
    }
}

/**
 * WPForms integration
 * - Mailchimp upsert on registration form submission
 */
if ( ! function_exists( 'suz_sync_mailchimp_contact' ) ) {
    function suz_sync_mailchimp_contact( $mc_data ) {
        $mc_fields['FNAME']   = $mc_data['first_name'] ?? '';
        $mc_fields['LNAME']   = $mc_data['last_name'] ?? '';
        $mc_fields['EMAIL']   = $mc_data['email'];
        // $mc_fields['EMAIL']   = 'programmer+1@magicmedia.sk';
        $mc_fields['PHONE']   = $mc_data['phone'] ?? '-';
        $mc_fields['MMERGE12'] = $mc_data['ico'] ?? '';
        $mc_fields['MMERGE9']  = $mc_data['company'] ?? '';
        if(!empty($mc_data['titul'])){
            $mc_fields['MMERGE10']  = $mc_data['titul'];
        }
        if(!empty($mc_data['titul_za'])){
            $mc_fields['MMERGE11']  = $mc_data['titul_za'];
        }
        if(!empty($mc_data['role'])){
            $mc_fields['MMERGE6']  = suz_role_to_tag($mc_data['role'] ?? '');
            $mc_fields['MMERGE7']  = suz_role_to_tag($mc_data['role'] ?? '');
            $mc_fields['MMERGE13']  = suz_role_to_member_type($mc_data['role'] ?? '');
        }

        // Build ADDRESS as an array (NOT json string)
        $adrs = [];
        if (!empty($mc_data['address']))  $adrs['addr1']   = $mc_data['address'];
        if (!empty($mc_data['city']))     $adrs['city']    = $mc_data['city'];
        if (!empty($mc_data['postcode'])) $adrs['zip']     = $mc_data['postcode'];
        if (!empty($mc_data['country']))  $adrs['country'] = strtoupper($mc_data['country']); // e.g., 'SK'

        // Only set ADDRESS if we have at least addr1 or city/zip/etc.
        if (!empty($adrs)) {
            $mc_fields['ADDRESS'] = $adrs;   // <- no json_encode here
        }


        $newsletter = get_user_meta( $mc_data['user_id'], 'newsletter', true );
        if($newsletter == 'true'){
            $mc_fields['newsletter'] = 1;
        }else{
            $mc_fields['newsletter'] = 0;
        }
        
        if(isset($mc_data['role'])){
            suz_mc_upsert_member( $mc_fields['EMAIL'], $mc_fields, [ suz_role_to_tag($mc_data['role'] ?? '') ] );
        }else{
            suz_mc_upsert_member( $mc_fields['EMAIL'], $mc_fields );
        }
    }
    
}


/**
 * Assign Mailchimp Event Tag on ticket purchase (Event ID based)
 */
add_action('woocommerce_order_status_completed', function($order_id){
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_user_id();
    $user_email = $user_id ? get_userdata($user_id)->user_email : $order->get_billing_email();

    $etn_order_id = $order->get_meta('eventin_order_id');
    $event_id = get_post_meta($etn_order_id, 'event_id', true);

    $event_tag = suz_get_first_eventin_tag($event_id);

    error_log('event tag assign: '. $event_id .' - '. $event_tag);

    suz_mc_add_tags($user_id, [$event_tag]);

});

function suz_get_first_eventin_tag( $event_id ) {
    $event_id = (int) $event_id;
    if ( $event_id <= 0 ) return null;

    $post = get_post( $event_id );
    if ( ! $post ) return null;

    // 1) Try common Eventin tag taxonomies
    $candidates = array( 'etn_tag', 'etn_tags', 'event_tag', 'eventin_tag' );
    foreach ( $candidates as $tax ) {
        if ( taxonomy_exists( $tax ) && is_object_in_taxonomy( $post->post_type, $tax ) ) {
            $terms = wp_get_post_terms( $event_id, $tax, array(
                'orderby' => 'term_order', // keep author order if set
                'order'   => 'ASC',
                'fields'  => 'all',
            ) );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                return $terms[0]->name;
            }
        }
    }

    // 2) Fallback: any taxonomy attached to this post type that looks like a "tag"
    $tax_objects = get_object_taxonomies( $post->post_type, 'objects' );
    foreach ( $tax_objects as $tax_obj ) {
        $tname = strtolower( $tax_obj->name );
        if ( strpos( $tname, 'tag' ) === false ) continue;

        $terms = wp_get_post_terms( $event_id, $tax_obj->name, array(
            'orderby' => 'term_order',
            'order'   => 'ASC',
            'fields'  => 'all',
        ) );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            return $terms[0]->name;
        }
    }

    return null;
}

/**
 * Assign "PaDR" role to old users during import and sync multiple custom fields to Mailchimp
 *
 * @param array $users Array of existing users to import with custom fields
 * Example:
 * [
 *   [
 *     'email' => 'olduser1@example.com',
 *     'ICO' => '12345678',
 *     'CompanyName' => 'ACME Corp',
 *     'Phone' => '+421900123456',
 *     'Position' => 'Manager'
 *   ],
 *   [
 *     'email' => 'olduser2@example.com',
 *     'ICO' => '87654321',
 *     'CompanyName' => 'Beta Ltd',
 *     'Phone' => '+421900654321',
 *     'Position' => 'Director'
 *   ],
 * ]
 */
function suz_import_old_users_as_padr( $users = [] ) {
    if ( empty($users) || !is_array($users) ) return;

    foreach ( $users as $data ) {
        if ( empty($data['email']) ) continue;

        $email = sanitize_email($data['email']);

        // Get existing WP user by email
        $user = get_user_by('email', $email);
        if ( !$user ) {
            error_log("[SUZ][PaDR] User with email {$email} not found.");
            continue;
        }

        $user_id = $user->ID;

        // Assign PaDR role
        $wp_user = new WP_User($user_id);
        $wp_user->set_role('PaDR');

        // Prepare merge fields for Mailchimp
        $merge_fields = [];
        if ( !empty($data['ICO']) ) {
            $merge_fields['ICO'] = sanitize_text_field($data['ICO']);
        }
        if ( !empty($data['CompanyName']) ) {
            $merge_fields['COMPANY'] = sanitize_text_field($data['CompanyName']);
        }
        if ( !empty($data['Phone']) ) {
            $merge_fields['PHONE'] = sanitize_text_field($data['Phone']);
        }
        if ( !empty($data['Position']) ) {
            $merge_fields['POSITION'] = sanitize_text_field($data['Position']);
        }

        // Upsert user to Mailchimp with custom fields
        suz_mc_upsert_member( $user->user_email, $merge_fields );

        // Sync Mailchimp role-based tag (PaDR / OLD USER)
        suz_sync_mailchimp_role_tags($user_id, 'PaDR');

        error_log("[SUZ][PaDR] Assigned 'PaDR' role, synced Mailchimp tag and multiple custom fields for user {$email}.");
    }
}

// Use admin_init hook to safely import old users after WP core is loaded
// add_action('admin_init', function() {
//     $suz_old_users = [
//         [
//             'email' => 'olduser1@example.com',
//             'ICO' => '12345678',
//             'CompanyName' => 'ACME Corp',
//             'Phone' => '+421900123456',
//             'Position' => 'Manager'
//         ],
//         [
//             'email' => 'olduser2@example.com',
//             'ICO' => '87654321',
//             'CompanyName' => 'Beta Ltd',
//             'Phone' => '+421900654321',
//             'Position' => 'Director'
//         ],
//     ];
//     suz_import_old_users_as_padr($suz_old_users);
// });

/**
 * Admin BackEnd User Profile Checkboxes for Potential Tags
 */

// Default priority (10) 
add_action('show_user_profile', 'suz_add_custom_checkboxes');
add_action('edit_user_profile', 'suz_add_custom_checkboxes');

function suz_add_custom_checkboxes($user) {
    $potential_tags = get_user_meta($user->ID, '_suz_potential_tags', true);
    if (!is_array($potential_tags)) $potential_tags = [];

    $options = [
        'Pot_Clen'           => 'Pot_Clen',
        'Pot_Partner'        => 'Pot_Partner',
        'Pot_Prednasajuci'   => 'Pot_Prednasajuci'
    ];
    ?>
    <h2>Potential Tags</h2>
    <table class="form-table" id="suz-potential-tags-table">
        <tbody>
            <?php foreach ($options as $key => $label) : ?>
            <tr>
                <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                <td>
                    <input type="checkbox" name="suz_potential_tags[]" id="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $potential_tags)); ?> />
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// Save checkbox values and sync to Mailchimp
add_action('personal_options_update', 'suz_save_custom_checkboxes');
add_action('edit_user_profile_update', 'suz_save_custom_checkboxes');

function suz_save_custom_checkboxes($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;

    $selected_tags = isset($_POST['suz_potential_tags']) ? array_map('sanitize_text_field', $_POST['suz_potential_tags']) : [];
    update_user_meta($user_id, '_suz_potential_tags', $selected_tags);

    suz_sync_mailchimp_potential_tags($user_id, $selected_tags);
}

function suz_sync_mailchimp_potential_tags($user_id, $tags) {
    if (!$user_id || !is_array($tags)) return;

    $tag_map = [
        'Pot_Clen'           => 'Pot_Clen',
        'Pot_Partner'        => 'Pot_Partner',
        'Pot_Prednasajuci'   => 'Pot_Prednasajuci'
    ];

    // Map selected keys to Mailchimp tag names
    $mailchimp_tags_to_add = [];
    foreach ($tags as $key) {
        if (isset($tag_map[$key])) {
            $mailchimp_tags_to_add[] = $tag_map[$key];
        }
    }

    // Add selected tags
    if (!empty($mailchimp_tags_to_add)) {
        suz_mc_add_tags($user_id, $mailchimp_tags_to_add);
    }

    // Remove unselected tags
    $all_tags = array_values($tag_map);
    $tags_to_remove = array_diff($all_tags, $mailchimp_tags_to_add);

    if (!empty($tags_to_remove)) {
        suz_mc_remove_tags($user_id, $tags_to_remove);
    }
}

/**
 * Move Potential Tags table right after Role field using jQuery
 */
add_action('admin_footer-user-edit.php', 'suz_move_potential_tags');
add_action('admin_footer-profile.php', 'suz_move_potential_tags');

function suz_move_potential_tags() {
    ?>
    <script>
    jQuery(document).ready(function($){
        var potentialTags = $('#suz-potential-tags-table');
        var roleTable = $('.user-role-wrap').closest('table');
        if (potentialTags.length && roleTable.length) {
            potentialTags.insertAfter(roleTable);
        }
    });
    </script>
    <?php
}


define('SUZ_WPF_REG_FORM_ID', 1985); // registration form ID
define('SUZ_WPF_NEWSLETTER_FIELD_ID', 7); // checkbox field ID

add_action('wpforms_user_registered', function($user_id, $fields, $form_data, $entry_id){
    if ((int)$form_data['id'] !== (int)SUZ_WPF_REG_FORM_ID) return;

    $consent = false;
    if (isset($fields[SUZ_WPF_NEWSLETTER_FIELD_ID])) {
        $f = $fields[SUZ_WPF_NEWSLETTER_FIELD_ID];
        if (isset($f['value_raw'])) {
            $consent = (bool)$f['value_raw'];
        } elseif (isset($f['value'])) {
            $v = $f['value'];
            $consent = is_array($v) ? !empty($v) : in_array(strtolower((string)$v), ['1','true','on','yes'], true);
        }
    }

    update_user_meta($user_id, 'newsletter', $consent ? 'true' : 'false'); // or use 1/0
}, 10, 4);
