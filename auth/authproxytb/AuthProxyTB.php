<?php

/**
 * A simple extension of AuthAdapter using Tela Botanica's SSO service
 * 
 * If called with an admin token, allows to obtain information about other users
 * @WARNING ^ limitation : another user will never be considered admin even if
 *			[s]he is one, due to the internal TBAuth mechanism (although it
 *			should be pluggable => this is in fact a TBAuth limitation only)
 * 
 * @TODO see TODO in AuthAdapter.php
 */
class AuthProxyTB extends AuthAdapter {

	protected $lib;
	protected $sso;

	/** $lib is the calling object (Ezmlm class) */
	public function __construct($lib=null, $config=null) {
		$this->lib = $lib;
		$this->sso = new AuthTB($config); // Composer lib
	}

	/**
	 * A user may read the list if the list is public, if [s]he subscribed to
	 * it, if [s]he is moderator of it, or if [s]he is admin
	 */
	public function mayRead($userEmail=false) {
		if ($userEmail === false) {
			$testedUserEmail = $this->sso->getUserEmail();
		} else {
			$testedUserEmail = $userEmail;
		}
		$isPublic = $this->lib->isPublic();
		// @TODO optimization: check user rights only if list is not public
		$isSubscriber = $this->lib->userIsSubscriberOf($testedUserEmail, $this->lib->getListName());
		// if an admin is asking for someone else's rights, don't consider the
		// admin status
		$rights = $isPublic || $isSubscriber || $this->isModerator($testedUserEmail);
		if ($userEmail === false || $userEmail === $this->sso->getUserEmail()) {
			$rights = $rights || $this->isAdmin();
		}
		return $rights;
	}

	/**
	 * A user may post to the list if [s]he subscribed to it, if [s]he's in the
	 * list
	 * of authorized posters or if [s]he is admin
	 * @TODO @WARNING a list might not authorize subscribers to post => to be
	 *		implemented in Ezmlm class
	 */
	public function mayPost($userEmail=false) {
		if ($userEmail === false) {
			$testedUserEmail = $this->sso->getUserEmail();
		} else {
			$testedUserEmail = $userEmail;
		}
		$isSubscriber = $this->lib->userIsSubscriberOf($testedUserEmail, $this->lib->getListName());
		$isAllowedPoster = $this->lib->userIsAllowedIn($testedUserEmail, $this->lib->getListName());
		// if an admin is asking for someone else's rights, don't consider the
		// admin status
		$rights = $isSubscriber || $isAllowedPoster;
		if ($userEmail === false || $userEmail === $this->sso->getUserEmail()) {
			$rights = $rights || $this->isAdmin();
		}
		return $rights;
	}

	/**
	 * A user is moderator if [s]he's in the list of moderators or if [s]he is
	 * admin
	 */
	public function isModerator($userEmail=false) {
		if ($userEmail === false) {
			$testedUserEmail = $this->sso->getUserEmail();
		} else {
			$testedUserEmail = $userEmail;
		}
		$isModerator = $this->lib->userIsModeratorOf($testedUserEmail, $this->lib->getListName());
		// if an admin is asking for someone else's rights, don't consider the
		// admin status
		$rights = $isModerator;
		if ($userEmail === false || $userEmail === $this->sso->getUserEmail()) {
			$rights = $rights || $this->isAdmin();
		}
		return $rights;
	}

	public function isCurrentUser($userEmail) {
		return $this->sso->getUserEmail() == $userEmail;
	}

	/**
	 * A user is administrator if his/her email address is listed in the config
	 * 
	 * @TODO check current list's administrators, too
	 * @WARNING ^ ezmlm-php admin is not the same as (way more than) current
	 *			list admin
	 */
	public function isAdmin($userEmail=false) {
		// any user other than the current one will never be considered admin
		if ($userEmail !== false && $userEmail !== $this->sso->getUserEmail()) {
			return false;
		}
		return $this->sso->isAdmin();
	}

	/* -------------------------- proxy methods ----------------------------- */

	public function getUser() {
		return $this->sso->getUser();
	}

	public function getUserEmail() {
		return $this->sso->getUserEmail();
	}

	public function getUserFullName() {
		return $this->sso->getUserFullName();
	}
}