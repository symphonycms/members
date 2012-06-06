<?php

	require_once(TOOLKIT . '/class.ajaxpage.php');

	Class contentExtensionMembersEvents extends AjaxPage {

		public function view() {
			// Ensure we have been set $_POST data from Members events
			if(!array_key_exists('members', $_POST)) {
				$this->_status = AjaxPage::STATUS_BAD;
				return;
			}
			// Check that the CONFIG is writable
			else if (!is_writable(CONFIG)) {
				$this->_status = AjaxPage::STATUS_BAD;
				$this->_Result->appendChild(
					new XMLElement('message', __('The Symphony configuration file, <code>/manifest/config.php</code>, is not writable. You will not be able to save changes to preferences.'))
				);
				return;
			}

			$settings = $_POST['members'];

			// Generate Recovery Code
			if(isset($settings['generate-recovery-code-template'])) {
				Symphony::Configuration()->set('generate-recovery-code-template', implode(',', array_filter($settings['generate-recovery-code-template'])), 'members');
			}
			// If no template was set, then the user selected nothing,
			// so remove the template preference
			else if($settings['event'] == 'generate-recovery-code') {
				Symphony::Configuration()->remove('generate-recovery-code-template', 'members');
			}

			// Reset Password
			if(isset($settings['reset-password-template'])) {
				Symphony::Configuration()->set('reset-password-template', implode(',', array_filter($settings['reset-password-template'])), 'members');
			}
			else if($settings['event'] == 'reset-password') {
				Symphony::Configuration()->remove('reset-password-template', 'members');
			}

			if($settings['event'] == 'reset-password') {
				Symphony::Configuration()->set('reset-password-auto-login', $settings['auto-login'], 'members');
			}

			// Regenerate Activation Code
			if(isset($settings['regenerate-activation-code-template'])) {
				Symphony::Configuration()->set('regenerate-activation-code-template', implode(',', array_filter($settings['regenerate-activation-code-template'])), 'members');
			}
			else if($settings['event'] == 'regenerate-activation-code') {
				Symphony::Configuration()->remove('regenerate-activation-code-template', 'members');
			}

			// Activate Account
			if(isset($settings['activate-account-template'])) {
				Symphony::Configuration()->set('activate-account-template', implode(',', array_filter($settings['activate-account-template'])), 'members');
			}
			else if($settings['event'] == 'activate-account') {
				Symphony::Configuration()->remove('activate-account-template', 'members');
			}

			if($settings['event'] == 'activate-account') {
				Symphony::Configuration()->set('activate-account-auto-login', $settings['auto-login'], 'members');
			}

			// Return successful
			if(Symphony::Configuration()->write()) {
				$this->_status = AjaxPage::STATUS_OK;
				$this->_Result->appendChild(
					new XMLElement('message', __('Preferences saved.'))
				);
				$this->_Result->appendChild(
					new XMLElement('timestamp', '<![CDATA[' . Widget::Time(null,__SYM_TIME_FORMAT__)->generate() . ']]>')
				);
			}
		}

	}
