<?php
	/**
	 * ☺ Amity Framework
	 * © Copyright Alexis Jehan
	 */
	/**
	 * Service de base de données utilisant l'extension « PDO »
	 * 
	 * Ce service permet de se connecter à une base de données en utilisant « PDO », c'est l'accès recommandé par défaut.
	 * 
	 * @package    framework
	 * @subpackage classes/services/database
	 * @author     Alexis Jehan <alexis.jehan2@gmail.com>
	 * @version    01/07/2020
	 * @since      23/09/2014
	 */
	final class PDODatabaseService extends DatabaseService {
		/*
		 * CHANGELOG:
		 * 01/07/2020: Ajout de la personnalisation d'options à la connexion à la base de données
		 * 06/06/2015: Gestion du charset personnalisé, compatible selon la version de PHP
		 * 23/09/2014: Version initiale
		 */

		/**
		 * Déclaration de la requête préparée
		 * 
		 * @var PDOStatement
		 */
		protected $statement;


		/**
		 * {@inheritdoc}
		 *
		 * @param  string  $host     Le nom de l'hôte
		 * @param  string  $port     Le port de connexion
		 * @param  string  $database Le nom de la base de données
		 * @param  string  $user     Le nom de l'utilisateur
		 * @param  string  $password Le mot de passe
		 * @param  string  $encoding L'encodage de connexion
		 * @param  array   $options  Les options de connexion
		 * @param  string  $driver   Le driver spécifique à la base de données [« mysql » par défaut]
		 * @return boolean           Vrai si la connexion a été effectuée
		 */
		protected function __connect($host, $port, $database, $user, $password, $encoding, array $options, $driver = 'mysql') {

			// Paramètres par défaut
			$settings = array(
				PDO::ATTR_PERSISTENT => TRUE,
				PDO::ATTR_EMULATE_PREPARES => TRUE,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			);

			// Pour les versions inférieures de MySQL, on définit le charset manuellement
			if('mysql' === $driver && version_compare(PHP_VERSION, '5.3.6', '<') && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
				$settings[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES '.$encoding;
			}

			$settings = $options + $settings;

			// Tentative de connexion via le DSN généré avec les paramètres
			try {
				$this->connection = new PDO($driver.':host='.$host.(!empty($port) ? ';port='.$port : '').';dbname='.$database.';charset='.$encoding, $user, $password, $settings);
			} catch(PDOException $exception) {

				// Impossible de se connecter à la base de données (serveur indisponible par exemple)
				// En mode de développement une exception sera lancée, autrement la connexion échouera et une page d'erreur d'affichera
				if(DEV_MODE) {
					$this->throwException($exception);
				}

				return FALSE;
			}

			// Seconde tentative du forçage de charset avec les serveurs MySQL
			if('mysql' === $driver && version_compare(PHP_VERSION, '5.3.6', '<') && !defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
				$this->connection->exec('SET NAMES '.$encoding);
			}

			// Bug: La désactivation de l'émulation des requêtes préparées produit une exception lors de l'utilisation d'un même placeholder nommé plusieurs fois
			// Exemple: SELECT * FROM users WHERE name = :login OR email = :login
			//                                           ------            ------
			/*if('mysql' === $driver) {
				$this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, version_compare($this->connection->getAttribute(PDO::ATTR_SERVER_VERSION), '5.1.17', '<'));
			}*/

			return TRUE;
		}

		/**
		 * {@inheritdoc}
		 *
		 * @return boolean Vrai si la déconnexion a été effectuée
		 */
		protected function __disconnect() {
			$this->statement = NULL;
			$this->connection = NULL;

			return TRUE;
		}

		/**
		 * {@inheritdoc}
		 *
		 * @param  string          $query La requête SQL à exécuter
		 * @return DatabaseService        L'instance courante
		 */
		public function query($query) {
			try {
				$this->statement = $this->connection->prepare($query);
			} catch(PDOException $exception) {
				$this->throwException($exception);
			}

			return $this;
		}

		/**
		 * {@inheritdoc}
		 *
		 * @param  string          $key   L'identifiant de la variable dans la requête
		 * @param  string          $value La valeur à associer
		 * @param  integer         $type  Le drapeau du type de la variable [vide par défaut]
		 * @return DatabaseService        L'instance courante
		 */
		public function bind($key, $value, $type = NULL) {

			// Si le type n'est pas renseigné on le détermine
			if(NULL === $type) {
				switch(TRUE) {
					case is_int ($value): $type = self::PARAM_INT;  break;
					case is_bool($value): $type = self::PARAM_BOOL; break;
					case NULL === $value: $type = self::PARAM_NULL; break;
					default: $type = self::PARAM_STR;
				}
			}

			$this->statement->bindValue($key, $value, $type);

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		protected function __execute() {
			try {
				$this->statement->execute();
			} catch(PDOException $exception) {
				$this->throwException($exception);
			}
		}

		/**
		 * {@inheritdoc}
		 *
		 * @param  integer $fetch La méthode de récupération [« DatabaseService::FETCH_ASSOC » par défaut]
		 * @return array          La ligne de résultat
		 */
		public function row($fetch = self::FETCH_ASSOC) {
			try {
				return $this->statement->fetch($fetch);
			} catch(PDOException $exception) {
				$this->throwException($exception);
			}
		}

		/**
		 * {@inheritdoc}
		 *
		 * @param  integer $fetch La méthode de récupération [« DatabaseService::FETCH_ASSOC » par défaut]
		 * @return array          Les lignes de résultats
		 */
		public function rows($fetch = self::FETCH_ASSOC) {
			try {
				return $this->statement->fetchAll($fetch);
			} catch(PDOException $exception) {
				$this->throwException($exception);
			}
		}

		/**
		 * {@inheritdoc}
		 *
		 * @param  integer $number Le numéro de la colonne
		 * @return mixed           La cellule
		 */
		public function column($number = 0) {
			try {
				return $this->statement->fetchColumn($number);
			} catch(PDOException $exception) {
				$this->throwException($exception);
			}
		}

		/**
		 * {@inheritdoc}
		 *
		 * @param  integer $number Le numéro de la colonne
		 * @return array           Les cellules
		 */
		public function columns($number = 0) {
			try {
				return $this->statement->fetchAll(PDO::FETCH_COLUMN, $number);
			} catch(PDOException $exception) {
				$this->throwException($exception);
			}
		}

		/**
		 * {@inheritdoc}
		 *
		 * @return integer Le nombre de lignes retournées ou altérées
		 */
		public function count() {
			return $this->statement->rowCount();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @param  boolean         $enabled Activation si vrai [« TRUE » par défaut]
		 * @return DatabaseService          L'instance courante
		 */
		public function autoCommit($enabled = TRUE) {
			$this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT , $enabled);

			return $this;
		}

		/**
		 * {@inheritdoc}
		 *
		 * @return DatabaseService L'instance courante
		 */
		public function beginTransaction() {
			$this->connection->beginTransaction();

			return $this;
		}

		/**
		 * {@inheritdoc}
		 *
		 * @return DatabaseService L'instance courante
		 */
		public function commit() {
			$this->connection->commit();

			return $this;
		}

		/**
		 * {@inheritdoc}
		 *
		 * @return DatabaseService L'instance courante
		 */
		public function rollback() {
			$this->connection->rollBack();

			return $this;
		}

		/**
		 * {@inheritdoc}
		 *
		 * @return string Le nom spécifique du service utilisé
		 */
		public function getAccessName() {
			return 'PDO ['.$this->connection->getAttribute(PDO::ATTR_DRIVER_NAME).']';
		}

		/**
		 * Adaptation du lancement d'une exception
		 *
		 * @param PDOException $exception L'exception provoquée et à adapter
		 */
		protected function throwException(PDOException $exception) {
			$code = $exception->getCode();
			$message = $exception->getMessage();

			if(0 === strpos($message, 'SQLSTATE[')) {
				preg_match('/^SQLSTATE\[\w+\]:\s*(?:[^:]+:\s*(\d*)\s*)?(.*)/', $message, $matches) || preg_match('/^SQLSTATE\[\w+\]\s*\[(\d+)\]\s*(.*)/', $message, $matches);
				$code = !empty($matches[1]) ? $matches[1] : 0;
				$message = $matches[2];
			}

			// Puis on lance la nouvelle exception adaptée
			throw new DatabaseException(/*utf8_encode(*/$message/*)*/, $code);
		}
	}

	// On vérifie que l'extension est disponible
	if(!extension_loaded('PDO')) {
		throw new SystemException('"%s" extension is not available', 'PDO');
	}
?>