<?php
	
	Class fieldMember extends Field{
		
		static private $_driver;
				
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Member: Username &amp; Password';
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

		public function mustBeUnique(){
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

		function prepareTableValue($data, XMLElement $link=NULL){
			return parent::prepareTableValue(array('value' => $data['username']), $link);
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			
			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			$username = $data['username'];		
			$label = Widget::Label('Username');
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', 'Optional'));
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][username]'.$fieldnamePostfix, (strlen($username) != 0 ? $username : NULL)));
			$div->appendChild($label);
			
			$password = $data['password'];		
			$label = Widget::Label('Password');
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', 'Optional'));
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][password]'.$fieldnamePostfix, (strlen($password) != 0 ? $password : NULL)));
			$div->appendChild($label);
			
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($div, $flagWithError));
			else $wrapper->appendChild($div);
			
		}
		
		public function checkPostFieldData($data, &$message, $entry_id=NULL){
			$message = NULL;

			if($this->get('required') == 'yes' && (strlen($data['username']) == 0 || strlen($data['password']) == 0)){
				$message = "Username and Password are required fields.";
				return self::__MISSING_FIELDS__;
			}

			if(!General::validateString($data['username'], '/^[\pL\s-_0-9]{1,}+$/iu')){
				$message = 'Username contains invalid characters.';
				return self::__INVALID_FIELDS__;				
			}
			
			$existing_member = $this->fetchMemberFromUsername($data['username']);
			
			if($this->get('required') == 'yes' && (is_object($existing_member) && $existing_member->get('id') != $entry_id)){
				$message = "That username is already taken";
				return self::__INVALID_FIELDS__;				
			}

			return self::__OK__;		
		}

		function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!isset($data['username']) || !isset($data['password'])) return;
			$wrapper->appendChild(
				new XMLElement(
					$this->get('element_name'), 
					NULL, 
					array('username' => $data['username'], 'password' => $data['password'])
			));
		}

		public function fetchMemberFromID($member_id){
			return self::$_driver->initialiseMemberObject($member_id);					
		}

		public function fetchMemberFromUsername($username){
			$member_id = Symphony::Database()->fetchVar('entry_id', 0, 
				"SELECT `entry_id` FROM `tbl_entries_data_".$this->get('id')."` WHERE `username` = '{$username}' LIMIT 1"
			);
			return ($member_id ? $this->fetchMemberFromID($member_id) : NULL);
		}

		private static function __hashit($data){
			
			if(strlen($data) == 0) return;
			elseif(strlen($data) != 32 || !preg_match('@^[a-f0-9]{32}$@i', $data)) return md5($data);
			
			return $data;
		}


		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			
			$status = self::__OK__;

			return array(
				'username' => $data['username'],
				'password' => self::__hashit($data['password']),
			);
		}

		function commit(){
			
			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			
			$this->_engine->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");		
			return $this->_engine->Database->insert($fields, 'tbl_fields_' . $this->handle());
					
		}
		
		public function createTable(){
			
			return $this->Database->query(
			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `username` varchar(50) default NULL,
				  `password` varchar(32) default NULL,				
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
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
		
		
		public function getExampleFormMarkup(){
			
			$div = new XMLElement('div', NULL, array('class' => 'group'));
			$label = Widget::Label('Username');
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').'][username]'));
			$div->appendChild($label);
			
			$label = Widget::Label('Password');
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').'][password]', NULL, 'password'));
			$div->appendChild($label);
						
			return $div;
		}		
				
	}

?>