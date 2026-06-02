<?php
/**
 * Class Manajeni_DB
 * Gere les operations sur la base de donnees WordPress.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_DB {

    /**
     * Crypte une valeur sensible.
     *
     * @param string $value Valeur brute.
     * @return string
     */
    public function encrypt_secret($value) {
        $encryption_key = wp_salt('auth');
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($value, $method, $encryption_key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypte une valeur sensible.
     *
     * @param string $encrypted_data Valeur cryptee.
     * @return string|null
     */
    public function decrypt_secret($encrypted_data) {
        if (empty($encrypted_data)) {
            return null;
        }

        $encryption_key = wp_salt('auth');
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $data = base64_decode($encrypted_data, true);

        if (false === $data || strlen($data) <= $iv_length) {
            return null;
        }

        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        return openssl_decrypt($encrypted, $method, $encryption_key, 0, $iv);
    }

    /**
     * Sauvegarde la connexion dans la base de donnees.
     *
     * @param string      $api_key Cle API.
     * @param string      $api_secret Secret API.
     * @param string      $api_email Email API.
     * @param string|null $connection_date Date de connexion.
     * @return bool
     */
    public function save_connection($api_key, $api_secret = '', $api_email = '', $connection_date = null) {
        $connection_date = $connection_date ?: current_time('mysql');

        update_option('manajeni_api_key_crypted', $this->encrypt_secret($api_key), false);
        update_option('manajeni_api_key_masked', $this->mask_sensitive_value($api_key), false);
        update_option('manajeni_api_secret_crypted', !empty($api_secret) ? $this->encrypt_secret($api_secret) : '', false);
        update_option('manajeni_api_secret_masked', !empty($api_secret) ? $this->mask_sensitive_value($api_secret) : '', false);
        update_option('manajeni_api_email', sanitize_email($api_email), false);
        update_option('manajeni_connected', true, false);
        update_option('manajeni_last_connection', $connection_date, false);

        manajeni_connector_add_log('db_save_connection', 'success', 'Connexion API sauvegardee');

        return true;
    }

    /**
     * Verifie si une connexion API est active.
     *
     * @return bool
     */
    public function is_connected() {
        return (bool) get_option('manajeni_connected', false) && $this->has_api_key();
    }

    /**
     * Met a jour la date de derniere activite.
     *
     * @return void
     */
    public function update_activity() {
        update_option('manajeni_last_activity', current_time('mysql'), false);
    }

    /**
     * Deconnecte le site.
     *
     * @return bool
     */
    public function disconnect() {
        delete_option('manajeni_api_key_crypted');
        delete_option('manajeni_api_key_masked');
        delete_option('manajeni_api_secret_crypted');
        delete_option('manajeni_api_secret_masked');
        delete_option('manajeni_api_email');
        delete_option('manajeni_connected');
        delete_option('manajeni_last_connection');
        delete_option('manajeni_last_activity');

        manajeni_connector_add_log('db_disconnect', 'success', 'Connexion API supprimee');

        return true;
    }

    /**
     * Recupere la cle API decryptee.
     *
     * @return string|null
     */
    public function get_api_key() {
        return $this->decrypt_secret(get_option('manajeni_api_key_crypted', ''));
    }

    /**
     * Indique si une cle API existe.
     *
     * @return bool
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
     * Recupere le secret API decrypte.
     *
     * @return string|null
     */
    public function get_api_secret() {
        return $this->decrypt_secret(get_option('manajeni_api_secret_crypted', ''));
    }

    /**
     * Recupere une version masquee du secret API.
     *
     * @return string
     */
    public function get_masked_api_secret() {
        return (string) get_option('manajeni_api_secret_masked', '');
    }

    /**
     * Recupere l'email API.
     *
     * @return string
     */
    public function get_api_email() {
        return (string) get_option('manajeni_api_email', '');
    }

    /**
     * Masque une valeur sensible pour affichage.
     *
     * @param string $value Valeur brute.
     * @return string
     */
    private function mask_sensitive_value($value) {
        $length = strlen($value);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($value, -4);
    }
}
