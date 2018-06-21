<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');
	require_once EXTENSIONS . '/members/lib/class.membersevent.php';

	Class eventMembers_Reset_Password extends MembersEvent {

		const ROOTELEMENT = 'members-reset-password';

		public static function about(){
			return array(
				'name' => 'Members: Reset Password',
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
				//Template
				$label = new XMLElement('label', __('Reset Password Email Template'));
				$reset_password_templates = extension_Members::setActiveTemplate($templates, 'reset-password-template');
				$label->appendChild(Widget::Select('members[reset-password-template][]', $reset_password_templates, array('multiple' => 'multiple')));
				$div->appendChild($label);
			}

			// Auto Login
			$div->appendChild(
				Widget::Input("members[auto-login]", 'no', 'hidden')
			);
			$label = new XMLElement('label');
			$input = Widget::Input("members[auto-login]", 'yes', 'checkbox');

			if (extension_Members::getSetting('reset-password-auto-login') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}

			$label->setValue(__('%s Automatically log the member in after changing their password', array($input->generate())));
			$div->appendChild($label);

			$div->appendChild(Widget::Input('members[event]', 'reset-password', 'hidden'));

			Administration::instance()->Page->Header->setAttribute('class', 'spaced-bottom');
	        Administration::instance()->Page->Context->setAttribute('class', 'spaced-right');
	        Administration::instance()->Page->Contents->setAttribute('class', 'centered-content');
	        $actions = new XMLElement('div');
	        $actions->setAttribute('class', 'actions');
			$actions->appendChild(
				Widget::SVGIconContainer(
					'save',
					Widget::Input(
						'action[save]',
						__('Save Changes'),
						'submit',
						array('accesskey' => 's')
					)
				)
			);

			$actions->appendChild(Widget::SVGIcon('chevron'));

			$div->appendChild($actions);

			return '
				<p>This event requires the user to enter their recovery code and then their new password. Should the recovery code
				be correct and the new password validate, the member\'s password will be changed to their new password.</p><p>
				A recovery code is available by including the
				Member: Password field in a data source on the same page as this event, or by using
				the event\'s result.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. An input field
				accepts the member\'s recovery code, either the member\'s email address or username and two password
				fields (one for password, one to confirm) will allow the member to change their password.</p>
				<pre class="XML"><code>
				&lt;form method="post"&gt;
					&lt;label&gt;Username: &lt;input name="fields[username]" type="text" value="{$username}"/&gt;&lt;/label&gt;
					or
					&lt;label&gt;Email: &lt;input name="fields[email]" type="text" value="{$email}"/&gt;&lt;/label&gt;
					&lt;label&gt;Recovery Code: &lt;input name="fields[password][recovery-code]" type="text" value="{$code}"/&gt;&lt;/label&gt;
					&lt;label&gt;Password: &lt;input name="fields[password][password]" type="password" /&gt;&lt;/label&gt;
					&lt;label&gt;Confirm Password: &lt;input name="fields[password][confirm]" type="password" /&gt;&lt;/label&gt;
					&lt;input type="hidden" name="members-section-id" value="{$your-section-id}"/&gt;
					&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Recover Account"/&gt;
					&lt;input type="hidden" name="redirect" value="{$root}/"/&gt;
				&lt;/form&gt;
				</code></pre>
				<h3>More Information</h3>
				<p>For further information about this event, including response and error XML, please refer to the
				<a href="https://github.com/symphonycms/members/wiki/Members%3A-Reset-Password">wiki</a>.</p>
				' . $div->generate() . '
			';
		}

		protected function __trigger(){
			$result = new XMLElement(self::ROOTELEMENT);
			$fields = $_REQUEST['fields'];
			$this->driver = Symphony::ExtensionManager()->create('members');
			$requested_identity = $fields[extension_Members::getFieldHandle('identity')];

			// Add POST values to the Event XML
			$post_values = new XMLElement('post-values');

			// Create the post data cookie element
			if (is_array($fields) && !empty($fields)) {
				General::array_to_xml($post_values, $fields, true);
			}

			// Set the section ID
			$result = $this->setMembersSection($result, $_REQUEST['members-section-id']);
			if($result->getAttribute('result') === 'error') {
				// We are not calling notifyMembersPasswordResetFailure here,
				// because this is not an authentication error
				$result->appendChild($post_values);
				return $result;
			}

			// Trigger the EventPreSaveFilter delegate. We are using this to make
			// use of the XSS Filter extension that will ensure our data is ok to use
			$this->notifyEventPreSaveFilter($result, $fields, $post_values);
			if($result->getAttribute('result') == 'error') {
				// We are not calling notifyMembersPasswordResetFailure here,
				// because this is not an authentication error
				return $result;
			}

			// Add any Email Templates for this event
			$this->addEmailTemplates('reset-password-template');

			// Check that there is a row with this recovery code and that they
			// request a password reset
			$auth = $this->driver->getMemberDriver()->section->getField('authentication');
			if(!$auth instanceof fieldMemberPassword) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('message', __('No Authentication field found.'), array(
						'message-id' => MemberEventMessages::MEMBER_ERRORS
					))
				);
				$this->notifyMembersPasswordResetFailure($requested_identity);
				$result->appendChild($post_values);
				return $result;
			}

			// Check that either a Member: Username or Member: Email field
			// has been detected
			$identity = $this->driver->getMemberDriver()->setIdentityField($fields, false);
			if(!$identity instanceof Identity) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('message', __('No Identity field found.'), array(
						'message-id' => MemberEventMessages::MEMBER_ERRORS
					))
				);
				$this->notifyMembersPasswordResetFailure($requested_identity);
				$result->appendChild($post_values);
				return $result;
			}

			if(
				!isset($fields[$this->driver->getMemberDriver()->section->getFieldHandle('authentication')]['recovery-code'])
				or empty($fields[$this->driver->getMemberDriver()->section->getFieldHandle('authentication')]['recovery-code'])
			) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('message', __('Member event encountered errors when processing.'), array(
						'message-id' => MemberEventMessages::MEMBER_ERRORS
					))
				);
				$result->appendChild(
					new XMLElement($auth->get('element_name'), null, array(
						'label' => $auth->get('label'),
						'type' => 'missing',
						'message-id' => EventMessages::FIELD_MISSING,
						'message' =>  __('Recovery code is a required field.'),
					))
				);

				$this->notifyMembersPasswordResetFailure($requested_identity);
				$result->appendChild($post_values);
				return $result;
			}

			// $row = Symphony::Database()->fetchRow(0, sprintf("
			// 		SELECT `entry_id`, `recovery-code`
			// 		FROM tbl_entries_data_%d
			// 		WHERE reset = 'yes'
			// 		AND `recovery-code` = '%s'
			// 	", $auth->get('id'), Symphony::Database()->cleanValue($fields[$this->driver->getMemberDriver()->section->getFieldHandle('authentication')]['recovery-code'])
			// ));
			$row = Symphony::Database()
				->select(['entry_id', 'recovery-code'])
				->from('tbl_entries_data_' . $auth->get('id'))
				->where(['reset' => 'yes'])
				->where(['recovery-code' => $fields[$this->driver->getMemberDriver()->section->getFieldHandle('authentication')]['recovery-code']])
				->execute()
				->next();

			if(empty($row)) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('message', __('Member encountered errors.'), array(
						'message-id' => MemberEventMessages::MEMBER_ERRORS
					))
				);
				$result->appendChild(
					new XMLElement($auth->get('element_name'), null, array(
						'label' => $auth->get('label'),
						'type' => 'invalid',
						'message-id' => EventMessages::FIELD_INVALID,
						'message' => __('No recovery code found.'),
					))
				);

				$this->notifyMembersPasswordResetFailure($requested_identity);
			}
			else {
				// Retrieve Member Entry record
				$entry = $this->driver->getMemberDriver()->fetchMemberFromID($row['entry_id']);

				// Check that the given Identity data matches the Member that the
				// recovery code is for
				$member_id = $identity->fetchMemberIDBy($fields[$identity->get('element_name')]);
				if(!$entry instanceof Entry || $member_id != $row['entry_id']) {
					$result->setAttribute('result', 'error');
					$result->appendChild(
						new XMLElement('message', __('Member event encountered errors when processing.'), array(
							'message-id' => MemberEventMessages::MEMBER_ERRORS
						))
					);
					$result->appendChild(
						new XMLElement(
							$identity->get('element_name'),
							null,
							extension_Members::$_errors[$identity->get('element_name')]
						)
					);
					$this->notifyMembersPasswordResetFailure($requested_identity);
					$result->appendChild($post_values);
					return $result;
				}

				// Check that the recovery code is still valid and has not expired
				// if(is_null(Symphony::Database()->fetchVar('entry_id', 0, sprintf("
				// 		SELECT `entry_id`
				// 		FROM `tbl_entries_data_%d`
				// 		WHERE `entry_id` = %d
				// 		AND DATE_FORMAT(expires, '%%Y-%%m-%%d %%H:%%i:%%s') > '%s'
				// 		LIMIT 1
				// 	",
				// 	$auth->get('id'), $member_id, DateTimeObj::get('Y-m-d H:i:s', strtotime('now - '. $auth->get('code_expiry')))
				// )))) {
				if (is_null(
					Symphony::Database()
						->select(['entry_id'])
						->from('tbl_entries_data_' . $auth->get('id'))
						->where(['entry_id' => $member_id])
						->where(['DATE_FORMAT(expires, :date_format)' => ['>' => $auth->get('id'), $member_id, DateTimeObj::get('Y-m-d H:i:s', strtotime('now - '. $auth->get('code_expiry')))]])
						->setValue('date_format', '%%Y-%%m-%%d %%H:%%i:%%s')
						->limit(1)
						->execute()
						->variable('entry_id')
				)) {
					$result->setAttribute('result', 'error');
					$result->appendChild(
						new XMLElement('message', __('Member event encountered errors when processing.'), array(
							'message-id' => MemberEventMessages::MEMBER_ERRORS
						))
					);
					$result->appendChild(
						new XMLElement($auth->get('element_name'), null, array(
							'label' => $auth->get('label'),
							'type' => 'invalid',
							'message-id' => MemberEventMessages::RECOVERY_CODE_INVALID,
							'message' => __('Recovery code has expired.'),
						))
					);
					$this->notifyMembersPasswordResetFailure($requested_identity);
					$result->appendChild($post_values);
					return $result;
				}

				// Create new password using the auth field so simulate the checkPostFieldData
				// and processRawFieldData functions.
				$message = '';

				// For the purposes of this event, the auth field should ALWAYS be required
				// as we have to set a password (ie. handle the case where this field is
				// actually optional) RE: #193
				$auth->set('required', 'yes');
				$status = $auth->checkPostFieldData($fields[$auth->get('element_name')], $message, $member_id);
				if(Field::__OK__ != $status) {
					$result->setAttribute('result', 'error');
					$result->appendChild(
						new XMLElement('message', __('Member event encountered errors when processing.'), array(
							'message-id' => MemberEventMessages::MEMBER_ERRORS
						))
					);
					$result->appendChild(
						new XMLElement($auth->get('element_name'), null, array(
							'type' => ($status == Field::__MISSING_FIELDS__) ? 'missing' : 'invalid',
							'message' => $message,
							'message-id' => ($status == Field::__MISSING_FIELDS__) ? EventMessages::FIELD_MISSING : EventMessages::FIELD_INVALID,
							'label' => $auth->get('label')
						))
					);
					$this->notifyMembersPasswordResetFailure($requested_identity);
					$result->appendChild($post_values);
					return $result;
				}

				// processRawFieldData will encode the user's new password with the current one
				$status = Field::__OK__;
				$data = $auth->processRawFieldData(array(
					'password' => Symphony::Database()->cleanValue($fields[$auth->get('element_name')]['password'])
				), $status);

				$data['recovery-code'] = null;
				$data['reset'] = 'no';
				$data['expires'] = null;

				// Update the database with the new password, removing the recovery code and setting
				// reset to no.
				Symphony::Database()->update($data, 'tbl_entries_data_' . $auth->get('id'), ' `entry_id` = ' . $member_id);

				/**
				 * Fired just after a Member has reset their password.
				 *
				 * @delegate MembersPostResetPassword
				 * @param string $context
				 *  '/frontend/'
				 * @param integer $member_id
				 *  The Member ID of the member who just reset their password
				 */
				Symphony::ExtensionManager()->notifyMembers('MembersPostResetPassword', '/frontend/', array(
					'member_id' => $member_id
				));

				// Trigger the EventFinalSaveFilter delegate. The Email Template Filter
				// and Email Template Manager extensions use this delegate to send any
				// emails attached to this event
				$this->notifyEventFinalSaveFilter($result, $fields, $post_values, $entry);

				if(extension_Members::getSetting('reset-password-auto-login') == "yes") {
					// Instead of replicating the same logic, call the UpdatePasswordLogin which will
					// handle relogging in the user.
					$this->driver->getMemberDriver()->filter_UpdatePasswordLogin(array(
						'entry' => $entry,
						'fields' => array(
							$this->driver->getMemberDriver()->section->getFieldHandle('authentication') => array(
								'password' => Symphony::Database()->cleanValue($fields[$auth->get('element_name')]['password'])
							)
						)
					));
				}

				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

				$result->setAttribute('result', 'success');
			}

			$result->appendChild($post_values);

			return $result;
		}
	}
