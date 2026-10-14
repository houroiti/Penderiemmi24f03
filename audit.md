# Audit qualité — Penderie

Date : 15 octobre 2026
Branche auditée : `feature/code-quality`
Auteur : Mohamed Houroiti

##  Contexte et périmètre

Cet audit est un audit de départ. Il est réalisé avant l'écriture du code métier : le dépôt contient le socle technique (Docker, Symfony 7.4, Doctrine, Security, Validator) et la maquette Figma des écrans principaux, mais aucune entité ni aucun contrôleur.

Il a trois objectifs :

1. mettre en place les outils de qualité et mesurer un point de référence ;
2. auditer la maquette pour l'accessibilité, l'écoconception et l'UX, puisque le front n'est pas encore codé ;
3. fixer les règles que le code devra respecter, et la liste de ce qui devra être testé.

 **Retard assumé.** La phase de construction a commencé sans code métier. Ce retard est documenté ici plutôt que masqué ; le plan d'action (section 9) indique l'ordre de rattrapage.

### Stack retenue

| Couche | Choix |
| --- | --- |
| Back / API | Symfony 7.4 LTS, PHP 8.4 |
| Base de données | PostgreSQL 17 |
| Front | Next.js (React), application découplée qui consomme l'API |
| Environnement | Docker (PHP-FPM, Nginx, PostgreSQL) |
| Qualité | PHPStan niveau 6, linters Symfony, Composer audit, Snyk (à brancher) |

Le choix de Next.js est justifié dans le journal de décisions : le rendu serveur des pages publiques permet l'aperçu des liens partagés sur Instagram, besoin exprimé par le client. Toute la logique métier reste dans Symfony.

## 2. Outils mis en place

Toutes les commandes passent par le `Makefile` à la racine. `make quality` lance l'ensemble des contrôles, et s'arrête au premier échec.

| Outil | Commande | Ce qu'il contrôle |
| --- | --- | --- |
| Composer validate | `make validate` | Validité de `composer.json` et cohérence avec `composer.lock` |
| Linter du conteneur | `make lint-container` | Que chaque service reçoit des arguments du bon type |
| Linter YAML | `make lint-yaml` | Syntaxe des fichiers de configuration |
| Linter Twig | `make lint-twig` | Syntaxe des templates |
| PHPStan niveau 6 | `make phpstan` | Erreurs de types, appels invalides, typage manquant, sans exécuter le code |
| Composer audit | `make audit` | Failles de sécurité connues dans les dépendances |

Configuration de PHPStan (`app/phpstan.dist.neon`) :
- niveau 6, qui impose le typage de tous les paramètres et retours, y compris le contenu des tableaux ;
- extensions `phpstan-symfony` et `phpstan-doctrine`, pour que PHPStan comprenne le conteneur de services et les entités ;
- analyse limitée à `src/` : seul le code écrit par l'équipe est analysé, les fichiers générés par Symfony (`config/`, `bin/`, `public/`) sont exclus. `tests/` sera ajouté dès la création des premiers tests.

## 3. Qualité technique Symfony et PHP — résultats

| Contrôle | Résultat | Détail |
| --- | -- | --- |
| `composer validate --strict` |  Valide | Après correction (voir constat 3.1) |
| `lint:container` | OK | Tous les services correctement injectés |
| `lint:yaml` |  OK | 17 fichiers YAML valides |
| `lint:twig` |  OK | 1 template (`base.html.twig`, installé avec le profiler) |
| PHPStan niveau 6 |  0 erreur | 1 fichier analysé (`src/Kernel.php`) |
| `composer audit` |  Aucune vulnérabilité | Dépendances à jour au 15/10/2026 |

### Constat 3.1 — `composer validate --strict` en échec

- Constat :la validation stricte échouait : propriétés `name` et `description` absentes de `composer.json`.
- Analyse : ces métadonnées sont exigées pour publier un package sur Packagist. Elles n'ont aucun impact sur le fonctionnement de l'application, mais l'option `--strict` les signale comme erreurs.
- Action : ajout de `name` (`houroiti/penderie`) et de `description`. L'ajout du nom a désynchronisé l'empreinte (`content-hash`) du `composer.lock`, resynchronisée avec `composer update --lock`, **sans modifier aucune version** de dépendance.
- Résultat : validation OK.

### Constat 3.2 — Twig présent sans avoir été choisi

- Constat : Twig est installé alors que le front sera en Next.js.
- Analyse : il est arrivé comme dépendance du Web Profiler (barre de debug), installé avec `symfony/debug-pack` en développement uniquement.
- Décision : conservé pour le profiler. Il n'est pas utilisé pour l'interface.

### Limite

Ces résultats valent pour un projet sans code métier. Ils servent de point de référence : chaque fonctionnalité ajoutée devra conserver `make quality` au vert.

## 4. Accessibilité (A11Y) — audit de la maquette

Les contrastes ont été calculés selon les règles WCAG 2.1, niveau AA : 4,5:1 minimum pour le texte**, 3:1 pour les composants d'interface (bordures de champs, icônes porteuses de sens).

| Élément | Couleurs | Contraste | Statut                |
| --- | --- | --- |-----------------------|
| Texte principal sur fond ivoire | `#1F1416` / `#F7F3EE` | 16,25:1 | valide                |
| Texte secondaire sur fond ivoire | `#6D5F61` / `#F7F3EE` | 5,49:1 | valide                |
| Texte secondaire sur carte blanche | `#6D5F61` / `#FFFFFF` | 6,07:1 | valide                |
| Texte blanc sur bouton bordeaux | `#FFFFFF` / `#8B0F3B` | 9,42:1 | valide                |
| Bordeaux sur fond ivoire (liens, titres) | `#8B0F3B` / `#F7F3EE` | 8,53:1 | valide                |
| Badge « Objet de valeur » | `#8B0F3B` / `#F6E7EC` | 7,88:1 | valide                |
| Badge « Favori » (cognac) | `#7A4E28` / `#F3E8DC` | 5,90:1 | valide                |
| Badge « En retard » (ambre) | `#9A5800` / `#FBEFDC` | 4,90:1 | valide                |
| **Badge « Prêté » (vert)** | `#2E7D4F` / `#E5F2EA` | **4,38:1** | non valide sous 4,5:1 |
| **Bordure des champs de formulaire** | `#E9E1D6` / `#F7F3EE` | **1,17:1** | non valide sous 3:1         |

### Constats

1. Badge « Prêté » insuffisant. À 11,5 px, ce texte doit atteindre 4,5:1. → Assombrir le vert du texte, puis recalculer.
2. Champs de formulaire peu visibles.La bordure se distingue mal du fond, ce qui gêne les personnes malvoyantes pour repérer où saisir. → Assombrir la bordure (objectif 3:1) ou renforcer le fond du champ.
3. La couleur ne porte jamais seule l'information.** Les états « Prêté », « En retard » et « Objet de valeur » ont toujours un **texte**, et l'objet de valeur une icône de cadenas. ✅
4. Labels visibles. Chaque champ du formulaire de scan a un label au-dessus, pas seulement un texte d'exemple qui disparaît à la saisie. ✅
5. Hiérarchie des titres. Un seul titre principal par écran (« Où est mon pull bleu ? », « Ajouter un objet »…). À traduire en un seul `<h1>` par page dans le code.

### Points à vérifier dans le code (non vérifiables sur maquette)

- navigation complète au **clavier** et **focus visible** sur chaque élément interactif ;
- **texte alternatif** des photos d'objets (le nom de l'objet par défaut) ;
- **messages d'erreur** des formulaires reliés à leur champ (`aria-describedby`) ;
- zones cliquables d'au moins **44 × 44 px** sur mobile (barre d'onglets, filtres).

## 5. Écoconception

| Point | Choix de conception | Statut |
| --- | --- | --- |
| Poids des photos | Le POC de reconnaissance impose déjà une conversion en JPEG et un redimensionnement avant envoi. La même règle s'appliquera au stockage. | ✅ Prévu |
| Formats d'image | Servir des miniatures pour les listes, la photo complète uniquement sur la fiche objet. | À implémenter |
| Pagination | La liste du dressing devra être paginée : un utilisateur peut posséder des centaines d'objets. | À implémenter |
| Polices | Deux familles (Bodoni Moda, Instrument Sans), avec uniquement les graisses utilisées. | ✅ Limité |
| CSS | CSS natif avec CSS Modules, sans framework CSS : seul le CSS utilisé est chargé. | ✅ Prévu |
| Appels à l'IA | Une seule requête par scan, déclenchée par l'utilisateur, jamais automatique. | ✅ Prévu |
| Rendu serveur | Limité aux pages publiques partagées ; le reste est rendu côté navigateur. | ✅ Prévu |

À mesurer avec Lighthouse dès que les premiers écrans seront codés : poids de la page, nombre de requêtes, temps d'affichage.

## 6. Expérience utilisateur (UX) — audit de la maquette

| Critère | Constat | Statut |
| --- | --- |  |
| Navigation | Barre d'onglets fixe à 3 entrées (Dressing, Scanner, Prêts), bouton de scan central mis en avant. | valide      |
| Cohérence visuelle | Composants partagés (Bouton, Badge, Carte objet, Champ) et couleurs en variables dans Figma. | valide       |
| Retour utilisateur | Étapes du scan affichées (photo préparée, objet reconnu) ; champs remplis par l'IA mis en évidence. | valide        |
| Prévention des erreurs | Saisie manuelle toujours disponible si le scan échoue (« test maman »). | valide        |
| Compréhension | La fiche pré-remplie dit explicitement « Rempli par la reconnaissance, à vérifier ». | valide        |
| Objet de valeur | La case précise son effet : « Visible par toi seul, même dans une pièce partagée ». | valide        |
| Écran Profil | Non maquetté. |  À faire |
| États vides | Écran d'un dressing sans objet non maquetté (premier lancement). |  À faire |
| Erreurs réseau | Comportement si l'API ou le scan ne répond pas non maquetté. |  À faire |

## 7. Inspection manuelle — règles pour le code à venir

Aucun code métier n'existant encore, cette section fixe les règles qui serviront de grille de relecture à chaque merge request.

1. Contrôleurs fins. Un contrôleur reçoit la requête, appelle un service, renvoie la réponse. Pas de logique métier dans les contrôleurs.
2. Logique métier dans des services, testables sans HTTP (exemple : la règle « les amis d'abord » pour la vente).
3. Autorisations dans des Voters Symfony**, jamais en `if` dispersés dans les contrôleurs.
4. Validation côté serveur systématique** avec les contraintes du Validator, même si le front valide aussi.
5. Sérialisation explicite : chaque réponse de l'API choisit les champs exposés (groupes de sérialisation), pour ne jamais exposer un champ interne.
6. Pas de duplication : un appel à l'API centralisé côté Next.js, pas de `fetch` répétés dans chaque composant.
7. Typage complet, imposé par PHPStan niveau 6.
8. Commits conventionnels, une merge request par fonctionnalité.

## 8. Testabilité et fiabilité (séances suivantes)

Fonctionnalités critiques, à couvrir en priorité :

| Fonctionnalité | Risque si elle casse | Tests à prévoir |
| --- | --- | --- |
| Accès aux objets d'un autre utilisateur | Fuite de données privées | Test fonctionnel : changer l'identifiant dans l'URL doit renvoyer 403 ou 404 |
| **Objet de valeur jamais partagé** | Exposition d'un objet que le client veut garder privé | Test : une pièce partagée ne doit pas lister ses objets de valeur |
| Liens publics | Accès non voulu | Tests : lien révoqué ou expiré refusé ; aucun objet privé exposé |
| Droits du sous-compte enfant | Vente ou partage interdit | Tests : création d'annonce et de lien refusées pour `ROLE_CHILD` |
| Prêts | Statut faux, rappels erronés | Tests unitaires : statut déduit des dates (en cours, rendu, en retard) |
| Vente « les amis d'abord » | Visibilité trop large | Test unitaire : délai de 24, 48 ou 72 h respecté |
| Scan par photo | Dépendance externe (API d'IA) | Simuler l'API en test ; vérifier le repli sur la saisie manuelle |

Données de test : fixtures Doctrine alimentées par Faker, avec une graine fixe pour des tests reproductibles, et un volume de l'ordre du millier d'objets pour tester la pagination.

## 9. Plan d'action

| Priorité | Action | Échéance |
| --- | --- | --- |
| 1 | Créer les entités Doctrine à partir du modèle de données, `make quality` au vert | Séance suivante |
| 1 | Brancher `make quality` dans une GitHub Action (push et merge request) | Séance 17 |
| 1 | Connecter Snyk au dépôt | Séance 17 |
| 2 | Corriger le contraste du badge « Prêté » et la bordure des champs dans Figma | Avant le codage du front |
| 2 | Écrire les fixtures (Faker, graine fixe, ~1 000 objets) | Avant le pitch 2 |
| 2 | Écrire les tests des fonctionnalités critiques (section 8) | Séances 17-18 |
| 3 | Maquetter le Profil, les états vides et les erreurs réseau | Avant le codage du front |
| 3 | Mesurer avec Lighthouse les premiers écrans codés | Dès le premier écran |

## 10. Comment reproduire cet audit

```bash
make up        # démarre les conteneurs
make quality   # lance tous les contrôles
```