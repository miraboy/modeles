<?php
require_once './classes/Authentificateur.php';

// Configuration de la base de données
$configBdd = [
    'host' => 'localhost',
    'database' => 'based',
    'username' => 'root',
    'password' => '',
    'sgbd' => 'mysql',
    'port' => '3306',
    'charset' => 'utf8mb4'
];

// Pour les tests, on peut utiliser SQLite si MySQL n'est pas disponible
$configBddSqlite = [
    'sgbd' => 'sqlite',
    'database' => ':memory:', // Base en mémoire pour les tests
    'username' => '',
    'password' => ''
];

// Initialisation de l'authentificateur
try {
    $auth = new Authentificateur($configBdd, [
        'nom_table' => 'utilisateurs',
        'colonne_login' => 'email',
        'colonne_mot_de_passe' => 'password',
        'fichier_log' => 'tentatives_echec.log',
        'creer_table_auto' => true
    ]);
} catch (Exception $e) {
    // Si MySQL échoue, essayer SQLite
    try {
        $auth = new Authentificateur($configBddSqlite);
        $message = "Note : Utilisation de SQLite en mémoire pour les tests (MySQL non disponible)";
        $messageType = "info";
    } catch (Exception $e2) {
        die("Erreur de connexion à la base de données : " . $e2->getMessage());
    }
}

// Définir des hooks de démonstration
$auth->definirHookAvantConnexion(function($login) {
    // Exemple : bloquer certains logins
    if ($login === 'admin_bloque') {
        return false;
    }
    return true;
});

$auth->definirHookApresConnexion(function($utilisateur) {
    // Exemple : enregistrer la dernière connexion
    file_put_contents('dernieres_connexions.log', 
        "[" . date('Y-m-d H:i:s') . "] Connexion de : " . $utilisateur['email'] . PHP_EOL, 
        FILE_APPEND
    );
});

// Traitement des actions
$message = '';
$messageType = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'creer_compte':
                $donneesSupplementaires = [];
                
                // Récupérer les champs obligatoires supplémentaires
                foreach ($auth->obtenirChampsObligatoires() as $champ) {
                    if (isset($_POST[$champ])) {
                        $donneesSupplementaires[$champ] = $_POST[$champ];
                    }
                }
                
                if ($auth->creerCompte($_POST['login'], $_POST['mot_de_passe'], $donneesSupplementaires)) {
                    $message = "Compte créé avec succès !";
                    $messageType = "success";
                } else {
                    $message = implode('<br>', $auth->obtenirErreurs());
                    $messageType = "error";
                }
                break;
                
            case 'connecter':
                if ($auth->connecter($_POST['login'], $_POST['mot_de_passe'])) {
                    $message = "Connexion réussie !";
                    $messageType = "success";
                } else {
                    $message = $auth->obtenirErreur();
                    $messageType = "error";
                }
                break;
                
            case 'deconnecter':
                $auth->deconnecter();
                $message = "Déconnexion effectuée";
                $messageType = "info";
                break;
        }
    }
}

$utilisateurConnecte = $auth->utilisateurConnecte();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Authentificateur</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1, h2 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .message {
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .form-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background-color: #fafafa;
        }
        
        .form-group {
            margin: 15px 0;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        button {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        
        button:hover {
            background-color: #0056b3;
        }
        
        button.secondary {
            background-color: #6c757d;
        }
        
        button.secondary:hover {
            background-color: #545b62;
        }
        
        button.danger {
            background-color: #dc3545;
        }
        
        button.danger:hover {
            background-color: #c82333;
        }
        
        .status-panel {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .user-info {
            background-color: #f0f9ff;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }
        
        .test-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }
        
        .log-content {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Test de la Classe Authentificateur</h1>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statut de connexion -->
        <div class="status-panel">
            <h2>📊 Statut de connexion</h2>
            <?php if ($auth->estConnecte()): ?>
                <div class="user-info">
                    <strong>✅ Utilisateur connecté :</strong><br>
                    Login: <?= htmlspecialchars($utilisateurConnecte['email']) ?><br>
                    ID: <?= htmlspecialchars($utilisateurConnecte['id']) ?><br>
                    Créé le: <?= htmlspecialchars($utilisateurConnecte['date_creation']) ?>
                </div>
                
                <form method="post" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="deconnecter">
                    <button type="submit" class="danger">🚪 Se déconnecter</button>
                </form>
            <?php else: ?>
                <p><strong>❌ Aucun utilisateur connecté</strong></p>
            <?php endif; ?>
        </div>
        
        <?php if (!$auth->estConnecte()): ?>
        <!-- Formulaire de création de compte -->
        <div class="form-section">
            <h2>👤 Créer un nouveau compte</h2>
            <form method="post">
                <input type="hidden" name="action" value="creer_compte">
                
                <div class="form-group">
                    <label for="login_creation">Email (Login) :</label>
                    <input type="email" id="login_creation" name="login" required>
                </div>
                
                <div class="form-group">
                    <label for="password_creation">Mot de passe :</label>
                    <input type="password" id="password_creation" name="mot_de_passe" required>
                </div>
                
                <?php 
                // Afficher les champs obligatoires supplémentaires
                $champsObligatoires = $auth->obtenirChampsObligatoires();
                if (!empty($champsObligatoires)): ?>
                    <div style="background-color: #fff3cd; padding: 10px; border-radius: 4px; margin: 15px 0;">
                        <strong>⚠️ Champs obligatoires détectés dans la table :</strong>
                    </div>
                    <?php foreach ($champsObligatoires as $champ): ?>
                        <div class="form-group">
                            <label for="<?= htmlspecialchars($champ) ?>"><?= htmlspecialchars(ucfirst($champ)) ?> :</label>
                            <input type="text" id="<?= htmlspecialchars($champ) ?>" name="<?= htmlspecialchars($champ) ?>" required>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <button type="submit">✨ Créer le compte</button>
            </form>
        </div>
        
        <!-- Formulaire de connexion -->
        <div class="form-section">
            <h2>🔑 Se connecter</h2>
            <form method="post">
                <input type="hidden" name="action" value="connecter">
                
                <div class="form-group">
                    <label for="login_connexion">Email (Login) :</label>
                    <input type="email" id="login_connexion" name="login" required>
                </div>
                
                <div class="form-group">
                    <label for="password_connexion">Mot de passe :</label>
                    <input type="password" id="password_connexion" name="mot_de_passe" required>
                </div>
                
                <button type="submit">🚀 Se connecter</button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Tests rapides -->
        <div class="form-section">
            <h2>⚡ Tests rapides</h2>
            <p>Utilisez ces boutons pour tester rapidement différents scénarios :</p>
            
            <div class="test-buttons">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="creer_compte">
                    <input type="hidden" name="login" value="test@example.com">
                    <input type="hidden" name="mot_de_passe" value="password123">
                    <button type="submit" class="secondary">👤 Créer compte test</button>
                </form>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="connecter">
                    <input type="hidden" name="login" value="test@example.com">
                    <input type="hidden" name="mot_de_passe" value="password123">
                    <button type="submit" class="secondary">🔑 Connexion test</button>
                </form>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="connecter">
                    <input type="hidden" name="login" value="test@example.com">
                    <input type="hidden" name="mot_de_passe" value="mauvais_password">
                    <button type="submit" class="secondary">❌ Mauvais password</button>
                </form>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="connecter">
                    <input type="hidden" name="login" value="admin_bloque">
                    <input type="hidden" name="mot_de_passe" value="password123">
                    <button type="submit" class="secondary">🚫 Test hook blocage</button>
                </form>
            </div>
        </div>
        
        <!-- Journalisation -->
        <div class="form-section">
            <h2>📝 Journaux</h2>
            
            <h3>Tentatives de connexion échouées :</h3>
            <div class="log-content">
                <?php
                if (file_exists('tentatives_echec.log')) {
                    echo htmlspecialchars(file_get_contents('tentatives_echec.log'));
                } else {
                    echo "Aucune tentative échouée enregistrée.";
                }
                ?>
            </div>
            
            <h3>Dernières connexions réussies :</h3>
            <div class="log-content">
                <?php
                if (file_exists('dernieres_connexions.log')) {
                    echo htmlspecialchars(file_get_contents('dernieres_connexions.log'));
                } else {
                    echo "Aucune connexion réussie enregistrée.";
                }
                ?>
            </div>
        </div>
        
        <!-- Analyse du schéma de table -->
        <div class="form-section">
            <h2>🔍 Analyse du schéma de la table</h2>
            
            <h3>Champs obligatoires détectés :</h3>
            <div class="log-content">
                <?php 
                $champsObligatoires = $auth->obtenirChampsObligatoires();
                if (empty($champsObligatoires)): ?>
Aucun champ obligatoire supplémentaire détecté.
                <?php else: ?>
                    <?php foreach ($champsObligatoires as $champ): ?>
- <?= htmlspecialchars($champ) ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <h3>Schéma complet de la table :</h3>
            <div class="log-content">
                <?php 
                $schema = $auth->obtenirSchemaTable();
                if (empty($schema)): ?>
Aucun schéma disponible (table peut-être inexistante).
                <?php else: ?>
                    <?php foreach ($schema as $colonne => $info): ?>
<?= htmlspecialchars($colonne) ?>:
  - Type: <?= htmlspecialchars($info['type']) ?>
  - Null: <?= $info['null'] ? 'Oui' : 'Non' ?>
  - Défaut: <?= $info['default'] ? htmlspecialchars($info['default']) : 'Aucun' ?>
  - Extra: <?= htmlspecialchars($info['extra']) ?>

                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informations de test -->
        <div class="form-section">
            <h2>ℹ️ Informations pour les tests</h2>
            <ul>
                <li><strong>Analyse automatique :</strong> La classe analyse le schéma de la table existante</li>
                <li><strong>Champs obligatoires :</strong> Détecte automatiquement les champs NOT NULL sans valeur par défaut</li>
                <li><strong>Colonnes timestamp :</strong> Ajoute automatiquement created_at et updated_at si absentes</li>
                <li><strong>Configuration BDD :</strong> La classe gère automatiquement la connexion PDO</li>
                <li><strong>SGBD supportés :</strong> MySQL, SQLite, PostgreSQL</li>
                <li><strong>Système de blocage :</strong> 5 tentatives échouées en 15 minutes bloquent l'IP</li>
                <li><strong>Hook avant connexion :</strong> Le login "admin_bloque" est automatiquement refusé</li>
                <li><strong>Hook après connexion :</strong> Chaque connexion réussie est enregistrée dans le log</li>
                <li><strong>Validation :</strong> Le mot de passe doit faire au moins 6 caractères</li>
                <li><strong>Sécurité :</strong> Les mots de passe sont hashés avec password_hash()</li>
                <li><strong>Table :</strong> Utilise la table "mes_utilisateurs" avec colonnes "email" et "password"</li>
            </ul>
            
            <h3>Configuration de base de données utilisée :</h3>
            <div class="log-content">
Host: <?= $configBdd['host'] ?? $configBddSqlite['sgbd'] ?>
Database: <?= $configBdd['database'] ?? $configBddSqlite['database'] ?>
SGBD: <?= $configBdd['sgbd'] ?? $configBddSqlite['sgbd'] ?>
Port: <?= $configBdd['port'] ?? 'N/A' ?>
Charset: <?= $configBdd['charset'] ?? 'N/A' ?>
            </div>
        </div>
        
        <!-- Refresh button -->
        <div style="text-align: center; margin-top: 30px;">
            <button onclick="window.location.reload()" class="secondary">🔄 Actualiser la page</button>
        </div>
    </div>
</body>
</html>