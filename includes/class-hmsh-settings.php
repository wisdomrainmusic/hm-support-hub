<?php

if (!defined('ABSPATH')) {
    exit;
}

class HMSH_Settings
{
    const OPTION_KEY = 'hmsh_settings';

    /**
     * Register hooks.
     */
    public static function init()
    {
        add_action('admin_init', [__CLASS__, 'register']);
    }

    /**
     * Default option values.
     *
     * @return array
     */
    public static function defaults()
    {
        return [
            // Güvenlik: şimdilik tek ortak key. Sonraki committe site bazlı otomatik üretime geçeceğiz.
            'shared_api_key' => '',

            // Bildirim alıcıları
            'notify_recipients' => 'destek@hizlimagazapro.com,alisverissepetiniz4601@gmail.com',

            // Gönderici bilgisi (SMTP FluentSMTP yönetecek, biz wp_mail header set edeceğiz)
            'from_name'  => 'Hızlı Mağaza Pro',
            'from_email' => 'destek@hizlimagazapro.com',
        ];
    }

    /**
     * Retrieve merged settings.
     *
     * @return array
     */
    public static function get()
    {
        $saved = get_option(self::OPTION_KEY, []);

        return wp_parse_args($saved, self::defaults());
    }

    /**
     * Register the option with sanitization.
     */
    public static function register()
    {
        register_setting(
            'hmsh_settings_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize'],
                'default'           => self::defaults(),
            ]
        );
    }

    /**
     * Sanitize settings input.
     *
     * @param array $input Input values.
     *
     * @return array
     */
    public static function sanitize($input)
    {
        $out = self::defaults();

        $out['shared_api_key'] = isset($input['shared_api_key'])
            ? sanitize_text_field(trim($input['shared_api_key']))
            : '';

        $out['notify_recipients'] = isset($input['notify_recipients'])
            ? sanitize_text_field(trim($input['notify_recipients']))
            : $out['notify_recipients'];

        $out['from_name'] = isset($input['from_name'])
            ? sanitize_text_field(trim($input['from_name']))
            : $out['from_name'];

        $out['from_email'] = isset($input['from_email'])
            ? sanitize_email(trim($input['from_email']))
            : $out['from_email'];

        return $out;
    }

    /**
     * Convert recipients into a clean array.
     *
     * @return array
     */
    public static function recipients_array()
    {
        $settings = self::get();
        $raw = isset($settings['notify_recipients']) ? $settings['notify_recipients'] : '';
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $emails = [];

        foreach ($parts as $part) {
            $email = sanitize_email($part);
            if (!empty($email)) {
                $emails[] = $email;
            }
        }

        // fallback
        if (empty($emails)) {
            $emails = ['destek@hizlimagazapro.com', 'alisverissepetiniz4601@gmail.com'];
        }

        return array_values(array_unique($emails));
    }
}
