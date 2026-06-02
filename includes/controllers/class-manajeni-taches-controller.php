<?php
/**
 * Class Manajeni_Taches_Controller
 * Gère la logique métier du module Tâches
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Taches_Controller {

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

        if (isset($_POST['add_tache']) && check_admin_referer('mj_tache_save', 'mj_nonce')) {
            $data = [
                'titre'    => sanitize_text_field($_POST['titre']),
                'priorite' => sanitize_text_field($_POST['priorite']),
                'echeance' => sanitize_text_field($_POST['echeance']),
                'statut'   => 'a_faire'
            ];

            if (!empty($_POST['tache_id'])) {
                $data['id'] = intval($_POST['tache_id']);
                $this->api_client->update_tache($data);
                $message = __('Tâche modifiée !', 'manajeni-connector');
            } else {
                $this->api_client->create_tache($data);
                $message = __('Tâche ajoutée !', 'manajeni-connector');
            }
            $message_type = 'success';
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('mj_delete_tache_' . $_GET['id'])) {
            $this->api_client->delete_tache($_GET['id']);
            $message = __('Tâche supprimée.', 'manajeni-connector');
            $message_type = 'success';
        }

        $taches = $this->api_client->get_taches();
        $stats = [
            ['icon' => '✅', 'value' => count($taches),                                                                               'label' => 'Total tâches'],
            ['icon' => '🔄', 'value' => count(array_filter($taches, fn($t) => $t['statut'] === 'en_cours')),                          'label' => 'En cours'],
            ['icon' => '📋', 'value' => count(array_filter($taches, fn($t) => $t['statut'] === 'a_faire')),                           'label' => 'À faire'],
            ['icon' => '🚨', 'value' => count(array_filter($taches, fn($t) => $t['priorite'] === 'urgente')),                         'label' => 'Urgentes'],
        ];

        global $apps_handler;
        $apps_handler = $this->apps_handler;

        include MANAJENI_CONNECTOR_PATH . 'admin/views/apps/taches.php';
    }
}
