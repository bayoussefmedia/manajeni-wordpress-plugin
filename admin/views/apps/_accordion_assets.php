<?php
/**
 * Partial partagé : Styles & JS pour l'accordéon de lignes de commande
 * Inclus dans toutes les vues de documents financiers
 */
?>
<script>
if (!window.mjToggle) {
    window.mjToggle = function(id) {
        var detail = document.getElementById('detail-' + id);
        var btn = document.querySelector('[data-id="' + id + '"] .mj-toggle-icon');
        if (!detail) return;
        var isOpen = detail.style.display !== 'none';
        detail.style.display = isOpen ? 'none' : 'table-row';
        if (btn) btn.classList.toggle('open', !isOpen);
    };
}
</script>
