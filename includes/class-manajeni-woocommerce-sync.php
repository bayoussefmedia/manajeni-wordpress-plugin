<?php
/**
 * Synchronisation temps reel WooCommerce <-> Manajeni.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_WooCommerce_Sync {

    const RETRY_QUEUE_OPTION = 'manajeni_sync_retry_queue';

    /**
     * @var object
     */
    private $api_client;

    /**
     * @var Manajeni_Sync_Mapper
     */
    private $mapper;

    /**
     * @var Manajeni_Sync_Logger
     */
    private $logger;

    /**
     * @var Manajeni_Webhook_Controller
     */
    private $webhook_controller;

    public function __construct($api_client = null, $mapper = null, $logger = null) {
        $this->api_client = $api_client ?: manajeni_connector_get_api_client();
        $this->mapper = $mapper ?: new Manajeni_Sync_Mapper();
        $this->logger = $logger ?: new Manajeni_Sync_Logger();
        $this->webhook_controller = new Manajeni_Webhook_Controller($this->mapper, $this->logger);
    }

    /**
     * Initialise la synchronisation.
     *
     * @return void
     */
    public function bootstrap() {
        add_action('rest_api_init', [$this->webhook_controller, 'register_routes']);
        add_action('manajeni_sync_retry_event', [$this, 'process_retry_queue']);
        add_action('manajeni_sync_process_retry_job', [$this, 'process_retry_job']);

        if (!manajeni_connector_is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'render_woocommerce_missing_notice']);
            return;
        }

        add_action('woocommerce_update_product', [$this, 'handle_product_update'], 20, 1);
        add_action('woocommerce_product_set_stock', [$this, 'handle_stock_change'], 20, 1);
        add_action('woocommerce_variation_set_stock', [$this, 'handle_stock_change'], 20, 1);
        add_action('woocommerce_reduce_order_stock', [$this, 'handle_order_stock_event'], 20, 1);
        add_action('woocommerce_restore_order_stock', [$this, 'handle_order_stock_event'], 20, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'handle_checkout_processed'], 20, 3);
        add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_changed'], 20, 4);
    }

    /**
     * Notice admin si WooCommerce est absent.
     *
     * @return void
     */
    public function render_woocommerce_missing_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page']) || false === strpos((string) $_GET['page'], 'manajeni')) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p><?php echo esc_html__('WooCommerce non installé', 'manajeni-connector'); ?></p>
        </div>
        <?php
    }

    /**
     * Sync create/update produit WooCommerce -> Manajeni.
     *
     * @param int $product_id Product ID.
     * @return void
     */
    public function handle_product_update($product_id) {
        if (!$this->can_call_api()) {
            return;
        }

        $product_id = absint($product_id);
        if (!$product_id || $this->mapper->has_lock('product', $product_id)) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $sku = trim((string) $product->get_sku());
        if ('' === $sku) {
            $this->logger->log('warning', 'product_sync_skipped', 'Produit WooCommerce ignore: SKU vide.', ['product_id' => $product_id]);
            return;
        }

        $payload = $this->build_product_payload($product);
        $mapping = $this->mapper->find_product_mapping(['wc_product_id' => $product_id]);
        $remote_item = $this->find_remote_catalogue_item_by_reference($sku);

        if ($remote_item && empty($mapping['manajeni_product_id'])) {
            $mapping = [
                'wc_product_id' => $product_id,
                'manajeni_product_id' => isset($remote_item['id']) ? (int) $remote_item['id'] : 0,
                'sku' => $sku,
            ];
        }

        if (!empty($mapping['manajeni_product_id'])) {
            $payload['id'] = (int) $mapping['manajeni_product_id'];
            $response = $this->api_client->update_catalogue_item($payload);
            $action = 'product_updated';
        } else {
            $response = $this->api_client->create_catalogue_item($payload);
            $action = 'product_created';
        }

        if (!$this->is_success_response($response)) {
            $this->logger->log('error', 'product_sync_failed', 'Echec synchronisation produit WooCommerce -> Manajeni.', ['product_id' => $product_id, 'sku' => $sku, 'response' => $response]);
            $this->schedule_retry('product', ['product_id' => $product_id]);
            return;
        }

        $remote_id = $this->extract_remote_id($response, $remote_item);
        $this->mapper->save_product_mapping([
            'wc_product_id' => $product_id,
            'manajeni_product_id' => $remote_id,
            'sku' => $sku,
        ]);

        $this->logger->log('info', $action, 'Produit WooCommerce synchronise vers Manajeni.', ['product_id' => $product_id, 'sku' => $sku, 'manajeni_product_id' => $remote_id]);
    }

    /**
     * Sync stock WooCommerce -> Manajeni.
     *
     * @param mixed $product Product ou ID.
     * @return void
     */
    public function handle_stock_change($product) {
        if (!$this->can_call_api()) {
            return;
        }

        $product = is_numeric($product) ? wc_get_product(absint($product)) : $product;
        if (!$product || !method_exists($product, 'get_id')) {
            return;
        }

        $product_id = (int) $product->get_id();
        if ($this->mapper->has_lock('product', $product_id)) {
            return;
        }

        $sku = trim((string) $product->get_sku());
        if ('' === $sku) {
            $this->logger->log('warning', 'stock_sync_skipped', 'Stock ignore: SKU vide.', ['product_id' => $product_id]);
            return;
        }

        $mapping = $this->mapper->find_product_mapping(['wc_product_id' => $product_id]);
        $remote_item = $this->find_remote_catalogue_item_by_reference($sku);

        if ($remote_item && empty($mapping['manajeni_product_id'])) {
            $mapping = [
                'wc_product_id' => $product_id,
                'manajeni_product_id' => isset($remote_item['id']) ? (int) $remote_item['id'] : 0,
                'sku' => $sku,
            ];
        }

        if (method_exists($this->api_client, 'update_catalogue_stock_by_reference')) {
            $response = $this->api_client->update_catalogue_stock_by_reference(
                $sku,
                (int) $product->get_stock_quantity(),
                'woocommerce_stock_sync'
            );
        } else {
            if (empty($mapping['manajeni_product_id'])) {
                $this->handle_product_update($product_id);
                $mapping = $this->mapper->find_product_mapping(['wc_product_id' => $product_id]);
            }

            if (empty($mapping['manajeni_product_id'])) {
                $this->logger->log('warning', 'stock_sync_skipped', 'Stock non synchronise: mapping Manajeni introuvable.', ['product_id' => $product_id, 'sku' => $sku]);
                return;
            }

            $response = $this->api_client->update_catalogue_item([
                'id' => (int) $mapping['manajeni_product_id'],
                'reference' => $sku,
                'sku' => $sku,
                'stock' => (int) $product->get_stock_quantity(),
                'stock_quantity' => (int) $product->get_stock_quantity(),
                'status' => $product->is_in_stock() ? 'active' : 'inactive',
                'source' => 'woocommerce',
                'sync_status' => 'synchronise',
                'last_sync_at' => current_time('mysql'),
            ]);
        }

        if (!$this->is_success_response($response)) {
            $this->logger->log('error', 'stock_sync_failed', 'Echec synchronisation stock WooCommerce -> Manajeni.', ['product_id' => $product_id, 'sku' => $sku, 'response' => $response]);
            $this->schedule_retry('stock', ['product_id' => $product_id]);
            return;
        }

        $this->mapper->save_product_mapping([
            'wc_product_id' => $product_id,
            'manajeni_product_id' => !empty($mapping['manajeni_product_id']) ? $mapping['manajeni_product_id'] : (isset($remote_item['id']) ? (int) $remote_item['id'] : 0),
            'sku' => $sku,
        ]);

        $this->logger->log('info', 'stock_synced', 'Stock WooCommerce synchronise vers Manajeni.', ['product_id' => $product_id, 'sku' => $sku, 'stock' => (int) $product->get_stock_quantity()]);
    }

    /**
     * Importe le catalogue Manajeni dans WooCommerce sans supprimer les produits existants.
     *
     * @return array
     */
    public function import_catalogue_from_manajeni() {
        $results = [
            'success' => false,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        if (!manajeni_connector_is_woocommerce_active()) {
            $results['errors']++;
            $results['messages'][] = __('WooCommerce non installe.', 'manajeni-connector');
            return $results;
        }

        if (!$this->can_call_api()) {
            $results['errors']++;
            $results['messages'][] = __('Connexion API Manajeni absente.', 'manajeni-connector');
            return $results;
        }

        $catalogue = $this->api_client->get_catalogue();
        if (!is_array($catalogue)) {
            $results['errors']++;
            $results['messages'][] = __('Catalogue Manajeni invalide.', 'manajeni-connector');
            return $results;
        }

        foreach ($catalogue as $item) {
            $reference = $this->extract_catalogue_reference($item);
            if ('' === $reference) {
                $results['skipped']++;
                $results['messages'][] = __('Article ignore: reference manquante.', 'manajeni-connector');
                $this->logger->log('warning', 'catalogue_import_skipped', 'Import catalogue ignore: reference manquante.', ['item' => $item]);
                continue;
            }

            $product_id = wc_get_product_id_by_sku($reference);
            $product = $product_id ? wc_get_product($product_id) : new WC_Product_Simple();
            if (!$product) {
                $results['errors']++;
                $results['messages'][] = sprintf(__('Impossible de charger le produit WooCommerce pour %s.', 'manajeni-connector'), $reference);
                $this->logger->log('error', 'catalogue_import_failed', 'Chargement produit WooCommerce impossible pendant import catalogue.', ['reference' => $reference]);
                continue;
            }

            $is_new = !$product_id;

            try {
                $this->apply_catalogue_item_to_product($product, $item, $reference);
                $product_id = $product->get_id();
                $this->maybe_import_product_image($product_id, $item);

                $this->mapper->save_product_mapping([
                    'wc_product_id' => $product_id,
                    'manajeni_product_id' => isset($item['id']) ? (int) $item['id'] : 0,
                    'sku' => $reference,
                ]);

                if ($is_new) {
                    $results['created']++;
                    $this->logger->log('info', 'catalogue_import_created', 'Produit WooCommerce cree depuis le catalogue Manajeni.', ['product_id' => $product_id, 'sku' => $reference]);
                } else {
                    $results['updated']++;
                    $this->logger->log('info', 'catalogue_import_updated', 'Produit WooCommerce mis a jour depuis le catalogue Manajeni.', ['product_id' => $product_id, 'sku' => $reference]);
                }
            } catch (Exception $exception) {
                $results['errors']++;
                $results['messages'][] = sprintf(__('Erreur import %s: %s', 'manajeni-connector'), $reference, $exception->getMessage());
                $this->logger->log('error', 'catalogue_import_failed', 'Echec import catalogue Manajeni -> WooCommerce.', ['reference' => $reference, 'message' => $exception->getMessage()]);
            }
        }

        $results['success'] = 0 === $results['errors'];

        return $results;
    }

    /**
     * Reagit aux changements de stock lies a une commande.
     *
     * @param mixed $order Order ou ID.
     * @return void
     */
    public function handle_order_stock_event($order) {
        $order = $this->resolve_order($order);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $this->handle_stock_change($product);
            }
        }
    }

    /**
     * Checkout WooCommerce -> Manajeni.
     *
     * @param int      $order_id Order ID.
     * @param array    $posted_data Donnees.
     * @param WC_Order $order Order.
     * @return void
     */
    public function handle_checkout_processed($order_id, $posted_data = [], $order = null) {
        $order = $this->resolve_order($order ?: $order_id);
        if (!$order) {
            return;
        }

        $this->sync_order($order);
    }

    /**
     * Mise a jour de statut commande -> Manajeni.
     *
     * @param int      $order_id Order ID.
     * @param string   $old_status Ancien statut.
     * @param string   $new_status Nouveau statut.
     * @param WC_Order $order Order.
     * @return void
     */
    public function handle_order_status_changed($order_id, $old_status, $new_status, $order) {
        $order = $this->resolve_order($order ?: $order_id);
        if (!$order) {
            return;
        }

        $this->sync_order($order);
    }

    /**
     * Execute un retry cron.
     *
     * @return void
     */
    public function process_retry_queue() {
        $queue = get_option(self::RETRY_QUEUE_OPTION, []);
        if (!is_array($queue) || empty($queue)) {
            return;
        }

        $remaining = [];
        foreach ($queue as $job) {
            if (!$this->process_retry_job($job)) {
                $remaining[] = $job;
            }
        }

        update_option(self::RETRY_QUEUE_OPTION, $remaining, false);
    }

    /**
     * Execute un retry unique.
     *
     * @param array $job Job.
     * @return bool
     */
    public function process_retry_job($job) {
        if (!is_array($job) || empty($job['type'])) {
            return true;
        }

        switch ($job['type']) {
            case 'product':
                if (!empty($job['product_id'])) {
                    $this->handle_product_update((int) $job['product_id']);
                }
                break;
            case 'stock':
                if (!empty($job['product_id'])) {
                    $this->handle_stock_change((int) $job['product_id']);
                }
                break;
            case 'order':
                if (!empty($job['order_id'])) {
                    $order = $this->resolve_order((int) $job['order_id']);
                    if ($order) {
                        $this->sync_order($order);
                    }
                }
                break;
            case 'client':
                if (!empty($job['order_id'])) {
                    $order = $this->resolve_order((int) $job['order_id']);
                    if ($order) {
                        $this->sync_customer_from_order($order);
                    }
                }
                break;
        }

        return true;
    }

    /**
     * Retourne le nombre de jobs en attente.
     *
     * @return int
     */
    public function get_retry_queue_count() {
        $queue = get_option(self::RETRY_QUEUE_OPTION, []);

        return is_array($queue) ? count($queue) : 0;
    }

    /**
     * Synchronise une commande WooCommerce vers Manajeni.
     *
     * @param WC_Order $order Commande.
     * @return void
     */
    private function sync_order($order) {
        if (!$this->can_call_api()) {
            return;
        }

        $order_id = $order->get_id();
        if ($this->mapper->has_lock('order', $order_id)) {
            return;
        }

        $client_payload = $this->sync_customer_from_order($order);
        $payload = $this->build_order_payload($order, $client_payload);
        $mapping = $this->mapper->find_order_mapping(['wc_order_id' => $order_id]);

        if (!empty($mapping['manajeni_order_id']) && method_exists($this->api_client, 'update_order')) {
            $payload['id'] = (int) $mapping['manajeni_order_id'];
            $response = $this->api_client->update_order($payload);
            $action = 'order_updated';
        } else {
            if (method_exists($this->api_client, 'create_order_from_woocommerce')) {
                $response = $this->api_client->create_order_from_woocommerce($payload);
            } else {
                $response = $this->api_client->create_order($payload);
            }
            $action = 'order_created';
        }

        if (!$this->is_success_response($response)) {
            $this->logger->log('error', 'order_sync_failed', 'Echec synchronisation commande WooCommerce -> Manajeni.', ['order_id' => $order_id, 'response' => $response]);
            $this->schedule_retry('order', ['order_id' => $order_id]);
            return;
        }

        $remote_id = $this->extract_remote_id($response, []);
        $this->mapper->save_order_mapping([
            'wc_order_id' => $order_id,
            'manajeni_order_id' => $remote_id,
            'order_number' => $order->get_order_number(),
        ]);

        $this->logger->log('info', $action, 'Commande WooCommerce synchronisee vers Manajeni.', ['order_id' => $order_id, 'manajeni_order_id' => $remote_id]);
    }

    /**
     * Cree ou synchronise le client lie a une commande.
     *
     * @param WC_Order $order Commande.
     * @return array
     */
    private function sync_customer_from_order($order) {
        if (!$this->can_call_api()) {
            return [];
        }

        $customer_id = (int) $order->get_customer_id();
        $email = sanitize_email($order->get_billing_email());
        $mapping = null;

        if ($customer_id > 0) {
            $mapping = $this->mapper->find_client_mapping(['wp_user_id' => $customer_id]);
        }

        if (!$mapping && $email) {
            $mapping = $this->mapper->find_client_mapping(['email' => $email]);
        }

        $payload = [
            'nom' => trim($order->get_formatted_billing_full_name()),
            'email' => $email,
            'telephone' => sanitize_text_field($order->get_billing_phone()),
            'adresse' => sanitize_text_field(trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2())),
            'ville' => sanitize_text_field($order->get_billing_city()),
            'ice' => sanitize_text_field((string) $order->get_meta('_billing_ice')),
            'source' => 'woocommerce',
            'sync_status' => 'synchronise',
            'last_sync_at' => current_time('mysql'),
            'type' => $customer_id > 0 ? 'registered' : 'guest',
        ];

        if (empty($payload['nom'])) {
            $payload['nom'] = $order->get_billing_company() ? sanitize_text_field($order->get_billing_company()) : 'Client WooCommerce';
        }

        if (!empty($mapping['manajeni_client_id'])) {
            $payload['id'] = (int) $mapping['manajeni_client_id'];
            $response = $this->api_client->update_client($payload);
        } else {
            $remote_client = $this->find_remote_client($email, $payload['nom']);
            if ($remote_client && !empty($remote_client['id'])) {
                $payload['id'] = (int) $remote_client['id'];
                $response = $this->api_client->update_client($payload);
            } else {
                if (method_exists($this->api_client, 'upsert_client')) {
                    $response = $this->api_client->upsert_client($payload);
                } else {
                    $response = $this->api_client->create_client($payload);
                }
            }
        }

        if (!$this->is_success_response($response)) {
            $this->logger->log('error', 'client_sync_failed', 'Echec synchronisation client WooCommerce -> Manajeni.', ['order_id' => $order->get_id(), 'email' => $email, 'response' => $response]);
            $this->schedule_retry('client', ['order_id' => $order->get_id()]);
            return $payload;
        }

        $remote_id = $this->extract_remote_id($response, isset($remote_client) ? $remote_client : []);
        $this->mapper->save_client_mapping([
            'wp_user_id' => $customer_id,
            'customer_id' => $customer_id > 0 ? $customer_id : 'guest_' . $order->get_id(),
            'manajeni_client_id' => $remote_id,
            'email' => $email,
        ]);

        $payload['id'] = $remote_id;
        $this->logger->log('info', $customer_id > 0 ? 'customer_synced' : 'guest_customer_synced', 'Client WooCommerce synchronise vers Manajeni.', ['order_id' => $order->get_id(), 'customer_id' => $customer_id, 'manajeni_client_id' => $remote_id]);

        return $payload;
    }

    /**
     * Construit le payload catalogue.
     *
     * @param WC_Product $product Produit.
     * @return array
     */
    private function build_product_payload($product) {
        $sku = trim((string) $product->get_sku());
        $regular_price = '' !== (string) $product->get_regular_price() ? (float) $product->get_regular_price() : (float) $product->get_price();
        $tax_class = $product->get_tax_class();

        return [
            'reference' => $sku,
            'sku' => $sku,
            'name' => sanitize_text_field($product->get_name()),
            'description' => wp_strip_all_tags((string) $product->get_description()),
            'category' => $this->extract_product_category_name($product),
            'type' => 'service' === $product->get_type() ? 'service' : 'product',
            'price' => $regular_price,
            'tax' => $this->resolve_tax_rate_label($tax_class),
            'stock' => (int) $product->get_stock_quantity(),
            'stock_quantity' => (int) $product->get_stock_quantity(),
            'status' => 'publish' === $product->get_status() ? 'active' : 'inactive',
            'source' => 'woocommerce',
            'sync_status' => 'synchronise',
            'last_sync_at' => current_time('mysql'),
        ];
    }

    /**
     * Construit le payload commande.
     *
     * @param WC_Order $order Commande.
     * @param array    $client_payload Payload client.
     * @return array
     */
    private function build_order_payload($order, $client_payload) {
        $lines = [];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $sku = $product ? trim((string) $product->get_sku()) : '';
            $quantity = max(1, (int) $item->get_quantity());
            $unit_price_ht = (float) $order->get_item_subtotal($item, false, true);

            $lines[] = [
                'sku' => sanitize_text_field($sku),
                'reference' => sanitize_text_field($sku),
                'name' => sanitize_text_field($item->get_name()),
                'quantity' => $quantity,
                'unit_price_ht' => $unit_price_ht,
                'subtotal_ht' => (float) $item->get_total(),
                'tax_total' => (float) $item->get_total_tax(),
            ];
        }

        $remote_customer_id = isset($client_payload['id']) ? (int) $client_payload['id'] : 0;
        $wc_customer_id = (int) $order->get_customer_id();

        return [
            'external_id' => (string) $order->get_id(),
            'status' => sanitize_text_field($order->get_status()),
            'ordered_at' => $order->get_date_created() ? $order->get_date_created()->date('c') : current_time('c'),
            'notes' => sanitize_textarea_field((string) $order->get_customer_note()),
            'customer' => [
                'id' => $remote_customer_id > 0 ? $remote_customer_id : ($wc_customer_id > 0 ? $wc_customer_id : null),
                'email' => sanitize_email($order->get_billing_email()),
                'phone' => sanitize_text_field($order->get_billing_phone()),
                'first_name' => sanitize_text_field($order->get_billing_first_name()),
                'last_name' => sanitize_text_field($order->get_billing_last_name()),
            ],
            'billing' => [
                'company' => sanitize_text_field($order->get_billing_company()),
                'first_name' => sanitize_text_field($order->get_billing_first_name()),
                'last_name' => sanitize_text_field($order->get_billing_last_name()),
                'phone' => sanitize_text_field($order->get_billing_phone()),
                'address_1' => sanitize_text_field($order->get_billing_address_1()),
                'address_2' => sanitize_text_field($order->get_billing_address_2()),
                'city' => sanitize_text_field($order->get_billing_city()),
            ],
            'shipping' => [
                'company' => sanitize_text_field($order->get_shipping_company()),
                'first_name' => sanitize_text_field($order->get_shipping_first_name()),
                'last_name' => sanitize_text_field($order->get_shipping_last_name()),
                'address_1' => sanitize_text_field($order->get_shipping_address_1()),
                'address_2' => sanitize_text_field($order->get_shipping_address_2()),
                'city' => sanitize_text_field($order->get_shipping_city()),
            ],
            'line_items' => $lines,
        ];
    }

    /**
     * Applique un article catalogue Manajeni a un produit WooCommerce.
     *
     * @param WC_Product $product Produit WooCommerce.
     * @param array      $item Article catalogue.
     * @param string     $reference Reference SKU.
     * @return void
     */
    private function apply_catalogue_item_to_product($product, $item, $reference) {
        $name = '';
        foreach (['name', 'nom', 'label', 'title'] as $field) {
            if (!empty($item[$field])) {
                $name = sanitize_text_field((string) $item[$field]);
                break;
            }
        }

        if ('' === $name) {
            $name = $reference;
        }

        $description = '';
        foreach (['description', 'details', 'content'] as $field) {
            if (!empty($item[$field])) {
                $description = wp_kses_post((string) $item[$field]);
                break;
            }
        }

        $price = null;
        foreach (['price', 'prix', 'unit_price'] as $field) {
            if (isset($item[$field]) && '' !== (string) $item[$field]) {
                $price = wc_format_decimal($item[$field]);
                break;
            }
        }

        $stock_quantity = null;
        foreach (['stock_quantity', 'stock', 'quantity'] as $field) {
            if (isset($item[$field]) && '' !== (string) $item[$field]) {
                $stock_quantity = (int) $item[$field];
                break;
            }
        }

        $status = isset($item['status']) ? sanitize_key((string) $item['status']) : 'active';
        $category = !empty($item['category']) ? sanitize_text_field((string) $item['category']) : '';

        $lock_id = $product->get_id() ?: $reference;
        $this->mapper->set_lock('product', $lock_id);
        try {
            $product->set_name($name);
            $product->set_sku($reference);
            $product->set_description($description);
            $product->set_status('active' === $status ? 'publish' : 'draft');

            if (null !== $price && '' !== $price) {
                $product->set_regular_price($price);
                $product->set_price($price);
            }

            if (null !== $stock_quantity) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity($stock_quantity);
                $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
            }

            $product_id = $product->save();
            if ($product_id && '' !== $category) {
                $term = term_exists($category, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term($category, 'product_cat');
                }

                if (!is_wp_error($term)) {
                    $term_id = is_array($term) && isset($term['term_id']) ? (int) $term['term_id'] : (int) $term;
                    if ($term_id > 0) {
                        wp_set_object_terms($product_id, [$term_id], 'product_cat', false);
                    }
                }
            }
        } finally {
            $this->mapper->release_lock('product', $lock_id);
        }
    }

    /**
     * Importe l'image principale WooCommerce depuis image_url si disponible.
     *
     * @param int   $product_id Product ID.
     * @param array $item Article catalogue.
     * @return void
     */
    private function maybe_import_product_image($product_id, $item) {
        if (empty($item['image_url'])) {
            return;
        }

        $image_url = esc_url_raw((string) $item['image_url']);
        if ('' === $image_url) {
            return;
        }

        $current_image_url = (string) get_post_meta($product_id, '_manajeni_image_url', true);
        if ($current_image_url === $image_url && get_post_thumbnail_id($product_id)) {
            return;
        }

        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_id = media_sideload_image($image_url, $product_id, null, 'id');
        if (is_wp_error($attachment_id)) {
            throw new Exception($attachment_id->get_error_message());
        }

        set_post_thumbnail($product_id, (int) $attachment_id);
        update_post_meta($product_id, '_manajeni_image_url', $image_url);
    }

    /**
     * Extrait une reference stable depuis un article catalogue.
     *
     * @param array $item Article catalogue.
     * @return string
     */
    private function extract_catalogue_reference($item) {
        foreach (['reference', 'sku'] as $field) {
            if (!empty($item[$field])) {
                return sanitize_text_field((string) $item[$field]);
            }
        }

        return '';
    }

    /**
     * Planifie un retry.
     *
     * @param string $type Type.
     * @param array  $payload Payload.
     * @return void
     */
    private function schedule_retry($type, $payload) {
        $job = [
            'type' => sanitize_key($type),
            'scheduled_at' => time() + 300,
        ] + $payload;

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time() + 300, 'manajeni_sync_process_retry_job', [$job], 'manajeni');
            return;
        }

        $queue = get_option(self::RETRY_QUEUE_OPTION, []);
        if (!is_array($queue)) {
            $queue = [];
        }

        $queue[] = $job;
        update_option(self::RETRY_QUEUE_OPTION, $queue, false);
    }

    /**
     * Cherche un produit distant via reference.
     *
     * @param string $reference Reference.
     * @return array|null
     */
    private function find_remote_catalogue_item_by_reference($reference) {
        $reference = sanitize_text_field($reference);
        $items = $this->api_client->get_catalogue();

        foreach ($items as $item) {
            $item_reference = '';
            if (!empty($item['reference'])) {
                $item_reference = (string) $item['reference'];
            } elseif (!empty($item['sku'])) {
                $item_reference = (string) $item['sku'];
            }

            if ($item_reference === $reference) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Cherche un client distant.
     *
     * @param string $email Email.
     * @param string $name Nom.
     * @return array|null
     */
    private function find_remote_client($email, $name) {
        $clients = $this->api_client->get_clients();

        foreach ($clients as $client) {
            if ($email && !empty($client['email']) && strtolower((string) $client['email']) === strtolower($email)) {
                return $client;
            }

            if ($name && !empty($client['nom']) && (string) $client['nom'] === $name) {
                return $client;
            }
        }

        return null;
    }

    /**
     * Extrait un ID distant depuis la reponse.
     *
     * @param array $response Reponse.
     * @param array $fallback Fallback.
     * @return int
     */
    private function extract_remote_id($response, $fallback = []) {
        foreach ([
            ['data', 'id'],
            ['data', 'data', 'id'],
            ['id'],
        ] as $path) {
            $value = $this->read_array_path($response, $path);
            if ($value) {
                return (int) $value;
            }
        }

        if (!empty($fallback['id'])) {
            return (int) $fallback['id'];
        }

        return 0;
    }

    /**
     * Lit un chemin dans un tableau.
     *
     * @param array $array Tableau.
     * @param array $path Chemin.
     * @return mixed|null
     */
    private function read_array_path($array, $path) {
        $cursor = $array;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * Retourne la categorie principale.
     *
     * @param WC_Product $product Produit.
     * @return string
     */
    private function extract_product_category_name($product) {
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if (is_array($terms) && !empty($terms[0]->name)) {
            return sanitize_text_field($terms[0]->name);
        }

        return '';
    }

    /**
     * Normalise le taux de taxe.
     *
     * @param string $tax_class Classe.
     * @return string
     */
    private function resolve_tax_rate_label($tax_class) {
        $tax_class = '' === $tax_class ? 'standard' : $tax_class;
        $rates = WC_Tax::get_rates($tax_class);

        if (empty($rates)) {
            return '0';
        }

        $first = reset($rates);

        return isset($first['rate']) ? sanitize_text_field((string) $first['rate']) : '0';
    }

    /**
     * Retourne true si la reponse API est un succes.
     *
     * @param mixed $response Reponse.
     * @return bool
     */
    private function is_success_response($response) {
        return is_array($response) && !empty($response['success']);
    }

    /**
     * Resolve une commande.
     *
     * @param mixed $order Order ou ID.
     * @return WC_Order|null
     */
    private function resolve_order($order) {
        if (is_object($order) && method_exists($order, 'get_id')) {
            return $order;
        }

        if (is_numeric($order)) {
            return wc_get_order(absint($order));
        }

        return null;
    }

    /**
     * Indique si les appels API sortants sont possibles.
     *
     * @return bool
     */
    private function can_call_api() {
        $db = new Manajeni_DB();

        return $db->has_api_key();
    }
}
