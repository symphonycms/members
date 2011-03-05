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
		public function isLoggedIn(Array &$errors = array());

		// Finding
		public function findMemberIDFromCredentials(Array $credentials, Array &$errors = array());

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
				$this->Member = self::fetchMemberFromID($member_id);
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
			$errors = array();

			if($this->isLoggedIn($errors)) {
				self::$driver->__updateSystemTimezoneOffset($this->Member->get('id'));
				$result->setAttributeArray(array(
					'logged-in' => 'true',
					'id' => $this->Member->get('id')
				));
			}
			else {
				$result->setAttribute('logged-in','false');

				if(extension_Members::$_failed_login_attempt) {
					$result->setAttribute('failed-login-attempt','true');
				}
				else {
					$result->setAttribute('failed-login-attempt','false');
				}

				if(is_array($errors) && !empty($errors)) {
					foreach($errors as $error) {
						$result->appendChild(
							new XMLElement($error[0], $error[1])
						);
					}
				}
			}

			$context['wrapper']->appendChild($result);
		}

	}
