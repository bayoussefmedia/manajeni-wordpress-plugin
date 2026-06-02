<?php
/**
 * Class Manajeni_Session
 * Gère la session utilisateur pour tous les sous-menus
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Session {
    
    private $db;
    private $xml_handler;
    private $session_key = 'manajeni_user_session';
    
    public function __construct() {
        $this->db = new Manajeni_DB();
        $this->xml_handler = new Manajeni_XML_Handler();
    }
    
    /**
     * Vérifie la session pour chaque sous-menu
     * À appeler au début de chaque page
     */
    public function check_session() {
        // 1. Vérifier que l'utilisateur est admin WordPress
        if (!current_user_can('manage_options')) {
            $this->redirect_to_denied();
            return false;
        }
        
        // 2. Vérifier si c'est la première connexion
        if (get_option('manajeni_need_first_login', false)) {
            $this->redirect_to_first_login();
            return false;
        }
        
        // 3. Vérifier uniquement la présence d'une clé API valide
        if (!$this->db->has_api_key()) {
            $this->clear_session();
            $this->redirect_to_api_connection();
            return false;
        }
        
        // 4. Vérifier que l'URL API est déclarée dans le XML
        if (!$this->xml_handler->is_url_declared()) {
            $this->redirect_to_url_config();
            return false;
        }

        $this->db->update_activity();
        
        return true;
    }
    
    /**
     * Vérifie si l'utilisateur est actuellement connecté
     */
    public function is_connected() {
        return $this->db->is_connected() && $this->xml_handler->is_url_declared();
    }
    
    /**
     * Connecte le site apres verification API.
     */
    public function login($api_key, $api_url, $connection_date = null) {
        $connection_date = $connection_date ?: current_time('mysql');
        $result = $this->db->save_connection($api_key, $connection_date);
        
        if ($result) {
            $this->xml_handler->declare_url($api_url);
            $this->xml_handler->update_api_key_in_xml($api_key, $connection_date);
            update_option('manajeni_url', esc_url_raw($api_url), false);
            $this->set_session();
            manajeni_connector_add_log('session_login', 'success', 'Connexion API active pour ' . home_url());
            return true;
        }
        
        return false;
    }
    
    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        $this->db->disconnect();
        $this->xml_handler->clear_api_key_in_xml(false);
        $this->clear_session();
        manajeni_connector_add_log('session_logout', 'success', 'Connexion API retiree');
    }
    
    /**
     * Définit la session
     */
    private function set_session() {
        $session_data = [
            'user_id' => get_current_user_id(),
            'login_time' => current_time('timestamp'),
            'site_url' => home_url(),
        ];
        
        update_option($this->session_key, $session_data);
    }
    
    /**
     * Vide la session
     */
    private function clear_session() {
        delete_option($this->session_key);
    }
    
    /**
     * Redirection vers la page de première connexion
     */
    private function redirect_to_first_login() {
        if (function_exists('manajeni_safe_redirect')) {
            manajeni_safe_redirect(admin_url('admin.php?page=manajeni-first-login'));
        } else {
            wp_redirect(admin_url('admin.php?page=manajeni-first-login'));
        }
        exit;
    }
    
    /**
     * Redirection vers la page de login
     */
    private function redirect_to_login() {
        if (function_exists('manajeni_safe_redirect')) {
            manajeni_safe_redirect(admin_url('admin.php?page=manajeni-api-connection'));
        } else {
            wp_redirect(admin_url('admin.php?page=manajeni-api-connection'));
        }
        exit;
    }
    
    /**
     * Redirection vers la page de connexion API
     */
    private function redirect_to_api_connection() {
        if (function_exists('manajeni_safe_redirect')) {
            manajeni_safe_redirect(admin_url('admin.php?page=manajeni-api-connection'));
        } else {
            wp_redirect(admin_url('admin.php?page=manajeni-api-connection'));
        }
        exit;
    }
    
    /**
     * Redirection vers la configuration URL
     */
    private function redirect_to_url_config() {
        if (function_exists('manajeni_safe_redirect')) {
            manajeni_safe_redirect(admin_url('admin.php?page=manajeni-url-config'));
        } else {
            wp_redirect(admin_url('admin.php?page=manajeni-url-config'));
        }
        exit;
    }
    
    /**
     * Redirection accès refusé
     */
    private function redirect_to_denied() {
        wp_die(
            'Désolé, vous n\'avez pas les droits pour accéder à cette page.',
            'Accès refusé',
            ['response' => 403]
        );
    }
}
