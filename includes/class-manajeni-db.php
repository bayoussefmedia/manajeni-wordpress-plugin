<?php
/**
 * Class Manajeni_DB
 * Gère les opérations sur la base de données WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_DB {

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
        if (empty($encrypted_data)) {
            return null;
        }

        $encryption_key = wp_salt('auth');
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        
        $data = base64_decode($encrypted_data);
        if (false === $data || strlen($data) <= $iv_length) {
            return null;
        }

        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, $method, $encryption_key, 0, $iv);
    }
    
    /**
     * Sauvegarde la connexion dans la base de données
     */
    public function save_connection($api_key, $connection_date = null) {
        $encrypted_key = $this->encrypt_api_key($api_key);
        $connection_date = $connection_date ?: current_time('mysql');

        update_option('manajeni_api_key_crypted', $encrypted_key, false);
        update_option('manajeni_api_key_masked', $this->mask_api_key($api_key), false);
        update_option('manajeni_connected', true, false);
        update_option('manajeni_last_connection', $connection_date, false);

        manajeni_connector_add_log('db_save_connection', 'success', 'Connexion API sauvegardee');

        return true;
    }
    
    /**
     * Verifie si une connexion API est active.
     */
    public function is_connected() {
        return (bool) get_option('manajeni_connected', false) && $this->has_api_key();
    }
    
    /**
     * Met a jour la date de derniere activite.
     */
    public function update_activity() {
        update_option('manajeni_last_activity', current_time('mysql'), false);
    }
    
    /**
     * Deconnecte le site.
     */
    public function disconnect() {
        delete_option('manajeni_api_key_crypted');
        delete_option('manajeni_api_key_masked');
        delete_option('manajeni_connected');
        delete_option('manajeni_last_connection');
        delete_option('manajeni_last_activity');

        manajeni_connector_add_log('db_disconnect', 'success', 'Connexion API supprimee');

        return true;
    }
    
    /**
     * Recupere la cle API decryptee.
     */
    public function get_api_key() {
        return $this->decrypt_api_key(get_option('manajeni_api_key_crypted', ''));
    }
    
    /**
     * Indique si une cle API existe.
     */
    public function has_api_key() {
        return !empty($this->get_api_key());
    }

    /**
     * Recupere une version masquee de la cle API.
     *
     * @return string
     */
    public function get_masked_api_key() {
        return (string) get_option('manajeni_api_key_masked', '');
    }

    /**
     * Masque une cle API pour affichage.
     *
     * @param string $api_key Cle API.
     * @return string
     */
    private function mask_api_key($api_key) {
        $length = strlen($api_key);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($api_key, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($api_key, -4);
    }
}
