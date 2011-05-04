<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Reset_Password extends Event {

		const ROOTELEMENT = 'members-reset-password';

		public function ignoreRolePermissions() {
			return true;
		}

		public static function about(){
			return array(
				'name' => 'Members: Reset Password',
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
				<p>This event takes a recovery code and a new password for a member. Should the recovery code
				be correct and the new password validate, the member\'s password is changed to their new password.<br />
				A recovery code is available by outputting the
				Member: Password field after the Member: Generate Recovery Code event has executed.</p>
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

			// Add POST values to the Event XML
			$post_values = new XMLElement('post-values');

			// Create the post data cookie element
			if (is_array($fields) && !empty($fields)) {
				General::array_to_xml($post_values, $fields, true);
			}

			// Check that there is a row with this recovery code and that they
			// request a password reset
			$auth = extension_Members::$fields['authentication'];
			if(!$auth instanceof fieldMemberPassword) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', null, array(
						'type' => 'invalid',
						'message' => __('No Authentication field found.')
					))
				);
				$result->appendChild($post_values);
				return $result;
			}

			// Check that either a Member: Username or Member: Email field
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

			if(!isset($fields[extension_Members::$handles['authentication']]['recovery-code']) or empty($fields[extension_Members::$handles['authentication']]['recovery-code'])) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement($auth->get('element_name'), null, array(
						'type' => 'missing',
						'message' =>  __('Recovery code is a required field.'),
						'label' => $auth->get('label')
					))
				);

				$result->appendChild($post_values);
				return $result;
			}

			$row = Symphony::Database()->fetchRow(0, sprintf("
					SELECT `entry_id`, `recovery-code`
					FROM tbl_entries_data_%d
					WHERE reset = 'yes'
					AND `recovery-code` = '%s'
				", $auth->get('id'), Symphony::Database()->cleanValue($fields[extension_Members::$handles['authentication']]['recovery-code'])
			));

			if(empty($row)) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement($auth->get('element_name'), null, array(
						'type' => 'invalid',
						'message' => __('No recovery code found.'),
						'label' => $auth->get('label')
					))
				);
			}
			else {
				// Retrieve Member Entry record
				$driver = Symphony::ExtensionManager()->create('members');
				$entry = $driver->Member->fetchMemberFromID($row['entry_id']);

				// Check that the given Identity data matches the Member that the
				// recovery code is for
				$member_id = $identity->fetchMemberIDBy($fields[$identity->get('element_name')]);

				if(!$entry instanceof Entry || $member_id != $row['entry_id']) {
					$result->setAttribute('result', 'error');
					$result->appendChild(
						new XMLElement($auth->get('element_name'), null, array(
							'type' => 'invalid',
							'message' =>  __('Member not found.')
						))
					);
					$result->appendChild($post_values);
					return $result;
				}

				// Check that the recovery code is still valid and has not expired
				if(is_null(Symphony::Database()->fetchVar('entry_id', 0, sprintf("
						SELECT `entry_id`
						FROM `tbl_entries_data_%d`
						WHERE `entry_id` = %d
						AND DATE_FORMAT(expires, '%%Y-%%m-%%d %%H:%%i:%%s') > '%s'
						LIMIT 1
					",
					$auth->get('id'), $member_id, DateTimeObj::get('Y-m-d H:i:s', strtotime('now - '. $auth->get('code_expiry')))
				)))) {
					$result->setAttribute('result', 'error');
					$result->appendChild(
						new XMLElement($auth->get('element_name'), null, array(
							'type' => 'invalid',
							'message' => __('Recovery code has expired.'),
							'label' => $auth->get('label')
						))
					);
					$result->appendChild($post_values);
					return $result;
				}

				// Create new password using the auth field so simulate the checkPostFieldData
				// and processRawFieldData functions.
				$message = '';
				$status = $auth->checkPostFieldData($fields[$auth->get('element_name')], &$message, $member_id);
				if(Field::__OK__ != $status) {
					$result->setAttribute('result', 'error');
					$result->appendChild(
						new XMLElement($auth->get('element_name'), null, array(
							'type' => ($status == Field::__MISSING_FIELDS__) ? 'missing' : 'invalid',
							'message' => $message,
							'label' => $auth->get('label')
						))
					);
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

				// Instead of replicating the same logic, call the UpdatePasswordLogin which will
				// handle relogging in the user.
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

			$result->appendChild($post_values);

			return $result;
		}
	}

