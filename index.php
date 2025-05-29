<?php

// Exemple d'utilisation de la classe Validateur

require_once './modele/Validateur.php';

$validateur= new Validateur();
$data=[
    'nom'=> '',
    'email'=>"totogmail.vom",
    'age'=>20
];

$validateur->setDonnees($data);

$re = [
    'nom' => ['required', 'alpha'],
    'email' => ['required', 'email'],
    'age' => ['required', 'numeric', 'min:18']
];

$msg = ['age.min'=>"l'age doit être 18 ans au moins"];

$validateur->setRegles((array)$re);

$validateur->setMessagesPersonnalises($msg);
if($validateur->valider()){
    echo " c'est valide";
}else{
    foreach ($validateur->getTousLesMessages() as $message) {
        echo $message . "\n";
    }
}
