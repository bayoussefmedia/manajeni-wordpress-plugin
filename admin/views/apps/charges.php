<?php
/**
 * Module Charges - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Charges_Controller :
// $charges, $stats, $apps_handler, $fournisseurs, $message, $message_type
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1><?php _e('📉 Charges', 'manajeni-connector'); ?></h1><p><?php _e('Suivi des dépenses et frais généraux', 'manajeni-connector'); ?></p></div>
        <button class="mj-btn-primary" onclick="mjResetChargeForm(); mjOpenModal('modal-new-charge')"><?php _e('+ Nouvelle charge', 'manajeni-connector'); ?></button>
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
                    <th><?php _e('RÉFÉRENCE', 'manajeni-connector'); ?></th>
                    <th><?php _e('FOURNISSEUR', 'manajeni-connector'); ?></th>
                    <th><?php _e('DATE', 'manajeni-connector'); ?></th>
                    <th><?php _e('MONTANT HT', 'manajeni-connector'); ?></th>
                    <th><?php _e('TVA', 'manajeni-connector'); ?></th>
                    <th><?php _e('MONTANT TTC', 'manajeni-connector'); ?></th>
                    <th><?php _e('STATUT', 'manajeni-connector'); ?></th>
                    <th><?php _e('ACTIONS', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($charges as $c): ?>
                <?php
                    $badge_bg = match($c['statut']) {
                        'payée'   => '#e6f6e8',
                        'à_payer' => '#fef2f2',
                        default   => '#f1f5f9',
                    };
                    $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $c['id']]), 'mj_delete_charge_' . $c['id']);
                ?>
                <tr class="mj-doc-row" data-id="chg-<?php echo $c['id']; ?>">
                    <td>
                        <button class="mj-toggle-btn" onclick="mjToggle('chg-<?php echo $c['id']; ?>')">
                            <span class="mj-toggle-icon">▶</span>
                        </button>
                    </td>
                    <td><strong><?php echo esc_html($c['reference']); ?></strong></td>
                    <td>
                        <div><?php echo esc_html($c['fournisseur']['nom'] ?? '-'); ?></div>
                        <small style="color:#888;"><?php echo esc_html($c['fournisseur']['ville'] ?? ''); ?></small>
                    </td>
                    <td><?php echo esc_html($c['date']); ?></td>
                    <td><?php echo number_format($c['montant_ht'], 2, ',', ' '); ?> DH</td>
                    <td><?php echo $c['tva']; ?>%</td>
                    <td><strong><?php echo number_format($c['montant_ttc'], 2, ',', ' '); ?> DH</strong></td>
                    <td><span class="mj-badge" style="background:<?php echo $badge_bg; ?>"><?php echo esc_html($c['statut']); ?></span></td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="mj-btn-icon" onclick='mjEditCharge(<?php echo json_encode($c); ?>)' title="<?php _e('Modifier', 'manajeni-connector'); ?>">✏️</button>
                            <a href="<?php echo $delete_url; ?>" class="mj-btn-icon" title="<?php _e('Supprimer', 'manajeni-connector'); ?>" onclick="return confirm('<?php _e('Confirmer la suppression ?', 'manajeni-connector'); ?>')">🗑️</a>
                        </div>
                    </td>
                </tr>
                <tr class="mj-doc-detail" id="detail-chg-<?php echo $c['id']; ?>" style="display:none;">
                    <td colspan="9">
                        <div class="mj-detail-inner">
                            <table class="mj-lines-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Description</th>
                                        <th>Total HT</th>
                                        <th>TVA</th>
                                        <th>Total TTC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($c['lignes'] as $i => $ligne): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo esc_html($ligne['description']); ?></td>
                                        <td><?php echo number_format($ligne['total_ht'], 2, ',', ' '); ?> DH</td>
                                        <td><?php echo $ligne['tva']; ?>%</td>
                                        <td><?php echo number_format($ligne['total_ht'] * (1 + $ligne['tva']/100), 2, ',', ' '); ?> DH</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
// Modale Nouvelle Charge
$apps_handler->render_modal_start('modal-new-charge', __('Nouvelle Charge', 'manajeni-connector'));
?>
<form method="post" action="">
    <?php wp_nonce_field('mj_charge_save', 'mj_nonce'); ?>
    <input type="hidden" name="charge_id" id="mj_charge_id">
    
    <div class="mj-form-group">
        <label><?php _e('Fournisseur', 'manajeni-connector'); ?></label>
        <select name="fournisseur_id" class="mj-form-control" required>
            <option value=""><?php _e('-- Choisir un fournisseur --', 'manajeni-connector'); ?></option>
            <?php foreach ($fournisseurs as $f): ?>
                <option value="<?php echo $f['id']; ?>"><?php echo esc_html($f['nom']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Référence / Objet', 'manajeni-connector'); ?></label>
        <input type="text" name="reference" class="mj-form-control" placeholder="ex: CHG-2024-001" required>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Montant HT (DH)', 'manajeni-connector'); ?></label>
        <input type="number" step="0.01" name="montant_ht" class="mj-form-control" placeholder="0.00" required>
    </div>

    <div class="mj-modal-footer">
        <button type="button" class="mj-btn-secondary" onclick="mjCloseModal('modal-new-charge')"><?php _e('Annuler', 'manajeni-connector'); ?></button>
        <button type="submit" name="add_charge" class="mj-btn-primary"><?php _e('Enregistrer', 'manajeni-connector'); ?></button>
    </div>
</form>
<?php 
$apps_handler->render_modal_end();
?>

<script>
function mjEditCharge(c) {
    document.getElementById('mj_charge_id').value = c.id;
    document.querySelector('#modal-new-charge h2').innerText = "✏️ <?php _e('Modifier Charge', 'manajeni-connector'); ?>";
    
    document.querySelector('select[name="fournisseur_id"]').value = c.fournisseur_id || '';
    document.querySelector('input[name="reference"]').value = c.reference;
    document.querySelector('input[name="montant_ht"]').value = c.montant_ht;
    
    mjOpenModal('modal-new-charge');
}

function mjResetChargeForm() {
    document.getElementById('mj_charge_id').value = '';
    document.querySelector('#modal-new-charge h2').innerText = "<?php _e('Nouvelle Charge', 'manajeni-connector'); ?>";
    document.querySelector('#modal-new-charge form').reset();
}
</script>

<?php
include __DIR__ . '/_accordion_assets.php'; 
?>