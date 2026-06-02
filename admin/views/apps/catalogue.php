<?php
/**
 * Module Catalogue - consultation read-only.
 */
if (!defined('ABSPATH')) {
    exit;
}

// Variables injectées par Manajeni_Catalogue_Controller :
// $catalogue, $stats, $apps_handler, $message, $message_type

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
            <h1><?php _e('📦 Catalogue', 'manajeni-connector'); ?></h1>
            <p><?php _e('Consultation du catalogue synchronisé avec Manajeni et WooCommerce.', 'manajeni-connector'); ?></p>
        </div>
    </div>

    <?php
    if (!empty($message)) {
        $apps_handler->show_message($message, $message_type);
    }
    $apps_handler->render_stats_cards($stats);
    ?>

    <div class="notice notice-info" style="margin:0 0 20px 0;">
        <p><?php echo esc_html__('Les modifications produits doivent etre faites dans Manajeni ou WooCommerce. Cette vue est en lecture seule.', 'manajeni-connector'); ?></p>
    </div>

    <div class="mj-table-wrapper">
        <table class="mj-table">
            <thead>
                <tr>
                    <th><?php _e('SKU', 'manajeni-connector'); ?></th>
                    <th><?php _e('NOM', 'manajeni-connector'); ?></th>
                    <th><?php _e('TYPE', 'manajeni-connector'); ?></th>
                    <th><?php _e('PRIX HT', 'manajeni-connector'); ?></th>
                    <th><?php _e('TAXE', 'manajeni-connector'); ?></th>
                    <th><?php _e('STOCK', 'manajeni-connector'); ?></th>
                    <th><?php _e('STATUT', 'manajeni-connector'); ?></th>
                    <th><?php _e('SOURCE / SYNC STATUS', 'manajeni-connector'); ?></th>
                    <th><?php _e('DERNIERE SYNCHRONISATION', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($catalogue)) : ?>
                    <tr>
                        <td colspan="9" style="text-align:center; color:var(--mj-slate-500);">
                            <?php _e('Aucun article synchronisé disponible.', 'manajeni-connector'); ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($catalogue as $item) : ?>
                    <?php
                    $reference = '';
                    if (isset($item['reference']) && '' !== trim((string) $item['reference'])) {
                        $reference = (string) $item['reference'];
                    } elseif (isset($item['sku']) && '' !== trim((string) $item['sku'])) {
                        $reference = (string) $item['sku'];
                    }

                    $type = isset($item['type']) && 'service' === $item['type'] ? __('Service', 'manajeni-connector') : __('Produit', 'manajeni-connector');
                    $price = isset($item['price']) ? (float) $item['price'] : 0;
                    $tax = isset($item['tax']) ? $item['tax'] : '0';
                    $stock = isset($item['stock']) ? $item['stock'] : (isset($item['stock_quantity']) ? $item['stock_quantity'] : '—');
                    $status = isset($item['status']) && '' !== (string) $item['status'] ? (string) $item['status'] : __('inconnu', 'manajeni-connector');
                    $source = isset($item['source']) && '' !== (string) $item['source'] ? (string) $item['source'] : 'Manajeni API';

                    $raw_sync_status = '';
                    foreach (['sync_status', 'sync_state', 'status_sync', 'sync'] as $sync_key) {
                        if (isset($item[$sync_key]) && '' !== trim((string) $item[$sync_key])) {
                            $raw_sync_status = sanitize_key((string) $item[$sync_key]);
                            break;
                        }
                    }

                    $sync_status = 'synchronise';
                    if ('' === $reference) {
                        $sync_status = 'erreur_sync';
                    } elseif (in_array($raw_sync_status, ['pending', 'en_attente', 'waiting'], true)) {
                        $sync_status = 'en_attente';
                    } elseif (in_array($raw_sync_status, ['error', 'erreur', 'erreur_sync', 'failed'], true)) {
                        $sync_status = 'erreur_sync';
                    } elseif (in_array($raw_sync_status, ['conflict', 'conflit'], true)) {
                        $sync_status = 'conflit';
                    } elseif (in_array($raw_sync_status, ['synced', 'synchronise', 'synchronised', 'ok'], true)) {
                        $sync_status = 'synchronise';
                    }

                    $sync_badge = $sync_badges[$sync_status];
                    $last_sync = '—';
                    foreach (['last_sync_at', 'synced_at', 'last_synced_at', 'updated_at'] as $date_key) {
                        if (!empty($item[$date_key])) {
                            $last_sync = (string) $item[$date_key];
                            break;
                        }
                    }
                    ?>
                    <tr>
                        <td>
                            <?php if ('' !== $reference) : ?>
                                <code style="background:var(--mj-slate-50); padding:4px 8px; border-radius:6px; font-size:12px; border:1px solid var(--mj-slate-100);"><?php echo esc_html($reference); ?></code>
                            <?php else : ?>
                                <div>
                                    <span class="mj-badge" style="background:var(--mj-error-soft); color:var(--mj-error);">
                                        <?php _e('Référence manquante', 'manajeni-connector'); ?>
                                    </span>
                                    <div style="font-size:12px; color:var(--mj-error); margin-top:6px;">
                                        <?php _e('Référence requise pour synchronisation WooCommerce', 'manajeni-connector'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html(isset($item['name']) ? $item['name'] : '—'); ?></strong>
                            <br>
                            <small style="color:var(--mj-slate-500);"><?php echo esc_html(isset($item['category']) ? $item['category'] : ''); ?></small>
                        </td>
                        <td><?php echo esc_html($type); ?></td>
                        <td><?php echo esc_html(number_format($price, 2, ',', ' ')); ?> DH</td>
                        <td><?php echo esc_html($tax); ?>%</td>
                        <td><?php echo esc_html((string) $stock); ?></td>
                        <td><span class="mj-badge mj-badge-info"><?php echo esc_html($status); ?></span></td>
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
