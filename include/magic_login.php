<?php
if (!defined('ABSPATH')) {
    exit; // don't access directly
};

/**
 * Magik Login
 */
add_action(
    'init',
    function () {
        if (isset($_GET['token']) && isset($_GET['uid']) && isset($_GET['nonce'])) {
            try {
                $user_id = (int)$_GET['uid'];
                $token = sanitize_key($_GET['token']);
                $nonce = sanitize_key($_GET['nonce']);
                $redirect_link = home_url('/wp-admin/');
                if (!is_user_logged_in()) {
                    if (!ShifterOneLogin::chk_login_param($user_id, $token, $nonce)) {
                        wp_logout();
                    } else {
                        wp_set_auth_cookie($user_id);
                    }
                    $redirect_link = ShifterOneLogin::current_page_url();
                } else {
                    $user = wp_get_current_user();
                    if ($user_id !== $user->ID) {
                        wp_logout();
                        $url_params = [
                            'uid' => $user_id,
                            'token' => $token,
                            'nonce' => $nonce,
                        ];
                        $redirect_link = add_query_arg($url_params, $redirect_link);
                    }
                }
                wp_redirect($redirect_link);
                exit;
            } catch ( Exception $ex ) {
                $error_msg = $ex->getMessage();
                error_log($error_msg, 0);
                wp_die($error_msg, 'Shifter Login');
            }
        }
        return;
    }
);

// Shifter customize

/**
 * Add Magic link
 */
add_filter(
    'manage_users_columns',
    function ($columns) {
        $columns['magic_link'] = 'Magic link';
        return $columns;
    }
);

add_filter(
    'manage_users_custom_column',
    function ($dummy, $column, $user_id) {
        if ($column == 'magic_link') {
            $user_info = get_userdata($user_id);
            $magik_link = ShifterOneLogin::magic_link($user_info->user_login);
            $res = '
<script>
function doCopy'.$user_info->ID.'(txt){
    var ta = document.createElement("textarea");
    document.getElementsByTagName("body")[0].appendChild(ta);
    ta.value=txt; ta.select();
    var ret = document.execCommand("copy");
    ta.parentNode.removeChild(ta);
    alert("Copied the '.$user_info->user_login.'\'s magic link");
}
</script>';
            $res .= '<a href="'.$magik_link.'">Magic Link</a><br/>';
            $res .= '<input class="button" type="submit" value="copy" onclick="doCopy'.$user_info->ID.'(\''.$magik_link.'\')">';
            return $res;
        }
    },
    10,
    3
);
