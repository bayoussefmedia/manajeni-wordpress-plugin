<?php
/**
 * Class Manajeni_XML_Handler
 * Gère les fichiers XML pour Manajeni
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_XML_Handler {
    
    private $xml_file_path;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->xml_file_path = $upload_dir['basedir'] . '/manajeni/config.xml';
    }
    
    /**
     * Met à jour la clé API dans le fichier XML
     */
    public function update_api_key_in_xml($api_key, $connection_date) {
        if (!file_exists($this->xml_file_path)) {
            $this->create_default_xml();
        }
        
        $xml_content = simplexml_load_file($this->xml_file_path);
        
        if ($xml_content) {
            // Générer une clé XML différente de la clé DB
            $xml_key = hash_hmac('sha256', $api_key . $connection_date, wp_salt('auth'));
            
            $xml_content->api->key = $xml_key;
            $xml_content->api->key['encrypted'] = 'true';
            $xml_content->api->date_connection = $connection_date;
            $xml_content->connection->status = 'connected';
            $xml_content->connection->last_connection = $connection_date;
            
            $xml_content->asXML($this->xml_file_path);
            
            manajeni_connector_add_log('xml_update', 'success', 'Fichier XML mis à jour');
            return true;
        }
        
        return false;
    }
    
    /**
     * Vérifie si l'URL est déclarée dans le XML
     */
    public function is_url_declared() {
        if (!file_exists($this->xml_file_path)) {
            return false;
        }
        
        $xml_content = simplexml_load_file($this->xml_file_path);
        
        if ($xml_content && isset($xml_content->settings->url)) {
            $url = (string)$xml_content->settings->url;
            return !empty($url) && $url !== 'null';
        }
        
        return false;
    }
    
    /**
     * Déclare l'URL dans le fichier XML
     */
    public function declare_url($url) {
        if (!file_exists($this->xml_file_path)) {
            $this->create_default_xml();
        }
        
        $xml_content = simplexml_load_file($this->xml_file_path);
        
        if ($xml_content) {
            $xml_content->settings->url = esc_url_raw($url);
            $xml_content->asXML($this->xml_file_path);
            
            manajeni_connector_add_log('xml_declare_url', 'success', 'URL déclarée: ' . $url);
            return true;
        }
        
        return false;
    }
    
    /**
     * Récupère l'URL depuis le XML
     */
    public function get_url() {
        if (!file_exists($this->xml_file_path)) {
            return null;
        }
        
        $xml_content = simplexml_load_file($this->xml_file_path);
        
        if ($xml_content && isset($xml_content->settings->url)) {
            $url = (string)$xml_content->settings->url;
            return $url !== 'null' ? $url : null;
        }
        
        return null;
    }
    
    /**
     * Vérifie la clé API XML
     */
    public function verify_xml_api_key($api_key) {
        if (!file_exists($this->xml_file_path)) {
            return false;
        }
        
        $xml_content = simplexml_load_file($this->xml_file_path);
        
        if ($xml_content && isset($xml_content->api->key)) {
            $stored_key = (string)$xml_content->api->key;
            $expected_key = hash_hmac('sha256', $api_key . $xml_content->api->date_connection, wp_salt('auth'));
            
            return $stored_key === $expected_key;
        }
        
        return false;
    }
    
    /**
     * Crée le fichier XML par défaut
     */
    private function create_default_xml() {
        $upload_dir = wp_upload_dir();
        $manajeni_dir = $upload_dir['basedir'] . '/manajeni/';
        
        if (!file_exists($manajeni_dir)) {
            wp_mkdir_p($manajeni_dir);
        }
        
        $xml_content = '<?xml version="1.0" encoding="UTF-8"?>
<manajeni_config>
    <connection>
        <status>not_configured</status>
        <last_connection>null</last_connection>
    </connection>
    <api>
        <key encrypted="false">null</key>
        <date_connection>null</date_connection>
    </api>
    <settings>
        <url>null</url>
    </settings>
</manajeni_config>';
        
        file_put_contents($this->xml_file_path, $xml_content);
    }
    
    /**
     * Récupère le statut de connexion depuis le XML
     */
    public function get_connection_status() {
        if (!file_exists($this->xml_file_path)) {
            return 'not_configured';
        }
        
        $xml_content = simplexml_load_file($this->xml_file_path);
        
        if ($xml_content && isset($xml_content->connection->status)) {
            return (string)$xml_content->connection->status;
        }
        
        return 'unknown';
    }
}