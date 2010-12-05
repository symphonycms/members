<?php

	function __autoload($name) {
		if(preg_match('/Member$/', $name)) {
			require_once EXTENSIONS . '/members/lib/members/class.' . $name . '.php';
		}
	}

	Interface Member {
		#	Authentication
		public function login(Array $credentials);
		public function logout();
		public function isLoggedIn();
		#	Finding
		public function findMemberIDFromCredentials(Array $credentials);
		#	Emails
		public function sendNewRegistrationEmail(Entry $entry, Role $role, Array $fields = array());
		public function sendNewPasswordEmail(Entry $entry, Role $role);
		public function sendResetPasswordEmail(Entry $entry, Role $role);
		#	Output
		public function AddMemberDetailsToPageParams(Array $context = null);
		public function appendLoginStatusToEventXML(Array $context = null);
		public function buildXML(Array $context = null);
	}

	Abstract Class Members implements Member {

		public static $driver = null;

		public static $member_id = 0;
		public static $isLoggedIn = false;

		public static $debug = false;

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
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if(is_null($this->cookie)) {
				$this->cookie = new Cookie(
					Symphony::Configuration()->get('cookie-prefix', 'members'),	TWO_WEEKS, __SYM_COOKIE_PATH__, true
				);
			}
		}

		public function initialiseMemberObject($member_id = null) {
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if(is_null($this->Member)) {
				$this->Member = self::fetchMemberFromID($member_id);
			}

			return $this->Member;
		}

	/*-------------------------------------------------------------------------
		Finding:
	-------------------------------------------------------------------------*/
		public function findMemberIDFromEmail($email = null){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if(is_null($email)) return null;

			$entry_id = Symphony::Database()->fetchCol('entry_id', sprintf("
					SELECT `entry_id`
					FROM `tbl_entries_data_%d`
					WHERE `value` = '%s'
				", extension_Members::getConfigVar('email_address_field_id'), Symphony::Database()->cleanValue($email)
			));

			return (is_null($entry_id) ? null : $entry_id);
		}

		public function fetchMemberFromID($member_id = null){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			$entryManager = new EntryManager(Frontend::instance());

			if(!is_null($member_id)) {
				$Member = $entryManager->fetch($member_id, NULL, NULL, NULL, NULL, NULL, false, true);
				return $Member[0];
			}
			else if(self::$member_id !== 0) {
				$Member = $entryManager->fetch(self::$member_id, NULL, NULL, NULL, NULL, NULL, false, true);
				return $Member[0];
			}

			return null;
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/
		public function logout(){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if(is_null($this->cookie)) $this->initialiseCookie();

			$this->cookie->expire();
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		public function AddMemberDetailsToPageParams(Array $context = null) {
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if(!$this->isLoggedIn()) return;

			$this->initialiseMemberObject();

			$context['params']['member-id'] = $this->Member->get('id');
			$context['params']['member-type'] = get_class($this);
			$context['params']['member-role'] = extension_Members::fetchRole(
				$this->Member->getData(extension_Members::roleField(), true)->role_id
			)->name();
		}

		public function appendLoginStatusToEventXML(Array $context = null){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if($this->isLoggedIn()) self::$driver->__updateSystemTimezoneOffset();

			$context['wrapper']->appendChild(
				self::$driver->buildXML($context)
			);
		}

		public function buildXML(Array $context = null){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			if(!self::$member_id == 0){
				if(!$this->Member) $this->initialiseMemberObject();

				$result = new XMLElement('member-login-info');
				$result->setAttributeArray(array(
					'logged-in' => 'true',
					'type' => get_class($this),
					'id' => $this->Member->get('id')
				));
			}

			else{
				$result = new XMLElement('member-login-info');
				$result->setAttributeArray(array(
					'logged-in' => 'false',
					'type' => get_class($this)
				));
			}

			return $result;
		}

	}
