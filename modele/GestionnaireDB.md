# 📚 Documentation GestionnaireBD

Une classe PHP simple et puissante pour gérer vos bases de données MySQL avec élégance.

## 🚀 Démarrage rapide

### Installation
```php
// Inclure la classe
require_once 'GestionnaireBD.php';

// Créer une instance
$db = new GestionnaireBD(
    'localhost',        // Host
    'username',         // Utilisateur
    'password',         // Mot de passe
    'ma_base',          // Base de données
    'ma_table'          // Table à manipuler
);
```

### Premier exemple
```php
// Ajouter un utilisateur
$db->ajouter([
    'nom' => 'Dupont',
    'email' => 'jean.dupont@email.com',
    'age' => 30
]);

// Récupérer tous les utilisateurs
$utilisateurs = $db->selectionnerTout();
```

## 🔧 Méthodes principales

### ➕ Ajouter des données
```php
// Ajouter un enregistrement
$succes = $db->ajouter([
    'nom' => 'Martin',
    'email' => 'marie.martin@email.com',
    'age' => 25,
    'ville' => 'Paris'
]);

if ($succes) {
    echo "Utilisateur ajouté avec succès !";
}
```

### 🔍 Récupérer des données

#### Récupérer tous les enregistrements
```php
$tousLesUtilisateurs = $db->selectionnerTout();
```

#### Récupérer un enregistrement spécifique
```php
// Avec condition simple
$utilisateur = $db->selectionnerUn('email = :email', [':email' => 'jean@email.com']);

// Avec plusieurs conditions
$utilisateur = $db->selectionnerUn(
    'age > :age AND ville = :ville', 
    [':age' => 18, ':ville' => 'Paris']
);
```

### ✏️ Modifier des données
```php
// Modifier un utilisateur
$db->modifier(
    ['age' => 31, 'ville' => 'Lyon'],           // Nouvelles données
    'email = :email',                            // Condition
    [':email' => 'jean.dupont@email.com']        // Paramètres
);
```

### 🗑️ Supprimer des données
```php
// Supprimer un utilisateur
$db->supprimer('id = :id', [':id' => 5]);

// Supprimer plusieurs utilisateurs
$db->supprimer('age < :age', [':age' => 18]);
```

## 🔧 Méthodes utilitaires

### 📊 Compter les enregistrements
```php
// Compter tous les enregistrements
$total = $db->compter();

// Compter avec condition
$adultes = $db->compter('age >= :age', [':age' => 18]);
```

### ✅ Vérifier l'existence
```php
// Vérifier si un email existe déjà
if ($db->existe('email = :email', [':email' => 'test@email.com'])) {
    echo "Cet email est déjà utilisé !";
}
```

### 🔄 Trier les données
```php
$utilisateurs = $db->selectionnerTout();

// Trier par nom (croissant)
$utilisateursTriesNom = $db->tri($utilisateurs, 'nom', 'ASC');

// Trier par âge (décroissant)
$utilisateursTriesAge = $db->tri($utilisateurs, 'age', 'DESC');
```

### 📄 Paginer les données
```php
$utilisateurs = $db->selectionnerTout();

// Page 1, 10 utilisateurs par page
$page1 = $db->pagination($utilisateurs, 1, 10);

// Page 2, 5 utilisateurs par page
$page2 = $db->pagination($utilisateurs, 2, 5);
```

## 🎯 Requêtes personnalisées

### Exécuter une requête SQL
```php
// Requête SELECT personnalisée
$resultats = $db->executerRequete(
    'SELECT nom, email FROM ma_table WHERE age BETWEEN :min AND :max',
    [':min' => 20, ':max' => 40]
);

// Requête UPDATE personnalisée
$db->executerRequete(
    'UPDATE ma_table SET statut = :statut WHERE derniere_connexion < :date',
    [':statut' => 'inactif', ':date' => '2024-01-01']
);
```

## 📂 Import / Export

### 📥 Importer des données

#### Depuis un fichier CSV
```php
// CSV avec en-têtes (par défaut)
$db->importer('utilisateurs.csv', 'csv', true);

// CSV sans en-têtes
$db->importer('donnees.csv', 'csv', false);
```

#### Depuis un fichier JSON
```php
$db->importer('utilisateurs.json', 'json');
```

### 📤 Exporter des données

#### Vers un fichier CSV
```php
// Exporter toutes les colonnes
$db->exporter('export_complet.csv', 'csv');

// Exporter seulement certaines colonnes
$db->exporter('export_partiel.csv', 'csv', ['nom', 'email']);
```

#### Vers un fichier JSON
```php
$db->exporter('utilisateurs.json', 'json');
```

## 🛠️ Configuration et maintenance

### Changer de table
```php
$db->setTable('autre_table');
```

### Personnaliser le fichier de journal
```php
$db->setCheminJournal('logs/mon_journal.log');
```

### Vérifier les erreurs
```php
$erreurs = $db->getErreurs();
if (!empty($erreurs)) {
    foreach ($erreurs as $erreur) {
        echo "[{$erreur['timestamp']}] {$erreur['message']}\n";
    }
}
```

## 💡 Exemples pratiques

### Système d'authentification
```php
class AuthManager {
    private $db;
    
    public function __construct() {
        $this->db = new GestionnaireBD('localhost', 'user', 'pass', 'app', 'utilisateurs');
    }
    
    public function inscrire($nom, $email, $motDePasse) {
        // Vérifier si l'email existe déjà
        if ($this->db->existe('email = :email', [':email' => $email])) {
            return false; // Email déjà utilisé
        }
        
        // Créer l'utilisateur
        return $this->db->ajouter([
            'nom' => $nom,
            'email' => $email,
            'mot_de_passe' => password_hash($motDePasse, PASSWORD_DEFAULT),
            'date_creation' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function connecter($email, $motDePasse) {
        $utilisateur = $this->db->selectionnerUn('email = :email', [':email' => $email]);
        
        if ($utilisateur && password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
            // Mettre à jour la dernière connexion
            $this->db->modifier(
                ['derniere_connexion' => date('Y-m-d H:i:s')],
                'id = :id',
                [':id' => $utilisateur['id']]
            );
            return $utilisateur;
        }
        
        return false;
    }
}
```

### Gestion de blog
```php
class BlogManager {
    private $db;
    
    public function __construct() {
        $this->db = new GestionnaireBD('localhost', 'user', 'pass', 'blog', 'articles');
    }
    
    public function publierArticle($titre, $contenu, $auteur) {
        return $this->db->ajouter([
            'titre' => $titre,
            'contenu' => $contenu,
            'auteur' => $auteur,
            'date_publication' => date('Y-m-d H:i:s'),
            'statut' => 'publie'
        ]);
    }
    
    public function getArticlesRecents($limite = 5) {
        $articles = $this->db->executerRequete(
            'SELECT * FROM articles WHERE statut = :statut ORDER BY date_publication DESC LIMIT :limite',
            [':statut' => 'publie', ':limite' => $limite]
        );
        
        return $articles;
    }
    
    public function rechercherArticles($motCle) {
        return $this->db->executerRequete(
            'SELECT * FROM articles WHERE (titre LIKE :motcle OR contenu LIKE :motcle) AND statut = :statut',
            [':motcle' => "%{$motCle}%", ':statut' => 'publie']
        );
    }
    
    public function exporterArticles() {
        return $this->db->exporter('sauvegarde_articles.json', 'json', ['titre', 'auteur', 'date_publication']);
    }
}
```

### Système de statistiques
```php
class StatsManager {
    private $db;
    
    public function __construct() {
        $this->db = new GestionnaireBD('localhost', 'user', 'pass', 'analytics', 'visites');
    }
    
    public function enregistrerVisite($page, $ip, $userAgent) {
        return $this->db->ajouter([
            'page' => $page,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function getStatsDuJour() {
        $aujourd_hui = date('Y-m-d');
        return [
            'visites_total' => $this->db->compter('DATE(timestamp) = :date', [':date' => $aujourd_hui]),
            'visiteurs_uniques' => $this->db->executerRequete(
                'SELECT COUNT(DISTINCT ip) as total FROM visites WHERE DATE(timestamp) = :date',
                [':date' => $aujourd_hui]
            )[0]['total'] ?? 0
        ];
    }
    
    public function getPagesPopulaires($limite = 10) {
        return $this->db->executerRequete(
            'SELECT page, COUNT(*) as visites FROM visites GROUP BY page ORDER BY visites DESC LIMIT :limite',
            [':limite' => $limite]
        );
    }
}
```

## ⚠️ Gestion des erreurs

### Types d'erreurs
Le GestionnaireBD gère automatiquement les erreurs et les journalise. Voici les principales situations :

- **Erreurs de connexion** : Problèmes de base de données
- **Erreurs de validation** : Données non conformes
- **Erreurs de requête** : SQL invalide
- **Erreurs de fichier** : Import/Export échoué

### Récupérer les erreurs
```php
// Vérifier s'il y a eu des erreurs
$erreurs = $db->getErreurs();

if (!empty($erreurs)) {
    echo "⚠️ Erreurs détectées :\n";
    foreach ($erreurs as $erreur) {
        echo "- [{$erreur['timestamp']}] {$erreur['message']}\n";
    }
}
```

### Journal des opérations
Toutes les opérations sont automatiquement journalisées dans le fichier `journalGBD.log` :

```
[2024-01-15 10:30:15] [INFO] [Connexion] Connexion établie à la base de données blog, table articles
[2024-01-15 10:30:20] [INFO] [Ajout] Enregistrement ajouté dans articles
[2024-01-15 10:30:25] [WARNING] [Validation] Erreurs de validation des données : La colonne 'email_invalide' n'existe pas dans la table
[2024-01-15 10:30:30] [ERROR] [Import] Erreur lors de l'import : Le fichier n'existe pas : donnees_inexistantes.csv
```

## 🔒 Sécurité

### Requêtes préparées
Toutes les requêtes utilisent des paramètres préparés pour éviter les injections SQL :

```php
// ✅ Sécurisé
$utilisateur = $db->selectionnerUn('email = :email', [':email' => $email]);

// ❌ À éviter (non disponible dans cette classe)
// $utilisateur = $db->executerRequete("SELECT * FROM users WHERE email = '$email'");
```

### Validation automatique
Les données sont automatiquement validées avant insertion :

```php
// Cette insertion échouera si :
// - Une colonne n'existe pas
// - Un type de données est incorrect
// - Une contrainte NOT NULL est violée
// - La longueur maximale est dépassée
$db->ajouter([
    'nom' => 'Jean',
    'age' => 'pas_un_nombre',  // ❌ Erreur de type
    'email' => null            // ❌ Erreur si NOT NULL
]);
```

## 🎯 Conseils et bonnes pratiques

### Performance
- Utilisez `existe()` plutôt que `selectionnerUn()` pour vérifier l'existence
- Spécifiez les colonnes nécessaires dans les exports
- Utilisez la pagination pour de gros volumes de données

### Maintenance
- Consultez régulièrement le fichier de journal
- Vérifiez les erreurs après chaque opération critique
- Sauvegardez vos données avec l'export automatique

### Structure de données
- Respectez les conventions de nommage de vos colonnes
- Définissez correctement les contraintes NOT NULL
- Utilisez les bons types de données MySQL

---

🎉 **Vous êtes maintenant prêt à utiliser GestionnaireBD !**

Pour plus d'informations ou des questions spécifiques, n'hésitez pas à consulter le code source ou à créer vos propres exemples basés sur cette documentation.