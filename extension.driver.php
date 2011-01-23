<?php

	include_once(TOOLKIT . '/class.entrymanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');

	include_once(EXTENSIONS . '/members/lib/class.emailtemplate.php');
	include_once(EXTENSIONS . '/members/lib/class.role.php');
	include_once(EXTENSIONS . '/members/lib/class.members.php');

	Class extension_Members extends Extension {

		public $Member = null;
		public static $_failed_login_attempt = false;
		public static $members_section = null;

		public static $debug = false;

		const CODE_EXPIRY_TIME = 3600; // 1 hour
		const GUEST_ROLE_ID = 1;
		const INACTIVE_ROLE_ID = 2;

		public function __construct() {
			if(class_exists('Frontend')) {
				$this->Member = new SymphonyMember($this);
			}
		}

		public function about(){
			return array(
				'name' 			=> 'Members',
				'version' 		=> '1.3 alpha',
				'release-date'	=> '2010',
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
					'location' => 300,
					'name' => __('Members'),
					'children' => array(
						array(
							'name' => __('Roles'),
							'link' => '/roles/'
						),
						array(
							'name' => __('Email Templates'),
							'link' => '/email_templates/'
						)
					)
				)
			);
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendPageResolved', //'FrontendProcessEvents',
					'callback' => 'cbCheckFrontendPagePermissions'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendParamsResolve',
					'callback' => 'cbAddMemberDetailsToPageParams'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendProcessEvents',
					'callback' => 'appendLoginStatusToEventXML'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPostSaveFilter',
					'callback' => 'processEventData'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPreSaveFilter',
					'callback' => 'checkEventPermissions'
				),
				array(
					'page' => '/publish/new/',
					'delegate' => 'EntryPostCreate',
					'callback' => 'cbEmailNewMember'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => '__SavePreferences'
				),
			);
		}

	/*-------------------------------------------------------------------------
		Versioning:
	-------------------------------------------------------------------------*/

		public function update($previous_version=false) {
			if($previous_version == '1.0'){
				Symphony::Database()->query("ALTER TABLE `sym_fields_memberlink` ADD  `allow_multiple` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no'");
			}

			// Holy hell there is going to need to be alot of logic here ;)
		}

		public function install(){

			Symphony::Configuration()->set('cookie-prefix', 'sym-members', 'members');
			Administration::instance()->saveConfig();

			Symphony::Database()->import("

				CREATE TABLE IF NOT EXISTS `tbl_fields_member` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `validator` varchar(255) DEFAULT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM;

				CREATE TABLE IF NOT EXISTS `tbl_fields_memberpassword` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `length` tinyint(2) NOT NULL,
				  `strength` enum('weak', 'good', 'strong') NOT NULL,
				  `salt` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM;

				CREATE TABLE IF NOT EXISTS `tbl_fields_memberlink` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `allow_multiple` enum('yes','no') NOT NULL default 'no',
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM;

				CREATE TABLE IF NOT EXISTS `tbl_fields_memberrole` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM;

				DROP TABLE IF EXISTS `tbl_members_codes`;
				CREATE TABLE `tbl_members_codes` (
				  `member_id` int(11) unsigned NOT NULL,
				  `code` varchar(32)  NOT NULL,
				  `expiry` int(11) NOT NULL,
				  PRIMARY KEY  (`member_id`),
				  KEY `code` (`code`)
				) ENGINE=MyISAM;

				DROP TABLE IF EXISTS `tbl_members_roles`;
				CREATE TABLE `tbl_members_roles` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `name` varchar(60)  NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `name` (`name`)
				) ENGINE=MyISAM;

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

				INSERT INTO `tbl_members_roles` VALUES (1, 'Guest');
				INSERT INTO `tbl_members_roles` VALUES (2, 'Inactive');
			");

		}

		public function uninstall(){
			Symphony::Configuration()->remove('members');
			Administration::instance()->saveConfig();
			Symphony::Database()->query(
				"DROP TABLE
					`tbl_members_email_templates`,
					`tbl_members_codes`,
					`tbl_members_roles`,
					`tbl_members_roles_event_permissions`,
					`tbl_members_roles_forbidden_pages`,
					`tbl_members_email_templates_role_mapping`;"
			);
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

        private static function getMembersSection() {
            if(is_null(self::$members_section)) {
                $fm = new FieldManager($this->_Parent);
                $fields = $fm->fetch(NULL, NULL, 'ASC', 'sortorder', 'member');
                $field = current($fields);
                if($field instanceof Identity) {
                    self::$members_section = $field->get('parent_section');
                }
            }

            return self::$members_section;
        }

		public static function roleField(){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = ".self::getMembersSection()." AND `type` = 'memberrole' LIMIT 1");
		}

		public static function memberSectionHandle(){
			return Symphony::Database()->fetchVar('handle', 0, "SELECT `handle` FROM `tbl_sections` WHERE `id` = " . self::getMembersSection(). " LIMIT 1");
		}

		public function __updateSystemTimezoneOffset() {
			$offset = Symphony::Database()->fetchVar('value', 0, sprintf("
					SELECT `value`
					FROM `tbl_entries_data_%d`
					WHERE `entry_id` = '%s'
					LIMIT 1
				", self::getConfigVar('timezone_offset_field_id'), Symphony::Database()->cleanValue($this->Member->Member->get('id'))
			));

			if(strlen(trim($offset)) == 0) return;

			//When using 'Etc/GMT...' the +/- signs are reversed. E.G. GMT+10 == Etc/GMT-10
			DateTimeObj::setDefaultTimezone('Etc/GMT' . ($offset >= 0 ? '-' : '+') . abs($offset));
		}

	/*-------------------------------------------------------------------------
		Roles:
	-------------------------------------------------------------------------*/

		public static function fetchRole($role_id, $include_permissions=false){
			if(!$row = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_roles` WHERE `id` = $role_id LIMIT 1")) return;

			$forbidden_pages = array();
			$event_permissions = array();

			if($include_permissions) self::rolePermissions($role_id, $event_permissions, $forbidden_pages);

			return new Role($row['id'], $row['name'], $event_permissions, $forbidden_pages);
		}

		public static function fetchRoles($include_permissions=false){
			if(!$rows = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles` ORDER BY `id` ASC")) return;

			$roles = array();

			foreach($rows as $r){
				$forbidden_pages = array();
				$event_permissions = array();

				if($include_permissions) self::rolePermissions($r['id'], $event_permissions, $forbidden_pages);

				$roles[] = new Role($r['id'], $r['name'], $event_permissions, $forbidden_pages);
			}

			return $roles;
		}

		public static function rolePermissions($role_id, &$event_permissions, &$forbidden_pages) {
			$forbidden_pages = Symphony::Database()->fetchCol('page_id', "SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = " . $role_id);

			$tmp = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = " . $role_id);
			if(is_array($tmp) && !empty($tmp)) foreach($tmp as $e) {
				$event_permissions[$e['event']][$e['action']] = $e['level'];
			}
		}

		public static function roleExists($name){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_members_roles` WHERE `name` = '{$name}' LIMIT 1");
		}

		public static function buildRolePermissionTableBody(Array $rows){
			$array = array();
			foreach($rows as $r){
				$array[] = self::buildRolePermissionTableRow($r[0], $r[1], $r[2], $r[3]);
			}
			return $array;
		}

		public static function buildRolePermissionTableRow($label, $event, $handle, $checked=false){
			$td1 = Widget::TableData($label);
			$td2 = Widget::TableData(Widget::Input('fields[permissions]['.$event.']['.$handle.']', 'yes', 'checkbox', ($checked === true ? array('checked' => 'checked') : NULL)));
			return Widget::TableRow(array($td1, $td2));
		}

		public function cbCheckFrontendPagePermissions($context) {
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);

			## Cookies only show up on page refresh. This flag helps in making sure the correct XML is being set
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
				if($this->Member->login(array(
					'username' => Symphony::Database()->cleanValue($_REQUEST['username']),
					'password' => Symphony::Database()->cleanValue($_REQUEST['password'])
					))
				) {
					if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);
					redirect(URL);
				}

				self::$_failed_login_attempt = true;
			}

			else $isLoggedIn = $this->Member->isLoggedIn();

			$this->Member->initialiseMemberObject();

			if($isLoggedIn && $this->Member->Member instanceOf Entry) {
				$role_data = $this->Member->Member->getData(self::roleField());
				$this->__updateSystemTimezoneOffset();
			}

			$role = self::fetchRole(($isLoggedIn ? $role_data['role_id'] : self::GUEST_ROLE_ID), true);

			if(!$role->canAccessPage((int)$context['page_data']['id'])) {

				if($row = Symphony::Database()->fetchRow(0,
					"SELECT `tbl_pages`.* FROM `tbl_pages`, `tbl_pages_types`
					WHERE `tbl_pages_types`.page_id = `tbl_pages`.id AND tbl_pages_types.`type` = '403'
					LIMIT 1")){

					$row['type'] = Symphony::Database()->fetchCol('type',
						"SELECT `type` FROM `tbl_pages_types` WHERE `page_id` = ".$row['id']
					);

					$row['filelocation'] = (PAGES . '/' . trim(str_replace('/', '_', $row['path'] . '_' . $row['handle']), '_') . '.xsl');

					$context['page_data'] = $row;
					return;
				}

				throw new SymphonyErrorPage(
					'Please <a href="'.URL.'/symphony/login/">login</a> to view this page.',
					'Forbidden', 'error',
					array('header' => 'HTTP/1.0 403 Forbidden')
				);

			}
		}

	/*-------------------------------------------------------------------------
		Email Templates:
	-------------------------------------------------------------------------*/

		public static function generateCode($member_id){

			## First check if a code already exists
			$code = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_codes` WHERE `member_id` = '$member_id' AND `expiry` > ".time()." LIMIT 1");

			if(is_array($code) && !empty($code)) return $code['code'];

			## Generate a code
			do{
				$code = md5(time().rand(0,100000));
				$row = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_codes` WHERE `code` = '{$code}'");
			} while(is_array($row) && !empty($row));

			Symphony::Database()->insert(
				array(
					'member_id' => $member_id,
					'code' => $code,
					'expiry' => (time() + self::CODE_EXPIRY_TIME)
				),
				'tbl_members_codes', true
			);

			return $code;
		}

		public static function purgeCodes($member_id=NULL){
			Symphony::Database()->query("DELETE FROM `tbl_members_codes` WHERE `expiry` <= ".time().($member_id ? " OR `member_id` = '$member_id'" : NULL));
		}

		public function fetchEmailTemplates(){
			$rows = Symphony::Database()->fetchCol('id', 'SELECT `id` FROM `tbl_members_email_templates` ORDER BY `id` ASC');
			$result = array();
			foreach($rows as $id) {
				$result[] = EmailTemplate::loadFromID($id);
			}
			return $result;
		}

		public function cbEmailNewMember($context){
			if($context['section']->get('handle') == self::memberSectionHandle()) return $this->emailNewMember($context);
		}

		public function emailNewMember($context){
			var_dump($context, __FUNCTION__);
			return $this->sendNewRegistrationEmail($context['entry'], $context['fields']);
		}

		public function sendNewRegistrationEmail(Entry $entry, Array $fields = array()){

			if(!$role = self::fetchRole($entry->getData(self::roleField(), true)->role_id)) return;
			var_dump(__FUNCTION__);
			return $this->Member->sendNewRegistrationEmail($entry, $role, $fields);
		}

		public function sendNewPasswordEmail($member_id){

			$entry = $this->Member->Member->fetchMemberFromID($member_id);

			if(!$entry instanceof Entry) throw new UserException('Invalid member ID specified');

			if(!$role = self::fetchRole($entry->getData(self::roleField(), true)->role_id)) return;

			return $this->Member->sendNewPasswordEmail($entry, $role);
		}

		public function sendResetPasswordEmail($member_id){

			$entry = $this->Member->Member->fetchMemberFromID($member_id);

			if(!$entry instanceof Entry) throw new UserException('Invalid member ID specified');

			if(!$role = self::fetchRole($entry->getData(self::roleField(), true)->role_id)) return;

			return $this->Member->sendResetPasswordEmail($entry, $role);
		}

	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		public function processEventData($context){
			if($context['event']->getSource() == self::getConfigVar('member_section') && isset($_POST['action']['members-register'])){
				return $this->sendNewRegistrationEmail($context['entry'], $context['fields']);
			}
		}

		public function checkEventPermissions($context){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);
			$action = 'create';
			$required_level = 1;
			$entry_id = NULL;

			if(isset($_POST['id'])){
				$entry_id = (int)$_POST['id'];
				$action = 'edit';
			}

			$isLoggedIn = $this->Member->isLoggedIn();

			$this->Member->initialiseMemberObject();

			if($isLoggedIn && $this->Member->Member instanceOf Entry) {
				$role_data = $this->Member->Member->getData(self::roleField());
			}

			$role = self::fetchRole(($isLoggedIn ? $role_data['role_id'] : self::GUEST_ROLE_ID), true);

			$event_handle = strtolower(preg_replace('/^event/i', NULL, get_class($context['event'])));

			$isOwner = false;

			if($action == 'edit'){
				$section_id = $context['event']->getSource();

				$member_field = Symphony::Database()->fetchRow(0,
					"SELECT * FROM `tbl_fields` WHERE `parent_section` = {$section_id} AND `type` IN ('memberlink', 'member') LIMIT 1"
				);

				$member_id = Symphony::Database()->fetchVar(
					($member_field['type'] == 'memberlink') ? 'member_id' : 'entry_id', 0,
					sprintf("SELECT * FROM `tbl_entries_data_%d` WHERE `entry_id` = %d LIMIT 1", $member_field['id'], $entry_id)
				);

				$isOwner = ($isLoggedIn) ? ((int)$this->Member->Member->get('id') == $member_id) : false;

				if($isOwner != true) $required_level = 2;
			}

			$success = $role->canPerformEventAction($event_handle, $action, $required_level) ? true : false;

			$context['messages'][] = array(
				'permission',
				$success,
				($success === false) ? 'not authorised to perform this action' : null
			);

		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function cbAddMemberDetailsToPageParams(Array $context = null) {
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);
			$this->Member->AddMemberDetailsToPageParams($context);
		}

		public function appendLoginStatusToEventXML(Array $context = null){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);
			$this->Member->appendLoginStatusToEventXML($context);
		}

		public function buildXML(Array $context = null){
			if(self::$debug) var_dump(__CLASS__ . ":" . __FUNCTION__);
			$result = $this->Member->buildXML();

			if(self::$_failed_login_attempt === true) $result->setAttribute('failed-login-attempt', 'true');

			if($this->Member->isLoggedIn()) {
				$role_data = $this->Member->Member->getData(self::roleField());
				$role = self::fetchRole($role_data['role_id'], true);

				//	Page Permissions
				$permission = new XMLElement('permissions');

				$forbidden_pages = $role->forbiddenPages();
				if(is_array($forbidden_pages) && !empty($forbidden_pages)){

					$rows = Symphony::Database()->fetch(sprintf(
						"SELECT id, title, handle, path FROM `tbl_pages` WHERE `id` IN (%s)",
						implode(',', $forbidden_pages)
					));

					$pages = new XMLElement('forbidden-pages');
					foreach($rows as $r) {
						$attr = array(
							'id' => $r['id'],
							'handle' => General::sanitize($r['handle'])
						);

						if(!is_null($r['path'])) $attr['parent-path'] = General::sanitize($r['path']);

						$pages->appendChild(new XMLElement('page',
							General::sanitize($r['title']),
							$attr
						));
					}

					$permission->appendChild($pages);
				}

				//	Event Permissions
				$event_permissions = $role->eventPermissions();
				if(is_array($event_permissions) && !empty($event_permissions)) foreach($event_permissions as $event_handle => $e){
					$obj = new XMLElement($event_handle);

					foreach($e as $action => $level) $obj->setAttribute($action, (string)$level);

					$permission->appendChild($obj);
				}

				$result->appendChild($permission);
			}

			return $result;
		}
	}
