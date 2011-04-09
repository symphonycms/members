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
					Symphony::Configuration()->get('cookie-prefix', 'members'), TWO_WEEKS, __SYM_COOKIE_PATH__, true
				);
			}
		}

		public function initialiseMemberObject($member_id = null) {
			if(is_null($this->Member)) {
				$this->Member = $this->fetchMemberFromID($member_id);
			}

			return $this->Member;
		}

	/*-------------------------------------------------------------------------
		Finding:
	-------------------------------------------------------------------------*/

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
		 * Given `$needle` this function will call the active Identity field
		 * to return the entry ID, aka. Member ID, of that entry matching the
		 * `$needle`.
		 *
		 * @param string $needle
		 * @return integer|null
		 */
		public function findMemberIDFromIdentity($needle = null){
			if(is_null($needle)) return null;

			$identity = extension_Members::$fields['identity'];

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

		public function addMemberDetailsToPageParams(Array $context = null) {
			if(!$this->isLoggedIn()) return;

			$this->initialiseMemberObject();

			$context['params']['member-id'] = $this->Member->get('id');

			if(!is_null(extension_Members::getConfigVar('role'))) {
				$role_data = $this->Member->getData(extension_Members::getConfigVar('role'));
				$role = RoleManager::fetch($role_data['role_id']);
				if($role instanceof Role) {
					$context['params']['member-role'] = $role->get('name');
				}
			}

			if(!is_null(extension_Members::getConfigVar('activation'))) {
				if($this->Member->getData(extension_Members::getConfigVar('activation'), true)->activated != "yes") {
					$context['params']['member-activated'] = 'no';
				}
			}
		}

		public function appendLoginStatusToEventXML(Array $context = null){
			$result = new XMLElement('member-login-info');

			if($this->isLoggedIn()) {
				self::$driver->__updateSystemTimezoneOffset($this->Member->get('id'));
				$result->setAttributeArray(array(
					'logged-in' => 'yes',
					'id' => $this->Member->get('id')
				));
			}
			else {
				$result->setAttribute('logged-in','no');

				if(extension_Members::$_failed_login_attempt) {
					$result->setAttribute('failed-login-attempt','yes');
				}
				else {
					$result->setAttribute('failed-login-attempt','no');
				}

				if(is_array(extension_Members::$_errors) && !empty(extension_Members::$_errors)) {
					foreach(extension_Members::$_errors as $type => $error) {
						$result->appendChild(
							new XMLElement($type, $error)
						);
					}
				}
			}

			$context['wrapper']->appendChild($result);
		}

	/*-------------------------------------------------------------------------
		Filters:
	-------------------------------------------------------------------------*/

		public function filter_Register(Array &$context) {
			return true;
		}

		public function filter_Activation(Array &$context) {
			return true;
		}

		public function filter_UpdatePassword(Array &$context) {
			return true;
		}

		public function filter_UpdatePasswordLogin(Array $context) {
			return true;
		}
	}
