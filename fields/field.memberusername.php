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

		public function isSortable(){
			return true;
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
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

		public function createTable(){
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(255) default NULL,
				  `handle` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `value` (`value`),
				  UNIQUE KEY `username` (`handle`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
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

			if(empty($username)) {
				extension_Members::$_errors[$this->get('element_name')] = array(
					'message' => __('\'%s\' is a required field.', array($this->get('label'))),
					'type' => 'missing',
					'label' => $this->get('label')
				);
				return null;
			}

			$member_id = Symphony::Database()->fetchVar('entry_id', 0, sprintf(
				"SELECT `entry_id` FROM `tbl_entries_data_%d` WHERE `handle` = '%s' LIMIT 1",
				$this->get('id'), Lang::createHandle($username)
			));

			if(is_null($member_id)) {
				extension_Members::$_errors[$this->get('element_name')] = array(
					'message' => __("Member not found."),
					'type' => 'invalid',
					'label' => $this->get('label')
				);
				return null;
			}
			else return $member_id;
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

			$wrapper->appendChild($group);

			$div = new XMLElement('div', null, array('class' => 'compact'));
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldMemberUsername::createSettingsTable();

			$fields = array(
				'field_id' => $id,
				'validator' => $this->get('validator')
			);

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

			//	If the field is required
			if(($this->get('required') == "yes") && empty($username)) {
				$message = __('\'%s\' is a required field.', array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}

			//	Check Username
			if(!empty($username)) {
				if($this->get('validator') && !General::validateString($username, $this->get('validator'))) {
					$message = __('%s contains invalid characters.', array($this->get('label')));
					return self::__INVALID_FIELDS__;
				}

				// We need to make sure the value doesn't already exist in the Section.
				$existing = $this->fetchMemberIDBy($username);

				// If there is an existing username, and it's not the current object (editing), error.
				if(!is_null($existing) && $existing != $entry_id) {
					$message = __('%s is already taken.', array($this->get('label')));
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
					'handle' => $data['handle']
				))
			);
		}

	}
