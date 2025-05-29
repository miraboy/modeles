<?php

// Exemple d'utilisation de la classe Validateur

require_once './modele/Validateur.php';

// ==================== EXEMPLE BASIQUE ====================

$validateur = new Validateur();

// Données à valider
$donnees = [
    'nom' => 'Jean Dupont',
    'email' => 'jean.dupontexample.com',
    'telephone' => '0123456789',
    'age' => '25',
    'website' => 'https://example.com'
];

// $validateur->setDonnees($donnees);

// // Validation avec détection automatique des règles
// if ($validateur->valider()) {
//     echo "✅ Validation réussie !\n";
// } else {
//     echo "❌ Erreurs de validation :\n";
//     foreach ($validateur->getTousLesMessages() as $message) {
//         echo "- $message\n";
//     }
// }
die;
// ==================== EXEMPLE AVEC RÈGLES SPÉCIFIQUES ====================

$validateur2 = new Validateur();

$donneesFormulaire = [
    'nom' => '',
    'email' => 'email-invalide',
    'mot_de_passe' => '123',
    'age' => 'abc'
];

// Définir des règles spécifiques
$regles = [
    'nom' => ['required'],
    'email' => ['required', 'email'],
    'mot_de_passe' => ['required', 'min:6'],
    'age' => ['required', 'numeric', 'min:18']
];

// Messages personnalisés
$messages = [
    'nom.required' => 'Le nom est obligatoire.',
    'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
    'age.min' => 'Vous devez avoir au moins 18 ans.'
];

$validateur2
    ->setDonnees($donneesFormulaire)
    ->setRegles($regles)
    ->setMessagesPersonnalises($messages);

if (!$validateur2->valider()) {
    echo "\n==================== ERREURS DÉTAILLÉES ====================\n";
    foreach ($validateur2->getErreurs() as $champ => $erreurs) {
        echo "\nChamp '$champ' :\n";
        foreach ($erreurs as $erreur) {
            echo "  [{$erreur['niveau']}] {$erreur['message']} (à {$erreur['timestamp']})\n";
        }
    }
}

// ==================== EXEMPLE AVEC RÈGLE PERSONNALISÉE ====================

$validateur3 = new Validateur();

// Ajouter une règle personnalisée pour vérifier si un mot de passe est fort
$validateur3->ajouterRegles('motDePasseFort', function($valeur, $parametre = null) {
    if (empty($valeur)) return true;
    
    // Vérifier: au moins 8 caractères, une majuscule, une minuscule, un chiffre
    return strlen($valeur) >= 8 
           && preg_match('/[A-Z]/', $valeur) 
           && preg_match('/[a-z]/', $valeur) 
           && preg_match('/[0-9]/', $valeur);
});

// Ajouter une règle pour vérifier l'âge majeur français
$validateur3->ajouterRegles('majeurFr', function($valeur, $parametre = null) {
    return is_numeric($valeur) && (int)$valeur >= 18;
});

$donneesAvecReglesPersonnalisees = [
    'email' => 'test@example.com',
    'mot_de_passe' => 'MonMotDePasse123',
    'age' => '20'
];

$reglesPersonnalisees = [
    'email' => ['required', 'email'],
    'mot_de_passe' => ['required', 'motDePasseFort'],
    'age' => ['required', 'majeurFr']
];

$messagesPersonnalises = [
    'motDePasseFort' => 'Le mot de passe doit contenir au moins 8 caractères avec majuscules, minuscules et chiffres.',
    'majeurFr' => 'Vous devez être majeur (18 ans minimum).'
];

$validateur3
    ->setDonnees($donneesAvecReglesPersonnalisees)
    ->setRegles($reglesPersonnalisees)
    ->setMessagesPersonnalises($messagesPersonnalises);

if ($validateur3->valider()) {
    echo "\n✅ Validation avec règles personnalisées réussie !\n";
} else {
    echo "\n❌ Erreurs avec règles personnalisées :\n";
    foreach ($validateur3->getTousLesMessages() as $message) {
        echo "- $message\n";
    }
}

// ==================== EXEMPLE DE COMBINAISON DE RÈGLES ====================

$validateur4 = new Validateur();

$donneesComplexes = [
    'code_postal' => '75001',
    'telephone_mobile' => '+33123456789'
];

$validateur4->setDonnees($donneesComplexes);

// Combiner plusieurs règles pour le code postal
$codePostalValide = $validateur4->combinerRegles('code_postal', [
    'required',
    'numeric',
    'regex:/^[0-9]{5}$/'
]);

// Combiner règles pour le téléphone
$telephoneValide = $validateur4->combinerRegles('telephone_mobile', [
    'required',
    'tel'
]);

if ($codePostalValide && $telephoneValide) {
    echo "\n✅ Validation combinée réussie !\n";
} else {
    echo "\n❌ Erreurs de validation combinée :\n";
    foreach ($validateur4->getTousLesMessages() as $message) {
        echo "- $message\n";
    }
}

// ==================== EXEMPLE D'UTILISATION FLUIDE ====================

$resultat = (new Validateur())
    ->setDonnees([
        'nom' => 'Alice',
        'email' => 'alice@example.com',
        'age' => '25'
    ])
    ->setRegles([
        'nom' => 'required|alpha',
        'email' => 'required|email',
        'age' => 'required|numeric|min:18'
    ])
    ->valider();

echo $resultat ? "\n✅ Validation fluide réussie !" : "\n❌ Validation fluide échouée !";

// ==================== AFFICHAGE DES INFORMATIONS DE DEBUG ====================

$validateur5 = new Validateur();
$validateur5->setDonnees(['test_email' => 'invalide']);

echo "\n\n==================== DEBUG INFO ====================\n";
echo "Données: " . json_encode($validateur5->getDonnees()) . "\n";
echo "Règles détectées automatiquement: " . json_encode($validateur5->detecterRegles()) . "\n";

if (!$validateur5->valider()) {
    echo "Erreurs détectées: " . count($validateur5->getTousLesMessages()) . "\n";
    echo "A échoué: " . ($validateur5->aEchoue() ? 'Oui' : 'Non') . "\n";
}