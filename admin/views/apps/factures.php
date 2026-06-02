<?php
/**
 * Module Factures - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Factures_Controller :
// $factures, $stats, $apps_handler, $clients, $message, $message_type
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1><?php _e('🧾 Factures', 'manajeni-connector'); ?></h1><p><?php _e('Gérez votre facturation et suivez vos paiements', 'manajeni-connector'); ?></p></div>
        <button class="mj-btn-primary" onclick="mjResetFactureForm(); mjOpenModal('modal-new-facture')"><?php _e('+ Nouvelle facture', 'manajeni-connector'); ?></button>
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
                    <th><?php _e('Échéance', 'manajeni-connector'); ?></th>
                    <th><?php _e('Montant HT', 'manajeni-connector'); ?></th>
                    <th><?php _e('TVA', 'manajeni-connector'); ?></th>
                    <th><?php _e('Montant TTC', 'manajeni-connector'); ?></th>
                    <th><?php _e('Statut', 'manajeni-connector'); ?></th>
                    <th><?php _e('Actions', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($factures as $f): ?>
                <?php
                    $badge_class = match($f['statut']) {
                        'payée'                  => 'mj-badge-success',
                        'impayée'                => 'mj-badge-danger',
                        'partiellement_payée'    => 'mj-badge-warning',
                        default                  => 'mj-badge-info',
                    };
                    $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $f['id']]), 'mj_delete_facture_' . $f['id']);
                ?>
                <tr class="mj-doc-row" data-id="fac-<?php echo $f['id']; ?>">
                    <td>
                        <button class="mj-toggle-btn" onclick="mjToggle('fac-<?php echo $f['id']; ?>')" title="<?php _e('Voir les lignes', 'manajeni-connector'); ?>">
                            <span class="mj-toggle-icon">▶</span>
                        </button>
                    </td>
                    <td><strong><?php echo esc_html($f['reference']); ?></strong></td>
                    <td>
                        <div><?php echo esc_html($f['client']['nom'] ?? '-'); ?></div>
                        <small style="color:#888;"><?php echo esc_html($f['client']['ville'] ?? ''); ?></small>
                    </td>
                    <td><?php echo esc_html($f['date']); ?></td>
                    <td><?php echo esc_html($f['date_echeance']); ?></td>
                    <td><?php echo number_format($f['montant_ht'], 2, ',', ' '); ?> DH</td>
                    <td><?php echo $f['tva']; ?>%</td>
                    <td><strong><?php echo number_format($f['montant_ttc'], 2, ',', ' '); ?> DH</strong></td>
                    <td><span class="mj-badge <?php echo $badge_class; ?>"><?php echo esc_html($f['statut']); ?></span></td>
                    <td>
                        <button class="mj-btn-icon" onclick='mjEditFacture(<?php echo json_encode($f); ?>)' title="<?php _e('Modifier', 'manajeni-connector'); ?>">✏️</button>
                        <button class="mj-btn-icon" title="<?php _e('PDF', 'manajeni-connector'); ?>" onclick="window.print()">📄</button>
                        <a href="<?php echo $delete_url; ?>" class="mj-btn-icon" style="text-decoration:none;" title="<?php _e('Supprimer', 'manajeni-connector'); ?>" onclick="return confirm('<?php _e('Confirmer la suppression ?', 'manajeni-connector'); ?>')">🗑️</a>
                    </td>
                </tr>
                <tr class="mj-doc-detail" id="detail-fac-<?php echo $f['id']; ?>" style="display:none;">
                    <td colspan="10">
                        <div class="mj-detail-inner">
                            <div class="mj-detail-header">
                                <strong>Client :</strong> <?php echo esc_html($f['client']['nom'] ?? '-'); ?>
                                &nbsp;|&nbsp; ICE : <?php echo esc_html($f['client']['ice'] ?? '-'); ?>
                                &nbsp;|&nbsp; <?php echo esc_html($f['client']['email'] ?? '-'); ?>
                            </div>
                            <table class="mj-lines-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Description</th>
                                        <th>Qté</th>
                                        <th>PU HT</th>
                                        <th>TVA</th>
                                        <th>Total HT</th>
                                        <th>Total TTC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($f['lignes'] as $i => $ligne): ?>
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
                                <div class="mj-total-row"><span>Sous-total HT</span><span><?php echo number_format($f['montant_ht'], 2, ',', ' '); ?> DH</span></div>
                                <div class="mj-total-row"><span>TVA (<?php echo $f['tva']; ?>%)</span><span><?php echo number_format($f['montant_ttc'] - $f['montant_ht'], 2, ',', ' '); ?> DH</span></div>
                                <div class="mj-total-row mj-total-final"><span>TOTAL TTC</span><span><?php echo number_format($f['montant_ttc'], 2, ',', ' '); ?> DH</span></div>
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
// Modale Nouvelle Facture
$apps_handler->render_modal_start('modal-new-facture', __('Nouvelle Facture', 'manajeni-connector'));
?>
<form method="post" action="">
    <?php wp_nonce_field('mj_facture_save', 'mj_nonce'); ?>
    <input type="hidden" name="facture_id" id="mj_facture_id">
    
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
        <input type="text" name="reference" class="mj-form-control" placeholder="ex: FAC-2024-XXX" required>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Montant HT (DH)', 'manajeni-connector'); ?></label>
        <input type="number" step="0.01" name="montant_ht" class="mj-form-control" placeholder="0.00" required>
    </div>

    <div class="mj-modal-footer">
        <button type="button" class="mj-btn-secondary" onclick="mjCloseModal('modal-new-facture')"><?php _e('Annuler', 'manajeni-connector'); ?></button>
        <button type="submit" name="create_facture" class="mj-btn-primary"><?php _e('Enregistrer', 'manajeni-connector'); ?></button>
    </div>
</form>
<?php 
$apps_handler->render_modal_end();
?>

<script>
function mjEditFacture(f) {
    document.getElementById('mj_facture_id').value = f.id;
    document.querySelector('#modal-new-facture h2').innerText = "✏️ <?php _e('Modifier Facture', 'manajeni-connector'); ?>";
    
    document.querySelector('select[name="client_id"]').value = f.client_id || '';
    document.querySelector('input[name="reference"]').value = f.reference;
    document.querySelector('input[name="montant_ht"]').value = f.montant_ht;
    
    mjOpenModal('modal-new-facture');
}

function mjResetFactureForm() {
    document.getElementById('mj_facture_id').value = '';
    document.querySelector('#modal-new-facture h2').innerText = "<?php _e('Nouvelle Facture', 'manajeni-connector'); ?>";
    document.querySelector('#modal-new-facture form').reset();
}
</script>

<?php
include __DIR__ . '/_accordion_assets.php'; 
?>