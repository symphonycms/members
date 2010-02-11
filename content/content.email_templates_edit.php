<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	
	Class contentExtensionMembersEmail_Templates_Edit extends AdministrationPage{

		private $_driver;

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_driver = Administration::instance()->ExtensionManager->create('members');
		}

		public function action(){

			$email_template_id = $this->_context[0];
			$fields = $_POST['fields'];
			
			if(isset($_POST['action']['delete'])):
				
				EmailTemplate::delete($email_template_id);
				
				redirect(extension_members::baseURL() . 'email_templates/');
			
			
			elseif(isset($_POST['action']['save'])):
			
				$et = EmailTemplate::loadFromID($email_template_id);
				$et->removeAllRoles();

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

				redirect(extension_members::baseURL() . 'email_templates_edit/' . $et->id . '/saved/');
				
			endif;

		}

		public function view(){

			if(!$email_template_id = $this->_context[0]) redirect(extension_members::baseURL());
					
			if(!$existing = EmailTemplate::loadFromID($email_template_id)){
				throw new SymphonyErrorPage(__('The email template you requested to edit does not exist.'), __('Email Template not found'), 'error');
			}
			
			if(isset($this->_context[1])){
				switch($this->_context[1]){
				
					case 'saved':
					
						$this->pageAlert(
							__(
								'Email Template updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Email Template</a>', 
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__), 
									extension_members::baseURL() . 'email_templates_new/', 
									extension_members::baseURL() . 'email_templates/',
								)
							), 
							Alert::SUCCESS);						
					
						break;
					
					case 'created':
						$this->pageAlert(
							__(
								'Email Template created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Email Template</a>', 
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__), 
									extension_members::baseURL() . 'email_templates_new/', 
									extension_members::baseURL() . 'email_templates/', 
								)
							), 
							Alert::SUCCESS);
						break;
				}
			}
			
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 9125341);


			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));

			if($formHasErrors){
				$this->pageAlert(
					__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), 
					AdministrationPage::PAGE_ALERT_ERROR
				);
			}

			$this->setPageType('form');	
			
			$this->setTitle('Symphony &ndash; Member Roles &ndash; ' . $existing->subject);
			$this->appendSubheading($existing->subject);

			$fields = array();

			if(isset($_POST['fields'])){
				$fields = $_POST['fields'];
			}
			
			else{
				
				$fields['subject'] = $existing->subject;
				$fields['body'] = $existing->body;				
				$fields['type'] = $existing->type;
				$fields['roles'] = NULL;
				foreach($existing->roles() as $role_id => $r){
					$fields['roles'] .= $r->name() . ", ";

				}
				$fields['roles'] = trim($fields['roles'], ', ');

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

			$fieldset->appendChild(new XMLElement('p', 'Dynamic fields and parameters can be included in the subject or body of the email using the <code>{$param}</code> syntax. Please see the <a href="http://github.com/symphony/members/blob/master/README.markdown">readme</a> for a complete list of available parameters.', array('class' => 'help')));
			
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
			$div->appendChild(Widget::Input('action[save]', 'Save Changes', 'submit', array('accesskey' => 's')));
			
			
			$button = new XMLElement('button', __('Delete'));
			$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'confirm delete', 'title' => __('Delete this email template')));
			$div->appendChild($button);
			
			$this->Form->appendChild($div);			

		}
	}

