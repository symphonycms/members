<?php


	require_once(TOOLKIT . '/class.field.php');

	/**
	 * The Identity class is extended by fields that act as the identity
	 * fields for the Members system. These fields provide unique information
	 * to identify a user uniquely in the system. At present, there are two
	 * Identity fields, Member: Username and Member: Email. As time goes on
	 * it is anticipated that this will extend to include other types, such
	 * as Facebook or Twitter.
	 *
	 * This class provides methods for the system to return an Entry of a
	 * Member given a particular search term via the `fetchMemberBy` function.
	 */
	Abstract Class Identity extends Field {

		protected static $driver = null;

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();
			$this->_required = true;
			$this->set('required', 'yes');
		}

		public function mustBeUnique() {
			return true;
		}

		public function canFilter(){
			return true;
		}

		public function allowDatasourceParamOutput(){
			return true;
		}

		public function canPrePopulate(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * Given a Member ID, return Member
		 *
		 * @param integer $member_id
		 * @return Entry
		 */
		public function fetchMemberFromID($member_id){
			if(!(Identity::$driver instanceof Extension)){
				Identity::$driver = Symphony::ExtensionManager()->create('members');
			}

			return Identity::$driver->getMemberDriver()->initialiseMemberObject($member_id);
		}

		abstract public function fetchMemberIDBy($needle, $member_id = null);

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$field_id = $this->get('id');
			$handle = $this->get('element_name');

			// Identity
			$label = Widget::Label($this->get('label'));
			if(!($this->get('required') == 'yes')) $label->appendChild(new XMLElement('i', __('Optional')));

			$label->appendChild(Widget::Input(
				"fields{$prefix}[{$handle}]{$postfix}", $data['value']
			));

			// Error?
			if(!is_null($error)) {
				$wrapper->appendChild(Widget::Error($label, $error));
			}
			else {
				$wrapper->appendChild($label);
			}
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function processRawFieldData($data, &$status, &$message=null, $simulate=false, $entry_id=null){
			$status = self::__OK__;

			if(empty($data)) return array();

			return array(
				'value' => trim($data),
				'handle' => Lang::createHandle(trim($data))
			);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null){
			if(empty($data)) return __('None');

			return parent::prepareTableValue(array('value' => General::sanitize($data['value'])), $link);
		}

		public function getParameterPoolValue(array $data, $entry_id = null) {
			return $data['value'];
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			// Filter is an regexp.
			if(self::isFilterRegex($data[0])) {
				$this->buildRegexSQL($data[0], array('value', 'handle', 'entry_id'), $joins, $where);
			}

			// Filter has + in it.
			else if($andOperation) {
				foreach($data as $key => $bit){
					$bit = Symphony::Database()->cleanValue($bit);
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND (
									`t$field_id$key`.value = '$bit'
									OR `t$field_id$key`.handle = '$bit'
									OR `t$field_id$key`.entry_id = '$bit'
								) ";
				}
			}

			// Normal
			else {
				if(!is_array($data)) {
					$data = array($data);
				}
				$data = array_map(array(Symphony::Database(), 'cleanValue'), $data);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (
								`t$field_id`.value IN ('".implode("', '", $data)."')
								OR `t$field_id`.handle IN ('".implode("', '", $data)."')
								OR `t$field_id`.entry_id IN ('".implode("', '", $data)."')
							) ";
			}

			return true;
		}

	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		public function getExampleFormMarkup(){

			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').']'));

			return $label;
		}

	}
