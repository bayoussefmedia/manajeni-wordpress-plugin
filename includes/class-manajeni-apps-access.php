<?php
/**
 * Controle l'acces aux applications Manajeni selon les capabilities API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Apps_Access {

    const CAPABILITIES_TRANSIENT = 'manajeni_capabilities_cache';
    const APP_ACCESS_TRANSIENT = 'manajeni_app_access_cache';
    const CACHE_TTL = 300;

    /**
     * @var Manajeni_DB
     */
    private $db;

    /**
     * @var object
     */
    private $api_client;

    public function __construct($api_client = null) {
        $this->db = new Manajeni_DB();
        $this->api_client = $api_client ?: manajeni_connector_get_api_client();
    }

    /**
     * Retourne les metadonnees locales des applications.
     *
     * @return array
     */
    public static function get_app_catalog() {
        return [
            'clients' => [
                'name' => 'Clients',
                'icon' => '👥',
                'category' => 'Commercial',
                'description' => 'Gerez votre base clients et fiches de contact',
                'features' => ['Fiches CRM', 'Segmentation', 'Historique'],
            ],
            'devis' => [
                'name' => 'Devis',
                'icon' => '📄',
                'category' => 'Commercial',
                'description' => 'Creez et gerez vos devis professionnels',
                'features' => ['Gabarits PDF', 'Signature', 'Statuts'],
            ],
            'factures' => [
                'name' => 'Factures',
                'icon' => '🧾',
                'category' => 'Commercial',
                'description' => 'Gestion complete de votre facturation',
                'features' => ['Paiements', 'Relances', 'Export PDF'],
            ],
            'catalogue' => [
                'name' => 'Catalogue',
                'icon' => '📦',
                'category' => 'Catalogue',
                'description' => 'Produits, services et gestion des stocks',
                'features' => ['Articles', 'Prix/TVA', 'Categories'],
            ],
            'paiements' => [
                'name' => 'Paiements',
                'icon' => '💸',
                'category' => 'Finances',
                'description' => 'Suivi des sorties d\'argent fournisseurs',
                'features' => ['Reglements', 'Modes paiement', 'Journal'],
            ],
            'charges' => [
                'name' => 'Charges',
                'icon' => '📉',
                'category' => 'Finances',
                'description' => 'Suivi exhaustif des depenses et frais',
                'features' => ['TVA deductible', 'Analytique', 'Justificatifs'],
            ],
            'projets' => [
                'name' => 'Projets',
                'icon' => '🎯',
                'category' => 'Projets',
                'description' => 'Pilotage structure et suivi budgetaire',
                'features' => ['Budgets', 'Rentabilite', 'Equipes'],
            ],
            'taches' => [
                'name' => 'Taches',
                'icon' => '✅',
                'category' => 'Projets',
                'description' => 'Organisation du travail et priorites',
                'features' => ['Assignations', 'Echeances', 'Checklists'],
            ],
            'rendez_vous' => [
                'name' => 'Rendez-vous',
                'icon' => '📅',
                'category' => 'Commercial',
                'description' => 'Planification reunions et evenements',
                'features' => ['Calendrier', 'Invitations', 'Compte-rendus'],
            ],
            'fournisseurs' => [
                'name' => 'Fournisseurs',
                'icon' => '🏭',
                'category' => 'Commercial',
                'description' => 'Base partenaires et prestataires',
                'features' => ['Coordonnees', 'ICE/IF', 'Historique'],
            ],
            'rapports' => [
                'name' => 'Rapports',
                'icon' => '📈',
                'category' => 'Organisation',
                'description' => 'Analyses et performances entreprise',
                'features' => ['CA/Benefices', 'Tresorerie', 'Visualisation'],
            ],
        ];
    }

    /**
     * Retourne le mapping scope -> application.
     *
     * @return array
     */
    public static function get_scope_mapping() {
        return [
            'clients:read' => 'clients',
            'catalogue:read' => 'catalogue',
            'projects:read' => 'projets',
            'quotes:read' => 'devis',
            'payments:read' => 'paiements',
            'invoices:read' => 'factures',
            'appointments:read' => 'rendez_vous',
            'tasks:read' => 'taches',
            'reports:read' => 'rapports',
            'expenses:read' => 'charges',
            'suppliers:read' => 'fournisseurs',
        ];
    }

    /**
     * Retourne les applications visibles selon l'API.
     *
     * @return array
     */
    public function get_visible_apps() {
        $catalog = self::get_app_catalog();
        $allowed_slugs = $this->get_authorized_app_slugs();

        if (empty($allowed_slugs)) {
            return [];
        }

        return array_intersect_key($catalog, array_flip($allowed_slugs));
    }

    /**
     * Indique si une application est autorisee.
     *
     * @param string $app_slug Slug d'application.
     * @return bool
     */
    public function user_can_access_app($app_slug) {
        $app_slug = sanitize_key($app_slug);

        if (empty($app_slug)) {
            return false;
        }

        return in_array($app_slug, $this->get_authorized_app_slugs(), true);
    }

    /**
     * Retourne les slugs d'apps autorises avec cache.
     *
     * @return array
     */
    public function get_authorized_app_slugs() {
        if (!$this->db->has_api_key()) {
            return [];
        }

        $cached = get_transient(self::APP_ACCESS_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        $capabilities = $this->get_capabilities_payload();
        if (empty($capabilities['success'])) {
            set_transient(self::APP_ACCESS_TRANSIENT, [], self::CACHE_TTL);
            return [];
        }

        $allowed = [];
        $scope_mapping = self::get_scope_mapping();

        foreach ($capabilities['data']['capabilities'] as $scope) {
            if (isset($scope_mapping[$scope])) {
                $allowed[] = $scope_mapping[$scope];
            }
        }

        $allowed = array_values(array_unique(array_filter($allowed, 'is_string')));

        set_transient(self::APP_ACCESS_TRANSIENT, $allowed, self::CACHE_TTL);

        return $allowed;
    }

    /**
     * Retourne les capabilities normalisees avec cache.
     *
     * @return array
     */
    public function get_capabilities_payload() {
        if (!$this->db->has_api_key()) {
            return [
                'success' => false,
                'code' => 0,
                'message' => __('Cle API Manajeni manquante.', 'manajeni-connector'),
                'data' => [
                    'resources' => [],
                    'capabilities' => [],
                    'resource_capabilities' => [],
                    'raw' => [],
                ],
            ];
        }

        $cached = get_transient(self::CAPABILITIES_TRANSIENT);
        if (is_array($cached) && isset($cached['success'])) {
            return $cached;
        }

        $payload = $this->api_client->get_capabilities();

        if (!is_array($payload) || empty($payload['success'])) {
            $fallback = [
                'success' => false,
                'code' => isset($payload['code']) ? (int) $payload['code'] : 0,
                'message' => isset($payload['message']) ? (string) $payload['message'] : __('Capabilities API indisponibles.', 'manajeni-connector'),
                'data' => [
                    'resources' => [],
                    'capabilities' => [],
                    'resource_capabilities' => [],
                    'raw' => is_array($payload) && isset($payload['data']) ? $payload['data'] : [],
                ],
            ];

            set_transient(self::CAPABILITIES_TRANSIENT, $fallback, self::CACHE_TTL);

            return $fallback;
        }

        set_transient(self::CAPABILITIES_TRANSIENT, $payload, self::CACHE_TTL);

        return $payload;
    }

    /**
     * Affiche un message standard quand une app n'est pas autorisee.
     *
     * @param string $app_slug Slug d'application.
     * @return void
     */
    public function render_unauthorized_notice($app_slug) {
        $catalog = self::get_app_catalog();
        $app_slug = sanitize_key($app_slug);
        $app_name = isset($catalog[$app_slug]['name']) ? $catalog[$app_slug]['name'] : ucfirst(str_replace('_', ' ', $app_slug));
        ?>
        <div class="wrap">
            <div class="notice notice-error" style="margin-top:20px;">
                <p>
                    <strong><?php echo esc_html($app_name); ?>:</strong>
                    <?php echo esc_html__('Application non autorisee par votre cle API', 'manajeni-connector'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Purge les caches d'acces et transients Manajeni.
     *
     * @return void
     */
    public static function clear_cache() {
        delete_option('manajeni_capabilities_cache');
        delete_option('manajeni_app_access_cache');
        delete_option('manajeni_sync_mappings');
        delete_transient(self::CAPABILITIES_TRANSIENT);
        delete_transient(self::APP_ACCESS_TRANSIENT);
        delete_site_transient(self::CAPABILITIES_TRANSIENT);
        delete_site_transient(self::APP_ACCESS_TRANSIENT);

        global $wpdb;

        if (isset($wpdb->options)) {
            $like_patterns = [
                $wpdb->esc_like('_transient_manajeni_') . '%',
                $wpdb->esc_like('_transient_timeout_manajeni_') . '%',
                $wpdb->esc_like('_site_transient_manajeni_') . '%',
                $wpdb->esc_like('_site_transient_timeout_manajeni_') . '%',
            ];

            foreach ($like_patterns as $pattern) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                ));
            }
        }
    }
}
