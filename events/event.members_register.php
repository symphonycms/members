<?php

	require_once(TOOLKIT . '/class.event.php');
	
	Class eventmembers_Register extends Event{

		const ROOTELEMENT = 'members-register';

		private $_driver;

		public function __construct(&$parent, $env=NULL){
			parent::__construct($parent, $env);
			$this->_driver = $this->_Parent->ExtensionManager->create('members');
		}
		
		public static function showInRolePermissions(){
			return true;
		}
		
		public static function about(){
			return array(
					 'name' => 'Members: Register',
					 'author' => array(
							'name' => 'Symphony Team',
							'website' => 'http://symphony-cms.com',
							'email' => 'alistair@symphony-cms.com'),
					 'version' => '1.0',
					 'release-date' => '2010-02-05T02:35:13+00:00',
					 'trigger-condition' => 'action[members-register]');	
		}

		public static function getSource(){
			return extension_Members::memberSectionID();
		}

		public static function allowEditorToParse(){
			return false;
		}

		public static function documentation(){
			return '';
		}
		
		public function load(){			
			if(isset($_POST['action']['members-register'])) return $this->__trigger();
		}
		
		protected function __trigger(){
			
			$role_field_handle = ASDCLoader::instance()->query(sprintf(
				"SELECT `element_name` FROM `tbl_fields` WHERE `type` = 'memberrole' AND `parent_section` = %d LIMIT 1",
				extension_Members::memberSectionID()
			))->current()->element_name;
			
			$role_id = Symphony::Configuration()->get('new_member_default_role', 'members');
			if(Symphony::Configuration()->get('require_activation', 'members') == 'yes'){
				$role_id = extension_Members::INACTIVE_ROLE_ID;
			}
			
			$_POST['fields'][$role_field_handle] = $role_id;
			
			include(TOOLKIT . '/events/event.section.php');
			return $result;
		}		

	}

