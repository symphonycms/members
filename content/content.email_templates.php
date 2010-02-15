<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	
	Class contentExtensionMembersEmail_Templates extends AdministrationPage{
		
		private $_driver;

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle(__('Symphony &ndash; Email Templates'));
			
			$this->_driver = Administration::instance()->ExtensionManager->create('members');
			
		}
		
		public function action() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) && !empty($checked)) {
				if($_POST['with-selected'] == 'delete'):
					foreach($checked as $id){
						EmailTemplate::delete($id);
					}
				endif;
				
			}
			
			redirect(extension_members::baseURL() . 'email_templates/');
		}	
	
		public function view(){

			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 9126341);
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/members/assets/scripts.js', 9126342);
			
			$create_button = Widget::Anchor(__('Create a new email template'), extension_members::baseURL() . 'email_templates_new/', __('Create a new email template'), 'create button');

			$this->setPageType('table');
			$this->appendSubheading(__('Email Templates ') . $create_button->generate(false));


			$aTableHead = array(
				array(__('Subject'), 'col'),
				array(__('Type'), 'col'),				
				array(__('Roles'), 'col')
			);	
		
			$iterator = new EmailTemplateIterator;
								
			$aTableBody = array();

			if($iterator->length() == 0){
				$aTableBody = array(
					Widget::TableRow(
						array(Widget::TableData(__('None Found.'), 'inactive', NULL, count($aTableHead)))
					)
				);
			}
			
			else{

				$bEven = true;

				foreach($iterator as $e){

					$td1 = Widget::TableData(Widget::Anchor($e->subject, extension_members::baseURL() . 'email_templates_edit/' . $e->id . '/', NULL, 'content'));

					$td2 = Widget::TableData($e->type);
					
					if(count($e->roles()) > 0){
 
						$links = array();
						foreach($e->roles() as $role_id => $r){
							$links[] = Widget::Anchor(
								$r->name(), 
								extension_members::baseURL() . 'roles_edit/' . $r->id() . '/', 
								__('Edit this role.')
							)->generate();
						}
						$td3 = Widget::TableData(implode(', ', $links));
					}
					else{
						$td3 = Widget::TableData(__('None'), 'inactive');
					}
					
					$td3->appendChild(Widget::Input("items[{$e->id}]", null, 'checkbox'));
					
					## Add a row to the body array, assigning each cell to the row
					$aTableBody[] = Widget::TableRow(array($td1, $td2, $td3), ($bEven ? 'odd' : NULL));		

					$bEven = !$bEven;
					
				}
			
			}
			
			$table = Widget::Table(
				Widget::TableHead($aTableHead), 
				NULL, 
				Widget::TableBody($aTableBody)
			);
					
			$this->Form->appendChild($table);
			
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, __('With Selected...')),
				array('delete', false, __('Delete')),
			);
			
			$tableActions->appendChild(Widget::Select('with-selected', $options, array('id' => 'with-selected')));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			
			$this->Form->appendChild($tableActions);			

		}
	}
	
