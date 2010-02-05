<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	Class contentExtensionMembersRoles extends AdministrationPage{
		
		private $_driver;

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle('Symphony &ndash; Member Roles');
			$this->_driver = Administration::instance()->ExtensionManager->create('members');
			
		}
		
		private function __deleteMembers($role_id){
			$sql = "SELECT `entry_id` FROM `tbl_entries_data_".$this->_driver->roleField()."` WHERE `role_id` = $role_id";
			$members = Administration::Database()->fetchCol('entry_id', $sql);
			
			###
			# Delegate: Delete
			# Description: Prior to deletion of entries. Array of Entries is provided.
			#              The array can be manipulated
			Administration::instance()->ExtensionManager->notifyMembers('Delete', '/publish/', array('entry_id' => &$checked));

			$entryManager = new EntryManager($this->_Parent);
			$entryManager->delete($members);
			
		}
		
		public function action() {
			
			if(!isset($_POST['items']) || !is_array($_POST['items']) || !empty($_POST['items'])) return;
			
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) && !empty($checked)) {
				if($_POST['with-selected'] == 'delete-members'):
					foreach($checked as $role_id){
						$this->__deleteMembers($role_id);
					}
				
				elseif($_POST['with-selected'] == 'delete'):
					foreach($checked as $role_id){
						$this->__deleteMembers($role_id);

						ASDCLoader::instance()->query("DELETE FROM `tbl_members_roles` WHERE `id` = {$role_id}");
						ASDCLoader::instance()->query("DELETE FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = {$role_id}");
						ASDCLoader::instance()->query("DELETE FROM `tbl_members_roles_event_permissions` WHERE `role_id` = {$role_id}");
					}
				
				elseif(preg_match('/move::(\d+)/i', $_POST['with-selected'], $match)):	
					$target_role = $match[1];
					
					if(!$replacement = $this->_driver->fetchRole($target_role)){
						die("no such target role");
					}

					foreach($checked as $role_id){
						if($role_id == $target_role) continue;
						ASDCLoader::instance()->query(sprintf(
							"UPDATE `tbl_entries_data_%d` SET `role_id` = %d WHERE `role_id` = %d",
							$this->_driver->roleField(),
							$target_role,
							$role_id
						));
					}
		
				endif;
			}
		}	
	
		public function view(){

			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 9126341);
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/members/assets/scripts.js', 9126342);
			
			$create_button = Widget::Anchor('Create a new role', extension_members::baseURL() . 'roles_new/', 'Create a new role', 'create button');

			$this->setPageType('table');
			$this->appendSubheading('Member Roles ' . $create_button->generate(false));


			$aTableHead = array(

				array('Name', 'col'),
				array('Members', 'col'),		

			);	
		
			$roles = $this->_driver->fetchRoles();
			
			
			
			$aTableBody = array();

			if(!is_array($roles) || empty($roles)){
				$aTableBody = array(
					Widget::TableRow(
						array(Widget::TableData(__('None Found.'), 'inactive', NULL, count($aTableHead)))
					)
				);
			}
			
			elseif(is_null(extension_members::memberSectionID())){
				$aTableBody = array(
					Widget::TableRow(
						array(Widget::TableData(__('No Member section has been specified in <a href="'.URL.'/symphony/extension/members/preferences/">Member Preferences</a>. Please do this first.'), 'inactive', NULL, count($aTableHead)))
					)
				);
			}
			
			else{
				
			    $sectionManager = new SectionManager($this->_Parent);
			    $section = $sectionManager->fetch($this->_driver->memberSectionID());
				
				$bEven = true;
				
				$role_field_name = Symphony::Database()->fetchVar('element_name', 0, 
					"SELECT `element_name` FROM `tbl_fields` WHERE `id` = '".$this->_driver->roleField()."' LIMIT 1"
				);
				
				
				$with_selected_roles = array();
				foreach($roles as $role){
					
					$member_count = Symphony::Database()->fetchVar('count', 0, "SELECT COUNT(*) AS `count` FROM `tbl_entries_data_".$this->_driver->roleField()."` WHERE `role_id` = '".$role->id()."'");
					
					## Setup each cell
					$td1 = Widget::TableData(Widget::Anchor($role->name(), extension_members::baseURL() . 'roles_edit/' . $role->id() . '/', NULL, 'content'));
					
					if(extension_Members::GUEST_ROLE_ID == $role->id()){
						$td2 = Widget::TableData('N/A', 'inactive');
					}
					else{
						$td2 = Widget::TableData(Widget::Anchor(
							"$member_count", 
							URL . '/symphony/publish/' . $section->get('handle') . '/?filter=' . $role_field_name . ':' . $role->id()
						));
					}
					
					if(!in_array($role->id(), array(extension_Members::GUEST_ROLE_ID, extension_Members::INACTIVE_ROLE_ID))){
						$td2->appendChild(Widget::Input("items[".$role->id()."]", null, 'checkbox'));
					}
					
					## Add a row to the body array, assigning each cell to the row
					$aTableBody[] = Widget::TableRow(array($td1, $td2), ($bEven ? 'odd' : NULL));		

					if($role->id() != extension_Members::GUEST_ROLE_ID){
						$with_selected_roles[] = array(
							"move::" . $role->id(),
							false,
							$role->name()
						);
					}
					
					$bEven = !$bEven;
					
				}
			
			}
			
			$table = Widget::Table(
				Widget::TableHead($aTableHead), 
				NULL, 
				Widget::TableBody($aTableBody)
			);
					
			$this->Form->appendChild($table);
			
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, __('With Selected...')),
				2 => array('delete-members', false, __('Delete Members')),
				array('delete', false, __('Delete')),
			);
			
			if(count($with_selected_roles) > 0){
				$options[1] = array('label' => __('Move Members To'), 'options' => $with_selected_roles);
			}
			
			ksort($options);
			
			$tableActions->appendChild(Widget::Select('with-selected', $options, array('id' => 'with-selected')));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			
			$this->Form->appendChild($tableActions);			

		}
	}
	
