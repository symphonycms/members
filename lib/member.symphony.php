<?php

	Class SymphonyMember extends Members {

		public function __construct($driver) {
			parent::__construct($driver);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		
		/**
		 * This function determines what field instance to use based on the current
		 * $_POST data.
		 *
		 * @param array $credentials
		 * @return Field
		 */
		public static function setIdentityField(Array $credentials) {
			extract($credentials);

			// Login with username
			if(is_null($email)) {
				$identity_field = extension_Members::$fields['identity'];
			}
			else if (is_null($username)) {
				$identity_field = extension_Members::$fields['email'];
			}

			return $identity_field;
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

			$identity = SymphonyMember::setIdentityField($credentials);

			if(!$identity instanceof Field) return null;

			// Member from Identity
			$member_id = $identity->fetchMemberIDBy($credentials, $errors);

			if(is_null($member_id)) return null;

			// Validate against Password
			$auth = extension_Members::$fields['authentication'];

			if(is_null($auth)) return $member_id;

			$member_id = $auth->fetchMemberIDBy($credentials, $member_id);

			if(is_null($member_id)) return null;

			// Check that if there's activiation, that this Member is activated.
			if(!is_null(extension_Members::getConfigVar('activation'))) {
				$entry = self::$driver->em->fetch($member_id);
				if($entry[0]->getData(extension_Members::getConfigVar('activation'), true)->activated != "yes") {
					extension_Members::$_errors['activation'] = __('Account not activated.');
					return false;
				}
			}

			return $member_id;
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/

		public function login(Array $credentials, $isHashed = false){
			extract($credentials);

			$auth = extension_Members::$fields['authentication'];

			$data = array(
				'password' => $isHashed ? $password : $auth->encodePassword($password)
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
		Filters:
	-------------------------------------------------------------------------*/

		public function filter_Register(Array &$context) {
			// If there is a Role field, this needs to check that if it was
			// not provided in the $_POST data, that it is set to the Default Role.
			if(!is_null(extension_Members::getConfigVar('role'))) {
				$role = extension_Members::$fields['role'];
				if(!isset($context['fields'][$role->get('element_name')])) {
					$context['fields'][$role->get('element_name')] = $role->get('default_role');
				}
			}
		}

		public function filter_Activation(Array &$context) {
			if(!is_null(extension_Members::getConfigVar('activation'))) {
				$activation = extension_Members::$fields['activation'];
				if(!isset($context['fields'][$activation->get('element_name')])) {
					$context['fields'][$activation->get('element_name')] = 'no';
				}
			}
		}

		/**
		 * Part 1 - Update Password
		 * If there is an Authentication field, we need to inject the 'optional'
		 * key so that it won't flag a user's password as invalid if they fail to
		 * enter it. The use of the 'optional' key will only trigger validation should
		 * they enter a value in the password field, in which it assumes the user is
		 * trying to update their password.
		 */
		public function filter_UpdatePassword(Array &$context) {
			if(!is_null(extension_Members::getConfigVar('authentication'))) {
				$auth = extension_Members::$fields['authentication'];
				$context['fields'][$auth->get('element_name')]['optional'] = 'yes';
			}
		}

		/**
		 * Part 2 - Update Password, logs the user in
		 * If the user changed their password, we need to login them back into the
		 * system with their new password.
		 */
		public function filter_UpdatePasswordLogin(Array $context) {
			// If the user didn't update their password.
			if(empty($context['fields']['password']['password'])) return;

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

	}
