<?php

/**
 * Exception personnalisée pour le GestionnaireBD
 */
class GestionnaireBDException extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Gestionnaire de base de données avec fonctionnalités avancées
 */
class GestionnaireBD
{
    // Informations de connexion (pas de getters/setters)
    private $host;
    private $user;
    private $pass;
    private $baseDonnees;
    
    // Attributs principaux
    private $table;
    private $pdo;
    private $structure;
    private $erreurs;
    private $cheminJournal;
    
    // Cache pour optimiser les performances
    private static $cacheStructure = [];
    
    /**
     * Types de données MySQL vers PHP
     */
    private $typesMapping = [
        'int' => 'integer',
        'tinyint' => 'integer',
        'smallint' => 'integer',
        'mediumint' => 'integer',
        'bigint' => 'integer',
        'float' => 'double',
        'double' => 'double',
        'decimal' => 'double',
        'varchar' => 'string',
        'char' => 'string',
        'text' => 'string',
        'longtext' => 'string',
        'mediumtext' => 'string',
        'date' => 'string',
        'datetime' => 'string',
        'timestamp' => 'string',
        'time' => 'string',
        'year' => 'integer',
        'boolean' => 'boolean',
        'bool' => 'boolean'
    ];
    
    /**
     * Constructeur
     */
    public function __construct($host, $user, $pass, $baseDonnees, $table, $cheminJournal = './logs/gestionnaireBD.log')
    {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->baseDonnees = $baseDonnees;
        $this->table = $table;
        $this->erreurs = [];
        $this->cheminJournal = $cheminJournal;
        
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->baseDonnees};charset=utf8",
                $this->user,
                $this->pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            $this->chargerStructure();
            $this->journaliser('INFO', 'Connexion', "Connexion établie à la base de données {$this->baseDonnees}, table {$this->table}");
            
        } catch (PDOException $e) {
            $message = "Erreur de connexion à la base de données : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Connexion', $message);
            throw new GestionnaireBDException($message);
        }
    }
    
    // GETTERS
    public function getTable() { return $this->table; }
    public function getErreurs() { return $this->erreurs; }
    public function getCheminJournal() { return $this->cheminJournal; }
    public function getStructure() { return $this->structure; }
    
    // SETTERS
    public function setTable($table)
    {
        $this->table = $table;
        $this->chargerStructure();
        $this->journaliser('INFO', 'Configuration', "Table changée pour : {$table}");
    }
    
    public function setCheminJournal($chemin)
    {
        $this->cheminJournal = $chemin;
    }
    
    /**
     * Ajouter une erreur au tableau d'erreurs
     */
    private function ajouterErreur($message)
    {
        $this->erreurs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
    }
    
    /**
     * Charger la structure de la table avec cache
     */
    private function chargerStructure()
    {
        $cacheKey = $this->baseDonnees . '.' . $this->table;
        
        if (isset(self::$cacheStructure[$cacheKey])) {
            $this->structure = self::$cacheStructure[$cacheKey];
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("DESCRIBE `{$this->table}`");
            $stmt->execute();
            $this->structure = $stmt->fetchAll();
            
            // Mise en cache
            self::$cacheStructure[$cacheKey] = $this->structure;
            
        } catch (PDOException $e) {
            $message = "Erreur lors du chargement de la structure de la table : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Structure', $message);
            throw new GestionnaireBDException($message);
        }
    }
    
    /**
     * Vérifier si une colonne existe dans la table
     */
    public function estColonne($col)
    {
        foreach ($this->structure as $colonne) {
            if ($colonne['Field'] === $col) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Obtenir le type d'une colonne
     */
    private function getTypeColonne($col)
    {
        foreach ($this->structure as $colonne) {
            if ($colonne['Field'] === $col) {
                $type = strtolower($colonne['Type']);
                // Extraire le type de base (sans la taille)
                if (preg_match('/^([a-z]+)/', $type, $matches)) {
                    return $matches[1];
                }
                return $type;
            }
        }
        return null;
    }
    
    /**
     * Valider les données selon la structure de la table
     */
    public function validerDonnees($donnees)
    {
        $erreurs = [];
        
        foreach ($donnees as $colonne => $valeur) {
            // Vérifier si la colonne existe
            if (!$this->estColonne($colonne)) {
                $erreurs[] = "La colonne '{$colonne}' n'existe pas dans la table";
                continue;
            }
            
            // Obtenir les informations de la colonne
            $infoColonne = null;
            foreach ($this->structure as $col) {
                if ($col['Field'] === $colonne) {
                    $infoColonne = $col;
                    break;
                }
            }
            
            if (!$infoColonne) continue;
            
            // Vérifier si la valeur peut être NULL
            if ($valeur === null && $infoColonne['Null'] === 'NO' && $infoColonne['Default'] === null) {
                $erreurs[] = "La colonne '{$colonne}' ne peut pas être NULL";
                continue;
            }
            
            if ($valeur !== null) {
                // Valider le type de données
                $typeColonne = $this->getTypeColonne($colonne);
                $typeAttendu = isset($this->typesMapping[$typeColonne]) ? $this->typesMapping[$typeColonne] : 'string';
                
                if (!$this->validerType($valeur, $typeAttendu, $typeColonne)) {
                    $erreurs[] = "La valeur pour '{$colonne}' n'est pas du bon type (attendu: {$typeAttendu})";
                }
                
                // Valider la longueur pour les chaînes
                if (in_array($typeColonne, ['varchar', 'char'])) {
                    preg_match('/\((\d+)\)/', $infoColonne['Type'], $matches);
                    if (isset($matches[1])) {
                        $longueurMax = (int)$matches[1];
                        if (strlen($valeur) > $longueurMax) {
                            $erreurs[] = "La valeur pour '{$colonne}' dépasse la longueur maximale ({$longueurMax})";
                        }
                    }
                }
            }
        }
        
        if (!empty($erreurs)) {
            foreach ($erreurs as $erreur) {
                $this->ajouterErreur($erreur);
            }
            $this->journaliser('WARNING', 'Validation', 'Erreurs de validation des données : ' . implode(', ', $erreurs));
            return false;
        }
        
        return true;
    }
    
    /**
     * Valider le type d'une valeur
     */
    private function validerType($valeur, $typeAttendu, $typeColonne)
    {
        switch ($typeAttendu) {
            case 'integer':
                return is_numeric($valeur) && (int)$valeur == $valeur;
            case 'double':
                return is_numeric($valeur);
            case 'boolean':
                return is_bool($valeur) || in_array($valeur, [0, 1, '0', '1', true, false]);
            case 'string':
                return is_string($valeur) || is_numeric($valeur);
            default:
                return true;
        }
    }
    
    /**
     * Ajouter des données dans la table
     */
    public function ajouter($donnees)
    {
        try {
            if (!$this->validerDonnees($donnees)) {
                throw new GestionnaireBDException("Validation des données échouée");
            }
            
            $colonnes = implode(', ', array_keys($donnees));
            $placeholders = ':' . implode(', :', array_keys($donnees));
            
            $sql = "INSERT INTO `{$this->table}` ({$colonnes}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            
            $result = $stmt->execute($donnees);
            
            if ($result) {
                $this->journaliser('INFO', 'Ajout', "Enregistrement ajouté dans {$this->table}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $message = "Erreur lors de l'ajout : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Ajout', $message);
            return false;
        }
    }
    
    /**
     * Sélectionner un enregistrement
     */
    public function selectionnerUn($condition, $parametres = [])
    {
        try {
            if (!$this->validerRequete("SELECT * FROM {$this->table} WHERE {$condition}", $parametres)) {
                throw new GestionnaireBDException("Requête invalide");
            }
            
            $sql = "SELECT * FROM `{$this->table}` WHERE {$condition} LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametres);
            
            $result = $stmt->fetch();
            $this->journaliser('INFO', 'Selection', "Sélection d'un enregistrement dans {$this->table}");
            
            return $result ?: [];
            
        } catch (Exception $e) {
            $message = "Erreur lors de la sélection : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Selection', $message);
            return [];
        }
    }
    
    /**
     * Modifier des enregistrements
     */
    public function modifier($donnees, $condition, $parametresCondition = [])
    {
        try {
            if (!$this->validerDonnees($donnees)) {
                throw new GestionnaireBDException("Validation des données échouée");
            }
            
            $set = [];
            foreach (array_keys($donnees) as $colonne) {
                $set[] = "`{$colonne}` = :{$colonne}";
            }
            $setClause = implode(', ', $set);
            
            $sql = "UPDATE `{$this->table}` SET {$setClause} WHERE {$condition}";
            
            // Fusionner les paramètres
            $parametres = array_merge($donnees, $parametresCondition);
            
            if (!$this->validerRequete($sql, $parametres)) {
                throw new GestionnaireBDException("Requête invalide");
            }
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($parametres);
            
            if ($result) {
                $this->journaliser('INFO', 'Modification', "Enregistrement(s) modifié(s) dans {$this->table}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $message = "Erreur lors de la modification : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Modification', $message);
            return false;
        }
    }
    
    /**
     * Supprimer des enregistrements
     */
    public function supprimer($condition, $parametres = [])
    {
        try {
            $sql = "DELETE FROM `{$this->table}` WHERE {$condition}";
            
            if (!$this->validerRequete($sql, $parametres)) {
                throw new GestionnaireBDException("Requête invalide");
            }
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($parametres);
            
            if ($result) {
                $this->journaliser('INFO', 'Suppression', "Enregistrement(s) supprimé(s) dans {$this->table}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $message = "Erreur lors de la suppression : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Suppression', $message);
            return false;
        }
    }
    
    /**
     * Sélectionner tous les enregistrements
     */
    public function selectionnerTout()
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}`");
            $stmt->execute();
            
            $result = $stmt->fetchAll();
            $this->journaliser('INFO', 'Selection', "Sélection de tous les enregistrements de {$this->table}");
            
            return $result;
            
        } catch (Exception $e) {
            $message = "Erreur lors de la sélection : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Selection', $message);
            return [];
        }
    }
    
    /**
     * Compter les enregistrements
     */
    public function compter($condition = '', $parametres = [])
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM `{$this->table}`";
            if (!empty($condition)) {
                $sql .= " WHERE {$condition}";
            }
            
            if (!empty($condition) && !$this->validerRequete($sql, $parametres)) {
                throw new GestionnaireBDException("Requête invalide");
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametres);
            
            $result = $stmt->fetch();
            $this->journaliser('INFO', 'Comptage', "Comptage des enregistrements dans {$this->table}");
            
            return (int)$result['total'];
            
        } catch (Exception $e) {
            $message = "Erreur lors du comptage : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Comptage', $message);
            return 0;
        }
    }
    
    /**
     * Vérifier l'existence d'un enregistrement
     */
    public function existe($condition, $parametres = [])
    {
        return $this->compter($condition, $parametres) > 0;
    }
    
    /**
     * Trier les données
     */
    public function tri($donnees, $colonne, $ordre = 'ASC')
    {
        if (!$this->estColonne($colonne)) {
            $this->ajouterErreur("La colonne '{$colonne}' n'existe pas");
            return $donnees;
        }
        
        $ordre = strtoupper($ordre);
        if (!in_array($ordre, ['ASC', 'DESC'])) {
            $ordre = 'ASC';
        }
        
        usort($donnees, function($a, $b) use ($colonne, $ordre) {
            $valA = $a[$colonne] ?? '';
            $valB = $b[$colonne] ?? '';
            
            $comparison = $valA <=> $valB;
            return $ordre === 'DESC' ? -$comparison : $comparison;
        });
        
        $this->journaliser('INFO', 'Tri', "Tri des données par {$colonne} {$ordre}");
        return $donnees;
    }
    
    /**
     * Paginer les données
     */
    public function pagination($donnees, $page, $limite)
    {
        $page = max(1, (int)$page);
        $limite = max(1, (int)$limite);
        
        $offset = ($page - 1) * $limite;
        $result = array_slice($donnees, $offset, $limite);
        
        $this->journaliser('INFO', 'Pagination', "Pagination page {$page}, limite {$limite}");
        return $result;
    }
    
    /**
     * Valider une requête SQL
     */
    public function validerRequete($requete, $parametres = [])
    {
        // Vérifier la présence de mots-clés SQL
        $motsClefsSQL = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'FROM', 'WHERE', 'JOIN'];
        $contientMotClef = false;
        
        foreach ($motsClefsSQL as $motClef) {
            if (stripos($requete, $motClef) !== false) {
                $contientMotClef = true;
                break;
            }
        }
        
        if (!$contientMotClef) {
            $this->ajouterErreur("La requête ne contient aucun mot-clé SQL valide");
            return false;
        }
        
        // Compter les paramètres requis
        $parametresRequis = substr_count($requete, ':') + substr_count($requete, '?');
        if ($parametresRequis !== count($parametres)) {
            $this->ajouterErreur("Le nombre de paramètres ne correspond pas ({$parametresRequis} requis, " . count($parametres) . " fournis)");
            return false;
        }
        
        // Vérifier les colonnes mentionnées (simplifiée)
        foreach ($this->structure as $colonne) {
            $nomColonne = $colonne['Field'];
            if (strpos($requete, $nomColonne) !== false || strpos($requete, "`{$nomColonne}`") !== false) {
                // Au moins une colonne valide trouvée
                continue;
            }
        }
        
        return true;
    }
    
    /**
     * Exécuter une requête SQL personnalisée
     */
    public function executerRequete($requete, $parametres = [])
    {
        try {
            if (!$this->validerRequete($requete, $parametres)) {
                throw new GestionnaireBDException("Requête invalide");
            }
            
            $stmt = $this->pdo->prepare($requete);
            $stmt->execute($parametres);
            
            // Retourner les résultats pour les SELECT, sinon retourner le succès
            if (stripos(trim($requete), 'SELECT') === 0) {
                $result = $stmt->fetchAll();
                $this->journaliser('INFO', 'Requete', "Requête SELECT exécutée");
                return $result;
            } else {
                $this->journaliser('INFO', 'Requete', "Requête exécutée avec succès");
                return true;
            }
            
        } catch (Exception $e) {
            $message = "Erreur lors de l'exécution de la requête : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Requete', $message);
            return false;
        }
    }
    
    /**
     * Importer des données depuis un fichier
     */
    public function importer($cheminFichier, $format = 'csv', $premiereLigneEntete = true)
    {
        try {
            if (!file_exists($cheminFichier)) {
                throw new GestionnaireBDException("Le fichier n'existe pas : {$cheminFichier}");
            }
            
            $donnees = [];
            
            switch (strtolower($format)) {
                case 'csv':
                    $donnees = $this->lireFichierCSV($cheminFichier, $premiereLigneEntete);
                    break;
                case 'json':
                    $donnees = $this->lireFichierJSON($cheminFichier);
                    break;
                default:
                    throw new GestionnaireBDException("Format non supporté : {$format}");
            }
            
            $succes = 0;
            $echecs = 0;
            
            foreach ($donnees as $ligne) {
                if ($this->ajouter($ligne)) {
                    $succes++;
                } else {
                    $echecs++;
                }
            }
            
            $this->journaliser('INFO', 'Import', "Import terminé : {$succes} succès, {$echecs} échecs");
            return $echecs === 0;
            
        } catch (Exception $e) {
            $message = "Erreur lors de l'import : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Import', $message);
            return false;
        }
    }
    
    /**
     * Lire un fichier CSV
     */
    private function lireFichierCSV($cheminFichier, $premiereLigneEntete)
    {
        $donnees = [];
        $handle = fopen($cheminFichier, 'r');
        
        if (!$handle) {
            throw new GestionnaireBDException("Impossible d'ouvrir le fichier CSV");
        }
        
        $entetes = null;
        $premiereLigne = true;
        
        while (($ligne = fgetcsv($handle)) !== false) {
            if ($premiereLigne && $premiereLigneEntete) {
                $entetes = $ligne;
                $premiereLigne = false;
                continue;
            }
            
            if ($entetes) {
                $donnees[] = array_combine($entetes, $ligne);
            } else {
                $donnees[] = $ligne;
            }
        }
        
        fclose($handle);
        return $donnees;
    }
    
    /**
     * Lire un fichier JSON
     */
    private function lireFichierJSON($cheminFichier)
    {
        $contenu = file_get_contents($cheminFichier);
        $donnees = json_decode($contenu, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new GestionnaireBDException("Erreur de parsing JSON : " . json_last_error_msg());
        }
        
        return $donnees;
    }
    
    /**
     * Exporter les données vers un fichier
     */
    public function exporter($cheminFichier, $format = 'csv', $colonnes = [])
    {
        try {
            $donnees = $this->selectionnerTout();
            
            if (empty($donnees)) {
                $this->journaliser('WARNING', 'Export', "Aucune donnée à exporter");
                return false;
            }
            
            // Filtrer les colonnes si spécifiées
            if (!empty($colonnes)) {
                $donnees = array_map(function($ligne) use ($colonnes) {
                    return array_intersect_key($ligne, array_flip($colonnes));
                }, $donnees);
            }
            
            switch (strtolower($format)) {
                case 'csv':
                    return $this->ecrireFichierCSV($cheminFichier, $donnees);
                case 'json':
                    return $this->ecrireFichierJSON($cheminFichier, $donnees);
                default:
                    throw new GestionnaireBDException("Format non supporté : {$format}");
            }
            
        } catch (Exception $e) {
            $message = "Erreur lors de l'export : " . $e->getMessage();
            $this->ajouterErreur($message);
            $this->journaliser('ERROR', 'Export', $message);
            return false;
        }
    }
    
    /**
     * Écrire un fichier CSV
     */
    private function ecrireFichierCSV($cheminFichier, $donnees)
    {
        $handle = fopen($cheminFichier, 'w');
        
        if (!$handle) {
            throw new GestionnaireBDException("Impossible de créer le fichier CSV");
        }
        
        // Écrire les en-têtes
        if (!empty($donnees)) {
            fputcsv($handle, array_keys($donnees[0]));
            
            // Écrire les données
            foreach ($donnees as $ligne) {
                fputcsv($handle, $ligne);
            }
        }
        
        fclose($handle);
        $this->journaliser('INFO', 'Export', "Export CSV réussi : {$cheminFichier}");
        return true;
    }
    
    /**
     * Écrire un fichier JSON
     */
    private function ecrireFichierJSON($cheminFichier, $donnees)
    {
        $json = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new GestionnaireBDException("Erreur d'encodage JSON : " . json_last_error_msg());
        }
        
        $result = file_put_contents($cheminFichier, $json);
        
        if ($result === false) {
            throw new GestionnaireBDException("Impossible d'écrire le fichier JSON");
        }
        
        $this->journaliser('INFO', 'Export', "Export JSON réussi : {$cheminFichier}");
        return true;
    }
    
    /**
     * Journaliser les opérations
     */
    public function journaliser($niveau = 'INFO', $fonctionnalite = '', $message = '', $dateheure = null)
    {
        try {
            if ($dateheure === null) {
                $dateheure = date('Y-m-d H:i:s');
            }
            
            $niveauxValides = ['INFO', 'WARNING', 'ERROR'];
            if (!in_array($niveau, $niveauxValides)) {
                $niveau = 'INFO';
            }
            
            $ligneLog = "[{$dateheure}] [{$niveau}] [{$fonctionnalite}] {$message}" . PHP_EOL;
            
            $result = file_put_contents($this->cheminJournal, $ligneLog, FILE_APPEND | LOCK_EX);
            
            return $result !== false;
            
        } catch (Exception $e) {
            // En cas d'erreur de journalisation, ajouter à la liste d'erreurs
            $this->ajouterErreur("Erreur de journalisation : " . $e->getMessage());
            return false;
        }
    }
}

?>