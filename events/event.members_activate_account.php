<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	require_once(DOCROOT . '/extensions/asdc/lib/class.asdc.php');

	Class eventMembers_Activate_Account extends Event{

		private static $_fields;
		private static $_sections;

		const ROOTELEMENT = 'members-activate-account';

		public static function about(){
			return array(
				'name' => 'Members: Activate Account',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://symphony-cms.com',
					'email' => 'team@symphony-cms.com'),
				'version' => '1.0',
				'release-date' => '2009-11-05',
				'trigger-condition' => 'Inactive member logged in + fields[code] + action[members-activate-account]');
		}

		public function load(){
			if(isset($_POST['action'][self::ROOTELEMENT]) && isset($_POST['fields']['code'])) return $this->__trigger();
		}

		public static function documentation(){
			return new XMLElement('p', 'Activates an inactive member account.');
		}

		public static function findSectionID($handle){
			return self::$_sections[$handle];
		}

		public static function findFieldID($handle, $section){
			return self::$_fields[$section][$handle];
		}

		private static function __init(){
			if(!is_array(self::$_fields)){
				self::$_fields = array();

				$rows = ASDCLoader::instance()->query("SELECT s.handle AS `section`, f.`element_name` AS `handle`, f.`id`
					FROM `tbl_fields` AS `f`
					LEFT JOIN `tbl_sections` AS `s` ON f.parent_section = s.id
					ORDER BY `id` ASC");

				if($rows->length() > 0){
					foreach($rows as $r){
						self::$_fields[$r->section][$r->handle] = $r->id;
					}
				}
			}

			if(!is_array(self::$_sections)){
				self::$_sections = array();

				$rows = ASDCLoader::instance()->query("SELECT s.handle, s.id
					FROM `tbl_sections` AS `s`
					ORDER BY s.id ASC");

				if($rows->length() > 0){
					foreach($rows as $r){
						self::$_sections[$r->handle] = $r->id;
					}
				}
			}
		}


		protected function __trigger(){

			$result = new XMLElement(self::ROOTELEMENT);

			self::__init();
			$db = ASDCLoader::instance();

			$success = false;

			$Members = Frontend::instance()->ExtensionManager->create('members');
			$Members->initialiseCookie();

			if($Members->isLoggedIn() !== true){
				$result->appendChild(new XMLElement('error', 'Must be logged in.'));
				$result->setAttribute('status', 'error');
				return $result;
			}

			$Members->initialiseMemberObject();

			// Make sure we dont accidently use an expired code
			extension_Members::purgeCodes();

			$activation_row = $db->query(
				sprintf(
					"SELECT * FROM `tbl_members_codes` WHERE `code` = '%s' AND `member_id` = %d LIMIT 1",
					$db->escape($_POST['fields']['code']),
					(int)$Members->Member->get('id')
				)
			)->current();

			// No code, you are a spy!
			if($activation_row === false){
				$success = false;
				$result->appendChild(new XMLElement('error', 'Activation failed. Code was invalid.'));
			}

			else{
				// Got this far, all is well.
				$db->query(sprintf(
					"UPDATE `tbl_entries_data_%d` SET `role_id` = %d WHERE `entry_id` = %d LIMIT 1",
					$Members->roleField(),
					Symphony::Configuration()->get('new_member_default_role', 'members'),
					(int)$Members->Member->get('id')
				));

				extension_Members::purgeCodes((int)$Members->Member->get('id'));

				$em = new EntryManager($this->_Parent);
				$entry = end($em->fetch((int)$Members->Member->get('id')));

				$email = $entry->getData(self::findFieldID('email-address', 'members'));
				$name = $entry->getData(self::findFieldID('name', 'members'));

				$Members->emailNewMember(
					array(
						'entry' => $entry,
						'fields' => array(
							'username-and-password' => $entry->getData(self::findFieldID('username-and-password', 'members')),
							'name' => $name['value'],
							'email-address' => $email['value']
						)
					)
				);

				$success = true;
			}

			if($success == true && isset($_REQUEST['redirect'])){
				redirect($_REQUEST['redirect']);
			}

			$result->setAttribute('status', ($success === true ? 'success' : 'error'));
			
			
			return $result;
		}
	}

