<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access for security
}

/**
 * Render the main Dashboard page
 */
function suz_panel_render_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'SUZ Control Panel Dashboard', 'suz-control-panel' ); ?></h1>
        <p><?php esc_html_e( 'Welcome to the central control panel for the SUZ system.', 'suz-control-panel' ); ?></p>
        
        <div style="display:flex; gap:20px; margin-top:30px; flex-wrap: wrap;">
            
            <!-- BOX 1: Eventin Management Links -->
            <div style="flex:1; min-width:300px; padding:20px; background:#f0f8ff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                <h2>🎟️ <?php esc_html_e( 'Eventin Management', 'suz-control-panel' ); ?></h2>
                <p><?php esc_html_e( 'Quick access to Eventin system pages:', 'suz-control-panel' ); ?></p>
                <ol style="margin-left:20px;">
                    <li><a href="<?php echo esc_url( admin_url('admin.php?page=eventin#/dashboard') ); ?>"><?php esc_html_e( 'Eventin Dashboard', 'suz-control-panel' ); ?></a></li>
                    <li><a href="<?php echo esc_url( admin_url('edit.php?post_type=etn') ); ?>"><?php esc_html_e( 'All Events', 'suz-control-panel' ); ?></a></li>
                    <li><a href="<?php echo esc_url( admin_url('admin.php?page=eventin#/speakers') ); ?>"><?php esc_html_e( 'Organizers', 'suz-control-panel' ); ?></a></li>
                    <li><a href="<?php echo esc_url( admin_url('admin.php?page=eventin#/purchase-report') ); ?>"><?php esc_html_e( 'Bookings', 'suz-control-panel' ); ?></a></li>
                    <li><a href="<?php echo esc_url( admin_url('admin.php?page=eventin#/schedules') ); ?>"><?php esc_html_e( 'Schedules', 'suz-control-panel' ); ?></a></li>
                </ol>
            </div>

            <!-- BOX 2: System Stats -->
            <div style="flex:1; min-width:300px; padding:20px; background:#e6ffe6; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                <h2>📊 <?php esc_html_e( 'System Stats', 'suz-control-panel' ); ?></h2>
                <ul style="list-style-type: square; margin-left:20px;">
                    <li><strong><?php esc_html_e( 'Total Registered Users:', 'suz-control-panel' ); ?></strong> 
                        <?php echo number_format_i18n( count_users()['total_users'] ); ?>
                    </li>
                    <li><strong><?php esc_html_e( 'Verified IČOs:', 'suz-control-panel' ); ?></strong> 
                        <span id="verified-count-placeholder"><?php esc_html_e('Calculating...', 'suz-control-panel'); ?></span>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=suz-ico-verification-tracker') ); ?>" 
                           title="<?php esc_attr_e( 'View Verified IČOs', 'suz-control-panel' ); ?>"
                           style="margin-left:10px; display:inline-block; padding:4px 8px; border-radius:50%; background:#fff; border:2px solid; border-image:linear-gradient(45deg,#6dd5ed,#2193b0) 1; color:#2193b0; font-weight:bold; text-decoration:none; box-shadow:0 2px 5px rgba(0,0,0,0.1); transition:all 0.3s ease;">
                            🔍
                        </a>
                    </li>
                    <li><strong><?php esc_html_e( 'Mailchimp Sync Status:', 'suz-control-panel' ); ?></strong> 
                        <span style="color:green;">🟢 <?php esc_html_e( 'Active', 'suz-control-panel' ); ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <?php
        // Collect users with ICO meta
        $users_with_ico = get_users([
            'meta_key'     => 'company_ico',
            'meta_compare' => 'EXISTS',
            'fields'       => ['ID'],
        ]);

        $ico_usage = [];
        $verified_total = 0;
        $not_verified_total = 0;

        foreach ( $users_with_ico as $user ) {
            $ico = get_user_meta( $user->ID, 'company_ico', true );
            $user_info = get_userdata( $user->ID );

            $ico_key = ! empty( $ico ) ? $ico : '__EMPTY__';

            if ( ! isset( $ico_usage[ $ico_key ] ) ) {
                $ico_usage[ $ico_key ] = [
                    'count'    => 0,
                    'verified' => ! empty( $ico ) ? suz_check_ico_in_member_list( $ico ) : false,
                    'users'    => [],
                ];
            }

            $ico_usage[ $ico_key ]['count']++;
            $ico_usage[ $ico_key ]['users'][] = [
                'name'  => $user_info->display_name,
                'email' => $user_info->user_email,
            ];
        }

        // Count verified vs not verified
        foreach ( $ico_usage as $data ) {
            if ( $data['verified'] ) {
                $verified_total++;
            } else {
                $not_verified_total++;
            }
        }
        ?>

        <!-- BOX 3: IČO Usage Summary -->
        <div style="margin-top:30px; padding:20px; background:#fff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
            <h2>🏢 <?php esc_html_e( 'IČO Usage Summary', 'suz-control-panel' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'IČO', 'suz-control-panel' ); ?></th>
                        <th><?php esc_html_e( 'Used By (User Count)', 'suz-control-panel' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'suz-control-panel' ); ?></th>
                        <th><?php esc_html_e( 'Users (Name & Email)', 'suz-control-panel' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $ico_usage ) ) : ?>
                        <?php foreach ( $ico_usage as $ico => $data ) : ?>
                            <tr>
                                <td>
                                    <?php 
                                    echo $ico === '__EMPTY__' 
                                        ? '<em>' . esc_html__( '(Not Provided)', 'suz-control-panel' ) . '</em>' 
                                        : esc_html( $ico ); 
                                    ?>
                                </td>
                                <td><?php echo intval( $data['count'] ); ?></td>
                                <td>
                                    <?php 
                                    echo $data['verified'] 
                                        ? '<span style="color:green;">✔ ' . esc_html__( 'Verified', 'suz-control-panel' ) . '</span>' 
                                        : '<span style="color:red;">✘ ' . esc_html__( 'Not Verified', 'suz-control-panel' ) . '</span>'; 
                                    ?>
                                </td>
                                <td>
                                    <ul style="margin:0; padding-left:20px;">
                                        <?php foreach ( $data['users'] as $user ) : ?>
                                            <li><?php echo esc_html( $user['name'] ) . ' (' . esc_html( $user['email'] ) . ')'; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'No IČO data found.', 'suz-control-panel' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
            // Update Verified count dynamically
            document.getElementById('verified-count-placeholder').textContent = '<?php echo number_format_i18n( $verified_total ); ?>';
        </script>
    </div>
    <?php
}

/**
 * Render the SUZ Settings page
 */
function suz_panel_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'SUZ Settings', 'suz-control-panel' ); ?></h1>
        <p><?php esc_html_e( 'Add your custom settings and features here.', 'suz-control-panel' ); ?></p>
    </div>
    <?php
}

/**
 * Render the Important Links page
 */
function suz_panel_render_important_links_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Important Links', 'suz-control-panel' ); ?></h1>
        <p><?php esc_html_e( 'This page is reserved for important links. Content can be added later.', 'suz-control-panel' ); ?></p>
    </div>
    <?php
}

/**
 * Render the Debug Logs page with option to clear logs
 */
function suz_panel_render_debug_logs_page() {
    if ( isset( $_POST['clear_log'] ) && check_admin_referer( 'suz_clear_logs' ) ) {
        suz_clear_log();
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs cleared successfully.', 'suz-control-panel' ) . '</p></div>';
    }

    $log_content = suz_get_log();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Debug Logs', 'suz-control-panel' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'suz_clear_logs' ); ?>
            <textarea readonly rows="20" style="width:100%;"><?php echo esc_textarea( $log_content ); ?></textarea>
            <p>
                <button type="submit" name="clear_log" class="button button-secondary">
                    <?php esc_html_e( 'Clear Logs', 'suz-control-panel' ); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Render the IČO Verification Tracker admin page with search and pagination
 */
function suz_panel_render_ico_verification_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'suz-control-panel' ) );
    }

    // ===== Parameters =====
    $search_query = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
    $paged = isset( $_GET['paged'] ) ? max(1, intval($_GET['paged'])) : 1;
    $users_per_page = 25; 

    // ===== Fetch Users =====
    if ( ! empty( $search_query ) ) {
        // Search by email
        $email_args = [
            'search'         => '*' . $search_query . '*',
            'search_columns' => ['user_email'],
            'meta_key'       => 'company_ico',
            'meta_compare'   => 'EXISTS',
            'number'         => 1000, // large enough for merge
        ];
        $email_users = (new WP_User_Query($email_args))->get_results();

        // Search by IČO
        $ico_args = [
            'meta_key'     => 'company_ico',
            'meta_value'   => $search_query,
            'meta_compare' => 'LIKE',
            'number'       => 1000,
        ];
        $ico_users = (new WP_User_Query($ico_args))->get_results();

        // Merge & remove duplicates
        $all_users = [];
        foreach(array_merge($email_users, $ico_users) as $user){
            $all_users[$user->ID] = $user;
        }
        $all_users = array_values($all_users);
    } else {
        // No search, get all users with company_ico
        $args = [
            'meta_key'     => 'company_ico',
            'meta_compare' => 'EXISTS',
            'number'       => 1000, // large enough for slice
        ];
        $user_query = new WP_User_Query($args);
        $all_users = $user_query->get_results();
    }

    // ===== Pagination Logic =====
    $total_users = count($all_users);
    $total_pages = ceil($total_users / $users_per_page);
    $users = array_slice($all_users, ($paged-1)*$users_per_page, $users_per_page);

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'IČO Verification Tracker', 'suz-control-panel' ); ?></h1>

        <!-- ===== Search Panel ===== -->
        <div style="margin:20px auto; padding:15px; background:#f9f9f9; border:1px solid #ddd; max-width:600px; text-align:center;">
            <form method="get" action="" style="display:flex; gap:10px; justify-content:center; align-items:center;">
                <input type="hidden" name="page" value="suz-ico-verification-tracker">
                <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search by Email or IČo', 'suz-control-panel'); ?>" style="flex:1; min-width:200px; padding:5px;">
                <button class="button button-primary"><?php esc_html_e('Search', 'suz-control-panel'); ?></button>
            </form>
        </div>

        <!-- ===== Users Table ===== -->
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'User', 'suz-control-panel' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'suz-control-panel' ); ?></th>
                    <th><?php esc_html_e( 'IČO', 'suz-control-panel' ); ?></th>
                    <th><?php esc_html_e( 'Role', 'suz-control-panel' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'suz-control-panel' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'suz-control-panel' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( ! empty($users) ) : ?>
                <?php foreach ($users as $user) :
                    $user_id = $user->ID;
                    $user_info = get_userdata($user_id);
                    $ico = get_user_meta($user_id, 'company_ico', true);
                    $roles = implode(', ', $user_info->roles);
                    $ico_verified = function_exists('suz_check_ico_in_member_list') ? suz_check_ico_in_member_list($ico) : false;
                ?>
                    <tr>
                        <td><?php echo esc_html($user_info->display_name); ?></td>
                        <td><?php echo esc_html($user_info->user_email); ?></td>
                        <td><?php echo esc_html($ico); ?></td>
                        <td><?php echo esc_html($roles); ?></td>
                        <td>
                            <?php if ($ico_verified) : ?>
                                <span style="color:green;">✔ <?php esc_html_e('Verified', 'suz-control-panel'); ?></span>
                            <?php else : ?>
                                <span style="color:red;">✘ <?php esc_html_e('Not Verified', 'suz-control-panel'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('suz_ico_role_update', 'suz_ico_nonce'); ?>
                                <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
                                <select name="new_role">
                                    <?php foreach ( ['suz_member','suz_non_member','suz_representative','vip'] as $r ) : ?>
                                        <option value="<?php echo esc_attr($r); ?>" <?php selected(in_array($r, $user_info->roles, true)); ?>>
                                            <?php echo esc_html(ucfirst(str_replace('suz_', '', $r))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="submit" name="update_role" class="button button-small" value="<?php esc_attr_e('Update Role','suz-control-panel'); ?>">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="6"><?php esc_html_e('No users found for this search.', 'suz-control-panel'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- ===== Pagination ===== -->
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav"><div class="tablenav-pages">
                <?php
                $prev = max(1, $paged - 1);
                $next = min($total_pages, $paged + 1);

                $prev_url = add_query_arg(['paged' => $prev, 's' => $search_query, 'page' => 'suz-ico-verification-tracker']);
                $next_url = add_query_arg(['paged' => $next, 's' => $search_query, 'page' => 'suz-ico-verification-tracker']);

                echo '<a class="prev-page" href="' . esc_url( $prev_url ) . '">&laquo; ' . esc_html__( 'Previous', 'suz-control-panel' ) . '</a> ';

                for ($i = 1; $i <= $total_pages; $i++) {
                    $class = ($i == $paged) ? 'current' : '';
                    $url = add_query_arg(['paged' => $i, 's' => $search_query, 'page' => 'suz-ico-verification-tracker']);
                    echo '<a class="page-numbers '.$class.'" href="'.esc_url($url).'">'. $i .'</a> ';
                }

                echo '<a class="next-page" href="' . esc_url( $next_url ) . '">' . esc_html__( 'Next', 'suz-control-panel' ) . ' &raquo;</a>';
                ?>
            </div></div>
        <?php endif; ?>

    </div>
    <?php
}

/**
 * Process role update submitted from the IČO Verification Tracker page
 */
add_action( 'admin_init', 'suz_handle_ico_role_update' );
function suz_handle_ico_role_update() {
    if ( ! isset( $_POST['update_role'] ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'suz-control-panel' ) );
    }

    if ( ! isset( $_POST['suz_ico_nonce'] ) || ! wp_verify_nonce( $_POST['suz_ico_nonce'], 'suz_ico_role_update' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'suz-control-panel' ) );
    }

    $user_id = intval( $_POST['user_id'] );
    $new_role = sanitize_text_field( $_POST['new_role'] );

    $user = get_userdata( $user_id );
    if ( $user ) {
        $roles_to_remove = ['suz_member', 'suz_non_member', 'suz_representative', 'vip'];
        foreach ( $roles_to_remove as $role ) {
            if ( in_array( $role, $user->roles ) ) {
                $user->remove_role( $role );
            }
        }

        $user->add_role( $new_role );

        // for role update mailchimp
        if ( function_exists( 'suz_sync_mailchimp_role_tags' ) ) {
            suz_sync_mailchimp_role_tags($user_id, $new_role);
        }

        add_action( 'admin_notices', function() use ( $user_id, $new_role ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    echo esc_html( sprintf(
                        __( 'User #%d role updated to %s successfully.', 'suz-control-panel' ),
                        $user_id,
                        $new_role
                    ) );
                    ?>
                </p>
            </div>
            <?php
        } );
    }
}

/**
 * Process role update submitted from the IČO Verification Tracker page by hafij start
 */

function suz_check_ico_is_verified( $ico ) {
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

    return $count > 0;
}

/**
 * Process role update submitted from the IČO Verification Tracker page by hafij end
 */
