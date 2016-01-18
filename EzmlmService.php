<?php

require_once 'BaseService.php';
require_once 'Ezmlm.php';

/**
 * REST API to access an ezmlm list
 */
class EzmlmService extends BaseService {

	/** JSON Autodocumentation */
	public static $AUTODOC_PATH = "autodoc.json";

	/** Ezmlm lib */
	protected $lib;

	/** current mailing list if any */
	protected $listName;

	/** default number of messages returned by "latest messages" command */
	protected $defaultMessagesLimit;

	/** default number of threads returned by "latest threads" command */
	protected $defaultThreadsLimit;

	public function __construct() {
		parent::__construct();
		// Ezmlm lib
		$this->lib = new Ezmlm();

		// additional settings
		$this->defaultMessagesLimit = $this->config['settings']['defaultMessagesLimit'];
		$this->defaultThreadsLimit = $this->config['settings']['defaultThreadsLimit'];
	}

	/**
	 * Sends multiple results in a JSON object
	 */
	protected function sendMultipleResults($results) {
		$this->sendJson(
			array(
				"count" => count($results),
				"results" => $results
			)
		);
	}

	protected function getUrlForCurrentList() {
		return $this->config["domain_root"] . $this->config["base_uri"] . "/lists/" . $this->listName;
	}

	/**
	 * Adds an "href" field ton any attachment found, containing a URL to get a
	 * message's attachment
	 */
	protected function buildAttachmentsLinks(&$messages) {
		if (isset($messages["message_contents"])) { // single message
			$mess = array(&$messages);
		} else { // multiple messages
			$mess = &$messages;
		}
		// build links
		foreach ($mess as &$msg) {
			if (! empty($msg["message_contents"]) && isset($msg["message_contents"]["attachments"])) {
				foreach ($msg["message_contents"]["attachments"] as &$attch) {
					$url = $this->getUrlForCurrentList() . "/messages/" . $msg["message_id"] . "/attachments/" . urlencode($attch["filename"]);
					$attch["href"] = $url;
				}
			}
		}
	}

	/**
	 * A version of explode() that preserves NULL values - allows to make the
	 * difference between '' and NULL in multiple parameters, like "keywords"
	 */
	/*protected function explode($delimiter, $string) {
		if ($string === null) {
			return null;
		} else {
			return explode($delimiter, $string);
		}
	}*/

	/**
	 * Returns true if $val is true or "true", false if $val is
	 * false or "false", $val if $val is any other value
	 */
	protected function parseBool($val) {
		if ($val === true || $val === "true") {
			return true;
		}
		if ($val === false || $val === "false") {
			return false;
		}
		return $val;
	}

	/**
	 * Service autodescription
	 */
	protected function usage() {
		$rootUri = $this->domainRoot . $this->baseURI . "/";
		// reading JSON autodoc and replacing root URI
		if (file_exists(self::$AUTODOC_PATH)) {
			$infos = json_decode(file_get_contents(self::$AUTODOC_PATH), true);
			foreach ($infos['uri-patterns'] as &$up) {
				foreach($up as $k => &$v) {
					$up[$k] = str_replace("__ROOTURI__", $rootUri, $up[$k]);
				}
			}
			// calling usage() implies that a bad request was sent
			$this->sendError($infos);
		} else {
			$this->sendError("wrong URI");
		}
	}

	protected function get() {
		// positive response by default
		http_response_code(200);

		// we need at least one resource
		if (count($this->resources) < 1) {
			$this->usage();
			return false;
		}

		$firstResource = array_shift($this->resources);
		// currently only "lists" is supported
		switch($firstResource) {
			case "lists":
				// no more resources ?
				if (count($this->resources) == 0) {
					$this->getLists();
				} else {
					$nextResource = array_shift($this->resources);
					if ($nextResource == "search") {
						// no more resources ?
						if (count($this->resources) == 0) {
							$this->usage();
						} else {
							$this->searchLists($this->resources[0]);
						}
					} else {
						// storing list name
						$this->listName = $nextResource;
						// defining list name once for all
						$this->lib->setListName($this->listName);
						// no more resources ?
						if (count($this->resources) == 0) {
							$this->getListInfo();
						} else { // moar !!
							$nextResource = array_shift($this->resources);
							switch ($nextResource) {
								case 'options':
									$this->getListOptions();
									break;
								case 'subscribers':
									$this->getSubscribers();
									break;
								case 'allowed':
									$this->getAllowed();
									break;
								case 'moderators':
									$this->getModerators();
									break;
								case 'threads':
									$this->getByThreads();
									break;
								case 'authors':
									$this->getByAuthors();
									break;
								case 'messages':
									$this->getByMessages();
									break;
								default:
									$this->usage();
									return false;
							}
						}
					}
					break;
				}
			default:
				$this->usage();
				return false;
		}
	}

	/**
	 * Returns a list of the available lists
	 */
	protected function getLists() {
		//echo "list of lists !";

		$lists = $this->lib->getLists();
		$this->sendMultipleResults($lists);
	}

	/**
	 * Searches among available lists
	 */
	protected function searchLists($pattern) {
		//echo "Search lists: $pattern\n";
		$lists = $this->lib->getLists($pattern);
		$this->sendMultipleResults($lists);
	}

	/**
	 * Returns information about a list
	 */
	protected function getListInfo() {
		//echo "info for list [" . $this->listName . "]";
		$info = $this->lib->getListInfo();
		$this->sendJson($info);
	}

	protected function getListOptions() {
		echo "options for list [" . $this->listName . "]";
	}

	protected function getSubscribers() {
		// "count" switch ?
		$count = ($this->getParam('count') !== null);
		//echo "getSubscribers(); count : "; var_dump($count);
		$res = $this->lib->getSubscribers($count);
		if ($count) {
			$this->sendJson(count($res)); // bare number
		} else {
			$this->sendMultipleResults($res);
		}
	}

	protected function getAllowed() {
		// "count" switch ?
		$count = ($this->getParam('count') !== null);
		//echo "getAllowed(); count : "; var_dump($count);
		$res = $this->lib->getAllowed($count);
		if ($count) {
			$this->sendJson(count($res)); // bare number
		} else {
			$this->sendMultipleResults($res);
		}
	}

	protected function getModerators() {
		// "count" switch ?
		$count = ($this->getParam('count') !== null);
		//echo "getModerators(); count : "; var_dump($count);
		$res = $this->lib->getModerators($count);
		if ($count) {
			$this->sendJson(count($res)); // bare number
		} else {
			$this->sendMultipleResults($res);
		}
	}

	/**
	 * Entry point for /threads/* URIs
	 */
	protected function getByThreads() {
		// no more resources ?
		if (count($this->resources) == 0) {
			$this->getAllThreads();
		} else {
			$nextResource = array_shift($this->resources);
			switch ($nextResource) {
				case "search":
					// no more resources ?
					if (count($this->resources) == 0) {
						$this->usage();
					} else {
						$this->searchThreads($this->resources[0]);
					}
					break;
				case "latest":
					// more resources ?
					$limit = false;
					if (count($this->resources) > 0) {
						$limit = array_shift($this->resources);
					}
					$this->getLatestThreads($limit);
					break;
				default:
					// mention of a specific thread
					$threadHash = $nextResource;
					// no more resoures ?
					if (count($this->resources) == 0) {
						$this->getThreadInfo($threadHash);
					} else {
						$nextResource = array_shift($this->resources);
						switch ($nextResource) {
							case "messages":
								$this->getMessagesByThread($threadHash);
								break;
							default:
								$this->usage();
								return false;
						}
					}
			}
		}
	}

	/**
	 * Returns all threads from the current list
	 */
	protected function getAllThreads() {
		return $this->searchThreads(false);
	}

	/**
	 * Searches among available lists
	 */
	protected function searchThreads($pattern) {
		$count = ($this->getParam('count') !== null);
		$details = $this->parseBool($this->getParam('details'));
		$sort = $this->getParam('sort', 'desc');
		$offset = $this->getParam('offset', 0);
		$limit = $this->getParam('limit', null);
		//echo "getAllThreads()";
		if ($count) {
			$res = $this->lib->countAllThreads();
			$this->sendJson($res);
		} else {
			// @TODO manage "details" switch
			$threads = $this->lib->getAllThreads($pattern, $limit, $details, $sort, $offset);
			$this->sendMultipleResults($threads);
		}
	}

	protected function getLatestThreads($limit=false) {
		if ($limit === false) {
			$limit = $this->defaultThreadsLimit;
		}
		$details = ($this->getParam('details') !== null);
		//$contents = $this->parseBool($this->getParam('contents'));
		//echo "getLatestThreads($limit, $details)";
		$res = $this->lib->getLatestThreads($limit, $details);
		$this->sendMultipleResults($res);
	}

	protected function getThreadInfo($hash) {
		//echo "getThreadInfo($hash)";
		$details = ($this->getParam('details') !== null);
		$res = $this->lib->getThread($hash, $details);
		$this->sendJson(array(
			"hash" => $hash,
			"thread" => $res
		));
	}

	protected function getMessagesByThread($hash) {
		// no more resources ?
		if (count($this->resources) == 0) {
			$this->getAllMessagesByThread($hash);
		} else {
			$nextResource = array_shift($this->resources);
			switch ($nextResource) {
				case "latest":
					// more resources ?
					$limit = false;
					if (count($this->resources) > 0) {
						$limit = array_shift($this->resources);
					}
					$this->getLatestMessagesByThread($hash, $limit);
					break;
				case "search":
					// no more resources ?
					if (count($this->resources) == 0) {
						$this->usage();
					} else {
						$this->searchMessagesByThread($hash, $this->resources[0]);
					}
					break;
				default:
					// message number
					// this should be a message id
					if (is_numeric($nextResource)) {
						$messageId = $nextResource;
						// no more resoures ?
						if (count($this->resources) == 0) {
							$this->getMessage($messageId);
						} else {
							$nextResource = array_shift($this->resources);
							switch ($nextResource) {
								case "next":
									$this->getNextMessageByThread($hash, $messageId);
									break;
								case "previous":
									$this->getPreviousMessageByThread($hash, $messageId);
									break;
								case "attachments":
									// no more resoures ?
									if (count($this->resources) == 0) {
										$this->usage();
									} else {
										$this->getAttachment($messageId, $this->resources[0]);
									}
									break;
								default:
									$this->usage();
									return false;
							}
						}
					} else {
						$this->usage();
						return false;
					}
			}
		}
	}

	protected function getAllMessagesByThread($hash) {
		$count = ($this->getParam('count') !== null);
		$contents = $this->parseBool($this->getParam('contents'));
		$sort = $this->getParam('sort', 'desc');
		$offset = $this->getParam('offset', 0);
		$limit = $this->getParam('limit', null);
		//echo "Get all messages by thread : $count, $contents\n";
		if ($count) {
			$nb = $this->lib->countMessagesFromThread($hash);
			$this->sendJson($nb);
		} else {
			$messages = $this->lib->getAllMessagesByThread($hash, false, $contents, $sort, $offset, $limit);
			$this->buildAttachmentsLinks($messages);
			$this->sendMultipleResults($messages);
		}
	}

	protected function getLatestMessagesByThread($hash, $limit=false) {
		if ($limit === false) {
			$limit = $this->defaultMessagesLimit;
		}
		$contents = $this->parseBool($this->getParam('contents'));
		$sort = $this->getParam('sort', 'desc');
		//echo "Get latest messages by thread: $contents, $limit\n";
		$messages = $this->lib->getLatestMessagesByThread($hash, $limit, $contents, $sort);
		$this->buildAttachmentsLinks($messages);
		$this->sendMultipleResults($messages);
	}

	protected function searchMessagesByThread($hash, $pattern) {
		$contents = $this->parseBool($this->getParam('contents'));
		$sort = $this->getParam('sort', 'desc');
		$offset = $this->getParam('offset', 0);
		$limit = $this->getParam('limit', null);
		//echo "Search messages by threads: $pattern, $contents\n";
		$messages = $this->lib->getAllMessagesByThread($hash, $pattern, $contents, $sort, $offset, $limit);
		$this->buildAttachmentsLinks($messages);
		$this->sendMultipleResults($messages);
	}

	protected function getNextMessageByThread($hash, $id) {
		$contents = $this->parseBool($this->getParam('contents'));
		$nextMessage = $this->lib->getNextMessageByThread($hash, $id, $contents);
		$this->buildAttachmentsLinks($nextMessage);
		$this->sendJson($nextMessage);
	}

	protected function getPreviousMessageByThread($hash, $id) {
		$contents = $this->parseBool($this->getParam('contents'));
		$previousMessage = $this->lib->getPreviousMessageByThread($hash, $id, $contents);
		$this->buildAttachmentsLinks($previousMessage);
		$this->sendJson($previousMessage);
	}

	/**
	 * Entry point for /authors/* URIs
	 */
	protected function getByAuthors() {
		// no more resources ?
		if (count($this->resources) == 0) {
			// list all authors in a list (having written at least once)
			// "count" switch ?
			$count = ($this->getParam('count') !== null);
			$this->getAllAuthors($count);
		} else {
			// storing author's email
			$authorEmail = array_shift($this->resources);
			// no more resoures ?
			if (count($this->resources) == 0) {
				$this->getAuthorInfo();
			} else {
				$nextResource = array_shift($this->resources);
				switch ($nextResource) {
					case "threads":
						$this->getThreadsByAuthor();
						break;
					case "messages":
						$this->getMessagesByAuthor();
						break;
					default:
						$this->usage();
						return false;
				}
			}
		}
	}

	protected function getAllAuthors() {
		echo "getAllAuthors()";
	}

	protected function getAuthorInfo() {
		echo "getAuthorInfo()";
	}

	protected function getThreadsByAuthor() {
		// @TODO detect /latest et ?count
	}

	protected function getMessagesByAuthor() {
		// @TODO detect /latest, /search, /id/(next|previous) et ?count
	}

	/**
	 * Entry point for /messages/* URIs
	 */
	protected function getByMessages() {
		// no more resources ?
		if (count($this->resources) == 0) {
			// "count" switch ?
			$this->getAllMessages();
		} else {
			$nextResource = array_shift($this->resources);
			switch ($nextResource) {
				case "latest":
					// more resources ?
					$limit = false;
					if (count($this->resources) > 0) {
						$limit = array_shift($this->resources);
					}
					$this->getLatestMessages($limit);
					break;
				case "search":
					// more resources ?
					if (count($this->resources) > 0) {
						$this->searchMessages(array_shift($this->resources));
					} else {
						$this->usage();
					}
					break;
				default:
					// this should be a message id
					if (is_numeric($nextResource)) {
						$messageId = $nextResource;
						// no more resoures ?
						if (count($this->resources) == 0) {
							$this->getMessage($messageId);
						} else {
							$nextResource = array_shift($this->resources);
							switch ($nextResource) {
								case "next":
									$this->getNextMessage($messageId);
									break;
								case "previous":
									$this->getPreviousMessage($messageId);
									break;
								case "attachments":
									// no more resoures ?
									if (count($this->resources) == 0) {
										$this->usage();
									} else {
										$this->getAttachment($messageId, $this->resources[0]);
									}
									break;
								default:
									$this->usage();
									return false;
							}
						}
					} else {
						$this->usage();
						return false;
					}
			}
		}
	}

	protected function getAllMessages() {
		$count = ($this->getParam('count') !== null);
		$sort = $this->getParam('sort', 'desc');
		$offset = $this->getParam('offset', 0);
		$limit = $this->getParam('limit', null);
		$contents = $this->parseBool($this->getParam('contents'));
		//echo "getAllMessages(" . ($count ? "true" : "false") . " / $contents)";
		if ($count) {
			$res = $this->lib->countAllMessages();
			$this->sendJson($res);
		} else {
			$res = $this->lib->getAllMessages($contents, $sort, $offset, $limit);
			$this->buildAttachmentsLinks($res);
			$this->sendMultipleResults($res);
		}
	}

	protected function getLatestMessages($limit=false) {
		if ($limit === false) {
			$limit = $this->defaultMessagesLimit;
		}
		$sort = $this->getParam('sort', 'desc');
		$contents = $this->parseBool($this->getParam('contents'));
		//echo "getLatestMessages($limit / $contents)";
		$res = $this->lib->getLatestMessages($contents, $limit, $sort);
		$this->buildAttachmentsLinks($res);
		$this->sendMultipleResults($res);
	}

	protected function searchMessages($pattern) {
		$contents = $this->parseBool($this->getParam('contents'));
		$sort = $this->getParam('sort', 'desc');
		$offset = $this->getParam('offset', 0);
		$limit = $this->getParam('limit', null);
		//echo "Search messages: $pattern, $contents\n";
		$messages = $this->lib->searchMessages($pattern, $contents, $sort, $offset, $limit);
		$this->buildAttachmentsLinks($messages);
		$this->sendMultipleResults($messages);
	}

	protected function getMessage($id) {
		$contents = $this->parseBool($this->getParam('contents', true));
		//echo "getMessage() : " . $id;
		$res = $this->lib->getMessage($id, $contents);
		$this->buildAttachmentsLinks($res);
		$this->sendJson($res);
	}

	protected function getNextMessage($id) {
		$this->getMessage($id+1);
	}

	protected function getPreviousMessage($id) {
		$this->getMessage($id-1);
	}

	protected function getAttachment($messageId, $attachmentName) {
		$attachmentPath = $this->lib->getAttachmentPath($messageId, $attachmentName);
		$size = filesize($attachmentPath);
		// mimetype detection @TODO get it from Parser instead (redundancy)
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimetype = finfo_file($finfo, $attachmentPath);
		finfo_close($finfo);
		$this->sendFile($attachmentPath, $attachmentName, $size, $mimetype);
	}

	/**
	 * Écrase ou modifie les attributs ou la configuration d'une liste
	 */
	protected function put() {
		$this->sendError("PUT is not supported at the moment", 405);
		// positive response by default
		http_response_code(200);

		// we need at least one resource
		if (count($this->resources) < 1) {
			$this->usage();
			return false;
		}

		$firstResource = $this->resources[0];
		switch($firstResource) {
			case "lists":
				$this->modifyList();
				break;
			case "options":
				$this->modifyListOptions();
				break;
			default:
				$this->usage();
				return false;
		}
	}

	protected function modifyList() {
		echo "modifyList()";
	}

	protected function modifyListOptions() {
		echo "modifyListOptions()";
	}

	// lists, subscribers, allowed, moderators
	protected function post() {
		// positive response by default
		http_response_code(201);

		// we need at least one resource
		if (count($this->resources) < 1) {
			$this->usage();
			return false;
		}

		// read JSON data from request body
		$data = $this->readRequestBody();
		$jsonData = null;
		if (! empty($data)) {
			$jsonData = json_decode($data, true);
		}

		$firstResource = array_shift($this->resources);
		switch($firstResource) {
			case "lists":
				// no more resources ?
				if (count($this->resources) == 0) {
					$this->addList($jsonData);
				} else {
					// storing list name
					$this->listName = array_shift($this->resources);
					// defining list name once for all
					$this->lib->setListName($this->listName);

					// no more resources ?
					if (count($this->resources) == 0) {
						$this->usage();
						return false;
					} else {
						$nextResource = array_shift($this->resources);
						switch ($nextResource) {
							case "subscribers":
								$this->addSubscriber($jsonData);
								break;
							case "allowed":
								$this->addPoster($jsonData);
								break;
							case "moderators":
								$this->addModerator($jsonData);
								break;
							default:
								$this->usage();
								return false;
						}
					}
				}
				break;
			default:
				$this->usage();
				return false;
		}
	}

	protected function addList($data) {
		if (empty($data['name'])) {
			$this->sendError("missing 'name' in JSON data");
		}
		$options = null;
		if (!empty($data['options'])) {
			$options = $data['options'];
		}
		//echo "addList(" . $data['name'] . ")\n";
		$ret = $this->lib->addList($data['name'], $options);
		if ($ret === true) {
			$this->sendJson(array(
				"list" => $data['name'],
				"options" => $options,
				"created" => true
			));
		} else {
			$this->sendError('unknown error in addList()');
		}
	}

	protected function addSubscriber($data) {
		if (empty($data['address'])) {
			$this->sendError("missing 'address' in JSON data");
		}
		//echo "addSubscriber(" . $data['address'] . ")\n";
		$ret = $this->lib->addSubscriber($data['address']);
		if ($ret === true) {
			$this->sendJson(array(
				"list" => $this->listName,
				"new_subscriber" => $data['address']
			));
		} else {
			$this->sendError('unknown error in addSubscriber()');
		}
	}

	protected function addPoster($data) {
		if (empty($data['address'])) {
			$this->sendError("missing 'address' in JSON data");
		}
		//echo "addPoster(" . $data['address'] . ")\n";
		$ret = $this->lib->addPoster($data['address']);
		if ($ret === true) {
			$this->sendJson(array(
				"list" => $this->listName,
				"new_allowed_poster" => $data['address']
			));
		} else {
			$this->sendError('unknown error in addPoster()');
		}
	}

	protected function addModerator($data) {
		if (empty($data['address'])) {
			$this->sendError("missing 'address' in JSON data");
		}
		//echo "addModerator(" . $data['address'] . ")\n";
		$ret = $this->lib->addModerator($data['address']);
		if ($ret === true) {
			$this->sendJson(array(
				"list" => $this->listName,
				"new_moderator" => $data['address']
			));
		} else {
			$this->sendError('unknown error in addModerator()');
		}
	}

	// list, subscriber, allowed, moderator, message
	protected function delete() {
		// positive response by default
		http_response_code(200);

		// we need at least one resource
		if (count($this->resources) < 1) {
			$this->usage();
			return false;
		}

		$firstResource = array_shift($this->resources);
		switch($firstResource) {
			case "lists":
				// no more resources ?
				if (count($this->resources) == 0) {
					$this->usage();
					return false;
				} else {
					// storing list name
					$this->listName = array_shift($this->resources);
					// defining list name once for all
					$this->lib->setListName($this->listName);
					// no more resources ?
					if (count($this->resources) == 0) {
						$this->deleteList();
					} else {
						if (count($this->resources) != 2) {
							$this->usage();
							return false;
						} else {
							$nextResource = array_shift($this->resources);
							$argument = array_shift($this->resources);
							switch ($nextResource) {
								case "subscribers":
									$this->deleteSubscriber($argument);
									break;
								case "allowed":
									$this->deletePoster($argument);
									break;
								case "moderators":
									$this->deleteModerator($argument);
									break;
								case "messages":
									$this->deleteMessage($argument);
									break;
								default:
									$this->usage();
									return false;
							}
						}
					}
				}
				break;
			default:
				$this->usage();
				return false;
		}
	}

	protected function deleteList() {
		//echo "deleteList(" . $this->listName . ")\n";
		$ret = $this->lib->deleteList();
		if ($ret === true) {
			$this->sendJson(array(
				"list" => $this->listName,
				"deleted" => true
			));
		} else {
			$this->sendError('unknown error in deleteList()');
		};
	}

	protected function deleteSubscriber($address) {
		//echo "deleteSubscriber($address)\n";
		$ret = $this->lib->deleteSubscriber($address);
		if ($ret === true) {
			$this->sendJson(array(
				"list" => $this->listName,
				"deleted_subscriber" => $address
			));
		} else {
			$this->sendError('unknown error in deleteSubscriber()');
		};
	}

	protected function deleteModerator($address) {
		//echo "deleteModerator($address)\n";
		$ret = $this->lib->deleteModerator($address);
		if ($ret === true) {
			$this->sendJson(array(
				"list" => $this->listName,
				"deleted_moderator" => $address
			));
		} else {
			$this->sendError('unknown error in deleteModerator()');
		};
	}

	protected function deletePoster($address) {
		//echo "deletePoster($address)\n";
		$ret = $this->lib->deletePoster($address);
		if ($ret === true) {
			$this->sendJson(array(
				"list" => $this->listName,
				"deleted_allowed_poster" => $address
			));
		} else {
			$this->sendError('unknown error in deletePoster()');
		};
	}

	protected function deleteMessage() {
		echo "deleteMessage()";
	}

	protected function options() {
		$this->sendError("OPTIONS is not supported at the moment", 405);
	}
}
