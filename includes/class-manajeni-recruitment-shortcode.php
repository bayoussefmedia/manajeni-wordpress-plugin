<?php
/**
 * Public recruitment shortcodes. Candidate data never passes through WordPress:
 * the validated application and private assessments run on Manajeni.
 */
if (!defined('ABSPATH')) { exit; }

final class Manajeni_Recruitment_Shortcode
{
    private const FEED_BASE = 'https://manajeni.com/widgets/recruitment/companies/';

    public static function register(): void
    {
        add_shortcode('manajeni_jobs', [self::class, 'render']);
        add_shortcode('manajeni_emplois', [self::class, 'render']);
    }

    public static function render($atts): string
    {
        $attributes = shortcode_atts([
            'company_id' => '',
            'limit' => '9',
            'show_search' => 'yes',
            'title' => 'Nos offres d’emploi',
        ], (array) $atts, 'manajeni_jobs');

        $companyId = absint($attributes['company_id']);
        if (!$companyId) {
            $companyId = absint(get_option('manajeni_recruitment_company_id', 0));
        }
        if (!$companyId) {
            return current_user_can('manage_options')
                ? '<p>'.esc_html__('Manajeni : précisez company_id dans le shortcode.', 'manajeni-connector').'</p>'
                : '';
        }

        $limit = min(24, max(1, absint($attributes['limit'])));
        $page = isset($_GET['mj_job_page']) && is_scalar($_GET['mj_job_page'])
            ? max(1, min(10000, absint(wp_unslash($_GET['mj_job_page'])))) : 1;
        $search = isset($_GET['mj_job_q']) && is_scalar($_GET['mj_job_q'])
            ? mb_substr(sanitize_text_field(wp_unslash($_GET['mj_job_q'])), 0, 120) : '';
        $url = add_query_arg(['page' => $page, 'per_page' => $limit, 'q' => $search], self::FEED_BASE.$companyId.'/offers');
        $response = wp_safe_remote_get($url, [
            'timeout' => 12,
            'redirection' => 0,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return '<p class="mj-jobs-error">'.esc_html__('Les offres sont temporairement indisponibles.', 'manajeni-connector').'</p>';
        }
        $payload = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            return '<p class="mj-jobs-error">'.esc_html__('Réponse emplois invalide.', 'manajeni-connector').'</p>';
        }

        $offers = $payload['data'];
        $lastPage = max(1, min(10000, (int) ($payload['meta']['last_page'] ?? 1)));
        $uid = wp_unique_id('mj-jobs-');
        ob_start();
        ?>
        <section class="mj-jobs" aria-label="<?php echo esc_attr($attributes['title']); ?>" id="<?php echo esc_attr($uid); ?>">
            <style>
                .mj-jobs{font:inherit;color:inherit;max-width:1200px;margin:1.5rem auto}
                .mj-jobs *{box-sizing:border-box}
                .mj-jobs h2{margin:0 0 1.2rem}
                .mj-jobs-search{display:flex;gap:.6rem;flex-wrap:wrap;margin:0 0 1.25rem}
                .mj-jobs-search input{flex:1 1 220px;padding:.75rem;border:1px solid #cbd5e1;border-radius:8px}
                .mj-jobs-search button,.mj-jobs-apply{background:#1764e9;border:0;color:#fff!important;border-radius:8px;padding:.72rem 1.1rem;text-decoration:none!important;display:inline-block;cursor:pointer}
                .mj-jobs-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,270px),1fr));gap:1.1rem}
                .mj-job{border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;background:#fff;color:#18283f}
                .mj-job img{display:block;width:100%;aspect-ratio:1200/630;object-fit:cover}
                .mj-job-body{padding:1.1rem}
                .mj-job h3{font-size:1.15rem;line-height:1.35;margin:0 0 .55rem}
                .mj-job-meta{color:#475569;font-size:.89rem;margin:.45rem 0}
                .mj-job-desc{font-size:.92rem;line-height:1.6;margin:.8rem 0;overflow-wrap:anywhere}
                .mj-jobs-pages{display:flex;gap:1rem;margin-top:1.2rem}
                .mj-jobs-pages a{text-decoration:underline}
            </style>
            <?php if ($attributes['title'] !== '') : ?>
                <h2><?php echo esc_html($attributes['title']); ?></h2>
            <?php endif; ?>
            <?php if ($attributes['show_search'] !== 'no') : ?>
                <form method="get" action="" class="mj-jobs-search" role="search">
                    <?php
                    // Keep other plain GET filters when a page contains this shortcode.
                    foreach ($_GET as $key => $value) {
                        if (!is_scalar($value) || in_array($key, ['mj_job_q', 'mj_job_page'], true)) { continue; }
                        echo '<input type="hidden" name="'.esc_attr(sanitize_key($key)).'" value="'.esc_attr(sanitize_text_field(wp_unslash($value))).'">';
                    }
                    ?>
                    <label for="<?php echo esc_attr($uid.'-q'); ?>" class="screen-reader-text"><?php esc_html_e('Rechercher un emploi', 'manajeni-connector'); ?></label>
                    <input id="<?php echo esc_attr($uid.'-q'); ?>" name="mj_job_q" type="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Rechercher un poste...', 'manajeni-connector'); ?>">
                    <button type="submit"><?php esc_html_e('Rechercher', 'manajeni-connector'); ?></button>
                </form>
            <?php endif; ?>
            <?php if (!$offers) : ?>
                <p><?php esc_html_e('Aucune offre disponible actuellement.', 'manajeni-connector'); ?></p>
            <?php else : ?>
                <div class="mj-jobs-grid">
                    <?php foreach ($offers as $offer) :
                        if (!is_array($offer) || empty($offer['url']) || empty($offer['title'])) { continue; }
                        $offerUrl = esc_url_raw((string) $offer['url']);
                        if (wp_parse_url($offerUrl, PHP_URL_HOST) !== 'manajeni.com' || wp_parse_url($offerUrl, PHP_URL_SCHEME) !== 'https') { continue; }
                        $description = trim((string) ($offer['description'] ?? ''));
                        ?>
                        <article class="mj-job">
                            <?php
                            $image = (string) ($offer['image_url'] ?? '');
                            if ($image && wp_parse_url($image, PHP_URL_HOST) === 'manajeni.com' && wp_parse_url($image, PHP_URL_SCHEME) === 'https') : ?>
                                <img loading="lazy" src="<?php echo esc_url($image); ?>" alt="">
                            <?php endif; ?>
                            <div class="mj-job-body">
                                <h3><?php echo esc_html($offer['title']); ?></h3>
                                <?php if (!empty($offer['location'])) : ?><p class="mj-job-meta"><?php echo esc_html($offer['location']); ?></p><?php endif; ?>
                                <p class="mj-job-meta"><?php echo esc_html(implode(' · ', array_filter([
                                    $offer['type'] ?? '', $offer['contract_type'] ?? '', $offer['work_mode'] ?? '',
                                ]))); ?></p>
                                <?php if ($description !== '') : ?><p class="mj-job-desc"><?php echo esc_html(wp_trim_words($description, 32, '…')); ?></p><?php endif; ?>
                                <a class="mj-jobs-apply" href="<?php echo esc_url($offerUrl); ?>" rel="noopener"><?php esc_html_e('Voir l’offre et postuler', 'manajeni-connector'); ?></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($lastPage > 1) : ?>
                <nav class="mj-jobs-pages" aria-label="<?php esc_attr_e('Pagination emplois', 'manajeni-connector'); ?>">
                    <?php if ($page > 1) : ?><a href="<?php echo esc_url(add_query_arg(['mj_job_page' => $page - 1, 'mj_job_q' => $search])); ?>"><?php esc_html_e('Précédent', 'manajeni-connector'); ?></a><?php endif; ?>
                    <span><?php echo esc_html(min($page, $lastPage).' / '.$lastPage); ?></span>
                    <?php if ($page < $lastPage) : ?><a href="<?php echo esc_url(add_query_arg(['mj_job_page' => $page + 1, 'mj_job_q' => $search])); ?>"><?php esc_html_e('Suivant', 'manajeni-connector'); ?></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
