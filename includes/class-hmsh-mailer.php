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

    public static function send_customer_reply($ticket_post_id, $reply_message)
    {
        $s = HMSH_Settings::get();

        $customer_email = get_post_meta($ticket_post_id, '_hm_customer_email', true);
        if (empty($customer_email) || !is_email($customer_email)) {
            return new WP_Error('hmsh_no_customer_email', 'Müşteri e-posta adresi bulunamadı.');
        }

        $ticket_subject = get_the_title($ticket_post_id);
        $site_name = get_post_meta($ticket_post_id, '_hm_site_name', true);
        $site_url  = get_post_meta($ticket_post_id, '_hm_site_url', true);

        $mail_subject = '[HM Destek] Yanıt: ' . $ticket_subject;

        $lines = array();
        $lines[] = 'Merhaba,';
        $lines[] = '';
        $lines[] = 'Destek talebinize yanıt verdik:';
        $lines[] = '';
        $lines[] = $reply_message;
        $lines[] = '';
        $lines[] = '---';
        $lines[] = 'Talep Bilgisi';
        $lines[] = 'Kayıt ID: ' . $ticket_post_id;
        if (!empty($site_name)) $lines[] = 'Site: ' . $site_name;
        if (!empty($site_url))  $lines[] = 'Site URL: ' . $site_url;
        $lines[] = '---';
        $lines[] = 'Hızlı Mağaza Pro';

        $body = implode("\n", $lines);

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        if (!empty($s['from_email'])) {
            $from_name = !empty($s['from_name']) ? $s['from_name'] : 'Hızlı Mağaza Pro';
            $headers[] = 'From: ' . $from_name . ' <' . $s['from_email'] . '>';
            $headers[] = 'Return-Path: ' . $s['from_email'];
            // müşterinin cevaplayabilmesi için:
            $headers[] = 'Reply-To: ' . $s['from_email'];
        }

        $ok = wp_mail($customer_email, $mail_subject, $body, $headers);

        if (!$ok) {
            return new WP_Error('hmsh_mail_failed', 'E-posta gönderilemedi. SMTP loglarını kontrol edin.');
        }

        return true;
    }
}
