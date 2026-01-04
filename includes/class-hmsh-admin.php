<?php
if (!defined('ABSPATH')) exit;

class HMSH_Admin {
    const CAP = 'manage_options';

    const PAGE_INBOX   = 'hmsh-inbox';
    const PAGE_SETTINGS = 'hmsh-settings';
    const PAGE_VIEW    = 'hmsh-view';

    const ACTION_DELETE = 'hmsh_delete_ticket';
    const ACTION_BULK_DELETE = 'hmsh_bulk_delete';
    const ACTION_REPLY  = 'hmsh_reply_ticket';
    const ACTION_CLOSE  = 'hmsh_close_ticket';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));

        // Handlers
        add_action('admin_post_' . self::ACTION_DELETE, array(__CLASS__, 'handle_delete'));
        add_action('admin_post_' . self::ACTION_BULK_DELETE, array(__CLASS__, 'handle_bulk_delete'));
        add_action('admin_post_' . self::ACTION_REPLY, array(__CLASS__, 'handle_reply'));
        add_action('admin_post_' . self::ACTION_CLOSE, array(__CLASS__, 'handle_close'));
    }

    public static function menu() {
        add_menu_page(
            'HM Destek',
            'HM Destek',
            self::CAP,
            self::PAGE_INBOX,
            array(__CLASS__, 'render_inbox'),
            'dashicons-sos',
            58
        );

        add_submenu_page(
            self::PAGE_INBOX,
            'Gelen Talepler',
            'Gelen Talepler',
            self::CAP,
            self::PAGE_INBOX,
            array(__CLASS__, 'render_inbox')
        );

        add_submenu_page(
            self::PAGE_INBOX,
            'Ayarlar',
            'Ayarlar',
            self::CAP,
            self::PAGE_SETTINGS,
            array(__CLASS__, 'render_settings')
        );

        // hidden detail page
        add_submenu_page(
            null,
            'Talep Detayı',
            'Talep Detayı',
            self::CAP,
            self::PAGE_VIEW,
            array(__CLASS__, 'render_view')
        );
    }

    private static function require_cap() {
        if (!current_user_can(self::CAP)) {
            wp_die('Yetkiniz yok.');
        }
    }

    private static function status_badge_class($status) {
        $s = is_string($status) ? trim($status) : '';
        $s_l = mb_strtolower($s, 'UTF-8');

        $class = 'hmsh-badge';
        if ($s_l === 'kapalı' || $s_l === 'kapali') $class .= ' hmsh-green';
        if ($s_l === 'yanıtlandı' || $s_l === 'yanitlandi') $class .= ' hmsh-blue';
        return $class;
    }

    private static function urgency_badge_class($urgency) {
        $u = is_string($urgency) ? trim($urgency) : '';
        $u_l = mb_strtolower($u, 'UTF-8');

        $class = 'hmsh-badge';
        if ($u_l === 'kritik') $class .= ' hmsh-red';
        if ($u_l === 'yüksek' || $u_l === 'yuksek') $class .= ' hmsh-orange';
        return $class;
    }

    public static function render_inbox() {
        self::require_cap();

        $args = array(
            'post_type'      => HMSH_Ticket_CPT::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $q = new WP_Query($args);

        $bulk_url = admin_url('admin-post.php');
        $nonce_bulk = wp_create_nonce('hmsh_bulk_delete_nonce');
        ?>
        <div class="wrap hmsh-wrap">
            <h1>HM Destek • Gelen Talepler</h1>

            <?php if (isset($_GET['hmsh_msg'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sanitize_text_field($_GET['hmsh_msg'])); ?></p></div>
            <?php endif; ?>

            <div class="hmsh-card">
                <form method="post" action="<?php echo esc_url($bulk_url); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_BULK_DELETE); ?>">
                    <input type="hidden" name="hmsh_nonce" value="<?php echo esc_attr($nonce_bulk); ?>">

                    <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                        <button type="submit" class="button button-secondary"
                                onclick="return confirm('Seçilen talepler silinsin mi? Bu işlem geri alınamaz.');">
                            Seçilenleri Sil
                        </button>
                        <span class="description">Log dolmasın diye talepleri tek tek veya toplu silebilirsiniz.</span>
                    </div>

                    <table class="widefat fixed striped">
                        <thead>
                        <tr>
                            <th style="width:32px;"><input type="checkbox" onclick="document.querySelectorAll('.hmsh_cb').forEach(cb=>cb.checked=this.checked);"></th>
                            <th style="width:70px;">ID</th>
                            <th>Site</th>
                            <th>Müşteri E-posta</th>
                            <th style="width:110px;">Aciliyet</th>
                            <th style="width:120px;">Durum</th>
                            <th>Konu</th>
                            <th style="width:150px;">Tarih</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$q->have_posts()): ?>
                            <tr><td colspan="8">Henüz talep yok.</td></tr>
                        <?php else: ?>
                            <?php while ($q->have_posts()): $q->the_post(); ?>
                                <?php
                                $pid = get_the_ID();
                                $site_name = get_post_meta($pid, '_hm_site_name', true);
                                $site_id   = get_post_meta($pid, '_hm_site_id', true);
                                $cust_mail = get_post_meta($pid, '_hm_customer_email', true);
                                $urgency   = get_post_meta($pid, '_hm_urgency', true);
                                $status    = get_post_meta($pid, '_hm_status', true);

                                $view_url = add_query_arg(array(
                                    'page' => self::PAGE_VIEW,
                                    'ticket_id' => $pid,
                                ), admin_url('admin.php'));

                                $delete_url = add_query_arg(array(
                                    'action' => self::ACTION_DELETE,
                                    'ticket_id' => $pid,
                                    'hmsh_nonce' => wp_create_nonce('hmsh_delete_' . $pid),
                                ), admin_url('admin-post.php'));
                                ?>
                                <tr>
                                    <td><input class="hmsh_cb" type="checkbox" name="ticket_ids[]" value="<?php echo (int)$pid; ?>">
                                    </td>
                                    <td><?php echo (int)$pid; ?></td>
                                    <td><?php echo esc_html($site_name ?: $site_id); ?></td>
                                    <td><?php echo esc_html($cust_mail); ?></td>
                                    <td><span class="<?php echo esc_attr(self::urgency_badge_class($urgency)); ?>"><?php echo esc_html($urgency ?: 'Normal'); ?></span></td>
                                    <td><span class="<?php echo esc_attr(self::status_badge_class($status)); ?>"><?php echo esc_html($status ?: 'Yeni'); ?></span></td>
                                    <td>
                                        <a href="<?php echo esc_url($view_url); ?>"><strong><?php echo esc_html(get_the_title()); ?></strong></a>
                                        <div style="margin-top:6px; display:flex; gap:10px;">
                                            <a href="<?php echo esc_url($view_url); ?>">Görüntüle</a>
                                            <a href="<?php echo esc_url($delete_url); ?>"
                                               onclick="return confirm('Bu talep silinsin mi? Bu işlem geri alınamaz.');"
                                               style="color:#b32d2e;">Sil</a>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html(get_the_date('Y-m-d H:i')); ?></td>
                                </tr>
                            <?php endwhile; wp_reset_postdata(); ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
        <?php
    }

    public static function render_view() {
        self::require_cap();

        $ticket_id = isset($_GET['ticket_id']) ? absint($_GET['ticket_id']) : 0;
        if (!$ticket_id) wp_die('Talep bulunamadı.');

        $post = get_post($ticket_id);
        if (!$post || $post->post_type !== HMSH_Ticket_CPT::CPT) wp_die('Talep bulunamadı.');

        $site_name = get_post_meta($ticket_id, '_hm_site_name', true);
        $site_url  = get_post_meta($ticket_id, '_hm_site_url', true);
        $site_id   = get_post_meta($ticket_id, '_hm_site_id', true);

        $cust_mail = get_post_meta($ticket_id, '_hm_customer_email', true);
        $phone     = get_post_meta($ticket_id, '_hm_customer_phone', true);

        $urgency   = get_post_meta($ticket_id, '_hm_urgency', true);
        $status    = get_post_meta($ticket_id, '_hm_status', true);

        $reply_log = get_post_meta($ticket_id, '_hm_reply_log', true);
        if (!is_array($reply_log)) $reply_log = array();

        $inbox_url = add_query_arg(array('page' => self::PAGE_INBOX), admin_url('admin.php'));

        $reply_action = admin_url('admin-post.php');
        $close_action = admin_url('admin-post.php');

        ?>
        <div class="wrap hmsh-wrap">
            <h1>HM Destek • Talep Detayı (#<?php echo (int)$ticket_id; ?>)</h1>

            <p><a class="button" href="<?php echo esc_url($inbox_url); ?>">← Gelen Talepler</a></p>

            <?php if (isset($_GET['hmsh_msg'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sanitize_text_field($_GET['hmsh_msg'])); ?></p></div>
            <?php endif; ?>

            <div class="hmsh-card">
                <h2><?php echo esc_html(get_the_title($ticket_id)); ?></h2>

                <p>
                    <span class="<?php echo esc_attr(self::urgency_badge_class($urgency)); ?>"><?php echo esc_html($urgency ?: 'Normal'); ?></span>
                    <span class="<?php echo esc_attr(self::status_badge_class($status)); ?>"><?php echo esc_html($status ?: 'Yeni'); ?></span>
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th>Site</th>
                        <td><?php echo esc_html($site_name ?: $site_id); ?></td>
                    </tr>
                    <tr>
                        <th>Site URL</th>
                        <td>
                            <?php if (!empty($site_url)): ?>
                                <a href="<?php echo esc_url($site_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($site_url); ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Müşteri E-posta</th>
                        <td><?php echo esc_html($cust_mail); ?></td>
                    </tr>
                    <tr>
                        <th>Telefon</th>
                        <td><?php echo !empty($phone) ? esc_html($phone) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th>Oluşturma</th>
                        <td><?php echo esc_html(get_the_date('Y-m-d H:i', $ticket_id)); ?></td>
                    </tr>
                </table>

                <h3>Mesaj</h3>
                <div style="background:#f6f7f7; border:1px solid #e2e4e7; padding:12px; border-radius:8px; white-space:pre-wrap;">
                    <?php echo esc_html($post->post_content); ?>
                </div>
            </div>

            <div class="hmsh-card">
                <h2>Yanıt Yaz</h2>
                <form method="post" action="<?php echo esc_url($reply_action); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_REPLY); ?>">
                    <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket_id; ?>">
                    <input type="hidden" name="hmsh_nonce" value="<?php echo esc_attr(wp_create_nonce('hmsh_reply_' . $ticket_id)); ?>">

                    <textarea name="reply_message" rows="8" class="large-text" required placeholder="Müşteriye gönderilecek yanıtı yazın..."></textarea>

                    <p style="display:flex; gap:10px; align-items:center; margin-top:10px;">
                        <button type="submit" class="button button-primary">Yanıtı Gönder (E-posta)</button>

                        <button type="submit"
                                formaction="<?php echo esc_url($close_action); ?>"
                                formmethod="post"
                                class="button button-secondary"
                                onclick="return confirm('Talep kapatılsın mı?');"
                                name="close_submit"
                                value="1">
                            Talebi Kapat
                        </button>
                    </p>

                    <input type="hidden" name="action_close" value="<?php echo esc_attr(self::ACTION_CLOSE); ?>">
                </form>

                <p class="description">Not: Yanıt gönderildiğinde durum otomatik olarak <strong>Yanıtlandı</strong> olur.</p>
            </div>

            <div class="hmsh-card">
                <h2>Yanıt Geçmişi</h2>
                <?php if (empty($reply_log)): ?>
                    <p>Henüz yanıt yok.</p>
                <?php else: ?>
                    <?php foreach (array_reverse($reply_log) as $item): ?>
                        <?php
                        $when = !empty($item['time']) ? date_i18n('Y-m-d H:i', (int)$item['time']) : '';
                        $by   = !empty($item['by']) ? $item['by'] : '—';
                        $msg  = !empty($item['message']) ? $item['message'] : '';
                        ?>
                        <div style="border:1px solid #dcdcde; border-radius:10px; padding:12px; margin:10px 0; background:#fff;">
                            <div style="font-size:12px; opacity:.8; margin-bottom:8px;">
                                <strong><?php echo esc_html($by); ?></strong> • <?php echo esc_html($when); ?>
                            </div>
                            <div style="white-space:pre-wrap;"><?php echo esc_html($msg); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }

    public static function handle_delete() {
        self::require_cap();

        $ticket_id = isset($_GET['ticket_id']) ? absint($_GET['ticket_id']) : 0;
        $nonce = isset($_GET['hmsh_nonce']) ? sanitize_text_field($_GET['hmsh_nonce']) : '';

        if (!$ticket_id || !wp_verify_nonce($nonce, 'hmsh_delete_' . $ticket_id)) {
            wp_die('Güvenlik doğrulaması başarısız.');
        }

        $post = get_post($ticket_id);
        if ($post && $post->post_type === HMSH_Ticket_CPT::CPT) {
            wp_delete_post($ticket_id, true);
        }

        wp_safe_redirect(add_query_arg(array('page' => self::PAGE_INBOX, 'hmsh_msg' => rawurlencode('Talep silindi.')), admin_url('admin.php')));
        exit;
    }

    public static function handle_bulk_delete() {
        self::require_cap();

        $nonce = isset($_POST['hmsh_nonce']) ? sanitize_text_field($_POST['hmsh_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'hmsh_bulk_delete_nonce')) {
            wp_die('Güvenlik doğrulaması başarısız.');
        }

        $ids = isset($_POST['ticket_ids']) ? (array) $_POST['ticket_ids'] : array();
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);

        $count = 0;
        foreach ($ids as $id) {
            $post = get_post($id);
            if ($post && $post->post_type === HMSH_Ticket_CPT::CPT) {
                wp_delete_post($id, true);
                $count++;
            }
        }

        wp_safe_redirect(add_query_arg(array('page' => self::PAGE_INBOX, 'hmsh_msg' => rawurlencode($count . ' talep silindi.')), admin_url('admin.php')));
        exit;
    }

    public static function handle_reply() {
        self::require_cap();

        $ticket_id = isset($_POST['ticket_id']) ? absint($_POST['ticket_id']) : 0;
        $nonce = isset($_POST['hmsh_nonce']) ? sanitize_text_field($_POST['hmsh_nonce']) : '';
        $reply = isset($_POST['reply_message']) ? wp_strip_all_tags(wp_unslash($_POST['reply_message'])) : '';

        if (!$ticket_id || !wp_verify_nonce($nonce, 'hmsh_reply_' . $ticket_id)) {
            wp_die('Güvenlik doğrulaması başarısız.');
        }

        if (empty($reply)) {
            wp_safe_redirect(add_query_arg(array('page' => self::PAGE_VIEW, 'ticket_id' => $ticket_id, 'hmsh_msg' => rawurlencode('Yanıt boş olamaz.')), admin_url('admin.php')));
            exit;
        }

        $res = HMSH_Mailer::send_customer_reply($ticket_id, $reply);
        if (is_wp_error($res)) {
            wp_safe_redirect(add_query_arg(array('page' => self::PAGE_VIEW, 'ticket_id' => $ticket_id, 'hmsh_msg' => rawurlencode($res->get_error_message())), admin_url('admin.php')));
            exit;
        }

        // log reply
        $log = get_post_meta($ticket_id, '_hm_reply_log', true);
        if (!is_array($log)) $log = array();

        $user = wp_get_current_user();
        $log[] = array(
            'time' => time(),
            'by' => $user ? $user->display_name : 'Admin',
            'message' => $reply,
        );
        update_post_meta($ticket_id, '_hm_reply_log', $log);

        // status update
        update_post_meta($ticket_id, '_hm_status', 'Yanıtlandı');
        update_post_meta($ticket_id, '_hm_last_activity', time());

        wp_safe_redirect(add_query_arg(array('page' => self::PAGE_VIEW, 'ticket_id' => $ticket_id, 'hmsh_msg' => rawurlencode('Yanıt e-posta ile gönderildi.')), admin_url('admin.php')));
        exit;
    }

    public static function handle_close() {
        self::require_cap();

        // we reuse reply nonce for simplicity: require ticket_id & nonce from POST
        $ticket_id = isset($_POST['ticket_id']) ? absint($_POST['ticket_id']) : 0;
        $nonce = isset($_POST['hmsh_nonce']) ? sanitize_text_field($_POST['hmsh_nonce']) : '';

        if (!$ticket_id || !wp_verify_nonce($nonce, 'hmsh_reply_' . $ticket_id)) {
            wp_die('Güvenlik doğrulaması başarısız.');
        }

        update_post_meta($ticket_id, '_hm_status', 'Kapalı');
        update_post_meta($ticket_id, '_hm_last_activity', time());

        wp_safe_redirect(add_query_arg(array('page' => self::PAGE_VIEW, 'ticket_id' => $ticket_id, 'hmsh_msg' => rawurlencode('Talep kapatıldı.')), admin_url('admin.php')));
        exit;
    }

    public static function render_settings() {
        self::require_cap();

        $s = HMSH_Settings::get();
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
                                       value="<?php echo esc_attr($s['shared_api_key']); ?>" placeholder="Örn: hm_shared_key_123">
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
                                          name="<?php echo esc_attr(HMSH_Settings::OPTION_KEY); ?>[notify_recipients]"><?php echo esc_textarea($s['notify_recipients']); ?></textarea>
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
                                       value="<?php echo esc_attr($s['from_name']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="hmsh_from_email">Gönderici E-posta</label></th>
                            <td>
                                <input type="email" id="hmsh_from_email" class="regular-text"
                                       name="<?php echo esc_attr(HMSH_Settings::OPTION_KEY); ?>[from_email]"
                                       value="<?php echo esc_attr($s['from_email']); ?>">
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
