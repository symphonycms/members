<?php

	Class fieldMember extends Field{

		static private $_driver;

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Member: Username');
			$this->_required = true;
			$this->set('required', 'yes');

			if(!(self::$_driver instanceof Extension)){
				if(class_exists('Frontend')){
					self::$_driver = Frontend::instance()->ExtensionManager->create('members');
				}

				else{
					self::$_driver = Administration::instance()->ExtensionManager->create('members');
				}
			}
		}

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `username` varchar(150) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `username` (`username`)
				) ENGINE=MyISAM;"
			);
		}

		function canFilter(){
			return true;
		}

		function allowDatasourceParamOutput(){
			return true;
		}

		function canPrePopulate(){
			return true;
		}

		public function mustBeUnique(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function fetchMemberFromID($member_id){
			return self::$_driver->Member->initialiseMemberObject($member_id);
		}

		public function fetchMemberFromUsername($username){
			$member_id = Symphony::Database()->fetchVar('entry_id', 0,
				"SELECT `entry_id` FROM `tbl_entries_data_".$this->get('id')."` WHERE `username` = '{$username}' LIMIT 1"
			);
			return ($member_id ? $this->fetchMemberFromID($member_id) : NULL);
		}

		public function getExampleFormMarkup(){

			$label = Widget::Label('Username');
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').'][username]'));

			return $label;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			$order = $this->get('sortorder');

			$this->buildValidationSelect($wrapper, $this->get('validator'), 'fields['.$this->get('sortorder').'][validator]');
			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		public function checkFields(&$errors, $checkForDuplicates = true) {
			parent::checkFields($errors, $checkForDuplicates);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array(
				'field_id' => $id,
				'validator' =>$this->get('validator')
			);

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		public function setFromPOST($postdata){
			parent::setFromPOST($postdata);
			if($this->get('validator') == '') $this->remove('validator');
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data=NULL, $error =NULL, $prefix =NULL, $postfix =NULL, $entry_id = null){
			$required = ($this->get('required') == 'yes');
			$field_id = $this->get('id');
			$handle = $this->get('element_name');

			$container = new XMLElement('div');
			$container->setAttribute('class', 'container');

		//	Username
			$label = Widget::Label(__('Username'));
			if(!$required) $label->appendChild(new XMLElement('i', __('Optional')));

			$label->appendChild(Widget::Input(
				"fields{$prefix}[{$handle}][username]{$postfix}", $data['username']
			));

			$container->appendChild($label);

		//	Error?
			if(!is_null($error)) {
				$label = Widget::wrapFormElementWithError($container, $error);
				$wrapper->appendChild($label);
			}
			else {
				$wrapper->appendChild($container);
			}
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$message = null;
			$required = ($this->get('required') == "yes");

			$username = trim($data['username']);

			//	If the field is required, we should have both a $username and $password.
			if($required && empty($username)) {
				$message = __('Username is a required field.');
				return self::__MISSING_FIELDS__;
			}

			//	Check Username
			if(!empty($username)) {
				if($this->get('validator') && !General::validateString($username, $this->get('validator'))) {
					$message = __('Username contains invalid characters.');
					return self::__INVALID_FIELDS__;
				}

				$existing = $this->fetchMemberFromUsername($username);

				//	If there is an existing username, and it's not the current object (editing), error.
				if($existing instanceof Entry && $existing->get('id') !== $entry_id) {
					$message = __('That username is already taken.');
					return self::__INVALID_FIELDS__;
				}
			}

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id = null){
			$status = self::__OK__;

			if(empty($data)) return array();

			$username = trim($data['username']);

			return array(
				'username' 	=> $username,
			);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!isset($data['username'])) return;
			$wrapper->appendChild(
				new XMLElement(
					$this->get('element_name'),
					General::sanitize($data['username'])
			));
		}

		public function prepareTableValue($data, XMLElement $link=NULL){
			if(empty($data)) return __('None');

			return parent::prepareTableValue(array('value' => General::sanitize($data['username'])), $link);
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			if(self::isFilterRegex($data[0])):

				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.username REGEXP '$pattern' ";

			elseif($andOperation):

				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND `t$field_id$key`.username = '$bit' ";
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.username IN ('".@implode("', '", $data)."') ";

			endif;

			return true;

		}

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC', $useIDFieldForSorting=false){

			$sort_field = (!$useIDFieldForSorting ? 'ed' : 't' . $this->get('id'));

			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `$sort_field` ON (`e`.`id` = `$sort_field`.`entry_id`) ";
			$sort .= (strtolower($order) == 'random' ? 'RAND()' : "`$sort_field`.`username` $order");
		}

	}
