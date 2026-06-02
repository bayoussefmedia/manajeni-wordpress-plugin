<?php
/**
 * Plugin Name: Manajeni Connector
 * Description: Connecteur entre WordPress et Manajeni avec systeme de session securise.
 * Version: 0.6.1
 * Author: Manajeni
 * Text Domain: manajeni-connector
 */

if (!defined('ABSPATH')) {
    exit;
}

// Démarrer la bufferisation pour éviter les problèmes de headers
if (!ob_get_level()) {
    ob_start();
}

// Constantes
define('MANAJENI_CONNECTOR_VERSION', '0.6.1');
define('MANAJENI_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('MANAJENI_CONNECTOR_URL', plugin_dir_url(__FILE__));

if (!defined('MANAJENI_CONNECTOR_DEV_MODE')) {
    define('MANAJENI_CONNECTOR_DEV_MODE', false);
}

// Fonction de redirection sécurisée
function manajeni_safe_redirect($url) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        wp_redirect($url);
        exit;
    } else {
        ?>
        <script>window.location.href = "<?php echo esc_url($url); ?>";</script>
        <noscript><meta http-equiv="refresh" content="0;url=<?php echo esc_url($url); ?>"></noscript>
        <?php
        exit;
    }
}

/**
 * Indique si le plugin tourne en mode developpement.
 *
 * @return bool
 */
function manajeni_connector_is_dev_mode() {
    return (bool) MANAJENI_CONNECTOR_DEV_MODE;
}

/**
 * Retourne le client API approprie.
 *
 * @param string $api_url URL API optionnelle.
 * @param string $api_key Cle API optionnelle.
 * @return object
 */
function manajeni_connector_get_api_client($api_url = '', $api_key = '') {
    if (manajeni_connector_is_dev_mode()) {
        if (!class_exists('Manajeni_Fake_API_Client')) {
            require_once MANAJENI_CONNECTOR_PATH . 'services/class-manajeni-fake-api-client.php';
        }

        return new Manajeni_Fake_API_Client();
    }

    if (!class_exists('Manajeni_API_Client')) {
        require_once MANAJENI_CONNECTOR_PATH . 'services/class-manajeni-api-client.php';
    }

    return new Manajeni_API_Client($api_url, $api_key);
}

/**
 * Retourne le service central de gestion des acces applications.
 *
 * @return Manajeni_Apps_Access
 */
function manajeni_connector_get_apps_access() {
    if (!class_exists('Manajeni_Apps_Access')) {
        require_once MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-apps-access.php';
    }

    return new Manajeni_Apps_Access();
}

/**
 * Retourne le logger de synchronisation.
 *
 * @return Manajeni_Sync_Logger
 */
function manajeni_connector_get_sync_logger() {
    if (!class_exists('Manajeni_Sync_Logger')) {
        require_once MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-sync-logger.php';
    }

    return new Manajeni_Sync_Logger();
}

/**
 * Retourne le mapper de synchronisation.
 *
 * @return Manajeni_Sync_Mapper
 */
function manajeni_connector_get_sync_mapper() {
    if (!class_exists('Manajeni_Sync_Mapper')) {
        require_once MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-sync-mapper.php';
    }

    return new Manajeni_Sync_Mapper();
}

/**
 * Indique si WooCommerce est actif.
 *
 * @return bool
 */
function manajeni_connector_is_woocommerce_active() {
    return class_exists('WooCommerce') && function_exists('wc_get_product');
}

/**
 * Retourne le secret webhook utilise pour valider les callbacks entrants.
 *
 * @return string
 */
function manajeni_connector_get_webhook_secret() {
    $db = new Manajeni_DB();
    $api_secret = (string) $db->get_api_secret();
    if ('' !== $api_secret) {
        return $api_secret;
    }

    $fallback = (string) get_option('manajeni_webhook_secret', '');
    if ('' === $fallback) {
        $fallback = wp_generate_password(32, false, false);
        update_option('manajeni_webhook_secret', $fallback, false);
    }

    return $fallback;
}

/**
 * Retourne le service WooCommerce Sync.
 *
 * @return Manajeni_WooCommerce_Sync
 */
function manajeni_connector_get_woocommerce_sync() {
    static $sync_service = null;

    if (null === $sync_service) {
        if (!class_exists('Manajeni_WooCommerce_Sync')) {
            require_once MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-woocommerce-sync.php';
        }
        $sync_service = new Manajeni_WooCommerce_Sync();
    }

    return $sync_service;
}

// Chargement automatique des classes
spl_autoload_register(function($class) {
    $prefix = 'Manajeni_';
    if (strpos($class, $prefix) !== 0) return;
    
    $class_name = str_replace($prefix, '', $class);
    $class_name = strtolower(str_replace('_', '-', $class_name));
    
    $files = [
        MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-' . $class_name . '.php',
        MANAJENI_CONNECTOR_PATH . 'services/class-manajeni-' . $class_name . '.php',
        MANAJENI_CONNECTOR_PATH . 'includes/controllers/class-manajeni-' . $class_name . '.php',
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Fonction de log globale
function manajeni_connector_add_log($action, $status, $details = '') {
    $logs = get_option('manajeni_connector_logs', []);
    array_unshift($logs, [
        'date' => current_time('Y-m-d H:i:s'),
        'action' => $action,
        'status' => $status,
        'details' => $details
    ]);
    update_option('manajeni_connector_logs', array_slice($logs, 0, 100));
}

// WP-Cron - Synchronisation automatique
register_activation_hook(__FILE__, function() {
    require_once MANAJENI_CONNECTOR_PATH . 'includes/class-manajeni-activator.php';
    Manajeni_Activator::activate();
    
    if (!wp_next_scheduled('manajeni_hourly_sync')) {
        wp_schedule_event(time(), 'hourly', 'manajeni_hourly_sync');
    }

    if (!wp_next_scheduled('manajeni_sync_retry_event')) {
        wp_schedule_event(time() + 300, 'manajeni_five_minutes', 'manajeni_sync_retry_event');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('manajeni_hourly_sync');
    wp_clear_scheduled_hook('manajeni_sync_retry_event');
    manajeni_connector_add_log('deactivation', 'success', 'Plugin désactivé');
});

add_action('manajeni_hourly_sync', function() {
    // Simulation de synchronisation
    manajeni_connector_add_log('cron_sync', 'success', 'Synchronisation automatique réussie');
});

add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['manajeni_five_minutes'])) {
        $schedules['manajeni_five_minutes'] = [
            'interval' => 300,
            'display' => __('Toutes les 5 minutes', 'manajeni-connector'),
        ];
    }

    return $schedules;
});

// Menu admin
add_action('admin_menu', function() {
    // Menu principal
    add_menu_page(
        'Manajeni Connector',
        'Manajeni',
        'manage_options',
        'manajeni-dashboard',
        'manajeni_dashboard_page',
        'dashicons-cloud',
        25
    );
    
    // Sous-menus
    add_submenu_page('manajeni-dashboard', 'Tableau de bord', '📊 Tableau de bord', 'manage_options', 'manajeni-dashboard', 'manajeni_dashboard_page');
    add_submenu_page('manajeni-dashboard', 'Connexion API', '🔑 Connexion API', 'manage_options', 'manajeni-api-connection', 'manajeni_api_connection_page');
    add_submenu_page('manajeni-dashboard', 'Synchronisation', '🔄 Synchronisation', 'manage_options', 'manajeni-test-data', 'manajeni_test_data_page');
    add_submenu_page('manajeni-dashboard', 'Paramètres', '⚙️ Paramètres', 'manage_options', 'manajeni-settings', 'manajeni_settings_page');
    
    // Pages sans vérification de session
    add_submenu_page(null, 'Première connexion', null, 'manage_options', 'manajeni-first-login', 'manajeni_first_login_page');
    add_submenu_page(null, 'Configuration URL', null, 'manage_options', 'manajeni-url-config', 'manajeni_url_config_page');
    add_submenu_page(null, 'Déconnexion', null, 'manage_options', 'manajeni-logout', function(){});
    
    // AJOUT: Pages des applications dynamiques
    $apps_list = [
        'clients', 'devis', 'factures', 'catalogue', 'paiements', 'charges',
        'projets', 'taches', 'rendez_vous', 'fournisseurs', 'rapports'
    ];
    
    foreach ($apps_list as $app) {
        add_submenu_page(
            null, // Caché du menu principal
            ucfirst($app),
            ucfirst($app),
            'manage_options',
            'manajeni-' . $app,
            function() use ($app) {
                if (!current_user_can('manage_options')) {
                    wp_die('Accès non autorisé.');
                }

                if (!class_exists('Manajeni_Session')) {
                    wp_die('Erreur système');
                }

                $session = new Manajeni_Session();
                if (!$session->check_session()) {
                    return;
                }

                $apps_access = manajeni_connector_get_apps_access();
                if (!$apps_access->user_can_access_app($app)) {
                    $apps_access->render_unauthorized_notice($app);
                    return;
                }
                
                // STRATÉGIE MVC: Vérifier si un contrôleur existe pour cette app
                // Format: Manajeni_Clients_Controller
                $controller_class = 'Manajeni_' . ucfirst($app) . '_Controller';
                if (class_exists($controller_class)) {
                    $controller = new $controller_class();
                    $controller->render();
                    return;
                }
                
                // Fallback: Chargement direct de la vue (pour les modules simples)
                $file = MANAJENI_CONNECTOR_PATH . 'admin/views/apps/' . $app . '.php';
                if (file_exists($file)) {
                    global $apps_handler, $api_client;
                    $apps_handler = new Manajeni_Apps_Handler();
                    $api_client = manajeni_connector_get_api_client();
                    include $file;
                } else {
                    echo '<div class="wrap"><div class="notice notice-warning"><p>Module "' . esc_html($app) . '" en cours de développement.</p></div></div>';
                }
            }
        );
    }
});

// Pages principales
function manajeni_dashboard_page() {
    if (!class_exists('Manajeni_Session')) {
        echo '<div class="error"><p>Classe Manajeni_Session non trouvée</p></div>';
        return;
    }
    $session = new Manajeni_Session();
    if (!$session->check_session()) return;
    if (file_exists(MANAJENI_CONNECTOR_PATH . 'admin/views/dashboard.php')) {
        include_once MANAJENI_CONNECTOR_PATH . 'admin/views/dashboard.php';
    }
}

function manajeni_test_data_page() {
    if (!class_exists('Manajeni_Session')) {
        echo '<div class="error"><p>Classe Manajeni_Session non trouvée</p></div>';
        return;
    }
    $session = new Manajeni_Session();
    if (!$session->check_session()) return;
    if (file_exists(MANAJENI_CONNECTOR_PATH . 'admin/views/sync-test.php')) {
        include_once MANAJENI_CONNECTOR_PATH . 'admin/views/sync-test.php';
    }
}

function manajeni_settings_page() {
    if (!class_exists('Manajeni_Session')) {
        echo '<div class="error"><p>Classe Manajeni_Session non trouvée</p></div>';
        return;
    }
    $session = new Manajeni_Session();
    if (!$session->check_session()) return;
    if (file_exists(MANAJENI_CONNECTOR_PATH . 'admin/views/settings.php')) {
        include_once MANAJENI_CONNECTOR_PATH . 'admin/views/settings.php';
    }
}

function manajeni_first_login_page() {
    if (file_exists(MANAJENI_CONNECTOR_PATH . 'admin/views/first-login.php')) {
        include_once MANAJENI_CONNECTOR_PATH . 'admin/views/first-login.php';
    }
}

function manajeni_url_config_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Accès non autorisé.');
    }
    if (file_exists(MANAJENI_CONNECTOR_PATH . 'admin/views/url-config.php')) {
        include_once MANAJENI_CONNECTOR_PATH . 'admin/views/url-config.php';
    }
}

function manajeni_api_connection_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Accès non autorisé.');
    }
    if (file_exists(MANAJENI_CONNECTOR_PATH . 'admin/views/connection.php')) {
        include_once MANAJENI_CONNECTOR_PATH . 'admin/views/connection.php';
    }
}

// Gestion des sessions
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'manajeni-logout') {
        if (class_exists('Manajeni_Session')) {
            $session = new Manajeni_Session();
            $session->logout();
        }
        manajeni_safe_redirect(admin_url('admin.php?page=manajeni-first-login'));
        exit;
    }
    
    if (isset($_GET['page']) && strpos($_GET['page'], 'manajeni-') === 0) {
        $public_pages = ['manajeni-first-login', 'manajeni-url-config', 'manajeni-api-connection'];
        if (!in_array($_GET['page'], $public_pages)) {
            if (class_exists('Manajeni_Session')) {
                $session = new Manajeni_Session();
                $session->check_session();
            }
        }
    }
}, 5);

// Styles
add_action('admin_enqueue_scripts', function($hook) {
    wp_enqueue_style('manajeni-admin-css', MANAJENI_CONNECTOR_URL . 'admin/assets/css/admin.css', [], MANAJENI_CONNECTOR_VERSION);
});

add_filter('admin_body_class', function($classes) {
    if (isset($_GET['page']) && strpos($_GET['page'], 'manajeni') !== false) {
        $classes .= ' mj-admin-page ';
    }
    return $classes;
});

add_action('shutdown', function() {
    while (ob_get_level()) {
        ob_end_flush();
    }
});

add_action('plugins_loaded', function() {
    manajeni_connector_get_woocommerce_sync()->bootstrap();
}, 20);
