<?php
/**
 * Dashboard principal Manajeni.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé.');
}

$current_user = wp_get_current_user();
$user_label = $current_user && $current_user->exists() ? $current_user->display_name : 'Administrateur';
$apps_access = manajeni_connector_get_apps_access();
$applications = $apps_access->get_visible_apps();
$active_apps_count = count($applications);
?>

<div class="mj-app-container">
    <div class="mj-apps-header">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:30px;">
            <div style="display:flex; align-items:center; gap:25px;">
                <span style="font-size:50px; line-height:1;">✨</span>
                <div style="display:flex; flex-direction:column; gap:5px;">
                    <h1 class="mj-logo-title" style="margin:0 !important;">Manajeni Workspace</h1>
                    <div style="display:flex; gap:35px; margin-top:15px; border-top:1px solid rgba(255,255,255,0.1); padding-top:15px;">
                        <div><div style="font-size:32px; font-weight:800; line-height:1;"><?php echo esc_html($active_apps_count); ?></div><div style="font-size:11px; opacity:0.6; text-transform:uppercase; letter-spacing:1px; margin-top:5px;">Applications Actives</div></div>
                        <div><div style="font-size:32px; font-weight:800; line-height:1;">Hub</div><div style="font-size:11px; opacity:0.6; text-transform:uppercase; letter-spacing:1px; margin-top:5px;">Architecture Core</div></div>
                        <div><div style="font-size:32px; font-weight:800; line-height:1;">Pro</div><div style="font-size:11px; opacity:0.6; text-transform:uppercase; letter-spacing:1px; margin-top:5px;">Edition Entreprise</div></div>
                    </div>
                </div>
            </div>
            <div class="mj-user-info">
                <div style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg, #6366f1, #a855f7); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px;">
                    <?php echo esc_html(strtoupper(substr($user_label, 0, 1))); ?>
                </div>
                <div style="flex-grow:1">
                    <div style="font-weight:800; font-size:15px;"><?php echo esc_html($user_label); ?></div>
                    <div style="font-size:11px; opacity:0.7"><?php echo manajeni_connector_is_dev_mode() ? 'Mode Developpement' : 'Production API Active'; ?></div>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=manajeni-logout')); ?>" class="mj-logout-btn">Logout 🚪</a>
            </div>
        </div>
    </div>

    <div class="mj-search-container-box">
        <div style="flex-grow:1; max-width:450px; position:relative;">
            <span style="position:absolute; left:16px; top:50%; transform:translateY(-50%); font-size:18px; opacity:0.5;">🔍</span>
            <input type="text" id="appSearch" class="mj-form-control" placeholder="Rechercher une application..." style="padding-left:50px !important;">
        </div>
        <div style="display:flex; gap:12px;" id="catFilters">
            <button class="mj-btn-secondary active" data-cat="all">Toutes</button>
            <button class="mj-btn-secondary" data-cat="Commercial">Commercial</button>
            <button class="mj-btn-secondary" data-cat="Finances">Finances</button>
            <button class="mj-btn-secondary" data-cat="Projets">Projets</button>
        </div>
    </div>

    <?php if (empty($applications)) : ?>
        <div class="mj-table-wrapper" style="padding:40px; text-align:center;">
            <h2 style="margin-top:0;">Aucune application active</h2>
            <p style="margin-bottom:0; color:var(--mj-slate-500);">
                Aucun module metier n'est autorise pour cette cle API ou l'API capabilities est indisponible.
            </p>
        </div>
    <?php else : ?>
        <div class="mj-hub-grid" id="appsGrid">
            <?php foreach ($applications as $id => $app) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=manajeni-' . $id)); ?>" class="mj-hub-card" data-cat="<?php echo esc_attr($app['category']); ?>" data-name="<?php echo esc_attr(strtolower($app['name'])); ?>">
                    <div class="mj-hub-icon">
                        <?php echo esc_html($app['icon']); ?>
                    </div>
                    <div class="mj-hub-title"><?php echo esc_html($app['name']); ?></div>
                    <div class="mj-hub-desc"><?php echo esc_html($app['description']); ?></div>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:5px;">
                        <?php foreach ($app['features'] as $feature) : ?>
                            <span class="mj-badge" style="background:var(--mj-slate-50); color:var(--mj-slate-500); border:1px solid var(--mj-slate-100);">
                                <?php echo esc_html($feature); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:auto; padding-top:15px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--mj-slate-50);">
                        <span class="mj-badge" style="background:#e6f6e8; color:#059669;">ACTIF</span>
                        <span style="font-size:20px; color:var(--mj-primary); font-weight:800;">→</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div id="noResults" style="display:none; text-align:center; padding:120px 0; background:white; border-radius:30px; box-shadow:var(--mj-shadow-md);">
        <div style="font-size:70px; margin-bottom:20px;">🔍</div>
        <h2 style="font-weight:800; font-family:'Outfit',sans-serif; color:var(--mj-slate-900)">Aucun résultat trouvé</h2>
        <p style="color:var(--mj-slate-500); font-size:16px;">Essayez avec d'autres mots-clés ou filtres de catégorie.</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('appSearch');
    const filterBtns = document.querySelectorAll('#catFilters button');
    const cards = document.querySelectorAll('.mj-hub-card');
    const grid = document.getElementById('appsGrid');
    const noResults = document.getElementById('noResults');

    let activeCat = 'all';
    let searchQuery = '';

    const filter = () => {
        let count = 0;

        cards.forEach((card) => {
            const cat = card.dataset.cat;
            const name = card.dataset.name;
            const matchesCat = activeCat === 'all' || cat === activeCat;
            const matchesSearch = name.includes(searchQuery);

            if (matchesCat && matchesSearch) {
                card.style.display = 'flex';
                count++;
            } else {
                card.style.display = 'none';
            }
        });

        if (!grid) {
            return;
        }

        if (count === 0) {
            grid.style.display = 'none';
            noResults.style.display = cards.length ? 'block' : 'none';
        } else {
            grid.style.display = 'grid';
            noResults.style.display = 'none';
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase();
            filter();
        });
    }

    filterBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            filterBtns.forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            activeCat = btn.dataset.cat;
            filter();
        });
    });

    filter();
});
</script>
