<?php

/**
 * Classe générique pour la validation des données
 * Supporte les règles natives, personnalisées et la détection automatique
 */
class Validateur
{
    // Constantes pour les niveaux d'erreur
    const NIVEAU_INFO = 'info';
    const NIVEAU_WARNING = 'warning';
    const NIVEAU_ERREUR = 'erreur';

    // Attributs principaux
    protected array $donnees = [];
    protected array $regles = [];
    protected array $erreurs = [];
    protected array $messagesPersonnalises = [];
    
    // Règles personnalisées (statiques pour accès global)
    protected static array $reglesPersonnalisees = [];
    
    // Fichier pour stocker les nouvelles règles
    protected string $fichierRegles = 'newRegles.php';

    /**
     * Messages par défaut pour chaque règle
     */
    protected array $messagesDefaut = [
        'required' => 'Le champ :champ est obligatoire.',
        'email' => 'Le champ :champ doit être une adresse email valide.',
        'tel' => 'Le champ :champ doit être un numéro de téléphone valide.',
        'url' => 'Le champ :champ doit être une URL valide.',
        'numeric' => 'Le champ :champ doit être numérique.',
        'integer' => 'Le champ :champ doit être un entier.',
        'min' => 'Le champ :champ doit avoir au minimum :valeur caractères.',
        'max' => 'Le champ :champ doit avoir au maximum :valeur caractères.',
        'regex' => 'Le champ :champ ne respecte pas le format requis.',
        'date' => 'Le champ :champ doit être une date valide.',
        'alpha' => 'Le champ :champ ne doit contenir que des lettres.',
        'alphanumeric' => 'Le champ :champ ne doit contenir que des lettres et des chiffres.',
    ];

    /**
     * Constructeur du validateur.
     */
    public function __construct()
    {
        $this->chargerReglesPersonnalisees();
    }

    // ==================== SETTERS ====================

    /**
     * Définit les données à valider.
     * @param array $donnees
     * @return self
     */
    public function setDonnees(array $donnees): self
    {
        $this->donnees = $donnees;
        return $this;
    }

    /**
     * Définit les règles de validation.
     * @param array $regles
     * @return self
     */
    public function setRegles(array $regles): self
    {
        $this->regles = $regles;
        return $this;
    }

    /**
     * Définit les messages personnalisés.
     * @param array $messages
     * @return self
     */
    public function setMessagesPersonnalises(array $messages): self
    {
        $this->messagesPersonnalises = array_merge($this->messagesPersonnalises, $messages);
        return $this;
    }

    // ==================== GETTERS ====================

    /**
     * Retourne les données à valider.
     * @return array
     */
    public function getDonnees(): array
    {
        return $this->donnees;
    }

    /**
     * Retourne les règles de validation.
     * @return array
     */
    public function getRegles(): array
    {
        return $this->regles;
    }

    /**
     * Retourne les messages personnalisés.
     * @return array
     */
    public function getMessagesPersonnalises(): array
    {
        return $this->messagesPersonnalises;
    }

    /**
     * Retourne toutes les erreurs de validation.
     * @return array
     */
    public function getErreurs(): array
    {
        return $this->erreurs;
    }

    /**
     * Retourne les erreurs pour un champ donné.
     * @param string $champ
     * @return array
     */
    public function getErreursParChamp(string $champ): array
    {
        return $this->erreurs[$champ] ?? [];
    }

    // ==================== VALIDATION PRINCIPALE ====================

    /**
     * Lance la validation des données selon les règles.
     * @param array $regles Règles à utiliser (optionnel)
     * @return bool True si aucune erreur, false sinon
     */
    public function valider(array $regles = []): bool
    {
        $this->erreurs = []; // Reset des erreurs
        
        // Utiliser les règles passées en paramètre, sinon celles de l'objet, sinon détection auto
        $reglesAUtiliser = !empty($regles) ? $regles : 
                          (!empty($this->regles) ? $this->regles : $this->detecterRegles());

        foreach ($reglesAUtiliser as $champ => $reglesChamp) {
            $this->validerChamp($champ, $reglesChamp);
        }

        return empty($this->erreurs);
    }

    /**
     * Valide un champ spécifique avec ses règles
     */
    protected function validerChamp(string $champ, $reglesChamp): void
    {
        $valeur = $this->donnees[$champ] ?? null;
        $regles = is_string($reglesChamp) ? explode('|', $reglesChamp) : $reglesChamp;

        foreach ($regles as $regle) {
            if (!$this->appliquerRegle($champ, $regle, $valeur)) {
                break; // Arrêter si une règle échoue (optionnel)
            }
        }
    }

    /**
     * Applique une règle spécifique à un champ
     */
    protected function appliquerRegle(string $champ, string $regle, $valeur): bool
    {
        // Parsing de la règle (ex: min:5, regex:/pattern/)
        $parametres = explode(':', $regle, 2);
        $nomRegle = $parametres[0];
        $parametre = $parametres[1] ?? null;

        // Vérifier si c'est une règle personnalisée
        if (isset(self::$reglesPersonnalisees[$nomRegle])) {
            return $this->validerNewRegle($champ, $nomRegle, $valeur, $parametre);
        }

        // Appliquer les règles natives
        $methodeName = 'valider' . ucfirst($nomRegle);
        if (method_exists($this, $methodeName)) {
            return $this->$methodeName($champ, $valeur, $parametre);
        }

        // Règle inconnue
        $this->ajouterErreur($champ, self::NIVEAU_WARNING, "Règle inconnue: $nomRegle");
        return false;
    }

    /**
     * Détecte automatiquement les règles à partir des données
     */
    public function detecterRegles(): array
    {
        $reglesDetectees = [];

        foreach ($this->donnees as $champ => $valeur) {
            $regles = [];

            // Détection basée sur le nom du champ
            if (strpos($champ, 'email') !== false) {
                $regles[] = 'email';
            }
            if (strpos($champ, 'tel') !== false || strpos($champ, 'phone') !== false) {
                $regles[] = 'tel';
            }
            if (strpos($champ, 'url') !== false || strpos($champ, 'website') !== false) {
                $regles[] = 'url';
            }
            if (strpos($champ, 'date') !== false) {
                $regles[] = 'date';
            }

            // Détection basée sur la valeur
            if (is_numeric($valeur)) {
                $regles[] = 'numeric';
            }

            // Si aucune règle détectée, appliquer 'required' par défaut
            if (empty($regles)) {
                $regles[] = 'required';
            }

            $reglesDetectees[$champ] = $regles;
        }

        return $reglesDetectees;
    }

    // ==================== RÈGLES DE VALIDATION NATIVES ====================

    protected function validerRequired(string $champ, $valeur, $parametre = null): bool
    {
        $valide = !empty($valeur) || $valeur === '0' || $valeur === 0;
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('required', $champ));
        }
        return $valide;
    }

    protected function validerEmail(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true; // Skip si vide (sauf si required)
        
        $valide = filter_var($valeur, FILTER_VALIDATE_EMAIL) !== false;
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('email', $champ));
        }
        return $valide;
    }

    protected function validerTel(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        // Pattern pour numéros français et internationaux
        $pattern = '/^(\+33|0)[1-9](\d{8})$|^\+\d{1,3}\d{4,14}$/';
        $valide = preg_match($pattern, str_replace([' ', '-', '.'], '', $valeur));
        
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('tel', $champ));
        }
        return $valide;
    }

    protected function validerUrl(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        $valide = filter_var($valeur, FILTER_VALIDATE_URL) !== false;
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('url', $champ));
        }
        return $valide;
    }

    protected function validerNumeric(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        $valide = is_numeric($valeur);
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('numeric', $champ));
        }
        return $valide;
    }

    protected function validerInteger(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        $valide = filter_var($valeur, FILTER_VALIDATE_INT) !== false;
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('integer', $champ));
        }
        return $valide;
    }

    protected function validerMin(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        $longueur = is_string($valeur) ? strlen($valeur) : $valeur;
        $valide = $longueur >= (int)$parametre;
        
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('min', $champ, ['valeur' => $parametre]));
        }
        return $valide;
    }

    protected function validerMax(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        $longueur = is_string($valeur) ? strlen($valeur) : $valeur;
        $valide = $longueur <= (int)$parametre;
        
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('max', $champ, ['valeur' => $parametre]));
        }
        return $valide;
    }

    protected function validerRegex(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        $valide = preg_match($parametre, $valeur);
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('regex', $champ));
        }
        return $valide;
    }

    protected function validerDate(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        $format = $parametre ?? 'Y-m-d';
        $date = DateTime::createFromFormat($format, $valeur);
        $valide = $date && $date->format($format) === $valeur;
        
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('date', $champ));
        }
        return $valide;
    }

    protected function validerAlpha(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        $valide = ctype_alpha($valeur);
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('alpha', $champ));
        }
        return $valide;
    }

    protected function validerAlphanumeric(string $champ, $valeur, $parametre = null): bool
    {
        if (empty($valeur)) return true;
        
        $valide = ctype_alnum($valeur);
        if (!$valide) {
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, 
                $this->getMessage('alphanumeric', $champ));
        }
        return $valide;
    }

    // ==================== GESTION DES ERREURS ====================

    /**
     * Ajoute une erreur pour un champ.
     * @param string $champ
     * @param string $niveau
     * @param string $message
     * @return void
     */
    public function ajouterErreur(string $champ, string $niveau, string $message): void
    {
        if (!isset($this->erreurs[$champ])) {
            $this->erreurs[$champ] = [];
        }

        $this->erreurs[$champ][] = [
            'niveau' => $niveau,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'datetime' => new DateTime()
        ];
    }

    /**
     * Récupère le message d'erreur approprié
     */
    protected function getMessage(string $regle, string $champ, array $parametres = []): string
    {
        // Message personnalisé spécifique au champ
        $cle = $champ . '.' . $regle;
        if (isset($this->messagesPersonnalises[$cle])) {
            $message = $this->messagesPersonnalises[$cle];
        }
        // Message personnalisé pour la règle
        elseif (isset($this->messagesPersonnalises[$regle])) {
            $message = $this->messagesPersonnalises[$regle];
        }
        // Message par défaut
        else {
            $message = $this->messagesDefaut[$regle] ?? 'Erreur de validation pour le champ :champ.';
        }

        // Remplacer les placeholders
        $message = str_replace(':champ', $champ, $message);
        foreach ($parametres as $cle => $valeur) {
            $message = str_replace(':' . $cle, $valeur, $message);
        }

        return $message;
    }

    // ==================== RÈGLES PERSONNALISÉES ====================

    /**
     * Ajoute une règle personnalisée.
     * @param string $titre Nom de la règle
     * @param callable $fonction Fonction de validation
     * @return bool
     */
    public function ajouterRegles(string $titre, callable $fonction): bool
    {
        try {
            self::$reglesPersonnalisees[$titre] = $fonction;
            $this->sauvegarderReglesPersonnalisees();
            return true;
        } catch (Exception $e) {
            $this->ajouterErreur('system', self::NIVEAU_WARNING, 
                "Impossible d'ajouter la règle $titre: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Valide un champ avec une règle personnalisée.
     * @param string $champ
     * @param string $titre
     * @param mixed $valeur
     * @param mixed $parametre
     * @return bool
     */
    public function validerNewRegle(string $champ, string $titre, $valeur, $parametre = null): bool
    {
        if (!isset(self::$reglesPersonnalisees[$titre])) {
            $this->ajouterErreur($champ, self::NIVEAU_WARNING, "Règle personnalisée '$titre' introuvable.");
            return false;
        }

        $fonction = self::$reglesPersonnalisees[$titre];
        $resultat = $fonction($valeur, $parametre);

        if (!$resultat) {
            $message = $this->getMessage($titre, $champ, ['valeur' => $parametre]);
            $this->ajouterErreur($champ, self::NIVEAU_ERREUR, $message);
        }

        return $resultat;
    }

    /**
     * Combine plusieurs règles pour un champ.
     * @param string $champ
     * @param array $regles
     * @return bool
     */
    public function combinerRegles(string $champ, array $regles): bool
    {
        $valeur = $this->donnees[$champ] ?? null;
        $toutesValides = true;

        foreach ($regles as $regle) {
            if (!$this->appliquerRegle($champ, $regle, $valeur)) {
                $toutesValides = false;
            }
        }

        return $toutesValides;
    }

    /**
     * Charge les règles personnalisées depuis un fichier.
     * @return void
     */
    public function chargerReglesPersonnalisees(): void
    {
        if (file_exists($this->fichierRegles)) {
            $regles = include $this->fichierRegles;
            if (is_array($regles)) {
                foreach ($regles as $nom => $fonction) {
                    if (is_callable($fonction)) {
                        self::$reglesPersonnalisees[$nom] = $fonction;
                    } else {
                        $this->ajouterErreur('system', self::NIVEAU_WARNING, "La règle personnalisée '$nom' est invalide.");
                    }
                }
            }
        }
    }

    /**
     * Sauvegarde les règles personnalisées dans le fichier défini.
     * @return void
     */
    protected function sauvegarderReglesPersonnalisees(): void
    {
        $contenu = "<?php\n\nreturn [\n";
        foreach (self::$reglesPersonnalisees as $nom => $fonction) {
            if (is_string($fonction)) {
                // Si la fonction est une string (nom de fonction globale)
                $contenu .= "    '$nom' => '$fonction',\n";
            } elseif ($fonction instanceof \Closure) {
                // Les closures ne peuvent pas être exportées directement
                $contenu .= "    // '$nom' => function() { /* Closure non exportable */ },\n";
            } else {
                // Autre type de callable
                $contenu .= "    // '$nom' => /* Callable non exportable */,\n";
            }
        }
        $contenu .= "];\n";
        file_put_contents($this->fichierRegles, $contenu);
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Indique si la validation a échoué.
     * @return bool
     */
    public function aEchoue(): bool
    {
        return !empty($this->erreurs);
    }

    /**
     * Retourne le premier message d'erreur pour un champ.
     * @param string $champ
     * @return string|null
     */
    public function premierMessageErreur(string $champ): ?string
    {
        return $this->erreurs[$champ][0]['message'] ?? null;
    }

    /**
     * Retourne tous les messages d'erreur sous forme de tableau plat.
     * @return array
     */
    public function getTousLesMessages(): array
    {
        $messages = [];
        foreach ($this->erreurs as $champ => $erreursChamp) {
            foreach ($erreursChamp as $erreur) {
                $messages[] = $erreur['message'];
            }
        }
        return $messages;
    }

    /**
     * Efface toutes les erreurs de validation.
     * @return void
     */
    public function effacerErreurs(): void
    {
        $this->erreurs = [];
    }

    /**
     * Définit le fichier pour les règles personnalisées.
     * @param string $chemin
     * @return void
     */
    public function setFichierRegles(string $chemin): void
    {
        $this->fichierRegles = $chemin;
    }
}