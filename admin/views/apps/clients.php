<?php
/**
 * Module Clients - consultation read-only.
 */
if (!defined('ABSPATH')) {
    exit;
}

// Variables injectées par Manajeni_Clients_Controller :
// $clients, $stats, $apps_handler, $message, $message_type

$sync_badges = [
    'synchronise' => ['label' => __('Synchronisé', 'manajeni-connector'), 'style' => 'background:var(--mj-success-soft); color:var(--mj-success);'],
    'en_attente' => ['label' => __('En attente', 'manajeni-connector'), 'style' => 'background:var(--mj-warning-soft); color:var(--mj-warning);'],
    'erreur_sync' => ['label' => __('Erreur sync', 'manajeni-connector'), 'style' => 'background:var(--mj-error-soft); color:var(--mj-error);'],
    'conflit' => ['label' => __('Conflit', 'manajeni-connector'), 'style' => 'background:#efe3ff; color:#7c3aed;'],
];
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div>
            <h1><?php _e('👥 Clients', 'manajeni-connector'); ?></h1>
            <p><?php _e('Consultation des clients synchronisés depuis Manajeni/API.', 'manajeni-connector'); ?></p>
        </div>
    </div>

    <?php
    if (!empty($message)) {
        $apps_handler->show_message($message, $message_type);
    }
    $apps_handler->render_stats_cards($stats);
    ?>

    <div class="notice notice-info" style="margin:0 0 20px 0;">
        <p><?php echo esc_html__('Les modifications clients doivent etre faites dans Manajeni. Cette vue est en lecture seule.', 'manajeni-connector'); ?></p>
    </div>

    <div class="mj-search-container-box">
        <form method="get" style="display:flex; width:100%; gap:15px; align-items:center;">
            <input type="hidden" name="page" value="manajeni-clients">
            <div style="flex-grow:1; position:relative;">
                <span style="position:absolute; left:16px; top:50%; transform:translateY(-50%); opacity:0.4">🔍</span>
                <input type="text" name="search" placeholder="<?php _e('Rechercher un client synchronisé...', 'manajeni-connector'); ?>" value="<?php echo isset($_GET['search']) ? esc_attr(wp_unslash($_GET['search'])) : ''; ?>" class="mj-form-control" style="padding-left:45px !important;">
            </div>
            <button type="submit" class="mj-btn-primary" style="padding:12px 30px;"><?php _e('Rechercher', 'manajeni-connector'); ?></button>
        </form>
    </div>

    <div class="mj-table-wrapper">
        <table class="mj-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php _e('NOM', 'manajeni-connector'); ?></th>
                    <th><?php _e('VILLE', 'manajeni-connector'); ?></th>
                    <th><?php _e('ICE', 'manajeni-connector'); ?></th>
                    <th><?php _e('TYPE', 'manajeni-connector'); ?></th>
                    <th><?php _e('SOURCE / SYNC STATUS', 'manajeni-connector'); ?></th>
                    <th><?php _e('DERNIERE SYNCHRONISATION', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)) : ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:var(--mj-slate-500);">
                            <?php _e('Aucun client synchronisé disponible.', 'manajeni-connector'); ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($clients as $c) : ?>
                    <?php
                    $source = isset($c['source']) && '' !== (string) $c['source'] ? (string) $c['source'] : 'Manajeni API';
                    $raw_sync_status = '';
                    foreach (['sync_status', 'sync_state', 'status_sync', 'sync'] as $sync_key) {
                        if (isset($c[$sync_key]) && '' !== trim((string) $c[$sync_key])) {
                            $raw_sync_status = sanitize_key((string) $c[$sync_key]);
                            break;
                        }
                    }

                    $sync_status = 'synchronise';
                    if (in_array($raw_sync_status, ['pending', 'en_attente', 'waiting'], true)) {
                        $sync_status = 'en_attente';
                    } elseif (in_array($raw_sync_status, ['error', 'erreur', 'erreur_sync', 'failed'], true)) {
                        $sync_status = 'erreur_sync';
                    } elseif (in_array($raw_sync_status, ['conflict', 'conflit'], true)) {
                        $sync_status = 'conflit';
                    } elseif ('' === $raw_sync_status || in_array($raw_sync_status, ['synced', 'synchronise', 'synchronised', 'ok'], true)) {
                        $sync_status = 'synchronise';
                    }

                    $sync_badge = $sync_badges[$sync_status];
                    $last_sync = '—';
                    foreach (['last_sync_at', 'synced_at', 'last_synced_at', 'updated_at'] as $date_key) {
                        if (!empty($c[$date_key])) {
                            $last_sync = (string) $c[$date_key];
                            break;
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html(isset($c['id']) ? (string) $c['id'] : '—'); ?></td>
                        <td>
                            <div style="font-weight:700; color:#1e293b;"><?php echo esc_html(isset($c['nom']) ? $c['nom'] : '—'); ?></div>
                            <div style="font-size:12px; color:#64748b;"><?php echo esc_html(isset($c['email']) ? $c['email'] : ''); ?></div>
                        </td>
                        <td><?php echo esc_html(isset($c['ville']) ? $c['ville'] : '—'); ?></td>
                        <td><code style="background:var(--mj-slate-50); padding:4px 8px; border-radius:6px; font-size:12px; border:1px solid var(--mj-slate-100);"><?php echo esc_html(isset($c['ice']) ? $c['ice'] : '—'); ?></code></td>
                        <td>
                            <span class="mj-badge <?php echo isset($c['type']) && 'premium' === $c['type'] ? 'mj-badge-premium' : 'mj-badge-info'; ?>">
                                <?php echo isset($c['type']) && 'premium' === $c['type'] ? '⭐ Premium' : '👤 Standard'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:6px;">
                                <span style="font-size:12px; color:var(--mj-slate-500);"><?php echo esc_html($source); ?></span>
                                <span class="mj-badge" style="<?php echo esc_attr($sync_badge['style']); ?>"><?php echo esc_html($sync_badge['label']); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($last_sync); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
