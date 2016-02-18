<?php

/**
 * Authentification / gestion des utilisateurs à l'aide du SSO de Tela Botanica
 */
class AuthTB {

	/** Config passée par Ezmlm.php */
	protected $config;

	/** Données d'un jeton SSO représentant un utilisateur */
	protected $user;

	/** Groupes auxquels appartient l'utilisateur */
	protected $groups = array();

	public function __construct($config) {
		// copie de la config
		$this->config = $config;

		// lecture des infos utilisateur depuis le jeton
		$this->user = $this->getUserFromToken();
	}

	/**
	 * Retourne les données utilisateur en cours
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Retourne l'identifiant de l'utilisateur (id numérique @TODO valider cette
	 * stratégie)
	 */
	public function getUserId() {
		return $this->user['id'];
	}

	/**
	 * Retourne l'adresse email de l'utilisateur (stockée dans "sub" pour
	 * l'instant)
	 */
	public function getUserEmail() {
		return $this->user['sub'];
	}

	/**
	 * Retourne le nom complet / pseudo de l'utilisateur
	 */
	public function getUserFullName() {
		return $this->user['intitule'];
	}

	/**
	 * Retourne les groupes auxquels l'utilisateur en cours appartient
	 */
	public function getUserGroups() {
		return $this->groups;
	}

	/**
	 * Retourne true si le courriel de l'utilisateur identifié par le jeton SSO
	 * est dans la liste des admins, située dans la configuration
	 */
	public function isAdmin() {
		$admins = $this->config['adapters']['AuthTB']['admins'];
		return in_array($this->user['sub'], $admins);
	}

	/**
	 * Recherche un jeton SSO dans l'entête HTTP "Authorization", vérifie ce
	 * jeton auprès de l'annuaire et en cas de succès décode les informations
	 * de l'utilisateur et les place dans $this->user
	 */
	protected function getUserFromToken() {
		// utilisateur non identifié par défaut
		$user = $this->getUnknownUser();
		// lecture du jeton
		$token = $this->readTokenFromHeader();
		//echo "Token : $token\n";
		if ($token != null) {
			// validation par l'annuaire
			$valid = $this->verifyToken($token);
			if ($valid === true) {
				// décodage du courriel utilisateur depuis le jeton
				$tokenData = $this->decodeToken($token);
				if ($tokenData != null && $tokenData["sub"] != "") {
					// récupération de l'utilisateur
					//$email = $tokenData["sub"];
					$user = $tokenData;
					// @TODO demander les détails à l'annuaire
					// $utilisateur = $this->recupererUtilisateurEnBdd($courriel);
					// @TODO demander les groupes à BuddyPress
					// $groups = ...
				}
			}
		}
		return $user;
	}

	/**
	 * Définit comme utilisateur courant un pseudo-utilisateur inconnu
	 */
	protected function getUnknownUser() {
		$this->user = array(
			'sub' => null,
			'id' => null // @TODO remplacer par un ID de session ?
		);
	}

	/**
	 * Essaye de trouver un jeton JWT non vide dans l'entête HTTP "Authorization"
	 */
	protected function readTokenFromHeader() {
		$jwt = null;
		$headers = apache_request_headers();
		if (isset($headers["Authorization"]) && ($headers["Authorization"] != "")) {
			$jwt = $headers["Authorization"];
		}
		return $jwt;
	}

	/**
	 * Vérifie un jeton auprès de l'annuaire
	 */
	protected function verifyToken($token) {
		$verificationServiceURL = $this->config['adapters']['AuthTB']['AnnuaireURL'];
		$verificationServiceURL = trim($verificationServiceURL, '/') . "/verifytoken";
		$verificationServiceURL .= "?token=" . $token;

		// curl avec les options suivantes ignore les pbs de certificat
		// auto-signé (pour tester en local)
		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $verificationServiceURL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		// équivalent de "-k"
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$data = curl_exec($ch);
		curl_close($ch);
		$info = $data;

		$info = json_decode($info, true);

		return ($info === true);
	}

	/**
	 * Décode un jeton JWT (SSO) précédemment validé et retourne les infos
	 * qu'il contient (payload / claims)
	 */
	protected function decodeToken($token) {
		$parts = explode('.', $token);
		$payload = $parts[1];
		$payload = base64_decode($payload);
		$payload = json_decode($payload, true);

		return $payload;
	}
}

/**
 * Compatibilité avec nginx - merci http://php.net/manual/fr/function.getallheaders.php
 */
if (! function_exists('apache_request_headers')) {
	function apache_request_headers() {
		$headers = '';
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}