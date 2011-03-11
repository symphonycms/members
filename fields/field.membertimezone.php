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
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM;
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

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			Field::displaySettingsPanel($wrapper, $errors);

			$label = new XMLElement('label', __('Available Zones'));

			$zones = is_array($this->get('available_zones'))
				? $this->get('available_zones')
				: explode(',', $this->get('available_zones'));

			// Loop over the DateTimeZone class constants for Zones
			$ref = new ReflectionClass('DateTimeZone');
			foreach($ref->getConstants() as $zone => $value) {
				if($value >= 1024) break;

				$options[] = array($zone, in_array($zone, $zones), ucwords(strtolower($zone)));
			}

			$label->appendChild(Widget::Select(
				"fields[{$this->get('sortorder')}][available_zones][]", $options, array('multiple' => 'multiple')
			));

			$wrapper->appendChild($label);

			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		public function checkFields(&$errors, $checkForDuplicates=true) {
			Field::checkFields(&$errors, $checkForDuplicates);
		}

		public function commit(){
			if(!Field::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldMemberTimezone::createSettingsTable();

			$fields = array(
				'field_id' => $id,
				'available_zones' => implode(",", $this->get('available_zones'))
			);

			if(extension_Members::getMembersSection() == $this->get('parent_section') || is_null(extension_Members::getMembersSection())) {
				Symphony::Configuration()->set('timezone', $id, 'members');
				Administration::instance()->saveConfig();
			}

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		public function tearDown() {
			Symphony::Configuration()->remove('timezone', 'members');
			Administration::instance()->saveConfig();

			return true;
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$zones = explode(",", $this->get('available_zones'));

			$groups = array();

			if ($this->get('required') != 'yes') $groups[] = array(NULL, false, NULL);

			foreach($zones as $zone) {
				$timezones = DateTimeZone::listIdentifiers(constant('DateTimeZone::' . $zone));

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
	}
