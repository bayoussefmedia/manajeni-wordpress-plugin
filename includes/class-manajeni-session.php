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
        
        // 3. Vérifier la session en cours
        $user_email = $this->get_current_user_email();
        
        if (!$user_email) {
            $this->redirect_to_login();
            return false;
        }
        
        // 4. Vérifier que la session est valide dans la base de données
        if (!$this->db->is_connected($user_email)) {
            $this->clear_session();
            $this->redirect_to_login();
            return false;
        }
        
        // 5. Vérifier que l'API Key existe et est valide
        $api_key = $this->db->get_api_key($user_email);
        if (!$api_key) {
            $this->redirect_to_api_connection();
            return false;
        }
        
        // 6. Vérifier que l'URL est déclarée dans le XML
        if (!$this->xml_handler->is_url_declared()) {
            $this->redirect_to_url_config();
            return false;
        }
        
        return true;
    }
    
    /**
     * Vérifie si l'utilisateur est actuellement connecté
     */
    public function is_connected() {
        $user_email = $this->get_current_user_email();
        if (!$user_email) {
            return false;
        }
        return $this->db->is_connected($user_email);
    }
    
    /**
     * Connecte l'utilisateur (après vérification API)
     */
    public function login($email, $api_key, $connection_date = null) {
        // Sauvegarder dans la base de données
        $result = $this->db->save_connection($email, $api_key, $connection_date);
        
        if ($result) {
            // Mettre à jour le XML avec une clé différente
            $this->xml_handler->update_api_key_in_xml($api_key, $connection_date);
            
            // Stocker en session
            $this->set_session($email);
            
            manajeni_connector_add_log('session_login', 'success', 'Utilisateur connecté: ' . $email);
            return true;
        }
        
        return false;
    }
    
    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        $email = $this->get_current_user_email();
        
        if ($email) {
            $this->db->disconnect($email);
        }
        
        $this->clear_session();
        manajeni_connector_add_log('session_logout', 'success', 'Utilisateur déconnecté');
    }
    
    /**
     * Définit la session
     */
    private function set_session($email) {
        $session_data = [
            'email' => $email,
            'login_time' => current_time('timestamp'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        update_option($this->session_key, $session_data);
    }
    
    /**
     * Récupère l'email de l'utilisateur connecté
     */
    private function get_current_user_email() {
        $session = get_option($this->session_key, []);
        
        if (isset($session['email']) && !empty($session['email'])) {
            return $session['email'];
        }
        
        return null;
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
            manajeni_safe_redirect(admin_url('admin.php?page=manajeni-first-login'));
        } else {
            wp_redirect(admin_url('admin.php?page=manajeni-first-login'));
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