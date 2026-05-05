<?php

// Visit the URL while logged-in as admin:
// echo suz_import_url(); somewhere (admin page or WP-CLI) to get a valid link.
add_action('init', function () {
  if (!isset($_GET['suz_import'])) return;

  if (!is_user_logged_in() || !current_user_can('manage_options')) {
    status_header(403); exit('Forbidden');
  }
  if (empty($_GET['_wpnonce'])) {
    status_header(403); exit('Bad nonce');
  }

  $res = suz_do_import();
  wp_die('Import finished. Imported: ' . intval($res['imported']) .
         ', Updated: ' . intval($res['updated']) .
         ', Skipped: ' . intval($res['skipped']));
});

function suz_import_url(): string {
  return wp_nonce_url(home_url('/?suz_import=1'), 'suz_import');
}

function suz_do_import(): array {
  $csv_path = plugin_dir_path(__FILE__) . 'demo_user.csv';
  if (!file_exists($csv_path)) wp_die('CSV not found at: ' . esc_html($csv_path));

  // 1) Disable ALL core new-user notifications (admin + user)
  add_filter('wp_send_new_user_notifications', '__return_false', 10, 3);

  // 2) Send our email as HTML
  add_filter('wp_mail_content_type', fn() => 'text/html; charset=UTF-8');

  $to_bool = function ($v): bool {
    $v = trim(mb_strtolower((string)$v, 'UTF-8'));
    return in_array($v, ['áno','ano','yes','y','1','true'], true);
  };
  $unique_login = function ($base) {
    $login = $base ?: 'member';
    $i = 1;
    while (username_exists($login)) $login = $base . '_' . $i++;
    return $login;
  };
  $make_login = function (array $r) use ($unique_login) {
    $email = trim($r['Email Address'] ?? '');
    if ($email && ($at = strpos($email, '@')) !== false) {
      $base = sanitize_user(substr($email, 0, $at), true);
      return $unique_login($base);
    }
    $first = sanitize_user(trim($r['Meno'] ?? ''), true);
    $last  = sanitize_user(trim($r['Prizvisko'] ?? ''), true);
    return $unique_login(trim($first . '.' . $last, '.'));
  };

  $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $login_url = site_url() . '/prihlasenie';

  $imported = $updated = $skipped = 0;

  if (($h = fopen($csv_path, 'r')) === false) wp_die('Unable to open CSV.');
  $headers = fgetcsv($h, 0, ','); 
  if (!$headers) { fclose($h); wp_die('CSV has no header row.'); }

  $idx = array_flip($headers);
  foreach (['Membership Validity','Email','Meno','Prizvisko'] as $col) {
    if (!isset($idx[$col])) { fclose($h); wp_die('Missing column: ' . esc_html($col)); }
  }

  while (($row = fgetcsv($h, 0, ',')) !== false) {
    $r = [];
    foreach ($headers as $i => $key) { $r[$key] = $row[$i] ?? ''; }

    dd($r);

    $email = trim($r['Email'] ?? '');
    if (!is_email($email)) { $skipped++; continue; }

    $first = trim($r['Meno'] ?? '');
    $last  = trim($r['Prizvisko'] ?? '');
    $title_before = trim($r['Titul'] ?? '');
    $title_after  = trim($r['Titul za'] ?? '');
    $display = trim(implode(' ', array_filter([$title_before, $first, $last, $title_after])));

    // $username   = $make_login($r);
    $username   = $email;
    $password   = wp_generate_password(12, true, false); // plain; WP hashes on save
    // $roles_all = suz_roles_for_user($r['Member type'] ?? '');
    $roles_all = suz_roles_for_user('suz member');
    $roles_all = array_values(array_unique(array_merge(['customer'], array_diff($roles_all, ['customer'])))); // put 'customer' first
    $primary   = 'customer';

    // Create or update ONCE
    $existing = get_user_by('email', $email);
    if (!$existing) $existing = get_user_by('login', $username);

    $fields = [
      'user_email'   => $email,
      'first_name'   => $first,
      'last_name'    => $last,
      'display_name' => $display ?: trim("$first $last"),
      'role'         => $primary,     // temporary; final roles applied below
      'user_pass'    => $password,    // plain here, hashed in DB
    ];


    // Prepare Mailchimp data array
    $mc_data = [
        'email'   => $email
    ];

    if ($existing) {
      $fields['ID'] = $existing->ID;
      $uid = wp_update_user($fields);
      if (is_wp_error($uid)) { $skipped++; continue; }
      $final_login = $existing->user_login;
      $updated++;
      $mc_data['user_id'] = $uid;
    } else {
      $fields['user_login'] = $username;
      $uid = wp_insert_user($fields);
      if (is_wp_error($uid)) { $skipped++; continue; }
      $final_login = $username;
      $mc_data['user_id'] = $uid;
      $imported++;
    }

    // Apply full role set (resets to primary, then add extras)
    suz_apply_roles($uid, $roles_all);

    // --- Meta (use $uid and $r) ---
    $phone = trim($r['Phone'] ?? '');

    $postal = trim($r['Postcode'] ?? '');
    $street = trim($r['Street'] ?? '');
    $city = trim($r['City'] ?? '');
    $country = trim($r['Country'] ?? '');

    update_user_meta($uid, 'billing_company',      trim($r['Company Name'] ?? ''));
    update_user_meta($uid, 'first_name',           $first);
    update_user_meta($uid, 'billing_first_name',   $first);
    update_user_meta($uid, 'last_name',            $last);
    update_user_meta($uid, 'billing_last_name',    $last);
    update_user_meta($uid, 'company_ico',          trim($r['ICO'] ?? ''));
    update_user_meta($uid, 'billing_ico',          trim($r['ICO'] ?? ''));
    update_user_meta($uid, 'position',             trim($r['Funkcia/Pozícia'] ?? ''));
    update_user_meta($uid, 'relationship_to_suz',  trim($r['Vzťah k SUZ'] ?? ''));
    update_user_meta($uid, 'phone',                $phone);
    update_user_meta($uid, 'billing_phone',        $phone);
    update_user_meta($uid, 'billing_address_1',    $street);
    update_user_meta($uid, 'billing_postcode',     $postal);
    update_user_meta($uid, 'billing_city',         $city);
    update_user_meta($uid, 'billing_country',      $country);
    update_user_meta($uid, 'birthday',             trim($r['Birthday'] ?? ''));
    update_user_meta($uid, 'gender',               trim($r['Gender'] ?? ''));
    $consent = $to_bool($r['Povolenie pre posielanie informácií emailom'] ?? '');
    update_user_meta($uid, 'email_optin',          $consent ? 'yes' : 'yes');
    update_user_meta($uid, 'latitude',             trim($r['LATITUDE'] ?? ''));
    update_user_meta($uid, 'longitude',            trim($r['LONGITUDE'] ?? ''));
    update_user_meta($uid, 'timezone',             trim($r['TIMEZONE'] ?? ''));
    update_user_meta($uid, 'member_rating',        trim($r['MEMBER_RATING'] ?? ''));
    update_user_meta($uid, 'optin_time',           trim($r['OPTIN_TIME'] ?? ''));
    update_user_meta($uid, 'optin_ip',             trim($r['OPTIN_IP'] ?? ''));
    update_user_meta($uid, 'confirm_time',         trim($r['CONFIRM_TIME'] ?? ''));
    update_user_meta($uid, 'confirm_ip',           trim($r['CONFIRM_IP'] ?? ''));
    update_user_meta($uid, 'cc',                   trim($r['CC'] ?? ''));
    update_user_meta($uid, 'region',               trim($r['REGION'] ?? ''));
    update_user_meta($uid, 'last_changed',         trim($r['LAST_CHANGED'] ?? ''));
    update_user_meta($uid, 'leid',                 trim($r['LEID'] ?? ''));
    update_user_meta($uid, 'euid',                 trim($r['EUID'] ?? ''));
    update_user_meta($uid, 'notes',                trim($r['NOTES'] ?? ''));
    // update_user_meta($uid, 'tags',                 trim($r['TAGS'] ?? ''));

    $mc_data['first_name'] = $first;
    $mc_data['last_name']  = $last;
    $mc_data['titul']  = $title_before;
    $mc_data['titul_za']  = $title_after;
    $mc_data['ico'] = trim($r['ICO'] ?? '');
    $mc_data['phone'] = !empty($phone) ? $phone : '-';
    $mc_data['company'] = trim($r['Company Name'] ?? '');
    $mc_data['address'] = $street;
    $mc_data['postcode'] = $postal;
    $mc_data['city'] = $city;
    $mc_data['country'] = $country;
    $mc_data['role'] = 'suz_member';


    if ( function_exists( 'suz_sync_mailchimp_contact' ) ) {
        suz_sync_mailchimp_contact( $mc_data );
    }

    // --- One email to the user only ---
    $subject = __( 'Vaše prihlasovacie údaje - Spoločnosť údržby zariadení - SUZ', 'suz-control-panel' );

    $site_host  = preg_replace('/^www\./', '', parse_url(home_url(), PHP_URL_HOST) ?: '');
    $user_email = esc_html($email);
    $user_pass  = esc_html($password);
    $site_title = esc_html($site_name);
    $site_host_safe = esc_html($site_host);
    $email_greeting = esc_html__( 'Dobrý deň,', 'suz-control-panel' );
    $email_intro_1 = esc_html__( 'Tento e-mail obsahuje Vaše prihlasovacie údaje pre prístup na našu webovú stránku.', 'suz-control-panel' );
    $email_intro_2 = esc_html__( 'Prihláste sa pomocou uvedených údajov nižšie.', 'suz-control-panel' );
    $email_help_1 = esc_html__( 'Ak by ste narazili na akýkoľvek problém s prihlásením alebo prístupom,', 'suz-control-panel' );
    $email_help_2 = esc_html__( 'neváhajte nás kontaktovať - radi Vám pomôžeme.', 'suz-control-panel' );
    $email_credentials = esc_html__( 'Vaše prístupové údaje:', 'suz-control-panel' );
    $email_username = esc_html__( 'Prihlasovacie meno:', 'suz-control-panel' );
    $email_password = esc_html__( 'Heslo:', 'suz-control-panel' );
    $email_security = esc_html__( 'Po prihlásení odporúčame heslo zmeniť kvôli bezpečnosti.', 'suz-control-panel' );
    $email_sent_from = esc_html__( 'Odoslané z', 'suz-control-panel' );

    $body = <<<HTML
        <!doctype html>
        <html lang="sk">
        <body style="margin:0;padding:0;background:#eceff1;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eceff1;">
            <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border:1px solid #e5e7eb;border-radius:6px;">
                <tr>
                    <td style="padding:32px 28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,'Noto Sans',sans-serif;color:#111827;line-height:1.55;">
                    
                    <p style="margin:0 0 16px 0;font-size:14px;">{$email_greeting}</p>

                    <p style="margin:0 0 16px 0;font-size:14px;">
                        {$email_intro_1}
                        {$email_intro_2}
                    </p>

                    <p style="margin:0 0 16px 0;font-size:14px;">
                        {$email_help_1}
                        {$email_help_2}
                    </p>

                    <p style="font-size:14px;margin:20px 0 8px 0;"><strong>{$email_credentials}</strong></p>
                    <p style="margin:0 0 6px 0;font-size:15px;">
                        <strong>{$email_username}</strong>
                        <a href="mailto:{$user_email}" style="color:#ea580c;text-decoration:underline;">{$user_email}</a>
                    </p>
                    <p style="margin:0 0 20px 0;font-size:15px;">
                        <strong>{$email_password}</strong> {$user_pass}
                    </p>

                    <p style="margin:0 0 16px 0;font-size:14px;">
                        {$email_security}
                    </p>

                    <hr style="border:none;height:1px;background:#e5e7eb;margin:20px 0 10px 0;" />

                    <p style="margin:0;text-align:center;color:#9ca3af;font-size:13px;">
                        {$email_sent_from} <a href="{home_url()}" style="color:#9ca3af;text-decoration:underline;">{$site_title}</a>
                        - <a href="https://{$site_host_safe}" style="color:#9ca3af;text-decoration:underline;">{$site_host_safe}</a>
                    </p>
                    </td>
                </tr>
                </table>
            </td>
            </tr>
        </table>
        </body>
        </html>
    HTML;
    $sent = wp_mail($email, $subject, $body);
    // $sent = true;

    

    if (!$sent) {
      error_log("[SUZ Import] wp_mail FAILED for {$email}. Configure SMTP (e.g. WP Mail SMTP).");
    } else {
      error_log("[SUZ Import] Email sent to {$email}");
    }
  }

  fclose($h);
  return compact('imported','updated','skipped');
}

/** ROLES **/

function suz_primary_role_from_member_type(?string $raw): string {
  $t = trim(strtolower(remove_accents((string)$raw)));
  $t = preg_replace('/\s+|-+/', ' ', $t);

  if ($t === 'suz member')                     return 'suz_member';
  if ($t === 'suz non member')                 return 'suz_non_member';
  if ($t === 'vip')                            return 'vip';
  if ($t === 'suz representive' || $t === 'suz representative') return 'suz_representative';

  return get_role('suz_member') ? 'suz_member' : 'subscriber';
}

function suz_roles_for_user(?string $member_type): array {
  $primary = suz_primary_role_from_member_type($member_type);
  $common = ['customer', 'padr']; // your common roles

  $roles = array_values(array_unique(array_merge([$primary], $common)));
  $roles = array_values(array_filter($roles, fn($r) => (bool) get_role($r)));

  // Optional: log missing roles
  // foreach (array_diff(array_unique([$primary, ...$common]), $roles) as $missing) {
  //   error_log("[SUZ Import] Role not found and skipped: {$missing}");
  // }

  return $roles;
}

// Set primary (resets to that one) then add remaining roles
function suz_apply_roles(int $user_id, array $roles): void {
  if (empty($roles)) return;
  $primary = $roles[0];

  wp_update_user(['ID' => $user_id, 'role' => $primary]);

  $u = new WP_User($user_id);
  foreach ($roles as $r) {
    if ($r !== $primary) $u->add_role($r);
  }
}
