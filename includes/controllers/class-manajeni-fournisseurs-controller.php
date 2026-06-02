<?php
/**
 * Class Manajeni_Fournisseurs_Controller
 * Gère la logique métier du module Fournisseurs
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Fournisseurs_Controller {

    private $api_client;
    private $apps_handler;

    public function __construct() {
        if (!class_exists('Manajeni_Apps_Handler')) {
            require_once MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-apps-handler.php';
        }
        $this->api_client   = manajeni_connector_get_api_client();
        $this->apps_handler = new Manajeni_Apps_Handler();
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }

        $message = '';
        $message_type = '';

        // 1. Actions logic
        if (isset($_POST['add_fournisseur']) && check_admin_referer('mj_fournisseur_save', 'mj_nonce')) {
            $data = [
                'nom'       => sanitize_text_field($_POST['nom']),
                'categorie' => sanitize_text_field($_POST['categorie']),
                'ville'     => sanitize_text_field($_POST['ville']),
                'ice'       => sanitize_text_field($_POST['ice']),
                'statut'    => 'actif'
            ];

            if (!empty($_POST['fournisseur_id'])) {
                $data['id'] = intval($_POST['fournisseur_id']);
                $this->api_client->update_fournisseur($data);
                $message = __('Fournisseur modifié !', 'manajeni-connector');
            } else {
                $this->api_client->create_fournisseur($data);
                $message = __('Fournisseur ajouté !', 'manajeni-connector');
            }
            $message_type = 'success';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_fournisseur_' . $_GET['id'])) {
            $this->api_client->delete_fournisseur($_GET['id']);
            $message = __('Fournisseur supprimé.', 'manajeni-connector');
            $message_type = 'success';
        }

        // 2. Fetch Data
        $fournisseurs = $this->api_client->get_fournisseurs();
        $categories   = array_unique(array_column($fournisseurs, 'categorie'));
        
        $stats = [
            ['icon' => '🏭', 'value' => count($fournisseurs), 'label' => __('Fournisseurs', 'manajeni-connector')],
            ['icon' => '📂', 'value' => count($categories),   'label' => __('Catégories', 'manajeni-connector')],
        ];

        global $apps_handler;
        $apps_handler = $this->apps_handler;

        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/fournisseurs.php';
    }
}
