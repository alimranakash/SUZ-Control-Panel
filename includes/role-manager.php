<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register custom user roles for SUZ.
 */
function suz_register_custom_roles() {
    add_role(
        'suz_member',
        __( 'SUZ Member', 'suz' ),
        array(
            'read' => true,
            // Add more capabilities as needed
        )
    );

    add_role(
        'suz_non_member',
        __( 'SUZ Non-Member', 'suz' ),
        array(
            'read' => true,
        )
    );

    add_role(
        'suz_representative',
        __( 'SUZ Representative', 'suz' ),
        array(
            'read' => true,
            // Extra capabilities for representative if any
        )
    );

    add_role(
        'vip',
        __( 'VIP', 'suz' ),
        array(
            'read' => true,
            // VIP capabilities
        )
    );
}
add_action( 'init', 'suz_register_custom_roles' );

/**
 * Remove SUZ custom roles (optional, e.g. on plugin deactivation)
 */
function suz_remove_custom_roles() {
    remove_role( 'suz_member' );
    remove_role( 'suz_non_member' );
    remove_role( 'suz_representative' );
    remove_role( 'vip' );
}

/**
 * Reset user roles: remove all SUZ-related roles and assign a new one.
 *
 * @param WP_User|int $user User object or ID.
 * @param string $new_role Role to assign.
 * @return void
 */
function suz_set_user_role( $user, $new_role ) {
    if ( is_int( $user ) ) {
        $user = get_userdata( $user );
    }
    if ( ! $user ) {
        return;
    }

    $roles_to_remove = array( 'suz_member', 'suz_non_member', 'suz_representative', 'vip' );
    foreach ( $roles_to_remove as $role ) {
        if ( in_array( $role, $user->roles, true ) ) {
            $user->remove_role( $role );
        }
    }
    $user->add_role( $new_role );
}

/**
 * Helper: Get all SUZ roles as an array.
 * Useful for UI or validations.
 *
 * @return array
 */
function suz_get_custom_roles() {
    return array(
        'suz_member'       => __( 'SUZ Member', 'suz' ),
        'suz_non_member'   => __( 'SUZ Non-Member', 'suz' ),
        'suz_representative' => __( 'SUZ Representative', 'suz' ),
        'vip'              => __( 'VIP', 'suz' ),
    );
}