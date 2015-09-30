<?php

/**
 * Library for ezmlm discussion lists management
 */
class Ezmlm {

	/** JSON config */
	protected $config = array();
	public static $CHEMIN_CONFIG = "config/config.json";

	/** Authentication management */
	protected $authAdapter;

	/** current mailing list */
	protected $listName;

	public function __construct() {
		// config
		if (file_exists(self::$CHEMIN_CONFIG)) {
			$this->config = json_decode(file_get_contents(self::$CHEMIN_CONFIG), true);
		} else {
			throw new Exception("file " . self::$CHEMIN_CONFIG . " doesn't exist");
		}

		// authentication adapter / rights management
		$this->authAdapter = null;
		// rights management is not mandatory
		if (! empty($this->config['authAdapter'])) {
			$authAdapterName = $this->config['authAdapter'];
			$authAdapterDir = strtolower($authAdapterName);
			$authAdapterPath = 'auth/' . $authAdapterDir . '/' . $authAdapterName . '.php';
			if (strpos($authAdapterName, "..") != false || $authAdapterName == '' || ! file_exists($authAdapterPath)) {
				throw new Exception ("auth adapter " . $authAdapterPath . " doesn't exist");
			}
			require $authAdapterPath;
			// passing config to the adapter - 
			$this->authAdapter = new $authAdapterName($this->config);
		}
	}

	public function setListName($listName) {
		$this->listName = $listName;
	}

	public function getListName() {
		return $this->listName;
	}

	/**
	 * Si $inverse est true, indique à l'adapteur que les critères de recherche
	 * devront être inversés
	 */
	public function setInverseCriteria($inverse) {
		$this->storageAdapter->setInverseCriteria($inverse);
	}

	/**
	 * Calcule une clef de fichier à partir du chemin et du nom
	 */
	public function computeKey($path, $fileName) {
		return $this->storageAdapter->computeKey($path, $fileName);
	}

	/**
	 * Retourne true si $string est une clef
	 * @WARNING difficile à rendre déterministe !
	 */
	public function isKey($string) {
		return $this->storageAdapter->isKey($string);
	}

	/**
	 * Retourne un fichier à partir de sa clef
	 */
	public function getByKey($key) {
		return $this->storageAdapter->getByKey($key);
	}

	/**
	 * Retourne une liste de dossiers se trouvant sous $path; si $recursive est
	 * true, renvoie aussi leurs sous-dossiers
	 */
	public function getFolders($path, $recursive=false) {
		return $this->storageAdapter->getFolders($path, $recursive);
	}

	/**
	 * Retourne une liste de fichiers dont les noms correspondent à $name; si
	 * $trict est true, compare avec un "=" sinon avec un "LIKE"
	 */
	public function getByName($name, $strict=false) {
		return $this->storageAdapter->getByName($name, $strict);
	}

	/**
	 * Retourne une liste de fichiers se trouvant dans le répertoire $path; si
	 * $recursive est true, cherchera dans tous les sous-répertoires; si
	 * $includeFolders est true, incluera les dossiers présents sous le chemin
	 */
	public function getByPath($path, $recursive=false, $includeFolders=false) {
		return $this->storageAdapter->getByPath($path, $recursive, $includeFolders);
	}

	/**
	 * Retourne une liste de fichiers dont les mots-clefs sont $keywords
	 * (séparés par des virgules ); si $mode est "OR", un "OU" sera appliqué
	 * entre les mots-clefs, sinon un "ET"; si un mot-clef est préfixé par "!",
	 * on cherchera les fichiers n'ayant pas ce mot-clef
	 */
	public function getByKeywords($keywords, $mode="AND") {
		return $this->storageAdapter->getByKeywords($keywords, $mode);
	}

	/**
	 * Retourne une liste de fichiers appartenant aux groupes $groups
	 * (séparés par des virgules ); si $mode est "OR", un "OU" sera appliqué
	 * entre les groupes, sinon un "ET"; si un groupe est préfixé par "!", on
	 * cherchera les fichiers n'appartenant pas à ce groupe
	 * @TODO gérer les droits
	 */
	public function getByGroups($groups, $mode="AND") {
		return $this->storageAdapter->getByGroups($groups, $mode);
	}

	/**
	 * Retourne une liste de fichiers appartenant à l'utilisateur $user
	 * @TODO gérer les droits
	 */
	public function getByUser($user) {
		return $this->storageAdapter->getByUser($user);
	}

	/**
	 * Retourne une liste de fichiers dont le type MIME est $mimetype
	 */
	public function getByMimetype($mimetype) {
		return $this->storageAdapter->getByMimetype($mimetype);
	}

	/**
	 * Retourne une liste de fichiers dont la licence est $license
	 */
	public function getByLicense($license) {
		return $this->storageAdapter->getByLicense($license);
	}

	/**
	 * Retourne une liste de fichiers en fonction de leur date (@TODO de création
	 * ou de modification ?) : si $date1 et $date2 sont spécifiées, renverra les
	 * fichiers dont la date se trouve entre les deux; sinon, comparera à $date1
	 * en fonction de $operator ("=", "<" ou ">")
	 */
	public function getByDate($dateColumn, $date1, $date2, $operator="=") {
		return $this->storageAdapter->getByDate($dateColumn, $date1, $date2, $operator);
	}

	/**
	 * Recherche avancée - retourne une liste de fichiers correspondant aux
	 * critères de recherche contenus dans $searchParams (paires de
	 * clefs / valeurs); le critère "mode" peut valoir "OR" (par défaut) ou
	 * "AND"
	 */
	public function search($searchParams=array()) {
		return $this->storageAdapter->search($searchParams);
	}

	/**
	 * Ajoute le fichier $file au stock, dans le chemin $path, avec la clef $key,
	 * les mots-clefs $keywords (séparés par des virgules), les groupes $groupes
	 * (séparés par des virgules), les permissions $permissions, la licence
	 * $license et les métadonnées $meta (portion de JSON libre). Si le fichier
	 * existe déjà, il sera remplacé
	 */
	public function addOrUpdateFile($file, $path, $name, $keywords=null, $groups=null, $permissions=null, $license=null, $meta=null) {
		return $this->storageAdapter->addOrUpdateFile($file, $path, $name, $keywords, $groups, $permissions, $license, $meta);
	}

	/**
	 * Met à jour les métadonnées du fichier identifié par $key; si
	 * $newname est fourni, renomme le fichier
	 */
	public function updateByKey($key, $newname=null, $newpath=null, $keywords=null, $groups=null, $permissions=null, $license=null, $meta=null) {
		return $this->storageAdapter->updateByKey($key, $newname, $newpath, $keywords, $groups, $permissions, $license, $meta);
	}

	/**
	 * Supprime le fichier identifié par $key; si $keepFile est true, ne
	 * supprime que la référence mais conserve le fichier dans le stockage
	 */
	public function deleteByKey($key, $keepFile=false) {
		return $this->storageAdapter->deleteByKey($key, $keepFile);
	}

	/**
	 * Retourne les attributs (métadonnées) du fichier identifié par $key,
	 * mais pas le fichier lui-même
	 */
	public function getAttributesByKey($key) {
		return $this->storageAdapter->getAttributesByKey($key);
	}
}