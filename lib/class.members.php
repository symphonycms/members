<?php

	function loadMemberImplementations($name) {
		if(preg_match('/Member$/', $name)) {
			require_once EXTENSIONS . '/members/lib/member.' . strtolower(preg_replace('/Member$/', '', $name)) . '.php';
		}
	}
	spl_autoload_register('loadMemberImplementations');

	Interface Member {
		// Utilities
		public function setMemberSectionID(MemberSection $section);
		public function getMemberSectionID();
		public function setIdentityField(array $credentials, $simplified = true);

		// Authentication
		public function login(array $credentials);
		public function logout();
		public function isLoggedIn();

		// Finding
		public function findMemberIDFromCredentials(array $credentials);
		public function fetchMemberFromID($member_id = null);

		// Output
		public function addMemberDetailsToPageParams(array $context = null);
		public function appendLoginStatusToEventXML(array $context = null);
	}

	Abstract Class Members implements Member {

		protected static $member_id = 0;
		protected static $isLoggedIn = false;

		public $Member = null;
		public $cookie = null;
		public $section_id = null;
		public $section = null;

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function getMember() {
			return $this->Member;
		}

		public function getMemberID() {
			return self::$member_id;
		}

		public function setMemberSectionID(MemberSection $section) {
			$this->section = $section;
			$this->section_id = (int)$section->getData()->id;
		}

		public function getMemberSectionID() {
			if(is_null($this->cookie)) $this->initialiseCookie();

			return $this->cookie->get('members-section-id');
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

		/**
		 * This function will adjust the locale for the currently logged in
		 * user if the active Member section has a Member: Timezone field.
		 *
		 * @param integer $member_id
		 * @return void
		 */
		public function updateSystemTimezoneOffset() {
			if(is_null($this->Member)) return;

			$timezone = $this->section->getField('timezone');

			if(!$timezone instanceof fieldMemberTimezone) return;

			$tz = $timezone->getMemberTimezone($this->getMemberID());

			if(is_null($tz)) return;

			try {
				DateTimeObj::setDefaultTimezone($tz);
			}
			catch(Exception $ex) {
				Symphony::Log()->pushToLog(__('Members Timezone') . ': ' . $ex->getMessage(), $ex->getCode(), true);
			}
		}

	/*-------------------------------------------------------------------------
		Finding:
	-------------------------------------------------------------------------*/

		public function fetchMemberFromID($member_id = null) {
			if(!is_null($member_id)) {
				$Member = EntryManager::fetch($member_id, NULL, NULL, NULL, NULL, NULL, false, true);
				return $Member[0];
			}

			else if(self::$member_id !== 0) {
				$Member = EntryManager::fetch(self::$member_id, NULL, NULL, NULL, NULL, NULL, false, true);
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

			$identity = $this->section->getField('identity');

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
			$context['params']['member-section-id'] = $this->getMemberSectionID();

			if(!is_null($this->section->getFieldHandle('role'))) {
				$role_data = $this->getMember()->getData($this->section->getField('role')->get('id'));
				$role = RoleManager::fetch($role_data['role_id']);
				if($role instanceof Role) {
					$context['params']['member-role'] = $role->get('name');
				}
			}

			if(!is_null($this->section->getFieldHandle('activation'))) {
				if($this->getMember()->getData($this->section->getField('activation')->get('id'), true)->activated != "yes") {
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
					'section-id' => $this->getMemberSectionID(),
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
