<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');
	require_once EXTENSIONS . '/members/lib/class.membersevent.php';

	Class eventMembers_Generate_Recovery_Code extends MembersEvent {

		const ROOTELEMENT = 'members-generate-recovery-code';

		public static function about(){
			return array(
				'name' => 'Members: Generate Recovery Code',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://symphony-cms.com',
					'email' => 'team@symphony-cms.com'),
				'version' => 'Members 1.0',
				'release-date' => '2011-05-10'
			);
		}

		public function load(){
			if(isset($_POST['action'][self::ROOTELEMENT])) return $this->__trigger();
		}

		public static function documentation() {
			// Fetch all the Email Templates available and add to the end of the documentation
			$templates = extension_Members::fetchEmailTemplates();
			$div = new XMLElement('div');

			if(!empty($templates)) {
				$label = new XMLElement('label', __('Generate Recovery Code Email Template'));
				$generate_recovery_code_templates = extension_Members::setActiveTemplate($templates, 'generate-recovery-code-template');
				$label->appendChild(Widget::Select('members[generate-recovery-code-template][]', $generate_recovery_code_templates, array('multiple' => 'multiple')));
				$div->appendChild($label);

				$div->appendChild(Widget::Input('members[event]', 'generate-recovery-code', 'hidden'));
				$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));
			}

			return '
				<p>This event takes a member\'s email address or username to validate the existence of the Member before,
				generating a recovery code for the member. A member\'s password is not reset completely until they enter
				their recovery code through the Members: Reset Password event.<br /> This recovery code be seen
				by outputting the Member: Password field in a datasource once this event has completed, or by outputting
				the event result.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. An input field
				accepts either the member\'s email address or username.</p>
				<pre class="XML"><code>
				&lt;form method="post"&gt;
					&lt;label&gt;Username: &lt;input name="fields[username]" type="text" value="{$username}"/&gt;&lt;/label&gt;
					or
					&lt;label&gt;Email: &lt;input name="fields[email]" type="text" value="{$email}"/&gt;&lt;/label&gt;
					&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Reset Password"/&gt;
					&lt;input type="hidden" name="redirect" value="{$root}/"/&gt;
				&lt;/form&gt;
				</code></pre>
				<h3>More Information</h3>
				<p>For further information about this event, including response and error XML, please refer to the
				<a href="https://github.com/symphonycms/members/wiki/Members%3A-Generate-Recovery-Code">wiki</a>.</p>
				' . $div->generate() . '
			';
		}

		protected function __trigger() {
			$result = new XMLElement(self::ROOTELEMENT);
			$fields = $_POST['fields'];
			$driver = Symphony::ExtensionManager()->create('members');

			// Add POST values to the Event XML
			$post_values = new XMLElement('post-values');

			// Create the post data cookie element
			if (is_array($fields) && !empty($fields)) {
				General::array_to_xml($post_values, $fields, true);
			}

			// If a member is logged in, return early with an error
			if($driver->getMemberDriver()->isLoggedIn()) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', null, array(
						'type' => 'invalid',
						'message' => __('You cannot generate a recovery code while being logged in.')
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Trigger the EventPreSaveFilter delegate. We are using this to make
			// use of the XSS Filter extension that will ensure our data is ok to use
			$this->notifyEventPreSaveFilter($result, $fields, $post_values);
			if($result->getAttribute('result') == 'error') return $result;

			// Add any Email Templates for this event
			$this->addEmailTemplates('generate-recovery-code-template');

			// Check that either a Member: Username or Member: Password field
			// has been detected
			$identity = SymphonyMember::setIdentityField($fields, false);
			if(!$identity instanceof Identity) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', null, array(
						'type' => 'invalid',
						'message' => __('No Identity field found.')
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Check that a member exists first before proceeding.
			if(!isset($fields[$identity->get('element_name')]) or empty($fields[$identity->get('element_name')])) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement($identity->get('element_name'), null, array(
						'type' => 'missing',
						'message' => __('%s is a required field.', array($identity->get('label'))),
						'label' => $identity->get('label')
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			$member_id = $identity->fetchMemberIDBy($fields[$identity->get('element_name')]);
			if(is_null($member_id)) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement($identity->get('element_name'), null, array(
						'type' => 'invalid',
						'message' => __('Member not found.'),
						'label' => $identity->get('label')
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Find the Authentication fiedl
			$auth = extension_Members::getField('authentication');
			$status = Field::__OK__;

			// Generate new password
			$newPassword = $auth->generatePassword();

			$entry = $driver->getMemberDriver()->fetchMemberFromID($member_id);
			$entry_data = $entry->getData();

			// Generate a Recovery Code with the same logic as a normal password
			$data = $auth->processRawFieldData(array(
				'password' => General::hash($newPassword . $member_id, 'sha1'),
			), $status);

			// Set the Entry password to be reset and the current timestamp
			$data['recovery-code'] = $data['password'];
			$data['reset'] = 'yes';
			$data['expires'] = DateTimeObj::get('Y-m-d H:i:s', time());

			// Overwrite the password with the old password data. This prevents
			// a users account from being locked out if it it just reset by a random
			// member of the public
			$data['password'] = $entry_data[$auth->get('id')]['password'];
			$data['length'] = $entry_data[$auth->get('id')]['length'];
			$data['strength'] = $entry_data[$auth->get('id')]['strength'];

			Symphony::Database()->update($data, 'tbl_entries_data_' . $auth->get('id'), ' `entry_id` = ' . $member_id);

			// Trigger the EventFinalSaveFilter delegate. The Email Template Filter
			// and Email Template Manager extensions use this delegate to send any
			// emails attached to this event
			$this->notifyEventFinalSaveFilter($result, $fields, $post_values, $entry);

			// If a redirect is set, redirect, the page won't be able to receive
			// the Event XML anyway
			if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

			$result->setAttribute('result', 'success');
			$result->appendChild(
				new XMLElement('recovery-code', $data['recovery-code'])
			);

			$result->appendChild($post_values);

			return $result;
		}

	}
