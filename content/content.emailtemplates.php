<?php

	require_once(TOOLKIT . '/class.ajaxpage.php');

	Class contentExtensionMembersEmailTemplates extends AjaxPage {

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
					new XMLElement('error', __('The Symphony configuration file, <code>/manifest/config.php</code>, is not writable. You will not be able to save changes to preferences.'))
				);
				return;
			}

			$settings = $_POST['members'];

			// Generate Recovery Code
			if(isset($settings['generate-recovery-code-template'])) {
				Symphony::Configuration()->set('generate-recovery-code-template', implode(',', $settings['generate-recovery-code-template']), 'members');
			}

			// Activate Account
			if(isset($settings['activate-account-template'])) {
				Symphony::Configuration()->set('activate-account-template', implode(',', $settings['activate-account-template']), 'members');
			}

			// Regenerate Activation Code
			if(isset($settings['regenerate-activation-code-template'])) {
				Symphony::Configuration()->set('regenerate-activation-code-template', implode(',', $settings['regenerate-activation-code-template']), 'members');
			}

			// Return successful
			if(Administration::instance()->saveConfig()) {
				$this->_status = AjaxPage::STATUS_OK;
				$this->_Result->appendChild(
					new XMLElement('error', __('Preferences saved.'))
				);
			}
		}

	}
