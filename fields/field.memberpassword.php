<?php

	Class fieldMemberPassword extends Field{

		protected static $_strengths = array(
			array('weak', false, 'Weak'),
			array('good', false, 'Good'),
			array('strong', false, 'Strong')
		);

		protected static $_strength_map = array(
			0			=> 1,
			1			=> 1,
			2			=> 2,
			3			=> 3,
			4			=> 3,
			'weak'		=> 1,
			'good'		=> 2,
			'strong'	=> 3
		);

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Member: Password');
			$this->_required = true;

			$this->set('required', 'yes');
			$this->set('length', '6');
			$this->set('strength', 'good');
		}

		public function canFilter(){
			return true;
		}

		public function mustBeUnique(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public static function createSettingsTable() {
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_memberpassword` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `length` tinyint(2) NOT NULL,
				  `strength` enum('weak', 'good', 'strong') NOT NULL,
				  `salt` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM;
			");
		}

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `password` varchar(40) default NULL,
				  `recovery-code` varchar(40) default NULL,
				  `length` tinyint(2) NOT NULL,
				  `strength` enum('weak', 'good', 'strong') NOT NULL,
				  `reset` enum('yes','no') default 'no',
				  `expires` DATETIME default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `length` (`length`),
				  KEY `password` (`password`),
				  KEY `expires` (`expires`)
				) ENGINE=MyISAM;"
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * Given an array or string as `$needle` and an existing `$member_id`
		 * this function will return the `$member_id` if the given
		 * password matches this `$member_id`, otherwise null.
		 *
		 * @param array|string $needle
		 * @param integer $member_id
		 * @return Entry|null
		 */
		public function fetchMemberIDBy($needle, $member_id, &$errors) {
			if(is_array($needle)) {
				extract($needle);
			}
			else {
				$password = $needle;
			}

			$data = Symphony::Database()->fetchRow(0, sprintf("
					SELECT `entry_id`, `reset`
					FROM `tbl_entries_data_%d`
					WHERE `password` = '%s'
					LIMIT 1
				",
				$this->get('id'), $password, DateTimeObj::get('Y-m-d H:i:s', strtotime('now - 1 hour'))
			));

			// Check that if the password has been reset that it is still valid
			if(!empty($data) && $data['reset'] == 'yes') {
				$valid_id = Symphony::Database()->fetchVar('entry_id', 0, sprintf("
						SELECT `entry_id`
						FROM `tbl_entries_data_%d`
						WHERE `entry_id` = %d
						AND DATE_FORMAT(expires, '%%Y-%%m-%%d %%H:%%i:%%s') < '%s'
						LIMIT 1
					",
					$this->get('id'), $data['entry_id'], DateTimeObj::get('Y-m-d H:i:s', strtotime('now + 1 hour'))
				));

				// If we didn't get an entry_id back, then it's because it was expired
				if(is_null($valid_id)) {
					$errors[] = array('expired-password', __('Password has expired'));
				}
				// Otherwise, we found the entry_id, so lets remove the reset and expires as this password
				// has now been used by the user.
				else {
					$fields = array('reset' => 'no', 'expires' => null);
					Symphony::Database()->update($fields, 'tbl_entries_data_' . $this->get('id'), ' `entry_id` = ' . $valid_id);
				}
			}

			if($data['entry_id'] == $member_id) return $member_id;

			return null;
		}

		/**
		 * Given a string, this function will encode it using the
		 * field's salt and the sha1 algorithm
		 *
		 * @param string $password
		 * @return string
		 */
		public function encodePassword($password) {
			return sha1($this->get('salt') . $password);
		}

		protected static function checkPassword($password) {
			$strength = 0;
			$patterns = array(
				'/[a-z]/', '/[A-Z]/', '/[0-9]/',
				'/[¬!"£$%^&*()`{}\[\]:@~;\'#<>?,.\/\\-=_+\|]/'
			);

			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $password, $matches)) {
					$strength++;
				}
			}

			return $strength;
		}

		protected static function compareStrength($a, $b) {
			if (fieldMemberPassword::$_strength_map[$a] >= fieldMemberPassword::$_strength_map[$b]) return true;

			return false;
		}

		protected function rememberSalt() {
			$field_id = $this->get('id');

			try {
				$salt = Symphony::Database()->fetchVar('salt', 0, "
					SELECT
						f.salt
					FROM
						`tbl_fields_memberpassword` AS f
					WHERE
						f.field_id = '$field_id'
					LIMIT 1
				");
			}
			catch (DatabaseException $ex) {
				// Table hasn't been created yet, just catch
				// and do nothing because there is no salt
				// to remember ;)
				return;
			}

			if ($salt and !$this->get('salt')) {
				$this->set('salt', $salt);
			}
		}

		protected function rememberData($entry_id) {
			$field_id = $this->get('id');

			return Symphony::Database()->fetchRow(0, "
				SELECT
					f.password, f.strength, f.length
				FROM
					`tbl_entries_data_{$field_id}` AS f
				WHERE
					f.entry_id = '{$entry_id}'
				LIMIT 1
			");
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			$order = $this->get('sortorder');

		// Validator ----------------------------------------------------------

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$label = Widget::Label(__('Minimum Length'));
			$label->appendChild(Widget::Input(
				"fields[{$order}][length]", $this->get('length')
			));

			$group->appendChild($label);

		// Strength -----------------------------------------------------------

			$values = fieldMemberPassword::$_strengths;

			foreach ($values as &$value) {
				$value[1] = $value[0] == $this->get('strength');
			}

			$label = Widget::Label(__('Minimum Strength'));
			$label->appendChild(Widget::Select(
				"fields[{$order}][strength]", $values
			));

			$group->appendChild($label);
			$wrapper->appendChild($group);

		// Salt ---------------------------------------------------------------

			$label = Widget::Label(__('Password Salt'));
			$input = Widget::Input(
				"fields[{$order}][salt]", $this->get('salt')
			);

			if ($this->get('salt')) {
				$input->setAttribute('disabled', 'disabled');
			}

			$label->appendChild($input);

			if (isset($errors['salt'])) {
				$label = Widget::wrapFormElementWithError($label, $errors['salt']);
			}

			$wrapper->appendChild($label);

			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		public function checkFields(&$errors, $checkForDuplicates = true) {
			parent::checkFields($errors, $checkForDuplicates);

			$this->rememberSalt();

			if (trim($this->get('salt')) == '') {
				$errors['salt'] = __('This is a required field.');
			}
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldMemberPassword::createSettingsTable();

			$this->rememberSalt();

			$fields = array(
				'field_id' => $id,
				'length' => $this->get('length'),
				'strength' => $this->get('strength'),
				'salt' => $this->get('salt')
			);
			
			if(extension_Members::getMembersSection() == $this->get('parent_section')) {
				Symphony::Configuration()->set('authentication', $id, 'members');
				Administration::instance()->saveConfig();
			}

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		public function tearDown(){
			Symphony::Configuration()->remove('authentication', 'members');
			Administration::instance()->saveConfig();

			return true;
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$field_id = $this->get('id');
			$handle = $this->get('element_name');

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

		//	Password
			$password = $data['password'];
			$password_set = Symphony::Database()->fetchVar('id', 0, sprintf("
					SELECT
						f.id
					FROM
						`tbl_entries_data_%d` AS f
					WHERE
						f.entry_id = %d
					LIMIT 1
				", $field_id, $entry_id
			));

			if(!is_null($password_set)) {
				$this->displayPublishPassword(
					$group, 'New Password', "{$prefix}[{$handle}][password]{$postfix}"
				);
				$this->displayPublishPassword(
					$group, 'Confirm New Password', "{$prefix}[{$handle}][confirm]{$postfix}"
				);

				$group->appendChild(Widget::Input(
					"fields{$prefix}[{$handle}][optional]{$postfix}", 'yes', 'hidden'
				));

				$help = new XMLElement('p');
				$help->setAttribute('class', 'help');
				$help->setValue(__('Leave new password field blank to keep the current password'));

				$group->appendChild($help);
			}
			else {
				$this->displayPublishPassword(
					$group, 'Password', "{$prefix}[{$handle}][password]{$postfix}"
				);
				$this->displayPublishPassword(
					$group, 'Confirm Password', "{$prefix}[{$handle}][confirm]{$postfix}"
				);
			}

		//	Error?
			if(!is_null($error)) {
				$group = Widget::wrapFormElementWithError($group, $error);
			}

			$wrapper->appendChild($group);
		}

		public function displayPublishPassword($wrapper, $title, $name) {
			$required = ($this->get('required') == 'yes');

			$label = Widget::Label(__($title));
			if(!$required) $label->appendChild(new XMLElement('i', __('Optional')));

			$input = Widget::Input("fields{$name}", null, 'password', array('autocomplete' => 'off'));

			$label->appendChild($input);
			$wrapper->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$message = null;
			$required = ($this->get('required') == "yes");
			$requires_password = false;

			$password = trim($data['password']);
			$confirm = trim($data['confirm']);

			//	If the field is required, we should have both a $username and $password.
			if($required && !isset($data['optional']) && (empty($password))) {
				$message = __('%s is a required field.', array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}

			//	Check password
			if(!empty($password)) {
				if($confirm !== $password) {
					$message = __('Passwords do not match.');
					return self::__INVALID_FIELDS__;
				}

				if(strlen($password) < (int)$this->get('length')) {
					$message = __('Password is too short. It must be at least %d characters.', array($this->get('length')));
					return self::__INVALID_FIELDS__;
				}

				if (!fieldMemberPassword::compareStrength(fieldMemberPassword::checkPassword($password), $this->get('strength'))) {
					$message = __('Password is not strong enough.');
					return self::__INVALID_FIELDS__;
				}
			}
			else if(!isset($data['optional'])) {
				$message = __('%s cannot be blank.', array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id = null){
			$status = self::__OK__;
			$required = ($this->get('required') == "yes");

			if(empty($data)) return array();

			$password = trim($data['password']);

			//	We only want to run the processing if the password has been altered
			//	or if the entry hasn't been created yet. If someone attempts to change
			//	their username, but not their password, this will be caught by checkPostFieldData
			if(!empty($password) || is_null($entry_id)) {
				return array(
					'password'	=> $this->encodePassword($password),
					'strength'	=> fieldMemberPassword::checkPassword($password),
					'length'	=> strlen($password)
				);
			}

			else return $this->rememberData($entry_id);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!isset($data['password'])) return;

			$pw = new XMLElement($this->get('element_name'));

			// If reset is set, return the recovery-code
			if($data['reset'] == 'yes') {
				$pw->setAttribute('reset-requested', 'yes');

				$pw->appendChild(
					new XMLElement('recovery-code', $data['recovery-code'])
				);
				
			}
			// Output the hash of the password.
			else {
				$pw->setValue($data['password']);
			}

			$wrapper->appendChild($pw);
		}

		public function prepareTableValue($data, XMLElement $link=NULL){
			if(empty($data)) return __('None');

			return parent::prepareTableValue(array(
				'value' => ucwords($data['strength']) . ' (' . $data['length'] . ')'
			), $link);
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			if($andOperation) {
				foreach($data as $key => $value) {
					$this->_key++;
					$value = $this->encodePassword($value);
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND `t$field_id$key`.password = '$value' ";
				}

			}
			else {
				if (is_array($data) and isset($data['password'])) {
					$data = array($data['password']);
				}
				else if (!is_array($data)) {
					$data = array($data);
				}

				foreach ($data as &$value) {
					$value = $this->encodePassword($value);
				}

				$data = implode("', '", $data);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.password IN ('{$data}') ";
			}

			return true;

		}

	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		public function getExampleFormMarkup(){
			$fieldset = new XMLElement('fieldset');

			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').'][password]', null, 'password'));

			$fieldset->appendChild($label);

			$label = Widget::Label($this->get('label') . ' ' . __('Confirm'));
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').'][confirm]', null, 'password'));

			$fieldset->appendChild($label);

			return $fieldset;
		}

	}
