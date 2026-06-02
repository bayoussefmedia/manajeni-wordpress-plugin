<?php
/**
 * Class Manajeni_Clients_Controller
 * Gère la logique métier du module Clients
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Clients_Controller {
    
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
    
    /**
     * Point d'entrée principal de la page
     */
    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }

        $message = '';
        $message_type = '';

        // 1. Gérer les actions (POST/GET Logic)
        $this->handle_actions($message, $message_type);

        // 2. Récupérer les données (Model Data)
        $clients = $this->api_client->get_clients();
        
        // 3. Traiter les données (Filtrage/Stats)
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        if ($search) {
            $clients = array_filter($clients, function($c) use ($search) {
                return stripos($c['nom'], $search) !== false || stripos($c['email'], $search) !== false;
            });
        }

        $stats = [
            ['icon' => '👥', 'value' => count($clients), 'label' => 'Total Clients'],
            ['icon' => '⭐', 'value' => count(array_filter($clients, fn($c) => $c['type'] === 'premium')), 'label' => 'Premium'],
            ['icon' => '📧', 'value' => count(array_filter($clients, fn($c) => !empty($c['email']))), 'label' => 'Email validé'],
            ['icon' => '📍', 'value' => count(array_unique(array_column($clients, 'ville'))), 'label' => 'Villes']
        ];

        // 4. Charger la Vue (Injection des variables)
        // On rend les variables disponibles pour la vue
        global $apps_handler;
        $apps_handler = $this->apps_handler;
        
        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/clients.php';
    }

    /**
     * Gère les soumissions de formulaires et suppressions
     */
    private function handle_actions(&$message, &$message_type) {
        // Suppression
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_client_' . $_GET['id'])) {
            $result = $this->api_client->delete_client(intval($_GET['id']));
            if ($result['success']) {
                $message = __('Client supprimé avec succès !', 'manajeni-connector');
                $message_type = 'success';
            }
        }

        // Ajout / Modification
        if (isset($_POST['add_client']) && check_admin_referer('mj_client_save', 'mj_nonce')) {
            $data = [
                'nom'       => sanitize_text_field($_POST['nom']),
                'email'     => sanitize_email($_POST['email']),
                'telephone' => sanitize_text_field($_POST['telephone']),
                'adresse'   => sanitize_textarea_field($_POST['adresse']),
                'ville'     => sanitize_text_field($_POST['ville']),
                'ice'       => sanitize_text_field($_POST['ice']),
                'type'      => sanitize_text_field($_POST['type']),
            ];
            
            if (!empty($_POST['client_id'])) {
                $data['id'] = intval($_POST['client_id']);
                $this->api_client->update_client($data);
                $message = __('Client modifié avec succès !', 'manajeni-connector');
            } else {
                $data['chiffre_affaire'] = 0;
                $data['statut'] = 'actif';
                $this->api_client->create_client($data);
                $message = __('Client ajouté avec succès !', 'manajeni-connector');
            }
            $message_type = 'success';
        }
    }
}
