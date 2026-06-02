<?php
/**
 * Class Manajeni_Rapports_Controller
 * Gère la logique métier du module Rapports
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Rapports_Controller {

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

        $rapports       = $this->api_client->get_rapports();
        $ca_total       = array_sum($rapports['chiffre_affaires']);
        $depenses_total = array_sum($rapports['depenses']);
        $benefice_total = array_sum($rapports['benefices']);
        $mois           = ['jan', 'fev', 'mar', 'avr'];

        $stats = [
            ['icon' => '📈', 'value' => number_format($ca_total, 0, ',', ' ') . ' DH', 'label' => 'Chiffre d\'affaires'],
            ['icon' => '📉', 'value' => number_format($depenses_total, 0, ',', ' ') . ' DH', 'label' => 'Dépenses'],
            ['icon' => '💰', 'value' => number_format($benefice_total, 0, ',', ' ') . ' DH', 'label' => 'Bénéfices'],
        ];

        global $apps_handler;
        $apps_handler = $this->apps_handler;

        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/rapports.php';
    }
}
