<?php

    require_once(EXTENSIONS . '/members/lib/class.identity.php');
    
	Class fieldMemberEmail extends Identity {

		static private $_driver;
		
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Member: Email');
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
		Setup:
	-------------------------------------------------------------------------*/

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `email` varchar(150) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `email` (`email`)
				) ENGINE=MyISAM;"
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function fetchMemberFromID($member_id){
			return self::$_driver->Member->initialiseMemberObject($member_id);
		}

		// Does this need to get moved out to the Identity class?
		
		public function fetchMemberFromEmail($email){
			$member_id = Symphony::Database()->fetchVar('entry_id', 0,
				"SELECT `entry_id` FROM `tbl_entries_data_".$this->get('id')."` WHERE `email` = '{$email}' LIMIT 1"
			);
			return ($member_id ? $this->fetchMemberFromID($member_id) : NULL);
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			$order = $this->get('sortorder');

			// We already know how we need to validate this, right?
			// $this->buildValidationSelect($wrapper, $this->get('validator'), 'fields['.$this->get('sortorder').'][validator]');
			
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
				
				// Set this explicitly?
				//'validator' =>$this->get('validator')
			);

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		public function setFromPOST($postdata){
			parent::setFromPOST($postdata);
			
			// Set this explicitly?
			//if($this->get('validator') == '') $this->remove('validator');
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
			$label = Widget::Label(__('Email Address'));
			if(!$required) $label->appendChild(new XMLElement('i', __('Optional')));

			$label->appendChild(Widget::Input(
				"fields{$prefix}[{$handle}][email]{$postfix}", $data['email']
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

			$email = trim($data['email']);

			//	If the field is required, we should have both a $username and $password.
			if($required && empty($email)) {
				$message = __('Email Address is a required field.');
				return self::__MISSING_FIELDS__;
			}

			//	Check Email Address
			if(!empty($email)) {
			
				// This should be explicitly validating the email address?
				if($this->get('validator') && !General::validateString($email, $this->get('validator'))) {
					$message = __('Email contains invalid characters.');
					return self::__INVALID_FIELDS__;
				}

				$existing = $this->fetchMemberFromEmail($email);

				//	If there is an existing email, and it's not the current object (editing), error.
				if($existing instanceof Entry && $existing->get('id') !== $entry_id) {
					$message = __('That email address is already registered.');
					return self::__INVALID_FIELDS__;
				}
			}

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id = null){
			$status = self::__OK__;

			if(empty($data)) return array();

			$email = trim($data['email']);

			return array(
				'email' 	=> $email,
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
					
					// Need to hash this?
					General::sanitize($data['email'])
			));
		}

		public function prepareTableValue($data, XMLElement $link=NULL){
			if(empty($data)) return __('None');

			return parent::prepareTableValue(array('value' => General::sanitize($data['email'])), $link);
		}

	/*-------------------------------------------------------------------------
		Sorting:
	-------------------------------------------------------------------------*/
	
		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC', $useIDFieldForSorting=false){

			$sort_field = (!$useIDFieldForSorting ? 'ed' : 't' . $this->get('id'));

			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `$sort_field` ON (`e`.`id` = `$sort_field`.`entry_id`) ";
			$sort .= (strtolower($order) == 'random' ? 'RAND()' : "`$sort_field`.`email` $order");
		}
		
	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			if(self::isFilterRegex($data[0])):

				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.email REGEXP '$pattern' ";

			elseif($andOperation):

				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND `t$field_id$key`.email = '$bit' ";
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.email IN ('".@implode("', '", $data)."') ";

			endif;

			return true;

		}
		
	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		public function getExampleFormMarkup(){

			$label = Widget::Label('Email Address');
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').'][email]'));

			return $label;
		}
	}
