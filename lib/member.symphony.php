<?php
	require_once EXTENSIONS . '/members/lib/class.membersevent.php';

	Class SymphonyMember extends Members {

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * This function determines what field instance to use based on the current
		 * $_POST data.
		 *
		 * @param array $credentials
		 * @param boolean $simplified
		 *  If true, this function assumes that the $credentials array contains a
		 *  username and email key, otherwise, it will attempt to map a value using
		 *  the field handles to a normalised username/email.
		 * @return Field
		 */
		public function setIdentityField(array $credentials, $simplified = true) {
			if($simplified) {
				extract($credentials);
			}
			else {
				// Map POST data to simple terms
				if(isset($credentials[$this->section->getFieldHandle('identity')])) {
					$username = $credentials[$this->section->getFieldHandle('identity')];
				}

				if(isset($credentials[$this->section->getFieldHandle('email')])) {
					$email = $credentials[$this->section->getFieldHandle('email')];
				}
			}

			// Check to see if neither can be found, just return null
			if(is_null($username) && is_null($email)) {
				return null;
			}

			// If email is supplied, use the Email field
			if((isset($email) && !empty($email)) && !is_null($email)) {
				$identity_field = $this->section->getField('email');
			}
			// If username is supplied, use the Username field
			else if ((isset($username) && !empty($username)) && !is_null($username)) {
				$identity_field = $this->section->getField('identity');
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
		 * @param boolean $isHashed
		 *  Defaults to false
		 * @return integer
		 */
		public function findMemberIDFromCredentials(array $credentials, $isHashed = false) {
			if((
				(!isset($credentials['username']) || is_null($credentials['username']))
				&& (!isset($credentials['email']) || is_null($credentials['email']))
			)) {
				return null;
			}

			$identity = $this->setIdentityField($credentials);

			if(!$identity instanceof Field) return null;

			// Member from Identity
			$member_id = $identity->fetchMemberIDBy($credentials);

			// Validate against Password
			$auth = $this->section->getField('authentication');
			if(!is_null($auth)) {
				$member_id = $auth->fetchMemberIDBy($credentials, $member_id, $isHashed);
			}

			// No Member found, can't even begin to check Activation
			// Return null
			if(is_null($member_id)) return null;

			// Check that if there's activiation, that this Member is activated.
			if(!is_null($this->section->getFieldHandle('activation'))) {
				$entry = EntryManager::fetch($member_id, NULL, NULL, NULL, NULL, NULL, false, true, array($this->section->getFieldHandle('activation')));

				$isActivated = $entry[0]->getData($this->section->getField('activation')->get('id'), true)->activated == "yes";

				// If we are denying login for non activated members, lets do so now
				if($this->section->getField('activation')->get('deny_login') == 'yes' && !$isActivated) {
					extension_Members::$_errors[$this->section->getFieldHandle('activation')] = array(
						'message' => __('Member is not activated.'),
						'type' => 'invalid',
						'label' => $this->section->getField('activation')->get('label')
					);

					return null;
				}

				// If the member isn't activated and a Role field doesn't exist
				// just return false.
				if(!$isActivated && !FieldManager::isFieldUsed(extension_Members::getFieldType('role'))) {
					extension_Members::$_errors[$this->section->getFieldHandle('activation')] = array(
						'message' => __('Member is not activated.'),
						'type' => 'invalid',
						'label' => $this->section->getField('activation')->get('label')
					);

					return false;
				}
			}

			return $member_id;
		}

		public function fetchMemberFromID($member_id = null) {
			$member = parent::fetchMemberFromID($member_id);

			if(is_null($member)) return null;

			// If the member isn't activated and a Role field exists, we need to override
			// the current Role with the Activation Role. This may allow Members to view certain
			// things until they active their account.
			if(!is_null($this->section->getFieldHandle('activation'))) {
				if($member->getData($this->section->getField('activation')->get('id'), true)->activated != "yes") {
					if(FieldManager::isFieldUsed($this->section->getFieldHandle('role'))) {
						$member->setData(
							$this->section->getField('role')->get('id'),
							$this->section->getField('activation')->get('activation_role_id')
						);
					}
				}
			}

			return $member;
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/

		/**
		 * Login function takes an associative array of fields that contain
		 * an Identity field (Email/Username) and a Password field. They keys
		 * should be the Field's `element_name`.
		 * An optional parameter, `$isHashed` refers to if the password provided
		 * is hashed already, or requires hashing prior to logging in.
		 *
		 * @throws Exception
		 * @param array $credentials
		 * @param boolean $isHashed
		 *  Defaults to false
		 * @return boolean
		 */
		public function login(array $credentials, $isHashed = false) {
			$username = $email = $password = null;
			$data = extension_Members::$_errors = array();

			// Map POST data to simple terms
			if(isset($credentials[$this->section->getFieldHandle('identity')])) {
				$username = $credentials[$this->section->getFieldHandle('identity')];
			}

			if(isset($credentials[$this->section->getFieldHandle('email')])) {
				$email = $credentials[$this->section->getFieldHandle('email')];
			}

			// Allow login via username OR email. This normalises the $data array from the custom
			// field names to simple names for ease of use.
			if(isset($username)) {
				$data['username'] = Symphony::Database()->cleanValue($username);
			}
			else if(isset($email) && !is_null($this->section->getFieldHandle('email'))) {
				$data['email'] = Symphony::Database()->cleanValue($email);
			}

			// Map POST data for password to `$password`
			if(isset($credentials[$this->section->getFieldHandle('authentication')])) {
				$password = $credentials[$this->section->getFieldHandle('authentication')];
				$data['password'] = (!empty($password)) ? $password : '';
			}

			// Check to ensure that we actually have some data to try and log a user in with.
			if(empty($data['password']) && isset($credentials[$this->section->getFieldHandle('authentication')])) {
				extension_Members::$_errors[$this->section->getFieldHandle('authentication')] = array(
					'label' => $this->section->getField('authentication')->get('label'),
					'type' => 'missing',
					'message-id' => EventMessages::FIELD_MISSING,
					'message' => __('%s is a required field.', array($this->section->getField('authentication')->get('label'))),
				);
			}

			if(isset($data['username']) && empty($data['username'])) {
				extension_Members::$_errors[$this->section->getFieldHandle('identity')] = array(
					'label' => $this->section->getField('identity')->get('label'),
					'type' => 'missing',
					'message-id' => EventMessages::FIELD_MISSING,
					'message' => __('%s is a required field.', array($this->section->getField('identity')->get('label'))),
				);
			}

			if(isset($data['email']) && empty($data['email'])) {
				extension_Members::$_errors[$this->section->getFieldHandle('email')] = array(
					'label' => $this->section->getField('email')->get('label'),
					'type' => 'missing',
					'message-id' => EventMessages::FIELD_MISSING,
					'message' => __('%s is a required field.', array($this->section->getField('email')->get('label'))),
				);
			}
			else if(!fieldMemberEmail::applyValidationRule($email)) {
				extension_Members::$_errors[$this->section->getFieldHandle('email')] = array(
					'message' => __('\'%s\' contains invalid characters.', array($this->section->getField('email')->get('label'))),
					'message-id' => EventMessages::FIELD_INVALID,
					'type' => 'invalid',
					'label' => $this->section->getField('email')->get('label')
				);
				return null;
			}

			// If there is errors already, no point continuing, return false
			if(!empty(extension_Members::$_errors)) {
				return false;
			}

			if($id = $this->findMemberIDFromCredentials($data, $isHashed)) {
				try{
					self::$member_id = $id;
					$this->initialiseCookie();
					$this->initialiseMemberObject();

					$this->cookie->set('id', $id);
					$this->cookie->set('members-section-id', $this->getMember()->get('section_id'));

					if(isset($username)) {
						$this->cookie->set('username', $data['username']);
					}
					else {
						$this->cookie->set('email', $data['email']);
					}

					if (isset($credentials[$this->section->getFieldHandle('authentication')])){
						$this->cookie->set('password', $this->getMember()->getData($this->section->getField('authentication')->get('id'), true)->password);
					}
					
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

			if($id = $this->findMemberIDFromCredentials($data, true)) {
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

		public function filter_LockRole(array &$context) {
			// If there is a Role field, this will force it to be the Default Role.
			if(!is_null($this->section->getFieldHandle('role'))) {
				// Can't use `$context` as `$fields` only contains $_POST['fields']
				if(isset($_POST['id'])) {
					$member = parent::fetchMemberFromID(
						Symphony::Database()->cleanValue($_POST['id'])
					);

					if(!$member instanceof Entry) return;

					// If there is a Role set to this Member, lock the `$fields` role to the same value
					$role_id = $member->getData($this->section->getField('role')->get('id'), true)->role_id;
					$context['fields'][$this->section->getFieldHandle('role')] = $role_id;
				}
				// New Member, so use the default Role
				else {
					$context['fields'][$this->section->getFieldHandle('role')] = $this->section->getField('role')->get('default_role');
				}
			}
		}

		public function filter_LockActivation(array &$context) {
			// If there is an Activation field, this will force it to be no.
			if(!is_null($this->section->getFieldHandle('activation'))) {
				// Can't use `$context` as `$fields` only contains $_POST['fields']
				if(isset($_POST['id'])) {
					$member = parent::fetchMemberFromID(
						Symphony::Database()->cleanValue($_POST['id'])
					);

					if(!$member instanceof Entry) return;

					// Lock the `$fields` activation to the same value as what is set to the Member
					$activated = $member->getData($this->section->getField('activation')->get('id'), true)->activated;
					$context['fields'][$this->section->getFieldHandle('activation')] = $activated;
				}
				// New Member, so set activation to 'no'
				else {
					$context['fields'][$this->section->getFieldHandle('activation')] = 'no';
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
		 *
		 * @param array $context
		 */
		public function filter_UpdatePassword(array &$context) {
			if(!is_null($this->section->getFieldHandle('authentication'))) {
				$context['fields'][$this->section->getFieldHandle('authentication')]['optional'] = 'yes';
			}
		}

		/**
		 * Part 2 - Update Password, logs the user in
		 * If the user changed their password, we need to login them back into the
		 * system with their new password.
		 *
		 * @param array $context
		 *
		 * @return bool
		 */
		public function filter_UpdatePasswordLogin(array $context) {
			// If the user didn't update their password, or no Identity field exists return
			if(empty($context['fields'][$this->section->getFieldHandle('authentication')]['password'])) return;

			// Handle which is the Identity field, either the Member: Username or Member: Email field
			$identity = is_null($this->section->getFieldHandle('identity')) ? 'email' : 'identity';

			$this->login(array(
				$this->section->getFieldHandle($identity) => $context['entry']->getData($this->section->getField($identity)->get('id'), true)->value,
				$this->section->getFieldHandle('authentication') => $context['fields'][$this->section->getFieldHandle('authentication')]['password']
			), false);
		}
	}
