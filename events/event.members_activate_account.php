<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Activate_Account extends Event{

		const ROOTELEMENT = 'members-activate-account';

		public function ignoreRolePermissions() {
			return true;
		}

		public static function about(){
			return array(
				'name' => 'Members: Activate Account',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://symphony-cms.com',
					'email' => 'team@symphony-cms.com'),
				'version' => '1.0',
				'release-date' => '2011-03-23'
			);
		}

		public function load(){
			if(isset($_POST['action'][self::ROOTELEMENT])) return $this->__trigger();
		}

		public static function documentation(){
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
					&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Activate Account"/&gt;
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
					&lt;error&gt;No Activation field found&lt;/error&gt;
					&lt;error&gt;No Identity field found&lt;/error&gt;
					&lt;error&gt;Member not found&lt;/error&gt;
					&lt;error&gt;Member is already activated&lt;/error&gt;
					&lt;error&gt;Activation is a required field&lt;/error&gt;
					&lt;error&gt;Activation error. Code was invalid or has expired.&lt;/error&gt;
				&lt;/' . self::ROOTELEMENT . '&gt;
				</code></pre>
			';
		}

		protected function __trigger(){
			$result = new XMLElement(self::ROOTELEMENT);
			$fields = $_POST['fields'];

			// Add POST values to the Event XML
			$post_values = new XMLElement('post-values');

			// Create the post data cookie element
			if (is_array($fields) && !empty($fields)) {
				General::array_to_xml($post_values, $fields, true);
			}

			// Read the activate account template from the Configuration if it exists
			// This is required for the Email Template Filter/Email Template Manager
			if(!is_null(extension_Members::getConfigVar('activate-account-template'))) {
				$this->eParamFILTERS = array(
					extension_Members::getConfigVar('activate-account-template')
				);
			}

			$activation = extension_Members::$fields['activation'];
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

			// Retrieve Member Entry record
			$driver = Symphony::ExtensionManager()->create('members');
			$entry = $driver->Member->fetchMemberFromID($member_id);

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

			// Update the database setting activation to yes.
			Symphony::Database()->update($data, 'tbl_entries_data_' . $activation->get('id'), ' `entry_id` = ' . $member_id);

			// Update our `$entry` object with the new activation data
			$entry->setData($activation->get('id'), $data);

			// Simulate an array to login with.
			$data_fields = array_merge($fields, array(
				extension_Members::$handles['authentication'] => $entry->getData(extension_Members::getConfigVar('authentication'), true)->password
			));

			// Only login if the Activation field allows auto login.
			if($activation->get('auto_login') == 'no' || $driver->Member->login($data_fields, true)) {
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
			}
			// User didn't login, unknown error.
			else if($activation->get('auto_login') == 'yes') {
				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

				$result->setAttribute('result', 'error');
			}

			$result->appendChild($post_values);

			return $result;
		}

	}
