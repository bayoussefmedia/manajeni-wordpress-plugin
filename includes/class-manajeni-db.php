<?php
/**
 * Class Manajeni_DB
 * Gère les opérations sur la base de données WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_DB {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'manajeni_connection';
    }
    
    /**
     * Crypte une clé API
     */
    public function encrypt_api_key($api_key) {
        $encryption_key = wp_salt('auth');
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt($api_key, $method, $encryption_key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Décrypte une clé API
     */
    public function decrypt_api_key($encrypted_data) {
        $encryption_key = wp_salt('auth');
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, $method, $encryption_key, 0, $iv);
    }
    
    /**
     * Sauvegarde la connexion dans la base de données
     */
    public function save_connection($email, $api_key, $connection_date = null) {
        global $wpdb;
        
        $encrypted_key = $this->encrypt_api_key($api_key);
        $xml_key = $this->generate_xml_key($api_key);
        
        $connection_date = $connection_date ?: current_time('mysql');
        
        // Vérifier si l'email existe déjà
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE email = %s",
            sanitize_email($email)
        ));
        
        if ($existing) {
            // Mise à jour
            $result = $wpdb->update(
                $this->table_name,
                [
                    'api_key_crypted' => $encrypted_key,
                    'api_key_xml' => $xml_key,
                    'connection_date' => $connection_date,
                    'last_activity' => current_time('mysql'),
                    'status' => 'active'
                ],
                ['email' => sanitize_email($email)]
            );
        } else {
            // Insertion
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'email' => sanitize_email($email),
                    'api_key_crypted' => $encrypted_key,
                    'api_key_xml' => $xml_key,
                    'connection_date' => $connection_date,
                    'last_activity' => current_time('mysql'),
                    'status' => 'active'
                ]
            );
        }
        
        if ($result) {
            manajeni_connector_add_log('db_save_connection', 'success', 'Connexion sauvegardée pour ' . $email);
            return true;
        }
        
        manajeni_connector_add_log('db_save_connection', 'error', 'Erreur sauvegarde pour ' . $email);
        return false;
    }
    
    /**
     * Génère une clé XML différente de la clé DB
     */
    private function generate_xml_key($api_key) {
        return hash_hmac('sha256', $api_key . wp_salt('nonce'), wp_salt('auth'));
    }
    
    /**
     * Vérifie si l'utilisateur est connecté (session valide)
     */
    public function is_connected($email) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE email = %s AND status = 'active'",
            sanitize_email($email)
        ));
        
        if (!$result) {
            return false;
        }
        
        // Vérifier la date de dernière activité (session expire après 24h par défaut)
        $last_activity = strtotime($result->last_activity);
        $timeout = apply_filters('manajeni_session_timeout', 86400); // 24 heures
        
        if (time() - $last_activity > $timeout) {
            $this->disconnect($email);
            return false;
        }
        
        // Mettre à jour la dernière activité
        $this->update_activity($email);
        
        return true;
    }
    
    /**
     * Met à jour la dernière activité
     */
    public function update_activity($email) {
        global $wpdb;
        
        $wpdb->update(
            $this->table_name,
            ['last_activity' => current_time('mysql')],
            ['email' => sanitize_email($email)]
        );
    }
    
    /**
     * Déconnecte l'utilisateur
     */
    public function disconnect($email) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table_name,
            ['status' => 'disconnected'],
            ['email' => sanitize_email($email)]
        );
    }
    
    /**
     * Récupère la clé API cryptée pour un email
     */
    public function get_api_key($email) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT api_key_crypted FROM {$this->table_name} WHERE email = %s",
            sanitize_email($email)
        ));
        
        if ($result) {
            return $this->decrypt_api_key($result);
        }
        
        return null;
    }
    
    /**
     * Vérifie si les identifiants sont valides (vérification API)
     */
    public function verify_credentials($email, $password) {
        // Appel à l'API Manajeni pour vérifier le compte
        $simulation_mode = get_option('manajeni_simulation_mode', true);
        
        if ($simulation_mode) {
            // Mode simulation : accepter test@manajeni.com / password
            if ($email === 'test@manajeni.com' && $password === 'password') {
                return ['success' => true, 'message' => 'Compte valide'];
            }
            return ['success' => false, 'message' => 'Compte inexistant. Utilisez test@manajeni.com / password'];
        }
        
        // TODO: Appel API réelle
        return ['success' => false, 'message' => 'Mode API réelle - À implémenter'];
    }
}