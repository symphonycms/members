<?php

	require_once(TOOLKIT . '/fields/field.select.php');

	/**
	 * Activation field. If added to a Members section, it generates and stores
	 * activation codes for new members, handles activation via normal events,
	 * sends emails, and displays as a checkbox in the backend publish area.
	 */

	Class fieldMemberActivation extends fieldSelect {

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();
			$this->_name = __('Member: Activation');
			$this->_showassociation = false;
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

		public function canPrePopulate(){
			return false;
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
				  `activation_role_id` int(11) unsigned NOT NULL,
				  `deny_login` enum('yes','no') NOT NULL default 'yes',
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
				  `activated` enum('yes','no') NOT NULL default 'no',
				  `timestamp` DATETIME default NULL,
				  `code` varchar(40) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `code` (`code`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
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
				$code = General::hash(uniqid(), 'sha1');
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
		 * @todo possibly return if the code didn't exist or if it was expired
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
				DateTimeObj::get('Y-m-d H:i:s', strtotime('now + ' . $this->get('code_expiry')))
			));

			if(is_array($code) && !empty($code) && !is_null($code['code'])) {
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

			return Symphony::Database()->update(
				array(
					'code' => null
				),
				"`tbl_entries_data_{$this->get('id')}`",
				sprintf("`activated` = 'no' AND DATE_FORMAT(timestamp, '%%Y-%%m-%%d %%H:%%i:%%s') < '%s' %s",
					DateTimeObj::get('Y-m-d H:i:s', strtotime('now - ' . $this->get('code_expiry'))),
					($entry_id ? " OR `entry_id` = $entry_id" : '')
				)
			);
		}

		public static function findCodeExpiry() {
			return extension_Members::findCodeExpiry('tbl_fields_memberactivation');
		}

		public function getToggleStates() {
			return array('yes' => __('Yes'), 'no' => __('No'));
		}

		public function toggleFieldData($data, $newState, $entry_id){
			$data['activated'] = $newState;

			if($data['activated'] == 'no') {
				$data = array_merge($data, $this->generateCode($entry_id));
			}
			else {
				$data['timestamp'] = DateTimeObj::get('Y-m-d H:i:s', time());
			}

			return $data;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function setFromPOST(array $settings = array()) {
			$settings['deny_login'] = (isset($settings['deny_login']) && $settings['deny_login'] == 'yes' ? 'yes' : 'no');

			parent::setFromPOST($settings);
		}

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			Field::displaySettingsPanel($wrapper, $errors);

			$group = new XMLElement('div');
			$group->setAttribute('class', 'two columns');

			// Add Activiation Code Expiry
			$div = new XMLElement('div');
			$div->setAttribute('class', 'column');

			$label = Widget::Label(__('Activation Code Expiry'));
			$label->appendChild(
				new XMLElement('i', __('How long a member\'s activation code will be valid for before it expires'))
			);
			$label->appendChild(Widget::Input(
				"fields[{$this->get('sortorder')}][code_expiry]", $this->get('code_expiry')
			));

			$ul = new XMLElement('ul', NULL, array('class' => 'tags singular'));
			$tags = fieldMemberActivation::findCodeExpiry();
			foreach($tags as $name => $time) {
				$ul->appendChild(new XMLElement('li', $name, array('class' => $time)));
			}

			$div->appendChild($label);
			$div->appendChild($ul);

			if (isset($errors['code_expiry'])) {
				$div = Widget::Error($div, $errors['code_expiry']);
			}

			// Get Roles in system
			$roles = RoleManager::fetch();
			$options = array();
			if(is_array($roles) && !empty($roles)) {
				foreach($roles as $role) {
					$options[] = array($role->get('id'), ($this->get('activation_role_id') == $role->get('id')), $role->get('name'));
				}
			}

			$label = new XMlElement('label', __('Role for Members who are awaiting activation'));
			$label->setAttribute('class', 'column');
			$label->appendChild(Widget::Select(
				"fields[{$this->get('sortorder')}][activation_role_id]", $options
			));

			$group->appendChild($label);

			// Add Group
			$group->appendChild($div);
			$wrapper->appendChild($group);

			$div = new XMLElement('div', null, array('class' => 'two columns'));

			// Add Deny Login
			$div->appendChild(Widget::Input("fields[{$this->get('sortorder')}][deny_login]", 'no', 'hidden'));

			$label = Widget::Label();
			$label->setAttribute('class', 'column');
			$input = Widget::Input("fields[{$this->get('sortorder')}][deny_login]", 'yes', 'checkbox');

			if ($this->get('deny_login') == 'yes') $input->setAttribute('checked', 'checked');

			$label->setValue(__('%s Prevent unactivated members from logging in', array($input->generate())));

			$div->appendChild($label);

			// Add Show Column
			$this->appendShowColumnCheckbox($div);

			$wrapper->appendChild($div);
		}

		public function checkFields(&$errors, $checkForDuplicates=true) {
			Field::checkFields($errors, $checkForDuplicates);

			if (trim($this->get('code_expiry')) == '') {
				$errors['code_expiry'] = __('This is a required field.');
			}

			if(!DateTimeObj::validate($this->get('code_expiry'))) {
				$errors['code_expiry'] = __('Code expiry must be a unit of time, such as <code>1 day</code> or <code>2 hours</code>');
			}
		}

		public function commit(){
			if(!Field::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldMemberActivation::createSettingsTable();

			$fields = array(
				'field_id' => $id,
				'code_expiry' => $this->get('code_expiry'),
				'activation_role_id' => $this->get('activation_role_id'),
				'deny_login' => $this->get('deny_login') == 'yes' ? 'yes' : 'no'
			);

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$isActivated = ($data['activated'] == 'yes');

			// If $entry_id is null, just preset to Activated Account as it means an authorised user
			// has enough access to create the record in the backend anyway.
			$options = array(
				array('no', ($data['activated'] == 'no'), __('Not Activated')),
				array('yes', ($isActivated || is_null($entry_id) || is_null($data)), __('Activated'))
			);

			$label = Widget::Label($this->get('label'));
			if(!$isActivated) {
				$label->appendChild(Widget::Select(
					'fields'.$prefix.'['.$this->get('element_name').']'.$postfix, $options
				));
			}
			else {
				$label->appendChild(Widget::Input(
					'fields'.$prefix.'['.$this->get('element_name').']'.$postfix, 'yes', 'hidden'
				));
			}

			// Member not activated
			if(!$isActivated && !is_null($data)) {
				// If code is still live, displays when the code was generated.
				if($this->isCodeActive($entry_id) !== false) {
					$label->appendChild(
						new XMLElement('span', __('Activation code %s', array('<code>' . $data['code'] . '</code>')), array('class' => 'frame'))
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
				$wrapper->appendChild(Widget::Error($label, $error));
			}
			else {
				$wrapper->appendChild($label);
			}
		}

		public function processRawFieldData($data, &$status, &$message=null, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;

			if(is_null($data) && !is_null($entry_id)) {
				$entry = EntryManager::fetch($entry_id);

				$data = $entry[0]->getData($this->get('id'));
			}
			else {
				if(!is_array($data)) {
					$data = array('activated' => $data);
				}

				if($data['activated'] == 'no') {
					$data = array_merge($data, $this->generateCode($entry_id));
				}
				else {
					$data['timestamp'] = DateTimeObj::get('Y-m-d H:i:s', time());
				}
			}

			return $data;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false, $mode = null, $entry_id = null){
			if (!is_array($data) or is_null($data['activated'])) return;

			$el = new XMLElement($this->get('element_name'));
			$el->setAttribute('activated', $data['activated']);

			if($data['activated'] == 'yes') {
				// Append the time the person was activated
				$el->appendChild(
					General::createXMLDateObject(strtotime($data['timestamp']), 'date')
				);
			}
			else {
				// Append the code
				$el->appendChild(
					new XMLElement('code', $data['code'])
				);

				// Add expiry timestamp, including how long the code is valid for
				$expiry = General::createXMLDateObject(strtotime($data['timestamp'] . ' + ' . $this->get('code_expiry')), 'expires');
				$expiry->setAttribute('expiry', $this->get('code_expiry'));
				$el->appendChild($expiry);
			}

			$wrapper->appendChild($el);
		}

		public function prepareTableValue($data, XMLElement $link=NULL) {
			return parent::prepareTableValue(array(
				'value' => ($data['activated'] == 'yes') ? __('Activated') : __('Not Activated')
			), $link);
		}

		public function getParameterPoolValue($data, $entry_id = null) {
			return $data['activated'];
		}

	/*-------------------------------------------------------------------------
		Sorting:
	-------------------------------------------------------------------------*/

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC') {
			if(in_array(strtolower($order), array('random', 'rand'))) {
				$sort = 'ORDER BY RAND()';
			}
			else {
				$sort = sprintf(
					'ORDER BY (
						SELECT %s
						FROM tbl_entries_data_%d AS `ed`
						WHERE entry_id = e.id
					) %s',
					'`ed`.activated',
					$this->get('id'),
					$order
				);
			}
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation=false){

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
