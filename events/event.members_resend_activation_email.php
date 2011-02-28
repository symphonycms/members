<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Resend_Activation_Email extends Event{

		private static $_fields;

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

		public static function findFieldID($handle){
			return self::$_fields[$handle];
		}

		private static function __init(){
			if(!is_array(self::$_fields)){
				self::$_fields = array();

				$rows = Symphony::Database()->fetch(sprintf("
						SELECT f.`element_name` AS `handle`, f.`id`
						FROM `sym_fields` AS `f`
						WHERE f.parent_section = %d
						ORDER BY `id` ASC
					", extension_Members::getMembersSection())
				);

				if(!empty($rows)) foreach($rows as $r){
                    self::$_fields[$r['handle']] = $r['id'];
				}
			}
		}

		protected function __trigger(){

			$result = new XMLElement(self::ROOTELEMENT);

			$success = false;
			self::__init();

			$Members = $this->_Parent->ExtensionManager->create('members');

			if(!get_class($Members) == 'SymphonyMember') {
				$result->appendChild(new XMLElement('notice', 'Unsupported Member Class ' . get_class($Members)));
				return $result;
			}

			$Members->Member->initialiseCookie();

			if($Members->Member->isLoggedIn() !== true){
				$result->appendChild(new XMLElement('error', 'Must be logged in.'));
				$result->setAttribute('status', 'error');
				return $result;
			}

			$Members->Member->initialiseMemberObject();

			// Make sure we dont accidently use an expired code
			extension_Members::purgeCodes();

			$entry = $Members->Member->Member;
			$email = $entry->getData(extension_Members::getConfigVar('email'));
			$name = $entry->getData(self::findFieldID('name'));

			$success = $Members->emailNewMember(
				array(
					'entry' => $entry,
					'fields' => array(
						'username-and-password' => $entry->getData(self::findFieldID('username-and-password')),
						'name' => $name['value'],
						'email-address' => $email['value']
					)
				)
			);

			if($success == true && isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

			$result->setAttribute('result', ($success === true ? 'success' : 'error'));

			return $result;

		}
	}

