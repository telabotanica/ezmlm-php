<?php

require_once 'auth/AuthAdapter.php';

/**
 * A simple extension of AuthAdapter using Tela Botanica's SSO service
 */
class AuthProxyTB extends AuthAdapter /* required through Composer */ {

	protected $lib;
	protected $sso;

	/** $lib is the calling object (Ezmlm class) */
	public function __construct($lib=null, $config=null) {
		$this->lib = $lib;
		$this->sso = new AuthTB($config); // Composer lib
	}

	/**
	 * A user may read the list if he subscribed to it (or he is admin)
	 * @TODO @WARNING change this ! a list might be public
	 */
	public function mayRead() {
		$isSubscriber = $this->lib->userIsSubscriberOf($this->sso->getUserEmail(), $this->lib->getListName());
		return $isSubscriber || $this->isAdmin();
	}

	/**
	 * A user may post to the list if he subscribed to it or if he's in the list
	 * of authorized posters (or he is admin)
	 * @TODO @WARNING change this ! if a list is public, read rights don't imply post rights !
	 */
	public function mayPost() {
		$isAllowedPoster = $this->lib->userIsAllowedIn($this->sso->getUserEmail(), $this->lib->getListName());
		// mayRead() includes isAdmin()
		return $this->mayRead() || $isAllowedPoster;
	}

	/**
	 * A user is moderator if he's in the list of moderators (or he is admin)
	 */
	public function isModerator() {
		$isModerator = $this->lib->userIsModeratorOf($this->sso->getUserEmail(), $this->lib->getListName());
		return $isModerator || $this->isAdmin();
	}

	/**
	 * A user is administrator if his email address is listed in the config
	 */
	public function isAdmin() {
		return $this->sso->isAdmin();
	}

	public function getUser() {
		return $this->sso->getUser();
	}
}