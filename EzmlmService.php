<?php

require_once 'Ezmlm.php';
require_once 'EzmlmInterface.php';

/**
 * REST API to access an ezmlm list
 */
class EzmlmService extends BaseRestServiceTB implements EzmlmInterface {

	/** JSON Autodocumentation */
	public static $AUTODOC_PATH = "./autodoc.json";

	/** JSON service configuration */
	public static $CONFIG_PATH = "config/service.json";

	/** Ezmlm lib */
	protected $lib;

	/** current mailing list if any */
	protected $listName;

	/** default number of messages returned by "latest messages" command */
	protected $defaultMessagesLimit;

	/** default number of threads returned by "latest threads" command */
	protected $defaultThreadsLimit;

	public function __construct() {
		// config
		$config = null;
		if (file_exists(self::$CONFIG_PATH)) {
			$config = json_decode(file_get_contents(self::$CONFIG_PATH), true);
		} else {
			throw new Exception("file " . self::$CONFIG_PATH . " doesn't exist");
		}

		parent::__construct($config);
		// Ezmlm lib
		$this->lib = new Ezmlm();

		// ne pas indexer - placé ici pour simplifier l'utilisation avec nginx
		// (pas de .htaccess)
		header("X-Robots-Tag: noindex, nofollow", true);

		// additional settings
		$this->defaultMessagesLimit = $this->config['settings']['defaultMessagesLimit'];
		$this->defaultThreadsLimit = $this->config['settings']['defaultThreadsLimit'];
	}

	/**
	 * Sends multiple results in a JSON object
	 */
	protected function sendMultipleResults($results) {
		// 2-in-1 dirty mode @TODO do it better
		if (array_key_exists("data", $results) && array_key_exists("total", $results)) {
			$return = array(
				"count" => count($results['data']),
				"total" => $results['total'],
				"results" => $results['data']
			);
		} else {
			$return = array(
				"count" => count($results),
				"results" => $results
			);
		}
		$this->sendJson($return);
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
		} elseif(isset($messages["data"])) {
			// 2-in-1 dirty mode @TODO do it better
			$mess = &$messages["data"];
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
		if (file_exists(dirname(__FILE__) . '/' . self::$AUTODOC_PATH)) {
			$infos = json_decode(file_get_contents(dirname(__FILE__) . '/' . self::$AUTODOC_PATH), true);
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
								case 'users':
									$this->getByListUsers();
									break;
								case 'messages':
									$this->getByMessages();
									break;
								case 'calendar':
									$this->getListCalendar();
									break;
								default:
									$this->usage();
									return false;
							}
						}
					}
					break;
				}
			case 'users':
				$this->getByUsers();
				break;
			default:
				$this->usage();
				return false;
		}
	}

	/**
	 * Entry point for /users/* URIs
	 */
	protected function getByUsers() {
		// no more resources ?
		if (count($this->resources) == 0) {
			$this->getAllUsers();
		} else {
			$userEmail = array_shift($this->resources);
			// no more resources ?
			if (count($this->resources) == 0) {
				$this->getUserInfo($userEmail);
			} else {
				$nextResource = array_shift($this->resources);
				switch ($nextResource) {
					case "moderator-of":
						// no more resources ?
						if (count($this->resources) == 0) {
							$this->getListsUserIsModeratorOf($userEmail);
						} else {
							$this->userIsModeratorOf($userEmail, array_shift($this->resources));
						}
						break;
					case "subscriber-of":
						// no more resources ?
						if (count($this->resources) == 0) {
							$this->getListsUserIsSubscriberOf($userEmail);
						} else {
							$this->userIsSubscriberOf($userEmail, array_shift($this->resources));
						}
						break;
					case "allowed-in":
						// no more resources ?
						if (count($this->resources) == 0) {
							$this->getListsUserIsAllowedIn($userEmail);
						} else {
							$this->userIsAllowedIn($userEmail, array_shift($this->resources));
						}
						break;
					case "change-address-to":
						// no more resources ?
						if (count($this->resources) == 0) {
							$this->usage();
						} else {
							$this->changeUserAddress($userEmail, array_shift($this->resources));
						}
						break;
					default:
						$this->usage();
				}
			}
		}
	}

	// @TODO admin only
	protected function getAllUsers() {
		throw new Exception('getAllUsers() : not implemented');
	}

	// @TODO admin or same user only
	protected function getUserInfo() {
		throw new Exception('getUserInfo() : not implemented');
	}

	/**
	 * Returns all the lists the current user is moderator of
	 */
	protected function getListsUserIsModeratorOf($userEmail) {
		$lists = $this->lib->getListsUserIsModeratorOf($userEmail);
		$this->sendMultipleResults($lists);
	}

	/**
	 * Returns true if the current user is moderator of the list $listName
	 */
	protected function userIsModeratorOf($userEmail, $listName) {
		$info = $this->lib->userIsModeratorOf($userEmail, $listName);
		$this->sendJson($info);
	}

	/**
	 * Returns all the lists the current user is subscriber of
	 */
	protected function getListsUserIsSubscriberOf($userEmail) {
		$lists = $this->lib->getListsUserIsSubscriberOf($userEmail);
		$this->sendMultipleResults($lists);
	}

	/**
	 * Returns true if the current user is subscriber of the list $listName
	 */
	protected function userIsSubscriberOf($userEmail, $listName) {
		$info = $this->lib->userIsSubscriberOf($userEmail, $listName);
		$this->sendJson($info);
	}

	/**
	 * Returns all the lists the current user is allowed to write to
	 */
	protected function getListsUserIsAllowedIn($userEmail) {
		$lists = $this->lib->getListsUserIsAllowedIn($userEmail);
		$this->sendMultipleResults($lists);
	}

	/**
	 * Returns true if the current user is allowed to write to the list $listName
	 */
	protected function userIsAllowedIn($userEmail, $listName) {
		$info = $this->lib->userIsAllowedIn($userEmail, $listName);
		$this->sendJson($info);
	}

	// @TODO admin only
	protected function changeUserAddress($oldAddress, $newAddress) {
		$info = $this->lib->changeUserAddress($oldAddress, $newAddress);
		$this->sendJson($info);
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

	/**
	 * Returns the "calendar" for a list : a summary of the number of messages
	 * per month, per year
	 */
	protected function getListCalendar() {
		//echo "calendar for list [" . $this->listName . "]";
		$calendar = $this->lib->getListCalendar();
		$this->sendJson($calendar);
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
		$res = $this->lib->getPosters($count);
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
	 * Entry point for /lists/X/threads/* URIs
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
				case "date":
					// no more resources ?
					if (count($this->resources) == 0) {
						$this->usage();
					} else {
						$this->getThreadsByDate($this->resources[0]);
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
	 * Searches among available threads
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
			$threads = $this->lib->getAllThreads($pattern, $limit, $details, $sort, $offset);
			$this->sendMultipleResults($threads);
		}
	}

	/**
	 * Returns all threads having at least one message whose date matches the
	 * given $datePortion, using "YYYY[-MM]" format (ie. "2015" or "2015-04")
	 */
	protected function getThreadsByDate($datePortion) {
		$details = $this->parseBool($this->getParam('details'));
		$sort = $this->getParam('sort', 'desc');
		$offset = $this->getParam('offset', 0);
		$limit = $this->getParam('limit', null);
		//echo "getThreadsByDate($datePortion)";
		$threads = $this->lib->getThreadsByDate($datePortion, $limit, $details, $sort, $offset);
		$this->sendMultipleResults($threads);
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
	 * Entry point for /lists/X/users/* URIs
	 */
	protected function getByListUsers() {
		// no more resources ?
		if (count($this->resources) == 0) {
			// list all users in a list (having written at least once)
			// "count" switch ?
			$count = ($this->getParam('count') !== null);
			$this->getAllListUsers($count);
		} else {
			// storing user's email
			$userEmail = array_shift($this->resources);
			// no more resoures ?
			if (count($this->resources) == 0) {
				$this->getListUserInfo($userEmail);
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

	protected function getAllListUsers() {
		$this->sendError('getAllListUsers() : Not implemented');
	}

	protected function getListUserInfo($userEmail) {
		$info = $this->lib->getListUserInfo($userEmail);
		$this->sendJson($info);
	}

	protected function getThreadsByAuthor() {
		// @TODO detect /latest et ?count
		$this->sendError('getThreadsByAuthor() : Not implemented');
	}

	protected function getMessagesByAuthor() {
		// @TODO detect /latest, /search, /id/(next|previous) et ?count
		$this->sendError('getMessagesByAuthor() : Not implemented');
	}

	/**
	 * Entry point for /lists/X/messages/* URIs
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
				case "date":
					// more resources ?
					if (count($this->resources) > 0) {
						$this->getMessagesByDate(array_shift($this->resources));
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

	/**
	 * Returns all messages whose date matches the given $datePortion, using
	 * "YYYY[-MM[-DD]]" format (ie. "2015", "2015-04" or "2015-04-23")
	 */
	protected function getMessagesByDate($datePortion) {
		$contents = $this->parseBool($this->getParam('contents'));
		$sort = $this->getParam('sort', 'desc');
		$offset = $this->getParam('offset', 0);
		$limit = $this->getParam('limit', null);
		//echo "getThreadsByDate($datePortion)";
		// @TODO manage ?count
		$res = $this->lib->getMessagesByDate($datePortion, $contents, $limit, $sort, $offset);
		$this->buildAttachmentsLinks($res);
		$this->sendMultipleResults($res);
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
							case "messages":
								$this->sendMessage($jsonData);
								break;
							case "threads":
								// no more resources ?
								if (count($this->resources) == 0) {
									$this->usage();
									return false;
								} else {
									// storing list name
									$threadHash = array_shift($this->resources);
									// no more resources ?
									if (count($this->resources) == 0) {
										$this->usage();
										return false;
									} else {
										$nextResource = array_shift($this->resources);
										switch ($nextResource) {
											case "messages":
												$this->sendMessage($jsonData, $threadHash);
												break;
											default:
												usage();
										}
									}
								}
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
				"options" => ($options !== null ? $options : "default"),
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

	/**
	 * Sends a message to the list
	 */
	protected function sendMessage($data, $threadHash=null) {
		//echo "sendMessage: [$threadHash]";
		//var_dump($data);
		if (empty($data['body'])) {
			$this->sendError("missing 'body' in JSON data");
		}
		if ($threadHash == "" && empty($data['subject'])) {
			$this->sendError("please provide either a threadHash parameter or a 'subject' field in JSON data");
		}
		// send message
		$ret = $this->lib->sendMessage($data, $threadHash);
		if ($ret === true) {
			$this->sendJson(array(
				"list" => $this->listName,
				"message_sent" => true
			));
		} else {
			$this->sendError('unknown error in sendMessage()');
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

			case 'subscriber':
				if (count($this->resources) != 1) {
					$this->usage();
					return false;
				} else {
					$argument = array_shift($this->resources);
					$this->deleteSubscriberFromAllLists($argument);
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

	protected function deleteSubscriberFromAllLists($address) {
		//echo "deleteSubscriberFromAllLists($address)\n";
		$ret = $this->lib->deleteSubscriberFromAllLists($address);
		if ($ret === true) {
			$this->sendJson(array(
				"deleted_subscriber_from_all_lists" => $address
			));
		} else {
			$this->sendError('unknown error in deleteSubscriberFromAllLists()');
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
		$this->sendError('deleteMessage() : Not implemented');
	}

	protected function options() {
		// don't send any error here or it will break CORS preflight requests
	}
}
