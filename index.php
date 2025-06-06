<?php

require_once 'classes/Authentificateur.php';

// Instanciation de l'authentificateur avec deux tableaux de configuration
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

// Liste des fonctionnalités à tester
$etapes = [
    1 => 'Créer un compte',
    2 => 'Connexion avec bon mot de passe',
    3 => 'Connexion avec mauvais mot de passe',
    4 => 'Déconnexion',
    5 => 'Vérifier utilisateur connecté',
    6 => 'Afficher erreurs',
    7 => 'Afficher schéma table',
    8 => 'Afficher champs obligatoires'
];

$etape = isset($_POST['etape']) ? (int)$_POST['etape'] : 1;
$maxEtape = count($etapes);

// Traitement des actions
$message = '';
if (isset($_POST['action'])) {
     var_dump($_POST);
    switch ($_POST['action']) {
        
        case 'creer_compte':
            if ($auth->creerCompte($_POST['login'], $_POST['mot_de_passe'],["nom"=> "Test", "age" => 10])) {
                $message = "✅ Compte créé avec succès.";
            } else {

                $message = "❌ Erreur lors de la création du compte.";
            }
            break;
        case 'connecter':
            if ($auth->connecter($_POST['login'], $_POST['mot_de_passe'])) {
                $message = "✅ Connexion réussie.";
            } else {
               
                $auth->obtenirErreurs(); // Pour capturer les erreurs éventuelles
                $message = "❌ Connexion échouée.";
            }
            break;
        case 'deconnecter':
            $auth->deconnecter();
            $message = "✅ Déconnexion effectuée.";
            break;
    }
}

echo '<div style="background:#e9ecef;padding:20px;margin:20px 0;border-radius:8px">';
echo "<h2>🧪 Test pas à pas des fonctionnalités Authentificateur</h2>";
echo "<strong>Étape $etape/$maxEtape : {$etapes[$etape]}</strong><br><br>";
if ($message) {
    echo '<div style="margin-bottom:10px;font-weight:bold;">' . htmlspecialchars($message) . '</div>';
}

// Affichage des erreurs à chaque étape
$erreurs = $auth->obtenirErreurs();
if (!empty($erreurs)) {
    echo '<div style="background:#fff3cd;color:#856404;padding:10px;border-radius:5px;margin-bottom:10px;">';
    echo '<strong>Erreurs détectées :</strong><br>';
    echo nl2br(htmlspecialchars(implode("\n", $erreurs)));
    echo '</div>';
}

switch ($etape) {
    case 1: // Créer un compte
        echo '<form method="post">
            <input type="texte" name="etape" value="2">
            <input type="texte" name="action" value="creer_compte">
            <label>Email : <input type="email" name="login" value="test@example.com" required></label><br>
            <label>Mot de passe : <input type="password" name="mot_de_passe" value="password123" required></label><br>
            <button type="submit">Créer le compte test@example.com</button>
        </form>';
        break;

    case 2: // Connexion avec bon mot de passe
        echo '<form method="post">
            <input type="texte" name="etape" value="3">
            <input type="texte" name="action" value="connecter">
            <label>Email : <input type="email" name="login" value="test@example.com" required></label><br>
            <label>Mot de passe : <input type="password" name="mot_de_passe" value="password123" required></label><br>
            <button type="submit">Connexion (mot de passe correct)</button>
        </form>';
        break;

    case 3: // Connexion avec mauvais mot de passe
        echo '<form method="post">
            <input type="texte" name="etape" value="4">
            <input type="texte" name="action" value="connecter">
            <label>Email : <input type="email" name="login" value="test@example.com" required></label><br>
            <label>Mot de passe : <input type="password" name="mot_de_passe" value="mauvaispass" required></label><br>
            <button type="submit">Connexion (mauvais mot de passe)</button>
        </form>';
        break;

    case 4: // Déconnexion
        echo '<form method="post">
            <input type="texte" name="etape" value="5">
            <input type="texte" name="action" value="deconnecter">
            <button type="submit">Déconnexion</button>
        </form>';
        break;

    case 5: // Vérifier utilisateur connecté
        if ($auth->estConnecte()) {
            echo '<div style="color:green;">Utilisateur connecté : ' . htmlspecialchars($auth->utilisateurConnecte()['email']) . '</div>';
        } else {
            echo '<div style="color:orange;">Aucun utilisateur connecté.</div>';
        }
        echo '<form method="post">
            <input type="texte" name="etape" value="6">
            <button type="submit">Suivant</button>
        </form>';
        break;

    case 6: // Afficher erreurs
        // Les erreurs sont déjà affichées au-dessus
        echo '<form method="post">
            <input type="texte" name="etape" value="7">
            <button type="submit">Suivant</button>
        </form>';
        break;

    case 7: // Afficher schéma table
        $schema = $auth->obtenirSchemaTable();
        if (!empty($schema)) {
            echo '<pre style="background:#fff;padding:10px;border-radius:5px;">';
            foreach ($schema as $col => $info) {
                echo htmlspecialchars($col) . ': ' . htmlspecialchars(json_encode($info)) . "\n";
            }
            echo '</pre>';
        } else {
            echo '<div style="color:red;">Aucun schéma disponible.</div>';
        }
        echo '<form method="post">
            <input type="texte" name="etape" value="8">
            <button type="submit">Suivant</button>
        </form>';
        break;

    case 8: // Afficher champs obligatoires
        $champs = $auth->obtenirChampsObligatoires();
        if (!empty($champs)) {
            echo '<div style="background:#fff;padding:10px;border-radius:5px;">' . htmlspecialchars(implode(", ", $champs)) . '</div>';
        } else {
            echo '<div style="color:blue;">Aucun champ obligatoire supplémentaire.</div>';
        }
        echo '<form method="post">
            <input type="texte" name="etape" value="1">
            <button type="submit">Recommencer</button>
        </form>';
        break;
}

echo '</div>';