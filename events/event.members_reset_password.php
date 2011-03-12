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
				<p>This event takes a member\'s email address to validate the existence of the Member before,
				resetting their password and generating a recovery code for the user.<br /> This recovery code be seen
				by outputting the Member: Password field in a datasource once this event has completed, or by outputting
				the event result.</p>
				<p>You can set the Email Template Filter for this event from the <a href="' . SYMPHONY_URL . '/system/preferences/">Preferences</a>
				page</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. An input field
				accepts the member\'s email address.</p>
				<pre class="XML"><code>
				&lt;form method="post"&gt;
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
					&lt;error&gt;Member not found&lt;/error&gt;
				&lt;/' . self::ROOTELEMENT . '&gt;
				</code></pre>
			';
		}

		protected function __trigger() {
			$result = new XMLElement(self::ROOTELEMENT);
			$fields = $_POST['fields'];

			// Read the password template from the Configuration and continue
			$this->eParamFILTERS = array(
				'etf-' . extension_Members::getConfigVar('reset-password-template')
			);

			// Check that this Email has an Entry
			$email = extension_Members::$fields['email'];
			$member_id = $email->fetchMemberIDBy($fields[$email->get('element_name')]);

			if(is_null($member_id)) {
				$result->setAttribute('result', 'error');
				$result->appendChild(
					new XMLElement('error', __('Member not found.'))
				);
				return $result;
			}

			// Generate new password
			$newPassword = General::generatePassword();

			// Set the Entry password to be reset and the current timestamp
			$auth = extension_Members::$fields['authentication'];
			$status = Field::__OK__;
			$data = $auth->processRawFieldData(array(
				'password' => sha1($newPassword . $member_id),
			), $status);

			$data['recovery-code'] = $data['password'];
			$data['password'] = null;
			$data['length'] = 0;
			$data['strength'] = 'weak';
			$data['reset'] = 'yes';
			$data['expires'] = DateTimeObj::get('Y-m-d H:i:s', time());

			Symphony::Database()->update($data, 'tbl_entries_data_' . $auth->get('id'), ' `entry_id` = ' . $member_id);

			// We now need to simulate the EventFinalSaveFilter which the EmailTemplateFilter
			// uses to send emails.
			$filter_errors = array();
			$entryManager = new EntryManager(Frontend::instance());
			$entry = $entryManager->fetch($member_id);

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
					'entry'		=> $entry[0]
				)
			);

			//if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

			$result->setAttribute('result', 'success');
			$result->appendChild(
				new XMLElement('recovery-code', sha1($newPassword . $member_id))
			);

			return $result;
		}

	}
