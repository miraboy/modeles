<?php

class Authentificateur {
    private $db;
    private $validateur;
    private $config;
    private $tableUtilisateurs;
    private $useCustomTable;
    private $authMethods = ['classic', 'email_link', 'both'];

    /**
     * Constructeur
     * @param GestionnaireBD $db Instance de GestionnaireBD
     * @param Validateur $validateur Instance de Validateur
     * @param array $config Configuration d'authentification
     */
    public function __construct(GestionnaireBD $db, Validateur $validateur, array $config = []) {
        $this->db = $db;
        $this->validateur = $validateur;
        
        // Configuration par défaut
        $this->config = array_merge([
            'methode' => 'both', // classic, email_link, both
            'use_custom_table' => false,
            'custom_table_name' => 'utilisateurs',
            'token_expiration' => 3600, // 1 heure en secondes
            'password_policy' => 'motdepasse', // Règle de validation des mots de passe
            'email_field' => 'email',
            'password_field' => 'motdepasse',
            'token_field' => 'token_auth',
            'token_expiry_field' => 'token_expiry',
            'create_table_if_not_exists' => true
        ], $config);

        $this->useCustomTable = $this->config['use_custom_table'];
        $this->tableUtilisateurs = $this->useCustomTable ? 
            $this->config['custom_table_name'] : 'auth_users';

        // Créer la table d'authentification si nécessaire
        if (!$this->useCustomTable && $this->config['create_table_if_not_exists']) {
            $this->creerTableAuth();
        }
    }

    /**
     * Crée la table d'authentification si elle n'existe pas
     */
    private function creerTableAuth() {
        try {
            $this->db->executerRequete($this->tableUtilisateurs, "CREATE TABLE IF NOT EXISTS $this->tableUtilisateurs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                {$this->config['email_field']} VARCHAR(255) NOT NULL UNIQUE,
                {$this->config['password_field']} VARCHAR(255),
                {$this->config['token_field']} VARCHAR(255),
                {$this->config['token_expiry_field']} DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )", [], false);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la création de la table d'authentification: " . $e->getMessage());
        }
    }

    /**
     * Inscrire un nouvel utilisateur
     * @param array $donnees Données utilisateur
     * @return bool Succès de l'inscription
     */
    public function inscrire(array $donnees): bool {
        // Valider les données
        $regles = [
            $this->config['email_field'] => 'required|email',
        ];

        // Ajouter la validation du mot de passe si la méthode le nécessite
        if ($this->config['methode'] === 'classic' || $this->config['methode'] === 'both') {
            $regles[$this->config['password_field']] = 'required|' . $this->config['password_policy'];
        }

        if (!$this->validateur->valider($regles)) {
            // var_dump($this->validateur->getDonnees());die;
            return false;
        }

        // Vérifier si l'email existe déjà
        $existe = $this->db->selectionnerUn(
            $this->tableUtilisateurs,
            "{$this->config['email_field']} = :email",
            [':email' => $donnees[$this->config['email_field']]]
        );

        if ($existe) {
            $this->validateur->ajouterErreur($this->config['email_field'], 'Cet email est déjà utilisé.');
            return false;
        }

        // Hasher le mot de passe si nécessaire
        if (isset($donnees[$this->config['password_field']])) {
            $donnees[$this->config['password_field']] = password_hash(
                $donnees[$this->config['password_field']],
                PASSWORD_DEFAULT
            );
        }

        // Insérer l'utilisateur
        $this->db->ajouter($this->tableUtilisateurs, $donnees);

        return true;
    }

    /**
     * Authentification classique par email/mot de passe
     * @param string $email
     * @param string $motDePasse
     * @return array|false Données utilisateur ou false si échec
     */
    public function authentifierClassique(string $email, string $motDePasse) {
        if ($this->config['methode'] === 'email_link') {
            throw new Exception("L'authentification classique n'est pas activée dans la configuration.");
        }

        $utilisateur = $this->db->selectionnerUn(
            $this->tableUtilisateurs,
            "{$this->config['email_field']} = :email",
            [':email' => $email]
        );

        if (!$utilisateur || !isset($utilisateur[$this->config['password_field']])) {
            return false;
        }   

        if (password_verify($motDePasse, $utilisateur[$this->config['password_field']])) {
            return $utilisateur;
        }

        return false;
    }

    /**
     * Génère un lien d'authentification par email
     * @param string $email
     * @return string Token généré
     */
    public function genererLienAuth(string $email): string {
        if ($this->config['methode'] === 'classic') {
            throw new Exception("L'authentification par lien email n'est pas activée dans la configuration.");
        }

        // Vérifier si l'email existe
        $utilisateur = $this->db->selectionnerUn(
            $this->tableUtilisateurs,
            "{$this->config['email_field']} = :email",
            [':email' => $email]
        );

        if (!$utilisateur) {
            throw new Exception("Aucun utilisateur trouvé avec cet email.");
        }

        // Générer un token unique
        $token = bin2hex(random_bytes(32));
        $expiration = date('Y-m-d H:i:s', time() + $this->config['token_expiration']);

        // Mettre à jour l'utilisateur avec le token
        $this->db->modifier(
            $this->tableUtilisateurs,
            [
                $this->config['token_field'] => $token,
                $this->config['token_expiry_field'] => $expiration
            ],
            "{$this->config['email_field']} = :email",
            [':email' => $email]
        );

        return $token;
    }

    /**
     * Authentifie un utilisateur via un token
     * @param string $token
     * @return array|false Données utilisateur ou false si échec
     */
    public function authentifierParToken(string $token) {
        if ($this->config['methode'] === 'classic') {
            throw new Exception("L'authentification par token n'est pas activée dans la configuration.");
        }

        $utilisateur = $this->db->selectionnerUn(
            $this->tableUtilisateurs,
            "{$this->config['token_field']} = :token AND {$this->config['token_expiry_field']} > :now",
            [
                ':token' => $token,
                ':now' => date('Y-m-d H:i:s')
            ]
        );

        if (!$utilisateur) {
            return false;
        }

        // Invalider le token après utilisation
        $this->db->modifier(
            $this->tableUtilisateurs,
            [
                $this->config['token_field'] => null,
                $this->config['token_expiry_field'] => null
            ],
            "id = :id",
            [':id' => $utilisateur['id']]
        );

        return $utilisateur;
    }

    /**
     * Méthode générique d'authentification qui utilise la méthode configurée
     * @param array $credentials
     * @return array|false Données utilisateur ou false si échec
     */
    public function authentifier(array $credentials) {
        switch ($this->config['methode']) {
            case 'classic':
                if (!isset($credentials['email']) || !isset($credentials['password'])) {
                    throw new Exception("Email et mot de passe requis pour l'authentification classique.");
                }
                return $this->authentifierClassique($credentials['email'], $credentials['password']);
                
            case 'email_link':
                if (!isset($credentials['token'])) {
                    throw new Exception("Token requis pour l'authentification par lien email.");
                }
                return $this->authentifierParToken($credentials['token']);
                
            case 'both':
                if (isset($credentials['token'])) {
                    return $this->authentifierParToken($credentials['token']);
                } elseif (isset($credentials['email']) && isset($credentials['password'])) {
                    return $this->authentifierClassique($credentials['email'], $credentials['password']);
                } else {
                    throw new Exception("Token ou email/mot de passe requis pour l'authentification.");
                }
                
            default:
                throw new Exception("Méthode d'authentification non reconnue.");
        }
    }

    /**
     * Change la méthode d'authentification
     * @param string $methode classic, email_link ou both
     */
    public function setMethodeAuth(string $methode) {
        if (!in_array($methode, $this->authMethods)) {
            throw new Exception("Méthode d'authentification non valide.");
        }
        $this->config['methode'] = $methode;
    }

    /**
     * Utilise une table personnalisée pour l'authentification
     * @param string $tableName Nom de la table
     * @param array $fieldMap Mapping des champs
     */
    public function utiliserTablePersonnalisee(string $tableName, array $fieldMap = []) {
        $this->useCustomTable = true;
        $this->tableUtilisateurs = $tableName;
        
        // Mettre à jour la configuration des champs si fournie
        if (isset($fieldMap['email_field'])) $this->config['email_field'] = $fieldMap['email_field'];
        if (isset($fieldMap['password_field'])) $this->config['password_field'] = $fieldMap['password_field'];
        if (isset($fieldMap['token_field'])) $this->config['token_field'] = $fieldMap['token_field'];
        if (isset($fieldMap['token_expiry_field'])) $this->config['token_expiry_field'] = $fieldMap['token_expiry_field'];
    }

    /**
     * Utilise la table d'authentification par défaut
     */
    public function utiliserTableAuthParDefaut() {
        $this->useCustomTable = false;
        $this->tableUtilisateurs = 'auth_users';
    }

    /**
     * Récupère les erreurs de validation
     * @return array
     */
    public function getErreurs(): array {
        return $this->validateur->getErreurs();
    }
}