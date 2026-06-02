<?php
/**
 * Class Manajeni_Charges_Controller
 * Gère la logique métier du module Charges
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Charges_Controller {
    
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
        if (isset($_POST['add_charge']) && check_admin_referer('mj_charge_save', 'mj_nonce')) {
            $fournisseur_id = intval($_POST['fournisseur_id']);
            $fournisseurs = $this->api_client->get_fournisseurs();
            $fournisseur = null;
            foreach ($fournisseurs as $f) {
                if ($f['id'] == $fournisseur_id) { $fournisseur = $f; break; }
            }

            $data = [
                'reference' => sanitize_text_field($_POST['reference']),
                'fournisseur' => $fournisseur ? [
                    'nom' => $fournisseur['nom'],
                    'ville' => $fournisseur['ville']
                ] : null,
                'date' => current_time('Y-m-d'),
                'statut' => 'à_payer',
                'montant_ht' => floatval($_POST['montant_ht']),
                'tva' => 20,
                'montant_ttc' => floatval($_POST['montant_ht']) * 1.2,
                'lignes' => [
                    ['description' => sanitize_text_field($_POST['reference']), 'quantite' => 1, 'prix_unitaire' => floatval($_POST['montant_ht']), 'tva' => 20, 'total_ht' => floatval($_POST['montant_ht'])]
                ]
            ];
            
            if (!empty($_POST['charge_id'])) {
                $data['id'] = intval($_POST['charge_id']);
                $this->api_client->update_charge($data);
                $message = __('Charge modifiée !', 'manajeni-connector');
            } else {
                $this->api_client->create_charge($data);
                $message = __('Charge enregistrée !', 'manajeni-connector');
            }
            $message_type = 'success';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_charge_' . $_GET['id'])) {
            $this->api_client->delete_charge($_GET['id']);
            $message = __('Charge supprimée.', 'manajeni-connector');
            $message_type = 'success';
        }

        // 2. Fetch Data
        $charges        = $this->api_client->get_charges();
        $fournisseurs   = $this->api_client->get_fournisseurs();

        $total_ttc      = array_sum(array_column($charges, 'montant_ttc'));
        $total_ht       = array_sum(array_column($charges, 'montant_ht'));
        $a_payer        = array_filter($charges, fn($c) => $c['statut'] === 'à_payer');
        
        $stats = [
            ['icon' => '📉', 'value' => count($charges), 'label' => __('Total charges', 'manajeni-connector')],
            ['icon' => '💰', 'value' => number_format($total_ht, 0, ',', ' ') . ' DH', 'label' => __('Total HT', 'manajeni-connector')],
            ['icon' => '📊', 'value' => number_format($total_ttc, 0, ',', ' ') . ' DH', 'label' => __('Total TTC', 'manajeni-connector')],
            ['icon' => '⚠️', 'value' => count($a_payer), 'label' => __('À payer', 'manajeni-connector')],
        ];

        global $apps_handler;
        $apps_handler = $this->apps_handler;
        
        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/charges.php';
    }
}
