<?php

/**
 * Library for ezmlm discussion lists management
 */
class Ezmlm {

	/** JSON config */
	protected $config = array();
	public static $CONFIG_PATH = "config/config.json";

	/** ezmlm-idx tools path */
	protected $ezmlmIdxPath;

	/** Authentication management */
	protected $authAdapter;

	/** current mailing list domain (eg my-domain.org) */
	protected $domain;

	/** current mailing list */
	protected $listName;

	public function __construct() {
		// config
		if (file_exists(self::$CONFIG_PATH)) {
			$this->config = json_decode(file_get_contents(self::$CONFIG_PATH), true);
		} else {
			throw new Exception("file " . self::$CONFIG_PATH . " doesn't exist");
		}

		// ezmlm-idx tools path configuration
		$this->ezmlmIdxPath = $this->config['ezmlm-idx']['binariesPath'];

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

		// default domain
		$this->setDomain($this->config['ezmlm']['domain']);
	}

	/**
	 * Sets current directory to the current domain directory
	 */
	protected function chdirToDomain() {
		$domainFolder = $this->config['ezmlm']['domainsPath'] . '/' . $this->domain;
		chdir($domainFolder);
	}

	public function setDomain($domain) {
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
	 * ret : Run ezmlm-idx Tool
	 * Runs an ezmlm-idx binary, located in $this->ezmlmIdxPath
	 */
	public function rt($toolAndOptionsString, &$stdout, &$stderr) {
		// sanitize
		if (strpos($toolAndOptionsString, "..") !== false || strpos($toolAndOptionsString, "/") !== false) {
			throw new Exception("forbidden command: [$toolAndOptionsString]");
		}

		// prepare proces opening
		$descriptorspec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("pipe", "w")
		);  
		// cautiousness
		$cwd = '/tmp';

		$process = proc_open($this->ezmlmIdxPath . '/' . $toolAndOptionsString, $descriptorspec, $pipes, $cwd);

		if (is_resource($process)) {
			// optionally write something to stdin
			fclose($pipes[0]);

			$stdout = stream_get_contents($pipes[1]);
			//echo $stdout;
			fclose($pipes[1]);
			//echo "\n";

			$stderr = stream_get_contents($pipes[2]);
			//echo $stderr;
			fclose($pipes[2]);

			// It is important that you close any pipes before calling
			// proc_close in order to avoid a deadlock
			$return_value = proc_close($process);

			return $return_value;
		} else {
			throw new Exception('rt(): cound not create process');
		}
	}

	/**
	 * Returns true if $fileName exists in directory $dir, false otherwise
	 */
	protected function fileExistsInDir($fileName, $dir) {
		$dirP = opendir($dir);
		$exists = false;
		while (($file = readdir($dirP)) && ! $exists) {
			$exists = ($exists || ($file == $fileName));
		}
		return $exists;
	}

	// ------------------ API METHODS -----------------------------

	public function getLists() {
		$dirP = opendir('.');
		$lists = array();
		while ($subdir = readdir($dirP)) {
			if (is_dir($subdir) && substr($subdir, 0, 1) != '.') {
				// presence of "lock" file means this is a list (ezmlm-web's strategy)
				if ($this->fileExistsInDir('lock', $subdir)) {
					$lists[] = $subdir;
				}
			}
		}
		closedir($dirP);
		sort($lists); // @TODO ignore case ?
		return $lists;
	}

	public function getListInfo() {
		$this->rt("ezmlm-get", $out, $err);
		echo "Out: $out\nErr: $err";
	}
}
