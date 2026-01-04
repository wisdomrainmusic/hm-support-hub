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

        register_rest_route(
            'hmsh/v1',
            '/register',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'handle_register'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'hmsh/v1',
            '/ping',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'ping'],
                'permission_callback' => '__return_true',
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

        $site = self::find_site_by_key($header_key, $request);

        if ($site) {
            $request->set_param('hmsh_site_id', isset($site['site_id']) ? $site['site_id'] : '');

            return true;
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
     * Locate a site by API key (optionally filtered by site header).
     *
     * @param string           $key     API key to match.
     * @param WP_REST_Request  $request Request instance.
     *
     * @return array|null
     */
    private static function find_site_by_key($key, WP_REST_Request $request)
    {
        $key = trim((string) $key);

        if (empty($key)) {
            return null;
        }

        $site_header = trim((string) $request->get_header('x-hm-site'));

        if ($site_header !== '') {
            $site = HMSH_Sites::find_by_site_id($site_header);

            if (!empty($site) && !empty($site['api_key']) && hash_equals((string) $site['api_key'], $key)) {
                return $site;
            }
        }

        foreach (HMSH_Sites::get_all() as $row) {
            if (!empty($row['api_key']) && hash_equals((string) $row['api_key'], $key)) {
                return $row;
            }
        }

        return null;
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
        $site_id_from_auth = sanitize_text_field($request->get_param('hmsh_site_id'));
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
            'site_id'           => $site_id_from_auth ?: $site_header,
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

    /**
     * Handle client auto-registration. Generates a per-site API key.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_register(WP_REST_Request $req)
    {
        $payload = $req->get_json_params();

        if (!is_array($payload)) {
            $payload = [];
        }

        $site_url  = isset($payload['site_url']) ? esc_url_raw($payload['site_url']) : '';
        $site_name = isset($payload['site_name']) ? sanitize_text_field($payload['site_name']) : '';

        if (empty($site_url) || empty($site_name)) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'site_url ve site_name zorunludur.',
            ], 400);
        }

        $s = HMSH_Settings::get();
        $shared = !empty($s['shared_api_key']) ? (string) $s['shared_api_key'] : '';
        $provided = isset($payload['shared_api_key']) ? sanitize_text_field($payload['shared_api_key']) : '';

        if (empty($shared) || $provided !== $shared) {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => 'shared_api_key doğrulaması başarısız.',
            ], 403);
        }

        $row = HMSH_Sites::upsert($site_name, $site_url);

        return new WP_REST_Response([
            'ok'      => true,
            'site_id' => $row['site_id'],
            'api_key' => $row['api_key'],
        ], 200);
    }

    /**
     * Simple ping endpoint to verify connectivity.
     */
    public static function ping(WP_REST_Request $request)
    {
        return new WP_REST_Response([
            'ok'      => true,
            'time'    => time(),
            'version' => (defined('HMSH_VERSION') ? HMSH_VERSION : 'unknown'),
        ], 200);
    }
}
