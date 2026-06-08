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

        add_action('woocommerce_admin_process_product_object', [$this, 'handle_product_object_sync'], 20, 1);
        add_action('woocommerce_after_product_object_save', [$this, 'handle_product_object_sync_after_save'], 20, 2);
        add_action('save_post_product', [$this, 'handle_save_post_product'], 20, 3);
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
     * Hook WooCommerce produit avant sauvegarde.
     *
     * @param WC_Product $product Produit.
     * @return void
     */
    public function handle_product_object_sync($product) {
        $this->maybe_sync_product_to_manajeni($product, 'woocommerce_admin_process_product_object');
    }

    /**
     * Hook WooCommerce produit apres sauvegarde.
     *
     * @param WC_Product $product Produit.
     * @param mixed      $data_store Store.
     * @return void
     */
    public function handle_product_object_sync_after_save($product, $data_store = null) {
        $this->maybe_sync_product_to_manajeni($product, 'woocommerce_after_product_object_save');
    }

    /**
     * Hook save_post pour les produits.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post Post.
     * @param bool    $update Update.
     * @return void
     */
    public function handle_save_post_product($post_id, $post = null, $update = false) {
        if (wp_is_post_revision($post_id) || 'product' !== get_post_type($post_id)) {
            return;
        }

        $this->maybe_sync_product_to_manajeni($post_id, 'save_post_product');
    }

    /**
     * Compatibilite historique.
     *
     * @param int $product_id Product ID.
     * @return void
     */
    public function handle_product_update($product_id) {
        $this->maybe_sync_product_to_manajeni($product_id, 'woocommerce_update_product');
    }

    /**
     * Synchronise un produit WooCommerce vers Manajeni selon le hook appelant.
     *
     * @param mixed  $product_or_id Produit ou ID.
     * @param string $source_hook Hook source.
     * @return void
     */
    public function maybe_sync_product_to_manajeni($product_or_id, $source_hook = 'manual') {
        if (!$this->can_call_api()) {
            return;
        }

        $product = $this->resolve_product($product_or_id);
        if (!$product) {
            return;
        }

        $product_id = (int) $product->get_id();
        if ($this->mapper->has_lock('product', $product_id)) {
            return;
        }

        $result = $this->create_or_update_manajeni_from_wc_product($product, [
            'source_hook' => $source_hook,
        ]);

        if (!$result['success']) {
            if ('sku_empty' === $result['reason']) {
                return;
            }

            $this->schedule_retry('product', ['product_id' => $product_id]);
        }
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

        $product = $this->resolve_product($product);
        if (!$product) {
            return;
        }

        $product_id = (int) $product->get_id();
        if ($this->mapper->has_lock('product', $product_id)) {
            return;
        }

        $sku = trim((string) $product->get_sku());
        if ('' === $sku) {
            $this->logger->log('warning', 'product_sync_skipped_sku_empty', 'Stock ignore: SKU vide.', ['product_id' => $product_id]);
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
                $result = $this->create_or_update_manajeni_from_wc_product($product, ['source_hook' => 'stock_fallback']);
                if (!$result['success']) {
                    $this->logger->log('warning', 'stock_sync_skipped', 'Stock non synchronise: mapping Manajeni introuvable.', ['product_id' => $product_id, 'sku' => $sku]);
                    return;
                }
                $mapping = $this->mapper->find_product_mapping(['wc_product_id' => $product_id]);
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
            $this->logger->log('error', 'product_sync_failed', 'Echec synchronisation stock WooCommerce -> Manajeni.', ['product_id' => $product_id, 'sku' => $sku, 'response' => $response]);
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
     * Reconciliation bidirectionnelle Catalogue Manajeni <-> WooCommerce.
     *
     * @param int $batch Batch size.
     * @param int $offset Offset.
     * @return array
     */
    public function reconcile_catalogue_bidirectional($batch = 20, $offset = 0) {
        $batch = max(1, absint($batch));
        $offset = max(0, absint($offset));

        $result = [
            'success' => true,
            'processed' => 0,
            'offset' => $offset,
            'has_more' => false,
            'created_in_manajeni' => 0,
            'created_in_woocommerce' => 0,
            'updated_in_manajeni' => 0,
            'updated_in_woocommerce' => 0,
            'linked' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (!manajeni_connector_is_woocommerce_active()) {
            $result['success'] = false;
            $result['errors'][] = __('WooCommerce non installe.', 'manajeni-connector');
            return $result;
        }

        if (!$this->can_call_api()) {
            $result['success'] = false;
            $result['errors'][] = __('Connexion API Manajeni absente.', 'manajeni-connector');
            return $result;
        }

        $wc_index = $this->build_woocommerce_index();
        $manajeni_index = $this->build_manajeni_index();
        $skus = array_values(array_unique(array_merge(array_keys($wc_index), array_keys($manajeni_index))));
        sort($skus, SORT_NATURAL | SORT_FLAG_CASE);

        if (0 === $offset) {
            $this->logger->log('info', 'reconciliation_started', 'Reconciliation catalogue Manajeni <-> WooCommerce demarree.', ['total_candidates' => count($skus), 'batch' => $batch]);
        }

        $slice = array_slice($skus, $offset, $batch);
        $result['processed'] = count($slice);
        $result['offset'] = $offset + $result['processed'];
        $result['has_more'] = $result['offset'] < count($skus);

        foreach ($slice as $sku) {
            $wc_item = isset($wc_index[$sku]) ? $wc_index[$sku] : null;
            $manajeni_item = isset($manajeni_index[$sku]) ? $manajeni_index[$sku] : null;

            try {
                if ($wc_item && $manajeni_item) {
                    if ($this->link_existing_pair($sku, $wc_item, $manajeni_item)) {
                        $result['linked']++;
                    }

                    $winner = $this->determine_sync_winner($wc_item, $manajeni_item);
                    if ('manajeni' === $winner) {
                        $sync = $this->create_or_update_wc_product_from_manajeni($manajeni_item['raw'], [
                            'allow_create' => false,
                            'log_action' => true,
                        ]);
                        $this->merge_reconcile_sync_result($result, $sync, 'woocommerce');
                    } elseif ('woocommerce' === $winner) {
                        $product = wc_get_product($wc_item['product_id']);
                        $sync = $this->create_or_update_manajeni_from_wc_product($product, [
                            'source_hook' => 'manual_reconciliation',
                            'log_action' => true,
                        ]);
                        $this->merge_reconcile_sync_result($result, $sync, 'manajeni');
                    } else {
                        $result['skipped']++;
                    }

                    continue;
                }

                if ($wc_item) {
                    $product = wc_get_product($wc_item['product_id']);
                    $sync = $this->create_or_update_manajeni_from_wc_product($product, [
                        'source_hook' => 'manual_reconciliation',
                        'log_action' => true,
                    ]);
                    $this->merge_reconcile_sync_result($result, $sync, 'manajeni');
                    continue;
                }

                if ($manajeni_item) {
                    $sync = $this->create_or_update_wc_product_from_manajeni($manajeni_item['raw'], [
                        'allow_create' => true,
                        'log_action' => true,
                    ]);
                    $this->merge_reconcile_sync_result($result, $sync, 'woocommerce');
                    continue;
                }

                $result['skipped']++;
            } catch (Exception $exception) {
                $result['success'] = false;
                $result['errors'][] = sprintf('%s: %s', $sku, $exception->getMessage());
                $this->logger->log('error', 'product_sync_failed', 'Echec reconciliation produit.', ['sku' => $sku, 'message' => $exception->getMessage()]);
            }
        }

        if (!$result['has_more']) {
            $this->logger->log('info', 'reconciliation_finished', 'Reconciliation catalogue Manajeni <-> WooCommerce terminee.', [
                'processed' => $result['processed'],
                'offset' => $result['offset'],
                'created_in_manajeni' => $result['created_in_manajeni'],
                'created_in_woocommerce' => $result['created_in_woocommerce'],
                'updated_in_manajeni' => $result['updated_in_manajeni'],
                'updated_in_woocommerce' => $result['updated_in_woocommerce'],
                'linked' => $result['linked'],
                'skipped' => $result['skipped'],
                'errors' => count($result['errors']),
            ]);
        }

        if (!empty($result['errors'])) {
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * Compatibilite historique pour l'ancien bouton d'import.
     *
     * @return array
     */
    public function import_catalogue_from_manajeni() {
        $offset = 0;
        $aggregate = [
            'success' => true,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        do {
            $result = $this->reconcile_catalogue_bidirectional(20, $offset);
            $aggregate['success'] = $aggregate['success'] && $result['success'];
            $aggregate['created'] += (int) $result['created_in_woocommerce'];
            $aggregate['updated'] += (int) $result['updated_in_woocommerce'];
            $aggregate['skipped'] += (int) $result['skipped'];
            $aggregate['errors'] += count($result['errors']);
            $aggregate['messages'] = array_merge($aggregate['messages'], $result['errors']);
            $offset = (int) $result['offset'];
        } while (!empty($result['has_more']));

        return $aggregate;
    }

    /**
     * Cree ou met a jour un produit WooCommerce depuis Manajeni.
     *
     * @param array $item Article Manajeni.
     * @param array $options Options.
     * @return array
     */
    public function create_or_update_wc_product_from_manajeni($item, $options = []) {
        $reference = $this->extract_catalogue_reference($item);
        if ('' === $reference) {
            $this->logger->log('warning', 'product_sync_skipped_sku_empty', 'Produit Manajeni ignore: reference vide.', ['item' => $item]);
            return [
                'success' => false,
                'reason' => 'sku_empty',
                'action' => 'skipped',
            ];
        }

        $mapping = $this->find_product_mapping_for_sku($reference, $item);
        $product_id = wc_get_product_id_by_sku($reference);
        if (!$product_id && !empty($mapping['wc_product_id'])) {
            $product_id = (int) $mapping['wc_product_id'];
        }

        $product = $product_id ? wc_get_product($product_id) : null;
        if (!$product) {
            if (!empty($options['allow_create'])) {
                $product = new WC_Product_Simple();
                $product_id = 0;
            } else {
                return [
                    'success' => false,
                    'reason' => 'product_not_found',
                    'action' => 'skipped',
                ];
            }
        }

        $lock_id = $product_id ?: $reference;
        if ($product_id && $this->mapper->has_lock('product', $product_id)) {
            return [
                'success' => true,
                'reason' => 'locked',
                'action' => 'skipped',
            ];
        }

        $is_new = !$product_id;
        $this->mapper->lock('product', $lock_id, 120);

        try {
            $product_id = $this->apply_catalogue_item_to_product($product, $item, $reference);

            update_post_meta($product_id, '_manajeni_product_id', isset($item['id']) ? (int) $item['id'] : 0);
            update_post_meta($product_id, '_manajeni_reference', $reference);
            update_post_meta($product_id, '_manajeni_last_sync_at', current_time('mysql'));

            $this->maybe_import_product_image($product_id, $item);

            $this->mapper->save_product_mapping([
                'wc_product_id' => $product_id,
                'manajeni_product_id' => isset($item['id']) ? (int) $item['id'] : 0,
                'sku' => $reference,
            ]);
        } finally {
            $this->mapper->unlock('product', $lock_id);
        }

        $action = $is_new ? 'created' : 'updated';
        $log_action = $is_new ? 'wc_product_created_from_manajeni' : 'wc_product_updated_from_manajeni';
        $this->logger->log('info', $log_action, 'Produit WooCommerce synchronise depuis Manajeni.', ['product_id' => $product_id, 'sku' => $reference]);

        return [
            'success' => true,
            'action' => $action,
            'product_id' => $product_id,
            'sku' => $reference,
        ];
    }

    /**
     * Cree ou met a jour un article Manajeni depuis WooCommerce.
     *
     * @param mixed $product Produit WooCommerce ou ID.
     * @param array $options Options.
     * @return array
     */
    public function create_or_update_manajeni_from_wc_product($product, $options = []) {
        $product = $this->resolve_product($product);
        if (!$product) {
            return [
                'success' => false,
                'reason' => 'invalid_product',
                'action' => 'skipped',
            ];
        }

        $product_id = (int) $product->get_id();
        $sku = trim((string) $product->get_sku());

        if ('' === $sku) {
            $this->logger->log('warning', 'product_sync_skipped_sku_empty', 'Produit WooCommerce ignore: SKU vide.', ['product_id' => $product_id, 'source_hook' => isset($options['source_hook']) ? $options['source_hook'] : 'manual']);
            return [
                'success' => false,
                'reason' => 'sku_empty',
                'action' => 'skipped',
            ];
        }

        if ($this->mapper->has_lock('product', $product_id)) {
            return [
                'success' => true,
                'reason' => 'locked',
                'action' => 'skipped',
            ];
        }

        $payload = $this->build_product_payload($product);
        $mapping = $this->find_product_mapping_for_sku($sku);
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
            $action = 'updated';
        } elseif ($remote_item && !empty($remote_item['id'])) {
            $payload['id'] = (int) $remote_item['id'];
            $response = $this->api_client->update_catalogue_item($payload);
            $action = 'updated';
        } else {
            $response = $this->api_client->create_catalogue_item($payload);
            $action = 'created';
        }

        if (!$this->is_success_response($response)) {
            $this->logger->log('error', 'product_sync_failed', 'Echec synchronisation produit WooCommerce -> Manajeni.', ['product_id' => $product_id, 'sku' => $sku, 'response' => $response]);
            return [
                'success' => false,
                'reason' => 'api_error',
                'action' => $action,
                'response' => $response,
            ];
        }

        $remote_id = $this->extract_remote_id($response, $remote_item ?: []);
        $this->mapper->save_product_mapping([
            'wc_product_id' => $product_id,
            'manajeni_product_id' => $remote_id,
            'sku' => $sku,
        ]);

        update_post_meta($product_id, '_manajeni_product_id', $remote_id);
        update_post_meta($product_id, '_manajeni_reference', $sku);
        update_post_meta($product_id, '_manajeni_last_sync_at', current_time('mysql'));

        $log_action = 'created' === $action ? 'manajeni_product_created_from_wc' : 'manajeni_product_updated_from_wc';
        $this->logger->log('info', $log_action, 'Produit WooCommerce synchronise vers Manajeni.', ['product_id' => $product_id, 'sku' => $sku, 'manajeni_product_id' => $remote_id]);

        return [
            'success' => true,
            'action' => $action,
            'product_id' => $product_id,
            'manajeni_product_id' => $remote_id,
            'sku' => $sku,
        ];
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
                    $this->maybe_sync_product_to_manajeni((int) $job['product_id'], 'retry_queue');
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
     * Indexe les produits WooCommerce par SKU.
     *
     * @return array
     */
    private function build_woocommerce_index() {
        $index = [];
        $product_ids = wc_get_products([
            'limit' => -1,
            'status' => ['publish', 'draft'],
            'return' => 'ids',
        ]);

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            $sku = trim((string) $product->get_sku());
            if ('' === $sku) {
                continue;
            }

            $index[$sku] = [
                'product_id' => (int) $product_id,
                'sku' => $sku,
                'name' => sanitize_text_field($product->get_name()),
                'description' => wp_strip_all_tags((string) $product->get_description()),
                'price' => '' !== (string) $product->get_regular_price() ? (float) $product->get_regular_price() : (float) $product->get_price(),
                'stock_quantity' => null !== $product->get_stock_quantity() ? (int) $product->get_stock_quantity() : null,
                'status' => sanitize_key((string) $product->get_status()),
                'updated_at' => get_post_modified_time('mysql', false, $product_id),
                'raw' => $product,
            ];
        }

        return $index;
    }

    /**
     * Indexe le catalogue Manajeni par reference/SKU.
     *
     * @return array
     */
    private function build_manajeni_index() {
        $index = [];
        $catalogue = $this->api_client->get_catalogue();

        if (!is_array($catalogue)) {
            return $index;
        }

        foreach ($catalogue as $item) {
            $reference = $this->extract_catalogue_reference($item);
            if ('' === $reference) {
                continue;
            }

            $index[$reference] = [
                'reference' => $reference,
                'sku' => $reference,
                'name' => $this->extract_manajeni_name($item),
                'description' => $this->extract_manajeni_description($item),
                'price' => $this->extract_manajeni_price($item),
                'stock_quantity' => $this->extract_manajeni_stock($item),
                'status' => $this->extract_manajeni_status($item),
                'updated_at' => $this->extract_manajeni_updated_at($item),
                'id' => isset($item['id']) ? (int) $item['id'] : 0,
                'raw' => $item,
            ];
        }

        return $index;
    }

    /**
     * Applique un article catalogue Manajeni a un produit WooCommerce.
     *
     * @param WC_Product $product Produit WooCommerce.
     * @param array      $item Article catalogue.
     * @param string     $reference Reference SKU.
     * @return int
     */
    private function apply_catalogue_item_to_product($product, $item, $reference) {
        $name = $this->extract_manajeni_name($item);
        if ('' === $name) {
            $name = $reference;
        }

        $description = $this->extract_manajeni_description($item);
        $price = $this->extract_manajeni_price($item);
        $stock_quantity = $this->extract_manajeni_stock($item);
        $status = $this->extract_manajeni_status($item);
        $category = !empty($item['category']) ? sanitize_text_field((string) $item['category']) : '';

        $product->set_name($name);
        $product->set_sku($reference);
        $product->set_description($description);
        $product->set_status('active' === $status ? 'publish' : 'draft');

        if (null !== $price) {
            $product->set_regular_price(wc_format_decimal($price));
            $product->set_price(wc_format_decimal($price));
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

        return (int) $product_id;
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
            $item_reference = $this->extract_catalogue_reference($item);
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
     * Resolve un produit.
     *
     * @param mixed $product Product ou ID.
     * @return WC_Product|null
     */
    private function resolve_product($product) {
        if (is_object($product) && method_exists($product, 'get_id')) {
            return $product;
        }

        if (is_numeric($product)) {
            return wc_get_product(absint($product));
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
     * Retourne le nom Manajeni.
     *
     * @param array $item Article.
     * @return string
     */
    private function extract_manajeni_name($item) {
        foreach (['name', 'nom', 'label', 'title'] as $field) {
            if (!empty($item[$field])) {
                return sanitize_text_field((string) $item[$field]);
            }
        }

        return '';
    }

    /**
     * Retourne la description Manajeni.
     *
     * @param array $item Article.
     * @return string
     */
    private function extract_manajeni_description($item) {
        foreach (['description', 'details', 'content'] as $field) {
            if (!empty($item[$field])) {
                return wp_kses_post((string) $item[$field]);
            }
        }

        return '';
    }

    /**
     * Retourne le prix Manajeni.
     *
     * @param array $item Article.
     * @return float|null
     */
    private function extract_manajeni_price($item) {
        foreach (['price', 'prix', 'unit_price'] as $field) {
            if (isset($item[$field]) && '' !== (string) $item[$field]) {
                return (float) $item[$field];
            }
        }

        return null;
    }

    /**
     * Retourne le stock Manajeni.
     *
     * @param array $item Article.
     * @return int|null
     */
    private function extract_manajeni_stock($item) {
        foreach (['stock_quantity', 'stock', 'quantity'] as $field) {
            if (isset($item[$field]) && '' !== (string) $item[$field]) {
                return (int) $item[$field];
            }
        }

        return null;
    }

    /**
     * Retourne le statut Manajeni.
     *
     * @param array $item Article.
     * @return string
     */
    private function extract_manajeni_status($item) {
        return isset($item['status']) ? sanitize_key((string) $item['status']) : 'active';
    }

    /**
     * Retourne updated_at Manajeni.
     *
     * @param array $item Article.
     * @return string
     */
    private function extract_manajeni_updated_at($item) {
        foreach (['updated_at', 'last_sync_at', 'synced_at', 'created_at'] as $field) {
            if (!empty($item[$field])) {
                return sanitize_text_field((string) $item[$field]);
            }
        }

        return '';
    }

    /**
     * Retourne le mapping produit le plus pertinent pour un SKU.
     *
     * @param string     $sku SKU.
     * @param array|null $item Article Manajeni optionnel.
     * @return array|null
     */
    private function find_product_mapping_for_sku($sku, $item = null) {
        $mapping = $this->mapper->find_product_mapping(['sku' => $sku]);
        if ($mapping) {
            return $mapping;
        }

        if ($item && !empty($item['id'])) {
            $mapping = $this->mapper->find_product_mapping(['manajeni_product_id' => (int) $item['id']]);
            if ($mapping) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Lie un produit existant des deux cotes.
     *
     * @param string $sku SKU.
     * @param array  $wc_item Produit WooCommerce indexe.
     * @param array  $manajeni_item Article Manajeni indexe.
     * @return bool
     */
    private function link_existing_pair($sku, $wc_item, $manajeni_item) {
        $mapping = $this->find_product_mapping_for_sku($sku, $manajeni_item['raw']);
        $already_linked = $mapping
            && isset($mapping['wc_product_id'], $mapping['manajeni_product_id'])
            && (int) $mapping['wc_product_id'] === (int) $wc_item['product_id']
            && (int) $mapping['manajeni_product_id'] === (int) $manajeni_item['id'];

        $this->mapper->save_product_mapping([
            'wc_product_id' => (int) $wc_item['product_id'],
            'manajeni_product_id' => (int) $manajeni_item['id'],
            'sku' => $sku,
        ]);

        update_post_meta((int) $wc_item['product_id'], '_manajeni_product_id', (int) $manajeni_item['id']);
        update_post_meta((int) $wc_item['product_id'], '_manajeni_reference', $sku);
        update_post_meta((int) $wc_item['product_id'], '_manajeni_last_sync_at', current_time('mysql'));

        if (!$already_linked) {
            $this->logger->log('info', 'product_mapping_linked', 'Mapping produit lie sans doublon.', ['product_id' => (int) $wc_item['product_id'], 'manajeni_product_id' => (int) $manajeni_item['id'], 'sku' => $sku]);
        }

        return !$already_linked;
    }

    /**
     * Determine quel cote doit pousser ses champs catalogue.
     *
     * @param array $wc_item Produit WooCommerce indexe.
     * @param array $manajeni_item Article Manajeni indexe.
     * @return string
     */
    private function determine_sync_winner($wc_item, $manajeni_item) {
        $wc_updated = $this->normalize_timestamp($wc_item['updated_at']);
        $manajeni_updated = $this->normalize_timestamp($manajeni_item['updated_at']);

        if ($wc_updated && $manajeni_updated) {
            if ($manajeni_updated > $wc_updated) {
                return 'manajeni';
            }

            if ($wc_updated > $manajeni_updated) {
                return 'woocommerce';
            }

            return 'none';
        }

        return 'none';
    }

    /**
     * Normalise un timestamp en Unix.
     *
     * @param string $value Valeur.
     * @return int
     */
    private function normalize_timestamp($value) {
        if (empty($value)) {
            return 0;
        }

        $timestamp = strtotime((string) $value);

        return false !== $timestamp ? (int) $timestamp : 0;
    }

    /**
     * Integre un resultat de synchro dans l'agregat de reconciliation.
     *
     * @param array  $result Resultat global.
     * @param array  $sync Resultat unitaire.
     * @param string $target Target.
     * @return void
     */
    private function merge_reconcile_sync_result(&$result, $sync, $target) {
        if (empty($sync['success'])) {
            if (!empty($sync['reason']) && !in_array($sync['reason'], ['locked', 'sku_empty'], true)) {
                $result['success'] = false;
                $result['errors'][] = !empty($sync['sku']) ? $sync['sku'] . ': sync_failed' : 'sync_failed';
            } else {
                $result['skipped']++;
            }
            return;
        }

        if ('created' === $sync['action']) {
            if ('woocommerce' === $target) {
                $result['created_in_woocommerce']++;
            } else {
                $result['created_in_manajeni']++;
            }
            return;
        }

        if ('updated' === $sync['action']) {
            if ('woocommerce' === $target) {
                $result['updated_in_woocommerce']++;
            } else {
                $result['updated_in_manajeni']++;
            }
            return;
        }

        $result['skipped']++;
    }
}
