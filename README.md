# Manajeni Connector

Plugin WordPress d'integration entre WordPress et l'ecosysteme Manajeni.

## Objectif

Cette base sert aujourd'hui a fournir :

- un point d'entree WordPress unique via `manajeni-connector.php`
- une activation du plugin avec creation de table, repertoire XML et options par defaut
- une interface d'administration Manajeni dans WordPress
- une gestion de session admin pour proteger les sous-pages
- un client API simule pour stabiliser les ecrans sans dependre d'une API externe

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
- `includes/class-manajeni-db.php` : persistance de la connexion Manajeni en base WordPress.
- `includes/class-manajeni-xml-handler.php` : lecture et mise a jour du fichier XML de configuration.
- `includes/controllers/` : rendu des modules applicatifs par domaine.
- `services/class-manajeni-fake-api-client.php` : donnees factices persistantes pour les modules metier et les tests manuels.
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
5. Se connecter ensuite au parcours admin Manajeni pour la premiere configuration.

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
- une partie de la couche applicative fonctionne sur des donnees simulees via `Manajeni_Fake_API_Client`
- le plugin cree et utilise un stockage XML dans `uploads/manajeni/`
- la session applicative est stockee en options WordPress
- le plugin n'est pas encore structure comme une integration API de production complete

## Stabilisation appliquee

- header WordPress corrige pour rester lisible par WordPress
- `Description` remplacee par un texte simple, sans PHP
- version du header alignee sur `MANAJENI_CONNECTOR_VERSION`
- aucune modification volontaire de la logique metier

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
