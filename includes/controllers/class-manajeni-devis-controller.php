<?php
/**
 * Class Manajeni_Devis_Controller
 * Gère la logique métier du module Devis
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Devis_Controller {
    
    private $api_client;
    private $apps_handler;
    
    public function __construct() {
        if (!class_exists('Manajeni_Apps_Handler')) {
            require_once MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-apps-handler.php';
        }
        
        $this->api_client = manajeni_connector_get_api_client();
        $this->apps_handler = new Manajeni_Apps_Handler();
    }
    
    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }

        $message = '';
        $message_type = '';

        // 1. Actions logic
        if (isset($_POST['create_devis']) && check_admin_referer('mj_devis_save', 'mj_nonce')) {
            $client_id = intval($_POST['client_id']);
            $clients = $this->api_client->get_clients();
            $client = null;
            foreach ($clients as $c) {
                if ($c['id'] == $client_id) { $client = $c; break; }
            }

            $data = [
                'reference' => sanitize_text_field($_POST['reference']),
                'date' => current_time('Y-m-d'),
                'statut' => 'brouillon',
                'client' => $client ? [
                    'nom' => $client['nom'],
                    'email' => $client['email'],
                    'ice' => $client['ice'],
                    'ville' => $client['ville']
                ] : null,
                'montant_ht' => floatval($_POST['montant_ht']),
                'tva' => 20,
                'montant_ttc' => floatval($_POST['montant_ht']) * 1.2,
                'lignes' => [
                    ['description' => sanitize_text_field($_POST['reference']), 'quantite' => 1, 'prix_unitaire' => floatval($_POST['montant_ht']), 'tva' => 20, 'total_ht' => floatval($_POST['montant_ht'])]
                ]
            ];
            
            if (!empty($_POST['devis_id'])) {
                $data['id'] = intval($_POST['devis_id']);
                $this->api_client->update_devis($data);
                $message = __('Devis modifié avec succès !', 'manajeni-connector');
            } else {
                $this->api_client->create_devis($data);
                $message = __('Devis créé avec succès !', 'manajeni-connector');
            }
            $message_type = 'success';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_devis_' . $_GET['id'])) {
            $this->api_client->delete_devis($_GET['id']);
            $message = __('Devis supprimé.', 'manajeni-connector');
            $message_type = 'success';
        }

        // 2. Fetch Data
        $devis = $this->api_client->get_devis();
        $clients = $this->api_client->get_clients();

        // 3. Stats logic
        $stats = [
            ['icon' => '📄', 'value' => count($devis), 'label' => 'Total Devis'],
            ['icon' => '✅', 'value' => count(array_filter($devis, fn($d) => $d['statut'] === 'accepté')), 'label' => 'Acceptés'],
            ['icon' => '⏳', 'value' => count(array_filter($devis, fn($d) => $d['statut'] === 'envoyé')), 'label' => 'En attente'],
            ['icon' => '💰', 'value' => array_sum(array_column($devis, 'montant_ttc')) . ' DH', 'label' => 'Montant total']
        ];

        // 4. Load View
        global $apps_handler;
        $apps_handler = $this->apps_handler;
        
        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/devis.php';
    }
}
