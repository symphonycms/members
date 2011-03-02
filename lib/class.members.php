<?php

	function __autoload($name) {
		if(preg_match('/Member$/', $name)) {
			require_once EXTENSIONS . '/members/lib/member.' . strtolower(preg_replace('/Member$/', '', $name)) . '.php';
		}
	}

	Interface Member {
		// Authentication
		public function login(Array $credentials);
		public function logout();
		public function isLoggedIn();

		// Finding
		public function findMemberIDFromCredentials(Array $credentials);

		// Emails
		public function sendNewRegistrationEmail(Entry $entry, Role $role, Array $fields = array());
		public function sendNewPasswordEmail(Entry $entry, Role $role);
		public function sendResetPasswordEmail(Entry $entry, Role $role);

		// Output
		public function addMemberDetailsToPageParams(Array $context = null);
		public function appendLoginStatusToEventXML(Array $context = null);
	}

	Abstract Class Members implements Member {

		public static $driver = null;

		public static $member_id = 0;
		public static $isLoggedIn = false;

		public $Member = null;
		public $cookie = null;

		public function __construct($driver) {
			self::$driver = $driver;
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function getMemberID() {
			return self::$member_id;
		}

	/*-------------------------------------------------------------------------
		Initalise:
	-------------------------------------------------------------------------*/
		public function initialiseCookie() {
			if(is_null($this->cookie)) {
				$this->cookie = new Cookie(
					Symphony::Configuration()->get('cookie-prefix', 'members'),	TWO_WEEKS, __SYM_COOKIE_PATH__, true
				);
			}
		}

		public function initialiseMemberObject($member_id = null) {
			if(is_null($this->Member)) {
				$this->Member = self::fetchMemberFromID($member_id);
			}

			return $this->Member;
		}

	/*-------------------------------------------------------------------------
		Finding:
	-------------------------------------------------------------------------*/

		public function findMemberIDFromEmail($email = null){
			if(is_null($email)) return null;

			$entry_id = Symphony::Database()->fetchCol('entry_id', sprintf("
					SELECT `entry_id`
					FROM `tbl_entries_data_%d`
					WHERE `value` = '%s'
				", extension_Members::getConfigVar('email'), Symphony::Database()->cleanValue($email)
			));

			return (is_null($entry_id) ? null : $entry_id);
		}

		public function fetchMemberFromID($member_id = null) {
			if(!is_null($member_id)) {
				$Member = self::$driver->em->fetch($member_id, NULL, NULL, NULL, NULL, NULL, false, true);
				return $Member[0];
			}
			else if(self::$member_id !== 0) {
				$Member = self::$driver->em->fetch(self::$member_id, NULL, NULL, NULL, NULL, NULL, false, true);
				return $Member[0];
			}

			return null;
		}

		/**
		 * Returns an Entry object given an array of credentials
		 *
		 * @param array $credentials
		 * @return integer
		 */
		public function findMemberIDFromCredentials(Array $credentials) {
			extract($credentials);

			// It's expected that $password is sha1'd and salted.
			if(is_null($username) || is_null($password)) return null;

			$identity = self::$driver->fm->fetch(extension_Members::getConfigVar('identity'));

			// Member from Username
			$member = $identity->fetchMemberIDBy($credentials);

			if(is_null($member)) return null;

			$auth = self::$driver->fm->fetch(extension_Members::getConfigVar('authentication'));

			if(is_null($auth)) return $member;

			$member = $auth->fetchMemberIDBy($credentials, $member);

			return $member;
		}

		/**
		 * Given `$needle` this function will call the active Identity field
		 * to return the entry ID, aka. Member ID, of that entry matching the
		 * `$needle`.
		 *
		 * @param string $needle
		 * @return integer|null
		 */
		public function findMemberIDFromIdentity($needle = null){
			if(is_null($needle)) return null;

			$identity = self::$driver->$fm->fetch(extension_Members::getConfigVar('identity'));

			return $identity->fetchMemberIDBy($needle);
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/
		public function logout(){
			if(is_null($this->cookie)) $this->initialiseCookie();

			$this->cookie->expire();
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		/**
		 * TODO
		 * Do we leave this as-is, or shift to a more 3-esque approach where
		 * all the data goes into the XML?
		 */
		public function addMemberDetailsToPageParams(Array $context = null) {
			if(!$this->isLoggedIn()) return;

			$this->initialiseMemberObject();

			$context['params']['member-id'] = $this->Member->get('id');

			if(is_null(extension_Members::getConfigVar('role'))) return;

			$role_data = $this->Member->getData(extension_Members::getConfigVar('role'));
			$role = RoleManager::fetch($role_data['role_id']);
			if($role instanceof Role) {
				$context['params']['member-role'] = $role->get('name');
			}
		}

		public function appendLoginStatusToEventXML(Array $context = null){
			$result = new XMLElement('member-login-info');

			if($this->isLoggedIn()) {
				self::$driver->__updateSystemTimezoneOffset($this->Member->get('id'));
				$result->setAttributeArray(array(
					'logged-in' => 'true',
					'id' => $this->Member->get('id')
				));
			}
			else {
				$result->setAttributeArray(array(
					'logged-in' => 'false'
				));
			}

			$context['wrapper']->appendChild($result);
		}

	}
