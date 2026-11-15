# Penderie

## Voir la base de données

Adminer, une interface web pour PostgreSQL, est lancé avec les autres conteneurs (`make up`).

1. Ouvrir http://localhost:8081
2. Système : **PostgreSQL**
3. Serveur : **database** (le nom du service Docker, pas `localhost`)
4. Utilisateur, mot de passe et base : les valeurs `POSTGRES_USER`, `POSTGRES_PASSWORD` et `POSTGRES_DB` du service `database` dans [`compose.yaml`](compose.yaml).

> Ce sont des identifiants de **développement local uniquement**. Adminer et ces identifiants ne doivent jamais être utilisés en production.
