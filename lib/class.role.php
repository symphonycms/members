<?php

	Class RoleManager {

		public static $_pool = array();

		public function add(Array $data) {
			Symphony::Database()->insert($data['roles'], 'tbl_members_roles');
			$role_id = Symphony::Database()->getInsertID();

			$page_access = $data['roles_forbidden_pages']['page_access'];
			if(is_array($page_access) && !empty($page_access)) {
				foreach($page_access as $page_id){
					Symphony::Database()->query("INSERT INTO `tbl_members_roles_forbidden_pages` VALUES (NULL, $role_id, $page_id)");
				}
			}

			$permissions = $data['roles_event_permissions']['permissions'];
			if(is_array($permissions) && !empty($permissions)){

				$sql = "INSERT INTO `tbl_members_roles_event_permissions` VALUES ";

				foreach($permissions as $event_handle => $p){
					foreach($p as $action => $level)
						$sql .= "(NULL,  {$role_id}, '{$event_handle}', '{$action}', '{$level}'),";
				}

				Symphony::Database()->query(trim($sql, ','));
			}

			return $role_id;
		}

		public function edit($role_id, Array $data) {
			Symphony::Database()->update($data['roles'], 'tbl_members_roles', "`role_id` = " . $role_id);

			if(Symphony::Database()->delete("`tbl_members_roles_forbidden_pages`", "`role_id` = " . $role_id)) {
				$page_access = $data['roles_forbidden_pages']['page_access'];
				if(is_array($page_access) && !empty($page_access)) {
					foreach($page_access as $page_id){
						Symphony::Database()->query("INSERT INTO `tbl_members_roles_forbidden_pages` VALUES (NULL, $role_id, $page_id)");
					}
				}
			}

			if(Symphony::Database()->delete("`tbl_members_roles_event_permissions`", "`role_id` = " . $role_id)) {
				$permissions = $data['roles_event_permissions']['permissions'];
				if(is_array($permissions) && !empty($permissions)){

					$sql = "INSERT INTO `tbl_members_roles_event_permissions` VALUES ";

					foreach($permissions as $event_handle => $p){
						foreach($p as $action => $level)
							$sql .= "(NULL,  {$role_id}, '{$event_handle}', '{$action}', '{$level}'),";
					}

					$p = Symphony::Database()->query(trim($sql, ','));
				}
			}

			return true;
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
			Symphony::Database()->delete("`tbl_members_roles_forbidden_pages`", " WHERE `role_id` = " . $role_id);
			Symphony::Database()->delete("`tbl_members_roles_event_permissions`", " WHERE `role_id` = " . $role_id);
			Symphony::Database()->delete("`tbl_members_roles`", " WHERE `id` = " . $role_id);

			if($purge_members) {
				$members = Symphony::Database()->fetchCol('entry_id', sprintf(
					"SELECT `entry_id` FROM `tbl_entries_data_%d` WHERE `role_id` = %d",
					extension_Members::getConfigVar('role'), $role_id
				));

				/**
				 * Prior to deletion of entries. Array of Entry ID's is provided.
				 * The array can be manipulated
				 *
				 * @delegate Delete
				 * @param string $context
				 * '/publish/'
				 * @param array $checked
				 *  An array of Entry ID's passed by reference
				 */
				Symphony::ExtensionManager()->notifyMembers('Delete', '/publish/', array('entry_id' => &$checked));

				$entryManager = new EntryManager(Symphony::Engine());
				$entryManager->delete($members);
			}

			return true;
		}

		public static function fetch($role_id = null, $include_permissions = false) {
			$returnSingle = true;
			$result = array();

			if(is_null($role_id)) $returnSingle = false;

			if($returnSingle && !in_array($role_id, array_keys(RoleManager::$_pool))) {
				if(!$roles = Symphony::Database()->fetch(sprintf("
						SELECT * FROM `tbl_members_roles` WHERE `id` = %d LIMIT 1",
						$role_id
					))
				) return array();
			}
			else {
				$roles = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles`");
			}

			foreach($roles as $role) {
				RoleManager::$_pool[$role_id] = new Role($role);

				if($include_permissions) RoleManager::$_pool[$role_id]->getPermissions();

				if($returnSingle) return RoleManager::$_pool[$role_id];

				$result[] = RoleManager::$_pool[$role_id];
			}

			return $result;
		}

		public static function fetchRoleIDByName($name){
			return Symphony::Database()->fetchVar('id', 0, sprintf("
				SELECT `id` FROM `tbl_members_roles` WHERE `name` = '%s' LIMIT 1",
				Symphony::Database()->cleanValue($name)
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
