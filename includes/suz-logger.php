<?php
/**
 * Write log entry to file
 */
function suz_log_action( $message, $user_id = null ) {
    $time = current_time( 'mysql' );
    $user = $user_id ? sprintf( __( 'User #%d', 'suz-control-panel' ), $user_id ) : __( 'System', 'suz-control-panel' );
    $entry = "[{$time}] {$user}: {$message}\n";

    $log_dir  = plugin_dir_path( __FILE__ ) . '../logs/';
    $log_file = $log_dir . 'suz-log.txt';

    if ( ! file_exists( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
    }

    file_put_contents( $log_file, $entry, FILE_APPEND );
}

/**
 * Read the log contents
 */
function suz_get_log() {
    $log_file = plugin_dir_path( __FILE__ ) . '../logs/suz-log.txt';
    return file_exists( $log_file ) ? file_get_contents( $log_file ) : '';
}

/**
 * Clear the log file
 */
function suz_clear_log() {
    $log_file = plugin_dir_path( __FILE__ ) . '../logs/suz-log.txt';
    file_put_contents( $log_file, '' );
}

/**
 * Hook to log user role changes
 */
add_action( 'set_user_role', function( $user_id, $role, $old_roles ) {
    $old_role = is_array( $old_roles ) && ! empty( $old_roles ) ? implode( ',', $old_roles ) : __( 'none', 'suz-control-panel' );
    $message = sprintf( __( 'User role changed from [%s] to [%s]', 'suz-control-panel' ), $old_role, $role );
    suz_log_action( $message, $user_id );
}, 10, 3 );

/**
 * Hook to log user profile updates
 */
add_action( 'profile_update', function( $user_id, $old_user_data ) {
    $message = __( 'User profile updated.', 'suz-control-panel' );
    suz_log_action( $message, $user_id );
}, 10, 2 );

/**
 * Hook to log new user registration
 */
add_action( 'user_register', function( $user_id ) {
    $message = __( 'New user registered.', 'suz-control-panel' );
    suz_log_action( $message, $user_id );
});
