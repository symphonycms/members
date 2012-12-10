<?php

class MemberSection {

	private $section_id;
	private $data;
	private $fields;
	private $handles;

	public function __construct($section_id, $data) {
		$this->section_id = $section_id;
		$this->data = $data;
	}

	/**
	 * Returns the data of this section as an object
	 *
	 * @return object
	 */
	public function getData() {
		return (object)$this->data;
	}

	/**
	 * Where `$name` is one of the following values, `role`, `timezone`,
	 * `email`, `activation`, `authentication` and `identity`, this function
	 * will return a Field instance. Typically this allows extensions to access
	 * the Fields that are currently being used in the active Members section.
	 *
	 * @param string $type
	 * @return Field|null
	 *  If `$type` is not given, or no Field was found, null will be returned.
	 */
	public function getField($type = null) {
		if(is_null($type)) return null;

		$type = extension_Members::getFieldType($type);

		// Check to see if this name has been stored in our 'static' cache
		// If it hasn't, lets go find it (for better or worse)
		if(!isset($this->fields[$type])) {
			$this->initialiseField($type, $this->section_id);
		}

		// No field, return null
		if(!isset($this->fields[$type])) return null;

		// If it has, return it.
		return $this->fields[$type];
	}

	/**
	 * Where `$name` is one of the following values, `role`, `timezone`,
	 * `email`, `activation`, `authentication` and `identity`, this function
	 * will return the Field's `element_name`. `element_name` is a handle
	 * of the Field's label, used most commonly by events in `$_POST` data.
	 * If no `$name` is given, an array of all Member field handles will
	 * be returned.
	 *
	 * @param string $type
	 * @return string
	 */
	public function getFieldHandle($type = null) {
		if(is_null($type)) return null;

		$type = extension_Members::getFieldType($type);

		// Check to see if this name has been stored in our 'static' cache
		// If it hasn't, lets go find it (for better or worse)
		if(!isset($this->handles[$type])) {
			$this->initialiseField($type, $this->section_id);
		}

		// No field, return null
		if(!isset($this->handles[$type])) return null;

		// Return the handle
		return $this->handles[$type];
	}

	/**
	 * Given a `$type` and potentially `$section_id`, fetch the Field
	 * instance and populate the static `$fields` and `$handles` arrays
	 *
	 * @param string $type
	 * @param integer $section_id
	 */
	public function initialiseField($type, $section_id = null) {
		$field = FieldManager::fetch(null, $section_id, 'ASC', 'sortorder', $type);

		if(!empty($field)) {
			$field = current($field);
			$this->fields[$type] = $field;
			$this->handles[$type] = $field->get('element_name');
		}
	}

}