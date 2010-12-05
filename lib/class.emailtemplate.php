<?php

	Class EmailTemplate{

		private $_id = null;
		private $_type = null;
		private $_subject = null;
		private $_body = null;
		private $__roles = array();

		private static $_Members;
		private static $symphony;

		public function __construct(){
			if(!(self::$symphony instanceof Symphony)){
				if(class_exists('Frontend')){
					self::$symphony = Frontend::instance();
				}

				else{
					self::$symphony = Administration::instance();
				}
			}

			if(!(self::$_Members instanceof Extension)){
				self::$_Members = self::$symphony->ExtensionManager->create('members');
			}
		}

		public function roles(){
			return $this->__roles;
		}

		public function send($members, Array $vars = array()){

			if(!is_array($members)) $members = array($members);

			foreach($members as $member_id){
			/*
				TODO: Implement Core Email API
				$email = new LibraryEmail;

				$member = self::$_Members->Member->fetchMemberFromID($member_id);

				$email->to = $member->getData(extension_Members::getConfigVar('email_address_field_id'), true)->value;
				$email->from = sprintf(
					'%s <%s>',
					Symphony::Configuration()->get('sitename', 'general'),
					'noreply@' . parse_url(URL, PHP_URL_HOST)
				);

				$email->subject = $this->__replaceFieldsInString(
					$this->__replaceVarsInString($this->subject, $vars), $member
				);

				$email->message = $this->__replaceFieldsInString(
					$this->__replaceVarsInString($this->body, $vars), $member
				);

				try{
					return $email->send();
				}
				catch(Exception $e){
					// It's okay to discard errors
				}

				unset($email);
				*/
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
				$FieldManager = new FieldManager(self::$symphony);

				foreach($fields as $element_name => $field_id) {
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

			// This could be optimised so that it gets all the ID's in one query..
			foreach($field_handles as $h){
				$fields[$h] = Symphony::Database()>query('id', 0, sprintf("
						SELECT `id`
						FROM `tbl_fields`
						WHERE `element_name` = '%s'
						AND `parent_section` = %d
						LIMIT 1
					", $h, extension_Members::getConfigVar('member_section')
				));
			}

			return $fields;

		}

		public function addRole($role_id = null){
			if(is_null($role_id)) return;

			$this->__roles[$role_id] = Role::loadFromID($role_id);
		}

		public function removeRole($role_id){
			if(is_null($role_id)) return;

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
			$id = Symphony::Database()->fetchVar('id', 0, sprintf(
				"SELECT et.id FROM `tbl_members_email_templates` AS `et`
				LEFT JOIN `tbl_members_email_templates_role_mapping` AS `r` ON et.id = r.email_template_id
				WHERE et.type = '%s' %s",
				Symphony::Database()->cleanValue($type),
				(!is_null($role_id) ? "AND r.role_id = {$role_id}" : NULL)
			));

			return self::loadFromID($id);
		}

		public static function loadFromID($id = null){
			if(is_null($id)) return;

			$obj = new EmailTemplate();

			$record = Symphony::Database()->fetchRow(0,
				"SELECT * FROM `tbl_members_email_templates` WHERE `id` = {$id} LIMIT 1"
			);

			if(empty($record)) return;

			$obj->id = $record['id'];
			$obj->subject = $record['subject'];
			$obj->body = $record['body'];
			$obj->type = $record['type'];

			$roles = Symphony::Database()->fetch(
				"SELECT et.role_id
				FROM `tbl_members_email_templates_role_mapping` AS `et`
				WHERE et.email_template_id = {$record['id']}"
			);

			if(!empty($roles)) {
				foreach($roles as $r){
					$obj->addRole($r['role_id']);
				}
			}

			return $obj;
		}

		public static function delete($id){
			Symphony::Database()->delete('tbl_members_email_templates',  "`id` = {$id}");
			Symphony::Database()->delete('tbl_members_email_templates_role_mapping',  "`email_template_id` = {$id}");
			return true;
		}

		public static function save(EmailTemplate &$obj){

			$fields = array(
				'type' => $obj->type,
				'subject' => $obj->subject,
				'body' => $obj->body
			);

			if(!is_null($obj->id)){
				Symphony::Database()->update($fields, 'tbl_members_email_templates', "`id` = {$obj->id}");
			}
			else{
				$obj->id = Symphony::Database()->insert($fields, 'tbl_members_email_templates');
			}

			Symphony::Database()->delete('tbl_members_email_templates_role_mapping',  "`email_template_id` = {$obj->id}");
			foreach($obj->roles() as $id => $role){
				Symphony::Database()->insert(
					array('id' => NULL, 'role_id' => $id, 'email_template_id' => $obj->id),
					'tbl_members_email_templates_role_mapping'
				);
			}
		}
	}
