<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.eventmanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');
	
	Class contentExtensionMembersEmail_Templates_New extends AdministrationPage{

		private $_driver;

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle('Symphony &ndash; Email Templates &ndash; Untitled');
			$this->_driver = Administration::instance()->ExtensionManager->create('members');
		}
		
		public function action(){
			
			if(isset($_POST['action']['save'])){

				$fields = $_POST['fields'];

				$et = new EmailTemplate;
				$et->subject = $fields['subject'];
				$et->type = $fields['type'];				
				$et->body = $fields['body'];			
							
				if(isset($fields['roles']) && strlen(trim($fields['roles'])) > 0){
					$roles = preg_split('/\s*,\s*/i', $fields['roles'], -1, PREG_SPLIT_NO_EMPTY);
					foreach($roles as $r){
						$et->addRoleFromName($r);
					}
				}			
								
				EmailTemplate::save($et);
				
				redirect(extension_members::baseURL() . 'email_templates_edit/' . $et->id . '/created/');
			}

		}
		
		public function view(){
			
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 9125341);

			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));
			
			if($formHasErrors) $this->pageAlert('An error occurred while processing this form. <a href="#error">See below for details.</a>', AdministrationPage::PAGE_ALERT_ERROR);
			
			$this->setPageType('form');	

			$this->appendSubheading('Untitled');
		
			$fields = array();
			
			if(isset($_POST['fields'])){
				$fields = $_POST['fields'];
			}

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'primary');

			$label = Widget::Label('Subject');
			$label->appendChild(Widget::Input('fields[subject]', General::sanitize($fields['subject'])));

			if(isset($this->_errors['subject'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['subject']));
			else $fieldset->appendChild($label);

			$label = Widget::Label('Body');
			$label->appendChild(Widget::Textarea('fields[body]', 15, 75, General::sanitize($fields['body'])));

			if(isset($this->_errors['body'])) $fieldset->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['body']));
			else $fieldset->appendChild($label);

			$fieldset->appendChild(new XMLElement('p', 'Dynamic fields and parameters can be included in the subject or body of the email using the <code>{$param}</code> syntax. Available fields and parameters: member-name, member-email, username, root, site-name, etc, etc.', array('class' => 'help')));
						
			$this->Form->appendChild($fieldset);			
			
			$sidebar = new XMLElement('fieldset');
			$sidebar->setAttribute('class', 'secondary');
	
			$label = Widget::Label('Type');
			$options = array(
				array(NULL, false, NULL),
				array('reset-password', $fields['type'] == 'reset-password', 'Reset Password'),
				array('new-password', $fields['type'] == 'new-password', 'New Password'),				
				array('activate-account', $fields['type'] == 'activate-account', 'Activate Account'),
				array('welcome', $fields['type'] == 'welcome', 'Welcome Email'),
			);
			$label->appendChild(Widget::Select('fields[type]', $options));

			if(isset($this->_errors['type'])) $sidebar->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['type']));
			else $sidebar->appendChild($label);
				
			
			$label = Widget::Label('Roles');
			
			$label->appendChild(Widget::Input('fields[roles]', $fields['roles']));
		
			if(isset($this->_errors['roles'])) $sidebar->appendChild(Widget::wrapFormElementWithError($label, $this->_errors['roles']));
			else $sidebar->appendChild($label);
				
			$roles = DatabaseUtilities::resultColumn(ASDCLoader::instance()->query(
				"SELECT `name` FROM `tbl_members_roles` ORDER BY `name` ASC"
			), 'name');

			if(is_array($roles) && !empty($roles)){
				$taglist = new XMLElement('ul');
				$taglist->setAttribute('class', 'tags');
				
				foreach($roles as $tag) $taglist->appendChild(new XMLElement('li', $tag));
						
				$sidebar->appendChild($taglist);
			}

			
			$this->Form->appendChild($sidebar);					
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[save]', 'Create', 'submit', array('accesskey' => 's')));
	
			$this->Form->appendChild($div);			

		}
	}
	
