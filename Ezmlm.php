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

	/** absolute path of domains root (eg vpopmail's "domains" folder) */
	protected $domainsPath;

	/** current mailing list domain (eg "my-domain.org") */
	protected $domainName;

	/** absolute path of current domain @TODO rename not to confuse with $domainsPath ? */
	protected $domainPath;

	/** current mailing list (eg "my-list") */
	protected $listName;

	/** absolute path of current mailing list */
	protected $listPath;

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

		// domains path
		$this->domainsPath = $this->config['ezmlm']['domainsPath'];
		// default domain
		$this->setDomain($this->config['ezmlm']['domain']);
	}

	/**
	 * Sets current directory to the current domain directory
	 */
	protected function chdirToDomain() {
		if (! is_dir($this->domainPath)) {
			throw new Exception("domain path: cannot access directory [" . $this->domainPath . "]");
		}
		chdir($this->domainPath);
	}

	/**
	 * Runs an ezmlm-idx binary, located in $this->ezmlmIdxPath
	 * Output parameters are optional to reduce memory consumption
	 */
	protected function runEzmlmTool($tool, $optionsString, &$stdout=false, &$stderr=false) {
		// sanitize @TODO externalize and improve
		if (strpos($tool, "..") !== false || strpos($tool, "/") !== false) {
			throw new Exception("forbidden command: [$tool]");
		}

		// prepare proces opening
		$descriptorspec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("pipe", "w")
		);  
		// cautiousness
		$cwd = '/tmp';

		$process = proc_open($this->ezmlmIdxPath . '/' . $tool . ' ' . $optionsString, $descriptorspec, $pipes, $cwd);

		if (is_resource($process)) {
			// optionally write something to stdin
			fclose($pipes[0]);

			if ($stdout !== false) {
				$stdout = stream_get_contents($pipes[1]);
				//echo $stdout;
			}
			fclose($pipes[1]);
			//echo "\n";

			if ($stderr !== false) {
				$stderr = stream_get_contents($pipes[2]);
				//echo $stderr;
			}
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
	 * rt : Run ezmlm-idx tool; convenience method for runEzmlmTool()
	 * Throws an exception containing stderr if the command returns something else that 0;
	 * otherwise, returns true if $returnStdout is false (default), stdout otherwise
	 */
	protected function rt($tool, $optionsString, $returnStdout=false) {
		$ret = false;
		$stdout = $returnStdout;
		$stderr = null;
		// "smart" call to reduce memory consumption
		$ret = $this->runEzmlmTool($tool, $optionsString, $stdout, $stderr);
		// catch command error
		if ($ret !== 0) {
			throw new Exception($stderr);
		}
		// mixed return
		if ($returnStdout) {
			return $stdout;
		}
		return true;
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

	/**
	 * Throws an exception if $email is not valid, in the
	 * meaning of PHP's FILTER_VALIDATE_EMAIL
	 */
	protected function checkValidEmail($email) {
		if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new Exception("invalid email address [$email]");
		}
	}

	/**
	 * Throws an exception if $this->listName is not set
	 */
	protected function checkValidListName() {
		if (empty($this->listName)) {
			throw new Exception("please set a valid list");
		}
		if (!is_dir($this->listPath)) {
			throw new Exception("list [" . $this->listName . "] does not exist");
		}
	}

	/**
	 * Throws an exception if $this->listName is not set
	 */
	protected function checkValidDomain() {
		if (empty($this->domainName)) {
			throw new Exception("please set a valid domain");
		}
		if (!is_dir($this->domainPath)) {
			throw new Exception("domain [" . $this->domainName . "] does not exist");
		}
	}

	/*
	 * Returns true if list $name exists in $this->domainPath domain
	 */
	protected function listExists($name) {
		return is_dir($this->domainPath . '/' . $name);
	}

	// ------------------ API METHODS -----------------------------

	/**
	 * Sets the current domain to $domain and recomputes paths
	 */
	public function setDomain($domain) {
		$this->domainName = $domain;
		$this->domainPath = $this->domainsPath . '/' . $this->domainName;
		$this->chdirToDomain();
	}

	/**
	 * Returns the current domain (should always be set)
	 */
	public function getDomain() {
		return $this->domainName;
	}

	/**
	 * Sets the current list to $listName and recomputes paths
	 */
	public function setListName($listName) {
		$this->listName = $listName;
		if (! is_dir($this->domainPath)) {
			throw new Exception("please set a valid domain path before setting list name (current domain path: [" . $this->domainPath . "])");
		}
		$this->listPath = $this->domainPath . '/' . $this->listName;
	}

	/**
	 * Returns the current list
	 */
	public function getListName() {
		return $this->listName;
	}

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

	public function addList($name, $options=null) {
		$this->checkValidDomain();
		if ($this->listExists($name)) {
			throw new Exception("list [$name] already exists");
		} else {
			$this->setListName($name);
		}
		// convert options string (ex: "aBuD") to command switches (ex: "-a -B -u -D")
		$switches = '';
		if (!empty($options)) {
			// ...
		}
		$dotQmailFile = $this->domainPath . '/.qmail-' . $this->name;
		$commandOptions = $switches . ' ' . $this->listPath . ' ' . $dotQmailFile . ' ' . $this->listName . ' ' . $this->domainName;
		$ret = $this->rt('ezmlm-make', $commandOptions);
		if ($ret) {
			$ret = $this->rt('ezmlm-reply-to', $this->domainName . ' ' . $this->listName);
		}
		return $ret;
	}

	/**
	 * deletes a list : .qmail-[listname]-* files and /[listname] folder
	 */
	public function deleteList() {
		$this->checkValidListName();
		$dotQmailFilesPrefix = $this->domainPath . '/.qmail-' . $this->listName . '-';
		// list of .qmail files @WARNING depends on the options set when creating the list
		$dotQmailFiles = array(
			"default",
			"digest-owner",
			"digest-return-default",
			"owner",
			"reject-default",
			"return-default"
		);

		$out = array();
		$ret = 0;
		// delete list directory
		if (is_dir($this->listPath)) {
			$command = 'rm -r ' . $this->listPath;
			//echo $command . "\n";
			exec($command, $out, $retcode);
			$ret += $retcode;
		} else {
			throw new Exception("list [" . $this->listName . "] does not exist");
		}
		// delete all .qmail files
		foreach ($dotQmailFiles as $dqmf) {
			$filePath = $dotQmailFilesPrefix . $dqmf;
			if (file_exists($filePath)) {
				$command = 'rm ' . $filePath;
				//echo $command . "\n";
				exec($command, $out, $retcode);
				$ret += $retcode;
			}
		}

		// status
		if ($ret > 0) {
			throw new Exception("error while deleting list files");
		}
		return true;
	}

	public function addSubscriber($subscriberEmail) {
		$this->checkValidEmail($subscriberEmail);
		$command = "ezmlm-sub";
		$options = $this->listPath . ' ' . $subscriberEmail;
		//var_dump($command . " " . $options);
		$ret = $this->rt($command, $options);
		return $ret;
	}

	public function deleteSubscriber($subscriberEmail) {
		$this->checkValidEmail($subscriberEmail);
		$command = "ezmlm-unsub";
		$options = $this->listPath . ' ' . $subscriberEmail;
		//var_dump($command . " " . $options);
		$ret = $this->rt($command, $options);
		return $ret;
	}
}
