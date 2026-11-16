# Couverture des tests — Penderie

Date : 16 novembre 2026
Branche : `develop`
Auteur : Mohamed Houroiti

Ce document dit ce qui est testé, ce qui ne l'est pas, et pourquoi. Il complète la section 8 de `audit.md` (« Testabilité et fiabilité »).

## 1. Comment lancer les tests

```bash
make up        # démarre les conteneurs
make test-db   # (re)crée la base penderie_test et joue les migrations
make test      # lance PHPUnit
```

- Les tests tournent sur une base séparée, `penderie_test` : la base de développement n'est jamais touchée.
- `dama/doctrine-test-bundle` ouvre une transaction avant chaque test et l'annule après : chaque test part d'une base vide et crée ses propres données.
- **Intégration continue** : la même campagne tourne sur GitHub Actions à chaque push et à chaque pull request (`.github/workflows/ci.yml`), avec PHPStan niveau 6, les linters et `composer audit`. Une pull request rouge n'est pas mergée.

## 2. Les trois niveaux de tests

| Niveau | Classe de base | Base de données | Rôle | Tests |
| --- | --- | --- | --- | --- |
| Unitaire | `TestCase` | non | Vérifier une règle métier isolée, très vite | 29 |
| Intégration | `KernelTestCase` | oui | Vérifier que le code fonctionne avec Symfony et PostgreSQL | 5 |
| Fonctionnel | `WebTestCase` | oui | Vérifier une requête HTTP de bout en bout (API et back-offices) | 47 |
| De bout en bout (E2E) | Cypress | oui | Vérifier un parcours dans le navigateur — **plus tard**, voir section 6 | — |

**Résultat actuel : 81 tests, 211 assertions, tous au vert.**

## 3. Ce qui est testé

### Règles métier (unitaires)

| Fichier | Tests | Ce qui est vérifié |
| --- | --- | --- |
| `tests/Unit/Security/Voter/ItemVoterTest.php` | 18 | Qui peut voir ou modifier un objet : le propriétaire peut tout ; un inconnu et un anonyme rien ; un ami proche voit sans modifier ; un abonné ne voit que ce qui est ouvert aux abonnés ; le sous-compte enfant voit les objets de son parent sans les modifier ; **un objet personnel n'est jamais visible par un autre, même ouvert aux abonnés**. |
| `tests/Unit/Security/Voter/OwnedVoterTest.php` | 5 | Seul le propriétaire gère son logement, ses pièces, ses meubles, ses objets et ses prêts ; un utilisateur connecté peut ouvrir un formulaire de création. |
| `tests/Unit/Entity/LoanTest.php` | 6 | Le statut d'un prêt (en cours, en retard, rendu) est **déduit des dates**, jamais stocké ; un prêt dû aujourd'hui n'est pas encore en retard. |

### Base de données (intégration)

| Fichier | Tests | Ce qui est vérifié |
| --- | --- | --- |
| `tests/Integration/Repository/ItemRepositoryTest.php` | 1 | Un objet enregistré est relu depuis PostgreSQL avec ses valeurs (après `clear()`, pour ne pas lire la mémoire de Doctrine). |
| `tests/Integration/Service/StatsServiceTest.php` | 3 | Les KPI des back-offices sont justes : utilisateurs, objets, part d'objets personnels et rangés, prêts en cours et en retard, amis et abonnés ; un utilisateur ne compte que ses propres affaires. |
| `tests/Integration/Repository/UserDeletionTest.php` | 1 | Supprimer un compte supprime ses objets, logements, pièces, meubles, prêts et relations (droit à l'effacement), sans toucher aux affaires des autres. |

### Requêtes HTTP (fonctionnels)

| Fichier | Tests | Ce qui est vérifié |
| --- | --- | --- |
| `tests/Functional/Controller/ItemControllerTest.php` | 5 | `GET /api/items/{id}` : 200 pour le propriétaire, 403 pour un autre, 403 sur un objet personnel, 401 sans connexion, 404 si inexistant. Le JSON n'expose que `id` et `pseudo` du propriétaire. |
| `tests/Functional/BackOffice/AdminDashboardTest.php` | 8 | Accès au back-office admin selon le rôle ; KPI en français, anglais et arabe (page de droite à gauche) ; pas de mot de passe affiché ; suppression d'un compte ; modération des relations sans pouvoir en créer. |
| `tests/Functional/BackOffice/AdminReadOnlyContentTest.php` | 12 | L'admin pilote et modère sans exposer les affaires des utilisateurs : pas d'écran logements, pièces, meubles ni prêts ; objets en lecture et suppression seulement ; seul le rôle super admin est modifiable ; le statut d'enfant est conservé ; un admin ne peut ni se rétrograder ni supprimer son propre compte. |
| `tests/Functional/BackOffice/UserSpaceTest.php` | 10 | « Mon espace » : accès, KPI et titre traduits ; liste limitée à ses objets ; propriétaire fixé par le code ; objet personnel non partageable ; prêt limité à ses objets et statut affiché ; changer l'id dans l'URL → 403 ; le sous-compte enfant ne gère pas d'abonnés. |
| `tests/Functional/BackOffice/PlacesTest.php` | 12 | Raccourcis « Ajouter une pièce / un meuble » ; le logement d'un autre n'est jamais pré-sélectionné ; listes déroulantes avec un choix vide (fr et ar) ; création refusée sans logement ; « Pas encore rangé » affiché ; avertissement chiffré avant de supprimer un logement ou une pièce. |

### Les 4 défauts classiques de sécurité (consignes du module)

| Défaut | Protection | Test |
| --- | --- | --- |
| Changer l'id dans l'URL | `OwnedVoter` (back-office) et `ItemVoter` (API) | `UserSpaceTest` (403), `ItemControllerTest` (403) |
| Écrire un champ non modifiable | Propriétaire jamais présent dans les formulaires ; champs grisés côté admin | `UserSpaceTest` (création), `AdminReadOnlyContentTest` (formulaire utilisateur) |
| Exposer un champ interne dans une relation | Groupes de sérialisation (`item:read`) | `ItemControllerTest` (propriétaire = `id` + `pseudo` uniquement) |
| Pas de validation serveur | Contraintes du Validator, dont « objet personnel non partageable » | `UserSpaceTest` (422 et rien d'enregistré), `PlacesTest` (création sans logement refusée) |

### Lien avec les risques de `audit.md` (section 8)

| Risque identifié dans l'audit | Statut |
| --- | --- |
| Accès aux objets d'un autre utilisateur | **Couvert** : Voters en unitaire, 403 en fonctionnel (API et back-office) |
| Objet de valeur jamais partagé | **Couvert** : refusé aux amis, abonnés et sous-compte, même ouvert aux abonnés ; combinaison refusée par la validation |
| Droits du sous-compte enfant (`ROLE_CHILD`) | **Couvert en partie** : ne peut pas partager ni gérer d'abonnés ; vente et liens publics pas encore codés |
| Prêts (statut déduit des dates) | **Couvert** : en unitaire, en intégration (KPI) et en fonctionnel (affichage) |
| Liens publics | Non couvert : fonctionnalité pas encore codée |
| Vente « les amis d'abord » | Non couvert : fonctionnalité pas encore codée |
| Scan par photo | Non couvert : POC réalisé dans Postman (séance 5), pas encore dans l'application |

## 4. Ce qui n'est pas testé, et pourquoi

| Élément | Pourquoi il n'est pas testé |
| --- | --- |
| Getters et setters des entités | Aucune logique : couverts indirectement par les tests d'intégration et fonctionnels. |
| Envoi du formulaire de connexion | Les tests simulent la connexion avec `loginUser()` ; la vraie connexion a été vérifiée à la main et sera couverte par Cypress. |
| Le grisage de la case « visible par les abonnés » | C'est du JavaScript : PHPUnit ne l'exécute pas. La règle serveur, elle, est testée ; le grisage a été vérifié à la main. |
| `UserRepository::upgradePassword()` | Appelé par Symfony seulement quand l'algorithme de hachage change. |
| Méthodes `down()` des migrations | Retour arrière manuel. Les migrations `up()` sont, elles, jouées à chaque `make test-db` et dans la CI. |
| Fixtures (branche `feature/test`) | Outil de démonstration, pas du code métier. Les tests ne dépendent pas des fixtures. |
| Code de `tests/` par PHPStan | Prévu par l'audit (section 2), pas encore activé. |

### Pas de pourcentage de couverture

PHPUnit peut calculer un pourcentage de lignes couvertes (`--coverage-html`), mais il faut une extension PHP dédiée (**PCOV** ou **Xdebug**), absente de l'image Docker. Ce chiffre ne remplace pas la question importante : **les règles à risque sont-elles testées ?** C'est l'objet des sections 3 et 4. L'ajout de PCOV fait partie des prochaines étapes.

## 5. Les tests ont trouvé des défauts

Les tests manuels dans le navigateur ont révélé des défauts, corrigés et accompagnés d'un test pour qu'ils ne reviennent pas : suppression d'un compte qui échouait (clé étrangère), admin qui pouvait exposer des objets, objet personnel partageable, logement pré-sélectionné sans le vouloir. Exemple : le test de suppression de compte échoue **sans la correction, avec l'erreur d'origine**, et passe avec.

Le jeton CSRF refusé à la connexion a été corrigé et vérifié à la main (le formulaire de connexion n'est pas envoyé par les tests, voir section 4).

## 6. Plus tard : tests de bout en bout avec Cypress

Le front Next.js n'existe pas encore (maquettes Figma prêtes). **Cypress** testera alors les parcours dans un vrai navigateur, par exemple :

- se connecter, ouvrir un objet et voir ses informations ;
- vérifier qu'un objet personnel n'apparaît jamais chez un autre utilisateur ;
- vérifier l'affichage des erreurs (403, 404, réseau coupé).

Ces tests compléteront la pyramide sans la remplacer : ils sont lents et fragiles, on en écrira peu, sur les parcours critiques.
