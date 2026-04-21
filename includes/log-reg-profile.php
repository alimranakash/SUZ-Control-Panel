<?php
/**
 * Handle login and logout redirection based on user profile data.
 */

/**
 * Redirect user after login based on profile completeness.
 */
function suz_redirect_after_login_based_on_profile( $redirect_to, $requested_redirect_to, $user ) {
    if ( ! is_a( $user, 'WP_User' ) || ! $user->exists() ) {
        return $redirect_to;
    }

    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        // return $redirect_to;
        return site_url( '/' . esc_html__( 'wp-admin', 'suz' ) . '/' );
    }

    $first_name = get_user_meta( $user->ID, 'first_name', true );

    if ( empty( $first_name ) ) {
        return site_url( '/' . esc_html__( 'profile-completion', 'suz' ) . '/' );
    }

    return site_url( '/' . esc_html__( 'moj-ucet', 'suz' ) . '/' );
}
add_filter( 'login_redirect', 'suz_redirect_after_login_based_on_profile', 10, 3 );

/**
 * Redirect logged-in users away from auth pages
 */
function suz_redirect_logged_in_users_from_auth_pages() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id = get_current_user_id();
    $user    = get_userdata( $user_id );

    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        return;
    }

    $first_name = get_user_meta( $user_id, 'first_name', true );
    if ( empty( $first_name ) ) {
        return;
    }

    $current_path      = trim( $_SERVER['REQUEST_URI'], '/' );
    $restricted_pages  = array(
        esc_html__( 'login', 'suz' ),
        esc_html__( 'registration', 'suz' ),
        esc_html__( 'profile-completion', 'suz' )
    );

    if ( in_array( $current_path, $restricted_pages ) ) {
        wp_safe_redirect( site_url( '/' . esc_html__( 'dashboard', 'suz' ) . '/' ) );
        exit;
    }
}
add_action( 'template_redirect', 'suz_redirect_logged_in_users_from_auth_pages' );

/**
 * Redirect failed login attempt to registration page
 */
function suz_redirect_on_login_failure( $username ) {
    $referrer = wp_get_referer();

    if ( $referrer && strpos( $referrer, '/' . esc_html__( 'prihlasenie', 'suz' ) . '/' ) !== false ) {
        wp_redirect( add_query_arg( 'login', 'failed', $referrer ) );
        exit;
    }
}
add_action( 'wp_login_failed', 'suz_redirect_on_login_failure' );

// also handle empty username/password but only if the referrer was /registration/
add_filter( 'authenticate', function ( $user, $username, $password ) {
    if ( $user instanceof WP_User ) {
        return $user; // success
    }

    // check if empty and came from our custom page
    $referrer = wp_get_referer();
    if ( ( empty( $username ) || empty( $password ) )
         && $referrer
         && strpos( $referrer, '/prihlasenie/' ) !== false ) {

        wp_safe_redirect( add_query_arg( 'login', 'failed', $referrer ) );
        exit;
    }

    return $user;
}, 30, 3 );

/**
 * Show login error notice on default login page
 */
if ( ! function_exists( 'suz_show_login_error_notice' ) ) {
    function suz_show_login_error_notice() {
        if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
            echo '<div class="suz-login-error" style="color: red; font-weight: bold; margin-bottom: 15px;">';
            echo esc_html__( 'Invalid username or password. Please try again.', 'suz' );
            echo '</div>';
        }
    }
    add_action( 'login_form', 'suz_show_login_error_notice' );
}

/**
 * Optional: Show login error notice on custom front-end pages
 */
if ( ! function_exists( 'suz_show_login_error_notice_on_custom_page' ) ) {
    function suz_show_login_error_notice_on_custom_page() {
        if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
            echo '<div class="suz-login-error" style="color: red; font-weight: bold; margin-bottom: 15px;">';
            echo esc_html__( 'Invalid username or password. Please try again.', 'suz' );
            echo '</div>';
        }
    }
    add_action( 'wp_head', 'suz_show_login_error_notice_on_custom_page' );
}

/**
 * Show error notice on front-end /registration/ page built with Elementor.
 */
function suz_show_login_error_notice_on_custom_page() {
    if ( is_page( 'prihlasenie' ) && isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
        $message = __( 'Invalid username or password. Please try again.', 'suz-control-panel' );
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                let form = document.querySelector(".elementor-widget-login");
                var errorMsg = ' . json_encode( $message ) . ';
                if(form){
                    let message = document.createElement("div");
                    message.innerHTML = errorMsg;
                    message.style.color = "red";
                    message.style.fontWeight = "bold";
                    message.style.marginBottom = "15px";
                    form.prepend(message);
                }
            });
        </script>';
    }
}
add_action( 'wp_head', 'suz_show_login_error_notice_on_custom_page' );

/**
 * Redirect to registration page with activated=true if wpforms_activate param exists.
 */
// Add ?activated=true to the WPForms activation redirect
add_action('plugins_loaded', function () {
    // WPForms: after email activation, filter the redirect URL
    add_filter('wpforms_user_registration_activation_redirect_url', function ($url, $user_id = 0, $form_data = [] ) {
        // Send to your custom page with the flag
        return add_query_arg('activated', 'true', home_url('/prihlasenie/'));
    }, 99, 3);
});

/**
 * Show activation success message above Elementor login widget inside .activation-notice container
 */

add_action( 'wp_footer', function () {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $display_name = $current_user->display_name;

        // WooCommerce My Account URL
        $account_url = function_exists( 'wc_get_page_permalink' ) 
            ? wc_get_page_permalink( 'myaccount' ) 
            : home_url();
        $my_acc = esc_html__('My Account', 'suz-control-panel');

        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            let btn = document.querySelector(".header-btn.d-none.d-xl-block");
            if (btn) {
                // Change button text to "My Account (username)"
                let textContainer = btn.querySelector("span, strong, .btn-text") || btn;
                var my_acc = <?php echo json_encode($my_acc); ?>;
                textContainer.textContent = my_acc+" (<?php echo esc_js( $display_name ); ?>)";

                // Apply padding and bold font
                btn.style.padding = "27px";
                btn.style.fontWeight = "bold";

                // Make cursor a pointer to indicate clickability
                btn.style.cursor = "pointer";

                // Make button clickable for both <a> and <button>
                btn.addEventListener("click", function(e) {
                    e.preventDefault();
                    <?php if ( current_user_can( 'administrator' ) ) : ?>
                        // Admin goes to WP Admin
                        window.location.href = "<?php echo admin_url(); ?>";
                    <?php else : ?>
                        // Non-admin goes to My Account page
                        window.location.href = "<?php echo esc_url( $account_url ); ?>";
                    <?php endif; ?>
                });
            }
        });
        </script>
        <?php
    }
});


/**
 * Show activation success message above Elementor login widget inside .activation-notice container
 */

// code added for account activation message by hafij start

// Append ?activated=true ONLY when coming from WPForms activation
add_filter('wp_redirect', function ($location, $status) {

    // 1) Only run if the *current request* has the activation token
    $is_wpforms_activation = !empty($_GET['wpforms_activate']); // e.g. /?wpforms_activate=abc123
    if ( ! $is_wpforms_activation ) {
        return $location; // e.g. normal logout/login redirects won't be touched
    }

    // 2) Only adjust redirects that land on /registration/
    $target        = trailingslashit( home_url('/prihlasenie') );
    $loc_no_query  = strtok( $location, '?' );
    $is_registration_target = rtrim($loc_no_query, '/') === rtrim($target, '/');

    if ( ! $is_registration_target ) {
        return $location;
    }

    // 3) Avoid double-adding the flag
    if ( strpos($location, 'activated=') !== false ) {
        return $location;
    }

    // 4) Add our flag
    $location = add_query_arg( 'activated', 'true', $target );
    return $location;

}, 999, 2);


// Force a one-time activation notice on /registration/?activated=true (or 1/yes).
add_action('wp_head', function () {
    // 1) Only when coming from activation link
    if (!isset($_GET['activated'])) return;
    $flag = strtolower(sanitize_text_field($_GET['activated']));
    if (!in_array($flag, ['true','1','yes'], true)) return;

    // 2) Only on /registration (change slug if needed)
    $req = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
    if (!preg_match('~/(prihlasenie)(/|$|\?)~i', $req)) return;

    // 3) CSS + JS: inject before .activation-notice .elementor-widget-login, then clean URL
    $msg = esc_html__('Account activated successfully. Your login details have been sent to your email. Check & login, happy journey!', 'suz-control-panel');
    ?>
    <style>
      .activation-success {
        margin:20px 0; padding:10px; background:#e6ffed; border:1px solid #28a745;
        color:#155724; text-align:center; border-radius:8px; font-weight:600;
      }
    </style>
    <script>
    (function(){
      var messageText = <?php echo json_encode($msg); ?>;

      function createBanner(){
        var el = document.createElement('div');
        el.className = 'activation-success';
        el.setAttribute('role','alert');
        el.textContent = messageText;
        return el;
      }

      function insertNotice(){
        // Your requested selector:
        let formContainer = document.querySelector(".activation-notice .elementor-widget-login");
        if (!formContainer) return false;

        var banner = createBanner();
        // insert banner right before the login widget
        formContainer.parentNode.insertBefore(banner, formContainer);
        return true;
      }

      function cleanURL(){
        try {
          var url = new URL(window.location.href);
          if (url.searchParams.has('activated')) {
            url.searchParams.delete('activated');
            var qs = url.searchParams.toString();
            var clean = url.pathname + (qs ? '?' + qs : '') + url.hash;
            history.replaceState({}, '', clean);
          }
        } catch(e){}
      }

      function run(){
        if (insertNotice()) { cleanURL(); return; }

        // Elementor may render asynchronously: observe for up to ~5s
        var tries = 0;
        var iv = setInterval(function(){
          tries++;
          if (insertNotice() || tries > 50) { clearInterval(iv); cleanURL(); }
        }, 100);

        var obs = new MutationObserver(function(){
          if (insertNotice()) { obs.disconnect(); clearInterval(iv); cleanURL(); }
        });
        obs.observe(document.documentElement, {childList:true, subtree:true});
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
      } else {
        run();
      }
    })();
    </script>
    <?php
}, 1);

// code added for account activation message by hafij end
