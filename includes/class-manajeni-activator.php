<?php
/**
 * Class Manajeni_Activator
 * Gère l'activation du plugin et la création des structures nécessaires
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Activator {
    
    /**
     * Activation du plugin
     */
    public static function activate() {
        // 1. Créer la table WordPress
        self::create_database_table();
        
        // 2. Créer le dossier pour les fichiers XML
        self::create_xml_directory();
        
        // 3. Créer le fichier XML initial
        self::create_initial_xml();
        
        // 4. Définir les options par défaut
        self::set_default_options();
        
        // 5. Ajouter un log
        manajeni_connector_add_log('activation', 'success', 'Plugin activé - BD et XML créés');
        
        // 6. Rediriger vers la première connexion
        add_option('manajeni_need_first_login', true);
    }
    
    /**
     * Créer la table WordPress pour Manajeni
     */
    private static function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'manajeni_connection';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            api_key_crypted text NOT NULL,
            api_key_xml varchar(255) DEFAULT NULL,
            connection_date datetime DEFAULT NULL,
            last_activity datetime DEFAULT NULL,
            status varchar(50) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Vérifier si les colonnes existent
        self::ensure_columns_exist($table_name);
    }
    
    /**
     * S'assurer que toutes les colonnes existent
     */
    private static function ensure_columns_exist($table_name) {
        global $wpdb;
        
        $columns = $wpdb->get_col("DESCRIBE $table_name");
        
        if (!in_array('api_key_xml', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN api_key_xml varchar(255) DEFAULT NULL");
        }
        
        if (!in_array('last_activity', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN last_activity datetime DEFAULT NULL");
        }
    }
    
    /**
     * Créer le dossier pour les fichiers XML
     */
    private static function create_xml_directory() {
        $upload_dir = wp_upload_dir();
        $manajeni_dir = $upload_dir['basedir'] . '/manajeni/';
        
        if (!file_exists($manajeni_dir)) {
            wp_mkdir_p($manajeni_dir);
            
            // Ajouter un fichier .htaccess pour protéger le dossier
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents($manajeni_dir . '.htaccess', $htaccess_content);
            
            // Ajouter un fichier index.php
            $index_content = "<?php\n// Silence is golden.\n?>";
            file_put_contents($manajeni_dir . 'index.php', $index_content);
        }
    }
    
    /**
     * Créer le fichier XML initial
     */
    private static function create_initial_xml() {
        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/manajeni/config.xml';
        
        if (!file_exists($xml_file)) {
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
            
            file_put_contents($xml_file, $xml_content);
        }
    }
    
    /**
     * Définir les options par défaut
     */
    private static function set_default_options() {
        if (!get_option('manajeni_connector_logs')) {
            add_option('manajeni_connector_logs', []);
        }
        
        if (!get_option('manajeni_connected')) {
            add_option('manajeni_connected', false);
        }
        
        if (!get_option('manajeni_url')) {
            add_option('manajeni_url', '');
        }

        if (!get_option('manajeni_api_key_masked')) {
            add_option('manajeni_api_key_masked', '');
        }

        if (!get_option('manajeni_api_secret_masked')) {
            add_option('manajeni_api_secret_masked', '');
        }

        if (!get_option('manajeni_api_email')) {
            add_option('manajeni_api_email', '');
        }

        if (!get_option('manajeni_sync_logs')) {
            add_option('manajeni_sync_logs', []);
        }

        if (!get_option('manajeni_sync_mappings')) {
            add_option('manajeni_sync_mappings', []);
        }

        if (!get_option('manajeni_sync_retry_queue')) {
            add_option('manajeni_sync_retry_queue', []);
        }

        if (!get_option('manajeni_webhook_secret')) {
            add_option('manajeni_webhook_secret', wp_generate_password(32, false, false));
        }
    }
}
