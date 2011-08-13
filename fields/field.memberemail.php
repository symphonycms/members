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

		public function isSortable(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public static function createSettingsTable() {
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_memberemail` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `value` (`value`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
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

			if(empty($email)) {
				extension_Members::$_errors[$this->get('element_name')] = array(
					'message' => __('\'%s\' is a required field.', array($this->get('label'))),
					'type' => 'missing',
					'label' => $this->get('label')
				);
				return null;
			}

			$member_id = Symphony::Database()->fetchVar('entry_id', 0, sprintf(
				"SELECT `entry_id` FROM `tbl_entries_data_%d` WHERE `value` = '%s' LIMIT 1",
				$this->get('id'), $email
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

			$div = new XMLElement('div', null, array('class' => 'compact'));
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldMemberEmail::createSettingsTable();

			$fields = array(
				'field_id' => $id
			);

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

			//	If the field is required
			if($required && empty($email)) {
				$message = __('\'%s\' is a required field.', array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}

			//	Check Email Address
			if(!empty($email)) {
				if(!fieldMemberEmail::__applyValidationRule($email)) {
					$message = __('%s contains invalid characters.', array($this->get('label')));
					return self::__INVALID_FIELDS__;
				}

				// We need to make sure the value doesn't already exist in the Section.
				$existing = $this->fetchMemberIDBy($email);

				// If there is an existing email, and it's not the current object (editing), error.
				if(!is_null($existing) && $existing != $entry_id) {
					$message = __('%s is already taken.', array($this->get('label')));
					return self::__INVALID_FIELDS__;
				}
			}

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id = null){
			$status = self::__OK__;

			if(empty($data)) return array();

			return array(
				'value' => trim($data)
			);
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			// Filter is an regexp.
			if(self::isFilterRegex($data[0])) {
				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (
								`t$field_id`.value REGEXP '$pattern'
								OR `t$field_id`.entry_id REGEXP '$pattern'
							) ";
			}

			// Filter has + in it.
			else if($andOperation) {
				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND (
									`t$field_id$key`.value = '$bit'
									OR `t$field_id`.entry_id = '$bit'
								) ";
				}
			}

			// Normal
			else {
				if(!is_array($data)) {
					$data = array($data);
				}

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (
								`t$field_id`.value IN ('".implode("', '", $data)."')
								OR `t$field_id`.entry_id IN ('".implode("', '", $data)."')
							) ";
			}

			return true;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!isset($data['value'])) return;

			$wrapper->appendChild(
				new XMLElement($this->get('element_name'), General::sanitize($data['value']), array(
					'hash' => md5($data['value'])
				))
			);
		}

	}
