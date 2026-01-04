<?php

if (!defined('ABSPATH')) exit;

class HMSH_Ticket_CPT
{
    const CPT = 'hm_ticket';

    /**
     * Register hooks for the ticket post type.
     */
    public static function init()
    {
        add_action('init', [__CLASS__, 'register']);
    }

    /**
     * Register the custom post type.
     */
    public static function register()
    {
        $labels = [
            'name'               => 'Destek Talepleri',
            'singular_name'      => 'Destek Talebi',
            'menu_name'          => 'HM Destek',
            'add_new'            => 'Yeni Ekle',
            'add_new_item'       => 'Yeni Destek Talebi',
            'edit_item'          => 'Talebi Düzenle',
            'new_item'           => 'Yeni Talep',
            'view_item'          => 'Talebi Görüntüle',
            'search_items'       => 'Talep Ara',
            'not_found'          => 'Kayıt bulunamadı',
            'not_found_in_trash' => 'Çöp kutusunda kayıt yok',
        ];

        register_post_type(self::CPT, [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => false, // kendi özel panelimizi yapacağız
            'show_in_menu'    => false,
            'supports'        => ['title', 'editor'],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ]);
    }

    /**
     * Create a ticket post with default meta values.
     *
     * @param array $data Ticket data.
     *
     * @return int|WP_Error Post ID on success or WP_Error on failure.
     */
    public static function create_ticket($data)
    {
        $title = !empty($data['subject']) ? wp_strip_all_tags($data['subject']) : 'Destek Talebi';
        $content = !empty($data['message']) ? wp_kses_post($data['message']) : '';

        $post_id = wp_insert_post([
            'post_type'    => self::CPT,
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $content,
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $meta_map = [
            '_hm_site_id'        => isset($data['site_id']) ? sanitize_text_field($data['site_id']) : '',
            '_hm_site_url'       => isset($data['client_site_url']) ? esc_url_raw($data['client_site_url']) : '',
            '_hm_site_name'      => isset($data['client_site_name']) ? sanitize_text_field($data['client_site_name']) : '',
            '_hm_customer_email' => isset($data['customer_email']) ? sanitize_email($data['customer_email']) : '',
            '_hm_customer_phone' => isset($data['customer_phone']) ? sanitize_text_field($data['customer_phone']) : '',
            '_hm_urgency'        => isset($data['urgency']) ? sanitize_text_field($data['urgency']) : 'Normal',
            '_hm_status'         => isset($data['status']) ? sanitize_text_field($data['status']) : 'Yeni',
            '_hm_ticket_id'      => isset($data['ticket_id']) ? sanitize_text_field($data['ticket_id']) : '',
            '_hm_last_activity'  => time(),
        ];

        foreach ($meta_map as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        return $post_id;
    }
}
