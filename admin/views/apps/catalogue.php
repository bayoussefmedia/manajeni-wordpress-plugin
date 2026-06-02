<?php
/**
 * Module Catalogue - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Catalogue_Controller :
// $catalogue, $stats, $apps_handler, $message, $message_type
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1><?php _e('📦 Catalogue', 'manajeni-connector'); ?></h1><p><?php _e('Gérez vos produits et services', 'manajeni-connector'); ?></p></div>
        <button class="mj-btn-primary" onclick="mjResetCatalogueForm(); mjOpenModal('modal-new-article')"><?php _e('+ Nouvel article', 'manajeni-connector'); ?></button>
    </div>

    <?php 
    if (!empty($message)) $apps_handler->show_message($message, $message_type);
    $apps_handler->render_stats_cards($stats); 
    ?>

    <div class="mj-table-wrapper">
        <table class="mj-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th><?php _e('NOM', 'manajeni-connector'); ?></th>
                    <th><?php _e('TYPE', 'manajeni-connector'); ?></th>
                    <th><?php _e('PRIX HT', 'manajeni-connector'); ?></th>
                    <th><?php _e('TAXE', 'manajeni-connector'); ?></th>
                    <th><?php _e('STATUT', 'manajeni-connector'); ?></th>
                    <th><?php _e('ACTIONS', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($catalogue as $item): ?>
                <?php $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $item['id']]), 'mj_delete_catalogue_' . $item['id']); ?>
                <tr>
                    <td><code style="background:var(--mj-slate-50); padding:4px 8px; border-radius:6px; font-size:12px; border:1px solid var(--mj-slate-100);"><?php echo esc_html($item['sku']); ?></code></td>
                    <td><strong><?php echo esc_html($item['name']); ?></strong><br><small style="color:var(--mj-slate-500);"><?php echo esc_html($item['category']); ?></small></td>
                    <td><?php echo $item['type'] === 'service' ? '🛠️ Service' : '📦 Produit'; ?></td>
                    <td><?php echo number_format($item['price'], 2, ',', ' '); ?> DH</td>
                    <td><?php echo $item['tax']; ?>%</td>
                    <td><span class="mj-badge mj-badge-info"><?php echo esc_html($item['status']); ?></span></td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="mj-btn-icon" onclick='mjEditCatalogue(<?php echo json_encode($item); ?>)' title="<?php _e('Modifier', 'manajeni-connector'); ?>">✏️</button>
                            <a href="<?php echo $delete_url; ?>" class="mj-btn-icon" title="<?php _e('Supprimer', 'manajeni-connector'); ?>" onclick="return confirm('<?php _e('Confirmer la suppression ?', 'manajeni-connector'); ?>')">🗑️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
// Modale Nouvel Article
$apps_handler->render_modal_start('modal-new-article', __('Nouvel Article', 'manajeni-connector'));
?>
<form method="post" action="">
    <?php wp_nonce_field('mj_catalogue_save', 'mj_nonce'); ?>
    <input type="hidden" name="article_id" id="mj_article_id">
    
    <div class="mj-form-group">
        <label><?php _e('Nom de l\'article', 'manajeni-connector'); ?></label>
        <input type="text" name="name" class="mj-form-control" placeholder="ex: Maintenance Serveur" required>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
        <div class="mj-form-group">
            <label><?php _e('SKU / Code', 'manajeni-connector'); ?></label>
            <input type="text" name="sku" class="mj-form-control" placeholder="ex: REF-001" required>
        </div>
        <div class="mj-form-group">
            <label><?php _e('Catégorie', 'manajeni-connector'); ?></label>
            <input type="text" name="category" class="mj-form-control" placeholder="ex: Informatique">
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
        <div class="mj-form-group">
            <label><?php _e('Type', 'manajeni-connector'); ?></label>
            <select name="type" class="mj-form-control">
                <option value="service"><?php _e('Service', 'manajeni-connector'); ?></option>
                <option value="product"><?php _e('Produit', 'manajeni-connector'); ?></option>
            </select>
        </div>
        <div class="mj-form-group">
            <label><?php _e('Prix HT (DH)', 'manajeni-connector'); ?></label>
            <input type="number" step="0.01" name="price" class="mj-form-control" required>
        </div>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Taxe (TVA %)', 'manajeni-connector'); ?></label>
        <select name="tax" class="mj-form-control">
            <option value="20">20%</option>
            <option value="14">14%</option>
            <option value="10">10%</option>
            <option value="7">7%</option>
            <option value="0">Exonéré</option>
        </select>
    </div>

    <div class="mj-modal-footer">
        <button type="button" class="mj-btn-secondary" onclick="mjCloseModal('modal-new-article')"><?php _e('Annuler', 'manajeni-connector'); ?></button>
        <button type="submit" name="add_article" class="mj-btn-primary"><?php _e('Enregistrer', 'manajeni-connector'); ?></button>
    </div>
</form>
<?php 
$apps_handler->render_modal_end();
?>

<script>
function mjEditCatalogue(item) {
    document.getElementById('mj_article_id').value = item.id;
    document.querySelector('#modal-new-article h2').innerText = "✏️ <?php _e('Modifier Article', 'manajeni-connector'); ?>";
    
    document.querySelector('input[name="name"]').value = item.name;
    document.querySelector('input[name="sku"]').value = item.sku;
    document.querySelector('select[name="type"]').value = item.type;
    document.querySelector('input[name="price"]').value = item.price;
    document.querySelector('select[name="tax"]').value = item.tax;
    document.querySelector('input[name="category"]').value = item.category || '';
    
    mjOpenModal('modal-new-article');
}

function mjResetCatalogueForm() {
    document.getElementById('mj_article_id').value = '';
    document.querySelector('#modal-new-article h2').innerText = "<?php _e('Nouvel Article', 'manajeni-connector'); ?>";
    document.querySelector('#modal-new-article form').reset();
}
</script>