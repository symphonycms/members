<?php

    require_once EXTENSIONS . '/members/lib/class.identity.php';
	require_once FACE . '/interface.exportablefield.php';
	require_once FACE . '/interface.importablefield.php';

	Class fieldMemberUsername extends Identity implements ExportableField, ImportableField {

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();
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
			// return Symphony::Database()->query("
			// 	CREATE TABLE IF NOT EXISTS `tbl_fields_memberusername` (
			// 	  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			// 	  `field_id` INT(11) UNSIGNED NOT NULL,
			// 	  `validator` VARCHAR(255) DEFAULT NULL,
			// 	  PRIMARY KEY  (`id`),
			// 	  UNIQUE KEY `field_id` (`field_id`)
			// 	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			// ");
			return Symphony::Database()
				->create('tbl_fields_memberusername')
				->ifNotExists()
				->charset('utf8')
				->collate('utf8_unicode_ci')
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'field_id' => 'int(11)',
					'validator' => [
						'type' => 'varchar(255)',
						'null' => true,
					],
				])
				->keys([
					'id' => 'primary',
					'field_id' => 'unique',
				])
				->execute()
				->success();
		}

		public function createTable(){
			// return Symphony::Database()->query("
			// 	CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
			// 	  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			// 	  `entry_id` INT(11) UNSIGNED NOT NULL,
			// 	  `value` VARCHAR(255) DEFAULT NULL,
			// 	  `handle` VARCHAR(255) DEFAULT NULL,
			// 	  PRIMARY KEY  (`id`),
			// 	  KEY `entry_id` (`entry_id`),
			// 	  KEY `value` (`value`),
			// 	  UNIQUE KEY `username` (`handle`)
			// 	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			// ");
			return Symphony::Database()
				->create('tbl_entries_data_' . $this->get('id'))
				->ifNotExists()
				->charset('utf8')
				->collate('utf8_unicode_ci')
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'entry_id' => 'int(11)',
					'value' => [
						'type' => 'varchar(255)',
						'null' => true,
					],
					'handle' => [
						'type' => 'varchar(255)',
						'null' => true,
					],
				])
				->keys([
					'id' => 'primary',
					'entry_id' => 'key',
					'value' => 'key',
					'username' => [
						'type' => 'unique',
						'cols' => ['handle'],
					],
				])
				->execute()
				->success();
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
		public function fetchMemberIDBy($needle, $member_id = null) {
			$username = null;
			if (is_array($needle) && !empty($needle['username'])) {
				$username = $needle['username'];
			} else {
				$username = $needle;
			}

			if(empty($username)) {
				extension_Members::$_errors[$this->get('element_name')] = array(
					'message' => __('\'%s\' is a required field.', array($this->get('label'))),
					'message-id' => EventMessages::FIELD_MISSING,
					'type' => 'missing',
					'label' => $this->get('label')
				);
				return null;
			}

			// $member_id = Symphony::Database()->fetchVar('entry_id', 0, sprintf(
			// 	"SELECT `entry_id` FROM `tbl_entries_data_%d` WHERE `handle` = '%s' LIMIT 1",
			// 	$this->get('id'), Lang::createHandle($username)
			// ));
			$member_id = Symphony::Database()
				->select(['entry_id'])
				->from('tbl_entries_data_' . $this->get('id'))
				->where(['handle' => Lang::createHandle($username)])
				->limit(1)
				->execute()
				->variable('entry_id');

			if(is_null($member_id)) {
				extension_Members::$_errors[$this->get('element_name')] = array(
					'message' => __("Member not found."),
					'message-id' => MemberEventMessages::MEMBER_INVALID,
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

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
			parent::displaySettingsPanel($wrapper, $errors);

			$this->buildValidationSelect($wrapper, $this->get('validator'), 'fields['.$this->get('sortorder').'][validator]');

			$div = new XMLElement('div', null, array('class' => 'two columns'));
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

			return FieldManager::saveSettings($id, $fields);
		}

		public function setFromPOST(array $settings = array()){
			parent::setFromPOST($settings);
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
				$message = __('%s is a required field.', array($this->get('label')));
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

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null){
			if(!isset($data['value'])) return;

			$wrapper->appendChild(
				new XMLElement($this->get('element_name'), General::sanitize($data['value']), array(
					'handle' => $data['handle']
				))
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
	}
