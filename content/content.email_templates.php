<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	Class contentExtensionMembersEmail_Templates extends AdministrationPage {

		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Email Templates'))));

			if(is_null(extension_Members::getConfigVar('email')) && !is_null(extension_Members::getMembersSection())) {
				$this->pageAlert(
					__('There is no registered field in the active Members section. <a href="%s%d/">Add Member: Email field?</a>',
					array(
						SYMPHONY_URL . '/blueprints/sections/edit/',
						extension_Members::getMembersSection()
					)),
					Alert::NOTICE
				);
			}

			$this->appendSubheading(__('Email Templates'), Widget::Anchor(
				__('Create New'), extension_members::baseURL() . 'email_templates/new/', __('Create a Email Template'), 'create button', NULL, array('accesskey' => 'c')
			));

			$templates = EmailTemplateManager::fetch();

			$aTableHead = array(
				array(__('Subject'), 'col'),
				array(__('Type'), 'col'),
				array(__('Roles'), 'col')
			);

			$aTableBody = array();

			if(!is_array($templates) || empty($templates)){
				$aTableBody = array(Widget::TableRow(
					array(Widget::TableData(__('None found.'), 'inactive', NULL, count($aTableHead)))
				));
			}

			else if(is_null(extension_Members::getMembersSection())) {
				$aTableBody = array(Widget::TableRow(
					array(Widget::TableData(__('No Member section has been specified in %s. Please do this first.', array('<a href="'.SYMPHONY_URL.'/system/preferences/">Preferences</a>')), 'inactive', NULL, count($aTableHead)))
				));
			}

			else {

				foreach($templates as $template) {
					$td1 = Widget::TableData(Widget::Anchor(
						$template->get('subject'), extension_members::baseURL() . 'email_templates/edit/' . $template->get('id') . '/', NULL, 'content'
					));

					$td1->appendChild(Widget::Input("items[{$template->get('id')}]", null, 'checkbox'));

					$td2 = Widget::TableData($template->get('type'));

					$template_roles = $template->get('roles');
					if(empty($template_roles)) {
						$td3 = Widget::TableData(__('None'), 'inactive');
					}
					else {
						$links = array();
						foreach($template_roles as $role_id => $role) {
							$links[] = Widget::Anchor(
								$role->get('name'),
								extension_members::baseURL() . 'roles/edit/' . $role->get('id') . '/',
								__('Edit this role')
							);
						}
						$td3 = Widget::TableData(implode(', ', $links));
					}

					// Add cells to a row
					$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3));
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
				array(null, false, __('With Selected...')),
				array('delete', false, __('Delete'), 'confirm')
			);

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

			$this->Form->appendChild($tableActions);
		}

		public function __viewNew() {
			$this->__viewEdit();
		}

		public function __viewEdit() {
			$isNew = true;
			// Verify role exists
			if($this->_context[0] == 'edit') {
				$isNew = false;

				if(!$template_id = $this->_context[1]) redirect(extension_Members::baseURL() . 'email_templates/');

				if(!$existing = EmailTemplateManager::fetch($template_id)){
					throw new SymphonyErrorPage(__('The email template you requested to delete does not exist.'), __('Email Template not found'), 'error');
				}
			}

			// Append any Page Alerts from the form's
			if(isset($this->_context[2])){
				switch($this->_context[2]){
					case 'saved':
						$this->pageAlert(
							__(
								'Email template updated at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Email Templates</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									extension_members::baseURL() . 'email_templates/new/',
									extension_members::baseURL() . 'email_templates/',
								)
							),
							Alert::SUCCESS);
						break;

					case 'created':
						$this->pageAlert(
							__(
								'Email Template created at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Email Templates</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
									extension_members::baseURL() . 'email_templates/new/',
									extension_members::baseURL() . 'email_templates/',
								)
							),
							Alert::SUCCESS);
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
				$this->setTitle(__('Symphony &ndash; Email Templates &ndash; '));

				$fields = array(
					'subject' => null,
					'body' => null,
					'type' => null,
					'roles' => null
				);
			}
			else {
				$this->setTitle(__('Symphony &ndash; Member Roles &ndash; ') . $existing->get('subject'));
				$this->appendSubheading($existing->get('subject'));

				if(isset($_POST['fields'])){
					$fields = $_POST['fields'];
				}
				else {
					$fields['subject'] = $existing->get('subject');
					$fields['body'] = $existing->get('body');
					$fields['type'] = $existing->get('type');

					$fields['roles'] = null;
					foreach($existing->get('roles') as $role_id => $role){
						$fields['roles'] .= $role->get('name') . ', ';
					}
					$fields['roles'] = trim($fields['roles'], ', ');
				}
			}

			// Create the Settings fieldset
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Essentials')));

			$helptext = __('Choose the type of event that will trigger this Email Template being sent to the user.');

			if(!is_null(extension_Members::getConfigVar('role'))) {
				$roletext = __(' You can specify a different template for each of the Roles in your system.');
			}
			else $roletext = '';

			$help = new XMLElement('p', $helptext . $roletext);
			$help->setAttribute('class', 'help');
			$fieldset->appendChild($help);

			// Add Template Type
			$label = Widget::Label(__('Template Type'));
			$label->appendChild(Widget::Select(
				'fields[type]', array(
					array(NULL, false, NULL),
					array('activate-account', $fields['type'] == 'activate-account', __('Activate Account')),
					array('welcome', $fields['type'] == 'welcome', __('Welcome Email')),
					array('new-password', $fields['type'] == 'new-password', __('New Password')),
					array('reset-password', $fields['type'] == 'reset-password', __('Reset Password')),
				)
			));

			$fieldset->appendChild($label);

			// Add Roles Selector
			if(!is_null(extension_Members::getConfigVar('role'))) {
				$label = Widget::Label(__('Role'));
				$label->appendChild(Widget::Input('fields[roles]', General::sanitize($fields['roles'])));

				if(isset($this->_errors['roles'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['roles']));
				else $fieldset->appendChild($label);

				$roles = RoleManager::fetch();

				if(is_array($roles) && !empty($roles)) {
					$taglist = new XMLElement('ul');
					$taglist->setAttribute('class', 'tags');

					foreach($roles as $role) {
						$taglist->appendChild(new XMLElement('li', $role->get('name')));
					}

					$fieldset->appendChild($taglist);
				}
			}

			// Append the fieldset
			$this->Form->appendChild($fieldset);

			// Create the Email Contents fieldset
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Email Contents')));

			$help = new XMLElement('p',
				__('This will be the subject line that the user will see in their Inbox. <br /> Dynamic fields and parameters
				can be included in the subject or body of the email using the <code>{$param}</code> syntax. Please see the <a
				href="%s">readme</a> for a complete list of available parameters.',
					array("http://github.com/symphonycms/members/blob/master/README.markdown")
				)
			);
			$help->setAttribute('class', 'help');
			$fieldset->appendChild($help);

			// Add in Subject
			$label = Widget::Label(__('Subject'));
			$label->appendChild(Widget::Input('fields[subject]', General::sanitize($fields['subject'])));

			if(isset($this->_errors['subject'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['subject']));
			else $fieldset->appendChild($label);

			// Add in Body
			$label = Widget::Label(__('Body'));
			$label->appendChild(Widget::Textarea('fields[body]', 15, 75, General::sanitize($fields['body'])));

			if(isset($this->_errors['body'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['body']));
			else $fieldset->appendChild($label);

			// Append the fieldset
			$this->Form->appendChild($fieldset);

			// Form Actions
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

			if(!$isNew) {
				$button = new XMLElement('button', __('Delete'));
				$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => __('Delete this Email template'), 'type' => 'submit', 'accesskey' => 'd'));
				$div->appendChild($button);
			}

			$this->Form->appendChild($div);
		}

		public function __actionIndex() {
			$checked = (is_array($_POST['items'])) ? array_keys($_POST['items']) : null;

			if(is_array($checked) && !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach($checked as $template_id) {
							$this->__actionDelete($template_id);
						}
						redirect(extension_Members::baseURL() . 'email_templates/');

						break;
				}
			}
		}

		public function __actionNew() {
			return $this->__actionEdit();
		}

		public function __actionEdit() {
			if(array_key_exists('delete', $_POST['action'])) {
				return $this->__actionDelete($this->_context[1], extension_Members::baseURL() . 'email_templates/');
			}
			else if(array_key_exists('save', $_POST['action'])) {
				$isNew = ($this->_context[0] !== "edit");
				$fields = $_POST['fields'];

				// If we are editing, we need to make sure the current `$role_id` exists
				if(!$isNew) {
					if(!$template_id = $this->_context[1]) redirect(extension_Members::baseURL() . 'email_templates/');

					if(!$existing = EmailTemplateManager::fetch($template_id)){
						throw new SymphonyErrorPage(__('The email template you requested to delete does not exist.'), __('Email Template not found'), 'error');
					}
				}

				$subject = trim($fields['subject']);
				if(strlen($subject) == 0){
					$this->_errors['subject'] = __('This is a required field');
					return false;
				}

				$roles = trim($fields['roles']);
				if(strlen($roles) == 0){
					$this->_errors['roles'] = __('This is a required field');
					return false;
				}
				else {
					$roles = preg_split('/\s*,\s*/i', $fields['roles'], -1, PREG_SPLIT_NO_EMPTY);
					$et_roles = array();
					foreach($roles as $role_name) {
						$et_roles[] = RoleManager::fetchRoleIDByHandle(Lang::createHandle($role_name));
					}
					unset($roles);
				}

				$type = $fields['type'];
				$body = $fields['body'];

				// Make sure there isn't already a Email Template for this type and/or role.
				if($isNew) {
					if(EmailTemplateManager::fetch(null, $type, $et_roles)) {
						$this->_errors['type'] = __('A Email template for this type and role already exists.');
						return false;
					}
				}
				// If we are editing, we need to only run this check if the Role name has been altered
				else {
					if($template_id != $existing->get('id') && EmailTemplateManager::fetch(null, $type, $et_roles)) {
						$this->_errors['type'] = __('A Email template for this type and role already exists.');
						return false;
					}
				}

				$data['email_templates'] = array(
					'subject' => $subject,
					'type' => $type,
					'body' => $body
				);

				$data['email_templates_role_mapping'] = array(
					'roles' => $et_roles
				);

				if($isNew) {
					if($template_id = EmailTemplateManager::add($data)) {
						redirect(extension_members::baseURL() . 'email_templates/edit/' . $template_id . '/created/');
					}
				}
				else {
					if(EmailTemplateManager::edit($template_id, $data)) {
						redirect(extension_members::baseURL() . 'email_templates/edit/' . $template_id . '/saved/');
					}
				}
			}
		}

		public function __actionDelete($template_id = null, $redirect = null) {
			if(array_key_exists('delete', $_POST['action'])) {
				if(!$template_id) redirect(extension_Members::baseURL() . 'email_templates/');

				if(!$existing = EmailTemplateManager::fetch($template_id)){
					throw new SymphonyErrorPage(__('The email template you requested to delete does not exist.'), __('Email Template not found'), 'error');
				}

				EmailTemplateManager::delete($template_id);

				if(!is_null($redirect)) redirect($redirect);
			}
		}

	}
