<?php
/**
 * Module Rendez-vous - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Rendez_Vous_Controller :
// $rendez_vous, $stats, $apps_handler
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1><?php _e('📅 Rendez-vous', 'manajeni-connector'); ?></h1><p><?php _e('Planification réunions et événements', 'manajeni-connector'); ?></p></div>
        <button class="mj-btn-primary" onclick="mjResetRdvForm(); mjOpenModal('modal-new-rdv')"><?php _e('+ Planifier', 'manajeni-connector'); ?></button>
    </div>
    
    <?php 
    if (!empty($message)) $apps_handler->show_message($message, $message_type);
    $apps_handler->render_stats_cards($stats); 
    ?>
    
    <div class="mj-table-wrapper">
        <table class="mj-table">
            <thead>
                <tr>
                    <th><?php _e('OBJET', 'manajeni-connector'); ?></th>
                    <th><?php _e('DATE', 'manajeni-connector'); ?></th>
                    <th><?php _e('HEURE', 'manajeni-connector'); ?></th>
                    <th><?php _e('LIEU', 'manajeni-connector'); ?></th>
                    <th><?php _e('STATUT', 'manajeni-connector'); ?></th>
                    <th><?php _e('ACTIONS', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rendez_vous as $r): ?>
                <?php $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $r['id']]), 'mj_delete_rdv_' . $r['id']); ?>
                <tr>
                    <td><strong><?php echo esc_html($r['objet']); ?></strong></td>
                    <td><?php echo esc_html($r['date']); ?></td>
                    <td><?php echo esc_html($r['heure']); ?></td>
                    <td><?php echo esc_html($r['lieu']); ?></td>
                    <td><span class="mj-badge mj-badge-info"><?php echo esc_html($r['statut']); ?></span></td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="mj-btn-icon" onclick='mjEditRdv(<?php echo json_encode($r); ?>)' title="<?php _e('Modifier', 'manajeni-connector'); ?>">✏️</button>
                            <a href="<?php echo $delete_url; ?>" class="mj-btn-icon" title="<?php _e('Annuler', 'manajeni-connector'); ?>" onclick="return confirm('<?php _e('Annuler ce rendez-vous ?', 'manajeni-connector'); ?>')">🗑️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
$apps_handler->render_modal_start('modal-new-rdv', __('Nouveau Rendez-vous', 'manajeni-connector'));
?>
<form method="post" action="">
    <?php wp_nonce_field('mj_rdv_save', 'mj_nonce'); ?>
    <input type="hidden" name="rdv_id" id="mj_rdv_id">
    
    <div class="mj-form-group">
        <label><?php _e('Objet du rendez-vous', 'manajeni-connector'); ?></label>
        <input type="text" name="objet" class="mj-form-control" required>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
        <div class="mj-form-group">
            <label><?php _e('Date', 'manajeni-connector'); ?></label>
            <input type="date" name="date" class="mj-form-control" required>
        </div>
        <div class="mj-form-group">
            <label><?php _e('Heure', 'manajeni-connector'); ?></label>
            <input type="time" name="heure" class="mj-form-control" required>
        </div>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Lieu / Lien Meet', 'manajeni-connector'); ?></label>
        <input type="text" name="lieu" class="mj-form-control">
    </div>

    <div class="mj-modal-footer">
        <button type="button" class="mj-btn-secondary" onclick="mjCloseModal('modal-new-rdv')"><?php _e('Annuler', 'manajeni-connector'); ?></button>
        <button type="submit" name="add_rdv" class="mj-btn-primary"><?php _e('Planifier', 'manajeni-connector'); ?></button>
    </div>
</form>
<?php 
$apps_handler->render_modal_end();
?>

<script>
function mjEditRdv(r) {
    document.getElementById('mj_rdv_id').value = r.id;
    document.querySelector('#modal-new-rdv h2').innerText = "✏️ <?php _e('Modifier Rendez-vous', 'manajeni-connector'); ?>";
    
    document.querySelector('input[name="objet"]').value = r.objet;
    document.querySelector('input[name="date"]').value = r.date;
    document.querySelector('input[name="heure"]').value = r.heure;
    document.querySelector('input[name="lieu"]').value = r.lieu || '';
    
    mjOpenModal('modal-new-rdv');
}

function mjResetRdvForm() {
    document.getElementById('mj_rdv_id').value = '';
    document.querySelector('#modal-new-rdv h2').innerText = "<?php _e('Nouveau Rendez-vous', 'manajeni-connector'); ?>";
    document.querySelector('#modal-new-rdv form').reset();
}
</script>