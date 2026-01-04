<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once HMSH_PATH . 'includes/class-hmsh-cpt-ticket.php';
require_once HMSH_PATH . 'includes/class-hmsh-settings.php';
require_once HMSH_PATH . 'includes/class-hmsh-mailer.php';
require_once HMSH_PATH . 'includes/class-hmsh-sites.php';
require_once HMSH_PATH . 'includes/class-hmsh-rest.php';
require_once HMSH_PATH . 'includes/class-hmsh-admin.php';

class HMSH_Plugin
{
    /**
     * Singleton instance.
     *
     * @var HMSH_Plugin|null
     */
    private static $instance = null;

    /**
     * Retrieve the plugin instance.
     *
     * @return HMSH_Plugin
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Hook into WordPress actions.
     */
    private function __construct()
    {
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        HMSH_Ticket_CPT::init();
        HMSH_Settings::init();
        HMSH_REST::init();
        HMSH_Admin::init();
    }

    /**
     * Load plugin translations.
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'hm-support-hub',
            false,
            dirname(plugin_basename(HMSH_PATH . 'hm-support-hub.php')) . '/languages'
        );
    }

    /**
     * Register admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets($hook)
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_plugin_screen = (
            $screen && strpos((string) $screen->id, 'hmsh-') === 0
        );

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($is_plugin_screen || strpos($page, 'hmsh-') === 0) {
            wp_enqueue_style('hmsh-admin', HMSH_URL . 'assets/admin.css', [], HMSH_VERSION);
        }
    }
}
