<?php
/**
 * Page Test données Manajeni - Hub de Visualisation Technique
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé.');
}

$db = new Manajeni_DB();
$api_key = $db->get_api_key();
$api_client = manajeni_connector_get_api_client();
$items = $api_client->get_catalogue();
$api_error = '';

if (!manajeni_connector_is_dev_mode() && empty($items)) {
    $api_error = 'Aucune donnee catalogue n\'a ete retournee par l\'API Manajeni.';
}
?>

<div class="mj-app-container">
    
    <?php include MANAJENI_CONNECTOR_PATH . 'admin/views/partials/header.php'; ?>

    <div style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="font-family:'Outfit', sans-serif; font-size: 28px; font-weight: 800; color: var(--mj-slate-900); margin: 0;">🧪 Flux de Synchronisation</h2>
            <p style="color: var(--mj-slate-500); margin-top:8px;">Vérification de l'intégrité des données entrantes.</p>
        </div>
        <button class="mj-btn-primary" onclick="location.reload();">
            🔄 Rafraîchir les Flux
        </button>
    </div>

    <?php if ($api_error): ?>
        <div class="notice mj-notice notice-error">
            <p><strong><?php echo esc_html($api_error); ?></strong></p>
        </div>
    <?php endif; ?>

    <!-- Mode Status Bar -->
    <div style="background: white; padding: 20px 30px; border-radius: 20px; box-shadow: var(--mj-shadow-sm); margin-bottom: 40px; border: 1px solid var(--mj-slate-100); display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width:12px; height:12px; border-radius:50%; background:<?php echo manajeni_connector_is_dev_mode() ? 'var(--mj-warning)' : 'var(--mj-success)'; ?>; animation: mjPulse 2s infinite;"></div>
            <span style="font-weight: 700; color: var(--mj-slate-700);">
                Source de données : <?php echo manajeni_connector_is_dev_mode() ? 'Client de developpement' : 'API Manajeni'; ?>
            </span>
        </div>
        <div style="display:flex; gap:10px;">
            <span class="mj-badge" style="background:var(--mj-primary-soft); color:var(--mj-primary)">V3.2 Engine</span>
            <?php if ($api_key): ?>
                <span class="mj-badge" style="background:var(--mj-success-soft); color:var(--mj-success)">🔑 Canal Sécurisé</span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Stats Cards Fantastic -->
    <div class="mj-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div class="mj-stat-card">
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo count($items); ?></div>
                <div class="mj-stat-label">Éléments Détectés</div>
            </div>
        </div>
        <div class="mj-stat-card">
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo count(array_filter($items, fn($i) => $i['type'] === 'service')); ?></div>
                <div class="mj-stat-label">Services Cloud</div>
            </div>
        </div>
        <div class="mj-stat-card">
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo count(array_filter($items, fn($i) => $i['status'] === 'active')); ?></div>
                <div class="mj-stat-label">Flux Opérationnels</div>
            </div>
        </div>
    </div>
    
    <!-- Data Table Fantastic -->
    <div class="mj-table-wrapper" style="margin-top:40px;">
        <table class="mj-table">
            <thead>
                <tr>
                    <th>Ref ID</th>
                    <th>Élément Manajeni</th>
                    <th>Type de donnée</th>
                    <th>Valorisation</th>
                    <th>État Flux</th>
                    <th>Détails Métadonnées</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><code style="color:var(--mj-primary); font-weight:800;"><?php echo str_pad($item['id'], 3, '0', STR_PAD_LEFT); ?></code></td>
                    <td>
                        <div style="font-weight: 800; color:var(--mj-slate-900)"><?php echo esc_html($item['name']); ?></div>
                    </td>
                    <td>
                        <?php if ($item['type'] === 'service'): ?>
                            <span class="mj-badge" style="background: var(--mj-primary-soft); color: var(--mj-primary);">🛠️ SERVICE</span>
                        <?php else: ?>
                            <span class="mj-badge" style="background: var(--mj-success-soft); color: var(--mj-success);">📦 PRODUIT</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight: 800; color:var(--mj-slate-900)">
                        <?php echo number_format($item['price'], 2, ',', ' '); ?> DH
                    </td>
                    <td>
                        <?php if ($item['status'] === 'active'): ?>
                            <span style="color: var(--mj-success); font-weight: 800; font-size:11px;">● SYNCHRONISÉ</span>
                        <?php else: ?>
                            <span style="color: var(--mj-error); font-weight: 800; font-size:11px;">○ SUSPENDU</span>
                        <?php endif; ?>
                    </td>
                    <td style="color: var(--mj-slate-500); font-size: 13px;">
                        <?php echo esc_html($item['description']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@keyframes mjPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
</style>
