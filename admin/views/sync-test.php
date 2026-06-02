<?php
/**
 * Console Synchronisation WooCommerce <-> Manajeni.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé.');
}

$logger = manajeni_connector_get_sync_logger();
$mapper = manajeni_connector_get_sync_mapper();
$sync = manajeni_connector_get_woocommerce_sync();
$db = new Manajeni_DB();

$logs = $logger->get_logs(120);
$stats = $logger->get_stats();
$mapping_counts = $mapper->get_counts();
$woocommerce_active = manajeni_connector_is_woocommerce_active();
$webhook_base = rest_url('manajeni/v1/webhook/');
$retry_count = $sync->get_retry_queue_count();
$api_connected = $db->has_api_key();
?>

<div class="mj-app-container">
    <?php include MANAJENI_CONNECTOR_PATH . 'admin/views/partials/header.php'; ?>

    <div style="margin-bottom:40px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2 style="font-family:'Outfit', sans-serif; font-size:28px; font-weight:800; color:var(--mj-slate-900); margin:0;">🔄 Synchronisation</h2>
            <p style="color:var(--mj-slate-500); margin-top:8px;">Console temps réel WooCommerce ↔ Manajeni, logs, webhooks et jobs de retry.</p>
        </div>
        <button class="mj-btn-primary" onclick="location.reload();">Rafraîchir</button>
    </div>

    <?php if (!$woocommerce_active) : ?>
        <div class="notice mj-notice notice-warning">
            <p><strong><?php echo esc_html__('WooCommerce non installé', 'manajeni-connector'); ?></strong></p>
        </div>
    <?php endif; ?>

    <?php if (!$api_connected) : ?>
        <div class="notice mj-notice notice-warning">
            <p><strong><?php echo esc_html__('Aucune clé API connectée. Les hooks sortants vers Manajeni sont désactivés.', 'manajeni-connector'); ?></strong></p>
        </div>
    <?php endif; ?>

    <div class="mj-stats-grid" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));">
        <div class="mj-stat-card">
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo esc_html($woocommerce_active ? 'ON' : 'OFF'); ?></div>
                <div class="mj-stat-label">WooCommerce</div>
            </div>
        </div>
        <div class="mj-stat-card">
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo esc_html($stats['total']); ?></div>
                <div class="mj-stat-label">Logs Sync</div>
            </div>
        </div>
        <div class="mj-stat-card">
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo esc_html($retry_count); ?></div>
                <div class="mj-stat-label">Jobs Retry</div>
            </div>
        </div>
        <div class="mj-stat-card">
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo esc_html($mapping_counts['products']); ?></div>
                <div class="mj-stat-label">Mappings Produits</div>
            </div>
        </div>
        <div class="mj-stat-card">
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo esc_html($mapping_counts['clients']); ?></div>
                <div class="mj-stat-label">Mappings Clients</div>
            </div>
        </div>
        <div class="mj-stat-card">
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo esc_html($mapping_counts['orders']); ?></div>
                <div class="mj-stat-label">Mappings Commandes</div>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-top:40px;">
        <div class="mj-table-wrapper" style="padding:24px;">
            <h3 style="margin-top:0;">Webhooks Manajeni → WooCommerce</h3>
            <div style="display:grid; gap:12px; font-size:13px;">
                <div><strong>Catalogue</strong><br><code><?php echo esc_html($webhook_base . 'catalogue-updated'); ?></code></div>
                <div><strong>Stock</strong><br><code><?php echo esc_html($webhook_base . 'stock-updated'); ?></code></div>
                <div><strong>Client</strong><br><code><?php echo esc_html($webhook_base . 'client-updated'); ?></code></div>
                <div style="color:var(--mj-slate-500);">
                    Signature attendue: header <code>X-Manajeni-Signature</code> en HMAC SHA-256.
                </div>
            </div>
        </div>

        <div class="mj-table-wrapper" style="padding:24px;">
            <h3 style="margin-top:0;">Santé Synchronisation</h3>
            <div style="display:grid; gap:12px; font-size:13px;">
                <div style="display:flex; justify-content:space-between;"><span>API Manajeni</span><strong><?php echo esc_html($api_connected ? 'Connectée' : 'Déconnectée'); ?></strong></div>
                <div style="display:flex; justify-content:space-between;"><span>Warnings</span><strong><?php echo esc_html($stats['warning']); ?></strong></div>
                <div style="display:flex; justify-content:space-between;"><span>Erreurs</span><strong><?php echo esc_html($stats['error']); ?></strong></div>
                <div style="display:flex; justify-content:space-between;"><span>Infos</span><strong><?php echo esc_html($stats['info']); ?></strong></div>
            </div>
        </div>
    </div>

    <div class="mj-table-wrapper" style="margin-top:40px;">
        <div style="padding:25px 30px; border-bottom:1px solid var(--mj-slate-100); display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-family:'Outfit', sans-serif; font-size:18px; font-weight:800; margin:0;">Logs détaillés</h3>
            <span class="mj-badge" style="background:var(--mj-primary-soft); color:var(--mj-primary);"><?php echo esc_html(count($logs)); ?> entrées</span>
        </div>

        <?php if (empty($logs)) : ?>
            <div style="padding:80px 0; text-align:center; color:var(--mj-slate-300);">
                <div style="font-size:50px; margin-bottom:15px;">📭</div>
                <div style="font-weight:600;">Aucun log de synchronisation</div>
            </div>
        <?php else : ?>
            <table class="mj-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Niveau</th>
                        <th>Action</th>
                        <th>Message</th>
                        <th>Contexte</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <?php
                        $level = isset($log['level']) ? $log['level'] : 'info';
                        $badge_style = 'background:var(--mj-primary-soft); color:var(--mj-primary);';
                        if ('warning' === $level) {
                            $badge_style = 'background:var(--mj-warning-soft); color:var(--mj-warning);';
                        } elseif ('error' === $level) {
                            $badge_style = 'background:var(--mj-error-soft); color:var(--mj-error);';
                        } elseif ('info' === $level) {
                            $badge_style = 'background:var(--mj-success-soft); color:var(--mj-success);';
                        }
                        ?>
                        <tr>
                            <td style="font-size:12px; color:var(--mj-slate-400)"><?php echo esc_html($log['date']); ?></td>
                            <td><span class="mj-badge" style="<?php echo esc_attr($badge_style); ?>"><?php echo esc_html(strtoupper($level)); ?></span></td>
                            <td><strong style="color:var(--mj-slate-700)"><?php echo esc_html(strtoupper(str_replace('_', ' ', $log['action']))); ?></strong></td>
                            <td style="font-size:12px; color:var(--mj-slate-700)"><?php echo esc_html($log['message']); ?></td>
                            <td style="font-size:12px; color:var(--mj-slate-500)"><code><?php echo esc_html(wp_json_encode($log['context'])); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
