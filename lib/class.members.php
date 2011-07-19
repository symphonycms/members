<?php

	function __autoload($name) {
		if(preg_match('/Member$/', $name)) {
			require_once EXTENSIONS . '/members/lib/member.' . strtolower(preg_replace('/Member$/', '', $name)) . '.php';
		}
	}

	Interface Member {
		// Authentication
		public function login(array $credentials);
		public function logout();
		public function isLoggedIn();

		// Finding
		public static function setIdentityField(array $credentials, $simplified = true);
		public function findMemberIDFromCredentials(array $credentials);
		public function fetchMemberFromID($member_id = null);

		// Output
		public function addMemberDetailsToPageParams(array $context = null);
		public function appendLoginStatusToEventXML(array $context = null);
	}

	Abstract Class Members implements Member {

		protected static $driver = null;
		protected static $member_id = 0;
		protected static $isLoggedIn = false;

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

		public function getMember() {
			return $this->Member;
		}

	/*-------------------------------------------------------------------------
		Initalise:
	-------------------------------------------------------------------------*/

		public function initialiseCookie() {
			if(is_null($this->cookie)) {
				$this->cookie = new Cookie(
					Symphony::Configuration()->get('cookie-prefix', 'members'), TWO_WEEKS, __SYM_COOKIE_PATH__, null, true
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
				$Member = extension_Members::$entryManager->fetch($member_id, NULL, NULL, NULL, NULL, NULL, false, true);
				return $Member[0];
			}

			else if(self::$member_id !== 0) {
				$Member = extension_Members::$entryManager->fetch(self::$member_id, NULL, NULL, NULL, NULL, NULL, false, true);
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

			$identity = extension_Members::getField('identity');

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

		public function addMemberDetailsToPageParams(array $context = null) {
			if(!$this->isLoggedIn()) return;

			$this->initialiseMemberObject();

			$context['params']['member-id'] = $this->getMemberID();

			if(!is_null(extension_Members::getFieldHandle('role'))) {
				$role_data = $this->getMember()->getData(extension_Members::getField('role')->get('id'));
				$role = RoleManager::fetch($role_data['role_id']);
				if($role instanceof Role) {
					$context['params']['member-role'] = $role->get('name');
				}
			}

			if(!is_null(extension_Members::getFieldHandle('activation'))) {
				if($this->getMember()->getData(extension_Members::getField('activation')->get('id'), true)->activated != "yes") {
					$context['params']['member-activated'] = 'no';
				}
			}
		}

		public function appendLoginStatusToEventXML(array $context = null){
			$result = new XMLElement('member-login-info');

			if($this->isLoggedIn()) {
				$result->setAttributearray(array(
					'logged-in' => 'yes',
					'id' => $this->getMemberID(),
					'result' => 'success'
				));
			}
			else {
				$result->setAttribute('logged-in','no');

				// Append error messages
				if(is_array(extension_Members::$_errors) && !empty(extension_Members::$_errors)) {
					foreach(extension_Members::$_errors as $type => $error) {
						$result->appendChild(
							new XMLElement($type, null, array(
								'type' => $error['type'],
								'message' => $error['message'],
								'label' => General::sanitize($error['label'])
							))
						);
					}
				}

				// Append post values to simulate a real Symphony event
				if(extension_Members::$_failed_login_attempt) {
					$result->setAttribute('result', 'error');

					$post_values = new XMLElement('post-values');
					$post = General::getPostData();

					// Create the post data cookie element
					if (is_array($post['fields']) && !empty($post['fields'])) {
						General::array_to_xml($post_values, $post['fields'], true);
						$result->appendChild($post_values);
					}
				}
			}

			$context['wrapper']->appendChild($result);
		}

	/*-------------------------------------------------------------------------
		Filters:
	-------------------------------------------------------------------------*/

		public function filter_LockRole(array &$context) {
			return true;
		}

		public function filter_LockActivation(array &$context) {
			return true;
		}

		public function filter_UpdatePassword(array &$context) {
			return true;
		}

		public function filter_UpdatePasswordLogin(array $context) {
			return true;
		}
	}
