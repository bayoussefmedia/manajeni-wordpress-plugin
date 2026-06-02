<?php
/**
 * Module Paiements Fournisseurs - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Paiements_Controller :
// $paiements, $stats, $apps_handler, $fournisseurs, $message, $message_type
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1><?php _e('💸 Paiements Fournisseurs', 'manajeni-connector'); ?></h1><p><?php _e('Suivi des sorties d\'argent et règlements factures', 'manajeni-connector'); ?></p></div>
        <button class="mj-btn-primary" onclick="mjResetPaiementForm(); mjOpenModal('modal-new-paiement')"><?php _e('+ Nouveau paiement', 'manajeni-connector'); ?></button>
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
                    <th><?php _e('CATÉGORIE', 'manajeni-connector'); ?></th>
                    <th><?php _e('DATE', 'manajeni-connector'); ?></th>
                    <th><?php _e('MODE', 'manajeni-connector'); ?></th>
                    <th><?php _e('MONTANT HT', 'manajeni-connector'); ?></th>
                    <th><?php _e('TVA', 'manajeni-connector'); ?></th>
                    <th><?php _e('MONTANT TTC', 'manajeni-connector'); ?></th>
                    <th><?php _e('ACTIONS', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paiements as $p): ?>
                <?php $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $p['id']]), 'mj_delete_paiement_' . $p['id']); ?>
                <tr class="mj-doc-row" data-id="pay-<?php echo $p['id']; ?>">
                    <td>
                        <button class="mj-toggle-btn" onclick="mjToggle('pay-<?php echo $p['id']; ?>')">
                            <span class="mj-toggle-icon">▶</span>
                        </button>
                    </td>
                    <td><strong><?php echo esc_html($p['reference']); ?></strong></td>
                    <td>
                        <div><?php echo esc_html($p['fournisseur']['nom'] ?? '-'); ?></div>
                        <small style="color:var(--mj-slate-500);"><?php echo esc_html($p['fournisseur']['ville'] ?? ''); ?></small>
                    </td>
                    <td><span class="mj-badge mj-badge-info"><?php echo esc_html($p['categorie']); ?></span></td>
                    <td><?php echo esc_html($p['date']); ?></td>
                    <td><?php echo esc_html($p['mode']); ?></td>
                    <td><?php echo number_format($p['montant_ht'], 2, ',', ' '); ?> DH</td>
                    <td><?php echo $p['tva']; ?>%</td>
                    <td><strong><?php echo number_format($p['montant_ttc'], 2, ',', ' '); ?> DH</strong></td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="mj-btn-icon" onclick='mjEditPaiement(<?php echo json_encode($p); ?>)' title="<?php _e('Modifier', 'manajeni-connector'); ?>">✏️</button>
                            <a href="<?php echo $delete_url; ?>" class="mj-btn-icon" title="<?php _e('Supprimer', 'manajeni-connector'); ?>" onclick="return confirm('<?php _e('Confirmer la suppression ?', 'manajeni-connector'); ?>')">🗑️</a>
                        </div>
                    </td>
                </tr>
                <tr class="mj-doc-detail" id="detail-pay-<?php echo $p['id']; ?>" style="display:none;">
                    <td colspan="10">
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
                                    <?php foreach ($p['lignes'] as $i => $ligne): ?>
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
// Modale Nouveau Paiement
$apps_handler->render_modal_start('modal-new-paiement', __('Nouveau Paiement', 'manajeni-connector'));
?>
<form method="post" action="">
    <?php wp_nonce_field('mj_paiement_save', 'mj_nonce'); ?>
    <input type="hidden" name="paiement_id" id="mj_paiement_id">
    
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
        <label><?php _e('Référence / Libellé', 'manajeni-connector'); ?></label>
        <input type="text" name="reference" class="mj-form-control" placeholder="ex: PAY-2024-001" required>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
        <div class="mj-form-group">
            <label><?php _e('Catégorie', 'manajeni-connector'); ?></label>
            <select name="categorie" class="mj-form-control">
                <option value="Achat"><?php _e('Achat de marchandises', 'manajeni-connector'); ?></option>
                <option value="Services"><?php _e('Services extérieurs', 'manajeni-connector'); ?></option>
                <option value="Loyer"><?php _e('Loyer', 'manajeni-connector'); ?></option>
                <option value="Autre"><?php _e('Autre charge', 'manajeni-connector'); ?></option>
            </select>
        </div>
        <div class="mj-form-group">
            <label><?php _e('Mode de règlement', 'manajeni-connector'); ?></label>
            <select name="mode" class="mj-form-control">
                <option value="Virement">Virement</option>
                <option value="Chèque">Chèque</option>
                <option value="Espèces">Espèces</option>
            </select>
        </div>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Montant HT (DH)', 'manajeni-connector'); ?></label>
        <input type="number" step="0.01" name="montant_ht" class="mj-form-control" placeholder="0.00" required>
    </div>

    <div class="mj-modal-footer">
        <button type="button" class="mj-btn-secondary" onclick="mjCloseModal('modal-new-paiement')"><?php _e('Annuler', 'manajeni-connector'); ?></button>
        <button type="submit" name="add_paiement" class="mj-btn-primary"><?php _e('Enregistrer', 'manajeni-connector'); ?></button>
    </div>
</form>
<?php 
$apps_handler->render_modal_end();
?>

<script>
function mjEditPaiement(p) {
    document.getElementById('mj_paiement_id').value = p.id;
    document.querySelector('#modal-new-paiement h2').innerText = "✏️ <?php _e('Modifier Paiement', 'manajeni-connector'); ?>";
    
    document.querySelector('select[name="fournisseur_id"]').value = p.fournisseur_id || '';
    document.querySelector('input[name="reference"]').value = p.reference;
    document.querySelector('select[name="categorie"]').value = p.categorie;
    document.querySelector('select[name="mode"]').value = p.mode;
    document.querySelector('input[name="montant_ht"]').value = p.montant_ht;
    
    mjOpenModal('modal-new-paiement');
}

function mjResetPaiementForm() {
    document.getElementById('mj_paiement_id').value = '';
    document.querySelector('#modal-new-paiement h2').innerText = "<?php _e('Nouveau Paiement', 'manajeni-connector'); ?>";
    document.querySelector('#modal-new-paiement form').reset();
}
</script>

<?php
include __DIR__ . '/_accordion_assets.php'; 
?>