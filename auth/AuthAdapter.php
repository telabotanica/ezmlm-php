<?php

/**
 * Simple default class for rights management - you must extend it with your
 * own class, otherwise the rights management will be disabled (see default
 * return values for methods below)
 * 
 * @TODO all the mechanisms in AuthProxyTb could be factorized here; the only
 * external values needed are :
 * - is there a current logged-in user and who is (s)he ?
 * - the current user's email address
 */
class AuthAdapter {

	/**
	 * must return true if the current user has "read" rights on the current
	 * list, false otherwise
	 */
	public function mayRead() {
		return true; // rights management disabled by default
	}

	/**
	 * must return true if the current user has "post" (write) rights on the
	 * current list, false otherwise
	 */
	public function mayPost() {
		return true; // rights management disabled by default
	}

	/**
	 * must return true if the current user is a moderator of the current list,
	 * false otherwise
	 */
	public function isModerator() {
		return true; // rights management disabled by default
	}

	/**
	 * must return true if the current user is "administrator", false otherwise
	 */
	public function isAdmin() {
		return true; // rights management disabled by default
	}

	/**
	 * must return a representation of the current user
	 */
	public function getUser() {
		return null; // rights management disabled by default
	}

	/**
	 * must return true if the email address of the current user is equal to
	 * the specified $userEmail
	 */
	public function isCurrentUser($userEmail) {
		return true; // rights management disabled by default
	}

	/**
	 * throws an exception if the current user has no "read" rights
	 */
	public function requireReadRights() {
		if (! $this->mayRead()) {
			throw new Exception('You need "read" rights to do this');
		}
	}

	/**
	 * throws an exception if the current user has no "post" rights
	 */
	public function requirePostRights() {
		if (! $this->mayPost()) {
			throw new Exception('You need "post" rights to do this');
		}
	}

	/**
	 * throws an exception if the current user is no moderator
	 */
	public function requireModerator() {
		if (! $this->isModerator()) {
			throw new Exception('You need to be moderator to do this');
		}
	}

	/**
	 * throws an exception if the current user's email is different from
	 * the specified $userEmail
	 */
	public function requireUser($userEmail) {
		if (! $this->isCurrentUser($userEmail)) {
			throw new Exception('Information about users can only be obtained by themselves');
		}
	}

	/**
	 * throws an exception if the current user is no admin
	 */
	public function requireAdmin() {
		if (! $this->isAdmin()) {
			throw new Exception('You need to be admin to do this');
		}
	}
}