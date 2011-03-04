<?php

	Class EmailTemplateManager {

		private static $_pool = array();

		public static function add(Array $data) {
			Symphony::Database()->insert($data['email_templates'], 'tbl_email_templates');
			$template_id = Symphony::Database()->getInsertID();

			$roles = $data['email_templates_role_mapping']['roles'];
			if(is_array($roles) && !empty($roles)) {
				foreach($roles as $role_id){
					Symphony::Database()->insert(array(
							'email_template_id' => $template_id,
							'role_id' => $role_id
						),
						'`tbl_members_email_templates_role_mapping`'
					);
				}
			}

			return $template_id;
		}

		public static function edit($template_id = null, Array $data) {
			if(is_null($template_id)) return false;

			Symphony::Database()->update($data['email_templates'], 'tbl_email_templates', "`id` = " . $template_id);

			Symphony::Database()->delete("`tbl_members_email_templates_role_mapping`", "`email_template_id` = " . $template_id);
			$roles = $data['email_templates_role_mapping']['roles'];
			if(is_array($roles) && !empty($roles)) {
				foreach($roles as $role_id){
					Symphony::Database()->insert(array(
							'email_template_id' => $template_id,
							'role_id' => $role_id
						),
						'`tbl_members_email_templates_role_mapping`'
					);
				}
			}
		}

		public static function delete($template_id) {
			Symphony::Database()->delete('tbl_members_email_templates',  "`id` = {$template_id}");
			Symphony::Database()->delete('tbl_members_email_templates_role_mapping',  "`email_template_id` = {$template_id}");

			return true;
		}

		public static function fetch($template_id = null, $type = null, $roles = array()) {
			$result = array();
			$return_single = true;

			if(is_null($role_id)) $return_single = false;

			if($return_single) {
				// Check static cache for object
				if(in_array($template_id, array_keys(EmailTemplateManager::$_pool))) {
					return EmailTemplateManager::$_pool[$template_id];
				}

				// No cache object found
				if(!$templates = Symphony::Database()->fetch(sprintf("
						SELECT * FROM `tbl_members_email_templates` WHERE `id` = %d ORDER BY `id` ASC LIMIT 1",
						$template_id
					))
				) return array();
			}
			else {
				$templates = Symphony::Database()->fetch("SELECT * FROM `tbl_members_email_templates` ORDER BY `id` ASC");
			}

			foreach($templates as $template) {
				if(!in_array($template['id'], array_keys(EmailTemplateManager::$_pool))) {
					EmailTemplateManager::$_pool[$template['id']] = new EmailTemplate($template);
					EmailTemplateManager::$_pool[$template['id']]->getRoles();
				}

				$result[] = EmailTemplateManager::$_pool[$role['id']];
			}

			return $return_single ? current($result) : $result;
		}
	}

	Class EmailTemplate {

		private $settings = array();

		public function __construct(Array $settings){
			$this->setArray($settings);

			$this->set('roles', array());
		}

		public function set($name, $value) {
			$this->settings[$name] = $value;
		}

		public function setArray(Array $array) {
			foreach($array as $name => $value) {
				$this->set($name, $value);
			}
		}

		public function get($name = null) {
			if(is_null($name)) return $this->settings;

			if(!array_key_exists($name, $this->settings)) return null;

			return $this->settings[$name];
		}

		public function getRoles() {
			$roles = Symphony::Database()->fetch(sprintf("
					SELECT et.role_id
					FROM `tbl_members_email_templates_role_mapping` AS `et`
					WHERE et.email_template_id = %d
				", $this->get('id')
			));

			$et_roles = array();
			foreach($roles as $role_id) {
				$role = RoleManager::fetch($role_id);

				if($role) $et_roles[$role_id] = $role;
			}

			$this->set('roles', $et_roles);
		}

		public function send($member_id, Array $vars = array()) {
			$member = self::$_Members->Member->fetchMemberFromID($member_id);

			if(!$member) return false;

			try {
				$email = Email::create();

				$email->recipients = $member->getData(extension_Members::getConfigVar('email'), true)->value;

				$email->subject = EmailTemplate::__replaceFieldsInString(
					EmailTemplate::__replaceVarsInString($this->get('subject'), $vars), $member
				);

				$email->text_plain = EmailTemplate::__replaceFieldsInString(
					EmailTemplate::__replaceVarsInString($this->get('body'), $vars), $member
				);

				return $email->send();
			}
			catch(Exception $e){
			    throw new SymphonyErrorPage('Error sending email. ' . $e->getMessage());
			}
		}

		/**
		 * Given a `$string` and an `Entry` object, this function will replace
		 * params with the values from the Entry. For instance, a field called
		 * animal, used in a string like {$animal} would replace with the value of
		 * that field, ie. 'Hairy Nosed Wombat'.
		 * Should {$animal::handle} be used, this will be replaced with the handle
		 * version of this value, ie. 'hairy-nosed-wombat'
		 *
		 * @param string $string
		 * @param Entry $entry
		 * @return string
		 */
		public static function __replaceFieldsInString($string, Entry $entry) {
			$fields = EmailTemplate::__findFieldsInString($string);

			if(is_array($fields) && !empty($fields)){
				$FieldManager = new FieldManager(Symphony::Engine());

				foreach($fields as $field_id) {
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

		/**
		 * Given a `string`, search the active Members section to see if any
		 * tokens can be mapped to fields of that section.
		 *
		 * @param string $string
		 * @return array
		 */
		public static function __findFieldsInString($string) {
			preg_match_all('/{\$([^:}]+)(::handle)?}/', $string, $matches);

			$field_handles = array_unique($matches[1]);

			if(!is_array($field_handles) || empty($field_handles)) return array();

			return Symphony::Database()>fetch(sprintf("
					SELECT `id`
					FROM `tbl_fields`
					WHERE `element_name` IN ('%s')
					AND `parent_section` = %d
				", implode("', '", $field_handles), extension_Members::getMembersSection()
			));
		}

		/**
		 * Given a string and an associative array of values, this function will
		 * replace any tokens in the string with the values as found in the `$vars`
		 * array. For example, if `$vars` was array('name' => 'Brendan') and
		 * `$string` was {$name}, the resulting string would be 'Brendan'.
		 *
		 * @param string $string
		 * @param array $vars
		 * @return string
		 */
		public static function __replaceVarsInString($string, array $vars) {
			foreach($vars as $key => $value){
				$string = str_replace(sprintf('{$%s}', $key), $value, $string);
			}
			return $string;
		}

	}
