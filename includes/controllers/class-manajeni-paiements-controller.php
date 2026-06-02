<?php
/**
 * Class Manajeni_Paiements_Controller
 * Gère la logique métier du module Paiements
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Paiements_Controller {
    
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
        $message = '';
        $message_type = '';

        // 1. Actions logic
        if (isset($_POST['add_paiement']) && check_admin_referer('mj_paiement_save', 'mj_nonce')) {
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
                'categorie' => sanitize_text_field($_POST['categorie']),
                'date' => current_time('Y-m-d'),
                'mode' => sanitize_text_field($_POST['mode']),
                'montant_ht' => floatval($_POST['montant_ht']),
                'tva' => 20,
                'montant_ttc' => floatval($_POST['montant_ht']) * 1.2,
                'lignes' => [
                    ['description' => sanitize_text_field($_POST['reference']), 'quantite' => 1, 'prix_unitaire' => floatval($_POST['montant_ht']), 'tva' => 20, 'total_ht' => floatval($_POST['montant_ht'])]
                ]
            ];
            
            if (!empty($_POST['paiement_id'])) {
                $data['id'] = intval($_POST['paiement_id']);
                $this->api_client->update_paiement($data);
                $message = __('Paiement modifié avec succès !', 'manajeni-connector');
            } else {
                $this->api_client->create_paiement($data);
                $message = __('Paiement ajouté avec succès !', 'manajeni-connector');
            }
            $message_type = 'success';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_paiement_' . $_GET['id'])) {
            $this->api_client->delete_paiement($_GET['id']);
            $message = __('Paiement supprimé.', 'manajeni-connector');
            $message_type = 'success';
        }

        // 2. Fetch Data
        $paiements    = $this->api_client->get_paiements();
        $fournisseurs = $this->api_client->get_fournisseurs();

        $total_ttc    = array_sum(array_column($paiements, 'montant_ttc'));
        $total_ht     = array_sum(array_column($paiements, 'montant_ht'));
        $total_tva    = $total_ttc - $total_ht;
        
        $stats = [
            ['icon' => '💸', 'value' => count($paiements), 'label' => __('Total paiements', 'manajeni-connector')],
            ['icon' => '💰', 'value' => number_format($total_ht, 0, ',', ' ') . ' DH', 'label' => __('Total HT', 'manajeni-connector')],
            ['icon' => '🏛️', 'value' => number_format($total_tva, 0, ',', ' ') . ' DH', 'label' => __('TVA', 'manajeni-connector')],
            ['icon' => '📊', 'value' => number_format($total_ttc, 0, ',', ' ') . ' DH', 'label' => __('Total TTC', 'manajeni-connector')],
        ];

        global $apps_handler;
        $apps_handler = $this->apps_handler;
        
        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/paiements.php';
    }
}
