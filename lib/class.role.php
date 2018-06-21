<?php

	Class RoleManager {

		public static $_pool = array();

		/**
		 * Given an associative array of data with the keys being the
		 * relevant table names, and the values being an associative array
		 * of data to insert, add a new Role to the Database. Roles are spread
		 * across three tables, `tbl_members_roles`, `tbl_members_roles_forbidden_pages`
		 * and `tbl_members_roles_event_permissions`. This function will return
		 * the ID of the Role after it has been added to the database.
		 *
		 * @param array $data
		 * @return integer
		 *  The newly created Role's ID
		 */
		public static function add(array $data) {
			// Symphony::Database()->insert($data['roles'], 'tbl_members_roles');
			Symphony::Database()
				->insert('tbl_members_roles')
				->values($data['roles'])
				->execute()
				->success();
			$role_id = Symphony::Database()->getInsertID();

			$page_access = $data['roles_forbidden_pages']['page_access'];
			if(is_array($page_access) && !empty($page_access)) {
				foreach($page_access as $page_id){
					// Symphony::Database()->insert(array(
					// 		'page_id' => $page_id,
					// 		'role_id' => $role_id
					// 	),
					// 	'tbl_members_roles_forbidden_pages'
					// );
					Symphony::Database()
						->insert('tbl_members_roles_forbidden_pages')
						->values([
							'page_id' => $page_id,
							'role_id' => $role_id,
						])
						->execute()
						->success();
				}
			}

			$permissions = $data['roles_event_permissions']['permissions'];
			if(is_array($permissions) && !empty($permissions)){
				// $sql = "INSERT INTO `tbl_members_roles_event_permissions` VALUES ";

				Symphony::Database()
					->transaction(function (Database $db) use ($permissions) {

						foreach($permissions as $event_handle => $p){
							foreach($p as $action => $level) {
								// $sql .= sprintf("(null,%d,'%s','%s',%d),", $role_id, $event_handle, $action, $level);
								$db
									->insert('tbl_members_roles_event_permissions')
									->values([
										'role_id' => $role_id,
										'event' => $event_handle,
										'action' => $action,
										'level' => $level,
									]);
							}
						}

				})
				->execute()
				->success();

				// Symphony::Database()->query(trim($sql, ','));
			}

			return $role_id;
		}

		/**
		 * Given a `$role_id` and an associative array of data in the same fashion
		 * as `RoleManager::add()`, this will update a Role record returning boolean
		 *
		 * @param integer $role_id
		 * @param array $data
		 * @return boolean
		 */
		public static function edit($role_id, array $data) {
			if(is_null($role_id)) return false;

			// Symphony::Database()->update($data['roles'], 'tbl_members_roles', "`id` = " . $role_id);
			Symphony::Database()
				->update('tbl_members_roles')
				->set($data['roles'])
				->where(['id' => $role_id])
				->execute()
				->success();

			// Symphony::Database()->delete("tbl_members_roles_forbidden_pages", "`role_id` = " . $role_id);
			Symphony::Database()
				->delete('tbl_members_roles_forbidden_pages')
				->where(['role_id' => $role_id])
				->execute()
				->success();

			$page_access = $data['roles_forbidden_pages']['page_access'];
			if(is_array($page_access) && !empty($page_access)) {
				foreach($page_access as $page_id){
					// Symphony::Database()->insert(array(
					// 		'page_id' => $page_id,
					// 		'role_id' => $role_id
					// 	),
					// 	'tbl_members_roles_forbidden_pages'
					// );
					Symphony::Database()
						->insert('tbl_members_roles_forbidden_pages')
						->values([
							'page_id' => $page_id,
							'role_id' => $role_id,
						])
						->execute()
						->success();
				}
			}

			// Symphony::Database()->delete("tbl_members_roles_event_permissions", "`role_id` = " . $role_id);
			Symphony::Database()
				->delete('tbl_members_roles_event_permissions')
				->where(['role_id' => $role_id])
				->execute()
				->success();

			$permissions = $data['roles_event_permissions']['permissions'];
			if(is_array($permissions) && !empty($permissions)){
				// $sql = "INSERT INTO `tbl_members_roles_event_permissions` VALUES ";
				Symphony::Database()
					->transaction(function (Database $db) use ($role_id, $permissions) {

						foreach($permissions as $event_handle => $p){
							if(!array_key_exists('create', $p)) {
								// $sql .= sprintf("(null,%d,'%s','%s',%d),", $role_id, $event_handle, 'create', EventPermissions::NO_PERMISSIONS);
								$db
									->insert('tbl_members_roles_event_permissions')
									->values([
										'role_id' => $role_id,
										'event' => $event_handle,
										'action' => 'create',
										'level' => EventPermissions::NO_PERMISSIONS,
									])
									->execute()
									->success();
							}

							foreach($p as $action => $level) {
								// $sql .= sprintf("(null,%d,'%s','%s',%d),", $role_id, $event_handle, $action, $level);
								$db
									->insert('tbl_members_roles_event_permissions')
									->values([
										'role_id' => $role_id,
										'event' => $event_handle,
										'action' => $action,
										'level' => $level,
									])
									->execute()
									->success();
							}
						}
				})
				->execute()
				->success();

				// $p = Symphony::Database()->query(trim($sql, ','));
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
		public static function delete($role_id, $purge_members = false) {
			// Symphony::Database()->delete("tbl_members_roles_forbidden_pages", " `role_id` = " . $role_id);
			Symphony::Database()
				->delete('tbl_members_roles_forbidden_pages')
				->where(['role_id' => $role_id])
				->execute()
				->success();
			// Symphony::Database()->delete("tbl_members_roles_event_permissions", " `role_id` = " . $role_id);
			Symphony::Database()
				->delete('tbl_members_roles_event_permissions')
				->where(['role_id' => $role_id])
				->execute()
				->success();
			// Symphony::Database()->delete("tbl_members_roles", " `id` = " . $role_id);
			Symphony::Database()
				->delete('tbl_members_roles')
				->where(['id' => $role_id])
				->execute()
				->success();

			// $role_fields = FieldManager::fetch(null, null, 'ASC', 'sortorder', extension_Members::getFieldType('role'));
			$role_fields = (new FieldManager)
				->select()
				->sort('sortorder', 'asc')
				->type(extension_Members::getFieldType('role'))
				->execute()
				->rows();

			if($purge_members) {
				$members = array();
				foreach($role_fields as $role_field) {
					// $members_of_role = Symphony::Database()->fetchCol('entry_id', sprintf(
					// 	"SELECT `entry_id` FROM `tbl_entries_data_%d` WHERE `role_id` = %d",
					// 	$role_field->get('id'), $role_id
					// ));
					$members_of_role = Symphony::Database()
						->select(['entry_id'])
						->from('tbl_entries_data_' . $role_field->get('id'))
						->where(['role_id' => $role_id])
						->execute()
						->column('entry_id');

					$members = array_merge($members, $members_of_role);
				}

				/**
				 * Prior to deletion of entries. An array of Entry ID's is provided which
				 * can be manipulated. This delegate was renamed from `Delete` to `EntryPreDelete`
				 * in Symphony 2.3.
				 *
				 * @delegate EntryPreDelete
				 * @param string $context
				 * '/publish/'
				 * @param array $entry_id
				 *  An array of Entry ID's passed by reference
				 */
				Symphony::ExtensionManager()->notifyMembers('EntryPreDelete', '/publish/', array('entry_id' => &$members));

				EntryManager::delete($members);

				/**
				 * After the deletion of entries, this delegate provides an array of Entry ID's
				 * that were deleted.
				 *
				 * @since Symphony 2.3
				 * @delegate EntryPostDelete
				 * @param string $context
				 * '/publish/'
				 * @param array $entry_id
				 *  An array of Entry ID's that were deleted.
				 */
				Symphony::ExtensionManager()->notifyMembers('EntryPostDelete', '/publish/', array('entry_id' => $members));
			}

			return true;
		}

		/**
		 * This function will return Roles from the database. If the `$role_id` is
		 * given the function will return a Role object (should it be found) otherwise
		 * an array of Role objects will be returned.
		 *
		 * @param integer $role_id
		 * @return Role|array
		 */
		public static function fetch($role_id = null) {
			$result = array();
			$return_single = is_null($role_id) ? false : true;

			if($return_single) {
				// Check static cache for object
				if(in_array($role_id, array_keys(RoleManager::$_pool))) {
					return RoleManager::$_pool[$role_id];
				}

				// No cache object found
				// if(!$roles = Symphony::Database()->fetch(sprintf("
				// 		SELECT * FROM `tbl_members_roles` WHERE `id` = %d ORDER BY `id` ASC LIMIT 1",
				// 		$role_id
				// 	))
				// ) return array();
				if (!$roles = Symphony::Database()
					->select(['*'])
					->from('tbl_members_roles')
					->where(['id' => $role_id])
					->orderBy('id', 'asc')
					->limit(1)
					->execute()
					->rows()
				) {
					return array();
				}
			}
			else {
				// $roles = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles` ORDER BY `id` ASC");
				$roles = Symphony::Database()
					->select(['*'])
					->from('tbl_members_roles')
					->orderBy('id', 'asc')
					->execute()
					->rows();
			}

			foreach($roles as $role) {
				if(!in_array($role['id'], array_keys(RoleManager::$_pool))) {
					RoleManager::$_pool[$role['id']] = new Role($role);
					RoleManager::$_pool[$role['id']]->setPermissions();

					$result[] = RoleManager::$_pool[$role['id']];
				}
			}

			return $return_single ? current($result) : $result;
		}

		/**
		 * This function will find a Role by it's handle. Should `$asObject` be
		 * passed as true, this function will return a Role object, otherwise just
		 * the `$role_id`.
		 *
		 * @param string $handle
		 * @param boolean $asObject
		 * @return integer|Role|null
		 */
		public static function fetchRoleIDByHandle($handle, $asObject = false){
			// $role_id = Symphony::Database()->fetchVar('id', 0, sprintf("
			// 	SELECT `id` FROM `tbl_members_roles` WHERE `handle` = '%s' LIMIT 1",
			// 	Symphony::Database()->cleanValue($handle)
			// ));
			$role_id = Symphony::Database()
				->select(['id'])
				->from('tbl_members_roles')
				->where(['handle' => $handle])
				->limit(1)
				->execute()
				->variable('id');

			if(!$role_id) return null;

			if(!$asObject) return $role_id;

			return RoleManager::fetch($role_id);
		}
	}

	/**
	 * The Role class defines an Access Level for Frontend members which includes
	 * what pages are accessible and what events a member of this Role can use.
	 */
	Class Role {
		const PUBLIC_ROLE = 1;

		private $settings = array();

		public function __construct(array $settings){
			$this->setArray($settings);

			$this->set('forbidden_pages', array());
			$this->set('event_permissions', array());
		}

		/**
		 * Given a `$name` and a `$value`, this will set it into the Role's
		 * `$this->settings` array. By default, `$name` maps the `tbl_member_roles`
		 * column names.
		 *
		 * @param string $name
		 * @param mixed $value
		 */
		public function set($name, $value) {
			$this->settings[$name] = $value;
		}

		/**
		 * Convenience function to set an associative array without using multiple
		 * `set` calls. This function expects an associative array
		 *
		 * @param array $array
		 */
		public function setArray(array $array) {
			foreach($array as $name => $value) {
				$this->set($name, $value);
			}
		}

		/**
		 * Sets the permissions for the current Role by loading them from
		 * `tbl_members_roles_forbidden_pages` and `tbl_members_roles_event_permissions`.
		 * The permissions are set under `forbidden_pages` and `event_permissions` keys
		 * that can be accessed via `Role->get('event_permissions')`.
		 */
		public function setPermissions() {
			// Get all pages that this Role can't access
			// $this->set('forbidden_pages', Symphony::Database()->fetchCol('page_id', sprintf(
			// 	"SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = %d",
			// 	$this->get('id')
			// )));
			$this->set('forbidden_pages', Symphony::Database()
				->select(['page_id'])
				->from('tbl_members_roles_forbidden_pages')
				->where(['role_id' => $this->get('id')])
				->execute()
				->column('page_id')
			);

			// Get the events permssions for this Role
			$event_permissions = array();
			// $tmp = Symphony::Database()->fetch(sprintf(
			// 	"SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = %d",
			// 	$this->get('id')
			// ));
			$tmp = Symphony::Database()
				->select(['*'])
				->from('tbl_members_roles_event_permissions')
				->where(['role_id' => $this->get('id')])
				->execute()
				->rows();

			if(is_array($tmp) && !empty($tmp)) foreach($tmp as $e) {
				$event_permissions[$e['event']][$e['action']] = $e['level'];
			}

			$this->set('event_permissions', $event_permissions);
		}

		/**
		 * Given a `$name`, this function returns the setting for this Role. If
		 * no setting is found, this function will return null.
		 * If `$name` is not provided, the entire `$this->settings` array will
		 * be returned.
		 *
		 * @param string $name
		 * @return mixed
		 */
		public function get($name = null) {
			if(is_null($name)) return $this->settings;

			if(!array_key_exists($name, $this->settings)) return null;

			return $this->settings[$name];
		}

		/**
		 * Given a `$page_id`, this functions return true if this role is
		 * allowed to view the page with that ID.
		 *
		 * @param integer $page_id
		 * @return boolean
		 */
		public function canAccessPage($page_id){
			return !in_array($page_id, $this->get('forbidden_pages'));
		}

		/**
		 * Given an event handle, the desired action, either create or edit, and
		 * the required permission level, this function will return boolean if
		 * the user can process the event. The `$required_level` is one of the
		 * `EventPermissions` constants, `NO_PERMISSIONS`, `OWN_ENTRIES`, `ALL_ENTRIES`
		 * or `CREATE`.
		 *
		 * @since Symphony 2.4
		 *  This function defaults to false (`NO_PERMISSIONS`) if there are no matches.
		 *  This means the Role must be updated with all the event permissions before the
		 *  event can be used.
		 * @param string $event_handle
		 * @param string $action
		 * @param integer $required_level
		 * @return boolean
		 */
		public function canProcessEvent($event_handle, $action, $required_level){
			$event_permissions = $this->get('event_permissions');

			if(array_key_exists($event_handle, $event_permissions)) {
				return ($event_permissions[$event_handle][$action] >= $required_level);
			}

			// If the event wasn't in the array, then assume it's not ok.
			// Note this a change since Members 1.4. RE: #242.
			return false;
		}
	}

	Class EventPermissions {
		const NO_PERMISSIONS = 0;
		const OWN_ENTRIES = 1;
		const ALL_ENTRIES = 2;
		const CREATE = 1;

		public static $permissionMap = array(
			self::NO_PERMISSIONS => 'No Permission',
			self::OWN_ENTRIES => 'Own Entries',
			self::ALL_ENTRIES => 'All Entries'
		);
	}
