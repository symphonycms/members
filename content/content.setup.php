<?php

	require_once(TOOLKIT . '/class.administrationpage.php');

	Class contentExtensionMembersSetup extends AdministrationPage{

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle(__('Symphony &ndash; Members &ndash; Setup'));
			$this->setPageType('form');
		}

		public function view(){

			$sectionManager = new SectionManager(Administration::instance());

			$this->_Parent->Page->addStylesheetToHead(URL . '/extensions/members/assets/styles.css', 'screen', 70);

			$this->appendSubheading(__('Setup'));

		    $bIsWritable = true;
			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));

		    if(!is_writable(CONFIG)){
		        $this->pageAlert(__('The Symphony configuration file, <code>/manifest/config.php</code>, is not writable. You will not be able to save changes to preferences.'),
Alert::ERROR);
		        $bIsWritable = false;
		    }

			elseif($formHasErrors) $this->pageAlert(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR);

			if(!is_null(extension_members::getConfigVar('member_section'))){
				$member_section = $sectionManager->fetch(extension_members::getConfigVar('member_section'));
			}

			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Instructions')));

			$p = new XMLElement('p', __('A section is required for storing member details. Use the button below to automatically create a compatible section (you can always edit or add fields later), or use
the dropdowns to link to an existing section containing the required fields.'));
			$group->appendChild($p);

			$this->Form->appendChild($group);

			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Smart Setup')));

			$attr = array('name' => 'action[smart-setup]', 'type' => 'submit');
			if($member_section instanceof Section){
				$attr['disabled'] = 'disabled';
			}
			$div = new XMLElement('div', NULL, array('id' => 'file-actions', 'class' => 'label'));
			$span = new XMLElement('span');
			$span->appendChild(new XMLElement('button', __('Create'), $attr));
			$div->appendChild($span);

			$div->appendChild(new XMLElement('p', __('Automatically creates a new section, called Members, containing Username/Password, Role, Email Address, and Timezone Offset fields.'), array('class' =>
'help')));

			$group->appendChild($div);
			$this->Form->appendChild($group);


			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');

			$group->appendChild(new XMLElement('legend', __('Essentials')));

			$p = new XMLElement('p', __('Must contain a <code>Member</code> type field. Will be used to validate login details.'));
			$p->setAttribute('class', 'help');
			$group->appendChild($p);

			$div = new XMLElement('div', NULL, array('class' => 'group'));

			$section_list = Symphony::Database()->fetchCol('parent_section', "SELECT `parent_section` FROM `tbl_fields` WHERE `type` = 'member'");


			$label = Widget::Label(__('Member Section'));

			$options = array();

			foreach($section_list as $section_id){

				$section = $sectionManager->fetch($section_id);

				$options[] = array($section_id, (extension_members::getConfigVar('member_section') == $section_id), $section->get('name'));
			}

			$label->appendChild(Widget::Select('fields[member_section]', $options));
			$div->appendChild($label);


			$label = Widget::Label(__('Email Address Field'));


			if($member_section instanceof Section){

				$options = array(array('', false, ''));

				foreach($member_section->fetchFields() as $f){
					$options[] = array($f->get('id'), (Symphony::Configuration()->get('email_address_field_id', 'members') == $f->get('id')), $f->get('label'));
				}
			}

			else $options = array(array('', false, __('Must set Member section first')));

			$label->appendChild(Widget::Select('fields[email_address_field_id]', $options, ($member_section instanceof Section ? NULL : array('disabled' => 'disabled'))));
			$div->appendChild($label);

			$group->appendChild($div);


			$div = new XMLElement('div', NULL, array('class' => 'group'));

			$label = Widget::Label(__('Timezone Offset Field'));

			if($member_section instanceof Section){

				$options = array(array('', false, ''));

				foreach($member_section->fetchFields() as $f){
					$options[] = array($f->get('id'), (Symphony::Configuration()->get('timezone_offset_field_id', 'members') == $f->get('id')), $f->get('label'));
				}
			}

			else $options = array(array('', false, __('Must set Member section first')));

			$label->appendChild(Widget::Select('fields[timezone_offset_field_id]', $options, ($member_section instanceof Section ? NULL : array('disabled' => 'disabled'))));
			$div->appendChild($label);
			$group->appendChild($div);


			$group->appendChild(new XMLElement('p', __('Stores member timezones. Used to dynamically adjust date displays. Defaults to Symphony configuration offset.'), array('class' => 'help')));

			$this->Form->appendChild($group);


			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');

			$group->appendChild(new XMLElement('legend', __('Registration')));

			$div = new XMLElement('div', NULL, array('class' => 'group'));

			$label = Widget::Label(__('New Member Default Role'));

			$options = array(array(NULL, false, NULL));
			foreach(extension_members::fetchRoles() as $r){
				if(in_array($r->id(), array(extension_Members::GUEST_ROLE_ID, extension_Members::INACTIVE_ROLE_ID))) continue;
				$options[] = array($r->id(), Symphony::Configuration()->get('new_member_default_role', 'members') == $r->id(), $r->name());
			}

			$label->appendChild(Widget::Select('fields[new_member_default_role]', $options));
			$div->appendChild($label);
			$group->appendChild($div);

			$label = Widget::Label();
			$input = Widget::Input('fields[require_activation]', 'yes', 'checkbox');
			if(Symphony::Configuration()->get('require_activation', 'members') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' ' . __('New members require activation'));
			$group->appendChild($label);

			$group->appendChild(new XMLElement('p', __('If activation is required, new members will be added to the \'Inactive\' role until they reply to the \'Activate Account\' email. Activated members will be added to the role selected above.'), array('class' => 'help')));

			$this->Form->appendChild($group);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');

			$attr = array('accesskey' => 's');
			if(!$bIsWritable) $attr['disabled'] = 'disabled';
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', $attr));

			$this->Form->appendChild($div);

		}

		public function action(){

			##Do not proceed if the config file is read only
		    if(!is_writable(CONFIG)) redirect($this->_Parent->getCurrentPageURL());

			if(isset($_POST['action']['save'])){

				$settings = array_map('addslashes', $_POST['fields']);

				if(!isset($settings['require_activation'])) $settings['require_activation'] = 'no';

				foreach($settings as $key => $value) Symphony::Configuration()->set($key, $value, 'members');

				$this->_Parent->saveConfig();

				redirect($this->_Parent->getCurrentPageURL());

			}
			elseif(isset($_POST['action']['smart-setup'])){
				$db = Symphony::Database();

				try{

					// Create thew new Section
					$db->query("INSERT INTO `tbl_sections` VALUES(
						NULL, 'Members', 'members', 999, NULL, 'asc', 'no', 'Content'
					)");
					$section_id = $db->getInsertID();

					// Member Field
					$db->query(sprintf(
						"INSERT INTO `tbl_fields`
						VALUES(
							NULL, 'Username and Password', 'username-and-password', 'member', %d, 'yes', 0, 'main', 'yes'
						)",
						$section_id
					));
					$member_field_id = $db->getInsertID();

					$db->query(sprintf("INSERT INTO `tbl_fields_member` VALUES(NULL, %d, 1, 'weak', '')", $member_field_id));

					// Member Field data table
					$db->query(sprintf(
						"CREATE TABLE `tbl_entries_data_%d` (
						  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
						  `entry_id` int(11) unsigned NOT NULL,
						  `username` varchar(50) DEFAULT NULL,
						  `password` varchar(32) DEFAULT NULL,
						  `strength` tinyint(2) NOT NULL,
						  `length` tinyint(2) NOT NULL,
						  PRIMARY KEY (`id`),
						  KEY `entry_id` (`entry_id`),
						  KEY `username` (`username`)
						)",
						$member_field_id
					));

					// Role Field
					$db->query(sprintf(
						"INSERT INTO `tbl_fields`
						VALUES(NULL, 'Role', 'role', 'memberrole', %d, 'no', 2, 'sidebar', 'yes')",
						$section_id
					));
					$role_field_id = $db->getInsertID();

					$db->query(sprintf("INSERT INTO `tbl_fields_memberrole` VALUES(NULL, %d)", $role_field_id));

					// Role Field data table
					$db->query(sprintf(
						"CREATE TABLE `tbl_entries_data_%d` (
						  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
						  `entry_id` int(11) unsigned NOT NULL,
						  `role_id` int(11) unsigned NOT NULL,
						  PRIMARY KEY (`id`),
						  KEY `entry_id` (`entry_id`,`role_id`)
						)",
						$role_field_id
					));

					// Timezone Offset Field
					$db->query(sprintf(
						"INSERT INTO `tbl_fields`
						VALUES(NULL, 'Timezone Offset', 'timezone-offset', 'input', %d, 'no', 3, 'sidebar', 'yes')",
						$section_id
					));
					$timezone_field_id = $db->getInsertID();

					$db->query(sprintf("INSERT INTO `tbl_fields_input` VALUES(NULL, %d, NULL)", $timezone_field_id));

					// Timezone Offset Field data table
					$db->query(sprintf(
						"CREATE TABLE `tbl_entries_data_%d` (
						  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
						  `entry_id` int(11) unsigned NOT NULL,
						  `handle` varchar(255) DEFAULT NULL,
						  `value` varchar(255) DEFAULT NULL,
						  PRIMARY KEY (`id`),
						  KEY `entry_id` (`entry_id`),
						  KEY `handle` (`handle`),
						  KEY `value` (`value`)
						)",
						$timezone_field_id
					));

					// Email Field
					$db->query(sprintf(
						"INSERT INTO `tbl_fields`
						VALUES(NULL, 'Email Address', 'email-address', 'input', %d, 'yes', 1, 'main', 'yes')",
						$section_id
					));
					$email_field_id = $db->getInsertID();

					$db->query(sprintf("INSERT INTO `tbl_fields_input` VALUES(
						NULL, %d, '%s'
					)", $email_field_id, $db->cleanValue('/^\w(?:\.?[\w%+-]+)*@\w(?:[\w-]*\.)+?[a-z]{2,}$/i')));

					// Email Field data table
					$db->query(sprintf(
						"CREATE TABLE `tbl_entries_data_%d` (
						  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
						  `entry_id` int(11) unsigned NOT NULL,
						  `handle` varchar(255) DEFAULT NULL,
						  `value` varchar(255) DEFAULT NULL,
						  PRIMARY KEY (`id`),
						  KEY `entry_id` (`entry_id`),
						  KEY `handle` (`handle`),
						  KEY `value` (`value`)
						)",
						$email_field_id
					));

				}
				catch(Exception $e){
					print_r($db::getLastError());
					die();
				}

				/*
				###### MEMBERS ######
				'members' => array(
					'cookie-prefix' => 'sym-members',
					'member_section' => '11',
					'email_address_field_id' => '41',
					'timezone_offset_field_id' => '40',
				),
				########
				*/

				Symphony::Configuration()->set('member_section', $section_id, 'members');
				Symphony::Configuration()->set('email_address_field_id', $email_field_id, 'members');
				Symphony::Configuration()->set('timezone_offset_field_id', $timezone_field_id, 'members');

				Administration::instance()->saveConfig();

				redirect(Administration::instance()->getCurrentPageURL());

			}


			/*


				INSERT INTO `tbl_fields` VALUES(NULL, 'Username and Password', 'username-and-password', 'member', 7, 'yes', 0, 'main', 'yes');
				INSERT INTO `tbl_fields_member` VALUES(NULL, 25);
				CREATE TABLE `tbl_entries_data_25` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `entry_id` int(11) unsigned NOT NULL,
				  `username` varchar(50) DEFAULT NULL,
				  `password` varchar(32) DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `username` (`username`)
				);

				INSERT INTO `tbl_fields` VALUES(NULL, 'Role', 'role', 'memberrole', 7, 'no', 2, 'sidebar', 'yes');
				INSERT INTO `tbl_fields_memberrole` VALUES(NULL, 26);
				CREATE TABLE `tbl_entries_data_26` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `entry_id` int(11) unsigned NOT NULL,
				  `role_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`,`role_id`)
				);

				INSERT INTO `tbl_fields` VALUES(NULL, 'Timezone Offset', 'timezone-offset', 'input', 7, 'no', 3, 'sidebar', 'yes');
				INSERT INTO `tbl_fields_input` VALUES(NULL, 27, NULL);
				CREATE TABLE `tbl_entries_data_27` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `entry_id` int(11) unsigned NOT NULL,
				  `handle` varchar(255) DEFAULT NULL,
				  `value` varchar(255) DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `handle` (`handle`),
				  KEY `value` (`value`)
				);

				INSERT INTO `tbl_fields` VALUES(NULL, 'Email Address', 'email-address', 'input', 7, 'yes', 1, 'main', 'yes');
				INSERT INTO `tbl_fields_input` VALUES(NULL, 28, '/^\\w(?:\\.?[\\w%+-]+)*@\\w(?:[\\w-]*\\.)+?[a-z]{2,}$/i');
				CREATE TABLE `tbl_entries_data_28` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `entry_id` int(11) unsigned NOT NULL,
				  `handle` varchar(255) DEFAULT NULL,
				  `value` varchar(255) DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `handle` (`handle`),
				  KEY `value` (`value`)
				);

			*/

		}
	}

