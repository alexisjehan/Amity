<?php
	/**
	 * ☺ Amity Framework
	 * © Copyright Alexis Jehan
	 */
	/**
	 * Template de rendu d'un contenu
	 * 
	 * Cette classe charge le contenu d'un fichier de template, et y associe éventuellement des valeurs pour générer un rendu.
	 * 
	 * @package    framework
	 * @subpackage classes/utils
	 * @author     Alexis Jehan <alexis.jehan2@gmail.com>
	 * @version    26/02/2016
	 * @since      05/06/2014
	 */
	final class Template {
		/*
		 * CHANGELOG:
		 * 26/02/2016: L'échappement des caractères se fait désormais par défaut, et une nouvelle méthode pour ne pas le faire pour le HTML est aussi disponible
		 * 13/02/2016: Changement d'association des valeurs, ces dernières n'étant plus disponibles en tant qu'attributs de classe mais désormais en variables locales grâce à la fonction « extract() »
		 * 27/07/2015: Ajout d'une méthode d'échappement, qui remplace l'historique classe « XSS »
		 * 06/05/2015: Ajout d'une méthode statique pour charger un template sans instancier, et implémentation de Content
		 * 12/01/2015: Ajout du TemplateLoader, permettant de stocker les templates dans des sous-dossiers personnalisés
		 * 21/12/2014: Ajout d'une méthode prenant un tableau de clés plutôt que de le faire individuellement
		 * 05/06/2014: Version initiale
		 */

		/**
		 * Chargeur qui donne le fichier d'un template selon son nom
		 * 
		 * @var TemplateLoader
		 */
		private static $loader;

		/**
		 * Nom du template
		 * 
		 * @var string
		 */
		protected $name;

		/**
		 * Emplacement du fichier de template
		 * 
		 * @var string
		 */
		protected $file;

		/**
		 * Tableau de variables à associer au template
		 * 
		 * @var array
		 */
		protected $variables = array();


		/**
		 * Initialisation de la classe, instanciation du chargeur de templates
		 */
		public static function init() {

			// S'il n'a pas encore été instantié, on crée le chargeur statique, et on ajoute l'emplacement de l'application
			if(NULL === self::$loader) {
				self::$loader = new TemplateLoader();
				self::$loader->add(TEMPLATES_DIR)->load();
			}
		}

		/**
		 * Constructeur d'un template
		 *
		 * @param string $name Le nom du template
		 */
		public function __construct($name) {
			$this->name = $name;

			// On tente de récupérer un fichier correspondant au nom donné
			if(!$this->file = self::$loader->getFile($name)) {
				throw new SystemException('Unable to find a template file matching "%s"', $name);
			}
		}

		/**
		 * Association d'une variable dans le template
		 *
		 * @param  string   $name   Le nom de la variable
		 * @param  mixed    $value  La valeur de la variable
		 * @param  boolean  $escape Booléen indiquant si on doit échapper la valeur ou non [« TRUE » par défaut]
		 * @return Template         L'instance courante
		 */
		public function bind($name, $value, $escape = TRUE) {
			$this->variables[$name] = $escape ? $this->escape($value) : $value;

			return $this;
		}

		/**
		 * Association d'un contenu HTML dans le template, ce dernier n'étant pas échappé
		 *
		 * @param  string   $name    Le nom de la variable
		 * @param  string   $content Le contenu HTML
		 * @return Template          L'instance courante
		 */
		public function bindHtml($name, $content) {
			$this->bind($name, $content, FALSE);

			return $this;
		}

		/**
		 * Association de plusieurs variables dans le fichier de template
		 *
		 * @param  array    $variables Les variables à associer
		 * @param  boolean  $escapeAll Booléen indiquant si on doit échapper les valeurs ou non [« TRUE » par défaut]
		 * @return Template            L'instance courante
		 */
		public function bindArray(array $variables, $escapeAll = TRUE) {

			// Le tableau doit être associatif
			if($variables === array_values($variables)) {
				throw new InvalidParameterException('The template binding array must be associative', $name);
			}

			$this->variables += $escapeAll ? $this->escape($variables) : $variables;

			return $this;
		}

		/**
		 * Génération du rendu
		 *
		 * @param  array   $variables Un tableau de variables à éventuellement associer [optionnel]
		 * @param  boolean $escapeAll Booléen indiquant si on doit échapper les valeurs ou non [« TRUE » par défaut]
		 * @return string             Le rendu généré
		 */
		public function render(array $variables = array(), $escapeAll = TRUE) {

			// Si une association est renseignée on la fait avant de remplir le template
			if(!empty($variables)) {
				$this->bindArray($variables, $escapeAll);
			}

			ob_start();
			extract($this->variables);
			require($this->file);
			return ob_get_clean();
		}

		/**
		 * Échappe les caractères HTML d'une variable récursivement
		 *
		 * @param  string $variable La variable à échapper
		 * @return string           La variable échappée
		 */
		public function escape($variable) {

			// Si c'est un tableau, on échappe chaque élément qui le compose
			if(is_array($variable)) {
				foreach($variable as $name => $value) {
					$variable[$name] = $this->escape($value);
				}

			// Si c'est un objet, on échappe chacun de ses attributs
			} else if(is_object($variable)) {
				$values = get_class_vars(get_class($variable));
				foreach($values as $name => $value) {
					$variable->{$name} = $this->escape($value);
				}
			} else {
				$variable = htmlspecialchars($variable, ENT_COMPAT, 'UTF-8', FALSE);
			}

			return $variable;
		}

		/**
		 * Dé-échappement les caractères HTML d'une variable récursivement
		 *
		 * @param  string $variable La variable à dé-échapper
		 * @return string           La variable dé-échappée
		 */
		public function unescape($variable) {

			// Si c'est un tableau, on échappe chaque élément qui le compose
			if(is_array($variable)) {
				foreach($variable as $name => $value) {
					$variable[$name] = $this->unescape($value);
				}

			// Si c'est un objet, on échappe chacun de ses attributs
			} else if(is_object($variable)) {
				$values = get_class_vars(get_class($variable));
				foreach($values as $name => $value) {
					$variable->{$name} = $this->unescape($value);
				}
			} else {
				$variable = htmlspecialchars_decode($variable, ENT_COMPAT);
			}
			return $variable;
		}

		/**
		 * Retourne le nom du template
		 * 
		 * @return string Le nom du template
		 */
		public function getName() {
			return $this->name;
		}

		/**
		 * Retourne l'emplacement du fichier de template
		 * 
		 * @return string L'emplacement du fichier de template
		 */
		public function getFile() {
			return $this->file;
		}

		/**
		 * Indique si un template existe ou non
		 *
		 * @param  string  $name Le nom du template
		 * @return boolean       Vrai si le template existe
		 */
		public static function is($name) {
			return NULL !== self::$loader->getFile($name);
		}

		/**
		 * Méthode pratique de chargement du rendu d'un template
		 *
		 * @param  string  $name      Le nom du template
		 * @param  array   $variables Les variables à associer [optionnel]
		 * @param  boolean $escapeAll Booléen indiquant si on doit échapper les valeurs ou non [« TRUE » par défaut]
		 * @return string             Le rendu généré
		 */
		public static function load($name, $variables = array(), $escapeAll = TRUE) {
			$template = new self($name);
			return $template->render($variables, $escapeAll);
		}
	}

	// Initialisation de la classe au chargement de cette dernière
	Template::init();
?>