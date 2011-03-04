<?php

    require_once(EXTENSIONS . '/members/lib/class.identity.php');

	Class fieldMemberUsername extends Identity {

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Member: Username');
			$this->set('required', 'yes');
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public static function createSettingsTable() {
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_memberusername` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `validator` varchar(255) DEFAULT NULL,
				  `options` ENUM('unique', 'unique-and-identify') DEFAULT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM;
			");
		}

		public function createTable(){
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `username` (`value`)
				) ENGINE=MyISAM;
			");
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * Given a `$needle`, this function will return the Member object.
		 * If the `$needle` passed is an array, this function expects a
		 * key of 'username'
		 *
		 * @param string|array $needle
		 * @return Entry
		 */
		public function fetchMemberIDBy($needle) {
			if(is_array($needle)) {
				extract($needle);
			}
			else {
				$username = $needle;
			}

			$member_id = Symphony::Database()->fetchVar('entry_id', 0, sprintf(
				"SELECT `entry_id` FROM `tbl_entries_data_%d` WHERE `value` = '%s' LIMIT 1",
				$this->get('id'), Symphony::Database()->cleanValue($username)
			));

			return ($member_id ? $member_id : NULL);
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$group = new XMLElement('div', null, array('class' => 'group'));

			$div = new XMLElement('div');
			$this->buildValidationSelect($div, $this->get('validator'), 'fields['.$this->get('sortorder').'][validator]');
			$group->appendChild($div);

			$this->buildIdentitySelect($group);

			$wrapper->appendChild($group);

			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldMemberUsername::createSettingsTable();

			$fields = array(
				'field_id' => $id,
				'validator' => $this->get('validator'),
				'options' => $this->get('options')
			);

			Symphony::Configuration()->set('identity', $id, 'members');
			Administration::instance()->saveConfig();

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		public function setFromPOST($postdata){
			parent::setFromPOST($postdata);
			if($this->get('validator') == '') $this->remove('validator');
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$message = null;

			$username = trim($data);

			//	If the field is required, we should have both a $username and $password.
			if(($this->get('required') == "yes") && empty($username)) {
				$message = __('% is a required field.', array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}

			//	Check Username
			if(!empty($username)) {
				if($this->get('validator') && !General::validateString($username, $this->get('validator'))) {
					$message = __('%s contains invalid characters.', array($this->get('label')));
					return self::__INVALID_FIELDS__;
				}

				// If this field has any options (unique or unique & identify), we need to make sure the
				// value doesn't already exist in the Section.
				if(!is_null($this->get('options'))) {
					$existing = $this->fetchMemberIDBy($username);

					// If there is an existing username, and it's not the current object (editing), error.
					if(!is_null($existing) && $existing != $entry_id) {
						$message = __('That %s is already taken.', array($this->get('label')));
						return self::__INVALID_FIELDS__;
					}
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
				new XMLElement(
					$this->get('element_name'),
					General::sanitize($data['value'])
			));
		}

	}
