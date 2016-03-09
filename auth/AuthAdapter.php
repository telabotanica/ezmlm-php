<?php

/**
 * Simple abstract class for rights management
 */
abstract class AuthAdapter {

	/**
	 * must return true if the current user has "read" rights on the current
	 * list, false otherwise
	 */
	public abstract function mayRead();

	/**
	 * must return true if the current user has "post" (write) rights on the
	 * current list, false otherwise
	 */
	public abstract function mayPost();

	/**
	 * must return true if the current user is a moderator of the current list,
	 * false otherwise
	 */
	public abstract function isModerator();

	/**
	 * must return true if the current user is "administrator", false otherwise
	 */
	public abstract function isAdmin();

	/**
	 * must return a representation of the current user
	 */
	public abstract function getUser();

	/**
	 * must return true if the email address of the current user is equal to
	 * the specified $userEmail
	 */
	public abstract function isCurrentUser($userEmail);

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
			throw new Exception('Information about users can only be obtained by themselves or an admin');
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