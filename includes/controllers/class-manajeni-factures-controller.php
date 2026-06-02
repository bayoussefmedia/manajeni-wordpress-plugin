<?php
/**
 * Class Manajeni_Factures_Controller
 * Gère la logique métier du module Factures
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Factures_Controller {
    
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

        // 1. Actions logic
        if (isset($_POST['create_facture']) && check_admin_referer('mj_facture_save', 'mj_nonce')) {
            $client_id = intval($_POST['client_id']);
            $clients = $this->api_client->get_clients();
            $client = null;
            foreach ($clients as $c) {
                if ($c['id'] == $client_id) { $client = $c; break; }
            }

            $data = [
                'reference' => sanitize_text_field($_POST['reference']),
                'date' => current_time('Y-m-d'),
                'date_echeance' => date('Y-m-d', strtotime('+30 days')),
                'statut' => 'impayée',
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
            
            if (!empty($_POST['facture_id'])) {
                $data['id'] = intval($_POST['facture_id']);
                $this->api_client->update_facture($data);
                $message = __('Facture modifiée avec succès !', 'manajeni-connector');
            } else {
                $this->api_client->create_facture($data);
                $message = __('Facture créée avec succès !', 'manajeni-connector');
            }
            $message_type = 'success';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_facture_' . $_GET['id'])) {
            $this->api_client->delete_facture($_GET['id']);
            $message = __('Facture supprimée.', 'manajeni-connector');
            $message_type = 'success';
        }

        // 2. Fetch Data
        $factures = $this->api_client->get_factures();
        $clients = $this->api_client->get_clients();

        // 3. Stats logic
        $stats = [
            ['icon' => '🧾', 'value' => count($factures), 'label' => __('Total Factures', 'manajeni-connector')],
            ['icon' => '✅', 'value' => count(array_filter($factures, fn($f) => $f['statut'] === 'payée')), 'label' => __('Payées', 'manajeni-connector')],
            ['icon' => '⚠️', 'value' => count(array_filter($factures, fn($f) => $f['statut'] === 'impayée')), 'label' => __('Impayées', 'manajeni-connector')],
            ['icon' => '💰', 'value' => number_format(array_sum(array_column($factures, 'montant_ttc')), 0, ',', ' ') . ' DH', 'label' => __('Montant total', 'manajeni-connector')]
        ];

        // 3. Load View
        global $apps_handler;
        $apps_handler = $this->apps_handler;
        
        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/factures.php';
    }
}
