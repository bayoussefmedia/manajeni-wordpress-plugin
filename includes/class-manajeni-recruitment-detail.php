<?php
/**
 * Read-only offer details rendered on the company's own WordPress page.
 *
 * Important: this does not proxy CV uploads, Laravel sessions, private tests
 * or AI interviews. Those still require supported Manajeni candidate APIs.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class Manajeni_Recruitment_Detail
{
    private const FEED_BASE = 'https://manajeni.com/widgets/recruitment/companies/';

    public static function render(int $companyId, string $slug, int $sourcePage, int $pageSize, string $search): string
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $slug)) {
            return self::message(__('Offre introuvable.', 'manajeni-connector'));
        }

        $offer = self::findPublicOffer($companyId, $slug, $sourcePage, $pageSize, $search);
        if (!is_array($offer)) {
            return self::message(__('Cette offre n’est plus disponible. Veuillez consulter les offres ouvertes.', 'manajeni-connector'));
        }

        $originalUrl = esc_url_raw((string) ($offer['url'] ?? ''));
        if (!self::isPublicOfferUrl($originalUrl, $slug)) {
            return self::message(__('Lien de candidature indisponible.', 'manajeni-connector'));
        }

        $image = (string) ($offer['image_url'] ?? '');
        if (!self::isOfficialImageUrl($image)) {
            $image = '';
        }

        $typeLabels = [
            'emploi' => __('Emploi', 'manajeni-connector'),
            'stage' => __('Stage', 'manajeni-connector'),
            'alternance' => __('Alternance', 'manajeni-connector'),
            'freelance' => __('Freelance', 'manajeni-connector'),
        ];
        $modeLabels = [
            'sur_site' => __('Sur site', 'manajeni-connector'),
            'hybride' => __('Hybride', 'manajeni-connector'),
            'distance' => __('À distance', 'manajeni-connector'),
        ];

        $meta = array_filter([
            $typeLabels[$offer['type'] ?? ''] ?? '',
            (string) ($offer['location'] ?? ''),
            $modeLabels[$offer['work_mode'] ?? ''] ?? '',
            (string) ($offer['contract_type'] ?? ''),
        ], static fn($value) => trim($value) !== '');

        $description = (string) ($offer['description'] ?? '');
        $requirements = (string) ($offer['requirements'] ?? '');
        $companyName = (string) ($offer['company'] ?? '');
        $deadline = (string) ($offer['deadline'] ?? '');
        $compensation = is_array($offer['compensation'] ?? null) ? $offer['compensation'] : null;
        $backUrl = get_permalink();

        ob_start();
        ?>
        <section class="mj-offer-detail" aria-label="<?php echo esc_attr__('Détail de l’offre', 'manajeni-connector'); ?>">
            <style>
                .mj-offer-detail{max-width:960px;margin:1.5rem auto;color:inherit;font:inherit}
                .mj-offer-detail *{box-sizing:border-box}
                .mj-offer-detail a:focus-visible{outline:3px solid #1859dc;outline-offset:3px}
                .mj-offer-back{display:inline-block;margin-bottom:1.2rem;text-decoration:none}
                .mj-offer-panel{border:1px solid #dce4f1;border-radius:14px;padding:clamp(18px,3vw,32px);background:#fff;color:#18283f}
                .mj-offer-panel h2{font-size:clamp(1.4rem,2.5vw,2rem);margin:0 0 .45rem;line-height:1.3;color:#122644}
                .mj-offer-panel h3{font-size:1.12rem;margin:1.6rem 0 .65rem;color:#122644}
                .mj-offer-company{color:#475569;margin:0 0 .9rem}
                .mj-offer-meta{display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0}
                .mj-offer-meta span{display:inline-block;background:#edf4ff;color:#234975;border-radius:999px;padding:.4rem .75rem;font-size:.89rem}
                .mj-offer-cover{display:block;width:100%;height:auto;aspect-ratio:1200/630;object-fit:cover;border-radius:10px;margin:1.2rem 0}
                .mj-offer-copy{white-space:pre-line;overflow-wrap:anywhere;line-height:1.75}
                .mj-offer-info{color:#475569;font-size:.95rem;margin:1rem 0}
                .mj-offer-apply{display:inline-block;background:#1764e9!important;color:#fff!important;border-radius:8px;padding:.8rem 1.25rem;text-decoration:none!important;margin-top:1.2rem}
                .mj-offer-note{font-size:.86rem;color:#64748b;margin:.65rem 0 0}
                @media(max-width:480px){.mj-offer-panel{padding:16px}}
            </style>
            <a class="mj-offer-back" href="<?php echo esc_url($backUrl); ?>">
                <?php esc_html_e('← Retour aux offres', 'manajeni-connector'); ?>
            </a>
            <article class="mj-offer-panel">
                <h2><?php echo esc_html((string) $offer['title']); ?></h2>
                <?php if ($companyName !== '') : ?>
                    <p class="mj-offer-company"><?php echo esc_html($companyName); ?></p>
                <?php endif; ?>
                <?php if ($meta) : ?>
                    <div class="mj-offer-meta">
                        <?php foreach ($meta as $item) : ?>
                            <span><?php echo esc_html($item); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($image !== '') : ?>
                    <img class="mj-offer-cover" loading="lazy" src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr((string) $offer['title']); ?>">
                <?php endif; ?>
                <?php if ($description !== '') : ?>
                    <h3><?php esc_html_e('Description de l’offre', 'manajeni-connector'); ?></h3>
                    <div class="mj-offer-copy"><?php echo esc_html($description); ?></div>
                <?php endif; ?>
                <?php if ($requirements !== '') : ?>
                    <h3><?php esc_html_e('Profil recherché', 'manajeni-connector'); ?></h3>
                    <div class="mj-offer-copy"><?php echo esc_html($requirements); ?></div>
                <?php endif; ?>
                <?php if ($deadline !== '') : ?>
                    <p class="mj-offer-info"><?php echo esc_html(sprintf(__('Date limite : %s', 'manajeni-connector'), $deadline)); ?></p>
                <?php endif; ?>
                <?php if ($compensation !== null) : ?>
                    <?php
                    $parts = array_filter([
                        isset($compensation['min']) && $compensation['min'] !== null ? (string) $compensation['min'] : '',
                        isset($compensation['max']) && $compensation['max'] !== null ? (string) $compensation['max'] : '',
                    ], static fn($value) => $value !== '');
                    $compensationText = implode(' – ', $parts);
                    if ($compensationText !== '') {
                        $compensationText .= ' MAD';
                    }
                    if (!empty($compensation['period'])) {
                        $compensationText .= ' / '.(string) $compensation['period'];
                    }
                    if (!empty($compensation['details'])) {
                        $compensationText .= ' '.(string) $compensation['details'];
                    }
                    ?>
                    <?php if (trim($compensationText) !== '') : ?>
                        <p class="mj-offer-info"><?php echo esc_html(sprintf(__('Rémunération : %s', 'manajeni-connector'), trim($compensationText))); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <a class="mj-offer-apply" href="<?php echo esc_url($originalUrl); ?>" rel="noopener">
                    <?php esc_html_e('Postuler en toute sécurité', 'manajeni-connector'); ?>
                </a>
                <p class="mj-offer-note"><?php esc_html_e('La candidature et le dépôt du CV sont actuellement sécurisés par Manajeni. Le formulaire ouvrira son site officiel.', 'manajeni-connector'); ?></p>
            </article>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function findPublicOffer(int $companyId, string $slug, int $sourcePage, int $pageSize, string $search): ?array
    {
        // First request exactly the page the visitor saw, to minimize API calls.
        $first = self::loadFeed($companyId, $sourcePage, $pageSize, $search);
        $found = self::matchOffer($first, $slug);
        if ($found !== null) {
            return $found;
        }

        // Deep links/bookmarks: bounded fallback over current public listings.
        // Do not serve a cached or unverified offer that may have been closed.
        for ($page = 1; $page <= 8; $page++) {
            if ($sourcePage === $page && $pageSize === 24 && $search === '') {
                continue;
            }
            $payload = self::loadFeed($companyId, $page, 24, '');
            $found = self::matchOffer($payload, $slug);
            if ($found !== null) {
                return $found;
            }
            if (!is_array($payload) || $page >= max(1, (int) ($payload['meta']['last_page'] ?? 1))) {
                break;
            }
        }

        return null;
    }

    private static function loadFeed(int $companyId, int $page, int $perPage, string $search): ?array
    {
        if ($companyId < 1 || $page < 1 || $perPage < 1) {
            return null;
        }

        $url = add_query_arg([
            'page' => $page,
            'per_page' => min(24, $perPage),
            'q' => $search,
        ], self::FEED_BASE.$companyId.'/offers');

        $response = wp_safe_remote_get($url, [
            'timeout' => 10,
            'redirection' => 0,
            'limit_response_size' => 512000,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($payload) && isset($payload['data']) && is_array($payload['data'])
            ? $payload : null;
    }

    private static function matchOffer(?array $payload, string $slug): ?array
    {
        foreach (($payload['data'] ?? []) as $offer) {
            if (is_array($offer) && hash_equals($slug, (string) ($offer['slug'] ?? ''))) {
                return $offer;
            }
        }
        return null;
    }

    private static function isPublicOfferUrl(string $url, string $slug): bool
    {
        return wp_parse_url($url, PHP_URL_SCHEME) === 'https'
            && wp_parse_url($url, PHP_URL_HOST) === 'manajeni.com'
            && wp_parse_url($url, PHP_URL_PATH) === '/emplois/'.$slug;
    }

    private static function isOfficialImageUrl(string $url): bool
    {
        return $url !== ''
            && wp_parse_url($url, PHP_URL_SCHEME) === 'https'
            && wp_parse_url($url, PHP_URL_HOST) === 'manajeni.com';
    }

    private static function message(string $message): string
    {
        return '<p class="mj-jobs-error" role="status">'.esc_html($message).'</p>';
    }
}
