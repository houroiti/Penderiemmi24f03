# Couverture des tests — Penderie

Date : 18 octobre 2026
Branche : `feature/item-tests`
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

## 2. Les trois niveaux de tests

| Niveau | Classe de base | Base de données | Rôle |
| --- | --- | --- | --- |
| Unitaire | `TestCase` | non | Vérifier une règle métier isolée, très vite |
| Intégration | `KernelTestCase` | oui | Vérifier que le code fonctionne avec Symfony et PostgreSQL |
| Fonctionnel | `WebTestCase` | oui | Vérifier une requête HTTP de bout en bout dans l'API |
| De bout en bout (E2E) | Cypress | oui | Vérifier un parcours dans le navigateur — **plus tard**, voir section 5 |

## 3. Ce qui est testé

**Résultat actuel : 15 tests, 27 assertions, tous au vert.**

| Fichier | Niveau | Tests | Ce qui est vérifié |
| --- | --- | --- | --- |
| `tests/Unit/Security/Voter/ItemVoterTest.php` | Unitaire | 9 | Règles d'accès de `ItemVoter` : le propriétaire peut voir et modifier (y compris un objet personnel) ; un autre utilisateur ne peut ni voir ni modifier ; un objet personnel est refusé aux autres ; un anonyme est refusé ; le Voter s'abstient sur un attribut inconnu ou un sujet qui n'est pas un `Item`. |
| `tests/Integration/Repository/ItemRepositoryTest.php` | Intégration | 1 | Un `Item` enregistré est relu depuis PostgreSQL (après `clear()`) avec son nom, `isPersonal = false` par défaut, son propriétaire (clé étrangère) et sa date de création. |
| `tests/Functional/Controller/ItemControllerTest.php` | Fonctionnel | 5 | `GET /api/items/{id}` : 200 et JSON pour le propriétaire ; 403 pour un autre utilisateur ; 403 sur un objet personnel ; 401 sans connexion ; 404 pour un objet inexistant. Le propriétaire n'est exposé qu'avec `id` et `pseudo` (jamais l'email, les rôles ou le mot de passe). |

### Lien avec les risques de `audit.md` (section 8)

| Risque identifié dans l'audit | Statut |
| --- | --- |
| Accès aux objets d'un autre utilisateur | **Couvert** : 403 en test fonctionnel, DENIED en test unitaire |
| Objet de valeur jamais partagé | **Couvert en partie** : un objet personnel est refusé aux autres. Le cas « pièce partagée » attend les pièces et le partage |
| Liens publics | Non couvert : fonctionnalité pas encore codée |
| Droits du sous-compte enfant (`ROLE_CHILD`) | Non couvert : fonctionnalité pas encore codée |
| Prêts (statut déduit des dates) | Non couvert : fonctionnalité pas encore codée |
| Vente « les amis d'abord » | Non couvert : fonctionnalité pas encore codée |
| Scan par photo | Non couvert : fonctionnalité pas encore codée |

## 4. Ce qui n'est pas testé, et pourquoi

| Élément | Pourquoi il n'est pas testé |
| --- | --- |
| Getters et setters des entités | Aucune logique : ils sont couverts indirectement par les tests d'intégration et fonctionnels. |
| Contraintes de validation (`NotBlank`, `Email`, `Length`, `UniqueEntity`) | Aucune route ne crée ou ne modifie encore un `User` ou un `Item` : elles seront testées avec les futurs `POST` et `PATCH` (réponse 422). |
| Droit `EDIT` côté HTTP | Testé seulement en unitaire : aucune route de modification n'existe encore. |
| Connexion (login) et `UserRepository::upgradePassword()` | Pas encore d'authentification : les tests simulent la connexion avec `loginUser()`. |
| Partage amis / abonnés (FOLLOWS), sous-compte enfant | Fonctionnalités pas encore codées. Le Voter est prêt à accueillir le partage (`canView()`). |
| Méthodes `down()` des migrations | Retour arrière manuel, vérifié à la main. Les migrations `up()` sont, elles, jouées à chaque `make test-db`. |
| Fixtures (branche `feature/test`) | Outil de démonstration, pas du code métier. Les tests ne dépendent pas des fixtures. |
| Code de `tests/` par PHPStan | Prévu par l'audit (section 2), pas encore activé. |

### Pas de pourcentage de couverture

PHPUnit peut calculer un pourcentage de lignes couvertes (`--coverage-html`), mais il faut une extension PHP dédiée (**PCOV** ou **Xdebug**), absente de l'image Docker. Ce chiffre ne remplace pas la question importante : **les règles à risque sont-elles testées ?** C'est l'objet des sections 3 et 4.

## 5. Plus tard : tests de bout en bout avec Cypress

Le front Next.js n'existe pas encore. Quand il sera codé, **Cypress** testera les parcours dans un vrai navigateur, par exemple :

- se connecter, ouvrir un objet et voir ses informations ;
- vérifier qu'un objet personnel n'apparaît jamais chez un autre utilisateur ;
- vérifier l'affichage des erreurs (403, 404, réseau coupé).

Ces tests compléteront la pyramide sans la remplacer : ils sont lents et fragiles, on en écrira peu, sur les parcours critiques.