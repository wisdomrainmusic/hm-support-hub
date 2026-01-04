<?php

if (!defined('ABSPATH')) {
    exit;
}

class HMSH_Admin
{
    const CAPABILITY = 'manage_options';

    /**
     * Boot hooks.
     */
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    /**
     * Register admin menu pages.
     */
    public static function register_menu()
    {
        add_menu_page(
            'HM Destek',
            'HM Destek',
            self::CAPABILITY,
            'hmsh-inbox',
            [__CLASS__, 'render_inbox'],
            'dashicons-sos',
            58
        );

        add_submenu_page(
            'hmsh-inbox',
            'Gelen Talepler',
            'Gelen Talepler',
            self::CAPABILITY,
            'hmsh-inbox',
            [__CLASS__, 'render_inbox']
        );

        add_submenu_page(
            'hmsh-inbox',
            'Ayarlar',
            'Ayarlar',
            self::CAPABILITY,
            'hmsh-settings',
            [__CLASS__, 'render_settings']
        );
    }

    /**
     * Render inbox page with latest tickets.
     */
    public static function render_inbox()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('Yetkiniz yok.', 'hm-support-hub'));
        }

        $query = new WP_Query(
            [
                'post_type'      => HMSH_Ticket_CPT::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => 30,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );
        ?>
        <div class="wrap hmsh-wrap">
            <h1>HM Destek • Gelen Talepler</h1>

            <div class="hmsh-card">
                <p><strong>Not:</strong> Bu panel müşteri sitelerinden gelen talepleri toplar. Yanıtlama ve silme ekranını bir sonraki committe ekleyeceğiz.</p>
            </div>

            <div class="hmsh-card">
                <h2>Son Talepler</h2>

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Site</th>
                            <th>Müşteri E-posta</th>
                            <th>Aciliyet</th>
                            <th>Durum</th>
                            <th>Konu</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$query->have_posts()) : ?>
                        <tr><td colspan="7">Henüz talep yok.</td></tr>
                    <?php else : ?>
                        <?php while ($query->have_posts()) : $query->the_post(); ?>
                            <?php
                            $post_id   = get_the_ID();
                            $site_name = get_post_meta($post_id, '_hm_site_name', true);
                            $site_id   = get_post_meta($post_id, '_hm_site_id', true);
                            $cust_mail = get_post_meta($post_id, '_hm_customer_email', true);
                            $urgency   = get_post_meta($post_id, '_hm_urgency', true);
                            $status    = get_post_meta($post_id, '_hm_status', true);

                            $badge_class = 'hmsh-badge';
                            if ('Critical' === $urgency || 'Kritik' === $urgency) {
                                $badge_class .= ' hmsh-red';
                            }
                            ?>
                            <tr>
                                <td><?php echo (int) $post_id; ?></td>
                                <td><?php echo esc_html($site_name ?: $site_id); ?></td>
                                <td><?php echo esc_html($cust_mail); ?></td>
                                <td><span class="<?php echo esc_attr($badge_class); ?>"><?php echo esc_html($urgency ?: 'Normal'); ?></span></td>
                                <td><span class="hmsh-badge"><?php echo esc_html($status ?: 'Yeni'); ?></span></td>
                                <td><?php echo esc_html(get_the_title()); ?></td>
                                <td><?php echo esc_html(get_the_date('Y-m-d H:i')); ?></td>
                            </tr>
                        <?php endwhile; wp_reset_postdata(); ?>
                    <?php endif; ?>
                    </tbody>
                </table>

            </div>
        </div>
        <?php
    }

    /**
     * Render settings page.
     */
    public static function render_settings()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('Yetkiniz yok.', 'hm-support-hub'));
        }

        $settings = HMSH_Settings::get();
        ?>
        <div class="wrap hmsh-wrap">
            <h1>HM Destek • Ayarlar</h1>

            <form method="post" action="options.php">
                <?php settings_fields('hmsh_settings_group'); ?>

                <div class="hmsh-card">
                    <h2>Güvenlik</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="hmsh_shared_api_key">Ortak API Anahtarı</label></th>
                            <td>
                                <input type="text" id="hmsh_shared_api_key" class="regular-text"
                                       name="<?php echo esc_attr(HMSH_Settings::OPTION_KEY); ?>[shared_api_key]"
                                       value="<?php echo esc_attr($settings['shared_api_key']); ?>" placeholder="Örn: hm_shared_key_123">
                                <p class="description">Şimdilik tüm müşteri siteleri için tek anahtar. Bir sonraki adımda otomatik site bazlı anahtar üretimine geçeceğiz.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="hmsh-card">
                    <h2>Bildirimler</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="hmsh_notify_recipients">Bildirim Alıcıları</label></th>
                            <td>
                                <textarea id="hmsh_notify_recipients" class="large-text" rows="3"
                                          name="<?php echo esc_attr(HMSH_Settings::OPTION_KEY); ?>[notify_recipients]"><?php echo esc_textarea($settings['notify_recipients']); ?></textarea>
                                <p class="description">Virgülle ayırın. Örn: destek@hizlimagazapro.com, alisverissepetiniz4601@gmail.com</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="hmsh-card">
                    <h2>Gönderici</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="hmsh_from_name">Gönderici Adı</label></th>
                            <td>
                                <input type="text" id="hmsh_from_name" class="regular-text"
                                       name="<?php echo esc_attr(HMSH_Settings::OPTION_KEY); ?>[from_name]"
                                       value="<?php echo esc_attr($settings['from_name']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="hmsh_from_email">Gönderici E-posta</label></th>
                            <td>
                                <input type="email" id="hmsh_from_email" class="regular-text"
                                       name="<?php echo esc_attr(HMSH_Settings::OPTION_KEY); ?>[from_email]"
                                       value="<?php echo esc_attr($settings['from_email']); ?>">
                                <p class="description">FluentSMTP ayarlarınızla uyumlu olmalı.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button('Ayarları Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}
