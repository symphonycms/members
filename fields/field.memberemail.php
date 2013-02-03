<?php

    require_once EXTENSIONS . '/members/lib/class.identity.php';
	require_once FACE . '/interface.exportablefield.php';
	require_once FACE . '/interface.importablefield.php';

	Class fieldMemberEmail extends Identity implements ExportableField, ImportableField {

		protected static $validator = '/^\w(?:\.?[\w%+-]+)*@\w(?:[\w-]*\.)+?[a-z]{2,}$/i';

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();
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

		public function fetchMemberIDBy($needle, $member_id = null) {
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

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$div = new XMLElement('div', null, array('class' => 'two columns'));
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

			return FieldManager::saveSettings($id, $fields);
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
				$message = __('%s is a required field.', array($this->get('label')));
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

		public function processRawFieldData($data, &$status, &$message=null, $simulate=false, $entry_id = null){
			$status = self::__OK__;

			if(empty($data)) return array();

			return array(
				'value' => trim($data)
			);
		}

	/*-------------------------------------------------------------------------
		Import:
	-------------------------------------------------------------------------*/

		public function getImportModes() {
			return array(
				'getValue' =>		ImportableField::STRING_VALUE,
				'getPostdata' =>	ImportableField::ARRAY_VALUE
			);
		}

		public function prepareImportValue($data, $mode, $entry_id = null) {
			$message = $status = null;
			$modes = (object)$this->getImportModes();

			if($mode === $modes->getValue) {
				return $data;
			}
			else if($mode === $modes->getPostdata) {
				return $this->processRawFieldData($data, $status, $message, true, $entry_id);
			}

			return null;
		}

	/*-------------------------------------------------------------------------
		Export:
	-------------------------------------------------------------------------*/

		public function getExportModes() {
			return array(
				ExportableField::POSTDATA
			);
		}

		public function prepareExportValue($data, $mode, $entry_id = null) {
			if (isset($data['value'])) {
				return $data['value'];
			}

			return null;
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			// Filter is an regexp.
			if(self::isFilterRegex($data[0])) {
				$this->buildRegexSQL($data[0], array('value', 'entry_id'), $joins, $where);
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

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null){
			if(!isset($data['value'])) return;

			$mail = explode('@', General::sanitize($data['value']));

			$wrapper->appendChild(
				new XMLElement($this->get('element_name'), General::sanitize($data['value']), array(
					'hash' => md5($data['value']),
					'alias' => $mail[0],
					'domain' => $mail[1]
				))
			);
		}

	}
