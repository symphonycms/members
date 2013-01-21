<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');
	require_once EXTENSIONS . '/members/lib/class.membersevent.php';

	Class eventMembers_Activate_Account extends MembersEvent {

		const ROOTELEMENT = 'members-activate-account';

		public static function about(){
			return array(
				'name' => 'Members: Activate Account',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://getsymphony.com',
					'email' => 'team@getsymphony.com'),
				'version' => 'Members 1.0',
				'release-date' => '2011-05-10'
			);
		}

		public function load(){
			if(isset($_POST['action'][self::ROOTELEMENT])) return $this->__trigger();
		}

		public static function documentation(){
			// Fetch all the Email Templates available and add to the end of the documentation
			$templates = extension_Members::fetchEmailTemplates();
			$div = new XMLElement('div');

			if(!empty($templates)) {
				// Template
				$label = new XMLElement('label', __('Activate Account Email Template'));
				$activate_account_templates = extension_Members::setActiveTemplate($templates, 'activate-account-template');
				$label->appendChild(Widget::Select('members[activate-account-template][]', $activate_account_templates, array('multiple' => 'multiple')));
				$div->appendChild($label);
			}

			// Auto Login
			$div->appendChild(
				Widget::Input("members[auto-login]", 'no', 'hidden')
			);
			$label = new XMLElement('label');
			$input = Widget::Input("members[auto-login]", 'yes', 'checkbox');

			if (extension_Members::getSetting('activate-account-auto-login') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label->setValue(__('%s Automatically log the member in after activation', array($input->generate())));
			$div->appendChild($label);

			// Add Save Changes
			$div->appendChild(Widget::Input('members[event]', 'activate-account', 'hidden'));
			$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

			return '
				<p>This event takes an activation code and an identifier for the Member (either Email or Username) to activate their account.
				An activation code is available by outputting your Activation field in a Datasource after the registration event has executed.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. An input field
				accepts the member\'s activation code and either the member\'s email address or username.</p>
				<pre class="XML"><code>
				&lt;form method="post"&gt;
					&lt;label&gt;Username: &lt;input name="fields[username]" type="text" value="{$username}"/&gt;&lt;/label&gt;
					or
					&lt;label&gt;Email: &lt;input name="fields[email]" type="text" value="{$email}"/&gt;&lt;/label&gt;
					&lt;label&gt;Activation: &lt;input name="fields[activation]" type="text" value="{$code}"/&gt;&lt;/label&gt;
					&lt;input type="hidden" name="members-section-id" value="{$your-section-id}"/&gt;
					&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Activate Account"/&gt;
					&lt;input type="hidden" name="redirect" value="{$root}/"/&gt;
				&lt;/form&gt;
				</code></pre>
				<h3>More Information</h3>
				<p>For further information about this event, including response and error XML, please refer to the
				<a href="https://github.com/symphonycms/members/wiki/Members%3A-Activate-Account">wiki</a>.</p>
				' . $div->generate() . '
			';
		}

		protected function __trigger(){
			$result = new XMLElement(self::ROOTELEMENT);
			$fields = $_POST['fields'];
			$this->driver = Symphony::ExtensionManager()->create('members');

			// Add POST values to the Event XML
			$post_values = new XMLElement('post-values');

			// Create the post data cookie element
			if (is_array($fields) && !empty($fields)) {
				General::array_to_xml($post_values, $fields, true);
			}

			// Set the section ID
			$result = $this->setMembersSection($result, $_REQUEST['members-section-id']);
			if($result->getAttribute('result') === 'error') {
				$result->appendChild($post_values);
				return $result;
			}

			// Trigger the EventPreSaveFilter delegate. We are using this to make
			// use of the XSS Filter extension that will ensure our data is ok to use
			$this->notifyEventPreSaveFilter($result, $fields, $post_values);
			if($result->getAttribute('result') == 'error') return $result;

			// Add any Email Templates for this event
			$this->addEmailTemplates('activate-account-template');

			// Do sanity checks on the incoming data
			$activation = $this->driver->getMemberDriver()->section->getField('activation');
			if(!$activation instanceof fieldMemberActivation) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', null, array(
						'type' => 'invalid',
						'message' => __('No Activation field found.')
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Check that either a Member: Username or Member: Email field has been detected
			$identity = $this->driver->getMemberDriver()->setIdentityField($fields, false);
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

			// Ensure that the Member: Activation field has been provided
			if(!isset($fields[$activation->get('element_name')]) or empty($fields[$activation->get('element_name')])) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement($activation->get('element_name'), null, array(
						'type' => 'missing',
						'message' => __('%s is a required field.', array($activation->get('label'))),
						'label' => $activation->get('label')
					))
				);
				$result->appendChild($post_values);
				return $result;
			}
			else {
				$fields[$activation->get('element_name')] = trim($fields[$activation->get('element_name')]);
			}

			// Check that a member exists first before proceeding.
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

			// Retrieve Member's entry
			$entry = $this->driver->getMemberDriver()->fetchMemberFromID($member_id);

			if($entry->getData($activation->get('id'), true)->activated == 'yes') {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement($activation->get('element_name'), null, array(
						'type' => 'invalid',
						'message' => __('Member is already activated.'),
						'label' => $activation->get('label')
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Make sure we dont accidently use an expired code
			$activation->purgeCodes();

			$code = $activation->isCodeActive($member_id);
			if($code['code'] != $fields[$activation->get('element_name')]) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement($activation->get('element_name'), null, array(
						'type' => 'invalid',
						'message' => __('Activation error. Code was invalid or has expired.'),
						'label' => $activation->get('label')
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Got to here, then everything is awesome.
			$status = Field::__OK__;
			$data = $activation->processRawFieldData(array(
				'activated' => 'yes',
				'timestamp' => DateTimeObj::get('Y-m-d H:i:s', time()),
				'code' => null
			), $status);

			// If the Member has entry data for the Activation field, update it to yes
			if(array_key_exists((int)$activation->get('id'), $entry->getData())) {
				Symphony::Database()->update($data, 'tbl_entries_data_' . $activation->get('id'), ' `entry_id` = ' . $member_id);
			}
			else {
				$data['entry_id'] = $member_id;
				Symphony::Database()->insert($data, 'tbl_entries_data_' . $activation->get('id'));
			}

			// Update our `$entry` object with the new activation data
			$entry->setData($activation->get('id'), $data);

			// Simulate an array to login with.
			$data_fields = array_merge($fields, array(
				$this->driver->getMemberDriver()->section->getFieldHandle('authentication') => $entry->getData($this->driver->getMemberDriver()->section->getField('authentication')->get('id'), true)->password
			));

			/**
			 * Fired just after a Member has successfully activated their
			 * account. This delegate is fired just before the user is logged
			 * in (if the auto login setting is truthy)
			 *
			 * @delegate MembersPostActivation
			 * @param string $context
			 *  '/frontend/'
			 * @param integer $member_id
			 *  The Member ID of the member who just activated their account
			 */
			Symphony::ExtensionManager()->notifyMembers('MembersPostActivation', '/frontend/', array(
				'member_id' => $member_id
			));

			// Only login if the Activation field allows auto login.
			if(extension_Members::getSetting('activate-account-auto-login') == 'no' || $this->driver->getMemberDriver()->login($data_fields, true)) {
				// Trigger the EventFinalSaveFilter delegate. The Email Template Filter
				// and Email Template Manager extensions use this delegate to send any
				// emails attached to this event
				$this->notifyEventFinalSaveFilter($result, $fields, $post_values, $entry);

				// If a redirect is set, redirect, the page won't be able to receive
				// the Event XML anyway
				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

				$result->setAttribute('result', 'success');
			}

			// User didn't login, unknown error.
			else if(extension_Members::getSetting('activate-account-auto-login') == 'yes') {
				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

				$result->setAttribute('result', 'error');
			}

			$result->appendChild($post_values);

			return $result;
		}

	}
