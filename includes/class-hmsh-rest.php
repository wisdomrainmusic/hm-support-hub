<?php

if (!defined('ABSPATH')) {
    exit;
}

class HMSH_REST
{
    /**
     * Hook registration.
     */
    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register API routes.
     */
    public static function register_routes()
    {
        register_rest_route(
            'hmsh/v1',
            '/tickets',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'handle_ticket_create'],
                'permission_callback' => [__CLASS__, 'check_auth'],
            ]
        );
    }

    /**
     * Validate headers for authentication.
     *
     * @return true|WP_Error
     */
    public static function check_auth(WP_REST_Request $request)
    {
        $settings = HMSH_Settings::get();
        $shared_key = isset($settings['shared_api_key']) ? trim((string) $settings['shared_api_key']) : '';
        $header_key = trim((string) $request->get_header('x-hm-key'));

        if (empty($header_key)) {
            return new WP_Error('hmsh_missing_key', __('X-HM-Key header eksik.', 'hm-support-hub'), ['status' => 403]);
        }

        if (empty($shared_key)) {
            return new WP_Error('hmsh_no_key', __('API anahtarı ayarlardan girilmemiş.', 'hm-support-hub'), ['status' => 401]);
        }

        if (!hash_equals($shared_key, $header_key)) {
            return new WP_Error('hmsh_invalid_key', __('API anahtarı geçersiz.', 'hm-support-hub'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Handle ticket creation via REST.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_ticket_create(WP_REST_Request $request)
    {
        $site_header = sanitize_text_field($request->get_header('x-hm-site'));
        $body = $request->get_json_params();

        if (!is_array($body)) {
            return new WP_Error('hmsh_bad_json', __('Geçersiz JSON gövdesi.', 'hm-support-hub'), ['status' => 400]);
        }

        $subject = isset($body['subject']) ? sanitize_text_field($body['subject']) : '';
        $message = isset($body['message']) ? sanitize_textarea_field($body['message']) : '';
        $email   = isset($body['customer_email']) ? sanitize_email($body['customer_email']) : '';

        if (empty($subject) || empty($message) || empty($email)) {
            return new WP_Error(
                'hmsh_missing_fields',
                __('subject, message ve customer_email alanları zorunlu.', 'hm-support-hub'),
                ['status' => 400]
            );
        }

        $data = [
            'site_id'           => $site_header ?: '',
            'client_site_url'   => isset($body['client_site_url']) ? esc_url_raw($body['client_site_url']) : '',
            'client_site_name'  => isset($body['client_site_name']) ? sanitize_text_field($body['client_site_name']) : '',
            'customer_email'    => $email,
            'customer_phone'    => isset($body['customer_phone']) ? sanitize_text_field($body['customer_phone']) : '',
            'urgency'           => isset($body['urgency'])
                ? HMSH_Ticket_CPT::map_urgency_tr(sanitize_text_field($body['urgency']))
                : 'Normal',
            'status'            => 'Yeni',
            'ticket_id'         => isset($body['ticket_id']) ? sanitize_text_field($body['ticket_id']) : '',
            'subject'           => $subject,
            'message'           => $message,
        ];

        $post_id = HMSH_Ticket_CPT::create_ticket($data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        HMSH_Mailer::send_ticket_notification($post_id, $data);

        return rest_ensure_response([
            'success'   => true,
            'ticket_id' => $post_id,
        ]);
    }
}
