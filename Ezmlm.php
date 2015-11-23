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

	/** various settings read from config */
	protected $settings;

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
		// various settings
		$this->settings = $this->config['settings'];
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
	 * @TODO replace by a simple file_exists() provided by PHP ?!
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
	protected function checkValidList() {
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

	/**
	 * Converts a string of letters (ex: "aBuD") to a command line switches
	 * string (ex: "-a -B -u -D") @WARNING only supports single letter switches !
	 */
	protected function getSwitches($options) {
		$switches = '';
		if (!empty($options)) {
			$switchesArray = array();
			$optionsArray = str_split($options);
			// not twice the same switch
			$optionsArray = array_unique($optionsArray);
			foreach ($optionsArray as $opt) {
				// only letters are allowed
				if (preg_match('/[a-zA-Z]/', $opt)) {
					$switchesArray[] = '-' . $opt;
				}
			}
			$switches = implode(' ', $switchesArray);
		}
		return $switches;
	}

	/**
	 * Sets an option in ezmlm config so that the "Reply-To:" header points
	 * to the list address and not the sender's
	 * @TODO find a proper way to know if it worked or not
	 * © copyleft David Delon 2005 - Tela Botanica / Outils Réseaux
	 */
	protected function setReplyToHeader() {
		$this->checkValidDomain();
		$this->checkValidList();
		// files to be modified
		$headerRemovePath = $this->listPath . '/headerremove';
		$headerAddPath = $this->listPath . '/headeradd';
		// commands
		$hrCommand = "echo -e 'reply-to' >> " . $headerRemovePath;
		$haCommand = "echo -e 'Reply-To: <#l#>@<#h#>' >> " . $headerAddPath;
		//echo $haCommand; exit;
		exec($hrCommand);
		exec($haCommand);
	}

	/**
	 * Generates an author's hash using the included makehash program
	 * (i) copied from original lib
	 */
	protected function makehash($str) {
		$str = preg_replace ('/>/', '', $str); // wtf ?
		$hash = $this->rt("makehash", $str, true);
		return $hash;
	}

	// ------------------ PARSING METHODS -------------------------

	/**
	 * Reads "num" file in list dir to get total messages count
	 */
	protected function countMessagesFromArchive() {
		$numFile = $this->listPath . '/num';
		if (! file_exists($numFile)) {
			throw new Exception('list has no num file');
		}
		$num = file_get_contents($numFile);
		$num = explode(':', $num);

		return intval($num[0]);
	}

	/**
	 * Reads $limit messages from the list archive. Beware: setting $limit to 0 means no limit.
	 * If $includeMessages is true, returns the parsed message contents along with the metadata;
	 * if $includeMessages is "abstract", returns only the first characters of the message.
	 */
	protected function readMessagesFromArchive($includeMessages=false, $limit=false) {
		// check valid limit
		if (! is_numeric($limit) || $limit <= 0) {
			$limit = false;
		}
		// idiot-proof attempt
		if ($includeMessages === true) { // unlimited abstracts are allowed
			if (! empty($this->settings['maxMessagesWithContentsReadableAtOnce']) && ($this->settings['maxMessagesWithContentsReadableAtOnce']) < $limit) {
				throw new Exception("cannot read more than " . $this->settings['maxMessagesWithContentsReadableAtOnce'] . " messages at once, if messages contents are included");
			}
		}

		$archiveDir = $this->listPath . '/archive';
		if (! is_dir($archiveDir)) {
			throw new Exception('list is not archived'); // @TODO check if archive folder exists in case a list is archived but empty
		}
		$archiveD = opendir($archiveDir);
		//echo "Reading $archiveDir \n";
		// get all subfolders
		$subfolders = array();
		while (($d = readdir($archiveD)) !== false) {
			if (preg_match('/[0-9]+/', $d)) {
				$subfolders[] = $d;
			}
		}
		// sort and reverse order (last messages first)
		sort($subfolders, SORT_NUMERIC);
		$subfolders = array_reverse($subfolders);
		//var_dump($subfolders);

		$messages = array();
		// read index files for each folder
		$idx = 0;
		$read = 0;
		$length = count($subfolders);
		while (($idx < $length) && ($limit == false || $limit > $read)) { // stop if enough messages are read
			$sf = $subfolders[$idx];
			$subMessages = $this->readMessagesFromArchiveSubfolder($sf, $includeMessages, ($limit - $read)); // @WARNING setting limit to 0 means unlimited
			$messages = array_merge($messages, $subMessages);
			$read += count($subMessages);
			$idx++;
		}

		// bye
		closedir($archiveD);

		return $messages;
	}

	/**
	 * Reads $limit messages from an archive subfolder (ex: archive/0, archive/1) - this represents maximum
	 * 100 messages - then returns all metadata for each message, along with the messages contents if
	 * $includeMessages is true; limits the output to $limit messages, if $limit is a valid number > 0;
	 * beware: setting $limit to 0 means no limit !
	 */
	protected function readMessagesFromArchiveSubfolder($subfolder, $includeMessages=false, $limit=false) {
		// check valid limit
		if (! is_numeric($limit) || $limit <= 0) {
			$limit = false;
		}

		//$indexF = fopen($this->listPath . '/archive/' . $subfolder . '/index', 'r');
		// read file backwards
		$index = file($this->listPath . '/archive/' . $subfolder . '/index');
		$index = array_reverse($index);
		// var_dump($index); exit;
		// read 2 lines at once - @WARNING considers file contents is always even !
		$length = count($index);
		$idx = 0;
		$read = 0;
		$messages = array();
		while ($idx < $length && ($limit == false || $limit > $read)) {
			// Line 1 : get date, author hash and hash
			$temp = $index[$idx];
			preg_match('/\t([0-9]+) ([a-zA-Z][a-zA-Z][a-zA-Z]) ([0-9][0-9][0-9][0-9]) ([^;]+);([^ ]*) (.*)/', $temp, $match2);
			// Line 2 : get message number, subject hash and subject
			$temp = $index[$idx+1];
			preg_match('/([0-9]+): ([a-z]+) (.*)/', $temp, $match1);

			if ($match1[1] != '') {
				$messageId = $match1[1];
				// formatted return
				$messages[$messageId] = array(
					"message_id" => $messageId, // comfort redundancy
					"subject_hash" => $match1[2],
					"subject" => $match1[3],
					"message_date" => $match2[1] . ' ' . $match2[2] . ' ' . $match2[3],
					"author_hash" => $match2[5],
					"author_name" => $match2[6]
				);
				// read message contents on the fly
				// @TODO use message parser !!
				if ($includeMessages === true) {
					$messageContents = $this->readMessage($messageId);
					$messages[$messageId]["message_contents"] = $messageContents;
				} elseif ($includeMessages === "abstract") {
					$messageAbstract = $this->readMessageAbstract($messageId);
					$messages[$messageId]["message_contents"] = $messageAbstract;
				}
			}
			$idx += 2;
			$read++;
		}

		return $messages;
	}

	protected function readMessageAbstract($id) {
		return $this->readMessage($id, true);
	}

	/**
	 * Reads and returns the contents of the $id-th message in the current list's archive
	 * If $abstract is true, reads only the first $this-->settings['messageAbstractSize'] chars
	 * of the message (default 128)
	 */
	protected function readMessage($id, $abstract=false) {
		// check valid id
		if (! is_numeric($id) || $id <=0) {
			throw new Exception("invalid message id [$id]");
		}
		// ezmlm archive format : http://www.mathematik.uni-ulm.de/help/qmail/ezmlm.5.html
		$subfolder = intval($id / 100);
		$messageId = $id - (100 * $subfolder);
		if ($messageId < 10) {
			$messageId = '0' . $messageId;
		}
		//echo "ID: $id, SF: $subfolder, MSG: $messageId\n";
		$messageFile = $this->listPath . '/archive/' . $subfolder . '/' . $messageId;
		if (! file_exists($messageFile)) {
			throw new Exception("message of id [$id] does not exist");
		}
		// read message
		if ($abstract) {
			$abstractSize = 128;
			if (! empty($this->settings['messageAbstractSize']) && is_numeric($this->settings['messageAbstractSize']) && $this->settings['messageAbstractSize'] > 0) {
				$abstractSize = $this->settings['messageAbstractSize'];
			}
			$msgF = fopen($messageFile, 'r');
			$message = fread($msgF, $abstractSize);
			fclose($msgF);
		} else {
			$message = file_get_contents($messageFile);
		}
		return $message;
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
		$switches = $this->getSwitches($options);
		$dotQmailFile = $this->domainPath . '/.qmail-' . $this->listName;
		$commandOptions = $switches . ' ' . $this->listPath . ' ' . $dotQmailFile . ' ' . $this->listName . ' ' . $this->domainName;
		//echo "CO: $commandOptions\n";
		$ret = $this->rt('ezmlm-make', $commandOptions);
		if ($ret) {
			$this->setReplyToHeader();
		}
		return $ret;
	}

	/**
	 * deletes a list : .qmail-[listname]-* files and /[listname] folder
	 */
	public function deleteList() {
		$this->checkValidList();
		$dotQmailFilesPrefix = $this->domainPath . '/.qmail-' . $this->listName;
		// list of .qmail files @WARNING depends on the options set when creating the list
		$dotQmailFiles = array(
			"", // list main file
			"-accept-default",
			"-default",
			"-digest-owner",
			"-digest-return-default",
			"-owner",
			"-reject-default",
			"-return-default"
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
			//echo "Testing [$filePath]:\n";
			if (file_exists($filePath) || is_link($filePath)) { // .qmail files are usually links, file_exists() returns false
				$command = 'rm ' . $filePath;
				//echo "command: " . $command . "\n";
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

	public function getSubscribers() {
		$this->checkValidList();
		$command = "ezmlm-list";
		$options = $this->listPath;
		//var_dump($command . " " . $options);
		$ret = $this->rt($command, $options, true);
		// ezmlm returns one result per line
		$ret = array_filter(explode("\n", $ret));
		return $ret;
	}

	public function getModerators() {
		$this->checkValidList();
		$command = "ezmlm-list";
		$options = $this->listPath . '/mod';
		//var_dump($command . " " . $options);
		$ret = $this->rt($command, $options, true);
		// ezmlm returns one result per line
		$ret = array_filter(explode("\n", $ret));
		return $ret;
	}

	public function getPosters() {
		$this->checkValidList();
		$command = "ezmlm-list";
		$options = $this->listPath . '/allow';
		//var_dump($command . " " . $options);
		$ret = $this->rt($command, $options, true);
		// ezmlm returns one result per line
		$ret = array_filter(explode("\n", $ret));
		return $ret;
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

	public function addModerator($moderatorEmail) {
		$this->checkValidEmail($moderatorEmail);
		$command = "ezmlm-sub";
		$options = $this->listPath . '/mod ' . $moderatorEmail;
		//var_dump($command . " " . $options);
		$ret = $this->rt($command, $options);
		return $ret;
	}

	public function deleteModerator($moderatorEmail) {
		$this->checkValidEmail($moderatorEmail);
		$command = "ezmlm-unsub";
		$options = $this->listPath . '/mod ' . $moderatorEmail;
		//var_dump($command . " " . $options);
		$ret = $this->rt($command, $options);
		return $ret;
	}

	public function addPoster($posterEmail) {
		$this->checkValidEmail($posterEmail);
		$command = "ezmlm-sub";
		$options = $this->listPath . '/allow ' . $posterEmail;
		//var_dump($command . " " . $options);
		$ret = $this->rt($command, $options);
		return $ret;
	}

	public function deletePoster($posterEmail) {
		$this->checkValidEmail($posterEmail);
		$command = "ezmlm-unsub";
		$options = $this->listPath . '/allow ' . $posterEmail;
		//var_dump($command . " " . $options);
		$ret = $this->rt($command, $options);
		return $ret;
	}

	public function countAllMessages() {
		$this->checkValidList();
		$nb = $this->countMessagesFromArchive();
		return $nb;
	}

	public function getAllMessages($contents=false) {
		$this->checkValidList();
		$msgs = $this->readMessagesFromArchive($contents);
		return $msgs;
	}

	public function getLatestMessages($contents=false, $limit=10) {
		$this->checkValidList();
		$msgs = $this->readMessagesFromArchive($contents, $limit);
		return $msgs;
	}
}
