<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Activate_Account extends Event{

		const ROOTELEMENT = 'members-activate-account';

		public static function about(){
			return array(
				'name' => 'Members: Activate Account',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://symphony-cms.com',
					'email' => 'team@symphony-cms.com'),
				'version' => '1.0',
				'release-date' => '2011-03-09'
			);
		}

		public function load(){
			if(isset($_POST['action'][self::ROOTELEMENT])) return $this->__trigger();
		}

		public static function documentation(){
			return '
				<p>This event takes a recovery code and a new password for a user. A recovery code is
				can be sent to a Member\'s email after the Member: Reset Password filter has executed.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. An input field
				accepts the member\'s activation code and either the member\'s email address or username.</p>
				<pre class="XML"><code>
				&lt;form method="post"&gt;
					&lt;label&gt;Username: &lt;input name="fields[username]" type="text" value="{$username}"/&gt;&lt;/label&gt;
					or
					&lt;label&gt;Email: &lt;input name="fields[email]" type="text" value="{$email}"/&gt;&lt;/label&gt;
					&lt;label&gt;Activation Code: &lt;input name="fields[activation-code]" type="text" value="{$code}"/&gt;&lt;/label&gt;
					&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Activate Account"/&gt;
					&lt;input type="hidden" name="redirect" value="{$root}/"/&gt;
				&lt;/form&gt;
				</code></pre>
				<h3>Example Error XML</h3>
				<pre class="XML"><code>
				&lt;' . self::ROOTELEMENT . ' result="error"&gt;
					&lt;error&gt;No Activation field found&lt;/error&gt;
					&lt;error&gt;Member not found&lt;/error&gt;
					&lt;error&gt;Activation failed. Code was invalid.&lt;/error&gt;
				&lt;/' . self::ROOTELEMENT . '&gt;
				</code></pre>
			';
		}

		protected function __trigger(){
			$result = new XMLElement(self::ROOTELEMENT);
			$fields = $_REQUEST['fields'];

			$activation = extensionMembers::$fields['activation'];

			if(!$activation instanceof Field) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', __('No Activation field found'))
				);
				return $result;
			}

			// Make sure we dont accidently use an expired code
			$activation->purgeCodes();

			// Check that a member exists first before proceeding.
			$errors = array();
			$identity = SymphonyMember::setIdentityField($fields);
			$member_id = $identity->fetchMemberIDBy($fields, $errors);

			if(is_null($member_id)) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', __('Member not found.'))
				);
				return $result;
			}

			if($activation->isCodeActive($member_id) !== $fields['activation-code']) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', __('Activation failed. Code was invalid.'))
				);
				return $result;
			}

			// Got to here, then everything is awesome.
			$activation->purgeCodes($member_id);

			$data = $activation->processRawFieldData(array(
				'activated' => 'yes',
				'code' => null
			), false);

			// Update the database setting activation to yes.
			Symphony::Database()->update($data, 'tbl_entries_data_' . $activation->get('id'), ' `entry_id` = ' . $member_id);

			// Retrieve Member Entry record
			$entryManager = new EntryManager(Frontend::instance());
			$entry = $entryManager->fetch($member_id);

			// Simulate an array to login with.
			$data_fields = array_merge($fields, array(
				'password' => $entry->getData(extension_Members::getConfigVar('authentication'), true)->password
			));

			$driver = Symphony::ExtensionManager()->create('members');
			if($driver->Member->login($data_fields, true)) {
				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

				$result->setAttribute('result', 'success');
			}
			// User didn't login, unknown error.
			else {
				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

				$result->setAttribute('result', 'error');
			}

			return $result;
		}

	}
