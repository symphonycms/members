<?php

	include_once(TOOLKIT . '/class.entrymanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');

	include_once(EXTENSIONS . '/members/lib/class.role.php');
	include_once(EXTENSIONS . '/members/lib/class.members.php');

	Class extension_Members extends Extension {

		/**
		 * @var SymphonyMember $Member
		 */
		public $Member = null;

		/**
		 * @var integer $members_section
		 */
		public static $members_section = null;

		/**
		 * @var array $member_fields
		 */
		public static $member_fields = array(
			'memberusername', 'memberemail'
		);

		/**
		 * @var boolean $failed_login_attempt
		 */
		public static $_failed_login_attempt = false;

		/**
		 * @var boolean $_errors
		 */
		public static $_errors = array();

		/**
		 * @var array $fields
		 */
		public static $fields = array();

		/**
		 * @var array $handles
		 */
		public static $handles = array();

		/**
		 * @var boolean $initialised
		 */
		public static $initialised = false;

		/**
		 * @var SectionManager $sm
		 */
		public $sm = null;

		/**
		 * @var EntryManager $em
		 */
		public $em = null;

		/**
		 * Only create a Member object on the Frontend of the site.
		 * There is no need to create this in the Administration context
		 * as authenticated users are Authors and are handled by Symphony,
		 * not this extension.
		 */
		public function __construct() {

			if(class_exists('Symphony') && Symphony::Engine() instanceof Frontend) {
				$this->Member = new SymphonyMember($this);
			}

			$this->sm = new SectionManager(Symphony::Engine());
			$this->em = new EntryManager(Symphony::Engine());

			if(!extension_Members::$initialised) {
				extension_Members::initialise();
			}
		}

		/**
		 * Loops over the configuration to detect the capabilities of this
		 * Members setup. Populates two array's, one for Field objects, and
		 * one for Field handles.
		 */
		public static function initialise() {
			extension_Members::$initialised = true;
			$fieldManager = new FieldManager(Symphony::Engine());

			if(!is_null(extension_Members::getConfigVar('timezone'))) {
				extension_Members::$fields['timezone'] = $fieldManager->fetch(
					extension_Members::getConfigVar('timezone')
				);

				if(extension_Members::$fields['timezone'] instanceof Field) {
					extension_Members::$handles['timezone'] = extension_Members::$fields['timezone']->get('element_name');
				}
			}

			if(!is_null(extension_Members::getConfigVar('role'))) {
				extension_Members::$fields['role'] = $fieldManager->fetch(
					extension_Members::getConfigVar('role')
				);

				if(extension_Members::$fields['role'] instanceof Field) {
					extension_Members::$handles['role'] = extension_Members::$fields['role']->get('element_name');
				}
			}

			if(!is_null(extension_Members::getConfigVar('activation'))) {
				extension_Members::$fields['activation'] = $fieldManager->fetch(
					extension_Members::getConfigVar('activation')
				);

				if(extension_Members::$fields['activation'] instanceof Field) {
					extension_Members::$handles['activation'] = extension_Members::$fields['activation']->get('element_name');
				}
			}

			if(!is_null(extension_Members::getConfigVar('identity'))) {
				extension_Members::$fields['identity'] = $fieldManager->fetch(
					extension_Members::getConfigVar('identity')
				);

				if(extension_Members::$fields['identity'] instanceof Field) {
					extension_Members::$handles['identity'] = extension_Members::$fields['identity']->get('element_name');
				}
			}

			if(!is_null(extension_Members::getConfigVar('email'))) {
				extension_Members::$fields['email'] = $fieldManager->fetch(
					extension_Members::getConfigVar('email')
				);

				if(extension_Members::$fields['email'] instanceof Field) {
					extension_Members::$handles['email'] = extension_Members::$fields['email']->get('element_name');
				}
			}

			if(!is_null(extension_Members::getConfigVar('authentication'))) {
				extension_Members::$fields['authentication'] = $fieldManager->fetch(
					extension_Members::getConfigVar('authentication')
				);

				if(extension_Members::$fields['authentication'] instanceof Field) {
					extension_Members::$handles['authentication'] = extension_Members::$fields['authentication']->get('element_name');
				}
			}
		}

		public function about(){
			return array(
				'name' 			=> 'Members',
				'version' 		=> '1.0 Beta 2',
				'release-date'	=> 'March 28th 2011',
				'author' => array(
					'name'		=> 'Symphony Team',
					'website'	=> 'http://www.symphony-cms.com',
					'email'		=> 'team@symphony-cms.com'
				),
				'description'	=> 'Frontend Membership extension for Symphony CMS'
			);
		}

		public function fetchNavigation(){
			return array(
				array(
					'location' 	=> __('System'),
					'name' 		=> __('Member Roles'),
					'link' 		=> '/roles/'
				)
			);
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
					'callback' => 'processPostSaveFilter'
				),
				/*
					BACKEND
				*/
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'appendPreferences'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'Save',
					'callback'	=> 'savePreferences'
				),
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
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
			");
		}

		/**
		 * Remove's all `members` Configuration values and then drops all the
		 * database tables created by the Members extension
		 *
		 * @return boolean
		 */
		public function uninstall(){
			Symphony::Configuration()->remove('members');
			Administration::instance()->saveConfig();

			return Symphony::Database()->query("
				DROP TABLE IF EXISTS
					`tbl_fields_memberusername`,
					`tbl_fields_memberpassword`,
					`tbl_fields_memberemail`,
					`tbl_fields_memberactivation`,
					`tbl_fields_memberrole`,
					`tbl_fields_membertimezone`,
					`tbl_members_roles`,
					`tbl_members_roles_event_permissions`,
					`tbl_members_roles_forbidden_pages`
			");
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public static function baseURL(){
			return SYMPHONY_URL . '/extension/members/';
		}

		public static function getConfigVar($handle) {
			$value = Symphony::Configuration()->get($handle, 'members');
			return ((is_numeric($value) && $value == 0) || is_null($value) || empty($value)) ? NULL : $value;
		}

		public static function getMembersSection() {
			if(is_null(extension_Members::$members_section)) {
				extension_Members::$members_section = extension_Members::getConfigVar('section');
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
			$timezone = extension_Members::$fields['timezone'];

			if(!$timezone instanceof fieldMemberTimezone) return;

			$tz = $timezone->getMemberTimezone($member_id);

			if(is_null($tz)) return;

			try {
				DateTimeObj::setDefaultTimezone($tz);
			}
			catch(Exception $ex) {
				Symphony::$Log->pushToLog(__('Members Timezone') . ': ' . $ex->getMessage(), $code, true);
			}
		}

		/**
		 * The Members extension provides a number of filters for users to add their
		 * events to do various functionality. This negates the need for custom events
		 *
		 * @uses AppendEventFilter
		 */
		public function appendFilter($context) {
			$selected = !is_array($context['selected']) ? array() : $context['selected'];

			// Add Member: Register filter
			$context['options'][] = array(
				'member-register',
				in_array('member-register', $selected),
				__('Members: Register')
			);

			if(!is_null(extension_Members::getConfigVar('activation')) && !is_null(extension_Members::getConfigVar('email'))) {
				// Add Member: Activation filter
				$context['options'][] = array(
					'member-activation',
					in_array('member-activation', $selected),
					__('Members: Activation')
				);
			}

			if(!is_null(extension_Members::getConfigVar('authentication'))) {
				// Add Member: Update Password filter
				$context['options'][] = array(
					'member-update-password',
					in_array('member-update-password', $selected),
					__('Members: Update Password')
				);
			}
		}

	/*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/

		/**
		 * Allows a user to select which section they would like to use as their
		 * active members section. This allows developers to build multiple sections
		 * for migration during development.
		 *
		 * @uses AddCustomPreferenceFieldsets
		 * @todo Look at how this could be expanded so users can log into multiple
		 * sections. This is not in scope for 1.0
		 */
		public function appendPreferences($context) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Members')));

			$group = new XMLElement('div', null, array('class' => 'group'));

			$label = new XMLElement('label', __('Active Members Section'));

			$sections = $this->sm->fetch();
			$member_sections = array();
			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					$schema = $section->fetchFieldsSchema();

					foreach($schema as $field) {
						if(!in_array($field['type'], extension_Members::$member_fields)) continue;

						if(array_key_exists($section->get('id'), $member_sections)) continue;

						$member_sections[$section->get('id')] = $section->get();
					}
				}
			}

			$options = array();
			$options[] = array(null, false, null);
			foreach($member_sections as $section_id => $section) {
  				$options[] = array($section_id, ($section_id == extension_Members::getMembersSection()), $section['name']);
			}

			$label->appendChild(Widget::Select('settings[members][section]', $options));

			if(count($options) == 1) {
				$label->appendChild(
					new XMLElement('p', __('A Members section will at minimum contain either a Member: Email or a Member: Username field'), array('class' => 'help'))
				);
			}

			$group->appendChild($label);

			$label = new XMLElement('label', __('Reset Password Email Template'));
			$options = array();

			// Email Template Filter
			// @link http://symphony-cms.com/download/extensions/view/20743/
			try {
				$driver = Symphony::ExtensionManager()->getInstance('emailtemplatefilter');
				if($driver instanceof Extension) {
					$templates = $driver->getTemplates();

					$g = array('label' => __('Email Template Filter'));
					$group_options = array();

					foreach($templates as $template) {
						$group_options[] = array('etf-'.$template['id'], ('etf-'.$template['id'] == extension_Members::getConfigVar('reset-password-template')), $template['name']);
					}
					$g['options'] = $group_options;

					if(!empty($g['options'])) {
						$options[] = $g;
					}
				}
			}
			catch(Exception $ex) {

			}

			// Email Template Manager
			// @link http://symphony-cms.com/download/extensions/view/64322/
			try {
				$handles = Symphony::ExtensionManager()->listInstalledHandles();
				if(in_array('email_template_manager', $handles)){
					if(file_exists(EXTENSIONS . '/email_template_manager/lib/class.emailtemplatemanager.php') && !class_exists("EmailTemplateManager")) {
						include_once(EXTENSIONS . '/email_template_manager/lib/class.emailtemplatemanager.php');
					}

					if(class_exists("EmailTemplateManager")){
						$templates = EmailTemplateManager::listAll();

						$g = array('label' => __('Email Template Manager'));
						$group_options = array();

						foreach($templates as $template) {
							$group_options[] = array('etm-'.$template->getHandle(), ('etm-'.$template->getHandle() == extension_Members::getConfigVar('reset-password-template')), $template->getName());
						}

						$g['options'] = $group_options;

						if(!empty($g['options'])) {
							$options[] = $g;
						}
					}
				}
			}
			catch(Exception $ex) {
			}

			// Only append if there is any Templates.
			if(!empty($options)) {
				$label->appendChild(Widget::Select('settings[members][reset-password-template]', $options));
				$group->appendChild($label);
			}

			$fieldset->appendChild($group);
			$context['wrapper']->appendChild($fieldset);
		}

		/**
		 * Saves the Member Section to the configuration
		 *
		 * @uses savePreferences
		 */
		public function savePreferences(){
			$settings = $_POST['settings'];

			Symphony::Configuration()->set('section', $settings['members']['section'], 'members');
			Symphony::Configuration()->set('section', $settings['members']['reset-password-template'], 'members');
			Administration::instance()->saveConfig();
		}

	/*-------------------------------------------------------------------------
		Role Manager:
	-------------------------------------------------------------------------*/

		public function checkFrontendPagePermissions($context) {
			$isLoggedIn = false;
			$errors = array();

			// Checks $_REQUEST to see if a Member Action has been requested,
			// member-action['login'] and member-action['logout']/?member-action=logout
			// are the only two supported at this stage.
			if(is_array($_REQUEST['member-action'])){
				list($action) = array_keys($_REQUEST['member-action']);
			} else {
				$action = $_REQUEST['member-action'];
			}

			// Check to see a Member is already logged in.
			$isLoggedIn = $this->Member->isLoggedIn($errors);

			// Logout
			if(trim($action) == 'logout') {
				$this->Member->logout();
				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);
			}

			// Login
			else if(trim($action) == 'login' && !is_null($_POST['fields'])) {
				// If a Member is already logged in and another Login attempt is requested
				// log the Member out first before trying to login with new details.
				if($isLoggedIn) {
					$this->Member->logout();
				}

				if($this->Member->login($_POST['fields'])) {
					if(isset($_POST['redirect'])) redirect($_POST['redirect']);
				}
				else {
					self::$_failed_login_attempt = true;
				}
			}

			$this->Member->initialiseMemberObject();

			if($isLoggedIn && $this->Member->Member instanceOf Entry) {
				$this->__updateSystemTimezoneOffset($this->Member->Member->get('id'));

				if(!is_null(extension_Members::getConfigVar('role'))) {
					$role_data = $this->Member->Member->getData(extension_Members::getConfigVar('role'));
				}
			}

			// If there is no role field, or a Developer is logged in, return, as Developers
			// should be able to access every page.
			if(
				is_null(extension_Members::getConfigVar('role'))
				|| (Frontend::instance()->Author instanceof Author && Frontend::instance()->Author->isDeveloper())
			) return;

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
					$row['type'] = FrontendPage::fetchPageTypes($row['id']);
					$row['filelocation'] = FrontendPage::resolvePageFileLocation($row['path'], $row['handle']);

					$context['page_data'] = $row;
					return;
				}
				else {
					// No custom 403, just throw default 403
					GenericExceptionHandler::$enabled = true;
					throw new SymphonyErrorPage(
						__('The page you have requested has restricted access permissions.'),
						__('Forbidden'),
						'error',
						array('header' => 'HTTP/1.0 403 Forbidden')
					);
				}
			}
		}

	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		/**
		 * This function will ensure that the user who has submitted the form (and
		 * hence is requesting that an event be triggered) is actually allowed to
		 * do this request.
		 * There are 2 action types, creation and editing. Creation is a simple yes/no
		 * affair, whereas editing has three levels of permission, None, Own Entries
		 * or All Entries:
		 * - None: This user can't do process this event
		 * - Own Entries: If the entry the user is trying to update is their own
		 *   determined by if the `entry_id` or, in the case of a SBL or
		 *   similar field, the `entry_id` of the linked entry matches the logged in
		 *   user's id, process the event.
		 * - All Entries: The user can update any entry in Symphony.
		 * If there are no Roles in this system, or the event is set to ignore permissions
		 * (by including a function, `ignoreRolePermissions` that returns `true`, it will
		 * immediately proceed to processing any of the Filters attached to the event
		 * before returning.
		 *
		 * @uses checkEventPermissions
		 */
		public function checkEventPermissions(Array &$context){
			// If this system has no Roles, or the event is set to ignore role permissions
			// continue straight to processing the Filters
			if(
				is_null(extension_Members::getConfigVar('role')) ||
				(method_exists($context['event'], 'ignoreRolePermissions') && $context['event']->ignoreRolePermissions() == true)
			) {
				return $this->__processEventFilters($context);
			}

			if(isset($_POST['id'])){
				$entry_id = (int)$_POST['id'];
				$action = 'edit';
			}
			else {
				$action = 'create';
				$entry_id = 0;
			}

			$required_level = $action == 'create' ? EventPermissions::OWN_ENTRIES : EventPermissions::ALL_ENTRIES;
			$role_id = Role::PUBLIC_ROLE;

			$isLoggedIn = $this->Member->isLoggedIn();

			if($isLoggedIn && $this->Member->initialiseMemberObject()) {
				if($this->Member->Member instanceOf Entry) {
					$required_level = EventPermissions::OWN_ENTRIES;
					$role_data = $this->Member->Member->getData(extension_Members::getConfigVar('role'));
					$role_id = $role_data['role_id'];

					if($action == 'edit' && method_exists($context['event'], 'getSource')) {
						$section_id = $context['event']->getSource();
						$member_id = false;

						// If the event is the same section as the Members section, then for `$isOwner`
						// to be true, the `$entry_id` must match the currently logged in user.
						if($section_id == extension_Members::getMembersSection()) {
							$member_id = $entry_id;
						}

						// If the $section_id !== members_section, check the section associations table
						// for any links to either of the Member fields that my be used for linking,
						// that is the Username or Email field.
						else {
							$field_id = array();

							// Get the ID's of the fields that may be used for Linking (Username/Email)
							if(!is_null(extension_Members::getConfigVar('identity'))) {
								$field_ids[] = extension_Members::getConfigVar('identity');
							}

							if(!is_null(extension_Members::getConfigVar('email'))) {
								$field_ids[] = extension_Members::getConfigVar('email');
							}

							// Query for the `field_id` of any linking fields that link to the members
							// section AND to one of the linking fields (Username/Email)
							$field_id = Symphony::Database()->fetchVar('child_section_field_id', 0, sprintf("
									SELECT `child_section_field_id`
									FROM `tbl_sections_association`
									WHERE `parent_section_id` = %d
									AND `child_section_id` = %d
									AND `parent_section_field_id` IN ('%s')
								",
								extension_Members::getMembersSection(),
								$section_id,
								implode("','", $field_ids)
							));

							// If there was a link found, get the `relation_id`, which is the `member_id` of
							// an entry in the active Members section.
							if($field_id) {
								$member_id = Symphony::Database()->fetchVar('relation_id', 0, sprintf(
									"SELECT `relation_id` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d LIMIT 1",
									$field_id, $entry_id
								));
							}
						}

						// Check the logged in member is the same as the `$member_id` for the Entry we are editing
						// then this user is the Owner, and can modify EventPermissions::OWN_ENTRIES
						$isOwner = ($this->Member->Member->get('id') == $member_id);

						// User is not the owner, so they can edit EventPermissions::ALL_ENTRIES
						if($isOwner === false) $required_level = EventPermissions::ALL_ENTRIES;
					}
				}
			}

			try {
				$role = RoleManager::fetch($role_id);
				$event_handle = strtolower(preg_replace('/^event/i', NULL, get_class($context['event'])));
				$success = $role->canProcessEvent($event_handle, $action, $required_level) ? true : false;

				$context['messages'][] = array(
					'permission',
					$success,
					($success === false) ? __('You are not authorised to perform this action') : null
				);
			}
			catch (Exception $ex) {
				// Unsure of what the possible Exceptions would be here, so lets
				// just throw for now for the sake of discovery.
				throw new $ex;
			}

			// Process the Filters for this event.
			return $this->__processEventFilters($context);
		}

		/**
		 * We can safely assume at this stage of the process that whatever user has
		 * requested this event has permission to actually do so.
		 */
		private function __processEventFilters(Array &$context) {
			// Process the Member Register
			if (in_array('member-register', $context['event']->eParamFILTERS)) {
				$this->Member->filter_Register(&$context);
			}

			// Process the Member Activation
			if (in_array('member-activation', $context['event']->eParamFILTERS)) {
				$this->Member->filter_Activation(&$context);
			}

			// Process updating a Member's Password
			if (in_array('member-update-password', $context['event']->eParamFILTERS)) {
				$this->Member->filter_UpdatePassword(&$context);
			}
		}

		/**
		 * Any post save behaviour
		 *
		 * @uses EventPostSaveFilter
		 */
		public function processPostSaveFilter(Array &$context) {
			// Process updating a Member's Password
			if (in_array('member-update-password', $context['event']->eParamFILTERS)) {
				$this->Member->filter_UpdatePasswordLogin($context);
			}
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
