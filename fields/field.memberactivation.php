<?php

	/**
	 * Activation field. If added to a Members section, it generates and stores
	 * activation codes for new members, handles activation via normal events,
	 * sends emails, and displays as a checkbox in the backend publish area.
	 *
	 * === Events ===
	 * POST data submitted to an activation field can accept one of two kinds of
	 * data: an activation code, which will activate the member, or a response code.
	 * Response codes are predefined codes used to get the field to do something,
	 * like regenerate and reissue an activation code.
	*/

	Class fieldMemberActivation extends Field {

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Member: Activation';
		}

		public function canToggle(){
			return true;
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
				CREATE TABLE IF NOT EXISTS `tbl_fields_memberactivation` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `code_expiry` varchar(50) NOT NULL,
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
				  `activated` enum('yes','no') NOT NULL default 'no',
				  `timestamp` DATETIME default NULL,
				  `code` varchar(40) NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`)
				) ENGINE=MyISAM;"
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * Given a `$entry_id`, check to see whether this Entry has a valid
		 * code, if it doesn't, generate one and return an array for insertion
		 * into the entry table.
		 *
		 * @param integer $entry_id
		 * @return array
		 */
		public function generateCode($entry_id = null){
			$code = false;

			if(!is_null($entry_id)) {
				$code = $this->isCodeActive($entry_id);
				if($code !== false) return $code;
			}

			// Generate a code
			do {
				$code = sha1(uniqid());
				$row = Symphony::Database()->fetchRow(0, "
					SELECT 1 FROM `tbl_entries_data_{$this->get('id')}` WHERE `code` = '{$code}'
				");
			} while(is_array($row) && !empty($row));

			$data = array(
				'code' => $code,
				'timestamp' => DateTimeObj::get('Y-m-d H:i:s', time())
			);

			return $data;
		}

		/**
		 * Given an `$entry_id`, this function will check to see if the
		 * code generated is still valid by comparing it's generation timestamp
		 * with the maximum code expiry time.
		 *
		 * @param integer $entry_id
		 * @return array
		 */
		public function isCodeActive($entry_id) {
			// First check if a code already exists
			$code = Symphony::Database()->fetchRow(0, sprintf("
				SELECT `code`, `timestamp` FROM `tbl_entries_data_%d`
				WHERE `entry_id` = %d
				AND DATE_FORMAT(timestamp, '%%Y-%%m-%%d %%H:%%i:%%s') < '%s'
				LIMIT 1",
 				$this->get('id'),
				$entry_id,
				DateTimeObj::get('Y-m-d H:i:s', strtotime('now - ' . $this->get('code_expiry')))
			));

			if(is_array($code) && !empty($code)) {
				return $code;
			}
			else {
				return false;
			}
		}

		/**
		 * This function will remove all the codes from the database that are
		 * invalid. An optional `$entry_id` parameter allows the code to just
		 * be removed on a per member basis, or whether it should be a global
		 * purge.
		 *
		 * @param integer $entry_id
		 * @return boolean
		 */
		public function purgeCodes($entry_id = null){
			$entry_id = Symphony::Database()->cleanValue($entry_id);

			Symphony::Database()->delete("`tbl_entries_data_{$this->get('id')}`", sprintf("`timestamp` <= %s %s",
				time(), ($entry_id ? " OR `entry_id` = $entry_id" : '')
			));
		}

		public static function findCodeExpiry() {
			$default = array('1 hour' => '1 hour', '24 hours' => '24 hours');

			try {
				$used = Symphony::Database()->fetchCol('code_expiry', sprintf("
					SELECT DISTINCT(code_expiry) FROM `tbl_fields_memberactivation`
				"));

				if(is_array($used) && !empty($used)) {
					$default = array_merge($default, array_combine($used, $used));
				}
			}
			catch (DatabaseException $ex) {
				// Table doesn't exist yet, it's ok we have defaults.
			}

			return $default;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$label = Widget::Label(__('Activation Code Expiry'));
			$label->appendChild(
				new XMLElement('i', __('How long a user\'s activation code will be valid for before it expires'))
			);
			$label->appendChild(Widget::Input(
				"fields[{$this->get('sortorder')}][code_expiry]", $this->get('code_expiry')
			));

			$ul = new XMLElement('ul', NULL, array('class' => 'tags singular'));
			$tags = fieldMemberActivation::findCodeExpiry();
			foreach($tags as $name => $time) {
				$ul->appendChild(new XMLElement('li', $name, array('class' => $time)));
			}

			$wrapper->appendChild($label);
			$wrapper->appendChild($ul);

			$this->appendShowColumnCheckbox($wrapper);
		}

		public function checkFields(&$errors, $checkForDuplicates=true) {
			Field::checkFields(&$errors, $checkForDuplicates);

			if (trim($this->get('code_expiry')) == '') {
				$errors['code_expiry'] = __('This is a required field.');
			}
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldMemberActivation::createSettingsTable();

			$fields = array(
				'field_id' => $id,
				'code_expiry' => $this->get('code_expiry')
			);

			Symphony::Configuration()->set('activation', $id, 'members');
			Administration::instance()->saveConfig();

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		public function fieldCleanup() {
			Symphony::Configuration()->set('activation', null, 'members');
			Administration::instance()->saveConfig();

			return true;
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$isActivated = ($data['activated'] == 'yes');

			// If $entry_id is null, just preset to Activated Account as it means an authorised user
			// has enough access to create the record in the backend anyway.
			$options = array(
				array('no', ($data['activated'] == 'no'), __('Account not Activated')),
				array('yes', ($isActivated || is_null($entry_id) or is_null($data)), __('Account Activated'))
			);

			$label = Widget::Label($this->get('label'));
			if(!$isActivated) {
				$label->appendChild(Widget::Select(
					'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, $options
				));
			}
			else {
				$label->appendChild(Widget::Input(
					'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, 'yes', 'hidden'
				));
			}

			// Member not activated
			if(!$isActivated) {
				if(!is_null($data)) {
					// If code is still live, displays when the code was generated.
					if(strtotime($data['timestamp']) < strtotime('now + ' . $this->get('code_expiry'))) {
						$label->appendChild(
							new XMLElement('i', __('Activation code %s', array($data['code'])))
						);
					}
					// If the code is expired, displays 'Expired' w/the expiration timestamp.
					else {
						$label->appendChild(
							new XMLElement('i', __('Activation code expired %s', array(
								DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($data['timestamp']))
							)))
						);
					}
				}
			}
			else {
				if(is_null($data)) {
					// If member is activated, help text shows datetime activated
					$label->appendChild(
						new XMLElement('i', __('Account will be activated when entry is saved'))
					);
				}
				else {
					// If member is activated, help text shows datetime activated
					$label->appendChild(
						new XMLElement('i', __('Activated %s', array(
							DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($data['timestamp']))
						)))
					);
				}
			}

			if(!is_null($error)) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $error));
			}
			else {
				$wrapper->appendChild($label);
			}
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;

			$data = array(
				'activated' => $data
			);

			if($data['activated'] == "no") {
				$data = array_merge($data, $this->generateCode($entry_id));
			}
			else {
				$data['timestamp'] = DateTimeObj::get('Y-m-d H:i:s', time());
			}

			return $data;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			if (!is_array($data) or is_null($data['activated'])) return;

			$el = new XMLElement($this->get('element_name'));
			$el->setAttribute('activated', $data['activated']);

			if($data['activated'] == 'yes') {
				$el->appendChild(
					General::createXMLDateObject(strtotime($data['timestamp']), 'date')
				);
			}
			else {
				$el->appendChild(
					new XMLElement('code', $data['code'])
				);
				$el->appendChild(
					General::createXMLDateObject(strtotime($data['timestamp'] . ' + ' . $this->get('code_expiry')), 'expires')
				);
			}

			$wrapper->appendChild($el);
		}

		public function prepareTableValue($data, XMLElement $link=NULL) {
			return parent::prepareTableValue(array(
				'value' => ($data['activated'] == 'yes') ? __('Activated') : __('Not Activated')
			), $link);
		}

	/*-------------------------------------------------------------------------
		Sorting:
	-------------------------------------------------------------------------*/

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC') {
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort .= (strtolower($order) == 'random' ? 'RAND()' : "`ed`.`activated` $order");
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			// Filter has + in it.
			if($andOperation) {
				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND `t$field_id$key`.activated = '$bit' ";
				}
			}

			// Normal
			else {
				if(!is_array($data)) {
					$data = array($data);
				}

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.activated IN ('".implode("', '", $data)."') ";
			}

			return true;
		}


	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		public function getExampleFormMarkup(){

			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').']'));

			return $label;
		}

	}
