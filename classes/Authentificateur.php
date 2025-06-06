<?php
/**
 * Classe Authentificateur - Gestion de l'authentification générique et réutilisable
 * 
 * @author Développeur PHP Senior
 * @version 1.0
 */
class Authentificateur {
    
    private PDO $pdo;
    private string $nomTable;
    private string $colonneLogin;
    private string $colonneMotDePasse;
    private string $fichierLog;
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
        
        $config = array_merge($configDefaut, $configuration);
        
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
        
        // Vérification des paramètres obligatoires
        $paramsObligatoires = ['host', 'database', 'username', 'password'];
        foreach ($paramsObligatoires as $param) {
            if (!isset($configBdd[$param]) || empty($configBdd[$param])) {
                if ($param === 'password') {
                    // Le mot de passe peut être vide
                    continue;
                }
                throw new InvalidArgumentException("Le paramètre '{$param}' est obligatoire pour la connexion à la base de données");
            }
        }
        
        try {
            // Construction du DSN selon le SGBD
            switch (strtolower($configBdd['sgbd'])) {
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
            if (strtolower($configBdd['sgbd']) === 'mysql') {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$configBdd['charset']}";
            }
            
            $pdo = new PDO($dsn, $configBdd['username'], $configBdd['password'], $options);
            // Créer la table si nécessaire
            
            
            return $pdo;
            
        } catch (PDOException $e) {
            throw new PDOException("Erreur de connexion à la base de données : " . $e->getMessage());
        }
        
        
    }

    /**
     * Créer la table utilisateurs si elle n'existe pas
     */
    private function creerTableSiNecessaire(): void {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->nomTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                {$this->colonneLogin} VARCHAR(255) UNIQUE NOT NULL,
                {$this->colonneMotDePasse} VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors de la création de la table : " . $e->getMessage());
        }
    }
    
    /**
     * Analyser le schéma de la table pour déterminer les champs obligatoires
     */
    private function analyserSchemaTable(): void {
        
        try {
            
            // Vérifier si la table existe
            $sql = "SHOW TABLES LIKE :table_name";
            
            $stmt = $this->pdo->prepare($sql);
            
            $stmt->bindParam(':table_name', $this->nomTable);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // Table n'existe pas
                return;
            }
            
            // Récupérer la structure de la table
            $sql = "DESCRIBE {$this->nomTable}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $colonnes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->schemaTable = [];
            $this->champsObligatoires = [];
            
            foreach ($colonnes as $colonne) {
                $nomColonne = $colonne['Field'];
                $this->schemaTable[$nomColonne] = [
                    'type' => $colonne['Type'],
                    'null' => $colonne['Null'] === 'YES',
                    'default' => $colonne['Default'],
                    'extra' => $colonne['Extra']
                ];
                
                // Déterminer si le champ est obligatoire
                if ($colonne['Null'] === 'NO' && 
                    $colonne['Default'] === null && 
                    !str_contains($colonne['Extra'], 'auto_increment') &&
                    $nomColonne !== $this->colonneLogin &&
                    $nomColonne !== $this->colonneMotDePasse &&
                    !in_array($nomColonne, ['created_at', 'updated_at'])) {
                    
                    $this->champsObligatoires[] = $nomColonne;
                }
            }
            
            // Ajouter les colonnes created_at et updated_at si elles n'existent pas
            $this->ajouterColonnesTimestamp();
            
        } catch (PDOException $e) {
            // En cas d'erreur, on continue sans analyse de schéma
            $this->ajouterErreur("Impossible d'analyser le schéma de la table : " . $e->getMessage());
        }
    }
    
    /**
     * Ajouter les colonnes created_at et updated_at si elles n'existent pas
     */
    private function ajouterColonnesTimestamp(): void {
        try {
            if (!isset($this->schemaTable['created_at'])) {
                $sql = "ALTER TABLE {$this->nomTable} ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $this->pdo->exec($sql);
            }
            
            if (!isset($this->schemaTable['updated_at'])) {
                $sql = "ALTER TABLE {$this->nomTable} ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                $this->pdo->exec($sql);
            }
        } catch (PDOException $e) {
            // On continue même si l'ajout échoue
            error_log("Impossible d'ajouter les colonnes timestamp : " . $e->getMessage());
        }
    }
    
    /**
     * Connecter un utilisateur
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
            $this->ajouterErreur("Erreur de base de données : " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Créer un nouveau compte utilisateur
     * 
     * @param string $login Login du nouvel utilisateur
     * @param string $motDePasse Mot de passe du nouvel utilisateur
     * @param array $donneesSupplementaires Données supplémentaires pour les champs obligatoires
     * @return bool True si création réussie, false sinon
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
        foreach ($this->champsObligatoires as $champ) {
            if (!isset($donneesSupplementaires[$champ]) || empty($donneesSupplementaires[$champ])) {
                $this->ajouterErreur("Le champ '{$champ}' est obligatoire");
                return false;
            }
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
            
            // Ajouter les données supplémentaires
            foreach ($donneesSupplementaires as $champ => $valeur) {
                if (isset($this->schemaTable[$champ])) {
                    $donneesInsertion[$champ] = $valeur;
                }
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
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            $this->ajouterErreur("Erreur lors de la création du compte : " . $e->getMessage());
            return false;
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
     /* 
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
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP_INCONNUE';
        $timestamp = date('Y-m-d H:i:s');
        $message = "[{$timestamp}] Tentative échouée - IP: {$ip}, Login: {$login}" . PHP_EOL;
        
        file_put_contents($this->fichierLog, $message, FILE_APPEND | LOCK_EX);
        
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
        $_SESSION['tentatives_connexion'] = $tentativesRecentes;
        
        return count($tentativesRecentes) >= $this->maxTentatives;
    }
    
    /**
     * Réinitialiser les tentatives de connexion
     */
    private function reinitialiserTentatives(): void {
        unset($_SESSION['tentatives_connexion']);
    }
}
?>