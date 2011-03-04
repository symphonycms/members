<?php

    require_once(EXTENSIONS . '/members/lib/class.identity.php');

	Class fieldMemberEmail extends Identity {

		protected static $validator = '/^\w(?:\.?[\w%+-]+)*@\w(?:[\w-]*\.)+?[a-z]{2,}$/i';

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Member: Email');
			$this->_required = true;
			$this->set('required', 'yes');
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `email` (`value`)
				) ENGINE=MyISAM;"
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function fetchMemberIDBy($needle) {
			if(is_array($needle)) {
				extract($needle);
			}
			else {
				$email = $needle;
			}

			$member_id = Symphony::Database()->fetchVar('entry_id', 0, sprintf(
				"SELECT `entry_id` FROM `tbl_entries_data_%d` WHERE `value` = '%s' LIMIT 1",
				$this->get('id'), Symphony::Database()->cleanValue($email)
			));

			return ($member_id ? $member_id : NULL);
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array(
				'field_id' => $id
			);

			Symphony::Configuration()->set('email', $id, 'members');
			Administration::instance()->saveConfig();

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		private static function __applyValidationRule($data){
			include(TOOLKIT . '/util.validators.php');
			$rule = (isset($validators['email']) ? $validators['email'] : fieldMemberEmail::$validator);

			return General::validateString($data, $rule);
		}

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$message = null;
			$required = ($this->get('required') == "yes");

			$email = trim($data);

			//	If the field is required, we should have both a $username and $password.
			if($required && empty($email)) {
				$message = __('%s is a required field.', array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}

			//	Check Email Address
			if(!empty($email)) {
				if(!fieldMemberEmail::__applyValidationRule($email)) {
					$message = __('%s contains invalid characters.', array($this->get('label')));
					return self::__INVALID_FIELDS__;
				}

				$existing = $this->fetchMemberIDBy($email);

				// If there is an existing email, and it's not the current object (editing), error.
				// @todo This isn't working as expected.
				if($existing !== $entry_id) {
					$message = __('That email address is already registered.');
					return self::__INVALID_FIELDS__;
				}
			}

			return self::__OK__;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!isset($data['value'])) return;

			$wrapper->appendChild(
				new XMLElement($this->get('element_name'), General::sanitize($data['value']), array(
					'hash' => md5($data['value'])
				)
			));
		}

	}
