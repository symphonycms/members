<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Check_Recovery_Code extends Event {

		const ROOTELEMENT = 'members-check-recovery-code';

		public static function about(){
			return array(
				'name' => 'Members: Check Recovery Code',
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
				<p>This event takes a recovery code and a new password for a user. A recovery code is
				can be sent to a Member\'s email after the Member: Reset Password filter has executed.</p>
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
					<h3>Example Error XML</h3>
					<pre class="XML"><code>
					&lt;' . self::ROOTELEMENT . ' result="error"&gt;
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
			$row = Symphony::Database()->fetchRow(0, sprintf("
					SELECT `entry_id`, `recovery-code`
					FROM tbl_entries_data_%d
					WHERE reset = 'yes'
					AND recovery-code = '%s'
					AND password IS NULL
				", $auth->get('id'), Symphony::Database()->cleanValue($fields['recovery-code'])
			));

			if(empty($row)) {
				$result->setAttribute('status', 'failed');
				$result->appendChild(
					new XMLElement('error', __('No recovery code found'))
				);

				return $result;
			}
			else {
				// Retrieve Member Entry record
				$entryManager = new EntryManager(Frontend::instance());
				$entry = $entryManager->fetch($row['entry_id']);

				if(!$entry instanceof Entry) {
					$result->setAttribute('status', 'failed');
					$result->appendChild(
						new XMLElement('error', __('Member not found'))
					);

					return $result;
				}

				// Create new password using the auth field so simulate the checkPostFieldData
				// and processRawFieldData functions.
				$message = '';
				if(Field::__OK__ != $auth->checkPostFieldData($fields[$auth->get('element_name')], $message, $row['entry_id'])) {
					$result->setAttribute('status', 'failed');
					$result->appendChild(
						new XMLElement('error', $message)
					);

					return $result;
				}

				// processRawFieldData will encode the user's new password with the current one
				$data = $auth->processRawFieldData(array(
					'password' => $fields[$auth->get('element_name')],
					'recovery-code' => null,
					'reset' => 'no'
				), false);

				// Update the database with the new password, removing the recovery code and setting
				// reset to no.
				Symphony::Database()->update($data, 'tbl_entries_data_' . $auth->get('id'), ' `entry_id` = ' . $row['entry_id']);

				// Instead of replicating the same logic, call the UpdatePasswordLogin which will
				// handle relogging in the user.
				SymphonyMember::filter_UpdatePasswordLogin(array(
					'entry' => $entry,
					'fields' => array(
						'password' => array(
							'password' => $data['password']
						)
					)
				));
			}

			return $result;
		}
	}

