<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	require_once(DOCROOT . '/extensions/asdc/lib/class.asdc.php');

	Class eventMembers_Resend_Activation_Email extends Event{

		private static $_fields;
		private static $_sections;

		const ROOTELEMENT = 'members-resend-activation-email';

		public static function about(){
			return array(
				'name' => 'Members: Resend Activation Email',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://symphony-cms.com',
					'email' => 'team@symphony-cms.com'),
				'version' => '1.0',
				'release-date' => '2009-11-05',
				'trigger-condition' => 'Inactive member logged in + action[members-resend-activation-email]');
		}

		public function load(){
			if(isset($_POST['action'][self::ROOTELEMENT])) return $this->__trigger();
		}

		public static function documentation(){
			return '
				<p>This event will resend the activation email to an inactive member.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. All that&#39;s required is a submit button.</p>
				<pre class="XML"><code>&lt;form action="" method="post"&gt;		
	&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Resend Activation Email"/&gt;
&lt;/form&gt;</code></pre>
				<h3>Example Response XML</h3>
				<pre class="XML"><code>&lt;'.self::ROOTELEMENT.' result="success" /&gt;</code></pre>
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

		protected function __trigger(){

			$result = new XMLElement(self::ROOTELEMENT);
			
			$success = false;

			self::__init();
			$db = ASDCLoader::instance();

			$Members = $this->_Parent->ExtensionManager->create('members');
			$Members->initialiseCookie();

			if($Members->isLoggedIn() !== true){
				$result->appendChild(new XMLElement('error', 'Must be logged in.'));
				$result->setAttribute('status', 'error');
				return $result;
			}

			$Members->initialiseMemberObject();

			// Make sure we dont accidently use an expired code
			extension_Members::purgeCodes();

			$em = new EntryManager($this->_Parent);
			$entry = end($em->fetch((int)$Members->Member->get('id')));

			$email = $entry->getData(self::findFieldID('email-address', 'members'));
			$name = $entry->getData(self::findFieldID('name', 'members'));

			$success = $Members->emailNewMember(
				array(
					'entry' => $entry,
					'fields' => array(
						'username-and-password' => $entry->getData(self::findFieldID('username-and-password', 'members')),
						'name' => $name['value'],
						'email-address' => $email['value']
					)
				)
			);


			if($success == true && isset($_REQUEST['redirect'])){
				redirect($_REQUEST['redirect']);
			}

			$result->setAttribute('result', ($success === true ? 'success' : 'error'));

			return $result;

		}
	}

