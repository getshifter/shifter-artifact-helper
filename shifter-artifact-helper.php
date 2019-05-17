<?php
/*
Plugin Name: Shifter – Artifact Helper
Plugin URI: https://github.com/getshifter/shifter-artifact-helper
Description: Helper tool for building Shifter Artifacts
Version: 1.1.2
Author: Shifter Team
Author URI: https://getshifter.io
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit; // don't access directly
};
if (class_exists('ShifterUrlsBase')) {
    exit; // Prevent duplicate plug-in loading
}

define('SHIFTER_REST_ENDPOINT', 'shifter/v1');

// Shifter URLs
require_once __DIR__.'/include/admin.php';
require_once __DIR__.'/include/api.php';
require_once __DIR__.'/include/class-shifter-urls-base.php';
require_once __DIR__.'/include/class-shifter-onelogin.php';
require_once __DIR__.'/include/magic_login.php';
require_once __DIR__.'/include/relative_path.php';
require_once __DIR__.'/include/remove_meta_tags.php';

/**
 * Shifter URLs v1
 */
add_action(
    'template_redirect',
    function () {
        if (!isset($_GET['urls'])) {
            return;
        }

        try {
            $json_data = shifter_get_urls();

            if ($json_data['count'] <= 0) {
                header("HTTP/1.1 404 Not Found");
            }
            header('Content-Type: application/json');
            echo json_encode($json_data);
            die();
        } catch ( Exception $ex ) {
            error_log($ex->getMessage(), 0);
            die();
        }
    }
);

/**
 * Shifter URLs v2 (WP JSON REST API)
 */
add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            ShifterUrlsBase::REST_ENDPOINT,
            ShifterUrlsBase::REST_PATH,
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_urls_for_rest_api'
            ]
        );
        register_rest_route(
            ShifterUrlsBase::REST_ENDPOINT,
            ShifterUrlsBase::REST_PATH.'/(?P<path>.+)',
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_urls_for_rest_api'
            ]
        );
        register_rest_route(
            ShifterOneLogin::REST_ENDPOINT,
            ShifterOneLogin::REST_PATH,
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_one_login'
            ]
        );
        register_rest_route(
            ShifterOneLogin::REST_ENDPOINT,
            ShifterOneLogin::REST_PATH.'/(?P<username>.+)',
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => 'shifter_one_login'
            ]
        );
        register_rest_route(
            ShifterOneLogin::REST_ENDPOINT,
            ShifterOneLogin::REST_PATH.'/(?P<action>.+)/(?P<username>.+)',
            [
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => 'shifter_one_login'
            ]
        );
    }
);

/**
 * Callback function for WP JSON REST API
 *
 * @param array $data
 *
 * @return object
 */
function shifter_urls_for_rest_api($data=[])
{
    if (!defined('SHIFTER_REST_REQUEST')) {
        define('SHIFTER_REST_REQUEST', true);
    }

    $json_data = [];
    $status = 200;
    $error_msg = '';

    try {
        $request_path = trailingslashit('/' . (isset($data['path']) && !empty($data['path']) ? $data['path'] : ''));
        $json_data = shifter_get_urls($request_path, true);
        $json_data['page']++;
    } catch ( Exception $ex ) {
        $error_msg = $ex->getMessage();
        $json_data = [
            "status" => 500,
            "message" => $error_msg,
        ];
        $status = 500;
    }

    $response = new WP_REST_Response($json_data);
    $response->set_status($status);

    if (500 !== $status) {
        return $response;
    } else {
        error_log($error_msg, 0);
        return $response;
    }
}

/**
 * Callback function for WP JSON REST API
 *
 * @param array $data
 *
 * @return object
 */
function shifter_one_login($data=[])
{
    if (!defined('SHIFTER_REST_REQUEST')) {
        define('SHIFTER_REST_REQUEST', true);
    }
    if (!isset($_SERVER['SHIFTER_LOGIN_TOKEN'])) {
        $_SERVER['SHIFTER_LOGIN_TOKEN'] = 'NONE';
    }

    $json_data = [];
    $status = 401;
    $error_msg = '';
    $user_info = false;

    try {
        $request_method = strtoupper(sanitize_key(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET'));
        $token = sanitize_key(isset($_REQUEST['token']) ? $_REQUEST['token'] : '');
        $action = sanitize_key(isset($data['action']) && !empty($data['action']) ? $data['action'] : 'get');
        $username = sanitize_user(isset($data['username']) && !empty($data['username']) ? $data['username'] : '');
        $email = sanitize_email(isset($_REQUEST['email']) ? $_REQUEST['email'] : '');
        $json_data['user'] = [
            'user_login' => $username,
            'user_email' => $email,
        ];

        if (ShifterOneLogin::chk_token($token)) {
            $user = ShifterOneLogin::get_user($username, $email);
            $json_data['action'] = $action;

            switch ($action) {
            case 'get':
                if ($user) {
                    $username = $user->user_login;
                    $user_info = [
                        'ID' => $user->ID,
                        'user_login' => $username,
                        'user_email' => $user->user_email,
                        'role' => $user->roles,
                    ];
                    $status = 200;
                } else {
                    $status = 404;
                }
                break;
            case 'create':
            case 'update':
                if ('POST' === $request_method) {
                    $user = ShifterOneLogin::get_user($username, $email);
                    $role = sanitize_key(isset($_POST['role']) ? $_POST['role'] : 'administrator');
                    if ($user) {
                        $username = $user->user_login;
                        $user_info = ShifterOneLogin::update_user($username, $email, $role);
                    } else if ('create' === $action) {
                        $user_info = ShifterOneLogin::create_user($username, $email, $role);
                    } else {
                        $status = 404;
                    }
                }
                if ($user_info) {
                    $status = 200;
                }
                break;
            default:
                $status = 401;
                break;
            }
        }

        if (401 === $status) {
            $json_data['code'] = 'not_allowed';
            $json_data['message'] = "Sorry, you are not allowed to do that.";
        } else if (404 === $status) {
            $json_data['code'] = 'not_found';
            $json_data['message'] = "That user doesn't exist.";
        } else if (200 === $status) {
            $json_data['code'] = 'OK';
            $json_data['user'] = $user_info;
            $json_data['loginUrl'] = ShifterOneLogin::magic_link($user_info['user_login'], $user_info['user_email']);
        }
    } catch ( Exception $ex ) {
        $error_msg = $ex->getMessage();
        $json_data = [
            "status" => 500,
            "message" => $error_msg,
        ];
        $status = 500;
    }

    $response = new WP_REST_Response($json_data);
    $response->set_status($status);

    if (500 !== $status) {
        return $response;
    } else {
        error_log($error_msg, 0);
        return $response;
    }
}


/**
 * Remove /index.php/ from Permalink
 */
add_filter('got_rewrite', '__return_true');

/**
 * Create shifter_404.html
 */
add_action(
    'template_redirect',
    function () {
        if (!isset($_GET['urls'])) {
            $request_uri  = ShifterUrlsBase::link_normalize(
                isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''
            );
            if (preg_match('#^/'.preg_quote(ShifterUrlsBase::PATH_404_HTML).'/?$#i', $request_uri)) {
                header("HTTP/1.1 404 Not Found");
                $overridden_template = locate_template('404.php');
                if (!file_exists($overridden_template)) {
                    $overridden_template = locate_template('index.php');
                }
                load_template($overridden_template);
                die();
            } else {
                return;
            }
        }
    },
    1
);
