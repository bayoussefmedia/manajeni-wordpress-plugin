<?php
/**
 * Endpoints REST recevant les mises a jour Manajeni vers WooCommerce.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Webhook_Controller {

    /**
     * @var Manajeni_Sync_Mapper
     */
    private $mapper;

    /**
     * @var Manajeni_Sync_Logger
     */
    private $logger;

    public function __construct($mapper = null, $logger = null) {
        $this->mapper = $mapper ?: new Manajeni_Sync_Mapper();
        $this->logger = $logger ?: new Manajeni_Sync_Logger();
    }

    /**
     * Enregistre les routes REST.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route('manajeni/v1', '/webhook/catalogue-updated', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_catalogue_updated'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('manajeni/v1', '/webhook/stock-updated', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_stock_updated'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('manajeni/v1', '/webhook/client-updated', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_client_updated'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Webhook catalogue.
     *
     * @param WP_REST_Request $request Requete.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_catalogue_updated($request) {
        if (!$this->is_authorized($request)) {
            return new WP_Error('manajeni_forbidden', __('Signature webhook invalide.', 'manajeni-connector'), ['status' => 403]);
        }

        if (!manajeni_connector_is_woocommerce_active()) {
            return new WP_Error('manajeni_woocommerce_missing', __('WooCommerce non installe.', 'manajeni-connector'), ['status' => 503]);
        }

        $payload = $this->get_payload($request);
        $sku = $this->extract_reference($payload);

        if ('' === $sku) {
            $this->logger->log('warning', 'webhook_catalogue_invalid', 'Webhook catalogue ignore: reference absente.', $payload);
            return rest_ensure_response(['success' => false, 'message' => 'missing_reference']);
        }

        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            $this->logger->log('warning', 'webhook_catalogue_missing_product', 'Aucun produit WooCommerce trouve pour cette reference.', ['sku' => $sku]);
            return new WP_Error('manajeni_product_not_found', __('Produit WooCommerce introuvable pour ce SKU.', 'manajeni-connector'), ['status' => 404]);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('manajeni_product_invalid', __('Produit WooCommerce invalide.', 'manajeni-connector'), ['status' => 404]);
        }

        $this->mapper->set_lock('product', $product_id);
        try {
            if (!empty($payload['name'])) {
                $product->set_name(sanitize_text_field($payload['name']));
            }

            if (isset($payload['price'])) {
                $price = wc_format_decimal($payload['price']);
                $product->set_regular_price($price);
                $product->set_price($price);
            }

            if (isset($payload['stock'])) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity((int) $payload['stock']);
                $product->set_stock_status(((int) $payload['stock']) > 0 ? 'instock' : 'outofstock');
            }

            if (isset($payload['status'])) {
                $product->set_status('active' === $payload['status'] ? 'publish' : 'draft');
            }

            $product->save();

            $this->mapper->save_product_mapping([
                'wc_product_id' => $product_id,
                'manajeni_product_id' => isset($payload['id']) ? $payload['id'] : 0,
                'sku' => $sku,
            ]);

            $this->logger->log('info', 'webhook_catalogue_updated', 'Produit WooCommerce mis a jour depuis Manajeni.', ['product_id' => $product_id, 'sku' => $sku]);
        } finally {
            $this->mapper->release_lock('product', $product_id);
        }

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Webhook stock.
     *
     * @param WP_REST_Request $request Requete.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_stock_updated($request) {
        if (!$this->is_authorized($request)) {
            return new WP_Error('manajeni_forbidden', __('Signature webhook invalide.', 'manajeni-connector'), ['status' => 403]);
        }

        if (!manajeni_connector_is_woocommerce_active()) {
            return new WP_Error('manajeni_woocommerce_missing', __('WooCommerce non installe.', 'manajeni-connector'), ['status' => 503]);
        }

        $payload = $this->get_payload($request);
        $sku = $this->extract_reference($payload);
        $product_id = $sku ? wc_get_product_id_by_sku($sku) : 0;

        if (!$product_id) {
            $this->logger->log('warning', 'webhook_stock_missing_product', 'Stock webhook ignore: produit introuvable.', ['sku' => $sku]);
            return new WP_Error('manajeni_product_not_found', __('Produit WooCommerce introuvable pour ce SKU.', 'manajeni-connector'), ['status' => 404]);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('manajeni_product_invalid', __('Produit WooCommerce invalide.', 'manajeni-connector'), ['status' => 404]);
        }

        $quantity = isset($payload['stock']) ? (int) $payload['stock'] : (isset($payload['stock_quantity']) ? (int) $payload['stock_quantity'] : null);
        if (null === $quantity) {
            return new WP_Error('manajeni_missing_stock', __('Quantite de stock manquante.', 'manajeni-connector'), ['status' => 400]);
        }

        $this->mapper->set_lock('product', $product_id);
        try {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($quantity);
            $product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');
            $product->save();

            $this->logger->log('info', 'webhook_stock_updated', 'Stock WooCommerce mis a jour depuis Manajeni.', ['product_id' => $product_id, 'sku' => $sku, 'stock' => $quantity]);
        } finally {
            $this->mapper->release_lock('product', $product_id);
        }

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Webhook client.
     *
     * @param WP_REST_Request $request Requete.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_client_updated($request) {
        if (!$this->is_authorized($request)) {
            return new WP_Error('manajeni_forbidden', __('Signature webhook invalide.', 'manajeni-connector'), ['status' => 403]);
        }

        $payload = $this->get_payload($request);
        $email = isset($payload['email']) ? sanitize_email($payload['email']) : '';

        if ('' === $email) {
            return new WP_Error('manajeni_missing_email', __('Email client manquant.', 'manajeni-connector'), ['status' => 400]);
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            $this->logger->log('warning', 'webhook_client_missing_user', 'Aucun utilisateur WordPress trouve pour ce client Manajeni.', ['email' => $email]);
            return rest_ensure_response(['success' => true, 'message' => 'user_not_found']);
        }

        $this->mapper->set_lock('client', $user->ID);
        try {
            $full_name = isset($payload['nom']) ? sanitize_text_field($payload['nom']) : '';
            $parts = preg_split('/\s+/', trim($full_name), 2);

            if (!empty($parts[0])) {
                update_user_meta($user->ID, 'billing_first_name', $parts[0]);
            }

            if (!empty($parts[1])) {
                update_user_meta($user->ID, 'billing_last_name', $parts[1]);
            }

            if (!empty($payload['telephone'])) {
                update_user_meta($user->ID, 'billing_phone', sanitize_text_field($payload['telephone']));
            }

            if (!empty($payload['ville'])) {
                update_user_meta($user->ID, 'billing_city', sanitize_text_field($payload['ville']));
            }

            if (!empty($payload['adresse'])) {
                update_user_meta($user->ID, 'billing_address_1', sanitize_text_field($payload['adresse']));
            }

            $this->mapper->save_client_mapping([
                'wp_user_id' => $user->ID,
                'customer_id' => $user->ID,
                'manajeni_client_id' => isset($payload['id']) ? $payload['id'] : 0,
                'email' => $email,
            ]);

            $this->logger->log('info', 'webhook_client_updated', 'Client WordPress synchronise depuis Manajeni.', ['user_id' => $user->ID, 'email' => $email]);
        } finally {
            $this->mapper->release_lock('client', $user->ID);
        }

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Verifie la signature HMAC.
     *
     * @param WP_REST_Request $request Requete.
     * @return bool
     */
    private function is_authorized($request) {
        $signature = $request->get_header('x-manajeni-signature');
        $secret = manajeni_connector_get_webhook_secret();

        if (empty($signature) || empty($secret)) {
            return false;
        }

        $raw = $request->get_body();
        $expected = hash_hmac('sha256', $raw, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Retourne le payload JSON.
     *
     * @param WP_REST_Request $request Requete.
     * @return array
     */
    private function get_payload($request) {
        $payload = $request->get_json_params();

        return is_array($payload) ? $payload : [];
    }

    /**
     * Extrait la reference/SKU.
     *
     * @param array $payload Payload.
     * @return string
     */
    private function extract_reference($payload) {
        if (!empty($payload['reference'])) {
            return sanitize_text_field($payload['reference']);
        }

        if (!empty($payload['sku'])) {
            return sanitize_text_field($payload['sku']);
        }

        return '';
    }
}
