<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/class.event.php');
	require_once(DOCROOT . '/extensions/asdc/lib/class.asdc.php');
		
	Class eventMembers_Reset_Password extends Event{
		
		private static $_fields;
		private static $_sections;
						
		const ROOTELEMENT = 'members-reset-password';				
						
		public static function about(){
			return array(
				'name' => 'Members: Reset Password',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://symphony-cms.com',
					'email' => 'team@symphony-cms.com'),
				'version' => '1.0',
				'release-date' => '2009-11-05',
				'trigger-condition' => 'fields[members-reset-password]');
		}
	
		public function load(){

			if(isset($_POST['action'][self::ROOTELEMENT])){
				if(isset($_POST['fields']['code']) && strlen(trim($_POST['fields']['code'])) > 0){
					return $this->__triggerCode();
				}
				else{
					return $this->__trigger();
				}
			}

		}
		
		public static function documentation(){
			return '		
				
				<h3>Example Form</h3>
				<p><pre><code>
				  &lt;form action="" method="post">
					&lt;p>Supply either username or email address&lt;/p>
				    &lt;input name="member-email-address" type="text"/>
					&lt;input name="member-username" type="text"/>
				    &lt;input name="action['.self::ROOTELEMENT.']" value="go" type="submit"/>
				  &lt;/form></code></pre>				
				</p>
				
				<h3>Example Response</h3>
				<p><code>&lt;'.self::ROOTELEMENT.' sent="true">Email sent&lt;/'.self::ROOTELEMENT.'></code></p>
			
			';
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
		
		private function __triggerCode(){
			
			$result = new XMLElement(self::ROOTELEMENT, NULL, array('step' => '2'));
			$success = false;
			
			$Members = $this->_Parent->ExtensionManager->create('members');
			
			$code = $_POST['fields']['code'];

			self::__init();
			$db = ASDCLoader::instance();

			// Make sure we dont accidently use an expired code
			extension_Members::purgeCodes();

			$code_row = $db->query(
				sprintf(
					"SELECT * FROM `tbl_members_codes` WHERE `code` = '%s' LIMIT 1", 
					$db->escape($code)
				)
			)->current();

			// No code, you are a spy!
			if($code_row !== false){
				extension_Members::purgeCodes($code_row->member_id);
				$success = $Members->sendNewPasswordEmail($code_row->member_id);
			}
			
			$result->setAttribute('status', ($success === true ? 'success' : 'error'));
			
			if($success == false){
				$result->appendChild(new XMLElement('error', 'Sending email containing new password failed.'));
			}
			
			elseif($success == true && isset($_REQUEST['redirect'])){
				redirect($_REQUEST['redirect']);
			}
			
			return $result;

		}
			
		protected function __trigger(){

			$success = true;
			$result = new XMLElement(self::ROOTELEMENT, NULL, array('step' => '1'));
			
			$Members = $this->_Parent->ExtensionManager->create('members');
						
			$username = $email = $code = NULL;
			
			// Username take precedence
			if(isset($_POST['fields']['member-username']) && strlen(trim($_POST['fields']['member-username'])) > 0){
				$username = $_POST['fields']['member-username'];
			}
			
			if(isset($_POST['fields']['member-email-address']) && strlen(trim($_POST['fields']['member-email-address'])) > 0){
				$email = $_POST['fields']['member-email-address'];
			}
			
			if(is_null($username) && is_null($email)){
				$success = false;
				$result->appendChild(new XMLElement('member-username', NULL, array('type' => 'missing')));
				$result->appendChild(new XMLElement('member-email-address', NULL, array('type' => 'missing')));						
			}
			
			else{
				
				$members = array();
				
				if(!is_null($email)){
					$members = $Members->findMemberIDFromEmail($email);
					
					if(empty($members)){
						$result->appendChild(new XMLElement('member-email-address', NULL, array('type' => 'not-found')));	
					}
				}

				if(!is_null($username)){
					$tmp = $Members->findMemberIDFromUsername($username);
					if(is_null($tmp)){
						$result->appendChild(new XMLElement('member-username', NULL, array('type' => 'not-found')));		
					}
					else{
						$members[] = $tmp;
					}
				}
			
				// remove duplicates
				$members = array_unique($members);
				
				if(is_array($members) && !empty($members)){
				
					try{
						
						foreach($members as $member_id){
							$Members->sendResetPasswordEmail($member_id);
						}
						
						$success = true;
					
					}
					catch(Exception $e){
						// Shouldn't get here, but will catch an invalid member ID if it does
						$success = false;
						$result->appendChild(new XMLElement('error', 'Invalid member ID'));
					}
					
				}
				else{
					$success = false;
				}
				
				
			}
			
			if($success == true && isset($_REQUEST['redirect'])){
				redirect($_REQUEST['redirect']);
			}			
			
			$result->setAttribute('status', ($success === true ? 'success' : 'error'));

			return $result;
		}		

	}
