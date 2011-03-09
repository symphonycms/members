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
				SymphonyMember::$identity_field = extension_Members::$fields['identity'];
			}
			else if (is_null($username)) {
				SymphonyMember::$identity_field = extension_Members::$fields['email'];
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
			$auth = extension_Members::$fields['authentication'];

			if(is_null($auth)) return $member;

			$member = $auth->fetchMemberIDBy($credentials, $member, $errors);

			// Check that if there's activiation, that this Member is activated.
			if(!is_null(extension_Members::getConfigVar('activation'))) {
				$activation = extension_Members::$fields['activation'];

				if($activation->get('activated') == 'no') {
					$errors['activation'] = __('Account not activated');
					return false;
				}
			}

			return $member;
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/

		public function login(Array $credentials){
			extract($credentials);
			$errors = array();

			$auth = extension_Members::$fields['authentication'];

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
				$role = extension_Members::$fields['role'];
				if(!isset($context['fields'][$role->get('element_name')])) {
					$context['fields'][$role->get('element_name')] = $role->get('default_role');
				}
			}

			if(!is_null(extension_Members::getConfigVar('activation'))) {
				$activation = extension_Members::$fields['activation'];
				if(!isset($context['fields'][$activation->get('element_name')])) {
					$context['fields'][$activation->get('element_name')] = 'no';
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
				$auth = extension_Members::$fields['authentication'];
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

		/**
		 * Custom code that is called from the Event's load() function
		 *
		 * @param array $fields
		 * @param XMLElement $result
		 * @param Event $event
		 */
		public static function filter_PasswordReset(Array &$fields, XMLElement &$result, Event &$event) {

			// Check that this Email has an Entry
			$email = extension_Members::$fields['email'];
			$member_id = $email->fetchMemberIDBy($fields[$email->get('element_name')]);

			if(is_null($member_id)) return null;

			// Generate new password
			$newPassword = General::generatePassword();

			// Set the Entry password to be reset and the current timestamp
			$auth = extension_Members::$fields['authentication'];
			$simulate = false;
			$data = $auth->processRawFieldData(array(
				'recovery-code' => sha1($newPassword . $member_id),
				'password' => null
			), $simulate);

			$data['reset'] = 'yes';
			$data['expires'] = DateTimeObj::get('Y-m-d H:i:s', time());

			Symphony::Database()->update($data, 'tbl_entries_data_' . $auth->get('id'), ' `entry_id` = ' . $member_id);

			// Add member email to event output.
			$result->appendChild(
				new XMLElement('member-email', $fields[$email->get('element_name')])
			);

			// We now need to simulate the EventFinalSaveFilter which the EmailTemplateFilter
			// uses to send emails.
			$filter_errors = array();
			$entry = self::$driver->em->fetch($member_id);

			/**
			 * @delegate EventFinalSaveFilter
			 * @param string $context
			 * '/frontend/'
			 * @param array $fields
			 * @param string $event
			 * @param array $filter_errors
			 * @param Entry $entry
			 */
			Symphony::ExtensionManager()->notifyMembers(
				'EventFinalSaveFilter', '/frontend/', array(
					'fields'	=> $fields,
					'event'		=> &$event,
					'errors'	=> &$filter_errors,
					'entry'		=> $entry
				)
			);
		}

		/**
		 * Checks the recovery code to ensure it's valid and then sets the Members
		 * new password before logging them in.
		 *
		 * @param array $fields
		 * @param XMLElement $result
		 */
		public static function checkRecoveryCode(Array &$fields, XMLElement &$result) {

			// Check that there is a row with this recovery code and that they request a password
			// reset
			$auth = extension_Members::$fields['authentication'];
			$row = Symphony::Database()->fetchRow(0, sprintf("
					SELECT `entry_id`, `recovery-code`
					FROM tbl_entries_data_%d
					WHERE reset = 'yes'
					AND recovery-code = '%s'
					AND password IS NULL
				", $auth->get('id'), Symphony::Database()->cleanValue($fields['recovery-code'])
			));

			if(empty($row)) {
				$result->setAttribute('status', 'failed');
				$result->appendChild(
					new XMLElement('error', __('No recovery code found'))
				);

				return $result;
			}
			else {
				// Retrieve Member Entry record
				$entry = self::$driver->em->fetch($row['entry_id']);

				if(!$entry instanceof Entry) {
					$result->setAttribute('status', 'failed');
					$result->appendChild(
						new XMLElement('error', __('Member ID not found'))
					);

					return $result;
				}

				// Create new password using the auth field so simulate the checkPostFieldData
				// and processRawFieldData functions.
				$message = '';
				if(Field::__OK__ != $auth->checkPostFieldData($fields[$auth->get('element_name')], $message, $row['entry_id'])) {
					$result->setAttribute('status', 'failed');
					$result->appendChild(
						new XMLElement('error', $message)
					);

					return $result;
				}

				// processRawFieldData will encode the user's new password with the current one
				$simulate = false;
				$data = $auth->processRawFieldData(array(
					'password' => $fields[$auth->get('element_name')],
					'recovery-code' => null,
					'reset' => 'no'
				));

				// Update the database with the new password, removing the recovery code and setting
				// reset to no.
				Symphony::Database()->update($data, 'tbl_entries_data_' . $auth->get('id'), ' `entry_id` = ' . $row['entry_id']);

				// Instead of replicating the same logic, call the UpdatePasswordLogin which will
				// handle relogging in the user.
				SymphonyMember::filter_UpdatePasswordLogin(array(
					'entry' => $entry,
					'fields' => array(
						'password' => array(
							'password' => $data['password']
						)
					)
				));
			}
		}

	}
