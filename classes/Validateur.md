# Documentation - Classe Validateur PHP

## Table des matières
- [Introduction](#introduction)
- [Installation](#installation)
- [Utilisation basique](#utilisation-basique)
- [Configuration](#configuration)
- [Règles de validation](#règles-de-validation) 
- [Messages personnalisés](#messages-personnalisés)
- [Règles personnalisées](#règles-personnalisées)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Méthodes utilitaires](#méthodes-utilitaires)
- [Exemples avancés](#exemples-avancés)
- [API Reference](#api-reference)

## Introduction

La classe `Validateur` est une solution générique et extensible pour la validation de données en PHP. Elle offre un système complet de validation avec détection automatique des règles, support des règles personnalisées, et une gestion avancée des erreurs.

### Caractéristiques principales
- ✅ **Règles natives** : Email, téléphone, URL, numérique, etc.
- ✅ **Détection automatique** : Analyse les données pour appliquer les règles appropriées
- ✅ **Règles personnalisées** : Ajout facile de nouvelles règles métier
- ✅ **Messages personnalisés** : Customisation complète des messages d'erreur
- ✅ **Gestion d'erreurs avancée** : Niveaux d'erreur, timestamps, stockage structuré
- ✅ **Interface fluide** : Chaînage des méthodes pour une utilisation élégante
- ✅ **Persistance** : Sauvegarde automatique des règles personnalisées

---

## Installation

```php
// Inclure la classe dans votre projet
require_once 'Validateur.php';

// Créer une instance
$validateur = new Validateur();
```

---

## Utilisation basique 

### Validation simple avec détection automatique

```php
$validateur = new Validateur();

$donnees = [
    'nom' => 'Jean Dupont',
    'email' => 'jean@example.com',
    'telephone' => '0123456789'
];

$validateur->setDonnees($donnees);

if ($validateur->valider()) {
    echo "Validation réussie !";
} else {
    foreach ($validateur->getTousLesMessages() as $message) {
        echo $message . "\n";
    }
}
```

### Validation avec règles spécifiques

```php
$validateur = new Validateur();

$donnees = [
    'nom' => 'Jean',
    'email' => 'jean@example.com',
    'age' => '25'
];

$regles = [
    'nom' => ['required', 'alpha'],
    'email' => ['required', 'email'],
    'age' => ['required', 'numeric', 'min:18']
];

$resultat = $validateur
    ->setDonnees($donnees)
    ->setRegles($regles)
    ->valider();
```

---

## Configuration

### Setters (Interface fluide)

```php
$validateur = new Validateur();

$validateur
    ->setDonnees($donnees)                    // Définir les données à valider
    ->setRegles($regles)                      // Définir les règles de validation
    ->setMessagesPersonnalises($messages);    // Définir les messages personnalisés
```

### Getters

```php
$donnees = $validateur->getDonnees();                    // Récupérer les données
$regles = $validateur->getRegles();                      // Récupérer les règles
$messages = $validateur->getMessagesPersonnalises();     // Récupérer les messages
$erreurs = $validateur->getErreurs();                    // Récupérer toutes les erreurs
$erreursChamp = $validateur->getErreursParChamp('nom');  // Erreurs d'un champ spécifique
```

---

## Règles de validation

### Règles natives disponibles

| Règle         | Description                       | Exemple                   | Paramètre   |
|---------------|-----------------------------------|---------------------------|-------------|
| `required`    | Champ obligatoire                 | `required`                | Non         |
| `email`       | Adresse email valide              | `email`                   | Non         |
| `tel`         | Numéro de téléphone               | `tel`                     | Non         |
| `url`         | URL valide                        | `url`                     | Non         |
| `numeric`     | Valeur numérique                  | `numeric`                 | Non         |
| `integer`     | Nombre entier                     | `integer`                 | Non         |
| `min`         | Longueur/valeur minimale          | `min:5`                   | Oui         |
| `max`         | Longueur/valeur maximale          | `max:100`                 | Oui         |
| `regex`       | Expression régulière              | `regex:/^[A-Z]+$/`        | Oui         |
| `date`        | Date valide                       | `date:Y-m-d`              | Optionnel   |
| `alpha`       | Lettres uniquement                | `alpha`                   | Non         |
| `alphanumeric`| Lettres et chiffres               | `alphanumeric`            | Non         |

### Syntaxe des règles

```php
// Tableau de chaînes
$regles = [
    'champ' => ['required', 'email']
];

// Chaîne avec séparateur pipe
$regles = [
    'champ' => 'required|email|min:5'
];

// Règles avec paramètres
$regles = [
    'mot_de_passe' => ['required', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/']
];
```

### Détection automatique des règles

La classe peut détecter automatiquement les règles appropriées basées sur :

**Noms de champs :**
- Contient `email` → règle `email`
- Contient `tel` ou `phone` → règle `tel`
- Contient `url` ou `website` → règle `url`
- Contient `date` → règle `date`

**Types de données :**
- Valeur numérique → règle `numeric`
- Par défaut → règle `required`

```php
$donnees = [
    'user_email' => 'test@example.com',    // Détecte: email
    'phone_number' => '0123456789',        // Détecte: tel
    'website_url' => 'https://site.com',   // Détecte: url
    'birth_date' => '1990-01-01',          // Détecte: date
    'age' => '25'                          // Détecte: numeric
];

// Utilise la détection automatique
$validateur->setDonnees($donnees)->valider();
```

---

## Messages personnalisés

### Messages par défaut

La classe fournit des messages par défaut pour chaque règle avec des placeholders :

```php
'required' => 'Le champ :champ est obligatoire.'
'email' => 'Le champ :champ doit être une adresse email valide.'
'min' => 'Le champ :champ doit avoir au minimum :valeur caractères.'
```

### Personnalisation des messages

```php
$messages = [
    // Message global pour une règle
    'required' => 'Ce champ est obligatoire.',
    
    // Message spécifique à un champ
    'nom.required' => 'Le nom est obligatoire.',
    'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
    
    // Avec paramètres
    'age.min' => 'Vous devez avoir au moins :valeur ans.'
];

$validateur->setMessagesPersonnalises($messages);
```

### Placeholders disponibles

- `:champ` - Nom du champ validé
- `:valeur` - Valeur du paramètre de la règle

---

## Règles personnalisées

### Ajouter une règle personnalisée

```php
// Règle simple
$validateur->ajouterRegles('majeur', function($valeur, $parametre = null) {
    return is_numeric($valeur) && (int)$valeur >= 18;
});

// Règle complexe avec paramètre
$validateur->ajouterRegles('longueurExacte', function($valeur, $parametre = null) {
    return strlen($valeur) === (int)$parametre;
});
```

### Utilisation des règles personnalisées

```php
$regles = [
    'age' => ['required', 'majeur'],
    'code' => ['required', 'longueurExacte:5']
];

$messages = [
    'majeur' => 'Vous devez être majeur.',
    'longueurExacte' => 'Le code doit faire exactement :valeur caractères.'
];
```

### Persistance des règles

Les règles personnalisées sont automatiquement sauvegardées dans le fichier `newRegles.php` et rechargées à chaque instantiation.

```php
// Changer le fichier de sauvegarde
$validateur->setFichierRegles('mes_regles_custom.php');
```

---

## Gestion des erreurs

### Structure des erreurs

Chaque erreur contient :

```php
[
    'niveau' => 'erreur',                    // info, warning, erreur
    'message' => 'Message d\'erreur',
    'timestamp' => '2024-01-15 14:30:25',
    'datetime' => DateTime Object
]
```

### Niveaux d'erreur

```php
// Constantes disponibles
Validateur::NIVEAU_INFO     // 'info'
Validateur::NIVEAU_WARNING  // 'warning' 
Validateur::NIVEAU_ERREUR   // 'erreur'
```

### Récupération des erreurs

```php
// Toutes les erreurs structurées
$erreurs = $validateur->getErreurs();

// Erreurs d'un champ spécifique
$erreursNom = $validateur->getErreursParChamp('nom');

// Tous les messages sous forme de tableau
$messages = $validateur->getTousLesMessages();

// Premier message d'erreur d'un champ
$premierMessage = $validateur->premierMessageErreur('email');
```

### Ajouter des erreurs personnalisées

```php
$validateur->ajouterErreur(
    'champ_custom',                     // Nom du champ
    Validateur::NIVEAU_WARNING,         // Niveau
    'Message d\'erreur personnalisé'    // Message
);
```

---

## Méthodes utilitaires

### Vérification du statut

```php
// Vérifier si la validation a échoué
if ($validateur->aEchoue()) {
    echo "Des erreurs ont été détectées";
}

// Équivalent à
if (!empty($validateur->getErreurs())) {
    // ...
}
```

### Nettoyage

```php
// Effacer toutes les erreurs
$validateur->effacerErreurs();
```

### Combinaison de règles

```php
// Valider un champ avec plusieurs règles
$valide = $validateur->combinerRegles('email', [
    'required',
    'email',
    'regex:/^[a-zA-Z0-9._%+-]+@entreprise\.com$/'
]);
```

---

## Exemples avancés

### Validation de formulaire complet

```php
class FormulaireInscription
{
    private $validateur;
    
    public function __construct()
    {
        $this->validateur = new Validateur();
        
        // Ajouter des règles métier
        $this->ajouterReglesMetier();
    }
    
    private function ajouterReglesMetier()
    {
        // Mot de passe fort
        $this->validateur->ajouterRegles('motDePasseFort', function($valeur) {
            return strlen($valeur) >= 8 
                && preg_match('/[A-Z]/', $valeur)
                && preg_match('/[a-z]/', $valeur) 
                && preg_match('/[0-9]/', $valeur)
                && preg_match('/[^a-zA-Z0-9]/', $valeur);
        });
        
        // Email entreprise
        $this->validateur->ajouterRegles('emailEntreprise', function($valeur) {
            return str_ends_with($valeur, '@monentreprise.com');
        });
    }
    
    public function validerInscription(array $donnees): array
    {
        $regles = [
            'prenom' => 'required|alpha|min:2',
            'nom' => 'required|alpha|min:2',
            'email' => 'required|email|emailEntreprise',
            'mot_de_passe' => 'required|motDePasseFort',
            'age' => 'required|numeric|min:18',
            'telephone' => 'tel'
        ];
        
        $messages = [
            'emailEntreprise' => 'Vous devez utiliser votre email professionnel.',
            'motDePasseFort' => 'Le mot de passe doit contenir au moins 8 caractères avec majuscules, minuscules, chiffres et caractères spéciaux.'
        ];
        
        $valide = $this->validateur
            ->setDonnees($donnees)
            ->setRegles($regles)
            ->setMessagesPersonnalises($messages)
            ->valider();
            
        return [
            'valide' => $valide,
            'erreurs' => $this->validateur->getErreurs(),
            'messages' => $this->validateur->getTousLesMessages()
        ];
    }
}
```

### Validation conditionnelle

```php
// Ajouter une règle de validation conditionnelle
$validateur->ajouterRegles('requiredIf', function($valeur, $condition) {
    // La condition est passée sous forme "champ:valeur"
    [$champCondition, $valeurCondition] = explode(':', $condition);
    
    $donnees = $this->getDonnees(); // Accès aux données globales
    
    if (isset($donnees[$champCondition]) && $donnees[$champCondition] == $valeurCondition) {
        return !empty($valeur);
    }
    
    return true; // Pas de validation si condition non remplie
});

// Utilisation
$regles = [
    'type_compte' => 'required',
    'numero_siret' => 'requiredIf:type_compte:entreprise'
];
```

### Validation en cascade

```php
class ValidateurCascade extends Validateur
{
    public function validerEnCascade(array $groupesRegles): bool
    {
        foreach ($groupesRegles as $groupe => $regles) {
            echo "Validation du groupe: $groupe\n";
            
            if (!$this->setRegles($regles)->valider()) {
                echo "Échec du groupe $groupe\n";
                return false;
            }
            
            echo "Groupe $groupe validé ✅\n";
        }
        
        return true;
    }
}

// Utilisation
$groupes = [
    'donnees_personnelles' => [
        'nom' => 'required|alpha',
        'prenom' => 'required|alpha'
    ],
    'coordonnees' => [
        'email' => 'required|email',
        'telephone' => 'tel'
    ],
    'securite' => [
        'mot_de_passe' => 'required|motDePasseFort'
    ]
];

$validateur = new ValidateurCascade();
$validateur->setDonnees($donnees)->validerEnCascade($groupes);
```

---

## API Reference

### Constructeur

```php
/**
 * Initialise le validateur et charge les règles personnalisées existantes.
 */
public function __construct()
```

### Méthodes de configuration

```php
/**
 * Définit les données à valider.
 * @param array $donnees
 * @return self
 */
public function setDonnees(array $donnees): self

/**
 * Définit les règles de validation.
 * @param array $regles
 * @return self
 */
public function setRegles(array $regles): self  

/**
 * Définit les messages personnalisés.
 * @param array $messages
 * @return self
 */
public function setMessagesPersonnalises(array $messages): self

/**
 * Définit le fichier pour les règles personnalisées.
 * @param string $chemin
 * @return void
 */
public function setFichierRegles(string $chemin): void
```

### Méthodes d'accès

```php
public function getDonnees(): array
public function getRegles(): array
public function getMessagesPersonnalises(): array
public function getErreurs(): array
public function getErreursParChamp(string $champ): array
```

### Méthodes de validation

```php
public function valider(array $regles = []): bool
public function combinerRegles(string $champ, array $regles): bool
public function detecterRegles(): array
```

### Gestion des erreurs

```php
public function ajouterErreur(string $champ, string $niveau, string $message): void
public function getTousLesMessages(): array
public function premierMessageErreur(string $champ): ?string
public function effacerErreurs(): void
public function aEchoue(): bool
```

### Règles personnalisées

```php
public function ajouterRegles(string $titre, callable $fonction): bool
public function validerNewRegle(string $champ, string $titre, $valeur, $parametre = null): bool
```

### Règles natives (méthodes protégées)

```php
protected function validerRequired(string $champ, $valeur, $parametre = null): bool
protected function validerEmail(string $champ, $valeur, $parametre = null): bool
protected function validerTel(string $champ, $valeur, $parametre = null): bool
protected function validerUrl(string $champ, $valeur, $parametre = null): bool
protected function validerNumeric(string $champ, $valeur, $parametre = null): bool
protected function validerInteger(string $champ, $valeur, $parametre = null): bool
protected function validerMin(string $champ, $valeur, $parametre = null): bool
protected function validerMax(string $champ, $valeur, $parametre = null): bool
protected function validerRegex(string $champ, $valeur, $parametre = null): bool
protected function validerDate(string $champ, $valeur, $parametre = null): bool
protected function validerAlpha(string $champ, $valeur, $parametre = null): bool
protected function validerAlphanumeric(string $champ, $valeur, $parametre = null): bool
```
