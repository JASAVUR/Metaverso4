<?php
namespace Elgg\Database;

/// Map a username to a cached GUID
/**
 * @var int[] $USERNAME_TO_GUID_MAP_CACHE
 * @access private
 */
global $USERNAME_TO_GUID_MAP_CACHE;
$USERNAME_TO_GUID_MAP_CACHE = array();

/**
 * WARNING: API IN FLUX. DO NOT USE DIRECTLY.
 *
 * @access private
 *
 * @package    Elgg.Core
 * @subpackage Database
 * @since      1.10.0
 */
class UsersTable {
	/**
	 * Return the user specific details of a user by a row.
	 *
	 * @param int $guid The \ElggUser guid
	 *
	 * @return mixed
	 * @access private
	 */
	function getRow($guid) {
		global $CONFIG;
	
		$guid = (int)$guid;
		return _elgg_services()->db->getDataRow("SELECT * from {$CONFIG->dbprefix}users_entity where guid=$guid");
	}
	
	/**
	 * Disables all of a user's entities
	 *
	 * @param int $owner_guid The owner GUID
	 *
	 * @return bool Depending on success
	 */
	function disableEntities($owner_guid) {
		global $CONFIG;
		$owner_guid = (int) $owner_guid;
		if ($entity = get_entity($owner_guid)) {
			if (_elgg_services()->events->trigger('disable', $entity->type, $entity)) {
				if ($entity->canEdit()) {
					$query = "UPDATE {$CONFIG->dbprefix}entities
						set enabled='no' where owner_guid={$owner_guid}
						or container_guid = {$owner_guid}";
	
					$res = _elgg_services()->db->updateData($query);
					return $res;
				}
			}
		}
	
		return false;
	}
	
	/**
	 * Ban a user
	 *
	 * @param int    $user_guid The user guid
	 * @param string $reason    A reason
	 *
	 * @return bool
	 */
	function ban($user_guid, $reason = "") {
		global $CONFIG;
	
		$user_guid = (int)$user_guid;
	
		$user = get_entity($user_guid);
	
		if (($user) && ($user->canEdit()) && ($user instanceof \ElggUser)) {
			if (_elgg_services()->events->trigger('ban', 'user', $user)) {
				// Add reason
				if ($reason) {
					create_metadata($user_guid, 'ban_reason', $reason, '', 0, ACCESS_PUBLIC);
				}
	
				// invalidate memcache for this user
				static $newentity_cache;
				if ((!$newentity_cache) && (is_memcache_available())) {
					$newentity_cache = new \ElggMemcache('new_entity_cache');
				}
	
				if ($newentity_cache) {
					$newentity_cache->delete($user_guid);
				}
	
				// Set ban flag
				$query = "UPDATE {$CONFIG->dbprefix}users_entity set banned='yes' where guid=$user_guid";
				return _elgg_services()->db->updateData($query);
			}
	
			return false;
		}
	
		return false;
	}
	
	/**
	 * Unban a user.
	 *
	 * @param int $user_guid Unban a user.
	 *
	 * @return bool
	 */
	function unban($user_guid) {
		global $CONFIG;
	
		$user_guid = (int)$user_guid;
	
		$user = get_entity($user_guid);
	
		if (($user) && ($user->canEdit()) && ($user instanceof \ElggUser)) {
			if (_elgg_services()->events->trigger('unban', 'user', $user)) {
				create_metadata($user_guid, 'ban_reason', '', '', 0, ACCESS_PUBLIC);
	
				// invalidate memcache for this user
				static $newentity_cache;
				if ((!$newentity_cache) && (is_memcache_available())) {
					$newentity_cache = new \ElggMemcache('new_entity_cache');
				}
	
				if ($newentity_cache) {
					$newentity_cache->delete($user_guid);
				}
	
	
				$query = "UPDATE {$CONFIG->dbprefix}users_entity set banned='no' where guid=$user_guid";
				return _elgg_services()->db->updateData($query);
			}
	
			return false;
		}
	
		return false;
	}
	
	/**
	 * Makes user $guid an admin.
	 *
	 * @param int $user_guid User guid
	 *
	 * @return bool
	 */
	function makeAdmin($user_guid) {
		global $CONFIG;
	
		$user = get_entity((int)$user_guid);
	
		if (($user) && ($user instanceof \ElggUser) && ($user->canEdit())) {
			if (_elgg_services()->events->trigger('make_admin', 'user', $user)) {
	
				// invalidate memcache for this user
				static $newentity_cache;
				if ((!$newentity_cache) && (is_memcache_available())) {
					$newentity_cache = new \ElggMemcache('new_entity_cache');
				}
	
				if ($newentity_cache) {
					$newentity_cache->delete($user_guid);
				}
	
				$r = _elgg_services()->db->updateData("UPDATE {$CONFIG->dbprefix}users_entity set admin='yes' where guid=$user_guid");
				_elgg_invalidate_cache_for_entity($user_guid);
				return $r;
			}
	
			return false;
		}
	
		return false;
	}
	
	/**
	 * Removes user $guid's admin flag.
	 *
	 * @param int $user_guid User GUID
	 *
	 * @return bool
	 */
	function removeAdmin($user_guid) {
		global $CONFIG;
	
		$user = get_entity((int)$user_guid);
	
		if (($user) && ($user instanceof \ElggUser) && ($user->canEdit())) {
			if (_elgg_services()->events->trigger('remove_admin', 'user', $user)) {
	
				// invalidate memcache for this user
				static $newentity_cache;
				if ((!$newentity_cache) && (is_memcache_available())) {
					$newentity_cache = new \ElggMemcache('new_entity_cache');
				}
	
				if ($newentity_cache) {
					$newentity_cache->delete($user_guid);
				}
	
				$r = _elgg_services()->db->updateData("UPDATE {$CONFIG->dbprefix}users_entity set admin='no' where guid=$user_guid");
				_elgg_invalidate_cache_for_entity($user_guid);
				return $r;
			}
	
			return false;
		}
	
		return false;
	}
	
	/**
	 * Get a user object from a GUID.
	 *
	 * This function returns an \ElggUser from a given GUID.
	 *
	 * @param int $guid The GUID
	 *
	 * @return \ElggUser|false
	 */
	function get($guid) {
		// Fixes "Exception thrown without stack frame" when db_select fails
		if (!empty($guid)) {
			$result = get_entity($guid);
		}
	
		if ((!empty($result)) && (!($result instanceof \ElggUser))) {
			return false;
		}
	
		if (!empty($result)) {
			return $result;
		}
	
		return false;
	}
	
	/**
	 * Get user by username
	 *
	 * @param string $username The user's username
	 *
	 * @return \ElggUser|false Depending on success
	 */
	function getByUsername($username) {
		global $CONFIG, $USERNAME_TO_GUID_MAP_CACHE;
	
		// Fixes #6052. Username is frequently sniffed from the path info, which,
		// unlike $_GET, is not URL decoded. If the username was not URL encoded,
		// this is harmless.
		$username = rawurldecode($username);
	
		$username = sanitise_string($username);
		$access = _elgg_get_access_where_sql();
	
		// Caching
		if ((isset($USERNAME_TO_GUID_MAP_CACHE[$username]))
				&& (_elgg_retrieve_cached_entity($USERNAME_TO_GUID_MAP_CACHE[$username]))) {
			return _elgg_retrieve_cached_entity($USERNAME_TO_GUID_MAP_CACHE[$username]);
		}
	
		$query = "SELECT e.* FROM {$CONFIG->dbprefix}users_entity u
			JOIN {$CONFIG->dbprefix}entities e ON e.guid = u.guid
			WHERE u.username = '$username' AND $access";
	
		$entity = _elgg_services()->db->getDataRow($query, 'entity_row_to_elggstar');
		if ($entity) {
			$USERNAME_TO_GUID_MAP_CACHE[$username] = $entity->guid;
		} else {
			$entity = false;
		}
	
		return $entity;
	}
	
	/**
	 * Get an array of users from an email address
	 *
	 * @param string $email Email address.
	 *
	 * @return array
	 */
	function getByEmail($email) {
		global $CONFIG;
	
		$email = sanitise_string($email);
	
		$access = _elgg_get_access_where_sql();
	
		$query = "SELECT e.* FROM {$CONFIG->dbprefix}entities e
			JOIN {$CONFIG->dbprefix}users_entity u ON e.guid = u.guid
			WHERE email = '$email' AND $access";
	
		return _elgg_services()->db->getData($query, 'entity_row_to_elggstar');
	}
	
	/**
	 * Return users (or the number of them) who have been active within a recent period.
	 *
	 * @param array $options Array of options with keys:
	 *
	 *   seconds (int)  => Length of period (default 600 = 10min)
	 *   limit   (int)  => Limit (default 10)
	 *   offset  (int)  => Offset (default 0)
	 *   count   (bool) => Return a count instead of users? (default false)
	 *
	 *   Formerly this was the seconds parameter.
	 *
	 * @param int   $limit   Limit (deprecated usage, use $options)
	 * @param int   $offset  Offset (deprecated usage, use $options)
	 * @param bool  $count   Count (deprecated usage, use $options)
	 *
	 * @return \ElggUser[]|int
	 */
	function findActive($options = array(), $limit = 10, $offset = 0, $count = false) {
	
		$seconds = 600; //default value
	
		if (!is_array($options)) {
			elgg_deprecated_notice("find_active_users() now accepts an \$options array", 1.9);
			if (!$options) {
				$options = $seconds; //assign default value
			}
			$options = array('seconds' => $options);
		}
	
		$options = array_merge(array(
			'seconds' => $seconds,
			'limit' => $limit,
			'offset' => $offset,
			'count' => $count,
		), $options);
	
		// cast options we're sending to hook
		foreach (array('seconds', 'limit', 'offset') as $key) {
			$options[$key] = (int)$options[$key];
		}
		$options['count'] = (bool)$options['count'];
	
		// allow plugins to override
		$params = array(
			'seconds' => $options['seconds'],
			'limit' => $options['limit'],
			'offset' => $options['offset'],
			'count' => $options['count'],
			'options' => $options,
		);
		$data = _elgg_services()->hooks->trigger('find_active_users', 'system', $params, null);
		// check null because the handler could legitimately return falsey values.
		if ($data !== null) {
			return $data;
		}
	
		$dbprefix = _elgg_services()->config->get('dbprefix');
		$time = time() - $options['seconds'];
		return elgg_get_entities(array(
			'type' => 'user',
			'limit' => $options['limit'],
			'offset' => $options['offset'],
			'count' => $options['count'],
			'joins' => array("join {$dbprefix}users_entity u on e.guid = u.guid"),
			'wheres' => array("u.last_action >= {$time}"),
			'order_by' => "u.last_action desc",
		));
	}
	
	/**
	 * Generate and send a password request email to a given user's registered email address.
	 *
	 * @param int $user_guid User GUID
	 *
	 * @return bool
	 */
	function sendNewPasswordRequest($user_guid) {
		$user_guid = (int)$user_guid;
	
		$user = get_entity($user_guid);
		if ($user instanceof \ElggUser) {
			// generate code
			$code = generate_random_cleartext_password();
			$user->setPrivateSetting('passwd_conf_code', $code);
			$user->setPrivateSetting('passwd_conf_time', time());
	
			// generate link
			$link = _elgg_services()->config->getSiteUrl() . "changepassword?u=$user_guid&c=$code";
	
			// generate email
			$ip_address = _elgg_services()->request->getClientIp();
			$email = elgg_echo('email:changereq:body', array($user->name, $ip_address, $link));
	
			return notify_user($user->guid, elgg_get_site_entity()->guid,
				elgg_echo('email:changereq:subject'), $email, array(), 'email');
		}
	
		return false;
	}
	
	/**
	 * Low level function to reset a given user's password.
	 *
	 * This can only be called from execute_new_password_request().
	 *
	 * @param int    $user_guid The user.
	 * @param string $password  Text (which will then be converted into a hash and stored)
	 *
	 * @return bool
	 */
	function forcePasswordReset($user_guid, $password) {
		$user = get_entity($user_guid);
		if ($user instanceof \ElggUser) {
			$ia = elgg_set_ignore_access();
	
			$user->salt = _elgg_generate_password_salt();
			$hash = generate_user_password($user, $password);
			$user->password = $hash;
			$result = (bool)$user->save();
	
			elgg_set_ignore_access($ia);
	
			return $result;
		}
	
		return false;
	}
	
	/**
	 * Validate and change password for a user.
	 *
	 * @param int    $user_guid The user id
	 * @param string $conf_code Confirmation code as sent in the request email.
	 * @param string $password  Optional new password, if not randomly generated.
	 *
	 * @return bool True on success
	 */
	function executeNewPasswordReset($user_guid, $conf_code, $password = null) {
	
		$user_guid = (int)$user_guid;
		$user = get_entity($user_guid);
	
		if ($password === null) {
			$password = generate_random_cleartext_password();
			$reset = true;
		}
	
		if (!elgg_instanceof($user, 'user')) {
			return false;
		}
	
		$saved_code = $user->getPrivateSetting('passwd_conf_code');
		$code_time = (int) $user->getPrivateSetting('passwd_conf_time');
	
		if (!$saved_code || $saved_code != $conf_code) {
			return false;
		}
	
		// Discard for security if it is 24h old
		if (!$code_time || $code_time < time() - 24 * 60 * 60) {
			return false;
		}
	
		if (force_user_password_reset($user_guid, $password)) {
			remove_private_setting($user_guid, 'passwd_conf_code');
			remove_private_setting($user_guid, 'passwd_conf_time');
			// clean the logins failures
			reset_login_failure_count($user_guid);
	
			$ns = $reset ? 'resetpassword' : 'changepassword';
	
			notify_user($user->guid,
				elgg_get_site_entity()->guid,
				elgg_echo("email:$ns:subject"),
				elgg_echo("email:$ns:body", array($user->username, $password)),
				array(),
				'email'
			);
	
			return true;
		}
	
		return false;
	}
	
	/**
	 * Hash a password for storage. Currently salted MD5.
	 *
	 * @param \ElggUser $user     The user this is being generated for.
	 * @param string    $password Password in clear text
	 *
	 * @return string
	 */
	function generatePassword(\ElggUser $user, $password) {
		return md5($password . $user->salt);
	}
	
	/**
	 * Registers a user, returning false if the username already exists
	 *
	 * @param string $username              The username of the new user
	 * @param string $password              The password
	 * @param string $name                  The user's display name
	 * @param string $email                 The user's email address
	 * @param bool   $allow_multiple_emails Allow the same email address to be
	 *                                      registered multiple times?
	 *
	 * @return int|false The new user's GUID; false on failure
	 * @throws RegistrationException
	 */
	function register($username, $password, $name, $email, $allow_multiple_emails = false) {
	
		// no need to trim password.
		$username = trim($username);
		$name = trim(strip_tags($name));
		$email = trim($email);
	
		// A little sanity checking
		if (empty($username)
				|| empty($password)
				|| empty($name)
				|| empty($email)) {
			return false;
		}
	
		// Make sure a user with conflicting details hasn't registered and been disabled
		$access_status = access_get_show_hidden_status();
		access_show_hidden_entities(true);
	
		if (!validate_email_address($email)) {
			throw new \RegistrationException(elgg_echo('registration:emailnotvalid'));
		}
	
		if (!validate_password($password)) {
			throw new \RegistrationException(elgg_echo('registration:passwordnotvalid'));
		}
	
		if (!validate_username($username)) {
			throw new \RegistrationException(elgg_echo('registration:usernamenotvalid'));
		}
	
		if ($user = get_user_by_username($username)) {
			throw new \RegistrationException(elgg_echo('registration:userexists'));
		}
	
		if ((!$allow_multiple_emails) && (get_user_by_email($email))) {
			throw new \RegistrationException(elgg_echo('registration:dupeemail'));
		}
	
		access_show_hidden_entities($access_status);
	
		// Create user
		$user = new \ElggUser();
		$user->username = $username;
		$user->email = $email;
		$user->name = $name;
		$user->access_id = ACCESS_PUBLIC;
		$user->salt = _elgg_generate_password_salt();
		$user->password = generate_user_password($user, $password);
		$user->owner_guid = 0; // Users aren't owned by anyone, even if they are admin created.
		$user->container_guid = 0; // Users aren't contained by anyone, even if they are admin created.
		$user->language = get_current_language();
		if ($user->save() === false) {
			return false;
		}
	
		// Turn on email notifications by default
		set_user_notification_setting($user->getGUID(), 'email', true);
	
		return $user->getGUID();
	}
	
	/**
	 * Generates a unique invite code for a user
	 *
	 * @param string $username The username of the user sending the invitation
	 *
	 * @return string Invite code
	 */
	function generateInviteCode($username) {
		$secret = _elgg_services()->datalist->get('__site_secret__');
		return md5($username . $secret);
	}
	
	/**
	 * Set the validation status for a user.
	 *
	 * @param int    $user_guid The user's GUID
	 * @param bool   $status    Validated (true) or unvalidated (false)
	 * @param string $method    Optional method to say how a user was validated
	 * @return bool
	 */
	function setValidationStatus($user_guid, $status, $method = '') {
		$result1 = create_metadata($user_guid, 'validated', $status, '', 0, ACCESS_PUBLIC, false);
		$result2 = create_metadata($user_guid, 'validated_method', $method, '', 0, ACCESS_PUBLIC, false);
		if ($result1 && $result2) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Gets the validation status of a user.
	 *
	 * @param int $user_guid The user's GUID
	 * @return bool|null Null means status was not set for this user.
	 */
	function getValidationStatus($user_guid) {
		$md = elgg_get_metadata(array(
			'guid' => $user_guid,
			'metadata_name' => 'validated'
		));
		if ($md == false) {
			return null;
		}
	
		if ($md[0]->value) {
			return true;
		}
	
		return false;
	}
	
	/**
	 * Sets the last action time of the given user to right now.
	 *
	 * @param int $user_guid The user GUID
	 *
	 * @return void
	 */
	function setLastAction($user_guid) {
		$user_guid = (int) $user_guid;
		global $CONFIG;
		$time = time();
	
		$query = "UPDATE {$CONFIG->dbprefix}users_entity
			set prev_last_action = last_action,
			last_action = {$time} where guid = {$user_guid}";
	
		execute_delayed_write_query($query);
	}
	
	/**
	 * Sets the last logon time of the given user to right now.
	 *
	 * @param int $user_guid The user GUID
	 *
	 * @return void
	 */
	function setLastLogin($user_guid) {
		$user_guid = (int) $user_guid;
		global $CONFIG;
		$time = time();
	
		$query = "UPDATE {$CONFIG->dbprefix}users_entity
			set prev_last_login = last_login, last_login = {$time} where guid = {$user_guid}";
	
		execute_delayed_write_query($query);
	}
		
}