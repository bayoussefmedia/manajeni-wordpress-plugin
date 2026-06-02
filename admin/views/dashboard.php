<?php
/**
 * Dashboard Principal Manajeni - Hub des Applications (FIXED)
 * Fichier: admin/views/dashboard.php
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé.');
}

$current_user = wp_get_current_user();
$user_label = $current_user && $current_user->exists() ? $current_user->display_name : 'Administrateur';

$applications = [
    'clients' => [
        'name' => 'Clients',
        'icon' => '👥',
        'category' => 'Commercial',
        'description' => 'Gérez votre base clients et fiches de contact',
        'features' => ['Fiches CRM', 'Segmentation', 'Historique']
    ],
    'devis' => [
        'name' => 'Devis',
        'icon' => '📄',
        'category' => 'Commercial',
        'description' => 'Créez et gérez vos devis professionnels',
        'features' => ['Gabarits PDF', 'Signature', 'Statuts']
    ],
    'factures' => [
        'name' => 'Factures',
        'icon' => '🧾',
        'category' => 'Commercial',
        'description' => 'Gestion complète de votre facturation',
        'features' => ['Paiements', 'Relances', 'Export PDF']
    ],
    'catalogue' => [
        'name' => 'Catalogue',
        'icon' => '📦',
        'category' => 'Catalogue',
        'description' => 'Produits, services et gestion des stocks',
        'features' => ['Articles', 'Prix/TVA', 'Catégories']
    ],
    'paiements' => [
        'name' => 'Paiements',
        'icon' => '💸',
        'category' => 'Finances',
        'description' => 'Suivi des sorties d\'argent fournisseurs',
        'features' => ['Règlements', 'Modes paiement', 'Journal']
    ],
    'charges' => [
        'name' => 'Charges',
        'icon' => '📉',
        'category' => 'Finances',
        'description' => 'Suivi exhaustif des dépenses et frais',
        'features' => ['TVA déductible', 'Analytique', 'Justificatifs']
    ],
    'projets' => [
        'name' => 'Projets',
        'icon' => '🎯',
        'category' => 'Projets',
        'description' => 'Pilotage structuré et suivi budgétaire',
        'features' => ['Budgets', 'Rentabilité', 'Équipes']
    ],
    'taches' => [
        'name' => 'Tâches',
        'icon' => '✅',
        'category' => 'Projets',
        'description' => 'Organisation du travail et priorités',
        'features' => ['Assignations', 'Échéances', 'Checklists']
    ],
    'rendez_vous' => [
        'name' => 'Rendez-vous',
        'icon' => '📅',
        'category' => 'Commercial',
        'description' => 'Planification réunions et événements',
        'features' => ['Calendrier', 'Invitations', 'Compte-rendus']
    ],
    'fournisseurs' => [
        'name' => 'Fournisseurs',
        'icon' => '🏭',
        'category' => 'Commercial',
        'description' => 'Base partenaires et prestataires',
        'features' => ['Coordonnées', 'ICE/IF', 'Historique']
    ],
    'rapports' => [
        'name' => 'Rapports',
        'icon' => '📈',
        'category' => 'Organisation',
        'description' => 'Analyses et performances entreprise',
        'features' => ['CA/Bénéfices', 'Trésorerie', 'Visualisation']
    ],
];
?>

<div class="mj-app-container">
    
    <!-- Header Majestic -->
    <div class="mj-apps-header">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:30px;">
            <div style="display:flex; align-items:center; gap:25px;">
                <span style="font-size:50px; line-height:1;">✨</span>
                <div style="display:flex; flex-direction:column; gap:5px;">
                    <h1 class="mj-logo-title" style="margin:0 !important;">Manajeni Workspace</h1>
                    <div style="display:flex; gap:35px; margin-top:15px; border-top:1px solid rgba(255,255,255,0.1); padding-top:15px;">
                        <div><div style="font-size:32px; font-weight:800; line-height:1;"><?php echo count($applications); ?></div><div style="font-size:11px; opacity:0.6; text-transform:uppercase; letter-spacing:1px; margin-top:5px;">Applications Actives</div></div>
                        <div><div style="font-size:32px; font-weight:800; line-height:1;">Hub</div><div style="font-size:11px; opacity:0.6; text-transform:uppercase; letter-spacing:1px; margin-top:5px;">Architecture Core</div></div>
                        <div><div style="font-size:32px; font-weight:800; line-height:1;">Pro</div><div style="font-size:11px; opacity:0.6; text-transform:uppercase; letter-spacing:1px; margin-top:5px;">Édition Entreprise</div></div>
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
                <a href="<?php echo admin_url('admin.php?page=manajeni-logout'); ?>" class="mj-logout-btn">Logout 🚪</a>
            </div>
        </div>
    </div>

    <!-- Unified Search & Filter Container (FIXED CLASS) -->
    <div class="mj-search-container-box">
        <div style="flex-grow:1; max-width: 450px; position:relative;">
            <span style="position:absolute; left:16px; top:50%; transform:translateY(-50%); font-size:18px; opacity:0.5;">🔍</span>
            <input type="text" id="appSearch" class="mj-form-control" placeholder="Rechercher une application..." style="padding-left: 50px !important;">
        </div>
        <div style="display:flex; gap:12px;" id="catFilters">
            <button class="mj-btn-secondary active" data-cat="all">Toutes</button>
            <button class="mj-btn-secondary" data-cat="Commercial">Commercial</button>
            <button class="mj-btn-secondary" data-cat="Finances">Finances</button>
            <button class="mj-btn-secondary" data-cat="Projets">Projets</button>
        </div>
    </div>

    <!-- App Grid -->
    <div class="mj-hub-grid" id="appsGrid">
        <?php foreach ($applications as $id => $app): ?>
        <a href="<?php echo admin_url('admin.php?page=manajeni-' . $id); ?>" class="mj-hub-card" data-cat="<?php echo $app['category']; ?>" data-name="<?php echo strtolower($app['name']); ?>">
            <div class="mj-hub-icon">
                <?php echo $app['icon']; ?>
            </div>
            <div class="mj-hub-title"><?php echo $app['name']; ?></div>
            <div class="mj-hub-desc"><?php echo $app['description']; ?></div>
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:5px;">
                <?php foreach ($app['features'] as $f): ?>
                <span class="mj-badge" style="background:var(--mj-slate-50); color:var(--mj-slate-500); border:1px solid var(--mj-slate-100);">
                    <?php echo esc_html($f); ?>
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

    <div id="noResults" style="display:none; text-align:center; padding: 120px 0; background:white; border-radius:30px; box-shadow:var(--mj-shadow-md);">
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
        cards.forEach(card => {
            const cat = card.dataset.cat;
            const name = card.dataset.name;
            const matchesCat = activeCat === 'all' || cat === activeCat;
            const matchesSearch = name.includes(searchQuery);
            
            if(matchesCat && matchesSearch) {
                card.style.display = 'flex';
                count++;
            } else {
                card.style.display = 'none';
            }
        });
        
        if (count === 0) {
            grid.style.display = 'none';
            noResults.style.display = 'block';
        } else {
            grid.style.display = 'grid';
            noResults.style.display = 'none';
        }
    };
    
    searchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value.toLowerCase();
        filter();
    });
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeCat = btn.dataset.cat;
            filter();
        });
    });
});
</script>
