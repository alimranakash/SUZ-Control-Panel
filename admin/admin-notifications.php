<?php
// 1. Track normal user registration
add_action( 'user_register', 'suz_track_new_user', 10, 1 );

// 2. Track new user creation via WooCommerce checkout
add_action( 'woocommerce_created_customer', 'suz_track_new_user', 10, 1 );

function suz_track_new_user( $user_id ) {
    $new_users = get_transient( 'suz_new_user_ids' );
    if ( ! is_array( $new_users ) ) {
        $new_users = [];
    }

    if ( ! in_array( $user_id, $new_users ) ) {
        $new_users[] = $user_id;
        set_transient( 'suz_new_user_ids', $new_users, 60 * 60 ); // Store for 1 hour
    }
}

// 3. Show admin notice only on SUZ Plugin Dashboard page
add_action( 'admin_notices', 'suz_show_new_user_notification' );
function suz_show_new_user_notification() {
    // Check if we are on the SUZ control panel page
    if ( isset($_GET['page']) && $_GET['page'] === 'suz-control-panel' ) {
        $new_users = get_transient( 'suz_new_user_ids' );

        if ( is_array( $new_users ) && ! empty( $new_users ) ) {
            foreach ( $new_users as $user_id ) {
                $user = get_userdata( $user_id );
                if ( $user ) {
                    ?>
                    <div class="notice notice-info is-dismissible">
                        <p>
                            <strong><?php esc_html_e( 'New User Registered:', 'suz-control-panel' ); ?></strong>
                            <?php echo esc_html( $user->user_email ); ?>
                            <?php esc_html_e( 'just registered.', 'suz-control-panel' ); ?>
                            <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user_id ) ); ?>">
                                <?php esc_html_e( 'View Profile', 'suz-control-panel' ); ?>
                            </a>
                        </p>
                    </div>
                    <?php
                }
            }

            // Clear the transient after displaying notices
            delete_transient( 'suz_new_user_ids' );
        }
    }
}