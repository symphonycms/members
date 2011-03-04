<?php

	include_once(TOOLKIT . '/class.entrymanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');

	include_once(EXTENSIONS . '/members/lib/class.emailtemplate.php');
	include_once(EXTENSIONS . '/members/lib/class.role.php');
	include_once(EXTENSIONS . '/members/lib/class.members.php');

	Class extension_Members extends Extension {

		/**
		 * @param SymphonyMember $Member
		 */
		public $Member = null;

		/**
		 * @param integer $members_section
		 */
		public static $members_section = null;

		/**
		 * @param array $member_fields
		 */
		public static $member_fields = array(
			'memberusername'
		);

		/**
		 * @param FieldManager $fm
		 */
		public $fm = null;

		/**
		 * @param SectionManager $sm
		 */
		public $sm = null;

		/**
		 * @param EntryManager $em
		 */
		public $em = null;

		/**
		 * @param boolean $failed_login_attempt
		 */
		public static $_failed_login_attempt = false;

		/**
		 * Only create a Member object on the Frontend of the site.
		 * There is no need to create this in the Administration context
		 * as authenticated users are Authors and are handled by Symphony,
		 * not this extension.
		 */
		public function __construct() {
			if(class_exists('Frontend') && Frontend::instance()->Page() instanceof FrontendPage) {
				$this->Member = new SymphonyMember($this);
			}

			$this->fm = new FieldManager(Symphony::Engine());
			$this->sm = new SectionManager(Symphony::Engine());
			$this->em = new EntryManager(Symphony::Engine());
		}

		public function about(){
			return array(
				'name' 			=> 'Members',
				'version' 		=> '1.0 alpha',
				'release-date'	=> '2011',
				'author' => array(
					'name'		=> 'Symphony Team',
					'website'	=> 'http://www.symphony-cms.com',
					'email'		=> 'team@symphony-cms.com'
				),
				'description'	=> 'Frontend Membership extension for Symphony CMS'
			);
		}

		public function fetchNavigation(){
			$navigation = array();

			$navigation[] = array(
				'location' 	=> __('System'),
				'name' 		=> __('Member Roles'),
				'link' 		=> '/roles/'
			);

			if(!is_null(extension_Members::getConfigVar('email'))) {
				$navigation[] = array(
					'location' 	=> __('System'),
					'name' 		=> __('Member Emails'),
					'link' 		=> '/email_templates/'
				);
			}

			return $navigation;
		}

		public function getSubscribedDelegates(){
			return array(
				/*
					FRONTEND
				*/
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendPageResolved',
					'callback' => 'checkFrontendPagePermissions'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendParamsResolve',
					'callback' => 'addMemberDetailsToPageParams'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendProcessEvents',
					'callback' => 'appendLoginStatusToEventXML'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPreSaveFilter',
					'callback' => 'checkEventPermissions'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPostSaveFilter',
					'callback' => 'processEventData'
				),
				/*
					BACKEND
				*/
				array(
					'page' => '/publish/new/',
					'delegate' => 'EntryPostCreate',
					'callback' => 'emailNewMember'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'savePreferences'
				)
			);
		}

	/*-------------------------------------------------------------------------
		Versioning:
	-------------------------------------------------------------------------*/

		/**
		 * Sets the `cookie-prefix` of `sym-members` in the Configuration
		 * and creates all of the field's tables in the database
		 *
		 * @return boolean
		 */
		public function install(){

			Symphony::Configuration()->set('cookie-prefix', 'sym-members', 'members');
			Administration::instance()->saveConfig();

			return Symphony::Database()->import("
				DROP TABLE IF EXISTS `tbl_members_roles`;
				CREATE TABLE `tbl_members_roles` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `name` varchar(255) NOT NULL,
				  `handle` varchar(255) NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `handle` (`handle`)
				) ENGINE=MyISAM;

				INSERT INTO `tbl_members_roles` VALUES(1, 'Public', 'public');

				DROP TABLE IF EXISTS `tbl_members_roles_event_permissions`;
				CREATE TABLE `tbl_members_roles_event_permissions` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `role_id` int(11) unsigned NOT NULL,
				  `event` varchar(50) NOT NULL,
				  `action` varchar(60) NOT NULL,
				  `level` smallint(1) unsigned NOT NULL DEFAULT '0',
				  PRIMARY KEY (`id`),
				  KEY `role_id` (`role_id`,`event`,`action`)
				) ENGINE=MyISAM;

				DROP TABLE IF EXISTS `tbl_members_roles_forbidden_pages`;
				CREATE TABLE `tbl_members_roles_forbidden_pages` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `role_id` int(11) unsigned NOT NULL,
				  `page_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `role_id` (`role_id`,`page_id`)
				) ENGINE=MyISAM;

				DROP TABLE IF EXISTS `tbl_members_email_templates`;
				CREATE TABLE  `tbl_members_email_templates` (
					`id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`subject` VARCHAR( 255 ) NOT NULL ,
					`body` LONGTEXT NOT NULL ,
					`type` VARCHAR( 100 ) NOT NULL ,
					INDEX (`type`)
				) ENGINE=MyISAM;

				DROP TABLE IF EXISTS `tbl_members_email_templates_role_mapping`;
				CREATE TABLE  `tbl_members_email_templates_role_mapping` (
					`id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`email_template_id` INT( 11 ) UNSIGNED NOT NULL ,
					`role_id` INT( 11 ) UNSIGNED NOT NULL ,
					INDEX (  `email_template_id` ,  `role_id` )
				) ENGINE=MyISAM;
			");
		}

		/**
		 * Remove's all `members` Configuration values and then drops all the
		 * database tables created by the Members extension
		 *
		 * @return boolean
		 * @todo Missing the Email fields
		 */
		public function uninstall(){
			Symphony::Configuration()->remove('members');
			Administration::instance()->saveConfig();

			return Symphony::Database()->query("
				DROP TABLE
					`tbl_fields_memberusername`,
					`tbl_fields_memberpassword`,
					`tbl_fields_memberactivation`,
					`tbl_fields_memberrole`,
					`tbl_fields_membertimezone`,
					`tbl_members_email_templates`,
					`tbl_members_roles`,
					`tbl_members_roles_event_permissions`,
					`tbl_members_roles_forbidden_pages`,
					`tbl_members_email_templates_role_mapping`;
			");
		}

	/*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/

		public function appendPreferences($context) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Members')));

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$label = new XMLElement('label', __('Active Members Section'));

			$sections = $this->sm->fetch();
			$member_sections = array();

			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					$schema = $section->fetchFieldsSchema();

					foreach($schema as $field) {
						// Possible @todo, check for the existance of the Identity field  instead of using this array
						if(!in_array($field['type'], extension_Members::$member_fields)) continue;

						if(array_key_exists($section->get('id'), $member_sections)) continue;

						$member_sections[$section->get('id')] = $section->get();
					}
				}
			}

			$options = array();
			foreach($member_sections as $section_id => $section) {
				$options[] = array($section['id'], ($section->get['id'] == extension_Members::getMembersSection()), $section['name']);
			}

			$label->appendChild(Widget::Select('settings[members][section]', $options));

			$group->appendChild($label);

			if(!is_null(extension_Members::getMembersSection())) {
				$div = new XMLElement('div');

				$options = array();

				$fields = $this->fm->fetch(null, extension_Members::getMembersSection());

				foreach($fields as $field) {
					// Possible @todo, check that each field's validator is set to that of the email validator..
					$options[] = array($field->get('id'), ($field->get('id') == extension_Members::getConfigVar('email')), $field->get('label'));
				}

				$label = new XMLElement('label', __('Email Field'));
				$label->appendChild(Widget::Select('settings[members][email]', $options));
				$div->appendChild($label);

				$div->appendChild(
					new XMLElement('p', __('Symphony will use this field\'s value to send emails to this Member'), array('class' => 'help'))
				);

				$group->appendChild($div);
			}

			$fieldset->appendChild($group);
			$context['wrapper']->appendChild($fieldset);
		}

		public function savePreferences(){
			$settings = $_POST['settings'];

			$setting_group = 'members';

			Symphony::Configuration()->set('section', $settings['members']['section'], $setting_group);
			Symphony::Configuration()->set('email', $settings['members']['email'], $setting_group);
			Administration::instance()->saveConfig();
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public static function baseURL(){
			return SYMPHONY_URL . '/extension/members/';
		}

		public static function getConfigVar($handle) {
			$id = (int)Symphony::Configuration()->get($handle, 'members');
			return ($id == 0 ? NULL : $id);
		}

        public static function getMembersSection() {
			if(is_null(extension_Members::$members_section)) {
				extension_Members::$members_section = Symphony::Configuration()->get('section', 'members');
			}

			return extension_Members::$members_section;
        }

		public static function memberSectionHandle(){
			return Symphony::Database()->fetchVar('handle', 0, "SELECT `handle` FROM `tbl_sections` WHERE `id` = " . self::getMembersSection(). " LIMIT 1");
		}

		/**
		 * This function will adjust the locale for the currently logged in
		 * user if the active Member section has a Member: Timezone field.
		 *
		 * @param integer $member_id
		 * @return void
		 */
		public function __updateSystemTimezoneOffset($member_id) {
			if(is_null(extension_Members::getConfigVar('timezone'))) return;

			$timezone = $this->fm->fetch(extension_Members::getConfigVar('timezone'));

			if(!$timezone instanceof fieldMemberTimezone) return;

			$tz = $timezone->getMemberTimezone($member_id);

			try {
				DateTimeObj::setDefaultTimezone($tz);
			}
			catch(Exception $ex) {
				Symphony::$Log->pushToLog(__('Members Timezone') . ': ' . $ex->getMessage(), $code, true);
			}
		}

	/*-------------------------------------------------------------------------
		Role Manager:
	-------------------------------------------------------------------------*/

		public function checkFrontendPagePermissions($context) {
			$isLoggedIn = false;

			if(is_array($_REQUEST['member-action'])){
				list($action) = array_keys($_REQUEST['member-action']);
			} else {
				$action = $_REQUEST['member-action'];
			}

			if(trim($action) == 'logout') {
				$this->Member->logout();
				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);
				redirect(URL);
			}

			else if(trim($action) == 'login') {
				if($this->Member->login($_REQUEST['fields'])) {
					if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);
					redirect(URL);
				}

				self::$_failed_login_attempt = true;
			}

			else $isLoggedIn = $this->Member->isLoggedIn();

			$this->Member->initialiseMemberObject();

			if($isLoggedIn && $this->Member->Member instanceOf Entry) {
				$this->__updateSystemTimezoneOffset($this->Member->Member->get('id'));

				if(!is_null(extension_Members::getConfigVar('role'))) {
					$role_data = $this->Member->Member->getData(extension_Members::getConfigVar('role'));
				}
			}

			if(is_null(extension_Members::getConfigVar('role'))) return;

			$role_id = ($isLoggedIn) ? $role_data['role_id'] : Role::PUBLIC_ROLE;
			$role = RoleManager::fetch($role_id);

			if($role instanceof Role && !$role->canAccessPage((int)$context['page_data']['id'])) {

				// User has no access to this page, so look for a custom 403 page
				if($row = Symphony::Database()->fetchRow(0,"
					SELECT `p`.*
					FROM `tbl_pages` as `p`
					LEFT JOIN `tbl_pages_types` AS `pt` ON(`p`.id = `pt`.page_id)
					WHERE `pt`.type = '403'
				")) {

					$row['type'] = Symphony::Database()->fetchCol('type',
						"SELECT `type` FROM `tbl_pages_types` WHERE `page_id` = ".$row['id']
					);

					$row['filelocation'] = (PAGES . '/' . trim(str_replace('/', '_', $row['path'] . '_' . $row['handle']), '_') . '.xsl');

					$context['page_data'] = $row;
					return;
				}

				// No custom 403, just throw default 403
				GenericExceptionHandler::$enabled = true;
				throw new SymphonyErrorPage(
					__('Please <a href="%s">login</a> to view this page.', array(SYMPHONY_URL . '/login/')),
					__('Forbidden'),
					'error',
					array('header' => 'HTTP/1.0 403 Forbidden')
				);
			}
		}

	/*-------------------------------------------------------------------------
		Email Templates:
	-------------------------------------------------------------------------*/

		public function emailNewMember($context){
			if($context['section']->get('id') == extension_Members::getMembersSection()) {
				$this->sendNewRegistrationEmail($context['entry'], $context['fields']);
			}
		}

		public function sendNewRegistrationEmail(Entry $entry, Array $fields = array()){
			if(!$role = RoleManager::fetch($entry->getData(extension_Members::getConfigVar('role'))->role_id)) return;

			return $this->Member->sendNewRegistrationEmail($entry, $role, $fields);
		}

		public function sendNewPasswordEmail($member_id){
			$entry = $this->Member->Member->fetchMemberFromID($member_id);

			if(!$entry instanceof Entry) throw new Exception('Invalid member ID specified');

			if(!$role = RoleManager::fetch($entry->getData(extension_Members::getConfigVar('role'))->role_id)) return;

			return $this->Member->sendNewPasswordEmail($entry, $role);
		}

		public function sendResetPasswordEmail($member_id){
			$entry = $this->Member->Member->fetchMemberFromID($member_id);

			if(!$entry instanceof Entry) throw new Exception('Invalid member ID specified');

			if(!$role = RoleManager::fetch($entry->getData(extension_Members::getConfigVar('role'))->role_id)) return;

			return $this->Member->sendResetPasswordEmail($entry, $role);
		}

	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		public function processEventData($context){
			if($context['event']->getSource() == extension_Members::getMembersSection() && isset($_POST['action']['members-register'])){
				return $this->sendNewRegistrationEmail($context['entry'], $context['fields']);
			}
		}

		public function checkEventPermissions($context){
			/**
			 * If this system has no Roles, return immediately.
			 * @todo Possibly look at dynamically subscribing to this delegate
			 * when a Role field is added.
			 */
			if(is_null(extension_Members::getConfigVar('role'))) return;

			$action = 'create';
			$entry_id = 0;

			if(isset($_POST['id'])){
				$entry_id = (int)$_POST['id'];
				$action = 'edit';
			}

			$isLoggedIn = $this->Member->isLoggedIn();

			$this->Member->initialiseMemberObject();

			if($isLoggedIn && $this->Member->Member instanceOf Entry) {
				$role_data = $this->Member->Member->getData(extension_Members::getConfigVar('role'));
			}

			$role_id = ($isLoggedIn) ? $role_data['role_id'] : Role::PUBLIC_ROLE;
			$role = RoleManager::fetch($role_id);

			$event_handle = strtolower(preg_replace('/^event/i', NULL, get_class($context['event'])));

			$isOwner = false;
			$field_id = false;
			$required_level = ($isLoggedIn) ? EventPermissions::OWN_ENTRIES : EventPermissions::ALL_ENTRIES;

			if($action == 'edit' && $isLoggedIn) {
				$section_id = $context['event']->getSource();
				$member_id = false;

				// If the $section_id = members_section
				if($section_id == extension_Members::getMembersSection()) {
					$field_id = extension_Members::getConfigVar('authentication');
				}
				// Or a SBL/RL field links to the identity field
				// @todo check this is working as expected
				else {
					$field_id = Symphony::Database()->fetchVar('child_section_field_id', 0, sprintf("
							SELECT `child_section_field_id`
							FROM `tbl_sections_association`
							WHERE `parent_section_id` = %d
							AND `parent_section_field_id` = %d
							AND `child_section_id` = %d
						",
						extension_Members::getMembersSection(),
						extension_Members::getConfigVar('authentication'),
						$section_id
					));
				}

				// check that logged in member id == $entry_id
				if($field_id) {
					$member_id = Symphony::Database()->fetchVar('entry_id', 0, sprintf(
						"SELECT * FROM `tbl_entries_data_%d` WHERE `entry_id` = %d LIMIT 1",
						$field_id, $entry_id
					));
				}

				// if logged in member is the same as the member id for the Entry we are editing
				// then this user is the Owner, and can modify EventPermissions::OWN_ENTRIES
				$isOwner = ($this->Member->Member->get('id') == $member_id);

				// User is not the owner, so they can edit EventPermissions::ALL_ENTRIES
				if($isOwner === false) $required_level = EventPermissions::ALL_ENTRIES;
			}

			$success = $role->canProcessEvent($event_handle, $action, $required_level) ? true : false;

			$context['messages'][] = array(
				'permission',
				$success,
				($success === false) ? __('You are not authorised to perform this action') : null
			);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function addMemberDetailsToPageParams(Array $context = null) {
			$this->Member->addMemberDetailsToPageParams($context);
		}

		public function appendLoginStatusToEventXML(Array $context = null){
			$this->Member->appendLoginStatusToEventXML($context);
		}

	}

