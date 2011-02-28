<?php

	Class SymphonyMember extends Members {
		public function __construct($driver) {
			parent::__construct($driver);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		public static function usernameField(){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = ". extension_Members::getMembersSection(). " AND `type` = 'memberusername' LIMIT 1");
		}

		public static function usernameFieldHandle(){
			return Symphony::Database()->fetchVar('element_name', 0, "SELECT `element_name` FROM `tbl_fields` WHERE `parent_section` = ". extension_Members::getMembersSection() ." AND `type` = 'member' LIMIT 1");
		}

		public static function passwordField(){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = ". extension_Members::getMembersSection(). " AND `type` = 'memberpassword' LIMIT 1");
		}

		public static function passwordFieldHandle(){
			return Symphony::Database()->fetchVar('element_name', 0, "SELECT `element_name` FROM `tbl_fields` WHERE `parent_section` = ". extension_Members::getMembersSection() ." AND `type` = 'memberpassword' LIMIT 1");
		}

		public static function getSalt() {
			return Symphony::Database()->fetchVar('salt', 0, sprintf("
					SELECT `salt`
					FROM `tbl_fields_memberpassword`
					WHERE `field_id` = %d
					LIMIT 1
				", self::passwordField()
			));
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/
		public function login(Array $credentials){
			extract($credentials);

			$username = Symphony::Database()->cleanValue($username);
			$password = Symphony::Database()->cleanValue($password);

			if($id = $this->findMemberIDFromCredentials(array(
					'username' => $username,
					'password' => sha1(self::getSalt() . $password)
				))
			) {
				try{
					self::$member_id = $member->$id;
					$this->initialiseCookie();
					$this->initialiseMemberObject();

					$this->cookie->set('id', $id);
					$this->cookie->set('username', $username);
					$this->cookie->set('password', sha1(self::getSalt() . $password));

					self::$isLoggedIn = true;

				} catch(Exception $ex){
					// Or do something else?
					throw new Exception($ex);
				}

				return true;
			}

			$this->logout();

			return false;
		}

		public function isLoggedIn() {
			if(self::$isLoggedIn) return true;

			$this->initialiseCookie();

			if($id = $this->findMemberIDFromCredentials(array(
				'username' => $this->cookie->get('username'),
				'password' => $this->cookie->get('password')
				))
			) {
				self::$member_id = $id;
				self::$isLoggedIn = true;
				return true;
			}

			$this->logout();

			return false;
		}

	/*-------------------------------------------------------------------------
		Emails: All broken at the moment
	-------------------------------------------------------------------------*/

		public function sendNewRegistrationEmail(Entry $entry, Role $role, Array $fields = array()) {
			$email_template = EmailTemplate::find(
				($role->id() == extension_Members::INACTIVE_ROLE_ID ? 'activate-account' : 'welcome'),
				$role->id()
			);
			$member_field_handle = self::usernameFieldHandle();

			if(!$email_template instanceof EmailTemplate) return null;

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::username" => $fields[$member_field_handle]['username'],
				'code' => extension_Members::generateCode($entry->get('id')),
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));
		}

		public function sendNewPasswordEmail(Entry $entry, Role $role) {
			$new_password = General::generatePassword();

			// Attempt to update the password
			Symphony::Database()->query(sprintf(
				"UPDATE `tbl_entries_data_%d` SET `password` = '%s' WHERE `entry_id` = %d LIMIT 1",
				self::passwordField(),
				sha1(self::getSalt() . $new_password),
				$member_id
			));

			$email_template = EmailTemplate::find('new-password', $role->id());
			$member_field_handle = self::usernameFieldHandle();

			if(!$email_template instanceof EmailTemplate) return null;

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::username" => $entry->getData($member_field_handle, true)->username,
				'new-password' => $new_password,
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));
		}

		public function sendResetPasswordEmail(Entry $entry, Role $role) {
			$email_template = EmailTemplate::find('reset-password', $role->id());
			$member_field_handle = self::usernameFieldHandle();

			if(!$email_template instanceof EmailTemplate) return null;

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::username" => $entry->getData($member_field_handle, true)->username,
				'code' => extension_Members::generateCode($entry->get('id')),
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));

		}

	}
