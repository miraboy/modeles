<?php
/**
 * Classe Authentificateur - Gestion de l'authentification générique et réutilisable
 * Version améliorée avec compatibilité multi-SGBD et gestion d'erreurs renforcée
 * 
 * @author Sèkplon Mirabel  DOTOU
 * @version 2.0
 */
class Authentificateur {
    
    private PDO $pdo;
    private string $nomTable;
    private string $colonneLogin;
    private string $colonneMotDePasse;
    private string $fichierLog;
    private string $sgbd;
    public bool $creerTableAuto;
    private array $erreurs = [];
    private int $maxTentatives = 5;
    private int $dureeBloquage = 900; // 15 minutes en secondes
    private array $champsObligatoires = []; // Champs obligatoires de la table
    private array $schemaTable = []; // Schéma complet de la table
    
    // Hooks
    private $hookAvantConnexion = null;
    private $hookApresConnexion = null;
    
    /**
     * Constructeur de la classe Authentificateur
     * 
     * @param array $configurationBdd Configuration de la base de données
     * @param array $configuration Configuration de l'authentificateur
     */
    public function __construct(array $configurationBdd, array $configuration = []) {
        $this->pdo = $this->creerConnexionPdo($configurationBdd);

        // Configuration par défaut
        $configDefaut = [
            'nom_table' => 'utilisateur',
            'colonne_login' => 'login',
            'colonne_mot_de_passe' => 'mot_de_passe',
            'fichier_log' => 'auth_erreurs.log',
            'creer_table_auto' => true
        ];

        // Si $configuration n'est pas passé ou vide, utiliser la config par défaut
        if (empty($configuration)) {
            $config = $configDefaut;
        } else {
            $config = array_merge($configDefaut, $configuration);
        }

        $this->nomTable = $config['nom_table'];
        $this->colonneLogin = $config['colonne_login'];
        $this->colonneMotDePasse = $config['colonne_mot_de_passe'];
        $this->fichierLog = $config['fichier_log'];
        $this->creerTableAuto = $config['creer_table_auto'];
     
        if ($this->creerTableAuto) {
            $this->creerTableSiNecessaire();
        }
        
        // Analyser le schéma de la table
        $this->analyserSchemaTable();
        
        // Démarrer la session si pas déjà fait
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Créer la connexion PDO à partir des paramètres de configuration
     * 
     * @param array $config Configuration de la base de données
     * @return PDO Connexion PDO
     * @throws PDOException En cas d'erreur de connexion
     */
    private function creerConnexionPdo(array $config): PDO {
        // Configuration par défaut pour la base de données
        $configDefautBdd = [
            'host' => 'localhost',
            'port' => '3306',
            'charset' => 'utf8mb4',
            'sgbd' => 'mysql' // mysql, sqlite, pgsql
        ];
        
        $configBdd = array_merge($configDefautBdd, $config);
        $this->sgbd = strtolower($configBdd['sgbd']);
        
        // Vérification des paramètres obligatoires selon le SGBD
        if ($this->sgbd !== 'sqlite') {
            $paramsObligatoires = ['host', 'database', 'username', 'password'];
            foreach ($paramsObligatoires as $param) {
                if (!isset($configBdd[$param]) || (empty($configBdd[$param]) && $param !== 'password')) {
                    throw new InvalidArgumentException("Le paramètre '{$param}' est obligatoire pour la connexion à la base de données");
                }
            }
        } else {
            if (!isset($configBdd['database']) || empty($configBdd['database'])) {
                throw new InvalidArgumentException("Le paramètre 'database' est obligatoire pour SQLite");
            }
        }
        
        try {
            // Construction du DSN selon le SGBD
            switch ($this->sgbd) {
                case 'mysql':
                    $dsn = "mysql:host={$configBdd['host']};port={$configBdd['port']};dbname={$configBdd['database']};charset={$configBdd['charset']}";
                    break;
                    
                case 'sqlite':
                    $dsn = "sqlite:{$configBdd['database']}";
                    break;
                    
                case 'pgsql':
                case 'postgresql':
                    $dsn = "pgsql:host={$configBdd['host']};port={$configBdd['port']};dbname={$configBdd['database']}";
                    break;
                    
                default:
                    throw new InvalidArgumentException("SGBD non supporté : {$configBdd['sgbd']}");
            }
            
            // Options PDO par défaut
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            // Pour MySQL, ajouter les options spécifiques
            if ($this->sgbd === 'mysql') {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$configBdd['charset']}";
            }
            
            // Créer la connexion selon le SGBD
            if ($this->sgbd === 'sqlite') {
                $pdo = new PDO($dsn, null, null, $options);
            } else {
                $pdo = new PDO($dsn, $configBdd['username'], $configBdd['password'], $options);
            }
            
            return $pdo;
            
        } catch (PDOException $e) {
            throw new PDOException("Erreur de connexion à la base de données ({$this->sgbd}) : " . $e->getMessage());
        }
    }

    /**
     * Créer la table utilisateurs si elle n'existe pas
     */
    private function creerTableSiNecessaire(): void {
        try {
            $sql = $this->genererSqlCreationTable();
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors de la création de la table : " . $e->getMessage());
            throw new RuntimeException("Impossible de créer la table '{$this->nomTable}' : " . $e->getMessage());
        }
    }
    
    /**
     * Générer le SQL de création de table selon le SGBD
     * 
     * @return string SQL de création de table
     */
    private function genererSqlCreationTable(): string {
        $colonneId = "id";
        $typeId = "";
        $typeVarchar = "VARCHAR(255)";
        $typeTimestamp = "TIMESTAMP";
        $defaultTimestamp = "DEFAULT CURRENT_TIMESTAMP";
        $onUpdateTimestamp = "";
        
        switch ($this->sgbd) {
            case 'mysql':
                $typeId = "INT AUTO_INCREMENT PRIMARY KEY";
                $onUpdateTimestamp = "ON UPDATE CURRENT_TIMESTAMP";
                break;
                
            case 'sqlite':
                $typeId = "INTEGER PRIMARY KEY AUTOINCREMENT";
                $typeTimestamp = "DATETIME";
                $defaultTimestamp = "DEFAULT CURRENT_TIMESTAMP";
                $onUpdateTimestamp = ""; // SQLite ne supporte pas ON UPDATE
                break;
                
            case 'pgsql':
            case 'postgresql':
                $typeId = "SERIAL PRIMARY KEY";
                $typeTimestamp = "TIMESTAMP";
                $defaultTimestamp = "DEFAULT CURRENT_TIMESTAMP";
                $onUpdateTimestamp = ""; // PostgreSQL utilise des triggers pour ON UPDATE
                break;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->nomTable} (
            {$colonneId} {$typeId},
            {$this->colonneLogin} {$typeVarchar} UNIQUE NOT NULL,
            {$this->colonneMotDePasse} {$typeVarchar} NOT NULL,
            created_at {$typeTimestamp} {$defaultTimestamp},
            updated_at {$typeTimestamp} {$defaultTimestamp} {$onUpdateTimestamp}
        )";
        
        return $sql;
    }
    
    /**
     * Analyser le schéma de la table pour déterminer les champs obligatoires
     * Compatible avec MySQL, SQLite et PostgreSQL
     */
    private function analyserSchemaTable(): void {
        try {
            // Vérifier si la table existe
            if (!$this->tableExiste()) {
                return;
            }
            
            // Récupérer la structure de la table selon le SGBD
            $colonnes = $this->obtenirStructureTable();
            
            $this->schemaTable = [];
            $this->champsObligatoires = [];
            
            foreach ($colonnes as $colonne) {
                $nomColonne = $this->extraireNomColonne($colonne);
                $infoColonne = $this->extraireInfoColonne($colonne);
                
                $this->schemaTable[$nomColonne] = $infoColonne;
                
                // Déterminer si le champ est obligatoire
                if ($this->estChampObligatoire($nomColonne, $infoColonne)) {
                    $this->champsObligatoires[] = $nomColonne;
                }
            }
            
            // Ajouter les colonnes created_at et updated_at si elles n'existent pas
            $this->ajouterColonnesTimestamp();
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Impossible d'analyser le schéma de la table : " . $e->getMessage());
            throw new RuntimeException("Erreur lors de l'analyse du schéma de la table '{$this->nomTable}' : " . $e->getMessage());
        }
    }
    
    /**
     * Vérifier si la table existe selon le SGBD
     * 
     * @return bool True si la table existe
     */
    private function tableExiste(): bool {
        try {
            switch ($this->sgbd) {
                case 'mysql':
                    $nomTable = preg_replace('/[^a-zA-Z0-9_]/', '', $this->nomTable); // Sécurise le nom de la table
                    $sql = "SHOW TABLES LIKE '$nomTable'";
                    $stmt = $this->pdo->query($sql);
                    return $stmt->rowCount() > 0;
                    
                case 'sqlite':
                    $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name = :table_name";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->bindParam(':table_name', $this->nomTable);
                    break;
                    
                case 'pgsql':
                case 'postgresql':
                    $sql = "SELECT tablename FROM pg_tables WHERE tablename = :table_name";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->bindParam(':table_name', $this->nomTable);
                    break;
                    
                default:
                    return false;
            }
            
            $stmt->execute();
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            throw new PDOException("Erreur lors de la vérification de l'existence de la table : " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir la structure de la table selon le SGBD
     * 
     * @return array Structure de la table
     */
    private function obtenirStructureTable(): array {
        try {
            switch ($this->sgbd) {
                case 'mysql':
                    $sql = "DESCRIBE {$this->nomTable}";
                    break;
                    
                case 'sqlite':
                    $sql = "PRAGMA table_info({$this->nomTable})";
                    break;
                    
                case 'pgsql':
                case 'postgresql':
                    $sql = "SELECT column_name, data_type, is_nullable, column_default 
                           FROM information_schema.columns 
                           WHERE table_name = :table_name 
                           ORDER BY ordinal_position";
                    break;
                    
                default:
                    throw new InvalidArgumentException("SGBD non supporté pour l'analyse de schéma");
            }
            
            $stmt = $this->pdo->prepare($sql);
            
            if ($this->sgbd === 'pgsql' || $this->sgbd === 'postgresql') {
                $stmt->bindParam(':table_name', $this->nomTable);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new PDOException("Erreur lors de la récupération de la structure de la table : " . $e->getMessage());
        }
    }
    
    /**
     * Extraire le nom de la colonne selon le SGBD
     * 
     * @param array $colonne Information de la colonne
     * @return string Nom de la colonne
     */
    private function extraireNomColonne(array $colonne): string {
        switch ($this->sgbd) {
            case 'mysql':
                return $colonne['Field'];
            case 'sqlite':
                return $colonne['name'];
            case 'pgsql':
            case 'postgresql':
                return $colonne['column_name'];
            default:
                throw new InvalidArgumentException("SGBD non supporté");
        }
    }
    
    /**
     * Extraire les informations de la colonne selon le SGBD
     * 
     * @param array $colonne Information de la colonne
     * @return array Informations normalisées de la colonne
     */
    private function extraireInfoColonne(array $colonne): array {
        switch ($this->sgbd) {
            case 'mysql':
                return [
                    'type' => $colonne['Type'],
                    'null' => $colonne['Null'] === 'YES',
                    'default' => $colonne['Default'],
                    'extra' => $colonne['Extra'] ?? ''
                ];
                
            case 'sqlite':
                return [
                    'type' => $colonne['type'],
                    'null' => $colonne['notnull'] == 0,
                    'default' => $colonne['dflt_value'],
                    'extra' => $colonne['pk'] == 1 ? 'auto_increment' : ''
                ];
                
            case 'pgsql':
            case 'postgresql':
                $isAutoIncrement = strpos($colonne['column_default'] ?? '', 'nextval') !== false;
                return [
                    'type' => $colonne['data_type'],
                    'null' => $colonne['is_nullable'] === 'YES',
                    'default' => $colonne['column_default'],
                    'extra' => $isAutoIncrement ? 'auto_increment' : ''
                ];
                
            default:
                throw new InvalidArgumentException("SGBD non supporté");
        }
    }
    
    /**
     * Déterminer si un champ est obligatoire
     * 
     * @param string $nomColonne Nom de la colonne
     * @param array $infoColonne Informations de la colonne
     * @return bool True si le champ est obligatoire
     */
    private function estChampObligatoire(string $nomColonne, array $infoColonne): bool {
        // Exclure les colonnes système et de base
        $colonnesExclues = [
            $this->colonneLogin,
            $this->colonneMotDePasse,
            'id',
            'created_at',
            'updated_at'
        ];
        
        if (in_array($nomColonne, $colonnesExclues)) {
            return false;
        }
        
        // Un champ est obligatoire s'il ne peut pas être NULL, n'a pas de valeur par défaut
        // et n'est pas auto-incrémenté
        return !$infoColonne['null'] && 
               $infoColonne['default'] === null && 
               !str_contains($infoColonne['extra'], 'auto_increment');
    }
    
    /**
     * Ajouter les colonnes created_at et updated_at si elles n'existent pas
     */
    private function ajouterColonnesTimestamp(): void {
        try {
            if (!isset($this->schemaTable['created_at'])) {
                $this->ajouterColonneCreatedAt();
            }
            
            if (!isset($this->schemaTable['updated_at'])) {
                $this->ajouterColonneUpdatedAt();
            }
        } catch (PDOException $e) {
            error_log("Impossible d'ajouter les colonnes timestamp : " . $e->getMessage());
            // On continue même si l'ajout échoue
        }
    }
    
    /**
     * Ajouter la colonne created_at selon le SGBD
     */
    private function ajouterColonneCreatedAt(): void {
        switch ($this->sgbd) {
            case 'mysql':
                $sql = "ALTER TABLE {$this->nomTable} ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                break;
                
            case 'sqlite':
                $sql = "ALTER TABLE {$this->nomTable} ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP";
                break;
                
            case 'pgsql':
            case 'postgresql':
                $sql = "ALTER TABLE {$this->nomTable} ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                break;
                
            default:
                return;
        }
        
        $this->pdo->exec($sql);
        
        // Mettre à jour le schéma en mémoire
        $this->schemaTable['created_at'] = [
            'type' => 'TIMESTAMP',
            'null' => false,
            'default' => 'CURRENT_TIMESTAMP',
            'extra' => ''
        ];
    }
    
    /**
     * Ajouter la colonne updated_at selon le SGBD
     */
    private function ajouterColonneUpdatedAt(): void {
        switch ($this->sgbd) {
            case 'mysql':
                $sql = "ALTER TABLE {$this->nomTable} ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                break;
                
            case 'sqlite':
                // SQLite ne supporte pas ON UPDATE, on utilise des triggers ou on gère manuellement
                $sql = "ALTER TABLE {$this->nomTable} ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP";
                break;
                
            case 'pgsql':
            case 'postgresql':
                // PostgreSQL nécessite un trigger pour ON UPDATE
                $sql = "ALTER TABLE {$this->nomTable} ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->pdo->exec($sql);
                $this->creerTriggerUpdatedAtPostgreSQL();
                return;
                
            default:
                return;
        }
        
        $this->pdo->exec($sql);
        
        // Mettre à jour le schéma en mémoire
        $this->schemaTable['updated_at'] = [
            'type' => 'TIMESTAMP',
            'null' => false,
            'default' => 'CURRENT_TIMESTAMP',
            'extra' => 'ON UPDATE CURRENT_TIMESTAMP'
        ];
    }
    
    /**
     * Créer un trigger pour updated_at sur PostgreSQL
     */
    private function creerTriggerUpdatedAtPostgreSQL(): void {
        try {
            // Créer la fonction trigger si elle n'existe pas
            $sqlFunction = "
                CREATE OR REPLACE FUNCTION update_updated_at_column()
                RETURNS TRIGGER AS $$
                BEGIN
                    NEW.updated_at = CURRENT_TIMESTAMP;
                    RETURN NEW;
                END;
                $$ language 'plpgsql';
            ";
            
            // Créer le trigger
            $sqlTrigger = "
                DROP TRIGGER IF EXISTS update_{$this->nomTable}_updated_at ON {$this->nomTable};
                CREATE TRIGGER update_{$this->nomTable}_updated_at
                    BEFORE UPDATE ON {$this->nomTable}
                    FOR EACH ROW
                    EXECUTE FUNCTION update_updated_at_column();
            ";
            
            $this->pdo->exec($sqlFunction);
            $this->pdo->exec($sqlTrigger);
            
        } catch (PDOException $e) {
            error_log("Impossible de créer le trigger updated_at pour PostgreSQL : " . $e->getMessage());
        }
    }
    
    /**
     * Connecte un utilisateur avec son login et mot de passe.
     *
     * @param string $login Login de l'utilisateur
     * @param string $motDePasse Mot de passe de l'utilisateur
     * @return bool True si connexion réussie, false sinon
     */
    public function connecter(string $login, string $motDePasse): bool {
        $this->viderErreurs();
        
        // Vérifier si l'IP est bloquée
        if ($this->estIpBloquee()) {
            $this->ajouterErreur("Trop de tentatives échouées. Réessayez plus tard.");
            return false;
        }
        
        // Hook avant connexion
        if ($this->hookAvantConnexion) {
            $resultat = call_user_func($this->hookAvantConnexion, $login);
            if ($resultat === false) {
                $this->ajouterErreur("Connexion bloquée par le hook avant_connexion");
                return false;
            }
        }
        
        try {
            $sql = "SELECT * FROM {$this->nomTable} WHERE {$this->colonneLogin} = :login";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':login', $login);
            $stmt->execute();
            
            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($utilisateur && password_verify($motDePasse, $utilisateur[$this->colonneMotDePasse])) {
                // Connexion réussie
                $_SESSION['authentifie'] = true;
                $_SESSION['utilisateur_login'] = $login;
                $_SESSION['utilisateur_donnees'] = $utilisateur;
                
                // Réinitialiser les tentatives
                $this->reinitialiserTentatives();
                
                // Hook après connexion
                if ($this->hookApresConnexion) {
                    call_user_func($this->hookApresConnexion, $utilisateur);
                }
                
                return true;
            } else {
                // Connexion échouée
                $this->enregistrerTentativeEchouee($login);
                $this->ajouterErreur("Login ou mot de passe incorrect");
                return false;
            }
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur de base de données lors de la connexion : " . $e->getMessage());
            throw new RuntimeException("Erreur critique lors de la connexion : " . $e->getMessage());
        }
    }
    
    /**
     * Crée un nouveau compte utilisateur avec validation des champs obligatoires.
     *
     * @param string $login Login du nouvel utilisateur
     * @param string $motDePasse Mot de passe du nouvel utilisateur
     * @param array $donneesSupplementaires Données supplémentaires pour les champs obligatoires
     * @return bool True si la création réussit, false sinon
     */
    public function creerCompte(string $login, string $motDePasse, array $donneesSupplementaires = []): bool {
        $this->viderErreurs();
        
        // Validation basique
        if (empty($login) || empty($motDePasse)) {
            $this->ajouterErreur("Le login et le mot de passe sont obligatoires");
            return false;
        }
        
        if (strlen($motDePasse) < 6) {
            $this->ajouterErreur("Le mot de passe doit contenir au moins 6 caractères");
            return false;
        }
        
        // Vérifier que tous les champs obligatoires sont fournis
        $champsManquants = [];
        foreach ($this->champsObligatoires as $champ) {
            if (!isset($donneesSupplementaires[$champ]) || 
                (is_string($donneesSupplementaires[$champ]) && trim($donneesSupplementaires[$champ]) === '') ||
                (is_null($donneesSupplementaires[$champ]))) {
                $champsManquants[] = $champ;
            }
        }
        
        if (!empty($champsManquants)) {
            $this->ajouterErreur("Les champs suivants sont obligatoires : " . implode(', ', $champsManquants));
            return false;
        }
        
        try {
            // Vérifier si l'utilisateur existe déjà
            $sql = "SELECT COUNT(*) FROM {$this->nomTable} WHERE {$this->colonneLogin} = :login";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':login', $login);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                $this->ajouterErreur("Ce login existe déjà");
                return false;
            }
            
            // Préparer les données d'insertion
            $donneesInsertion = [
                $this->colonneLogin => $login,
                $this->colonneMotDePasse => password_hash($motDePasse, PASSWORD_DEFAULT)
            ];
            
            // Ajouter les données supplémentaires validées
            foreach ($donneesSupplementaires as $champ => $valeur) {
                if (isset($this->schemaTable[$champ])) {
                    $donneesInsertion[$champ] = $valeur;
                }
            }
            
            // Gérer updated_at pour SQLite et PostgreSQL (pas de ON UPDATE automatique)
            if (isset($this->schemaTable['updated_at']) && 
                ($this->sgbd === 'sqlite' || $this->sgbd === 'pgsql' || $this->sgbd === 'postgresql')) {
                $donneesInsertion['updated_at'] = date('Y-m-d H:i:s');
            }
            
            // Construire la requête d'insertion
            $colonnes = implode(', ', array_keys($donneesInsertion));
            $placeholders = ':' . implode(', :', array_keys($donneesInsertion));
            
            $sql = "INSERT INTO {$this->nomTable} ({$colonnes}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            
            // Bind des paramètres
            foreach ($donneesInsertion as $champ => $valeur) {
                $stmt->bindValue(":{$champ}", $valeur);
            }
            
            $resultat = $stmt->execute();
            
            if (!$resultat) {
                $this->ajouterErreur("Erreur lors de l'insertion en base de données");
                return false;
            }
            
            return true;
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors de la création du compte : " . $e->getMessage());
            throw new RuntimeException("Erreur critique lors de la création du compte : " . $e->getMessage());
        }
    }

    /**
     * Déconnecter l'utilisateur actuel
     */
    public function deconnecter(): void {
        unset($_SESSION['authentifie']);
        unset($_SESSION['utilisateur_login']);
        unset($_SESSION['utilisateur_donnees']);
    }
    
    /**
     * Vérifier si un utilisateur est connecté
     * 
     * @return bool True si utilisateur connecté, false sinon
     */
    public function estConnecte(): bool {
        return isset($_SESSION['authentifie']) && $_SESSION['authentifie'] === true;
    }
    
    /**
     * Obtenir les données de l'utilisateur connecté
     * 
     * @return array|null Données de l'utilisateur ou null si non connecté
     */
    public function utilisateurConnecte(): ?array {
        if ($this->estConnecte()) {
            return $_SESSION['utilisateur_donnees'] ?? null;
        }
        return null;
    }
    
    /**
     * Obtenir la liste des champs obligatoires de la table
     * 
     * @return array Liste des champs obligatoires
     */
    public function obtenirChampsObligatoires(): array {
        return $this->champsObligatoires;
    }
    
    /**
     * Obtenir le schéma complet de la table
     * 
     * @return array Schéma de la table
     */
    public function obtenirSchemaTable(): array {
        return $this->schemaTable;
    }
    
    /**
     * Vérifier si un champ existe dans la table
     * 
     * @param string $nomChamp Nom du champ à vérifier
     * @return bool True si le champ existe, false sinon
     */
    public function champExiste(string $nomChamp): bool {
        return isset($this->schemaTable[$nomChamp]);
    }
    
    /**
     * Obtenir les erreurs
     * 
     * @return array Tableau des erreurs
     */
    public function obtenirErreurs(): array {
        return $this->erreurs;
    }
    
    /**
     * Obtenir la première erreur
     * 
     * @return string|null Première erreur ou null
     */
    public function obtenirErreur(): ?string {
        return $this->erreurs[0] ?? null;
    }
    
    /**
     * Définir le hook avant connexion
     * 
     * @param callable $callback Fonction à appeler avant connexion
     */
    public function definirHookAvantConnexion(callable $callback): void {
        $this->hookAvantConnexion = $callback;
    }
    
    /**
     * Définir le hook après connexion
     * 
     * @param callable $callback Fonction à appeler après connexion
     */
    public function definirHookApresConnexion(callable $callback): void {
        $this->hookApresConnexion = $callback;
    }
    
    /**
     * Ajouter une erreur au tableau des erreurs
     * 
     * @param string $erreur Message d'erreur
     */
    private function ajouterErreur(string $erreur): void {
        $this->erreurs[] = $erreur;
    }
    
    /**
     * Vider le tableau des erreurs
     */
    private function viderErreurs(): void {
        $this->erreurs = [];
    }
    
    /**
     * Enregistrer une tentative de connexion échouée dans le log
     * 
     * @param string $login Login utilisé
     */
    private function enregistrerTentativeEchouee(string $login): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP_INCONNUE';
            $timestamp = date('Y-m-d H:i:s');
            $message = "[{$timestamp}] Tentative échouée - IP: {$ip}, Login: {$login}" . PHP_EOL;
            
            if (!file_put_contents($this->fichierLog, $message, FILE_APPEND | LOCK_EX)) {
                error_log("Impossible d'écrire dans le fichier de log : {$this->fichierLog}");
            }
        } catch (Exception $e) {
            error_log("Erreur lors de l'enregistrement de la tentative échouée : " . $e->getMessage());
        }
        
        // Enregistrer en session pour le système de blocage
        if (!isset($_SESSION['tentatives_connexion'])) {
            $_SESSION['tentatives_connexion'] = [];
        }
        
        $_SESSION['tentatives_connexion'][] = time();
    }
    
    /**
     * Vérifier si l'IP est bloquée (trop de tentatives)
     * 
     * @return bool True si IP bloquée, false sinon
     */
    private function estIpBloquee(): bool {
        if (!isset($_SESSION['tentatives_connexion']) || empty($_SESSION['tentatives_connexion'])) {
            return false;
        }
        
        $maintenant = time();
        $tentativesRecentes = array_filter($_SESSION['tentatives_connexion'], function($timestamp) use ($maintenant) {
            return ($maintenant - $timestamp) < $this->dureeBloquage;
        });
        
        // Nettoyer les anciennes tentatives
        $_SESSION['tentatives_connexion'] = array_values($tentativesRecentes);
        
        return count($tentativesRecentes) >= $this->maxTentatives;
    }
    
    /**
     * Réinitialiser les tentatives de connexion
     */
    private function reinitialiserTentatives(): void {
        unset($_SESSION['tentatives_connexion']);
    }
    
    /**
     * Mettre à jour un utilisateur (avec gestion automatique de updated_at)
     * 
     * @param string $login Login de l'utilisateur à mettre à jour
     * @param array $donneesAMettreAJour Données à mettre à jour
     * @return bool True si mise à jour réussie, false sinon
     */
    public function mettreAJourUtilisateur(string $login, array $donneesAMettreAJour): bool {
        $this->viderErreurs();
        
        if (empty($donneesAMettreAJour)) {
            $this->ajouterErreur("Aucune donnée à mettre à jour");
            return false;
        }
        
        try {
            // Filtrer les données selon le schéma de la table
            $donneesValides = [];
            foreach ($donneesAMettreAJour as $champ => $valeur) {
                if (isset($this->schemaTable[$champ]) && $champ !== 'id' && $champ !== 'created_at') {
                    // Hasher le mot de passe si c'est le champ mot de passe
                    if ($champ === $this->colonneMotDePasse) {
                        $valeur = password_hash($valeur, PASSWORD_DEFAULT);
                    }
                    $donneesValides[$champ] = $valeur;
                }
            }
            
            if (empty($donneesValides)) {
                $this->ajouterErreur("Aucune donnée valide à mettre à jour");
                return false;
            }
            
            // Gérer updated_at pour SQLite (pas de ON UPDATE automatique)
            if (isset($this->schemaTable['updated_at']) && $this->sgbd === 'sqlite') {
                $donneesValides['updated_at'] = date('Y-m-d H:i:s');
            }
            
            // Construire la requête de mise à jour
            $setClauses = [];
            foreach (array_keys($donneesValides) as $champ) {
                $setClauses[] = "{$champ} = :{$champ}";
            }
            
            $sql = "UPDATE {$this->nomTable} SET " . implode(', ', $setClauses) . " WHERE {$this->colonneLogin} = :login";
            $stmt = $this->pdo->prepare($sql);
            
            // Bind des paramètres
            foreach ($donneesValides as $champ => $valeur) {
                $stmt->bindValue(":{$champ}", $valeur);
            }
            $stmt->bindValue(':login', $login);
            
            $resultat = $stmt->execute();
            
            if (!$resultat) {
                $this->ajouterErreur("Erreur lors de la mise à jour");
                return false;
            }
            
            // Mettre à jour la session si c'est l'utilisateur connecté
            if ($this->estConnecte() && $_SESSION['utilisateur_login'] === $login) {
                // Recharger les données utilisateur
                $sqlSelect = "SELECT * FROM {$this->nomTable} WHERE {$this->colonneLogin} = :login";
                $stmtSelect = $this->pdo->prepare($sqlSelect);
                $stmtSelect->bindParam(':login', $login);
                $stmtSelect->execute();
                
                $utilisateurMisAJour = $stmtSelect->fetch(PDO::FETCH_ASSOC);
                if ($utilisateurMisAJour) {
                    $_SESSION['utilisateur_donnees'] = $utilisateurMisAJour;
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors de la mise à jour : " . $e->getMessage());
            throw new RuntimeException("Erreur critique lors de la mise à jour de l'utilisateur : " . $e->getMessage());
        }
    }
    
    /**
     * Supprimer un utilisateur
     * 
     * @param string $login Login de l'utilisateur à supprimer
     * @return bool True si suppression réussie, false sinon
     */
    public function supprimerUtilisateur(string $login): bool {
        $this->viderErreurs();
        
        if (empty($login)) {
            $this->ajouterErreur("Le login est obligatoire pour la suppression");
            return false;
        }
        
        try {
            $sql = "DELETE FROM {$this->nomTable} WHERE {$this->colonneLogin} = :login";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':login', $login);
            
            $resultat = $stmt->execute();
            
            if (!$resultat) {
                $this->ajouterErreur("Erreur lors de la suppression");
                return false;
            }
            
            // Si c'est l'utilisateur connecté qui est supprimé, le déconnecter
            if ($this->estConnecte() && $_SESSION['utilisateur_login'] === $login) {
                $this->deconnecter();
            }
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors de la suppression : " . $e->getMessage());
            throw new RuntimeException("Erreur critique lors de la suppression de l'utilisateur : " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir un utilisateur par son login
     * 
     * @param string $login Login de l'utilisateur
     * @return array|null Données de l'utilisateur ou null si non trouvé
     */
    public function obtenirUtilisateur(string $login): ?array {
        $this->viderErreurs();
        
        if (empty($login)) {
            $this->ajouterErreur("Le login est obligatoire");
            return null;
        }
        
        try {
            $sql = "SELECT * FROM {$this->nomTable} WHERE {$this->colonneLogin} = :login";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':login', $login);
            $stmt->execute();
            
            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);
            return $utilisateur ?: null;
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors de la récupération de l'utilisateur : " . $e->getMessage());
            throw new RuntimeException("Erreur critique lors de la récupération de l'utilisateur : " . $e->getMessage());
        }
    }
    
    /**
     * Lister tous les utilisateurs
     * 
     * @param int $limite Limite du nombre d'utilisateurs à retourner (0 = pas de limite)
     * @param int $offset Décalage pour la pagination
     * @return array Liste des utilisateurs
     */
    public function listerUtilisateurs(int $limite = 0, int $offset = 0): array {
        $this->viderErreurs();
        
        try {
            $sql = "SELECT * FROM {$this->nomTable} ORDER BY created_at DESC";
            
            if ($limite > 0) {
                switch ($this->sgbd) {
                    case 'mysql':
                    case 'sqlite':
                        $sql .= " LIMIT :limite OFFSET :offset";
                        break;
                    case 'pgsql':
                    case 'postgresql':
                        $sql .= " LIMIT :limite OFFSET :offset";
                        break;
                }
            }
            
            $stmt = $this->pdo->prepare($sql);
            
            if ($limite > 0) {
                $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors de la récupération des utilisateurs : " . $e->getMessage());
            throw new RuntimeException("Erreur critique lors de la récupération des utilisateurs : " . $e->getMessage());
        }
    }
    
    /**
     * Compter le nombre total d'utilisateurs
     * 
     * @return int Nombre d'utilisateurs
     */
    public function compterUtilisateurs(): int {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->nomTable}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return (int) $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors du comptage des utilisateurs : " . $e->getMessage());
            throw new RuntimeException("Erreur critique lors du comptage des utilisateurs : " . $e->getMessage());
        }
    }
    
    /**
     * Vérifier si un login existe déjà
     * 
     * @param string $login Login à vérifier
     * @return bool True si le login existe, false sinon
     */
    public function loginExiste(string $login): bool {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->nomTable} WHERE {$this->colonneLogin} = :login";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':login', $login);
            $stmt->execute();
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors de la vérification du login : " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir les informations sur le SGBD utilisé
     * 
     * @return array Informations sur le SGBD
     */
    public function obtenirInfosSgbd(): array {
        try {
            return [
                'sgbd' => $this->sgbd,
                'version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'driver' => $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)
            ];
        } catch (PDOException $e) {
            return [
                'sgbd' => $this->sgbd,
                'version' => 'Inconnue',
                'driver' => 'Inconnu',
                'erreur' => $e->getMessage()
            ];
        }
    }
}
?>