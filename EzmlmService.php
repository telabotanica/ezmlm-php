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

	public function __construct() {
		parent::__construct();
		// Ezmlm lib
		$this->lib = new Ezmlm();
	}

	/**
	 * Sends multiple results in a JSON object
	 */
	protected function sendMultipleResults($results, $errorMessage="no results", $errorCode=404) {
		if ($results == false) {
			$this->sendError($errorMessage, $errorCode);
		} else {
			$this->sendJson(
				array(
					"count" => count($results),
					"results" => $results
				)
			);
		}
	}

	/**
	 * A version of explode() that preserves NULL values - allows to make the
	 * difference between '' and NULL in multiple parameters, like "keywords"
	 */
	protected function explode($delimiter, $string) {
		if ($string === null) {
			return null;
		} else {
			return explode($delimiter, $string);
		}
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
			$this->sendJson($infos);
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
		$this->sendJson("list of lists !");
	}

	/**
	 * Returns information about a list
	 */
	protected function getListInfo() {
		echo "info for list [" . $this->listName . "]";
	}

	protected function getListOptions() {
		echo "options for list [" . $this->listName . "]";
	}

	protected function getSubscribers() {
		// "count" switch ?
		$count = ($this->getParam('count') !== null);
		echo "getSubscribers(); count : "; var_dump($count);
		$this->lib->getSubscribers($count);
	}

	protected function getPosters() {
		// "count" switch ?
		$count = ($this->getParam('count') !== null);
		echo "getPosters(); count : "; var_dump($count);
		$this->lib->getPosters($count);
	}

	protected function getModerators() {
		// "count" switch ?
		$count = ($this->getParam('count') !== null);
		echo "getModerators(); count : "; var_dump($count);
		$this->lib->getModerators($count);
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
					$this->getLastMessages();
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

	protected function getAllMessages() {
		echo "getAllMessages()";
	}

	protected function getLastMessages() {
		echo "getLastMessages()";
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
	 * GET http://tb.org/cumulus.php/by-name/compte rendu
	 * GET http://tb.org/cumulus.php/by-name/compte rendu?LIKE (par défaut)
	 * GET http://tb.org/cumulus.php/by-name/compte rendu?STRICT
	 * 
	 * Renvoie une liste de fichiers (les clefs et les attributs) correspondant
	 * au nom ou à la / aux portion(s) de nom fournie(s), quels que soient leurs
	 * emplacements
	 * @TODO paginate, sort and limit
	 */
	protected function getByName() {
		$name = isset($this->resources[1]) ? $this->resources[1] : null;
		$strict = false;
		if ($this->getParam('STRICT') !== null) {
			$strict = true;
		}

		//echo "getByName : [$name]\n";
		//var_dump($strict);
		$files = $this->lib->getByName($name, $strict);

		$this->sendMultipleResults($files);
	}

	/**
	 * GET http://tb.org/cumulus.php/search/foo,bar
	 * Recherche floue parmi les noms et les mots-clefs
	 * 
	 * GET http://tb.org/cumulus.php/search?keywords=foo,bar&user=jean-bernard@tela-botanica.org&date=...
	 * Recherche avancée
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
		// we need at least one resource
		if (count($this->resources) < 1) {
			$this->usage();
			return false;
		}

		$firstResource = $this->resources[0];
		switch($firstResource) {
			case "lists":
				$this->addList();
				break;
			case "subscribers":
				$this->addSubscriber();
				break;
			case "posters":
				$this->addPoster();
				break;
			case "moderators":
				$this->addModerator();
				break;
			default:
				$this->usage();
				return false;
		}
	}

	protected function addList() {
		echo "addList()";
	}

	protected function addSubscriber() {
		echo "addSubscriber()";
	}

	protected function addPoster() {
		echo "addPoster()";
	}

	protected function addModerator() {
		echo "addModerator()";
	}

	// list, subscriber, poster, moderator, message
	protected function delete() {
		// we need at least one resource
		if (count($this->resources) < 1) {
			$this->usage();
			return false;
		}

		$firstResource = $this->resources[0];
		switch($firstResource) {
			case "lists":
				$this->deleteList();
				break;
			case "subscribers":
				$this->deleteSubscriber();
				break;
			case "posters":
				$this->deletePoster();
				break;
			case "moderators":
				$this->deleteModerator();
				break;
			case "messages":
				$this->deleteMessage();
				break;
			default:
				$this->usage();
				return false;
		}
	}

	protected function deleteList() {
		echo "deleteList()";
	}

	protected function deleteSubscriber() {
		echo "deleteSubscriber()";
	}

	protected function deletePoster() {
		echo "deletePoster()";
	}

	protected function deleteModerator() {
		echo "deleteModerator()";
	}

	protected function deleteMessage() {
		echo "deleteMessage()";
	}

	protected function options() {
		$this->sendError("OPTIONS is not supported at the moment", 405);
	}
}