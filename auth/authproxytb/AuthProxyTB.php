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
		// anyone may read a public list
		$isPublic = $this->lib->isPublic();
		if ($isPublic) {
			return true;
		}
		// whose rights are we checking ?
		if ($userEmail === false || $this->isCurrentUser($userEmail)) {
			// checking current user's rights
			$isSubscriber = $this->lib->userIsSubscriberOf($this->sso->getUserEmail(), $this->lib->getListName());
			$rights = $isSubscriber || $this->isModerator() || $this->isAdmin();
		} else {
			// checking someone else's rights
			// only an admin may do this
			if (! $this->sso->isAdmin()) {
				throw new Exception('You need to be admin to do this');
			}
			$isSubscriber = $this->lib->userIsSubscriberOf($userEmail, $this->lib->getListName());
			// knowing if someone else is admin is impossible by design; no
			// isAdmin() test here (would always be true because current user
			// must be admin to check someone else's rights)
			$rights = $isSubscriber || $this->isModerator($userEmail);
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
		// anyone may post to a public list
		// @TODO test if list is public but moderated (add isModerated() to lib)
		$isPublic = $this->lib->isPublic();
		if ($isPublic) {
			return true;
		}
		// whose rights are we checking ?
		if ($userEmail === false || $this->isCurrentUser($userEmail)) {
			// checking current user's rights
			$isSubscriber = $this->lib->userIsSubscriberOf($this->sso->getUserEmail(), $this->lib->getListName());
			$isAllowedPoster = $this->lib->userIsAllowedIn($this->sso->getUserEmail(), $this->lib->getListName());
			$rights = $isSubscriber || $isAllowedPoster || $this->isModerator() || $this->isAdmin();
		} else {
			// checking someone else's rights
			// only an admin may do this
			if (! $this->sso->isAdmin()) {
				throw new Exception('You need to be admin to do this');
			}
			$isSubscriber = $this->lib->userIsSubscriberOf($userEmail, $this->lib->getListName());
			$isAllowedPoster = $this->lib->userIsAllowedIn($userEmail, $this->lib->getListName());
			// knowing if someone else is admin is impossible by design; no
			// isAdmin() test here (would always be true because current user
			// must be admin to check someone else's rights)
			$rights = $isSubscriber || $isAllowedPoster || $this->isModerator($userEmail);
		}
		return $rights;
	}

	/**
	 * A user is moderator if [s]he's in the list of moderators or if [s]he is
	 * admin
	 */
	public function isModerator($userEmail=false) {
		// whose rights are we checking ?
		if ($userEmail === false || $this->isCurrentUser($userEmail)) {
			// checking current user's rights
			$isModerator = $this->lib->userIsModeratorOf($this->sso->getUserEmail(), $this->lib->getListName());
			$rights = $isModerator || $this->isAdmin();
		} else {
			// checking someone else's rights
			// only an admin may do this
			if (! $this->sso->isAdmin()) {
				throw new Exception('You need to be admin to do this');
			}
			$isModerator = $this->lib->userIsModeratorOf($userEmail, $this->lib->getListName());
			// knowing if someone else is admin is impossible by design; no
			// isAdmin() test here (would always be true because current user
			// must be admin to check someone else's rights)
			$rights = $isModerator;
		}
		return $rights;
	}

	public function isCurrentUser($userEmail) {
		return strtolower($this->sso->getUserEmail()) == strtolower($userEmail);
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