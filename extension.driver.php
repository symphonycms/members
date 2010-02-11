<?php

	include_once(TOOLKIT . '/class.entrymanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');
	include_once(EXTENSIONS . '/asdc/lib/class.asdc.php');
	include_once(EXTENSIONS . '/smtp_email_library/lib/class.email.php');
			
	Class EmailTemplate{
		
		private $_id;
		private $_type;
		private $_subject;
		private $_body;
		private $__roles;
		
		private static $_Members;
		private static $_Parent;
		
		public function __construct(){
			$this->id = $this->type = $this->subject = $this->body = NULL;
			$this->__roles = array();

			if(!(self::$_Parent instanceof Symphony)){
				if(class_exists('Frontend')){
					self::$_Parent = Frontend::instance();
				}

				else{
					self::$_Parent = Administration::instance();
				}
			}			
			
			if(!(self::$_Members instanceof Extension)){
				self::$_Members = self::$_Parent->ExtensionManager->create('members');
			}
		}
		
		public function roles(){
			return $this->__roles;
		}
		
		public function send($members, array $vars=array()){

			if(!is_array($members)) $members = array($members);

			foreach($members as $member_id){
				$email = new LibraryEmail;
				
				$member = self::$_Members->fetchMemberFromID($member_id);

				$email->to = $member->getData(extension_Members::memberEmailFieldID(), true)->value;
				$email->from = sprintf(
					'%s <%s>', 
					Symphony::Configuration()->get('sitename', 'general'), 
					'noreply@' . parse_url(URL, PHP_URL_HOST)
				);
				
				$email->subject = $this->__replaceFieldsInString(
					$this->__replaceVarsInString($this->subject, $vars), $member
				);
				
				$email->body = $this->__replaceFieldsInString(
					$this->__replaceVarsInString($this->body, $vars), $member
				);

				try{
					return $email->send();
				}
				catch(Exception $e){
					// It's okay to discard errors
				}
				
				unset($email);
			}
			
		}
		
		private function __replaceVarsInString($string, array $vars){
			foreach($vars as $key => $value){
				$string = str_replace(sprintf('{$%s}', $key), $value, $string);
			}
			return $string;
		}
		
		private function __replaceFieldsInString($string, Entry $entry){
			
			$fields = $this->__findFieldsInString($string, true);

			if(is_array($fields) && !empty($fields)){
				
				$FieldManager = new FieldManager(self::$_Parent);
				
				foreach($fields as $element_name => $field_id){

					if(is_null($field_id)) continue;
					
					$field_data = $entry->getData($field_id);
					$fieldObj = $FieldManager->fetch($field_id);
					$value = $fieldObj->prepareTableValue($field_data);

					$string = str_replace('{$'.$element_name.'}', $value, $string);
					$string = str_replace('{$'.$element_name.'::handle}', Lang::createHandle($value), $string);
				}

			}
			
			return $string;
			
		}
		
		private function __findFieldsInString($string, $resolveIDValues=false){
			
			preg_match_all('/{\$([^:}]+)(::handle)?}/', $string, $matches);

			$field_handles = array_unique($matches[1]);
			
			if(!$resolveIDValues || !is_array($field_handles) || empty($field_handles)) return array();			
			
			$fields = array();
			
			foreach($field_handles as $h){
				$fields[$h] = ASDCLoader::instance()->query(
					"SELECT `id` FROM `tbl_fields` WHERE `element_name` = '{$h}' AND `parent_section` = ".self::$_Members->memberSectionID()." LIMIT 1"
				)->current()->id;
			}
			
			return $fields;
			
		}
				
		public function addRole($role_id){
			$this->__roles[$role_id] = Role::loadFromID($role_id);
		}
		
		public function addRoleFromName($role_name){
			$id = ASDCLoader::instance()->query(
				"SELECT `id` FROM `tbl_members_roles` WHERE `name` = '{$role_name}' LIMIT 1"
			)->current()->id;
			
			$this->addRole($id);
		}
		
		public function removeRole($role_id){
			unset($this->__roles[$role_id]);
		}

		public function removeAllRoles(){
			$this->__roles = array();
		}
		
		public function __get($name){
			return $this->{"_{$name}"};
		}
		
		public function __set($name, $val){
			$this->{"_{$name}"} = $val;
		}
		
		public static function find($type, $role_id=NULL){
			$id = ASDCLoader::instance()->query(sprintf(
				"SELECT et.id FROM `tbl_members_email_templates` AS `et`
				LEFT JOIN `tbl_members_email_templates_role_mapping` AS `r` ON et.id = r.email_template_id
				WHERE et.type = '%s' %s",
				ASDCLoader::instance()->escape($type),
				(!is_null($role_id) ? "AND r.role_id = {$role_id}" : NULL)
			))->current()->id;
			
			return self::loadFromID($id);
		}
		
		public static function loadFromID($id){
			
			$obj = new self;
			
			$record = ASDCLoader::instance()->query(
				"SELECT * FROM `tbl_members_email_templates` WHERE `id` = {$id} LIMIT 1"
			)->current();
			
			$obj->id = $record->id;
			$obj->subject = $record->subject;
			$obj->body = $record->body;
			$obj->type = $record->type;
									
			$roles = ASDCLoader::instance()->query(
				"SELECT et.role_id
				FROM `tbl_members_email_templates_role_mapping` AS `et`
				WHERE et.email_template_id = {$record->id}"
			);
			
			if($roles->length() > 0){
				foreach($roles as $r){
					$obj->addRole($r->role_id);
				}
			}
			
			return $obj;
			
		}
		
		public static function delete($id){
			ASDCLoader::instance()->delete('tbl_members_email_templates',  "`id` = {$id}");
			ASDCLoader::instance()->delete('tbl_members_email_templates_role_mapping',  "`email_template_id` = {$id}");
			return true;
		}
		
		public static function save(self &$obj){
			
			$fields = array(
				'type' => $obj->type,
				'subject' => $obj->subject,	
				'body' => $obj->body
			);
			
			if(!is_null($obj->id)){
				ASDCLoader::instance()->update($fields, 'tbl_members_email_templates', "`id` = {$obj->id}");
			}
			else{
				$obj->id = ASDCLoader::instance()->insert($fields, 'tbl_members_email_templates');
			}
			
			ASDCLoader::instance()->delete('tbl_members_email_templates_role_mapping',  "`email_template_id` = {$obj->id}");
			foreach($obj->roles() as $id => $role){
				ASDCLoader::instance()->insert(
					array('id' => NULL, 'role_id' => $id, 'email_template_id' => $obj->id), 
					'tbl_members_email_templates_role_mapping'
				);
			}
		}
	}
	
	Final Class EmailTemplateIterator implements Iterator{
	
		private $_iterator;
	
		public function __construct(){
			$this->_iterator = ASDCLoader::instance()->query("SELECT `id` FROM `tbl_members_email_templates`");
		}

		public function current(){
			$this->_current = EmailTemplate::loadFromID($this->_iterator->current()->id);
			return $this->_current;
		}
	
		public function innerIterator(){
			return $this->_iterator;
		}
	
		public function next(){
			$this->_iterator->next();
		}
	
		public function key(){
			return $this->_iterator->key();
		}

		public function valid(){
			return $this->_iterator->valid();
		}
	
		public function rewind(){
			$this->_iterator->rewind();
		}
			
		public function position(){
			return $this->_iterator->position();
		}

		public function length(){
			return $this->_iterator->length();
		}
	
	}

	
	Class Role{
		
		private $_id;
		private $_name;
		
		private $_forbidden_pages;
		private $_event_permissions;
		
		public function __construct($id, $name, array $event_permissions=array(), array $forbidden_pages=array()){
			$this->_id = $id;
			$this->_name = $name;
			$this->_forbidden_pages = $forbidden_pages;
			$this->_event_permissions = $event_permissions;
		}
		
		public static function loadFromID($id){
			
			$record = ASDCLoader::instance()->query("SELECT * FROM `tbl_members_roles` WHERE `id` = {$id} LIMIT 1")->current();
			
			$forbidden_pages = $event_permissions = array();
		
			$records = ASDCLoader::instance()->query(
				"SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = '{$id}' "
			);
			if($records->length() > 0){
				$forbidden_pages = DatabaseUtilities::ResultColumn($records, 'page_id');
			}

			$tmp = ASDCLoader::instance()->query("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = '{$id}'");
			if($tmp->length() > 0){
				foreach($tmp as $e){
					$event_permissions[$e->event][$e->action] = $e->level;
				}
			}
			
			return new self($id, $record->name, $event_permissions, $forbidden_pages);
		}
		
		public function __call($name, $var){
			return $this->{"_$name"};
		}
		
		public function forbiddenPages(){
			return $this->_forbidden_pages;
		}
		
		public function eventPermissions(){
			return $this->_event_permissions;			
		}
		
		public function canAccessPage($page_id){
			return !@in_array($page_id, $this->_forbidden_pages);
		}
		
		public function canPerformEventAction($event_handle, $action, $required_level){
			return ($this->_event_permissions[$event_handle][$action] >= $required_level);
		}		
		
	}

	Final Class extension_Members extends Extension{
		
		private $_cookie;
		private $_member_id;
		public $Member;
		static private $_failed_login_attempt = false;
		
		const CODE_EXPIRY_TIME = 3600; // 1 hour
		const GUEST_ROLE_ID = 1;
		const INACTIVE_ROLE_ID = 2;
		
		public static function baseURL(){
			return URL . '/symphony/extension/members/';
		}

		public function fetchNavigation(){
			return array(
				array(
					'location' => 330,
					'name' => 'Members',
					'children' => array(
						
						array(
							'name' => 'Roles',
							'link' => '/roles/'
						),
						
						array(
							'name' => 'Email Templates',
							'link' => '/email_templates/'
						),	
											
						array(
							'name' => 'Setup',
							'link' => '/setup/'
						),						
					)
				)
				
			);
		}		
		
		public function roleExists($name){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_members_roles` WHERE `name` = '$name' LIMIT 1");
		}
		
		public function about(){
			return array('name' => 'Members',
						 'version' => '1.1',
						 'release-date' => '2009-11-25',
						 'author' => array('name' => 'Symphony Team',
										   'website' => 'http://www.symphony-cms.com',
										   'email' => 'team@symphony-cms.com')
				 		);
		}
		
		public function update($previous_version){
			if($previous_version == '1.0'){
				Symphony::Database()->query("ALTER TABLE `sym_fields_memberlink` ADD  `allow_multiple` ENUM(  'yes',  'no' ) NOT NULL DEFAULT  'no'");
			}
		}
		
		public function install(){
			
			Symphony::Configuration()->set('cookie-prefix', 'sym-members', 'members');
			$this->_Parent->saveConfig();
			
			Symphony::Database()->import("
			
				CREATE TABLE IF NOT EXISTS `tbl_fields_member` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				);


				CREATE TABLE IF NOT EXISTS `tbl_fields_memberlink` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `allow_multiple` enum('yes','no') NOT NULL default 'no',
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				);


				CREATE TABLE IF NOT EXISTS `tbl_fields_memberrole` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				);

				DROP TABLE IF EXISTS `tbl_members_codes`;
				CREATE TABLE `tbl_members_codes` (
				  `member_id` int(11) unsigned NOT NULL,
				  `code` varchar(8)  NOT NULL,
				  `expiry` int(11) NOT NULL,
				  PRIMARY KEY  (`member_id`),
				  KEY `code` (`code`)
				) ;

			
				DROP TABLE IF EXISTS `tbl_members_roles`;
				CREATE TABLE `tbl_members_roles` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `name` varchar(60)  NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `name` (`name`)
				) ;

				DROP TABLE IF EXISTS `tbl_members_roles_event_permissions`;
				CREATE TABLE `tbl_members_roles_event_permissions` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `role_id` int(11) unsigned NOT NULL,
				  `event` varchar(50) NOT NULL,
				  `action` varchar(60) NOT NULL,
				  `level` smallint(1) unsigned NOT NULL DEFAULT '0',
				  PRIMARY KEY (`id`),
				  KEY `role_id` (`role_id`,`event`,`action`)
				);

				DROP TABLE IF EXISTS `tbl_members_roles_forbidden_pages`;
				CREATE TABLE `tbl_members_roles_forbidden_pages` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `role_id` int(11) unsigned NOT NULL,
				  `page_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `role_id` (`role_id`,`page_id`)
				);
				
				DROP TABLE IF EXISTS `tbl_members_email_templates`;
				CREATE TABLE  `tbl_members_email_templates` (
					`id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`subject` VARCHAR( 255 ) NOT NULL ,
					`body` LONGTEXT NOT NULL ,
					`type` VARCHAR( 100 ) NOT NULL ,
					INDEX (`type`)
				);
				
				DROP TABLE IF EXISTS `tbl_members_email_templates_role_mapping`;
				CREATE TABLE  `tbl_members_email_templates_role_mapping` (
					`id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`email_template_id` INT( 11 ) UNSIGNED NOT NULL ,
					`role_id` INT( 11 ) UNSIGNED NOT NULL ,
					INDEX (  `email_template_id` ,  `role_id` )
				);
				
				INSERT INTO `tbl_members_roles` VALUES (1, 'Guest');
				INSERT INTO `tbl_members_roles` VALUES (2, 'Inactive');
			");

		}	

		public function uninstall(){
			Symphony::Configuration()->remove('members');			
			$this->_Parent->saveConfig();
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

		public function fetchRole($role_id, $include_permissions=false){
			if(!$row = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_roles` WHERE `id` = $role_id LIMIT 1")) return;
			
			$forbidden_pages = array();
			$event_permissions = array();
			
			if($include_permissions){			
				$forbidden_pages = Symphony::Database()->fetchCol('page_id', "SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = '".$row['id']."' ");

				$tmp = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = '".$row['id']."'");
				if(is_array($tmp) && !empty($tmp)){
					foreach($tmp as $e){
						$event_permissions[$e['event']][$e['action']] = $e['level'];
					}
				}
			}
			
			return new Role($row['id'], $row['name'], $event_permissions, $forbidden_pages, $row['email_subject'], $row['email_body']);
		}

		public function fetchEmailTemplates(){
			return ASDCLoader::instance()->query('SELECT * FROM `tbl_members_email_templates` ORDER BY `id` ASC', 'EmailTemplateResultIterator');
		}

		public function fetchRoles($include_permissions=false){
			if(!$rows = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles` ORDER BY `id` ASC")) return;
			
			$roles = array();
			
			foreach($rows as $r){
				
				$forbidden_pages = array();
				$event_permissions = array();
				
				if($include_permissions){	
					$forbidden_pages = Symphony::Database()->fetchCol('page_id', "SELECT `page_id` FROM `tbl_members_roles_forbidden_pages` WHERE `role_id` = '".$r['id']."' ");

					$tmp = Symphony::Database()->fetch("SELECT * FROM `tbl_members_roles_event_permissions` WHERE `role_id` = '".$row['id']."'");
					if(is_array($tmp) && !empty($tmp)){
						foreach($tmp as $e){
							$event_permissions[$e['event']][$e['action']] = $e['level'];
						}
					}
				}
				
				$roles[] = new Role($r['id'], $r['name'], $event_permissions, $forbidden_pages, $r['email_subject'], $r['email_body']);
				
			}
			
			return $roles;
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
					'callback' => 'cbAddParamsToPage'
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
						
			);
		}
		
		public function cbAddParamsToPage(array $context=array()){
			//print_r($context); die();
		}
		
		public static function purgeCodes($member_id=NULL){
			Symphony::Database()->query("DELETE FROM `tbl_members_codes` WHERE `expiry` <= ".time().($member_id ? " OR `member_id` = '$member_id'" : NULL));
		}
		
		public function generateCode($member_id){
			
			## First check if a code already exists
			$code = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_codes` WHERE `member_id` = '$member_id' AND `expiry` > ".time()." LIMIT 1");
			
			if(is_array($code) && !empty($code)){
				return $code['code'];
			}
			
			## Generate a code
			do{
				$code = md5(time().rand(0,100000));
				$row = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_codes` WHERE `code` = '{$code}'");
			}while(is_array($row) && !empty($row));
			
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
		
		public function sendNewPasswordEmail($member_id){
			
			$entry = $this->fetchMemberFromID($member_id);
			
			if(!($entry instanceof Entry)){
				throw new Exception('Invalid member ID specified');
			}
			
			if(!$role = $this->fetchRole($entry->getData($this->roleField(), true)->role_id)) return;
			
			$new_password = General::generatePassword();
			
			// Attempt to update the password
			Symphony::Database()->query(sprintf(
				"UPDATE `tbl_entries_data_%d` SET `password` = '%s' WHERE `entry_id` = %d LIMIT 1",
				$this->usernameAndPasswordField(),
				md5($new_password),
				$member_id
			));
		
			$email_template = EmailTemplate::find('new-password', $role->id());
			
			$member_field_handle = $this->usernameAndPasswordFieldHandle();

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::username" => $entry->getData($this->usernameAndPasswordField(), true)->username,
				'new-password' => $new_password,
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));
			
		}
		
		public function sendResetPasswordEmail($member_id){
			
			$entry = $this->fetchMemberFromID($member_id);
			
			if(!($entry instanceof Entry)){
				throw new Exception('Invalid member ID specified');
			}
	
			if(!$role = $this->fetchRole($entry->getData($this->roleField(), true)->role_id)) return;
			
			$email_template = EmailTemplate::find('reset-password', $role->id());
			
			$member_field_handle = $this->usernameAndPasswordFieldHandle();

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::username" => $entry->getData($this->usernameAndPasswordField(), true)->username,
				'code' => $this->generateCode($entry->get('id')),
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));
			
		}
		
		private function __sendNewRegistrationEmail(Entry $entry, array $fields=array()){

			if(!$role = $this->fetchRole($entry->getData($this->roleField(), true)->role_id)) return;
			
			$email_template = EmailTemplate::find(
				($role->id() == self::INACTIVE_ROLE_ID ? 'activate-account' : 'welcome'), 
				$role->id()
			);
			
			$member_field_handle = $this->usernameAndPasswordFieldHandle();

			return $email_template->send($entry->get('id'), array(
				'root' => URL,
				"{$member_field_handle}::plaintext-password" => $fields[$member_field_handle]['password'],
				"{$member_field_handle}::username" => $fields[$member_field_handle]['username'],
				'code' => $this->generateCode($entry->get('id')),
				'site-name' => Symphony::Configuration()->get('sitename', 'general')
			));
						
		}
		
		public function emailNewMember($context){
			return $this->__sendNewRegistrationEmail($context['entry'], $context['fields']);
		}
		
		public function cbEmailNewMember($context){
			if($context['section']->get('handle') == $this->memberSectionHandle()){
				return $this->__sendNewRegistrationEmail($context['entry'], $context['fields']);
			}
		}

		public function processEventData($context){
			if($context['event']->getSource() == self::memberSectionID() && isset($_POST['action']['members-register'])){
				return $this->__sendNewRegistrationEmail($context['entry'], $context['fields']);	
			}
		}
		
		public function checkEventPermissions($context){

			$action = 'create';
			$required_level = 1;
			$entry_id = NULL;
			
			if(isset($_POST['id'])){
				$entry_id = (int)$_POST['id'];
				$action = 'edit';
			}
			
			$this->initialiseCookie();
			$this->initialiseMemberObject();
			$isLoggedIn = $this->isLoggedIn();	
			
			if($isLoggedIn && is_object($this->Member)){
				$role_data = $this->Member->getData($this->roleField());
			}
			
			$role = $this->fetchRole(($isLoggedIn ? $role_data['role_id'] : self::GUEST_ROLE_ID), true);
			
			$event_handle = strtolower(preg_replace('/^event/i', NULL, get_class($context['event'])));
			
			$is_owner = false;
			
			if($action == 'edit'){
				$section_id = $context['event']->getSource();
			
				$member_field = Symphony::Database()->fetchRow(0,
					"SELECT * FROM `tbl_fields` WHERE `parent_section` = {$section_id} AND `type` IN ('memberlink', 'member') LIMIT 1"
				);
				
				$member_id = Symphony::Database()->fetchVar(
					($member_field['type'] == 'memberlink' ? 'member_id' : 'entry_id'), 0,
					sprintf("SELECT * FROM `tbl_entries_data_%d` WHERE `entry_id` = %d LIMIT 1", $member_field['id'], $entry_id)
				);

				$is_owner = ($isLoggedIn ? ((int)$this->Member->get('id') == $member_id) : false);
				
				if($is_owner != true) $required_level = 2;
			}

			$success = false;
			if($role->canPerformEventAction($event_handle, $action, $required_level)){
				$success = true;
			}
			
			$context['messages'][] = array(
				'permission', 
				$success, 
				($success === false ? 'not authorised to perform this action' : NULL)
			);
			
		}

		private function __replaceFieldsInString($string, Entry $entry){
			
			$fields = $this->__findFieldsInString($string, true);

			if(is_array($fields) && !empty($fields)){
				
				$FieldManager = new FieldManager($this->_Parent);
				
				foreach($fields as $element_name => $field_id){

					if($field_id == NULL) continue;
					
					$field_data = $entry->getData($field_id);
					$fieldObj = $FieldManager->fetch($field_id);
					$value = $fieldObj->prepareTableValue($field_data);
					
					$string = str_replace('{$'.$element_name.'}', $value, $string);
					$string = str_replace('{$'.$element_name.'::handle}', Lang::createHandle($value), $string);
				}
			}
			
			return $string;
			
		}
		
		private function __findFieldsInString($string, $resolveIDValues=false){
			
			preg_match_all('/{\$([^:}]+)(::handle)?}/', $string, $matches);

			$field_handles = array_unique($matches[1]);
			
			if(!$resolveIDValues || !is_array($field_handles) || empty($field_handles)) return array();			
			
			$fields = array();
			foreach($field_handles as $h){
				$field_id = Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `element_name` = '$h' AND `parent_section` = ".self::memberSectionID()." LIMIT 1");
				
				$fields[$h] = $field_id;
				
			}
			
			return $fields;
			
		}
		
		public function appendLoginStatusToEventXML($context){

			$this->initialiseCookie();
			
			## Cookies only show up on page refresh. This flag helps in making sure the correct XML is being set
			$loggedin = $this->isLoggedIn();
			
			$this->initialiseMemberObject();
			
			if($loggedin == true){
				$this->__updateSystemTimezoneOffset();
			}

			$context['wrapper']->appendChild($this->buildXML());	
			
		}
		
		public function cbAddMemberDetailsToPageParams(array $context=NULL){
			$this->initialiseCookie();
			
			if(!$this->isLoggedIn()) return;
			
			$this->initialiseMemberObject();
			
			$context['params']['cookie-member-id'] = $this->Member->get('id');
			
		}
		
		public function cbCheckFrontendPagePermissions($context){

			$this->initialiseCookie();

			## Cookies only show up on page refresh. This flag helps in making sure the correct XML is being set
			$loggedin = false;
			
			$action = $_REQUEST['member-action'];

			if(trim($action) == 'logout'){
				$this->logout();
				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);
				redirect(URL);
			}
			
			elseif(trim($action) == 'login'){	

				$username = Symphony::Database()->cleanValue($_REQUEST['username']);
				$password = Symphony::Database()->cleanValue($_REQUEST['password']);	
				
				if($this->login($username, $password)){ 
					if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);
					redirect(URL);
				}
				
				self::$_failed_login_attempt = true;
				
			}
			
			else $loggedin = $this->isLoggedIn();

			
			$this->initialiseMemberObject();

			if($loggedin && is_object($this->Member)){
				$role_data = $this->Member->getData($this->roleField());
				$this->__updateSystemTimezoneOffset();
			}
						
			$role = $this->fetchRole(($loggedin ? $role_data['role_id'] : 1), true);
		
			if(!$role->canAccessPage((int)$context['page_data']['id'])):

				if($row = Symphony::Database()->fetchRow(0, 
					"SELECT `tbl_pages`.* FROM `tbl_pages`, `tbl_pages_types` 
					WHERE `tbl_pages_types`.page_id = `tbl_pages`.id AND tbl_pages_types.`type` = '403' 
					LIMIT 1")){

					$row['type'] = Symphony::Database()->fetchCol('type', 
						"SELECT `type` FROM `tbl_pages_types` WHERE `page_id` = '".$row['id']."' "
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
				
				
			endif;
						
		}
	
		public function roleField(){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = '".self::memberSectionID()."' AND `type` = 'memberrole' LIMIT 1");
		}
		
		public function usernameAndPasswordField(){
			return Symphony::Database()->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = '".self::memberSectionID()."' AND `type` = 'member' LIMIT 1");			
		}

		public function usernameAndPasswordFieldHandle(){
			return Symphony::Database()->fetchVar('element_name', 0, "SELECT `element_name` FROM `tbl_fields` WHERE `parent_section` = '".self::memberSectionID()."' AND `type` = 'member' LIMIT 1");			
		}
		
		public static function memberSectionID(){
			$id = (int)Symphony::Configuration()->get('member_section', 'members');
			return($id == 0 ? NULL : $id);
		}
		
		public static function memberEmailFieldID(){
			return (int)Symphony::Configuration()->get('email_address_field_id', 'members');
		}
		
		public static function memberTimezoneOffsetFieldID(){
			return (int)Symphony::Configuration()->get('timezone_offset_field_id', 'members');
		}		
					
		public function memberSectionHandle(){
			$section_id = self::memberSectionID();
			
			return Symphony::Database()->fetchVar('handle', 0, "SELECT `handle` FROM `tbl_sections` WHERE `id` = $section_id LIMIT 1");
		}	
		
		private function __updateSystemTimezoneOffset(){

			
			$offset = Symphony::Database()->fetchVar('value', 0, "SELECT `value` 
																	FROM `tbl_entries_data_".self::memberTimezoneOffsetFieldID()."` 
																	WHERE `entry_id` = '".Symphony::Database()->cleanValue($this->Member->get('id'))."'
																	LIMIT 1");
			
			if(strlen(trim($offset)) == 0) return;
			
			//When using 'Etc/GMT...' the +/- signs are reversed. E.G. GMT+10 == Etc/GMT-10
			DateTimeObj::setDefaultTimezone('Etc/GMT' . ($offset >= 0 ? '-' : '+') . abs($offset)); 

		}
		
		public function buildXML(){
			
			if(!empty($this->_member_id)){
				$result = new XMLElement('member-login-info');
				$result->setAttribute('logged-in', 'true');

				if(!$this->Member) $this->initialiseMemberObject();

				$result->setAttributeArray(array('id' => $this->Member->get('id')));

				$entryManager = new EntryManager($this->_Parent);

				foreach($this->Member->getData() as $field_id => $values){

					if(!isset($fieldPool[$field_id]) || !is_object($fieldPool[$field_id]))
						$fieldPool[$field_id] =& $entryManager->fieldManager->fetch($field_id);

					$fieldPool[$field_id]->appendFormattedElement($result, $values, false, NULL, $this->Member->get('id'));

				}
				
				$role_data = $this->Member->getData($this->roleField());
				$role = $this->fetchRole($role_data['role_id'], true);
			
				$permission = new XMLElement('permissions');
				
				$forbidden_pages = $role->forbiddenPages();
				if(is_array($forbidden_pages) && !empty($forbidden_pages)){
					
					$rows = ASDCLoader::instance()->query(sprintf(
						"SELECT * FROM `tbl_pages` WHERE `id` IN (%s)", 
						@implode(',', $forbidden_pages)
					));
					
					$pages = new XMLElement('forbidden-pages');
					foreach($rows as $r){
						
						$attr = array(
							'id' => $r->id, 
							'handle' => General::sanitize($r->handle)
						);
						
						if(!is_null($r->path)) $attr['parent-path'] = General::sanitize($r->path);
						
						$pages->appendChild(new XMLElement('page', 
							General::sanitize($r->title), 
							$attr
						));
					}
					
					$permission->appendChild($pages);
				}

				$event_permissions = $role->eventPermissions();
				if(is_array($event_permissions) && !empty($event_permissions)){

					foreach($event_permissions as $event_handle => $e){
						$obj = new XMLElement($event_handle);
						
						foreach($e as $action => $level){
							$obj->appendChild(new XMLElement($action, (string)$level));
						}
						
						$permission->appendChild($obj);
					}
					
				}
				
				$result->appendChild($permission);
			}
			
			else{
				$result = new XMLElement('member-login-info');
				$result->setAttribute('logged-in', 'false');
				
				if(self::$_failed_login_attempt === true){
					$result->setAttribute('failed-login-attempt', 'true');
				}
			}
			
			return $result;
			
		}
		
		public function initialiseMemberObject($member_id = NULL){
			
			$member_id = ($member_id ? $member_id : $this->_member_id);

			$this->Member = $this->fetchMemberFromID($member_id);
			
			return $this->Member;
		}
		
		public function fetchMemberFromID($member_id){
		
			$entryManager = new EntryManager($this->_Parent);
			$Member = $entryManager->fetch($member_id, NULL, NULL, NULL, NULL, NULL, false, true);
			$Member = $Member[0];
			
			return $Member;			
		}
		
		public function initialiseCookie(){
			if(!$this->_cookie) $this->_cookie =& new Cookie(Symphony::Configuration()->get('cookie-prefix', 'members'), TWO_WEEKS, __SYM_COOKIE_PATH__);
		}
		
		private function __findMemberIDFromCredentials($username, $password){
			$entry_id = Symphony::Database()->fetchVar('entry_id', 0, "SELECT `entry_id` 
																		   FROM `tbl_entries_data_".$this->usernameAndPasswordField()."` 
																		   WHERE `username` = '".Symphony::Database()->cleanValue($username)."' 
																			AND `password` = '".Symphony::Database()->cleanValue($password)."' 
																		   LIMIT 1");
			
			return (is_null($entry_id) ? NULL : $entry_id);
		}

		public function findMemberIDFromEmail($email){
			return Symphony::Database()->fetchCol('entry_id', "SELECT `entry_id` 
																		   FROM `tbl_entries_data_".self::memberEmailFieldID()."` 
																		   WHERE `value` = '".Symphony::Database()->cleanValue($email)."'");	
		}
		
		public function findMemberIDFromUsername($username){
			return Symphony::Database()->fetchVar('entry_id', 0, "SELECT `entry_id` 
																FROM `tbl_entries_data_".$this->usernameAndPasswordField()."` 
															 	WHERE `username` = '".Symphony::Database()->cleanValue($username)."' 
																LIMIT 1");	
		}
				
		public function isLoggedIn(){
			
			if($id = $this->__findMemberIDFromCredentials($this->_cookie->get('username'), $this->_cookie->get('password'))){
				$this->_member_id = $id;
				return true;
			}
			
			$this->_cookie->expire();
			return false;
		}

		public function logout(){
			$this->_cookie->expire();
		}
		
		public function login($username, $password, $isHash=false){
			
			if(!$isHash) $password = md5($password);
		
			if($id = $this->__findMemberIDFromCredentials($username, $password)){
				$this->_member_id = $id;
				
				try{	
					$this->_cookie->set('username', $username);
					$this->_cookie->set('password', $password);
										
				}catch(Exception $e){
					trigger_error($e->message(), E_USER_ERROR);
				}
				
				return true;
			}

			return false;
			
		}
		
		public static function buildRolePermissionTableBody(array $rows){
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

	}

