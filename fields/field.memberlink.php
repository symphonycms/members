<?php

	Class fieldMemberLink extends Field{

		static private $_driver;

		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Member Link';
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

		function isSortable(){
			return true;
		}

		function canFilter(){
			return true;
		}

		function allowDatasourceOutputGrouping(){
			return true;
		}

		function allowDatasourceParamOutput(){
			return true;
		}

		function canPrePopulate(){
			return true;
		}

		function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));

				$value = $data['username'];

				if(!isset($groups[$this->get('element_name')][$value])){
					$groups[$this->get('element_name')][$value] = array('attr' => array('value' => $value),
																		 'records' => array(), 'groups' => array());
				}

				$groups[$this->get('element_name')][$value]['records'][] = $r;

			}

			return $groups;
		}

		public function getParameterPoolValue($data){
			return implode(',', (array)$data['username']);
		}

		function prepareTableValue($data, XMLElement $link=NULL){

			if(!is_array($data) || empty($data)) return;

			$value = NULL;
			if(isset($data['username']) && !is_array($data['username'])){
				$data['username'] = array($data['username']);
				$data['member_id'] = array($data['member_id']);
			}

			if(!is_null($link)){
				return parent::prepareTableValue(array('value' => @implode(', ', $data['username']), $link));
			}

			foreach($data['username'] as $index => $username){
				$a = Widget::Anchor($username, URL . '/symphony/publish/' . extension_Members::memberSectionHandle() . '/edit/' . $data['member_id'][$index] . '/', "Edit Member '{$username}'");
				$value .= $a->generate() . ', ';
			}

			return trim($value, ', ');

		}

		function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);


			$order = $this->get('sortorder');
			$name = "fields[{$order}][allow_multiple]";

			$wrapper->appendChild(Widget::Input($name, 'no', 'hidden'));

			$label = Widget::Label();
			$input = Widget::Input($name, 'yes', 'checkbox');

			if ($this->get('allow_multiple') == 'yes') $input->setAttribute('checked', 'checked');

			$label->setValue(__('%s Accept Comma Separated List of Members', array($input->generate())));

			$wrapper->appendChild($label);


			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			if(isset($data['username'])){
				$username = (is_array($data['username']) ? implode(', ', $data['username']) : $data['username']);
			}

		//	$username = $data['username'];
			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', 'Optional'));
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, (strlen($username) != 0 ? $username : NULL)));

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}

		/*public function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$wrapper->appendChild(new XMLElement('h4', $this->get('label') . ' <i>'.$this->Name().'</i>'));
			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input('fields[filter]'.($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '').'['.$this->get('id').']'.($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : NULL)));
			$wrapper->appendChild($label);

		}*/

		public function checkPostFieldData($data, &$message, $entry_id=NULL){
			$message = NULL;
			$status = self::__OK__;

			if($this->get('required') == 'yes' && strlen($data) == 0){
				$message = "This is a required field.";
				return self::__MISSING_FIELDS__;
			}

			if($this->get('allow_multiple') == 'yes'){
				$data = preg_split('/\s*,\s*/', $data, -1, PREG_SPLIT_NO_EMPTY);
			}
			else{
				$data = array($data);
			}

			$invalid = NULL;

			foreach($data as $d){

				if(!is_numeric($d) && !$this->fetchMemberFromUsername($d)){
					$invalid .= ", {$d}";
					//$message = "Invalid Member username supplied";
					//return self::__INVALID_FIELDS__;
				}

				if(is_numeric($d) && !$this->fetchMemberFromID((int)$d)){
					$invalid .= ", {$d}";
					//$message = "Invalid Member ID supplied";
					//return self::__INVALID_FIELDS__;
				}
			}

			if(!is_null($invalid)){
				if(count($data) == 1){
					$message = "The Member Username or ID supplied was invalid.";
				}
				else{
					$message = "The following Member Usernames or ID values are invalid: " . trim($invalid, ', ');
				}

				return self::__INVALID_FIELDS__;
			}

			return $status;
		}

		function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!isset($data['username']) || !isset($data['member_id'])) return;


			if ($this->get('allow_multiple') != 'yes') {

				$hash = $this->Database->fetchVar('hash', 0,
					"SELECT MD5(`value`) AS `hash` FROM `tbl_entries_data_". extension_Members::getConfigVar('email_address_field_id') ."` WHERE `entry_id` = ".(int)$data['member_id']." LIMIT 1"
				);

				$wrapper->appendChild(new XMLElement($this->get('element_name'), $data['username'], array('id' => $data['member_id'], 'email-hash' => $hash)));

				return;
			}


			if(!is_array($data['member_id']) and !is_array($data['username'])){
				$data = array('member_id' => array($data['member_id']), 'username' => array($data['username']));
			}

			// Multiple!!

			$list = new XMLElement($this->get('element_name'));

			foreach ($data['member_id'] as $index => $member_id) {

				$hash = $this->Database->fetchVar('hash', 0,
					"SELECT MD5(`value`) AS `hash` FROM `tbl_entries_data_". extension_Members::getConfigVar('email_address_field_id') ."` WHERE `entry_id` = ".(int)$member_id." LIMIT 1"
				);

				$list->appendChild(
					new XMLElement('item', General::sanitize($data['username'][$index]), array('id' => $member_id, 'email-hash' => $hash))
				);
			}

			$wrapper->appendChild($list);

		}

		public function fetchMemberFromID($member_id){
			return self::$_driver->Member->initialiseMemberObject($member_id);
		}

		public function fetchMemberFromUsername($username){
			$member_id = Symphony::Database()->fetchVar('entry_id', 0, "SELECT `entry_id` FROM `tbl_entries_data_". SymphonyMember::usernameAndPasswordField() ."` WHERE `username` = '".$username."' LIMIT 1");

			return ($member_id ? $this->fetchMemberFromID($member_id) : NULL);
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::__OK__;
			$result = array();

			if($this->get('allow_multiple') == 'yes'){
				$data = preg_split('/\s*,\s*/', $data, -1, PREG_SPLIT_NO_EMPTY);
			}
			else{
				$data = array($data);
			}

			sort($data);

			foreach($data as $d){
				if(is_numeric($d) && $Member = $this->fetchMemberFromID($d)){
					$username = $Member->getData(SymphonyMember::usernameAndPasswordField());
					$username = $username['username'];
					$member_id = $d;
				}

				elseif(!is_numeric($d) && $Member = $this->fetchMemberFromUsername($d)){
					$member_id = $Member->get('id');
					$username = $d;
				}

				if(strlen($username) == 0 && !is_numeric($data)) $username = $d;
				elseif(strlen($member_id) == 0 && is_numeric($data)) $member_id = $d;

				$result[$member_id] = $username;

			}

			return (count($result) == 1
				? array('member_id' => end(array_keys($result)), 'username' => end(array_values($result)))
				: array('member_id' => array_keys($result), 'username' => array_values($result)));

		}

		function commit(){

			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array();

			$fields['field_id'] = $id;
			$fields['allow_multiple'] = ($this->get('allow_multiple') ? $this->get('allow_multiple') : 'no');

			$this->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");

			if(!$this->Database->insert($fields, 'tbl_fields_' . $this->handle())) return false;

			return true;

		}

		public function createTable(){

			return $this->Database->query(

				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `member_id` int(11) default NULL,
				  `username` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `member_id` (`member_id`),
				  KEY `username` (`username`)
				) TYPE=MyISAM;"

			);
		}

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			if(self::isFilterRegex($data[0])):

				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.username REGEXP '$pattern' ";


			elseif($andOperation):

				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND (`t$field_id$key`.username = '$bit' OR `t$field_id$key`.member_id = '$bit') ";
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (`t$field_id`.username IN ('".@implode("', '", $data)."') OR `t$field_id`.member_id IN ('".@implode("', '", $data)."')) ";

			endif;

			return true;

		}

	}

?>