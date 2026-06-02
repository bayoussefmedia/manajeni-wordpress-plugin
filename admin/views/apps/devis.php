<?php
/**
 * Module Devis - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Devis_Controller :
// $devis, $stats, $apps_handler, $clients, $message, $message_type
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1><?php _e('📄 Devis', 'manajeni-connector'); ?></h1><p><?php _e('Créez et gérez vos devis professionnels', 'manajeni-connector'); ?></p></div>
        <button class="mj-btn-primary" onclick="mjResetDevisForm(); mjOpenModal('modal-new-devis')"><?php _e('+ Créer un devis', 'manajeni-connector'); ?></button>
    </div>

    <?php 
    if (!empty($message)) $apps_handler->show_message($message, $message_type);
    $apps_handler->render_stats_cards($stats); 
    ?>

    <div class="mj-table-wrapper">
        <table class="mj-table mj-doc-table">
            <thead>
                <tr>
                    <th></th>
                    <th><?php _e('Référence', 'manajeni-connector'); ?></th>
                    <th><?php _e('Client', 'manajeni-connector'); ?></th>
                    <th><?php _e('Date', 'manajeni-connector'); ?></th>
                    <th><?php _e('Montant HT', 'manajeni-connector'); ?></th>
                    <th><?php _e('TVA', 'manajeni-connector'); ?></th>
                    <th><?php _e('Montant TTC', 'manajeni-connector'); ?></th>
                    <th><?php _e('Statut', 'manajeni-connector'); ?></th>
                    <th><?php _e('Actions', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devis as $d): ?>
                <?php
                    $badge_class = match($d['statut']) {
                        'accepté'  => 'mj-badge-success',
                        'envoyé'   => 'mj-badge-info',
                        'refusé'   => 'mj-badge-danger',
                        default    => 'mj-badge-warning',
                    };
                    $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $d['id']]), 'mj_delete_devis_' . $d['id']);
                ?>
                <tr class="mj-doc-row" data-id="devis-<?php echo $d['id']; ?>">
                    <td>
                        <button class="mj-toggle-btn" onclick="mjToggle('devis-<?php echo $d['id']; ?>')" title="<?php _e('Voir les lignes', 'manajeni-connector'); ?>">
                            <span class="mj-toggle-icon">▶</span>
                        </button>
                    </td>
                    <td><strong><?php echo esc_html($d['reference']); ?></strong></td>
                    <td>
                        <div><?php echo esc_html($d['client']['nom'] ?? '-'); ?></div>
                        <small style="color:#888;"><?php echo esc_html($d['client']['ville'] ?? ''); ?></small>
                    </td>
                    <td><?php echo esc_html($d['date']); ?></td>
                    <td><?php echo number_format($d['montant_ht'], 2, ',', ' '); ?> DH</td>
                    <td><?php echo $d['tva']; ?>%</td>
                    <td><strong><?php echo number_format($d['montant_ttc'], 2, ',', ' '); ?> DH</strong></td>
                    <td><span class="mj-badge <?php echo $badge_class; ?>"><?php echo esc_html($d['statut']); ?></span></td>
                    <td>
                        <button class="mj-btn-icon" onclick='mjEditDevis(<?php echo json_encode($d); ?>)' title="<?php _e('Modifier', 'manajeni-connector'); ?>">✏️</button>
                        <button class="mj-btn-icon" title="<?php _e('PDF', 'manajeni-connector'); ?>" onclick="window.print()">📄</button>
                        <a href="<?php echo $delete_url; ?>" class="mj-btn-icon" style="text-decoration:none;" title="<?php _e('Supprimer', 'manajeni-connector'); ?>" onclick="return confirm('<?php _e('Confirmer la suppression ?', 'manajeni-connector'); ?>')">🗑️</a>
                    </td>
                </tr>
                <tr class="mj-doc-detail" id="detail-devis-<?php echo $d['id']; ?>" style="display:none;">
                    <td colspan="9">
                        <div class="mj-detail-inner">
                            <div class="mj-detail-header">
                                <div><strong>Client :</strong> <?php echo esc_html($d['client']['nom'] ?? '-'); ?> — ICE : <?php echo esc_html($d['client']['ice'] ?? '-'); ?> — <?php echo esc_html($d['client']['email'] ?? '-'); ?></div>
                            </div>
                            <table class="mj-lines-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Description</th>
                                        <th>Qté</th>
                                        <th>Prix Unitaire HT</th>
                                        <th>TVA</th>
                                        <th>Total HT</th>
                                        <th>Total TTC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($d['lignes'] as $i => $ligne): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo esc_html($ligne['description']); ?></td>
                                        <td><?php echo $ligne['quantite']; ?></td>
                                        <td><?php echo number_format($ligne['prix_unitaire'], 2, ',', ' '); ?> DH</td>
                                        <td><?php echo $ligne['tva']; ?>%</td>
                                        <td><?php echo number_format($ligne['total_ht'], 2, ',', ' '); ?> DH</td>
                                        <td><?php echo number_format($ligne['total_ht'] * (1 + $ligne['tva']/100), 2, ',', ' '); ?> DH</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="mj-doc-totals">
                                <div class="mj-total-row"><span>Sous-total HT</span><span><?php echo number_format($d['montant_ht'], 2, ',', ' '); ?> DH</span></div>
                                <div class="mj-total-row"><span>TVA (<?php echo $d['tva']; ?>%)</span><span><?php echo number_format($d['montant_ttc'] - $d['montant_ht'], 2, ',', ' '); ?> DH</span></div>
                                <div class="mj-total-row mj-total-final"><span>TOTAL TTC</span><span><?php echo number_format($d['montant_ttc'], 2, ',', ' '); ?> DH</span></div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
// Modale Nouveau Devis
$apps_handler->render_modal_start('modal-new-devis', __('Nouveau Devis', 'manajeni-connector'));
?>
<form method="post" action="">
    <?php wp_nonce_field('mj_devis_save', 'mj_nonce'); ?>
    <input type="hidden" name="devis_id" id="mj_devis_id">
    
    <div class="mj-form-group">
        <label><?php _e('Client', 'manajeni-connector'); ?></label>
        <select name="client_id" class="mj-form-control" required>
            <option value=""><?php _e('-- Choisir un client --', 'manajeni-connector'); ?></option>
            <?php foreach ($clients as $client): ?>
                <option value="<?php echo $client['id']; ?>"><?php echo esc_html($client['nom']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Référence', 'manajeni-connector'); ?></label>
        <input type="text" name="reference" class="mj-form-control" placeholder="ex: DEV-2024-XXX" required>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Montant HT (DH)', 'manajeni-connector'); ?></label>
        <input type="number" step="0.01" name="montant_ht" class="mj-form-control" placeholder="0.00" required>
    </div>

    <div class="mj-modal-footer">
        <button type="button" class="mj-btn-secondary" onclick="mjCloseModal('modal-new-devis')"><?php _e('Annuler', 'manajeni-connector'); ?></button>
        <button type="submit" name="create_devis" class="mj-btn-primary"><?php _e('Enregistrer', 'manajeni-connector'); ?></button>
    </div>
</form>
<?php 
$apps_handler->render_modal_end();
?>

<script>
function mjEditDevis(d) {
    document.getElementById('mj_devis_id').value = d.id;
    document.querySelector('#modal-new-devis h2').innerText = "✏️ <?php _e('Modifier Devis', 'manajeni-connector'); ?>";
    
    document.querySelector('select[name="client_id"]').value = d.client_id || '';
    document.querySelector('input[name="reference"]').value = d.reference;
    document.querySelector('input[name="montant_ht"]').value = d.montant_ht;
    
    mjOpenModal('modal-new-devis');
}

function mjResetDevisForm() {
    document.getElementById('mj_devis_id').value = '';
    document.querySelector('#modal-new-devis h2').innerText = "<?php _e('Nouveau Devis', 'manajeni-connector'); ?>";
    document.querySelector('#modal-new-devis form').reset();
}
</script>

<?php
include __DIR__ . '/_accordion_assets.php'; 
?>