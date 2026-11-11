# Manajeni Connector

Plugin WordPress d'integration entre WordPress et l'ecosysteme Manajeni.

## Objectif

Cette base sert aujourd'hui a fournir :

- un point d'entree WordPress unique via `manajeni-connector.php`
- une activation du plugin avec creation de table, repertoire XML et options par defaut
- une interface d'administration Manajeni dans WordPress
- une gestion de session admin basee sur `manage_options`, URL API declaree et API key stockee cote WordPress
- un client API HTTP WordPress pour communiquer avec Manajeni
- un client simule reserve au developpement via `MANAJENI_CONNECTOR_DEV_MODE`

La logique metier existante est conservee en l'etat dans cette phase de stabilisation.

## Structure

```text
manajeni-connector/
|- manajeni-connector.php
|- includes/
|  |- class-manajeni-activator.php
|  |- class-manajeni-apps-handler.php
|  |- class-manajeni-db.php
|  |- class-manajeni-session.php
|  |- class-manajeni-xml-handler.php
|  `- controllers/
|- services/
|  `- class-manajeni-fake-api-client.php
`- admin/
   |- assets/css/
   `- views/
```

## Composants

- `manajeni-connector.php` : bootstrap du plugin, constantes, hooks d'activation/desactivation, autoload, menu admin et chargement des pages.
- `includes/class-manajeni-activator.php` : prepare la table WordPress, le dossier XML et les options par defaut.
- `includes/class-manajeni-session.php` : controle l'acces admin, la session et les redirections de parcours.
- `includes/class-manajeni-db.php` : persistance locale chiffree de la cle API et de l'etat de connexion.
- `includes/class-manajeni-xml-handler.php` : lecture et mise a jour du fichier XML de configuration.
- `includes/controllers/` : rendu des modules applicatifs par domaine.
- `services/class-manajeni-api-client.php` : client API reel base sur `wp_remote_get()` et `wp_remote_post()`.
- `services/class-manajeni-fake-api-client.php` : donnees factices reservees au mode developpement.
- `admin/views/` : vues d'administration.
- `admin/assets/css/` : styles de l'interface admin.

## Installation locale

1. Copier le dossier `manajeni-connector` dans `wp-content/plugins/`.
2. Verifier que le fichier principal est `manajeni-connector.php` a la racine du plugin.
3. Activer le plugin depuis l'administration WordPress.
4. A l'activation, le plugin cree :
   - la table `{$wpdb->prefix}manajeni_connection`
   - un dossier `uploads/manajeni/`
   - un fichier `uploads/manajeni/config.xml`
   - les options WordPress necessaires
5. Se connecter ensuite au parcours admin Manajeni pour renseigner :
   - l'URL API Manajeni
   - la cle API Manajeni
   - l'URL du site est ajoutee automatiquement dans le header `X-Manajeni-Origin`

## Mode developpement

Le mode simulation n'est plus pilote par une option admin.

Pour conserver le faux client en developpement uniquement, definir :

```php
define('MANAJENI_CONNECTOR_DEV_MODE', true);
```

Sans cette constante, le plugin utilise exclusivement l'API Manajeni reelle.

## Workflow Git recommande

1. Creer une branche de travail courte depuis la branche de reference.
2. Limiter chaque commit a un objectif unique : header, doc, correctif technique, test.
3. Ne pas melanger stabilisation technique et evolutions metier dans le meme commit.
4. Verifier au minimum la syntaxe PHP avant commit.
5. Ouvrir une revue avant fusion si la modification touche `manajeni-connector.php` ou `includes/`.

Exemple :

```bash
git checkout -b chore/stabilize-plugin-base
php -l manajeni-connector.php
git add manajeni-connector.php README.md
git commit -m "chore: stabilize plugin header and documentation"
```

## Etat actuel

Etat observe au moment de cette documentation :

- le plugin expose un menu admin Manajeni avec plusieurs sous-modules
- le bootstrap principal repose sur des fonctions anonymes et un autoload simple
- la connexion principale passe par `API URL + API Key`, sans email ni mot de passe Manajeni
- la cle API est stockee de maniere chiffree et affichee de facon masquee dans l'admin
- le plugin cree et utilise un stockage XML dans `uploads/manajeni/`
- les modules `clients`, `catalogue`, `orders/devis` et `appointments/rendez-vous` sont branches sur le client API reel
- les autres menus restent presents et chargeables, mais dependent encore d'endpoints Manajeni a formaliser si ces modules doivent devenir pleinement operationnels

## Stabilisation appliquee

- header WordPress corrige pour rester lisible par WordPress
- `Description` remplacee par un texte simple, sans PHP
- version du header alignee sur `MANAJENI_CONNECTOR_VERSION`
- suppression du flux `email/password` au profit d'une connexion uniquement par API key
- introduction d'un client API WordPress natif avec headers `Authorization: Bearer` et `X-Manajeni-Origin`
- conservation du faux client uniquement en mode developpement

## Verification minimale

Pour verifier que le plugin reste activable :

```bash
php -l manajeni-connector.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

L'activation fonctionnelle doit ensuite etre verifiee dans WordPress avec un environnement local complet.

## Securite

- ne jamais commiter de secret, cle API reelle ou URL sensible dans le depot
- conserver les controles `ABSPATH` dans les fichiers PHP charges directement
- limiter l'acces aux pages admin avec `manage_options`
- preferer les fonctions WordPress d'echappement et de redirection securisee pour les evolutions futures


## Offres d'emploi WordPress (nouveau)

Après déploiement **des deux dépôts**, insérer dans une page WordPress :

\`\`\`text
[manajeni_jobs company_id="8" limit="9" show_search="yes" title="Rejoignez notre équipe"]
\`\`\`

Remplacer \`8\` par l'identifiant **réel** de l'entreprise connectée à Manajeni (pas celui d'une autre entreprise). Alias : \`[manajeni_emplois ...]\`. Le shortcode affiche uniquement les offres ouvertes et publiées de cette entreprise, avec recherche, images et pagination. Aucune clé API n'est rendue côté visiteur. Les offres se chargent depuis le flux public HTTPS \`/widgets/recruitment/companies/{company_id}/offers\`.

**Parcours candidat actuellement fourni :** le bouton « Voir l'offre et postuler » ouvre la fiche officielle sécurisée sur Manajeni. Le formulaire de candidature, le dépôt du CV, les invitations aux questionnaires et les entretiens IA restent gérés par Manajeni et son contrôle des sessions. **Cette première version ne réalise pas encore la candidature ou l'entretien IA entièrement dans WordPress.** Ne pas utiliser d'iframe tiers pour les entretiens : sessions, PIN et protection de données nécessitent un parcours dédié.

La fonctionnalité exige que l'entreprise soit active et que l'application Recrutement soit accessible dans Manajeni. Le plugin ne publie rien, ne change aucune offre et n'enregistre aucun CV sur WordPress. Il faut déployer le backend Laravel avant d'activer le shortcode sur WordPress. Aucun déploiement de production n'est effectué par ces commits.

## Fiche d'offre locale WordPress (phase 1, sans modification Laravel)

Le shortcode existant reste inchangé :

    [manajeni_jobs company_id="8" limit="9"]

Les boutons « Voir l’offre et postuler » ouvrent désormais la fiche détaillée **sur la même page WordPress** avec le paramètre `mj_offer` et affichent les champs publics du flux Manajeni (description, exigences, image, localisation, contrat, rémunération si publique). Aucun nouveau permalien ni configuration de réécriture n'est requis. Le shortcode alternatif `[manajeni_job_detail company_id="8"]` permet aussi de placer une fiche sur une page distincte avec le même paramètre `mj_offer`.

**Limite de cette phase :** le bouton final « Postuler en toute sécurité » continue vers le formulaire officiel Manajeni. Le flux public existant est en lecture seule : il ne permet pas d'envoyer les CV, les réponses aux questionnaires, ni les échanges d'entretien IA. Les sessions et invitations sont actuellement gérées par Laravel. Ne jamais contourner ces contrôles par iframe, partage de cookie, stockage de PIN ou token en WordPress. Une expérience candidat entièrement locale exige des endpoints candidat sécurisés déjà disponibles ou une extension explicite de l'API Laravel, qui est hors périmètre de cette PR.

Tests à effectuer avant fusion : `php -l manajeni-connector.php`, `php -l includes/class-manajeni-recruitment-shortcode.php`, `php -l includes/class-manajeni-recruitment-detail.php`; vérifier sur WordPress liste → détail → retour → candidature externe, lien invalide, offre expirée, société sans accès, recherche et pagination. Aucune exécution de ces tests n'est revendiquée par la PR.
