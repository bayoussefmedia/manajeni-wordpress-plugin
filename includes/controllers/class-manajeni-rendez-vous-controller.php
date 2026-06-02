<?php
/**
 * Class Manajeni_Rendez_Vous_Controller
 * Gère la logique métier du module Rendez-vous
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Rendez_Vous_Controller {

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

        if (isset($_POST['add_rdv']) && check_admin_referer('mj_rdv_save', 'mj_nonce')) {
            $data = [
                'objet'    => sanitize_text_field($_POST['objet']),
                'date'     => sanitize_text_field($_POST['date']),
                'heure'    => sanitize_text_field($_POST['heure']),
                'lieu'     => sanitize_text_field($_POST['lieu']),
                'statut'   => 'planifie'
            ];

            if (!empty($_POST['rdv_id'])) {
                $data['id'] = intval($_POST['rdv_id']);
                $this->api_client->update_rendez_vous($data);
                $message = __('Rendez-vous modifié !', 'manajeni-connector');
            } else {
                $this->api_client->create_rendez_vous($data);
                $message = __('Rendez-vous planifié !', 'manajeni-connector');
            }
            $message_type = 'success';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_rdv_' . $_GET['id'])) {
            $this->api_client->delete_rendez_vous($_GET['id']);
            $message = __('Rendez-vous annulé.', 'manajeni-connector');
            $message_type = 'success';
        }

        $rendez_vous = $this->api_client->get_rendez_vous();
        $stats = [
            ['icon' => '📅', 'value' => count($rendez_vous),                                                                          'label' => 'Total RDV'],
            ['icon' => '📋', 'value' => count(array_filter($rendez_vous, fn($r) => $r['statut'] === 'planifie')),                     'label' => 'Planifiés'],
        ];

        global $apps_handler;
        $apps_handler = $this->apps_handler;

        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/rendez_vous.php';
    }
}
