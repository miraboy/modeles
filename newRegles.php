<?php

return [
    'motDePasseFort' => function($valeur, $parametre = null) {
        if (empty($valeur)) return true;
        
        // Vérifier: au moins 8 caractères, une majuscule, une minuscule, un chiffre
        return strlen($valeur) >= 8 
            && preg_match('/[A-Z]/', $valeur) 
            && preg_match('/[a-z]/', $valeur) 
            && preg_match('/[0-9]/', $valeur);
    },
        'majeurFr' => function($valeur, $parametre = null) {
        return is_numeric($valeur) && (int)$valeur >= 18;
    },
];
