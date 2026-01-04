<?php
/**
 * Plugin Name: HM Support Hub
 * Description: HM Support merkez eklentisi. Müşteri sitelerinden gelen destek taleplerini tek panelde toplar.
 * Version: 1.0.0
 * Author: Hızlı Mağaza Pro
 * Text Domain: hm-support-hub
 */

if (!defined('ABSPATH')) exit;

define('HMSH_VERSION', '1.0.0');
define('HMSH_PATH', plugin_dir_path(__FILE__));
define('HMSH_URL', plugin_dir_url(__FILE__));

require_once HMSH_PATH . 'includes/class-hmsh-plugin.php';

add_action('plugins_loaded', function () {
    HMSH_Plugin::instance();
});
