<?php
class ShifterOneLogin
{
    private $_var = [
        'access_token' => '',
    ];

    static $instance;

    const REST_ENDPOINT = 'shifter/v1';
    const REST_PATH     = '/user';

    const ENV_TOKEN     = 'SHIFTER_LOGIN_TOKEN';
    const ONETIME_TOKEN_KEY = 'shifter_onetime_token_';
    const ONETIME_TOKEN_EXPIRATION = 3 * HOUR_IN_SECONDS;

    /**
     * Constructor
     *
     * @return nothing
     */
    private function __construct()
    {
    }

    /**
     * Get self instance
     *
     * @return object
     */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }

        return self::$instance;
    }

    private static function get_token()
    {
        return isset($_SERVER[self::ENV_TOKEN])
            ? $_SERVER[self::ENV_TOKEN]
            : 'NONE';
    }

    public static function chk_token($token)
    {
        return $token === self::get_token()
            && $token !== 'NONE';
    }

    private static function get_nonce_action($username)
    {
        return self::REST_ENDPOINT.self::REST_PATH.'/'.$username;
    }

    private static function create_nonce($username)
    {
        return wp_create_nonce(self::get_nonce_action($username));
    }

    private static function chk_onetime_token($onetime_token, $user_id = 0)
    {
        return $onetime_token === self::onetime_token($user_id);
    }

    private static function create_passwd($length = 20)
    {
        require_once ABSPATH.'wp-includes/class-phpass.php';
        return wp_generate_password($length, false);
    }

    private static function onetime_token($user_id = 0)
    {
        $action = self::ONETIME_TOKEN_KEY.$user_id;
        if ( false === ( $token = get_transient($action) ) ) {
            $time = time();
            $key = self::create_passwd();
            $token  = wp_hash($key.$action.$time);
            $expiration = self::ONETIME_TOKEN_EXPIRATION;
            set_transient($action, $token, $expiration);
        }
        return $token;
    }

    public static function chk_login_param($user_id, $token, $nonce)
    {
        $user_info = get_userdata($user_id);
        return $user_info && !is_wp_error($user_info)
            && self::chk_onetime_token($token, $user_id)
            && wp_verify_nonce($nonce, self::get_nonce_action($user_info->user_login));
    }

    public static function current_page_url() {
        $req_uri = $_SERVER['REQUEST_URI'];
        $home_path = trim( parse_url( home_url(), PHP_URL_PATH ), '/' );
        $home_path_regex = sprintf( '|^%s|i', preg_quote( $home_path, '|' ) );
        $req_uri = ltrim($req_uri, '/');
        $req_uri = preg_replace( $home_path_regex, '', $req_uri );
        $req_uri = trim(home_url(), '/') . '/' . ltrim( $req_uri, '/' );
        return remove_query_arg(['uid', 'token', 'nonce'], $req_uri);
    }

    public static function get_user_by_loginname($username)
    {
        $user = get_user_by('login', $username);
        if ($user && !is_wp_error($user)) {
            return $user;
        } else {
            return false;
        }
    }

    public static function magic_link($username)
    {
        $magic_link = home_url('/wp-admin/');
        $url_params = [];
        if ($user = self::get_user_by_loginname($username)) {
            $url_params = [
                'uid' => $user->ID,
                'token' => self::onetime_token($user->ID),
                'nonce' => self::create_nonce($username),
            ];
        }
        $magic_link = add_query_arg($url_params, $magic_link);
        return $magic_link;
    }

    public static function create_user($username, $email, $role)
    {
        $user_pass = self::create_passwd();
        $userdata = array(
            'user_login'  => $username,
            'user_pass'   => $user_pass,
            'role'        => $role,
        );
        if (!empty($email)) {
            $userdata['user_email'] = $email;
        }
        $user_id = wp_insert_user($userdata);
        if ( ! is_wp_error( $user_id ) ) {
            $userdata['ID'] = $user_id;
        } else {
            $userdata = false;
        }
        return $userdata;
    }

    public static function update_user($username, $email, $role)
    {
        $userdata = false;
        if ( $user = self::get_user_by_loginname($username) ) {
            $userdata = array(
                'ID'          => $user->ID,
                'role'        => $role,
            );
            if (!empty($email)) {
                $userdata['user_email'] = $email;
            }
            $user_id = wp_update_user($userdata);
            if ( ! is_wp_error( $user_id ) ) {
                $userdata['user_login'] = $username;
            } else {
                $userdata = false;
            }
        }
        return $userdata;
    }
}