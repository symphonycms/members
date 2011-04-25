<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Generate_Recovery_Code extends Event{

		const ROOTELEMENT = 'members-generate-recovery-code';

		public $eParamFILTERS = array(

		);

		public static function about(){
			return array(
				'name' => 'Members: Generate Recovery Code',
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
				generating a recovery code for the member. A member\'s password is not reset completely until they enter
				their recovery code through the Members: Reset Password event.<br /> This recovery code be seen
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
					&lt;error&gt;You cannot generate a recovery code while being logged in&lt;/error&gt;
					&lt;error&gt;No Identity field found&lt;/error&gt;
					&lt;error&gt;Member not found&lt;/error&gt;
				&lt;/' . self::ROOTELEMENT . '&gt;
				</code></pre>
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

			if($driver->Member->isLoggedIn()) {
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

			// Read the password template from the Configuration if it exists
			// This is required for the Email Template Filter/Email Template Manager
			if(!is_null(extension_Members::getConfigVar('generate-recovery-code-template'))) {
				$this->eParamFILTERS = array(
					extension_Members::getConfigVar('generate-recovery-code-template')
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

			// Generate new password
			$newPassword = General::generatePassword();

			// Set the Entry password to be reset and the current timestamp
			$auth = extension_Members::$fields['authentication'];
			$status = Field::__OK__;

			$entry = $driver->Member->fetchMemberFromID($member_id);
			$entry_data = $entry->getData();

			// Generate a Recovery Code with the same logic as a normal password
			$data = $auth->processRawFieldData(array(
				'password' => General::hash($newPassword . $member_id, 'sha1'),
			), $status);

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

			// We now need to simulate the EventFinalSaveFilter which the EmailTemplateFilter
			// and EmailTemplateManager use to send emails.
			$filter_results = array();
			$filter_errors = array();
			/**
			 * @delegate EventFinalSaveFilter
			 * @param string $context
			 * '/frontend/'
			 * @param array $fields
			 * @param string $event
			 * @param array $messages
			 * @param array $errors
			 * @param Entry $entry
			 */
			Symphony::ExtensionManager()->notifyMembers(
				'EventFinalSaveFilter', '/frontend/', array(
					'fields'	=> $fields,
					'event'		=> $this,
					'messages'	=> $filter_results,
					'errors'	=> &$filter_errors,
					'entry'		=> $entry
				)
			);

			// If a redirect is set, redirect, the page won't be able to receive
			// the Event XML anyway
			if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

			// Take the logic from `event.section.php` to append `$filter_errors`
			if(is_array($filter_errors) && !empty($filter_errors)){
				foreach($filter_errors as $fr){
					list($name, $status, $message, $attributes) = $fr;

					$result->appendChild(
						extension_Members::buildFilterElement($name, ($status ? 'passed' : 'failed'), $message, $attributes)
					);
				}
			}

			$result->setAttribute('result', 'success');
			$result->appendChild(
				new XMLElement('recovery-code', $data['recovery-code'])
			);

			$result->appendChild($post_values);

			return $result;
		}

	}
