<?php

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