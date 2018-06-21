<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');
	require_once EXTENSIONS . '/members/lib/class.membersevent.php';

	Class eventMembers_Regenerate_Activation_Code extends MembersEvent {

		const ROOTELEMENT = 'members-regenerate-activation-code';

		public static function about(){
			return array(
				'name' => 'Members: Regenerate Activation Code',
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
				$label = new XMLElement('label', __('Regenerate Activation Code Email Template'));
				$regenerate_activation_code_templates = extension_Members::setActiveTemplate($templates, 'regenerate-activation-code-template');
				$label->appendChild(Widget::Select('members[regenerate-activation-code-template][]', $regenerate_activation_code_templates, array('multiple' => 'multiple')));
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
			}

			return '
				<p>This event will regenerate an activation code for a user if their current
				activation code has expired. The activation code can be sent to a Member\'s email after
				this event has executed.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. An input field
				accepts either the member\'s email address or username.</p>
				<pre class="XML"><code>
				&lt;form method="post"&gt;
					&lt;label&gt;Username: &lt;input name="fields[username]" type="text" value="{$username}"/&gt;&lt;/label&gt;
					or
					&lt;label&gt;Email: &lt;input name="fields[email]" type="text" value="{$email}"/&gt;&lt;/label&gt;
					&lt;input type="hidden" name="members-section-id" value="{$your-section-id}"/&gt;
					&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Regenerate Activation Code"/&gt;
					&lt;input type="hidden" name="redirect" value="{$root}/"/&gt;
				&lt;/form&gt;
				</code></pre>
				<h3>More Information</h3>
				<p>For further information about this event, including response and error XML, please refer to the
				<a href="https://github.com/symphonycms/members/wiki/Members%3A-Regenerate-Activation-Code">wiki</a>.</p>
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
			$this->addEmailTemplates('regenerate-activation-code-template');

			$activation = $this->driver->getMemberDriver()->section->getField('activation');
			if(!$activation instanceof fieldMemberActivation) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('message', __('No Activation field found.'), array(
						'message-id' => MemberEventMessages::MEMBER_ERRORS
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Check that either a Member: Username or Member: Password field
			// has been detected
			$identity = $this->driver->getMemberDriver()->setIdentityField($fields, false);
			if(!$identity instanceof Identity) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('message', __('No Identity field found.'), array(
						'message-id' => MemberEventMessages::MEMBER_ERRORS
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			if(!isset($fields[$identity->get('element_name')]) or empty($fields[$identity->get('element_name')])) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('message', __('Member event encountered errors when processing.'), array(
						'message-id' => MemberEventMessages::MEMBER_ERRORS
					))
				);
				$result->appendChild(
					new XMLElement($identity->get('element_name'), null, array(
						'label' => $identity->get('label'),
						'type' => 'missing',
						'message-id' => EventMessages::FIELD_MISSING,
						'message' => __('%s is a required field.', array($identity->get('label'))),
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Make sure we dont accidently use an expired code
			$activation->purgeCodes();

			// Check that a member exists first before proceeding.
			$member_id = $identity->fetchMemberIDBy($fields[$identity->get('element_name')]);
			if(is_null($member_id)) {
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
				$result->appendChild($post_values);
				return $result;
			}

			// Check that the current member isn't already activated. If they
			// are, no point in regenerating the code.
			$entry = $this->driver->getMemberDriver()->fetchMemberFromID($member_id);

			if($entry->getData($activation->get('id'), true)->activated == 'yes') {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('message', __('Member event encountered errors when processing.'), array(
						'message-id' => MemberEventMessages::MEMBER_ERRORS
					))
				);
				$result->appendChild(
					new XMLElement($activation->get('element_name'), null, array(
						'label' => $activation->get('label'),
						'type' => 'invalid',
						'message-id' => MemberEventMessages::ACTIVATION_PRE_COMPLETED,
						'message' => __('Member is already activated.'),
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Regenerate the code
			$status = Field::__OK__;
			$data = $activation->processRawFieldData(array(
				'activated' => 'no',
			), $status);

			// If the Member has entry data for the Activation field, update it to yes
			if(array_key_exists((int)$activation->get('id'), $entry->getData())) {
				// Symphony::Database()->update($data, 'tbl_entries_data_' . $activation->get('id'), ' `entry_id` = ' . $member_id);
				Symphony::Database()
					->update('tbl_entries_data_' . $activation->get('id'))
					->set($data)
					->where(['entry_id' => $member_id])
					->execute()
					->success();
			}
			else {
				$data['entry_id'] = $member_id;
				// Symphony::Database()->insert($data, 'tbl_entries_data_' . $activation->get('id'));
				Symphony::Database()
					->insert('tbl_entries_data_' . $activation->get('id'))
					->values($data)
					->execute()
					->success();
			}

			/**
			 * Fired just after a Member has regenerated their activation code
			 * for their account.
			 *
			 * @delegate MembersPostRegenerateActivationCode
			 * @param string $context
			 *  '/frontend/'
			 * @param integer $member_id
			 *  The Member ID of the member who just requested a new activation code
			 * @param string $activation_code
			 *  The new activation code for this Member
			 */
			Symphony::ExtensionManager()->notifyMembers('MembersPostRegenerateActivationCode', '/frontend/', array(
				'member_id' => $member_id,
				'activation_code' => $data['code']
			));

			// Trigger the EventFinalSaveFilter delegate. The Email Template Filter
			// and Email Template Manager extensions use this delegate to send any
			// emails attached to this event
			$this->notifyEventFinalSaveFilter($result, $fields, $post_values, $entry);

			// If a redirect is set, redirect, the page won't be able to receive
			// the Event XML anyway
			if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

			$result->setAttribute('result', 'success');
			$result->appendChild(
				new XMLElement('activation-code', $data['code'])
			);
			$result->appendChild($post_values);

			return $result;
		}

	}
