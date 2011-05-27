<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Abstract Class MembersEvent extends Event {

		// For the delegates to populate
		public $filter_results = array();
		public $filter_errors = array();

		// Don't allow a user to set permissions for any Members event
		// in the Roles interface.
		public function ignoreRolePermissions() {
			return true;
		}

		// The default filters for an event are the XSS Filter
		public $eParamFILTERS = array(
			'xss-fail'
		);

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * This function is directly copied from Symphony's default event
		 * include. It takes a result from an Event filter and generates XML
		 * to output with the custom events
		 */
		public static function buildFilterElement($name, $status, $message=NULL, array $attr=NULL){
			$ret = new XMLElement('filter', (!$message || is_object($message) ? NULL : $message), array(
				'name' => $name,
				'status' => $status
			));

			if(is_object($message)) $ret->appendChild($message);

			if(is_array($attr)) $ret->setAttributeArray($attr);

			return $ret;
		}

		protected function addEmailTemplates($template) {
			// Read the template from the Configuration if it exists
			// This is required for the Email Template Filter/Email Template Manager
			if(!is_null(extension_Members::getSetting($template))) {
				$this->eParamFILTERS = array_merge(
					$this->eParamFILTERS,
					explode(',',extension_Members::getSetting($template))
				);
			}
		}

	/*-------------------------------------------------------------------------
		Delegates:
	-------------------------------------------------------------------------*/

		protected function notifyEventPreSaveFilter(XMLElement &$result, array $fields, XMLElement $post_values) {
			/**
			 * @delegate EventPreSaveFilter
			 * @param string $context
			 * '/frontend/'
			 * @param array $fields
			 * @param string $event
			 * @param array $messages
			 * @param XMLElement $post_values
			 */
			Symphony::ExtensionManager()->notifyMembers(
				'EventPreSaveFilter',
				'/frontend/',
				array(
					'fields' => &$fields,
					'event' => &$this,
					'messages' => &$this->filter_results,
					'post_values' => &$post_values
				)
			);

			// Logic taken from `event.section.php` to fail should any `$this->filter_results`
			// be returned. This delegate can cause the event to exit early.
			if (is_array($this->filter_results) && !empty($this->filter_results)) {
				$can_proceed = true;

				foreach ($this->filter_results as $fr) {
					list($name, $status, $message, $attributes) = $fr;

					$result->appendChild(
						MembersEvent::buildFilterElement($name, ($status ? 'passed' : 'failed'), $message, $attributes)
					);

					if($status === false) $can_proceed = false;
				}

				if ($can_proceed !== true) {
					$result->setAttribute('result', 'error');
					$result->appendChild($post_values);
					return $result;
				}
			}
		}

		protected function notifyEventFinalSaveFilter(XMLElement &$result, array $fields, XMLElement $post_values, Entry $entry) {
			// We now need to simulate the EventFinalSaveFilter which the EmailTemplateFilter
			// and EmailTemplateManager use to send emails.
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
					'messages'	=> $this->filter_results,
					'errors'	=> &$this->filter_errors,
					'entry'		=> $entry
				)
			);

			// Take the logic from `event.section.php` to append `$this->filter_errors`
			if(is_array($this->filter_errors) && !empty($this->filter_errors)){
				foreach($this->filter_errors as $fr){
					list($name, $status, $message, $attributes) = $fr;

					$result->appendChild(
						MembersEvent::buildFilterElement($name, ($status ? 'passed' : 'failed'), $message, $attributes)
					);
				}
			}
		}

	}
