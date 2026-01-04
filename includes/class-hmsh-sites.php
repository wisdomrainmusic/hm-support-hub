<?php
if (!defined('ABSPATH')) exit;

class HMSH_Sites {
    const OPTION_KEY = 'hmsh_sites_registry';

    public static function get_all() {
        $data = get_option(self::OPTION_KEY, array());
        return is_array($data) ? $data : array();
    }

    public static function save_all($data) {
        if (!is_array($data)) $data = array();
        update_option(self::OPTION_KEY, $data, false);
    }

    public static function find_by_url($site_url) {
        $site_url = self::norm_url($site_url);
        foreach (self::get_all() as $row) {
            if (!empty($row['site_url']) && self::norm_url($row['site_url']) === $site_url) {
                return $row;
            }
        }
        return null;
    }

    public static function find_by_site_id($site_id) {
        $site_id = trim((string)$site_id);
        foreach (self::get_all() as $row) {
            if (!empty($row['site_id']) && (string)$row['site_id'] === $site_id) {
                return $row;
            }
        }
        return null;
    }

    public static function upsert($site_name, $site_url, $site_id = '') {
        $all = self::get_all();

        $site_url_n = self::norm_url($site_url);

        // if already exists by url, keep same ids/keys
        foreach ($all as $idx => $row) {
            if (!empty($row['site_url']) && self::norm_url($row['site_url']) === $site_url_n) {
                // update name maybe
                $all[$idx]['site_name'] = $site_name;
                $all[$idx]['site_url']  = $site_url;
                self::save_all($all);
                return $all[$idx];
            }
        }

        if (empty($site_id)) {
            $site_id = self::generate_site_id($all);
        }

        $api_key = self::generate_api_key();

        $row = array(
            'site_id' => $site_id,
            'site_name' => $site_name,
            'site_url' => $site_url,
            'api_key' => $api_key,
            'created_at' => time(),
        );

        $all[] = $row;
        self::save_all($all);

        return $row;
    }

    public static function generate_site_id($all) {
        // HMZP-1001 style
        $max = 1000;
        foreach ($all as $row) {
            if (!empty($row['site_id']) && preg_match('/HMZP-(\d+)/', (string)$row['site_id'], $m)) {
                $n = intval($m[1]);
                if ($n > $max) $max = $n;
            }
        }
        return 'HMZP-' . ($max + 1);
    }

    public static function generate_api_key() {
        return 'hm_site_' . wp_generate_password(32, false, false);
    }

    public static function norm_url($url) {
        $url = trim((string)$url);
        $url = rtrim($url, "/ \t\n\r\0\x0B");
        return strtolower($url);
    }
}
