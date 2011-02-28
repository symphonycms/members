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
		public function buildXML(Array $context = null);
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
		/**
		 * TODO
		 * Does this functionality get moved out to the field since username
		 * can no longer be assumed?
		 *
		 * And why is this here while findMemberIDFromCredentials() and
		 * findMemberIDFromUsername() are in member.symphony.php?
		 */
		public function findMemberIDFromEmail($email = null){
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
			$context['params']['member-type'] = get_class($this);
			$context['params']['member-role'] = extension_Members::fetchRole(
				$this->Member->getData(extension_Members::roleField(), true)->role_id
			)->name();
		}

		public function appendLoginStatusToEventXML(Array $context = null){
			if($this->isLoggedIn()) self::$driver->__updateSystemTimezoneOffset();

			$context['wrapper']->appendChild(
				self::$driver->buildXML($context)
			);
		}

		public function buildXML(Array $context = null){
			if(!self::$member_id == 0){
				if(!$this->Member) $this->initialiseMemberObject();

				$result = new XMLElement('member-login-info');
				$result->setAttributeArray(array(
					'logged-in' => 'true',
					'id' => $this->Member->get('id')
				));
			}

			else{
				$result = new XMLElement('member-login-info');
				$result->setAttributeArray(array(
					'logged-in' => 'false'
				));
			}

			return $result;
		}

	}
