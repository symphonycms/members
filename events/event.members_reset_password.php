<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Reset_Password extends Event{

		const ROOTELEMENT = 'members-reset-password';

		public $eParamFILTERS = array(

		);

		public static function about(){
			return array(
				'name' => 'Members: Reset Password',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://symphony-cms.com',
					'email' => 'team@symphony-cms.com'),
				'version' => '1.0',
				'release-date' => '2011-03-12'
			);
		}

		public function load(){
			if(isset($_POST['action'][self::ROOTELEMENT])) return $this->__trigger();
		}

		public static function documentation() {
			return '
				<p>This event takes a member\'s email address or username to validate the existence of the Member before,
				resetting their password and generating a recovery code for the member.<br /> This recovery code be seen
				by outputting the Member: Password field in a datasource once this event has completed, or by outputting
				the event result.</p>
				<p>You can set the Email Template for this event from the <a href="' . SYMPHONY_URL . '/system/preferences/">Preferences</a>
				page</p>
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
				<h3>Example Success XML</h3>
				<pre class="XML"><code>
				&lt;' . self::ROOTELEMENT . ' result="success"&gt;
					&lt;recovery-code&gt;{$code}&lt;/recovery-code&gt;
				&lt;/' . self::ROOTELEMENT . '&gt;
				</code></pre>
				<h3>Example Error XML</h3>
				<pre class="XML"><code>
				&lt;' . self::ROOTELEMENT . ' result="error"&gt;
					&lt;error&gt;No Identity field found&lt;/error&gt;
					&lt;error&gt;Member not found&lt;/error&gt;
				&lt;/' . self::ROOTELEMENT . '&gt;
				</code></pre>
			';
		}

		protected function __trigger() {
			$result = new XMLElement(self::ROOTELEMENT);
			$fields = $_POST['fields'];

			// Read the password template from the Configuration if it exists
			// This is required for the Email Template Filter/Email Template Manager
			if(!is_null(extension_Members::getConfigVar('reset-password-template'))) {
				$this->eParamFILTERS = array(
					extension_Members::getConfigVar('reset-password-template')
				);
			}

			// Check that either a Member: Username or Member: Password field
			// has been detected
			$identity = SymphonyMember::setIdentityField($fields, false);
			if(!$identity instanceof Identity) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', null, array(
						'type' => 'invalid',
						'message' => __('No Identity field found')
					))
				);
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
				return $result;
			}

			// Generate new password
			$newPassword = General::generatePassword();

			// Set the Entry password to be reset and the current timestamp
			$auth = extension_Members::$fields['authentication'];
			$status = Field::__OK__;
			$data = $auth->processRawFieldData(array(
				'password' => General::hash($newPassword . $member_id, 'sha1'),
			), $status);

			$data['recovery-code'] = $data['password'];
			$data['password'] = null;
			$data['length'] = 0;
			$data['strength'] = 'weak';
			$data['reset'] = 'yes';
			$data['expires'] = DateTimeObj::get('Y-m-d H:i:s', time());

			Symphony::Database()->update($data, 'tbl_entries_data_' . $auth->get('id'), ' `entry_id` = ' . $member_id);

			// We now need to simulate the EventFinalSaveFilter which the EmailTemplateFilter
			// and EmailTemplateManager use to send emails.
			$filter_errors = array();
			$driver = Symphony::ExtensionManager()->create('members');
			$entry = $driver->Member->fetchMemberFromID($member_id);

			/**
			 * @delegate EventFinalSaveFilter
			 * @param string $context
			 * '/frontend/'
			 * @param array $fields
			 * @param string $event
			 * @param array $filter_errors
			 * @param Entry $entry
			 */
			Symphony::ExtensionManager()->notifyMembers(
				'EventFinalSaveFilter', '/frontend/', array(
					'fields'	=> $fields,
					'event'		=> &$this,
					'errors'	=> &$filter_errors,
					'entry'		=> $entry
				)
			);

			if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

			$result->setAttribute('result', 'success');
			$result->appendChild(
				new XMLElement('recovery-code', General::hash($newPassword . $member_id, 'sha1'))
			);

			return $result;
		}

	}
