<?php
Class Role{

		private $_id;
		private $_name;

		private $_forbidden_pages;
		private $_event_permissions;

		public function __construct($id, $name, array $event_permissions=array(), array $forbidden_pages=array()){
			$this->_id = $id;
			$this->_name = $name;
			$this->_forbidden_pages = $forbidden_pages;
			$this->_event_permissions = $event_permissions;
		}

		public static function loadFromID($id){

			$record = ASDCLoader::instance()->query("SELECT * FROM `tbl_members_roles` WHERE `id` = {$id} LIMIT 1")->current();

			$forbidden_pages = $event_permissions = array();

			$records = ASDCLoader::instance()->query(
				"SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = '{$id}' "
			);
			if($records->length() > 0){
				$forbidden_pages = DatabaseUtilities::ResultColumn($records, 'page_id');
			}

			$tmp = ASDCLoader::instance()->query("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = '{$id}'");
			if($tmp->length() > 0){
				foreach($tmp as $e){
					$event_permissions[$e->event][$e->action] = $e->level;
				}
			}

			return new self($id, $record->name, $event_permissions, $forbidden_pages);
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
			return !@in_array($page_id, $this->_forbidden_pages);
		}

		public function canPerformEventAction($event_handle, $action, $required_level){
			return ($this->_event_permissions[$event_handle][$action] >= $required_level);
		}

	}