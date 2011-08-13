<?php

	require_once(TOOLKIT . '/fields/field.select.php');

	Class fieldMemberTimezone extends fieldSelect {

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Member: Timezone');
			$this->_showassociation = false;
		}

		public function mustBeUnique() {
			return true;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public static function createSettingsTable() {
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_membertimezone` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  `available_zones` VARCHAR(255) DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * This function will return the offset value for a particular `$member_id`
		 * The offset is the number of hours +/- from GMT
		 *
		 * @param integer $member_id
		 * @return string
		 *  ie. Africa/Asmara
		 */
		public function getMemberTimezone($member_id) {
			return Symphony::Database()->fetchVar('value', 0, sprintf("
					SELECT `value`
					FROM `tbl_entries_data_%d`
					WHERE `entry_id` = '%s'
					LIMIT 1
				", $this->get('id'), $member_id
			));
		}

		/**
		 * This functions acts as a standard way to get the zones
		 * available on the system. For PHP5.2, these constants are
		 * just copied from PHP5.3
		 *
		 * @link http://au2.php.net/manual/en/class.datetimezone.php
		 * @return array
		 */
		public function getZones() {
			if(PHP_VERSION_ID >= 50300) {
				$ref = new ReflectionClass('DateTimeZone');
				return $ref->getConstants();
			}
			else {
				return array(
					'AFRICA' => 1,
					'AMERICA' => 2,
					'ANTARCTICA' => 4,
					'ARCTIC' => 8,
					'ASIA' => 16,
					'ATLANTIC' => 32,
					'AUSTRALIA' => 64,
					'EUROPE' => 128,
					'INDIAN' => 256,
					'PACIFIC' => 512,
					'UTC' => 1024
				);
			}
		}

		/**
		 * This functions acts as a standard way to get the timezones
		 * regardless of PHP version. It accepts a single parameter,
		 * zone, which returns the timezones associated with that 'zone'
		 *
		 * @link http://au2.php.net/manual/en/class.datetimezone.php
		 * @link http://au2.php.net/manual/en/datetimezone.listidentifiers.php
		 * @param string $zone
		 *  The zone for the timezones the field wants. This maps to the
		 *  DateTimeZone constants
		 * @return array
		 */
		public function getTimezones($zone) {
			// PHP5.3 supports the `$what` parameter of the listIdentifiers function
			if(PHP_VERSION_ID >= 50300) {
				return DateTimeZone::listIdentifiers(constant('DateTimeZone::' . $zone));
			}
			else {
				$timezones = DateTimeZone::listIdentifiers();

				foreach($timezones as $index => $timezone) {
					if(stripos($timezone, $zone) === false) unset($timezones[$index]);
				}

				return $timezones;
			}
		}

		/**
		 * Creates a list of Timezones for the With Selected dropdown in the backend.
		 * This list has the limitation that the timezones cannot be grouped as the
		 * With Selected menu already uses `<optgroup>` to separate the toggling of
		 * different Field data.
		 *
		 * @return array
		 */
		public function getToggleStates() {
			$zones = explode(",", $this->get('available_zones'));

			foreach($zones as $zone) {
				$timezones = $this->getTimezones($zone);

				$options = array();
				foreach($timezones as $timezone) {
					$tz = new DateTime('now', new DateTimeZone($timezone));

					$options[$timezone] = sprintf("%s %s",
						str_replace('_', ' ', substr(strrchr($timezone, '/'),1)),
						$tz->format('P')
					);
				}
			}

			return $options;
		}

		/**
		 * Builds a XMLElement containing a `<select>` with all the available timezone
		 * options grouped by the different DateTimeZone constants allowed by an instance
		 * of this field. Developers can select what Timezones are available from the
		 * Section Editor.
		 *
		 * @link http://www.php.net/manual/en/class.datetimezone.php
		 * @param array $data
		 * @param string $prefix
		 * @param string $postfix
		 * @return XMLElement
		 */
		public function buildTZSelection($data = array(), $prefix = null, $postfix = null) {
			if(is_null($data)) $data = array();

			$groups = array();

			if ($this->get('required') != 'yes') $groups[] = array(NULL, false, NULL);

			$zones = explode(",", $this->get('available_zones'));

			foreach($zones as $zone) {
				$timezones = $this->getTimezones($zone);

				$options = array();
				foreach($timezones as $timezone) {
					$tz = new DateTime('now', new DateTimeZone($timezone));

					$options[] = array($timezone, ($timezone == $data['value']), sprintf("%s %s",
						str_replace('_', ' ', substr(strrchr($timezone, '/'),1)),
						$tz->format('P')
					));
				}

				$groups[] = array('label' => ucwords(strtolower($zone)), 'options' => $options);
			}

			$label = new XMLElement('label', $this->get('label'));
			$label->appendChild(Widget::Select(
				"fields{$prefix}[{$this->get('element_name')}]{$postfix}", $groups
			));

			return $label;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			Field::displaySettingsPanel($wrapper, $errors);

			$label = new XMLElement('label', __('Available Zones'));

			$zones = is_array($this->get('available_zones'))
				? $this->get('available_zones')
				: explode(',', $this->get('available_zones'));

			foreach($this->getZones() as $zone => $value) {
				if($value >= 1024) break;

				$options[] = array($zone, in_array($zone, $zones), ucwords(strtolower($zone)));
			}

			$label->appendChild(Widget::Select(
				"fields[{$this->get('sortorder')}][available_zones][]", $options, array('multiple' => 'multiple')
			));

			$wrapper->appendChild($label);

			$div = new XMLElement('div', null, array('class' => 'compact'));
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

		public function checkFields(&$errors, $checkForDuplicates=true) {
			Field::checkFields($errors, $checkForDuplicates);
		}

		public function commit(){
			if(!Field::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldMemberTimezone::createSettingsTable();

			$fields = array(
				'field_id' => $id
			);

			if(is_array($this->get('available_zones'))) {
				$fields['available_zones'] = implode(",", $this->get('available_zones'));
			}

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$label = $this->buildTZSelection($data, $prefix, $postfix);

			if(!is_null($error)) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $error));
			}
			else {
				$wrapper->appendChild($label);
			}
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			if (!is_array($data) or is_null($data['value'])) return;

			$el = new XMLElement($this->get('element_name'));
			$el->appendChild(
				new XMLElement('name', $data['value'], array(
					'handle'	=> $data['handle']
				))
			);

			// Calculate Timezone Offset
			$tz = new DateTime('now', new DateTimeZone($data['value']));
			$el->appendChild(
				new XMLElement('offset', $tz->format('P'))
			);

			$wrapper->appendChild($el);
		}

		public function getExampleFormMarkup(){
			return $this->buildTZSelection();
		}
	}
