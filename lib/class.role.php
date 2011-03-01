<?php

	Class RoleManager {

		public static $_pool = array();

		public function add() {

		}

		public function edit() {

		}

		/**
		 * Will delete the Role given a `$role_id`. Should `$purge_members`
		 * be passed, this function will remove all Members associated with
		 * this role as well
		 *
		 * @param integer $role_id
		 * @param boolean $purge_members
		 * @return boolean
		 */
		public function delete($role_id, $purge_members = false) {
			return true;
		}

		public static function fetch($role_id = null, $include_permissions = false) {
			if(!in_array($role_id, array_keys(RoleManager::$_pool))) {
				if(!$row = Symphony::Database()->fetchRow(0, sprintf("
						SELECT * FROM `tbl_members_roles` WHERE `id` = %d LIMIT 1",
						$role_id
					))
				) return null;
			}

			RoleManager::$_pool[$role_id] = new Role($settings);

			if($include_permissions) RoleManager::$_pool[$role_id]->getPermissions();

			return RoleManager::$_pool[$role_id];
		}

		public static function fetchRoleIDByHandle($handle){
			return Symphony::Database()->fetchVar('id', 0, sprintf("
				SELECT `id` FROM `tbl_members_roles` WHERE `handle` = '%s' LIMIT 1",
				Symphony::Database()->cleanValue($handle)
			));
		}
	}

	/**
	 * The Role class defines an Access Level for Frontend members
	 * which includes what pages are accessible and what events a
	 * member of this Role can use.
	 *
	 * Roles are optional and are only used if the Active Member's
	 * section has the Member: Role field.
	 */
	Class Role {

		private $settings = array();

		public function __construct(Array $settings){
			$this->setArray($settings);

			$this->set('forbidden_pages', array());
			$this->set('event_permissions', array());
		}

		public function set($name, $value) {
			$this->settings[$name] = $value;
		}

		public function setArray(Array $array) {
			foreach($array as $name => $value) {
				$this->set($name, $value);
			}
		}

		public function get($name = null) {
			if(is_null($name)) return $this->settings;

			if(!array_key_exists($name, $this->settings)) return null;

			return $this->settings[$name];
		}

		public function getPermissions() {
			// Get all pages that this Role can't access
			$this->set('forbidden_pages', Symphony::Database()->fetchCol('page_id', sprintf(
				"SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = %d",
				$this->get('id')
			)));

			// Get the events permssions for this Role
			$event_permissions = array();
			$tmp = Symphony::Database()->fetch(sprintf(
				"SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = %d",
				$this->get('id')
			));

			if(is_array($tmp) && !empty($tmp)) foreach($tmp as $e) {
				$event_permissions[$e['event']][$e['action']] = $e['level'];
			}

			$this->set('event_permissions', $event_permissions);
		}

		public function canAccessPage($page_id){
			return !in_array($page_id, $this->get('forbidden_pages'));
		}

		public function canProcessEvent($event_handle, $action, $required_level){
			$event_permissions = $this->get('event_permissions');

			if(in_array($event_handle, $event_permissions)) {
				return ($event_permissions[$event_handle][$action] >= $required_level);
			}

			// If the event wasn't in the array, then assume it's ok.
			return true;
		}
	}
