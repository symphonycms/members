<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.eventmanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');
	
	Class contentExtensionMembersRoles_New extends AdministrationPage{

		private $_driver;

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle(__('Symphony &ndash; Member Roles &ndash; Untitled'));
			$this->_driver = Administration::instance()->ExtensionManager->create('members');
		}
		
		public function action(){
			
			if(isset($_POST['action']['save'])){

				$fields = $_POST['fields'];

				$permissions = $fields['permissions'];
				$name = trim($fields['name']);
				$page_access = $fields['page_access'];
				
				if(strlen($name) == 0){
					$this->_errors['name'] = __('This is a required field');
					return;
				}
				
				elseif($this->_driver->roleExists($name)){
					$this->_errors['name'] = __('A role with the name <code>%s</code> already exists.', array($name));
					return;
				}
			
				ASDCLoader::instance()->query("INSERT INTO `tbl_members_roles` VALUES (NULL, '{$name}')");
				
				$role_id = ASDCLoader::instance()->lastInsertID();
				
				if(is_array($page_access) && !empty($page_access)){
					foreach($page_access as $page_id){
						ASDCLoader::instance()->query("INSERT INTO `tbl_members_roles_forbidden_pages` VALUES (NULL, $role_id, $page_id)");
					}
				}
				
				if(is_array($permissions) && !empty($permissions)){
					
					$sql = "INSERT INTO `tbl_members_roles_event_permissions` VALUES ";
					
					foreach($permissions as $event_handle => $p){
						foreach($p as $action => $level)
							$sql .= "(NULL,  {$role_id}, '{$event_handle}', '{$action}', '{$level}'),";
					}
					
					Symphony::Database()->query(trim($sql, ','));
				}
				
				redirect(extension_members::baseURL() . 'roles_edit/' . $role_id . '/created/');
			}

		}
		
		public function view(){
			
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 9125341);
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/members/assets/jquery-ui.css', 'screen', 9125342);
						
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/members/assets/jquery-ui.js', 9126342);
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/members/assets/members.js', 9126343);

			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));
			
			if($formHasErrors) $this->pageAlert(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), AdministrationPage::PAGE_ALERT_ERROR);
			
			$this->setPageType('form');	

			$this->appendSubheading(__('Untitled'));
		
			$fields = array();
			
			if(isset($_POST['fields'])){
				$fields = $_POST['fields'];
			}

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', __('Essentials')));

			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[name]', General::sanitize($fields['name'])));

			if(isset($this->_errors['name'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['name']));
			else $fieldset->appendChild($label);

			$this->Form->appendChild($fieldset);				
			
			$EventManager = new EventManager($this->_Parent);
			$events = $EventManager->listAll();

			if(is_array($events) && !empty($events)){
				foreach($events as $handle => $e){
					
					$show_in_role_permissions = 
						(method_exists("event{$handle}", 'showInRolePermissions') && call_user_func(array("event{$handle}", 'showInRolePermissions')) === true 
							? true 
							: false
						);
					
					if(!$e['can_parse'] && !$show_in_role_permissions) unset($events[$handle]);
				}
			}		
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', __('Event Level Permissions')));			
			
			$aTableHead = array(
				array(__('Event'), 'col'),
				array(__('Create'), 'col'),
				array(__('Edit'), 'col'),			
			//	array('Delete', 'col'),					
			);	

			$aTableBody = array();
			
			
			/*
			<tr class="global">
				<td>Set Global Permissions</td>
				<td class="add">
					<input type="checkbox" name="add-global" value="no"/>
				</td>
				<td class="edit">
					<p class="global-slider"></p>
					<span>n/a</span>
				</td>
				<!--<td class="delete">
					<p class="global-slider"></p>
					<span>n/a</span>
				</td>-->
			</tr>
			*/
				
			## Setup each cell
			$td1 = Widget::TableData(__('Global Permissions'));
			
			$td2 = Widget::TableData(Widget::Input(
				'global-add', 
				'1', 
				'checkbox'
			), 'add');
		
			$td3 = Widget::TableData(NULL, 'edit');
			$td3->appendChild(new XMLElement('p', NULL, array('class' => 'global-slider')));
			$td3->appendChild(new XMLElement('span', 'n/a'));				

			$td4 = Widget::TableData(NULL, 'delete');
			$td4->appendChild(new XMLElement('p', NULL, array('class' => 'global-slider')));
			$td4->appendChild(new XMLElement('span', 'n/a'));
			

			## Add a row to the body array, assigning each cell to the row
			$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3), 'global'); //, $td4
			
			if(is_array($events) && !empty($events)){

				foreach($events as $event_handle => $event){
				
					$permissions = $fields['permissions'][$event_handle];
				
					## Setup each cell
					$td1 = Widget::TableData($event['name']);

					$td2 = Widget::TableData(Widget::Input(
						"fields[permissions][{$event_handle}][create]", 
						'1', 
						'checkbox', 
						($permissions['create'] == 1 ? array('checked' => 'checked') : NULL)
					), 'add');
							
					$td3 = Widget::TableData(NULL, 'edit');
					$td3->appendChild(new XMLElement('p', NULL, array('class' => 'slider')));
					$span = new XMLElement('span');
					$span->setSelfClosingTag(false);
					$td3->appendChild($span);
				
					$td3->appendChild(Widget::Input(
						'fields[permissions][' . $event_handle .'][edit]', 
						(isset($permissions['edit']) ? $permissions['edit'] : '0'),
						'hidden'
					));
				
				
					$td4 = Widget::TableData(NULL, 'delete');
					$td4->appendChild(new XMLElement('p', NULL, array('class' => 'slider')));
					$span = new XMLElement('span');
					$span->setSelfClosingTag(false);
					$td4->appendChild($span);
				
					$td4->appendChild(Widget::Input(
						'fields[permissions][' . $event_handle .'][delete]', 
						(isset($permissions['delete']) ? $permissions['delete'] : '0'),
						'hidden'
					));
					/*
					<tr>
						<td>{EVENT-NAME}</td>
						<td class="add">
							<input type="checkbox" name="{ANY NAME}" value="{EXISTING STATE:No}"/>
						</td>
						<td class="edit">
							<p class="slider"></p>
							<span></span>
							<input type="hidden" name="{ANY NAME}" value="{EXISTING-VALUE:1}"/>
						</td>
						<!--<td class="delete">
							<p class="slider"></p>
							<span></span>
							<input type="hidden" name="{ANY NAME}" value="{EXISTING-VALUE:1}"/>
						</td>-->
					</tr>
					*/
					
					## Add a row to the body array, assigning each cell to the row
					$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3)); //, $td4));

				}
			}
			

			$table = Widget::Table(
				Widget::TableHead($aTableHead), 
				NULL, 
				Widget::TableBody($aTableBody),
				'role-permissions'
			);
				
									
			$fieldset->appendChild($table);		
			$this->Form->appendChild($fieldset);
			
			####
			# Delegate: MemberRolePermissionFieldsetsEdit
			# Description: Add custom fieldsets to the role page
			Administration::instance()->ExtensionManager->notifyMembers(
				'MemberRolePermissionFieldsetsEdit', 
				'/extension/members/roles_edit/', 
				array('form' => &$this->Form, 'permissions' => $fields['permissions'])
			);
			#####


			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', __('Page Level Permissions')));

			$pages = ASDCLoader::instance()->query(sprintf(
				"SELECT * FROM `tbl_pages` %s ORDER BY `title` ASC",
				($this->_context[0] == 'edit' ? "WHERE `id` != '{$page_id}' " : NULL)
			));

			$label = Widget::Label(__('Deny Access'));

			$options = array();
			if($pages->length() > 0){
				foreach($pages as $page){
					$options[] = array(
						$page->id, 
						@in_array($page->id, $fields['page_access']), 
						'/' . Administration::instance()->resolvePagePath($page->id)
					);
				}
			}

			$label->appendChild(Widget::Select('fields[page_access][]', $options, array('multiple' => 'multiple')));		
			$fieldset->appendChild($label);				
			$this->Form->appendChild($fieldset);

			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Create'), 'submit', array('accesskey' => 's')));
	
			$this->Form->appendChild($div);			

		}
	}
	
