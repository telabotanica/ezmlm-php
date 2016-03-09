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
	 * A user may read the list if the list is public, if he subscribed to it,
	 * if he is moderator of it, or if he is admin
	 */
	public function mayRead() {
		$isPublic = $this->lib->isPublic();
		// @TODO optimization: check user rights only if list is not public
		$isSubscriber = $this->lib->userIsSubscriberOf($this->sso->getUserEmail(), $this->lib->getListName());
		return $isPublic || $isSubscriber || $this->isModerator() || $this->isAdmin();
	}

	/**
	 * A user may post to the list if he subscribed to it, if he's in the list
	 * of authorized posters or if he is admin
	 * @TODO @WARNING a list might not authorize subscribers to post => to be
	 *		implemented in Ezmlm class
	 */
	public function mayPost() {
		$isSubscriber = $this->lib->userIsSubscriberOf($this->sso->getUserEmail(), $this->lib->getListName());
		$isAllowedPoster = $this->lib->userIsAllowedIn($this->sso->getUserEmail(), $this->lib->getListName());
		return $isSubscriber || $isAllowedPoster || $this->isAdmin();
	}

	/**
	 * A user is moderator if he's in the list of moderators or if he is admin
	 */
	public function isModerator() {
		$isModerator = $this->lib->userIsModeratorOf($this->sso->getUserEmail(), $this->lib->getListName());
		return $isModerator || $this->isAdmin();
	}

	public function isCurrentUser($userEmail) {
		return $this->sso->getUserEmail() == $userEmail;
	}

	/**
	 * A user is administrator if his email address is listed in the config
	 * @TODO check current list's administrators, too
	 */
	public function isAdmin() {
		return $this->sso->isAdmin();
	}

	public function getUser() {
		return $this->sso->getUser();
	}
}