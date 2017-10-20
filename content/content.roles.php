<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.eventmanager.php');

	Class contentExtensionMembersRoles extends AdministrationPage {

		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Member Roles'))));

			if(!FieldManager::isFieldUsed(extension_Members::getFieldType('role'))) {
				$this->pageAlert(
					__('There are no Member: Role fields in this Symphony installation. <a href="%s">Add Member: Role field?</a>',
					array(
						SYMPHONY_URL . '/blueprints/sections/'
					)),
					Alert::NOTICE
				);
			}

			$this->appendSubheading(__('Member Roles'), Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL().'new/', __('Create a Role'), 'create button', NULL, array('accesskey' => 'c')
			));

			$roles = RoleManager::fetch();
			// Find all possible member sections
			$config_sections = explode(',',extension_Members::getSetting('section'));

			$aTableHead = array(
				array(__('Name'), 'col')
			);

			$aTableBody = array();

			if(!is_array($roles) || empty($roles)){
				$aTableBody = array(Widget::TableRow(
					array(Widget::TableData(__('None found.'), 'inactive', NULL, count($aTableHead)))
				));
			}

			else if(empty($config_sections)) {
				$aTableBody = array(Widget::TableRow(
					array(Widget::TableData(__('No Member sections exist in Symphony. <a href="%s">Create a Section?</a>',
					array(
						SYMPHONY_URL . '/blueprints/sections/'
					))
					, 'inactive', NULL, count($aTableHead)))
				));
			}

			else {
				$hasRoles = FieldManager::isFieldUsed(extension_Members::getFieldType('role'));
				$roleFields = FieldManager::fetch(null, null, 'ASC', 'sortorder', extension_Members::getFieldType('role'));

				$with_selected_roles = array();
				$i = 0;

				foreach($roles as $role){
					// Setup each cell
					$td1 = Widget::TableData(Widget::Anchor(
						$role->get('name'), Administration::instance()->getCurrentPageURL().'edit/' . $role->get('id') . '/', null, 'content'
					));

					if($role->get('id') != Role::PUBLIC_ROLE) {
						$td1->appendChild(Widget::Input("items[{$role->get('id')}]", null, 'checkbox'));
					}

					// Get the number of members for this role, as long as it's not the Public Role.
					if($hasRoles && $role->get('id') != Role::PUBLIC_ROLE) {
						$columns = array($td1);

						foreach($roleFields as $roleField) {
							$section = SectionManager::fetch($roleField->get('parent_section'));
							$member_count = Symphony::Database()->fetchVar('count', 0, sprintf(
								"SELECT COUNT(*) AS `count` FROM `tbl_entries_data_%d` WHERE `role_id` = %d",
								$roleField->get('id'), $role->get('id')
							));

							// If it's the first time we're looping over the available sections
							// then change the table header, otherwise just ignore it as it's
							// been done before
							if($i === 1) {
								$aTableHead[] = array($section->get('name'), 'col');
							}

							$columns[] = Widget::TableData(Widget::Anchor(
								"$member_count",
								SYMPHONY_URL . '/publish/' . $section->get('handle') . '/?filter[' . $roleField->get('element_name') . ']=' . $role->get('id')
							));
						}

						$aTableBody[] = Widget::TableRow($columns);
					}

					else if($role->get('id') == Role::PUBLIC_ROLE) {
						$td2 = Widget::TableData(__('This is the role assumed by the general public.'));
					}

					else {
						$td2 = Widget::TableData(__('None'), 'inactive');
					}

					// Add cells to a row
					if($i === 0) $aTableBody[] = Widget::TableRow(array($td1, $td2));

					if($hasRoles && $role->get('id') != Role::PUBLIC_ROLE) {
						$with_selected_roles[] = array(
							"move::" . $role->get('id'), false, $role->get('name')
						);
					}

					$i++;
				}
			}

			$table = Widget::Table(
				Widget::TableHead($aTableHead),
				NULL,
				Widget::TableBody($aTableBody),
				'selectable'
			);

			$this->Form->appendChild($table);

			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				0 => array(null, false, __('With Selected...')),
				2 => array('delete', false, __('Delete'), 'confirm'),
				3 => array('delete-members', false, __('Delete Members'), 'confirm')
			);

			if(count($with_selected_roles) > 0){
				$options[1] = array('label' => __('Move Members To'), 'options' => $with_selected_roles);
			}

			$tableActions->appendChild(Widget::Apply($options));
			$this->Form->appendChild($tableActions);
		}

		public function __viewNew() {
			$this->__viewEdit();
		}

		public function __viewEdit() {
			$isNew = true;
			$time = Widget::Time();

			// Verify role exists
			if($this->_context[0] == 'edit') {
				$isNew = false;

				if(!$role_id = $this->_context[1]) redirect(extension_Members::baseURL() . 'roles/');

				if(!$existing = RoleManager::fetch($role_id)){
					throw new SymphonyErrorPage(__('The role you requested to edit does not exist.'), __('Role not found'));
				}
			}

			// Add in custom assets
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/members/assets/members.roles.css', 'screen', 101);
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/members/assets/members.roles.js', 104);

			// Append any Page Alerts from the form's
			if(isset($this->_context[2])){
				switch($this->_context[2]){
					case 'saved':
						$this->pageAlert(
							__('Role updated at %s.', array($time->generate()))
							. ' <a href="' . extension_members::baseURL() . 'roles/new/" accesskey="c">'
							. __('Create another?')
							. '</a> <a href="' . extension_members::baseURL() . 'roles/" accesskey="a">'
							. __('View all Roles')
							. '</a>'
							, Alert::SUCCESS);
						break;

					case 'created':
						$this->pageAlert(
							__('Role created at %s.', array($time->generate()))
							. ' <a href="' . extension_members::baseURL() . 'roles/new/" accesskey="c">'
							. __('Create another?')
							. '</a> <a href="' . extension_members::baseURL() . 'roles/" accesskey="a">'
							. __('View all Roles')
							. '</a>'
							, Alert::SUCCESS);
						break;
				}
			}

			// Has the form got any errors?
			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));

			if($formHasErrors) $this->pageAlert(
				__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR
			);

			$this->setPageType('form');

			if($isNew) {
				$this->setTitle(__('Symphony &ndash; Member Roles'));
				$this->appendSubheading(__('Untitled'));

				$fields = array(
					'name' => null,
					'permissions' => null,
					'page_access' => null
				);
			}
			else {
				$this->setTitle(__('Symphony &ndash; Member Roles &ndash; ') . $existing->get('name'));
				$this->appendSubheading($existing->get('name'));

				if(isset($_POST['fields'])){
					$fields = $_POST['fields'];
				}
				else{
					$fields = array(
						'name' => $existing->get('name'),
						'permissions' => $existing->get('event_permissions'),
						'page_access' => $existing->get('forbidden_pages')
					);
				}
			}
			$this->insertBreadcrumbs(array(
				Widget::Anchor(__('Member Roles'), extension_members::baseURL() . 'roles/'),
			));

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', __('Essentials')));

			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[name]', General::sanitize($fields['name'])));

			if(isset($this->_errors['name'])) $fieldset->appendChild(Widget::Error($label, $this->_errors['name']));
			else $fieldset->appendChild($label);

			$this->Form->appendChild($fieldset);

			$events = EventManager::listAll();

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', __('Event Level Permissions')));

			$aTableBody = array();

			if(is_array($events) && !empty($events)) foreach($events as $event_handle => $event){

				$permissions = $fields['permissions'][$event_handle];

				$td_name = Widget::TableData($event['name'], 'name');

				$td_permission_create = Widget::TableData(
					sprintf('<label title="%s">%s <span>%s</span></label>',
						__('User can create new entries'),
						Widget::Input(
							"fields[permissions][{$event_handle}][create]",
							(string)EventPermissions::CREATE,
							'checkbox',
							($permissions['create'] == EventPermissions::CREATE ? array('checked' => 'checked') : NULL)
						)->generate(),
						'Create'
					),
					'create'
				);

				$td_permission_none = Widget::TableData(
					sprintf('<label title="%s">%s <span>%s</span></label>',
						__('User cannot edit existing entries'),
						Widget::Input(
							"fields[permissions][{$event_handle}][edit]",
							(string)EventPermissions::NO_PERMISSIONS,
							'radio',
							($permissions['edit'] == EventPermissions::NO_PERMISSIONS ? array('checked' => 'checked') : NULL)
						)->generate(),
						'None'
					)
				);

				$td_permission_own = Widget::TableData(
					sprintf('<label title="%s">%s <span>%s</span></label>',
						__('User can edit their own entries only'),
						Widget::Input(
							"fields[permissions][{$event_handle}][edit]",
							(string)EventPermissions::OWN_ENTRIES,
							'radio',
							($permissions['edit'] == EventPermissions::OWN_ENTRIES ? array('checked' => 'checked') : NULL)
						)->generate(),
						'Own'
					)
				);

				$td_permission_all = Widget::TableData(
					sprintf('<label title="%s">%s <span>%s</span></label>',
						__('User can edit all entries'),
						Widget::Input(
							"fields[permissions][{$event_handle}][edit]",
							(string)EventPermissions::ALL_ENTRIES,
							'radio',
							($permissions['edit'] == EventPermissions::ALL_ENTRIES ? array('checked' => 'checked') : NULL)
						)->generate(),
						'All'
					)
				);

				// Create an Event instance
				$ev = EventManager::create($event_handle, array());

				$aTableBody[] = Widget::TableRow(
					array(
						$td_name,
						$td_permission_create,
						$td_permission_none,
						$td_permission_own,
						$td_permission_all
					),
					(method_exists($ev, 'ignoreRolePermissions') && $ev->ignoreRolePermissions() == true) ? 'inactive' : ''
				);

				unset($ev);
			}

			$thead = Widget::TableHead(
				array(
					array(__('Event'), 'col', array('class' => 'name')),
					array(__('Create New'), 'col', array('class' => 'new', 'title'=> __('Toggle all'))),
					array(__('No Edit'), 'col', array('class' => 'edit', 'title'=> __('Toggle all'))),
					array(__('Edit Own'), 'col', array('class' => 'edit', 'title'=> __('Toggle all'))),
					array(__('Edit All'), 'col', array('class' => 'edit', 'title'=> __('Toggle all')))
				)
			);

			$table = Widget::Table(
				$thead,
				NULL,
				Widget::TableBody($aTableBody),
				'role-permissions'
			);

			$fieldset->appendChild($table);
			$this->Form->appendChild($fieldset);

			// Add Page Permissions [simple Deny/Allow]
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', __('Page Level Permissions')));

			$label = Widget::Label(__('Deny Access'));

			if(!is_array($fields['page_access'])) $fields['page_access'] = array();

			$options = array();
			$pages = PageManager::fetch(false, array('id'));
			if(!empty($pages)) foreach($pages as $page) {
				$options[] = array(
					$page['id'],
					in_array($page['id'], $fields['page_access']),
					'/' . PageManager::resolvePagePath($page['id'])
				);
			}

			$label->appendChild(Widget::Select('fields[page_access][]', $options, array('multiple' => 'multiple')));
			$fieldset->appendChild($label);
			$this->Form->appendChild($fieldset);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

			if(!$isNew && $existing->get('id') != Role::PUBLIC_ROLE) {
				$button = new XMLElement('button', __('Delete'));
				$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => __('Delete this Role'), 'type' => 'submit', 'accesskey' => 'd'));
				$div->appendChild($button);
			}

			$this->Form->appendChild($div);
		}

		public function __actionIndex() {
			$checked = (is_array($_POST['items'])) ? array_keys($_POST['items']) : null;

			if(is_array($checked) && !empty($checked)) {
				if(preg_match('/move::(\d+)/i', $_POST['with-selected'], $match)) {
					$roleFields = FieldManager::fetch(null, null, 'ASC', 'sortorder', extension_Members::getFieldType('role'));
					$target_role = $match[1];

					if(!$replacement = RoleManager::fetch($target_role)) return false;

					foreach($checked as $role_id){
						if($role_id == $target_role) continue;

						foreach($roleFields as $roleField) {
							Symphony::Database()->query(sprintf("
									UPDATE `tbl_entries_data_%d` SET `role_id` = %d WHERE `role_id` = %d
								",
								$roleField->get('id'), $target_role, $role_id
							));
						}
					}

					return true;
				}

				switch ($_POST['with-selected']) {
					case 'delete':
						foreach($checked as $role_id) {
							RoleManager::delete($role_id);
						}
						redirect(extension_Members::baseURL() . '/roles/');

						break;

					case 'delete-members':
						foreach($checked as $role_id) {
							RoleManager::delete($role_id, null, true);
						}
						redirect(extension_Members::baseURL() . '/roles/');

						break;
				}
			}
		}

		public function __actionNew() {
			return $this->__actionEdit();
		}

		public function __actionEdit() {
			if(array_key_exists('delete', $_POST['action'])) {
				return $this->__actionDelete($this->_context[1], extension_Members::baseURL() . 'roles/');
			}

			if(array_key_exists('save', $_POST['action'])) {
				$isNew = ($this->_context[0] !== "edit");
				$fields = $_POST['fields'];

				// If we are editing, we need to make sure the current `$role_id` exists
				if(!$isNew) {
					if(!$role_id = $this->_context[1]) redirect(extension_Members::baseURL() . 'roles/');

					if(!$existing = RoleManager::fetch($role_id)){
						throw new SymphonyErrorPage(__('The role you requested to edit does not exist.'), __('Role not found'));
					}
				}

				$name = trim($fields['name']);

				if(strlen($name) == 0){
					$this->_errors['name'] = __('This is a required field');
					return false;
				}

				$handle = Lang::createHandle($name);

				// Make sure there isn't already a Role with the same name.
				if($isNew) {
					if(RoleManager::fetchRoleIDByHandle($handle)){
						$this->_errors['name'] = __('A role with the name <code>%s</code> already exists.', array($name));
						return false;
					}
				}
				// If we are editing, we need to only run this check if the Role name has been altered
				else if($handle != $existing->get('handle') && RoleManager::fetchRoleIDByHandle($handle)){
					$this->_errors['name'] = __('A role with the name <code>%s</code> already exists.', array($name));
					return false;
				}

				$data['roles'] = array(
					'name' => $name,
					'handle' => $handle
				);

				$data['roles_forbidden_pages'] = array(
					'page_access' => $fields['page_access']
				);

				$data['roles_event_permissions'] = array(
					'permissions' => $fields['permissions']
				);

				if($isNew) {
					if($role_id = RoleManager::add($data)) {
						redirect(extension_members::baseURL() . 'roles/edit/' . $role_id . '/created/');
					}
				}
				else if(RoleManager::edit($role_id, $data)) {
					redirect(extension_members::baseURL() . 'roles/edit/' . $role_id . '/saved/');
				}
			}
		}

		public function __actionDelete($role_id = null, $redirect = null, $purge_members = false) {
			if(array_key_exists('delete', $_POST['action'])) {
				if(!$role_id) redirect(extension_Members::baseURL() . 'roles/');

				if($role_id == Role::PUBLIC_ROLE) {
					return $this->pageAlert(
						__('The Public role cannot be removed'), Alert::ERROR
					);
				}

				if(!$existing = RoleManager::fetch($role_id)) {
					throw new SymphonyErrorPage(__('The role you requested to delete does not exist.'), __('Role not found'));
				}

				// @todo What should happen to any Members that had this Role?
				RoleManager::delete($role_id, $purge_members);

				if(!is_null($redirect)) redirect($redirect);
			}
		}
	}
