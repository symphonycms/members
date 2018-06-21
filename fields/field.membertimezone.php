<?php

	require_once TOOLKIT . '/fields/field.select.php';

	Class fieldMemberTimezone extends fieldSelect {

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();
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
			// return Symphony::Database()->query("
			// 	CREATE TABLE IF NOT EXISTS `tbl_fields_membertimezone` (
			// 	  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			// 	  `field_id` INT(11) UNSIGNED NOT NULL,
			// 	  `available_zones` VARCHAR(255) DEFAULT NULL,
			// 	  PRIMARY KEY (`id`),
			// 	  UNIQUE KEY `field_id` (`field_id`)
			// 	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			// ");
			return Symphony::Database()
				->create('tbl_fields_membertimezone')
				->ifNotExists()
				->charset('utf8')
				->collate('utf8_unicode_ci')
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'field_id' => 'int(11)',
					'available_zones' => [
						'type' => 'varchar(255)',
						'null' => true,
					],
				])
				->keys([
					'id' => 'primary',
					'field_id' => 'unique',
				])
				->execute()
				->success();
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
			// return Symphony::Database()->fetchVar('value', 0, sprintf("
			// 		SELECT `value`
			// 		FROM `tbl_entries_data_%d`
			// 		WHERE `entry_id` = '%s'
			// 		LIMIT 1
			// 	", $this->get('id'), $member_id
			// ));
			return Symphony::Database()
				->select(['value'])
				->from('tbl_entries_data_' . $this->get('id'))
				->where(['entry_id' => $member_id])
				->limit(1)
				->execute()
				->variable('value');
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

			$options = array();
			foreach($zones as $zone) {
				$timezones = DateTimeObj::getTimezones($zone);

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

			if ($this->get('required') != 'yes') $groups[] = array(null, false, null);

			$zones = explode(",", $this->get('available_zones'));

			foreach($zones as $zone) {
				$timezones = DateTimeObj::getTimezones($zone);

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

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
			Field::displaySettingsPanel($wrapper, $errors);

			$group = new XMLElement('div', null, array('class' => 'two columns'));

			$label = new XMLElement('label', __('Available Zones'));
			$label->setAttribute('class', 'column');
			$zones = is_array($this->get('available_zones'))
				? $this->get('available_zones')
				: explode(',', $this->get('available_zones'));

			foreach(DateTimeObj::getZones() as $zone => $value) {
				if($value >= 1024) break;

				$options[] = array($zone, in_array($zone, $zones), ucwords(strtolower($zone)));
			}

			$label->appendChild(Widget::Select(
				"fields[{$this->get('sortorder')}][available_zones][]", $options, array('multiple' => 'multiple')
			));

			$group->appendChild($label);
			$wrapper->appendChild($group);

			$div = new XMLElement('div', null, array('class' => 'two columns'));
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

		public function checkFields(array &$errors, $checkForDuplicates = true) {
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

			return FieldManager::saveSettings($id, $fields);
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$label = $this->buildTZSelection($data, $prefix, $postfix);

			if(!is_null($error)) {
				$wrapper->appendChild(Widget::Error($label, $error));
			}
			else {
				$wrapper->appendChild($label);
			}
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null){
			if (!is_array($data) or !isset($data['value']) or empty($data['value'])) return;

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
