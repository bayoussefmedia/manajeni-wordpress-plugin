<?php
/**
 * Class Manajeni_Apps_Handler
 * Gestionnaire des applications Manajeni
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Apps_Handler {
    
    private $api_client;
    
    public function __construct() {
        $this->api_client = manajeni_connector_get_api_client();
    }
    
    /**
     * Affiche les statistiques sous forme de cartes
     */
    public function render_stats_cards($stats) {
        if (empty($stats)) return;
        ?>
        <style>
            .mj-stats-grid { display: grid !important; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) !important; gap: 24px !important; margin-bottom: 40px !important; width: 100% !important; }
            .mj-stat-card { background: white !important; padding: 24px !important; border-radius: 16px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1) !important; display: flex !important; align-items: center !important; gap: 20px !important; border: 1px solid #f1f5f9 !important; transition: transform 0.2s ease !important; }
            .mj-stat-card:hover { transform: translateY(-3px) !important; }
            .mj-stat-icon-wrapper { width: 54px !important; height: 54px !important; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important; border-radius: 14px !important; display: flex !important; align-items: center !important; justify-content: center !important; font-size: 24px !important; color: white !important; flex-shrink: 0 !important; }
            .mj-stat-info { display: flex !important; flex-direction: column !important; }
            .mj-stat-value { font-size: 24px !important; font-weight: 800 !important; color: #0f172a !important; line-height: 1 !important; }
            .mj-stat-label { font-size: 13px !important; font-weight: 600 !important; color: #64748b !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; margin-top: 4px !important; }
        </style>
        <div class="mj-stats-grid">
            <?php foreach ($stats as $stat): ?>
            <div class="mj-stat-card">
                <div class="mj-stat-icon-wrapper"><?php echo $stat['icon']; ?></div>
                <div class="mj-stat-info">
                    <div class="mj-stat-value"><?php echo $stat['value']; ?></div>
                    <div class="mj-stat-label"><?php echo $stat['label']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Affiche un message de succès ou d'erreur
     */
    public function show_message($message, $type = 'success') {
        if (empty($message)) return;
        $class = $type === 'success' ? 'notice-success' : 'notice-error';
        ?>
        <div class="notice <?php echo $class; ?> is-dismissible" style="margin-bottom: 20px; border-radius: 8px;">
            <p><strong><?php echo $type === 'success' ? '✅' : '❌'; ?> <?php echo esc_html($message); ?></strong></p>
        </div>
        <?php
    }

    /**
     * Structure de base pour les modales CRUD
     */
    public function render_modal_start($id, $title) {
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="mj-modal">
            <div class="mj-modal-content">
                <div class="mj-modal-header">
                    <h2><?php echo esc_html($title); ?></h2>
                    <span class="mj-close-modal" onclick="mjCloseModal('<?php echo esc_attr($id); ?>')">&times;</span>
                </div>
                <div class="mj-modal-body">
        <?php
    }

    public function render_modal_end() {
        ?>
                </div>
            </div>
        </div>
        <script>
            function mjOpenModal(id) { document.getElementById(id).style.display = 'block'; }
            function mjCloseModal(id) { document.getElementById(id).style.display = 'none'; }
            window.onclick = function(event) { if (event.target.className === 'mj-modal') event.target.style.display = 'none'; }
        </script>
        <?php
    }
}
?>
