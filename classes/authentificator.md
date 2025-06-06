# Authentificateur

La classe **Authentificateur** est une classe PHP générique permettant de gérer l’authentification des utilisateurs via un login (ex : email) et un mot de passe, avec prise en charge de la gestion des erreurs, de la création de compte, de la connexion, de la déconnexion, et de la personnalisation des champs et de la table utilisateur.

## Sommaire

- [Instanciation](#instanciation)
- [Configuration](#configuration)
- [Principales Méthodes](#principales-méthodes)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Hooks](#hooks)
- [Exemple d’utilisation](#exemple-dutilisation)

---

## Instanciation

```php
$auth = new Authentificateur(
    [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'based'
    ],
    [
        'nom_table' => 'utilisateurs',
        'colonne_login' => 'email',
        'colonne_mot_de_passe' => 'password'
    ]
);
```

## Configuration

- **Paramètres de connexion à la base de données** (tableau 1) :
    - `host` : Adresse du serveur MySQL
    - `username` : Nom d’utilisateur MySQL
    - `password` : Mot de passe MySQL
    - `database` : Nom de la base de données

- **Paramètres de la table utilisateur** (tableau 2) :
    - `nom_table` : Nom de la table des utilisateurs
    - `colonne_login` : Nom de la colonne pour le login (ex : `email`)
    - `colonne_mot_de_passe` : Nom de la colonne pour le mot de passe (ex : `password`)
    - `fichier_log` (optionnel) : Fichier de log des erreurs
    - `creer_table_auto` (optionnel) : Création automatique de la table si elle n’existe pas (booléen)

## Principales Méthodes

- `creerCompte(string $login, string $motDePasse, array $donneesSupplementaires = []) : bool`
    - Crée un nouvel utilisateur avec login, mot de passe et champs supplémentaires.

- `connecter(string $login, string $motDePasse) : bool`
    - Connecte un utilisateur avec son login et mot de passe.

- `deconnecter() : void`
    - Déconnecte l’utilisateur actuellement connecté.

- `estConnecte() : bool`
    - Vérifie si un utilisateur est connecté.

- `utilisateurConnecte() : ?array`
    - Retourne les données de l’utilisateur connecté.

- `obtenirChampsObligatoires() : array`
    - Retourne la liste des champs obligatoires à la création d’un compte.

- `obtenirSchemaTable() : array`
    - Retourne le schéma de la table utilisateur.

- `obtenirErreurs() : array`
    - Retourne la liste des erreurs rencontrées lors des opérations.

## Gestion des erreurs

Toutes les méthodes principales ajoutent un message d’erreur consultable via `obtenirErreurs()` en cas d’échec (ex : login déjà existant, mot de passe trop court, champ obligatoire manquant, etc.).

## Hooks

Vous pouvez définir des hooks pour exécuter du code avant ou après la connexion :

```php
$auth->definirHookAvantConnexion(function($login) {
    // Code à exécuter avant la connexion
});
$auth->definirHookApresConnexion(function($login) {
    // Code à exécuter après la connexion
});
```

## Exemple d’utilisation

```php
// Création d’un compte
if ($auth->creerCompte('user@example.com', 'motdepasse123', ['nom' => 'Dupont'])) {
    echo "Compte créé !";
} else {
    print_r($auth->obtenirErreurs());
}

// Connexion
if ($auth->connecter('user@example.com', 'motdepasse123')) {
    echo "Connecté !";
} else {
    print_r($auth->obtenirErreurs());
}

// Vérification connexion
if ($auth->estConnecte()) {
    $utilisateur = $auth->utilisateurConnecte();
    echo "Bienvenue " . $utilisateur['email'];
}

// Déconnexion
$auth->deconnecter();
```

---

**Remarque** :  
La classe Authentificateur est conçue pour être facilement adaptable à toute structure de table utilisateur, tant que vous indiquez les bons noms de colonnes dans la configuration.