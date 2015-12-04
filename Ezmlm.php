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

	/**
	 * Checks if a string is valid UTF-8; if not, converts it from $fallbackCharset
	 * to UTF-8; returns the UTFized string
	 */
	protected function utfize($str, $fallbackCharset='ISO-8859-1') {
		$result = $this->utfizeAndStats($str, $fallbackCharset);
		return $result[0];
	}

	/**
	 * Checks if a string is valid UTF-8; if not, converts it from $fallbackCharset
	 * to UTF-8; returns an array containing the UTFized string, and a boolean
	 * telling if a conversion was performed
	 */
	protected function utfizeAndStats($str, $fallbackCharset='ISO-8859-1') {
		$valid_utf8 = preg_match('//u', $str);
		if (! $valid_utf8) {
			$str = iconv($fallbackCharset, "UTF-8//TRANSLIT", $str);
		}
		return array($str, ($valid_utf8 !== false));
	}

	/**
	 * Comparison function for usort() returning threads having the greatest
	 * "last_message_id" first
	 */
	protected function sortMostRecentThreads($a, $b) {
		return $b['last_message_id'] - $a['last_message_id'];
	}

	/**
	 * Converts a "*" based pattern to a regexp
	 */
	protected function convertPattern($pattern) {
		if ($pattern == "*") {
			$pattern = false; // micro-optimization
		}
		if ($pattern != false) {
			$pattern = str_replace('*', '.*', $pattern);
			$pattern = '/^' . $pattern . '$/i';
		}
		return $pattern;
	}

	// ------------------ PARSING METHODS -------------------------

	protected function computeSubfolderAndId($id) {
		// ezmlm archive format : http://www.mathematik.uni-ulm.de/help/qmail/ezmlm.5.html
		$subfolder = intval($id / 100);
		$messageId = $id - (100 * $subfolder);
		if ($messageId < 10) {
			$messageId = '0' . $messageId;
		}
		return array($subfolder, $messageId);
	}

	protected function extractMessageMetadata($line1, $line2) {
		// Line 1 : get message number, subject hash and subject
		preg_match('/([0-9]+): ([a-z]+) (.*)/', $line1, $match1);
		// Line 2 : get date, author hash and hash
		preg_match('/\t([0-9]+) ([a-zA-Z][a-zA-Z][a-zA-Z]) ([0-9][0-9][0-9][0-9]) ([^;]+);([^ ]*) (.*)/', $line2, $match2);

		$message = null;
		if ($match1[1] != '') {
			// formatted return
			$message = array(
				"message_id" => intval($match1[1]),
				"subject_hash" => $match1[2],
				"subject" => $this->utfize($match1[3]),
				"message_date" => $match2[1] . ' ' . $match2[2] . ' ' . $match2[3] . ' ' . $match2[4],
				"author_hash" => $match2[5],
				"author_name" => $this->utfize($match2[6])
			);
		}
		return $message;
	}

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
			$message = $this->extractMessageMetadata($index[$idx+1], $index[$idx]);
			$messageId = $message['message_id'];
			$messages[$messageId] = $message;
			// read message contents on the fly
			// @TODO use message parser !!
			if ($includeMessages === true) {
				$messageContents = $this->readMessageContents($messageId);
				$messages[$messageId]["message_contents"] = $this->utfize($messageContents);
			} elseif ($includeMessages === "abstract") {
				$messageAbstract = $this->readMessageAbstract($messageId);
				$messages[$messageId]["message_contents"] = $this->utfize($messageAbstract);
			}
			$idx += 2;
			$read++;
		}

		return $messages;
	}

	/**
	 * Reads and returns metadata for the $id-th message in the current list's archive.
	 * If $contents is true, includes the message contents; if $contents is "abstract",
	 * includes only the first characters of the message
	 */
	protected function readMessage($id, $contents=true) {
		list($subfolder, $messageid) = $this->computeSubfolderAndId($id);
		$indexPath = $this->listPath . '/archive/' . $subfolder . '/index';
		// sioux trick to get the 2 lines concerning the message
		$grep = 'grep "' . $id . ': " "' . $indexPath . '" -A 1';
		exec($grep, $lines);
		//var_dump($lines); exit;

		$ret = $this->extractMessageMetadata($lines[0], $lines[1]);
		if ($contents === true) {
			$messageContents = $this->readMessageContents($id);
			$ret["message_contents"] = $this->utfize($messageContents);
		} elseif ($contents === "abstract") {
			$messageAbstract = $this->readMessageAbstract($id);
			$ret["message_contents"] = $this->utfize($messageAbstract);
		}
		return $ret;
	}

	protected function readMessageAbstract($id) {
		return $this->readMessageContents($id, true);
	}

	/**
	 * Reads and returns the contents of the $id-th message in the current list's archive
	 * If $abstract is true, reads only the first $this-->settings['messageAbstractSize'] chars
	 * of the message (default 128)
	 */
	protected function readMessageContents($id, $abstract=false) {
		// check valid id
		if (! is_numeric($id) || $id <=0) {
			throw new Exception("invalid message id [$id]");
		}
		list($subfolder, $messageId) = $this->computeSubfolderAndId($id);
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

	/**
	 * Returns the number of threads existing in the current list's archive
	 * @WARNING assumes that "wc" executable is present in /usr/bin
	 * @WARNING raw counting, does not try to merge linked threads (eg "blah", "Re: blah", "Fwd: blah"...)
	 */
	protected function countThreadsFromArchive() {
		$threadsFolder = $this->listPath . '/archive/threads';
		$command = "cat $threadsFolder/* | /usr/bin/wc -l";
		exec($command, $output);
		//echo "CMD: $command\n";

		return intval($output[0]);
	}

	/**
	 * Reads and returns all threads in the current list's archive, most recent activity first.
	 * If $pattern is set, only returns threads whose subject matches $pattern. Il $limit is set,
	 * only returns the $limit most recent (regarding activity ie. last message id) threads. If
	 * $flMessageDetails is set, returns details for first and last message (take a lot more time)
	 */
	protected function readThreadsFromArchive($pattern=false, $limit=false, $flMessageDetails=false) {
		$pattern = $this->convertPattern($pattern);
		// read all threads files in chronological order
		$threadsFolder = $this->listPath . '/archive/threads';
		$threadFiles = scandir($threadsFolder);
		array_shift($threadFiles); // remove "."
		array_shift($threadFiles); // remove ".."
		//var_dump($threadFiles);
		$threads = array();
		foreach ($threadFiles as $tf) {
			$subthreads = file($threadsFolder . '/' . $tf);
			foreach ($subthreads as $st) {
				$thread = $this->parseThreadLine($st, $pattern);
				if ($thread !== false) {
					// might see the same subject hash in multiple thread files (multi-month thread) but
					// thread files are read chronologically so latest data always overwrite previous ones
					$threads[$thread["subject_hash"]] = $thread;
				}
			}
		}
		//var_dump($threads);
		//exit;

		// attempt to merge linked threads (eg "blah", "Re: blah", "Fwd: blah"...)
		$this->attemptToMergeThreads($threads);

		// sort by last message id descending (newer messages have greater ids) and limit;
		// usort has the advantage of removing natural keys here, thus sending a list whose
		// order will be preserved
		usort($threads, array($this, 'sortMostRecentThreads'));
		if ($limit !== false) {
			$threads = array_slice($threads, 0, $limit);
		}

		// get subject informations from subjects/ folder (author, first message, last message etc.)
		// @WARNING takes a LOT of time for large lists
		if ($flMessageDetails) {
			foreach ($threads as &$thread) {
				$this->readThreadsFirstAndLastMessageDetails($thread);
			}
		}

		// include all messages ? with contents ? (@TODO paginate)
		return $threads;
	}

	/**
	 * Reads a thread information from the archive; if $details is true, will get details
	 * of first and last message
	 */
	protected function readThread($hash, $details=false) {
		$threadsFolder = $this->listPath . '/archive/threads';
		// find thread hash mention in threads files
		$command = "grep $hash -h -m 1 $threadsFolder/*";
		exec($command, $output);
		if (count($output) == 0) {
			throw new Exception("Thread [$hash] not found in archive");
		}
		$line = $output[0];
		$thread = $this->parseThreadLine($line);
		if ($details) {
			$this->readThreadsFirstAndLastMessageDetails($thread);
		}
		return $thread;
	}

	// $pattern is applied here to optimize a bit
	protected function parseThreadLine($line, $pattern=false) {
		$thread = false;
		preg_match('/^([0-9]+):([a-z]+) \[([0-9]+)\] (.+)$/', $line, $matches);
		//var_dump($matches);
		$lastMessageId = $matches[1];
		$subjectHash = $matches[2];
		$nbMessages = $matches[3];
		$subject = $matches[4];
		if ($pattern == false || preg_match($pattern, $subject)) {
			list($subject, $charsetConverted) = $this->utfizeAndStats($subject);
			$thread = array(
				"last_message_id" => intval($lastMessageId),
				"subject_hash" => $subjectHash,
				"nb_messages" => intval($nbMessages),
				"subject" => $subject,
				"charset_converted" => $charsetConverted
			);
		}
		return $thread;
	}

	/**
	 * Reads the first and last message metadata for thread $thread, and infers the author of the thread
	 */
	protected function readThreadsFirstAndLastMessageDetails(&$thread) {
		$thread["last_message"] = $this->readMessage($thread["last_message_id"], false);
		$thread["first_message_id"] = $this->getThreadsFirstMessageId($thread["subject_hash"]);
		// small optimization
		//echo "FMI: " . $thread["first_message_id"] . ", LMI: " . $thread["last_message_id"] . "<br/>";
		if ($thread["first_message_id"] != $thread["last_message_id"]) {
			//echo "read!<br/>";
			$thread["first_message"] = $this->readMessage($thread["first_message_id"], false);
		} else {
			//echo "--<br/>";
			$thread["first_message"] = $thread["last_message"];
		}
		// author of first message is the author of the thread @TODO remove unnecessary redundancy ?
		$thread['author'] = $thread["first_message"]["author_name"];
	}

	/**
	 * Reads all messages from the thread of hash $hash
	 */
	protected function readThreadsMessages($hash, $pattern=false, $contents=false) {
		$pattern = $this->convertPattern($pattern);
		$ids = $this->getThreadsMessagesIds($hash);
		// newest messages first
		$ids = array_reverse($ids);
		// read messages
		$messages = array();
		foreach ($ids as $id) {
			$message = $this->readMessage($id, $contents);
			if ($pattern == false || preg_match($pattern, $contents)) {
				$messages[] = $message;
			}
		}
		return $messages;
	}

	/**
	 * Returns the id of the first message in the thread of hash $hash
	 */
	protected function getThreadsFirstMessageId($hash) {
		$ids = $this->getThreadsMessagesIds($hash, 1);
		return $ids[0];
	}

	/**
	 * Returns the ids of the $limit first messages in the thread of hash $hash
	 */
	protected function getThreadsMessagesIds($hash, $limit=false) {
		$subjectFile = $this->getSubjectFile($hash);
		// read 2nd line (1st message) @WARNING assumes that sed is present on the system
		// $command = "sed '2q;d' $subjectFile";
		$command = "grep";
		if (is_numeric($limit) && $limit > 0) {
			$command .= " -m $limit";
		}
		$command .= " '^.*[0-9]\+:[0-9]\+:[a-z]\+ .\+$' $subjectFile";
		exec($command, $output);
		//echo "$hash <br/>";
		//var_dump($output); echo "<br/>";
		//exit;
		$ids = array();
		foreach ($output as $line) {
			// regexp starts with .* because sometimes the subject (1st line) contains a \n and
			// thus contaminates the second line (wtf?)
			preg_match('/^(.*[^0-9])?([0-9]+):([0-9]+):([a-z]+) (.+)$/', $line, $matches);
			//echo $matches[2] . "<br/>";
			$ids[] = intval($matches[2]);
		}
		//var_dump($ids);
		return $ids;
	}

	/**
	 * Computes and returns the path of the file in /subjects archive folder
	 * that concerns the subject of hash $hash
	 */
	protected function getSubjectFile($hash) {
		$hash2f = substr($hash,0,2);
		$hashEnd = substr($hash,2);
		$subjectsFolder = $this->listPath . '/archive/subjects';
		$subjectFile = $subjectsFolder . '/' . $hash2f . '/' . $hashEnd;
		return $subjectFile;
	}

	/**
	 * Tries to detect subjects that were incorrectly separated into 2 or more threads, because
	 * of encoding problems, "Re: " ou "Fwd: " mentions, and so on; @WARNING 2 totally different
	 * threads might have the same subject string, so make sure there really was an encoding or
	 * mention problem before merging !
	 */
	protected function attemptToMergeThreads(&$threads) {
		// detect if subject string is problematic
		// try to RAM it (reductio ad minima)
		// try to merge it or overwrite subject with RAMed subject
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

	public function getLists($pattern=false) {
		$pattern = $this->convertPattern($pattern);
		$dirP = opendir('.');
		$lists = array();
		while ($subdir = readdir($dirP)) {
			if (is_dir($subdir) && substr($subdir, 0, 1) != '.') {
				// presence of "lock" file means this is a list (ezmlm-web's strategy)
				if ($this->fileExistsInDir('lock', $subdir)) {
					if ($pattern != false) {
						// exclude from results if list name doesn't match search pattern
						if (preg_match($pattern, $subdir) != 1) {
							continue;
						}
					}
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

	public function getMessage($id, $contents=true) {
		$this->checkValidList();
		$msg = $this->readMessage($id, $contents);
		return $msg;
	}

	public function getAllThreads($pattern=false, $details=false) {
		$this->checkValidList();
		$threads = $this->readThreadsFromArchive($pattern, false, $details);
		return $threads;
	}

	public function countAllThreads() {
		$this->checkValidList();
		$nb = $this->countThreadsFromArchive();
		return $nb;
	}

	public function getLatestThreads($limit=10, $details=false) {
		$this->checkValidList();
		$threads = $this->readThreadsFromArchive(false, $limit, $details);
		return $threads;
	}

	public function getThread($hash, $details=true) {
		$this->checkValidList();
		$thread = $this->readThread($hash, $details);
		return $thread;
	}

	public function getAllMessagesByThread($hash, $pattern=false, $contents=false) {
		$this->checkValidList();
		$messages = $this->readThreadsMessages($hash, $pattern, $contents);
		return $messages;
	}

	public function countMessagesFromThread($hash) {
		$this->checkValidList();
		$thread = $this->readThread($hash);
		$nb = $thread["nb_messages"];
		return $nb;
	}
}
