<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Activate_Account extends Event{

		private static $_fields;

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
			return '
				<p>This event uses a code to activate an inactive member account.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. A text field accepts the member&#39;s activation code, and a hidden field redirects the member when activation is successful.</p>
				<pre class="XML"><code>&lt;form action="" method="post"&gt;
	&lt;label&gt;Code: &lt;input name="fields[code]" type="text" value="{$code}"/&gt;&lt;/label&gt;
	&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Activate Account"/&gt;
	&lt;input type="hidden" name="redirect" value="{$root}/activate/success/"/&gt;
&lt;/form&gt;</code></pre>
				<h3>Example Response XML</h3>
				<p>On failure...</p>
				<pre class="XML"><code>&lt;'.self::ROOTELEMENT.' result="error"&gt;
	&lt;error&gt;Activation failed. Code was invalid.&lt;/error&gt;
&lt;/'.self::ROOTELEMENT.'&gt;</code></pre>
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
					", extension_Members::getConfigVar('member_section'))
				);

				if(!empty($rows)){
					foreach($rows as $r){
						self::$_fields[$r['handle']] = $r['id'];
					}
				}
			}
		}

		protected function __trigger(){

			$result = new XMLElement(self::ROOTELEMENT);

			self::__init();
			$success = false;

			$Members = Frontend::instance()->ExtensionManager->create('members');

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

			$activation_row = Symphony::Database()->fetchRow(0, sprintf(
					"SELECT * FROM `tbl_members_codes` WHERE `code` = '%s' AND `member_id` = %d LIMIT 1",
					$db->cleanValue($_POST['fields']['code']),
					(int)$Members->Member->get('id')
				)
			);

			// No code, you are a spy!
			if(!empty($activation_row)){
				$success = false;
				$result->appendChild(new XMLElement('error', 'Activation failed. Code was invalid.'));
			}

			else{
				// Got this far, all is well.
				Symphony::Database()->query(sprintf(
					"UPDATE `tbl_entries_data_%d` SET `role_id` = %d WHERE `entry_id` = %d LIMIT 1",
					$Members->roleField(),
					Symphony::Configuration()->get('new_member_default_role', 'members'),
					(int)$Members->Member->get('id')
				));

				extension_Members::purgeCodes((int)$Members->Member->get('id'));

				$entry = $Members->Member->Member;
				$email = $entry->getData(extension_Members::getConfigVar('email_address_field_id', 'members'));
				$name = $entry->getData(self::findFieldID('name'));

				$Members->emailNewMember(
					array(
						'entry' => $entry,
						'fields' => array(
							'username-and-password' => $Members->Member->getData(self::findFieldID('username-and-password')),
							'name' => $name['value'],
							'email-address' => $email['value']
						)
					)
				);

				$success = true;
			}

			if($success == true && isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

			$result->setAttribute('result', ($success === true ? 'success' : 'error'));

			return $result;
		}

	}
