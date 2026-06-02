<?php
/**
 * Class Manajeni_Projets_Controller
 * Gère la logique métier du module Projets
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Projets_Controller {
    
    private $api_client;
    private $apps_handler;
    
    public function __construct() {
        if (!class_exists('Manajeni_Fake_API_Client')) {
            require_once MANAJENI_CONNECTOR_PATH . 'services/class-manajeni-fake-api-client.php';
        }
        if (!class_exists('Manajeni_Apps_Handler')) {
            require_once MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-apps-handler.php';
        }
        
        $this->api_client = new Manajeni_Fake_API_Client();
        $this->apps_handler = new Manajeni_Apps_Handler();
    }
    
    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }

        $message = '';
        $message_type = '';

        // 1. Gérer les actions
        if (isset($_POST['create_projet']) && check_admin_referer('manajeni_projets_action')) {
            $client_id = intval($_POST['client_id']);
            $clients = $this->api_client->get_clients();
            $client_nom = 'Inconnu';
            foreach ($clients as $c) {
                if ($c['id'] == $client_id) {
                    $client_nom = $c['nom'];
                    break;
                }
            }

            $data = [
                'nom'        => sanitize_text_field($_POST['nom']),
                'client_id'  => $client_id,
                'client_nom' => $client_nom,
                'budget'     => floatval($_POST['budget']),
                'date_debut' => sanitize_text_field($_POST['date_debut']),
                'date_fin'   => sanitize_text_field($_POST['date_fin'])
            ];
            
            if (!empty($_POST['projet_id'])) {
                $data['id'] = intval($_POST['projet_id']);
                $this->api_client->update_projet($data);
                $message = 'Projet modifié avec succès !';
            } else {
                $data['depenses'] = 0;
                $data['avancement'] = 0;
                $data['statut'] = 'en_cours';
                $this->api_client->create_projet($data);
                $message = 'Projet créé avec succès !';
            }
            $message_type = 'success';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_projet_' . $_GET['id'])) {
            $this->api_client->delete_projet($_GET['id']);
            $message = __('Projet supprimé.', 'manajeni-connector');
            $message_type = 'success';
        }

        // 2. Fetch Data
        $projets = $this->api_client->get_projets();
        $clients = $this->api_client->get_clients();

        // 3. Process Stats
        $stats = [
            ['icon' => '🎯', 'value' => count($projets), 'label' => 'Total Projets'],
            ['icon' => '🔄', 'value' => count(array_filter($projets, fn($p) => $p['statut'] === 'en_cours')), 'label' => 'En cours'],
            ['icon' => '✅', 'value' => count(array_filter($projets, fn($p) => $p['statut'] === 'termine')), 'label' => 'Terminés'],
            ['icon' => '💰', 'value' => array_sum(array_column($projets, 'budget')) . ' DH', 'label' => 'Budget total']
        ];

        // 4. Load View
        global $apps_handler;
        $apps_handler = $this->apps_handler;
        
        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/projets.php';
    }
}
