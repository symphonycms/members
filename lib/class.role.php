<?php

	Class Role {

		private $_id;
		private $_name;

		private $_forbidden_pages;
		private $_event_permissions;

		public function __construct($id, $name, array $event_permissions = array(), array $forbidden_pages = array()){
			$this->_id = $id;
			$this->_name = $name;
			$this->_forbidden_pages = $forbidden_pages;
			$this->_event_permissions = $event_permissions;
		}

		public function getRoleIDFromName($role_name = null){
			if(is_null($role_name)) return null;

			return Symphony::Database()->fetchVar('id', 0,
				"SELECT `id` FROM `tbl_members_roles` WHERE `name` = '{$role_name}' LIMIT 1"
			);
		}

		public static function loadFromID($id = null){
			if(is_null($id)) return;

			$record = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_roles` WHERE `id` = {$id} LIMIT 1");

			$forbidden_pages = $event_permissions = array();

			$forbidden_pages = Symphony::Database()->fetchCol('page_id',
				"SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = '{$id}' "
			);

			$tmp = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = '{$id}'");
			if(!empty($tmp)) foreach($tmp as $e){
				$event_permissions[$e['event']][$e['action']] = $e['level'];
			}

			return new Role($id, $record['name'], $event_permissions, $forbidden_pages);
		}

		public function __call($name, $var){
			return $this->{"_$name"};
		}

		public function forbiddenPages(){
			return $this->_forbidden_pages;
		}

		public function eventPermissions(){
			return $this->_event_permissions;
		}

		public function canAccessPage($page_id){
			return !in_array($page_id, $this->_forbidden_pages);
		}

		public function canPerformEventAction($event_handle, $action, $required_level){
			if(in_array($event_handle, $this->_event_permissions)) {
				return ($this->_event_permissions[$event_handle][$action] >= $required_level);
			}

			return true;
		}
	}
