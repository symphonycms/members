<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Recover_Account extends Event {

		const ROOTELEMENT = 'members-recover-account';

		public static function about(){
			return array(
				'name' => 'Members: Recover Account',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://symphony-cms.com',
					'email' => 'team@symphony-cms.com'),
				'version' => '1.0',
				'release-date' => '2011-03-07'
			);
		}

		public function load(){
			if(isset($_POST['action'][self::ROOTELEMENT])) return $this->__trigger();
		}

		public static function documentation(){
			return '
				<p>This event takes a recovery code and a new password for a user. A recovery code is available by outputting the
				Member: Password field after the Member: Reset Password event has executed.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. An input field
				accepts the member\'s recovery code, two password fields (one for password, one to confirm)
				will allow the user to change their password.</p>
				<pre class="XML"><code>
				&lt;form method="post"&gt;
					&lt;label&gt;Recovery Code: &lt;input name="fields[recovery-code]" type="text" value="{$code}"/&gt;&lt;/label&gt;
					&lt;label&gt;Password: &lt;input name="fields[password][password]" type="password" /&gt;&lt;/label&gt;
					&lt;label&gt;Confirm Password: &lt;input name="fields[password][confirm]" type="password" /&gt;&lt;/label&gt;
					&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Recover Account"/&gt;
					&lt;input type="hidden" name="redirect" value="{$root}/"/&gt;
				&lt;/form&gt;
				</code></pre>
				<h3>Example Success XML</h3>
				<pre class="XML"><code>
				&lt;' . self::ROOTELEMENT . ' result="success"/&gt;
				</code></pre>
				<h3>Example Error XML</h3>
				<pre class="XML"><code>
				&lt;' . self::ROOTELEMENT . ' result="error"&gt;
					&lt;error&gt;No Authentication field found&lt;/error&gt;
					&lt;error&gt;Recovery code is a required field&lt;/error&gt;
					&lt;error&gt;No recovery code found&lt;/error&gt;
					&lt;error&gt;Member not found&lt;/error&gt;
					&lt;error&gt;Passwords do not match.&lt;/error&gt;
					&lt;error&gt;Password is too short. It must be at least %d characters.&lt;/error&gt;
					&lt;error&gt;Password is not strong enough.&lt;/error&gt;
				&lt;/' . self::ROOTELEMENT . '&gt;
				</code></pre>
			';
		}

		protected function __trigger(){
			$result = new XMLElement(self::ROOTELEMENT);
			$fields = $_REQUEST['fields'];

			// Check that there is a row with this recovery code and that they
			// request a password reset
			$auth = extension_Members::$fields['authentication'];
			if(!$auth instanceof fieldMemberPassword) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', null, array(
						'type' => 'invalid',
						'message' => __('No Authentication field found')
					))
				);
				return $result;
			}

			if(!isset($fields['recovery-code']) or empty($fields['recovery-code'])) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', null, array(
						'type' => 'missing',
						'message' =>  __('Recovery code is a required field.'),
						'label' => $auth->get('label')
					))
				);
				return $result;
			}

			$row = Symphony::Database()->fetchRow(0, sprintf("
					SELECT `entry_id`, `recovery-code`
					FROM tbl_entries_data_%d
					WHERE reset = 'yes'
					AND `recovery-code` = '%s'
					AND password IS NULL
				", $auth->get('id'), Symphony::Database()->cleanValue($fields['recovery-code'])
			));

			if(empty($row)) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', null, array(
						'type' => 'invalid',
						'message' => __('No recovery code found'),
						'label' => $auth->get('label')
					))
				);

				return $result;
			}
			else {
				// Retrieve Member Entry record
				$entryManager = new EntryManager(Frontend::instance());
				$entry = $entryManager->fetch($row['entry_id']);
				$entry = $entry[0];

				if(!$entry instanceof Entry) {
					$result->setAttribute('result', 'error');
					$result->appendChild(
						new XMLElement('error', null, array(
							'type' => 'invalid',
							'message' =>  __('Member not found.')
						))
					);

					return $result;
				}

				// Create new password using the auth field so simulate the checkPostFieldData
				// and processRawFieldData functions.
				$message = '';
				$status = $auth->checkPostFieldData($fields[$auth->get('element_name')], $message, $row['entry_id']);
				if(Field::__OK__ != $status) {
					$result->setAttribute('result', 'error');
					$result->appendChild(
						new XMLElement('error', null, array(
							'type' => ($status == Field::__MISSING_FIELDS__) ? 'missing' : 'invalid',
							'message' => $message,
							'label' => $auth->get('label')
						))
					);

					return $result;
				}

				// processRawFieldData will encode the user's new password with the current one
				$status = Field::__OK__;
				$data = $auth->processRawFieldData(array(
					'password' => Symphony::Database()->cleanValue($fields[$auth->get('element_name')]['password']),
					'recovery-code' => null,
					'reset' => 'no'
				), $status);

				// Update the database with the new password, removing the recovery code and setting
				// reset to no.
				Symphony::Database()->update($data, 'tbl_entries_data_' . $auth->get('id'), ' `entry_id` = ' . $row['entry_id']);

				// Instead of replicating the same logic, call the UpdatePasswordLogin which will
				// handle relogging in the user.
				$driver = Symphony::ExtensionManager()->create('members');
				$driver->Member->filter_UpdatePasswordLogin(array(
					'entry' => $entry,
					'fields' => array(
						extension_Members::$handles['authentication'] => array(
							'password' => $data['password']
						)
					)
				));

				$result->setAttribute('result', 'success');
			}

			return $result;
		}
	}

