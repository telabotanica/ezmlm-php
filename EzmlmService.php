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

	/** current topic if any */
	protected $topicName;

	/** current author if any */
	protected $authorEmail;

	/** current message if any */
	protected $messageId;

	/** default number of messages returned by "last messages" commands */
	protected $defaultMessagesLimit;

	public function __construct() {
		parent::__construct();
		// Ezmlm lib
		$this->lib = new Ezmlm();

		// additional settings
		$this->defaultMessagesLimit = $this->config['settings']['defaultMessagesLimit'];
	}

	/**
	 * Sends multiple results in a JSON object
	 */
	protected function sendMultipleResults($results/*, $errorMessage="no results", $errorCode=404*/) {
		//var_dump($results); exit;
		//if ($results == false) {
		//	$this->sendError($errorMessage, $errorCode);
		//} else {
			$this->sendJson(
				array(
					"count" => count($results),
					"results" => $results
				)
			);
		//}
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
					// storing list name
					$this->listName = array_shift($this->resources);
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
							case 'posters':
								$this->getPosters();
								break;
							case 'moderators':
								$this->getModerators();
								break;
							case 'topics':
								$this->getByTopics();
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
	 * Returns information about a list
	 */
	protected function getListInfo() {
		//echo "info for list [" . $this->listName . "]";
		$options = $this->lib->getListInfo();
		var_dump($options);
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

	protected function getPosters() {
		// "count" switch ?
		$count = ($this->getParam('count') !== null);
		//echo "getPosters(); count : "; var_dump($count);
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
	 * Entry point for /topics/* URIs
	 */
	protected function getByTopics() {
		// no more resources ?
		if (count($this->resources) == 0) {
			// "count" switch ?
			$count = ($this->getParam('count') !== null);
			$this->getAllTopics($count);
		} else {
			// storing topic name
			$this->topicName = array_shift($this->resources);
			// no more resoures ?
			if (count($this->resources) == 0) {
				$this->getTopicInfo();
			} else {
				$nextResource = array_shift($this->resources);
				switch ($nextResource) {
					case "messages":
						$this->getMessagesByTopic();
						break;
					default:
						$this->usage();
						return false;
				}
			}
		}
	}

	protected function getAllTopics() {
		echo "getAllTopics()";
	}

	protected function getTopicInfo() {
		echo "getTopicInfo()";
	}

	protected function getMessagesByTopic() {
		// @TODO detect /last, /search, /id/(next|previous) et ?count
		echo "getMessagesByTopic()";
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
			$this->authorEmail = array_shift($this->resources);
			// no more resoures ?
			if (count($this->resources) == 0) {
				$this->getAuthorInfo();
			} else {
				$nextResource = array_shift($this->resources);
				switch ($nextResource) {
					case "topics":
						$this->getTopicsByAuthor();
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

	protected function getTopicsByAuthor() {
		// @TODO detect /last et ?count
	}

	protected function getMessagesByAuthor() {
		// @TODO detect /last, /search, /id/(next|previous) et ?count
	}

	/**
	 * Entry point for /messages/* URIs
	 */
	protected function getByMessages() {
		// no more resources ?
		if (count($this->resources) == 0) {
			// "count" switch ?
			$count = ($this->getParam('count') !== null);
			$this->getAllMessages($count);
		} else {
			$nextResource = array_shift($this->resources);
			switch ($nextResource) {
				case "last":
					// more resources ?
					$limit = false;
					if (count($this->resources) > 0) {
						$limit = array_shift($this->resources);
					}
					$this->getLastMessages($limit);
					break;
				case "search":
					$this->searchMessages();
					break;
				default:
					// this should be a message id
					if (is_numeric($nextResource)) {
						// storing message id
						$this->messageId = $nextResource;
						// no more resoures ?
						if (count($this->resources) == 0) {
							$this->getMessage();
						} else {
							$nextResource = array_shift($this->resources);
							switch ($nextResource) {
								case "next":
									$this->getNextMessage();
									break;
								case "previous":
									$this->getPreviousMessage();
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

	protected function getAllMessages($count) {
		echo "getAllMessages(" . ($count ? "true" : "false") . ")";
		$res = $this->lib->getAllMessages($count);
		if ($count) {
			$this->sendJson(count($res)); // bare number
		} else {
			$this->sendMultipleResults($res);
		}
	}

	protected function getLastMessages($limit=false) {
		if ($limit === false) {
			$limit = $this->defaultMessagesLimit;
		}
		echo "getLastMessages($limit)";
		$res = $this->lib->getLastMessages($count);
		if ($count) {
			$this->sendJson(count($res)); // bare number
		} else {
			$this->sendMultipleResults($res);
		}
	}

	protected function searchMessages() {
		echo "searchMessages()";
	}

	protected function getMessage() {
		echo "getMessage()";
	}

	protected function getNextMessage() {
		echo "getNextMessage()";
	}

	protected function getPreviousMessage() {
		echo "getPreviousMessage()";
	}

	/**
	 */
	protected function search() {
		// mode pour les requêtes contenant une ressource (mode simplifié)
		$mode = "AND";
		if ($this->getParam('OR') !== null) {
			$mode = "OR";
		}
		// paramètres de recherche
		$searchParams = array(
			"mode" => $mode
		);
		// URL simplifiée ou non
		if (! empty($this->resources[1])) {
			$searchParams['keywords'] = $this->resources[1];
			$searchParams['name'] = $this->resources[1];
			$searchParams['mode'] = "OR";
		} else {
			$searchParams = $this->params;
		}

		//echo "search :\n";
		//var_dump($searchParams);
		$files = $this->lib->search($searchParams);

		$this->sendMultipleResults($files);
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

	// lists, subscribers, posters, moderators
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
							case "posters":
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

	// list, subscriber, poster, moderator, message
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
								case "posters":
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
