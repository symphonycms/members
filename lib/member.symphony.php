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
		public function findMemberIDFromCredentials(Array $credentials) {
			extract($credentials);

			// It's expected that $password is sha1'd and salted.
			if((is_null($username) && is_null($email)) || is_null($password)) return null;

			$identity = SymphonyMember::setIdentityField($credentials);

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

	}
