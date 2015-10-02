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

	/** current mailing list domain (eg my-domain.org) */
	protected $domain;

	/** current mailing list */
	protected $listName;

	public function __construct() {
		// config
		if (file_exists(self::$CHEMIN_CONFIG)) {
			$this->config = json_decode(file_get_contents(self::$CHEMIN_CONFIG), true);
		} else {
			throw new Exception("file " . self::$CHEMIN_CONFIG . " doesn't exist");
		}

		// default domain
		$this->domain = $this->config['ezmlm']['domain'];
		$this->chdirToDomain();

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

	/**
	 * Sets current directory to the current domain directory
	 */
	protected function chdirToDomain() {
		$domainFolder = $this->config['ezmlm']['domainsPath'] . '/' . $this->domain;
		chdir($domainFolder);
	}

	public function setDomaine($domain) {
		$this->domain = $domain;
		$this->chdirToDomain();
	}

	public function getDomain() {
		return $this->domain;
	}

	public function setListName($listName) {
		$this->listName = $listName;
	}

	public function getListName() {
		return $this->listName;
	}

	/**
	 * Returns true if $fileName exists in directory $dir, false otherwise
	 */
	protected function fileExistsInDir($fileName, $dir) {
		$dirP = opendir($dir);
		$exists = false;
		while ($file = readdir($dirP) && ! $exists) {
			$exists = $exists && ($file == $fileName);
		}
		return $exists;
	}

	public function getLists() {
		$dirP = opendir('.');
		$lists = array();
		while ($subdir = readdir($dirP)) {
			// presence of "lock" file means this is a list (ezmlm-web's strategy)
			if ($this->fileExistsInDir('lock', $subdir)) {
				$lists[] = $subdir;
			}
		}
		closedir($dirP);
		return $lists;
	}
}