<?php
/**
 * Class Manajeni_Catalogue_Controller
 * Gère la logique métier du module Catalogue
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Catalogue_Controller {

    private $api_client;
    private $apps_handler;

    public function __construct() {
        if (!class_exists('Manajeni_Fake_API_Client')) {
            require_once MANAJENI_CONNECTOR_PATH . 'services/class-manajeni-fake-api-client.php';
        }
        if (!class_exists('Manajeni_Apps_Handler')) {
            require_once MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-apps-handler.php';
        }
        $this->api_client   = new Manajeni_Fake_API_Client();
        $this->apps_handler = new Manajeni_Apps_Handler();
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }

        $message = '';
        $message_type = '';

        // 1. Actions logic
        if (isset($_POST['add_article']) && check_admin_referer('mj_catalogue_save', 'mj_nonce')) {
            $data = [
                'name'     => sanitize_text_field($_POST['name']),
                'sku'      => sanitize_text_field($_POST['sku']),
                'type'     => sanitize_text_field($_POST['type']),
                'price'    => floatval($_POST['price']),
                'tax'      => intval($_POST['tax']),
                'category' => sanitize_text_field($_POST['category']),
                'status'   => 'active'
            ];

            if (!empty($_POST['article_id'])) {
                $data['id'] = intval($_POST['article_id']);
                $this->api_client->update_catalogue_item($data);
                $message = __('Article modifié !', 'manajeni-connector');
            } else {
                $this->api_client->create_catalogue_item($data);
                $message = __('Article ajouté au catalogue !', 'manajeni-connector');
            }
            $message_type = 'success';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_catalogue_' . $_GET['id'])) {
            $this->api_client->delete_catalogue_item($_GET['id']);
            $message = __('Article supprimé.', 'manajeni-connector');
            $message_type = 'success';
        }

        // 2. Fetch Data
        $catalogue = $this->api_client->get_catalogue();
        
        $stats = [
            ['icon' => '📦', 'value' => count($catalogue), 'label' => __('Total articles', 'manajeni-connector')],
            ['icon' => '🛠️', 'value' => count(array_filter($catalogue, fn($c) => $c['type'] === 'service')), 'label' => __('Services', 'manajeni-connector')],
            ['icon' => '📦', 'value' => count(array_filter($catalogue, fn($c) => $c['type'] === 'product')), 'label' => __('Produits', 'manajeni-connector')],
            ['icon' => '✅', 'value' => count(array_filter($catalogue, fn($c) => $c['status'] === 'active')), 'label' => __('Actifs', 'manajeni-connector')],
        ];

        global $apps_handler;
        $apps_handler = $this->apps_handler;

        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/catalogue.php';
    }
}
