<?php

	Class SymphonyMember extends Members {
		public function __construct($driver) {
			parent::__construct($driver);
		}

	/*-------------------------------------------------------------------------
		Finding:
	-------------------------------------------------------------------------*/

		/**
		 * Returns an Entry object given an array of credentials
		 *
		 * @param array $credentials
		 * @return integer
		 */
		public function findMemberIDFromCredentials(Array $credentials) {
			extract($credentials);

			// It's expected that $password is sha1'd and salted.
			if((is_null($username) && is_null($email)) || is_null($password)) return null;

			// Login with username
			if(is_null($email)) {
				$identity = self::$driver->fm->fetch(extension_Members::getConfigVar('identity'));
			}
			else if (is_null($username)) {
				$identity = self::$driver->fm->fetch(extension_Members::getConfigVar('email'));
			}

			if(!$identity instanceof Field) return null;

			// Member from Username
			$member = $identity->fetchMemberIDBy($credentials);

			if(is_null($member)) return null;

			// Validate against Password
			$auth = self::$driver->fm->fetch(extension_Members::getConfigVar('authentication'));

			if(is_null($auth)) return $member;

			$member = $auth->fetchMemberIDBy($credentials, $member);

			return $member;
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/

		public function login(Array $credentials){
			extract($credentials);

			$auth = self::$driver->fm->fetch(extension_Members::getConfigVar('authentication'));

			$data = array(
				'password' => $auth->encodePassword($password)
			);

			if(isset($username)) {
				$data['username'] = Symphony::Database()->cleanValue($username);
			}
			else if(isset($email) && !is_null(extension_Members::getConfigVar('email'))) {
				$data['email'] = Symphony::Database()->cleanValue($email);
			}

			if($id = $this->findMemberIDFromCredentials($data)) {
				try{
					self::$member_id = $id;
					$this->initialiseCookie();
					$this->initialiseMemberObject();

					$this->cookie->set('id', $id);

					if(isset($username)) {
						$this->cookie->set('username', $data['username']);
					}
					else {
						$this->cookie->set('email', $data['email']);
					}

					$this->cookie->set('password', $data['password']);

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

			$data = array(
				'password' => $this->cookie->get('password')
			);

			if(!is_null($this->cookie->get('username'))) {
				$data['username'] = $this->cookie->get('username');
			}
			else {
				$data['email'] = $this->cookie->get('email');
			}

			if($id = $this->findMemberIDFromCredentials($data)) {
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
			/*$email_template = EmailTemplate::find(
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
			));*/
		}

		public function sendNewPasswordEmail(Entry $entry, Role $role) {
			/*$new_password = General::generatePassword();

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
			));*/
		}

		public function sendResetPasswordEmail(Entry $entry, Role $role) {
			/*$email_template = EmailTemplate::find('reset-password', $role->id());
			$member_field_handle = self::usernameFieldHandle();

			if(!$email_template instanceof EmailTemplate) return null;

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::username" => $entry->getData($member_field_handle, true)->username,
				'code' => extension_Members::generateCode($entry->get('id')),
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));
		*/
		}

	}
