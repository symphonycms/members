<?php

	Class SymphonyMember extends Members {

		protected static $identity_field = null;

		public function __construct($driver) {
			parent::__construct($driver);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public static function setIdentityField(Array $credentials) {
			extract($credentials);

			if(!is_null(SymphonyMember::$identity_field)) return SymphonyMember::$identity_field;

			// Login with username
			if(is_null($email)) {
				SymphonyMember::$identity_field = self::$driver->fm->fetch(extension_Members::getConfigVar('identity'));
			}
			else if (is_null($username)) {
				SymphonyMember::$identity_field = self::$driver->fm->fetch(extension_Members::getConfigVar('email'));
			}

			return SymphonyMember::$identity_field;
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
		public function findMemberIDFromCredentials(Array $credentials, Array &$errors = array()) {
			extract($credentials);

			// It's expected that $password is sha1'd and salted.
			if((is_null($username) && is_null($email)) || is_null($password)) return null;

			$identity = SymphonyMember::setIdentityField($credentials);

			if(!$identity instanceof Field) return null;

			// Member from Username
			$member = $identity->fetchMemberIDBy($credentials, $errors);

			if(is_null($member)) return null;

			// Validate against Password
			$auth = self::$driver->fm->fetch(extension_Members::getConfigVar('authentication'));;

			if(is_null($auth)) return $member;

			$member = $auth->fetchMemberIDBy($credentials, $member, $errors);

			return $member;
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/

		public function login(Array $credentials){
			extract($credentials);
			$errors = array();

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

			if($id = $this->findMemberIDFromCredentials($data, $errors)) {
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

		public function isLoggedIn(Array &$errors = array()) {
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

			if($id = $this->findMemberIDFromCredentials($data, $errors)) {
				self::$member_id = $id;
				self::$isLoggedIn = true;
				return true;
			}

			$this->logout();

			return false;
		}

	/*-------------------------------------------------------------------------
		Filters:
	-------------------------------------------------------------------------*/

		public function filter_Register(Array &$context) {
			// If there is a Role field, this needs to check that if it was
			// not provided in the $_POST data, that it is set to the Default Role.
			if(!is_null(extension_Members::getConfigVar('role'))) {
				$role = self::$driver->fm->fetch(extension_Members::getConfigVar('role'));
				if(!isset($context['fields'][$role->get('element_name')])) {
					$context['fields'][$role->get('element_name')] = $role->get('default_role');
				}
			}
		}

		public function filter_UpdatePassword(Array &$context) {
			// If there is an Authentication field, we need to inject the 'optional'
			// key so that it won't flag a user's password as invalid if they fail to
			// enter it. The use of the 'optional' key will only trigger validation should
			// they enter a value in the password field, in which it assumes the user is
			// trying to update their password.
			if(!is_null(extension_Members::getConfigVar('authentication'))) {
				$auth = self::$driver->fm->fetch(extension_Members::getConfigVar('authentication'));
				$context['fields'][$auth->get('element_name')]['optional'] = 'yes';
			}
		}

		public function filter_UpdatePasswordLogin(Array &$context) {
			$this->login(array(
				'password' => $context['fields']['password']['password'],
				'username' => $context['entry']->getData(extension_Members::getConfigVar('identity'), true)->value
			), array());

			if(isset($_REQUEST['redirect'])) {
				redirect($_REQUEST['redirect']);
			}
			else {
				redirect(URL);
			}
		}

		public function filter_PasswordReset(Array &$context) {

			// Check that this Email has an Entry
			$email = self::$driver->fm->fetch(extension_Members::getConfigVar('email'));
			$member_id = $email->fetchMemberIDBy($context['fields'][$email->get('element_name')]);

			if(is_null($member_id)) return null;

			// Generate new password
			$newPassword = General::generatePassword();

			// Set the Entry password to be reset and the current timestamp
			$auth = self::$driver->fm->fetch(extension_Members::getConfigVar('authentication'));
			$simulate = false;
			$fields = $auth->processRawFieldData(array('password' => $newPassword), $simulate);

			$fields['reset'] = 'yes';
			$fields['expires'] = DateTimeObj::get('Y-m-d H:i:s', time());

			Symphony::Database()->update($fields, 'tbl_entries_data_' . $auth->get('id'), ' `entry_id` = ' .$member_id);

			// Add new password to the Event output
			$context['messages'][] = array(
				'member-reset-password', false, $newPassword
			);
		}


	}
