<?php

if (!defined('ABSPATH')) {
    exit;
}

class HMSH_Mailer
{
    /**
     * Send a notification email when a ticket is created.
     *
     * @param int   $ticket_id Ticket post ID.
     * @param array $data      Ticket payload.
     */
    public static function send_ticket_notification($ticket_id, $data)
    {
        $settings = HMSH_Settings::get();
        $recipients = HMSH_Settings::recipients_array();

        if (empty($recipients)) {
            return;
        }

        $site_name = isset($data['client_site_name']) ? sanitize_text_field($data['client_site_name']) : '';
        $subject = sprintf('[HM Destek] Yeni Talep (%s)', $site_name ?: 'Bilinmeyen Site');

        $from_name = $settings['from_name'];
        $from_email = $settings['from_email'];
        $headers = [];

        if (!empty($from_name) && !empty($from_email)) {
            $headers[] = 'From: ' . sanitize_text_field($from_name) . ' <' . sanitize_email($from_email) . '>';
        }

        $message_parts = [
            'Yeni bir destek talebi oluşturuldu.',
            '',
            'Site ID: ' . (isset($data['site_id']) ? sanitize_text_field($data['site_id']) : ''),
            'Site URL: ' . (isset($data['client_site_url']) ? esc_url_raw($data['client_site_url']) : ''),
            'Site Adı: ' . $site_name,
            'Müşteri Email: ' . (isset($data['customer_email']) ? sanitize_email($data['customer_email']) : ''),
            'Telefon: ' . (isset($data['customer_phone']) ? sanitize_text_field($data['customer_phone']) : ''),
            'Öncelik: ' . (isset($data['urgency']) ? sanitize_text_field($data['urgency']) : ''),
            'Durum: ' . (isset($data['status']) ? sanitize_text_field($data['status']) : ''),
            'Kayıt ID: ' . $ticket_id,
            '',
            'Konu: ' . (isset($data['subject']) ? sanitize_text_field($data['subject']) : ''),
            '',
            'Mesaj:',
            wp_strip_all_tags(isset($data['message']) ? $data['message'] : ''),
        ];

        wp_mail($recipients, $subject, implode("\n", $message_parts), $headers);
    }
}
