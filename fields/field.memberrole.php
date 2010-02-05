<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	Class fieldMemberRole extends Field{
		
		private $_driver;
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Member: Role';
			
			// Set default
			$this->set('show_column', 'no');	
			
			if(!($this->_driver instanceof Extension)){
				if(class_exists('Frontend')){
					$this->_driver = Frontend::instance()->ExtensionManager->create('members');
				}

				else{
					$this->_driver = Administration::instance()->ExtensionManager->create('members');
				}
			}
		}

		function canToggle(){
			return true;
		}
		
		function allowDatasourceOutputGrouping(){
			return true;
		}

		function canFilter(){
			return true;
		}
		
		function canPrePopulate(){
			return true;
		}	

		function isSortable(){
			return true;
		}
		
		public function mustBeUnique(){
			return true;
		}
				
		function appendFormattedElement(&$wrapper, $data, $encode=false){

			if(!is_array($data) || empty($data)) return;

			$role_id = $data['role_id'];	
			$role = $this->_driver->fetchRole($role_id);
			
			$wrapper->appendChild(new XMLElement($this->get('element_name'), General::sanitize($role->name()), array('id' => $role->id())));

		}		

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC', $useIDFieldForSorting=false){
			
			$sort_field = (!$useIDFieldForSorting ? 'ed' : 't' . $this->get('id'));
			
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `$sort_field` ON (`e`.`id` = `$sort_field`.`entry_id`) ";
			$sort .= (strtolower($order) == 'random' ? 'RAND()' : "`$sort_field`.`role_id` $order");
		}
		
		function fetchAssociatedEntrySearchValue($data){
			if(!is_array($data)) return $data;
			
			return $data['value'];
		}

		function getToggleStates(){
			
			$roles = $this->_driver->fetchRoles();
		
			$states = array();
			foreach($roles as $r){
				if($r->id() == extension_Members::GUEST_ROLE_ID) continue;
				$states[$r->id()] = $r->name();
			}
			
			return $states;
		}
		
		function toggleFieldData($data, $newState){
			$data['role_id'] = $newState;
			return $data;
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			$states = $this->getToggleStates();
			
			$options = array();

			foreach($states as $role_id => $role_name){
				$options[] = array($role_id, $role_id == $data['role_id'], $role_name);
			}
			
			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;
			
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options));
			
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);		
		}

		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			
			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);
			
			$data = preg_split('/,\s*/i', $data);
			$data = array_map('trim', $data);
			
			$existing_options = $this->getToggleStates();

			if(is_array($existing_options) && !empty($existing_options)){
				$optionlist = new XMLElement('ul');
				$optionlist->setAttribute('class', 'tags');
				
				foreach($existing_options as $option) $optionlist->appendChild(new XMLElement('li', $option));
						
				$wrapper->appendChild($optionlist);
			}
					
		}

		/*function findAndAddDynamicOptions(&$values){

			if(!is_array($values)) $values = array();

			$sql = "SELECT DISTINCT `value` FROM `tbl_entries_data_".$this->get('dynamic_options')."`
					ORDER BY `value` DESC";
					
			if($results = $this->Database->fetchCol('value', $sql)) $values = array_merge($values, $results);

		}*/

		function prepareTableValue($data, XMLElement $link=NULL){
			$role_id = $data['role_id'];
			
			$role = $this->_driver->fetchRole($role_id);
			
			return parent::prepareTableValue(array('value' => (is_object($role) ? General::sanitize($role->name()) : NULL)), $link);
		}

		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;
			return array('role_id' => $data);
		}

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			
			$field_id = $this->get('id');
			
			if($andOperation):
			
				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$joins .= " LEFT JOIN `tbl_members_roles` AS `tg$field_id$key` ON (`t$field_id$key`.`role_id` = `tg$field_id$key`.id) ";					
					$where .= " AND (`t$field_id$key`.role_id = '$bit' OR `tg$field_id$key`.name = '$bit') ";
				}
							
			else:
			
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$joins .= " LEFT JOIN `tbl_members_roles` AS `tg$field_id` ON (`t$field_id`.`role_id` = `tg$field_id`.id) ";				
				$where .= " AND (`t$field_id`.role_id IN ('".@implode("', '", $data)."') OR `tg$field_id`.name IN ('".@implode("', '", $data)."')) ";
						
			endif;
			
			return true;
			
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
				
		function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		function groupRecords($records){
			
			if(!is_array($records) || empty($records)) return;
			
			$groups = array($this->get('element_name') => array());
			
			foreach($records as $r){
				$data = $r->getData($this->get('id'));
				
				$role_id = $data['role_id'];
				if(!$role = $this->_driver->fetchRole($role_id)) continue;
				
				if(!isset($groups[$this->get('element_name')][$role_id])){
					$groups[$this->get('element_name')][$role_id] = array('attr' => array('name' => General::sanitize($role->name()), 'id' => $role_id),
																		 'records' => array(), 'groups' => array());
				}	
																					
				$groups[$this->get('element_name')][$role_id]['records'][] = $r;
								
			}

			return $groups;
		}
		
		function createTable(){
			
			return $this->_engine->Database->query(
			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
 				  `role_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`, `role_id`)
				)"
			
			);
		}
		
		public function getExampleFormMarkup(){
			$states = $this->getToggleStates();
			
			$options = array();

			foreach($states as $role_id => $name){
				$options[] = array($role_id, NULL, $name);
			}
			
			$fieldname = 'fields['.$this->get('element_name').']';
			
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options));
			
			return $label;
		}			

	}

