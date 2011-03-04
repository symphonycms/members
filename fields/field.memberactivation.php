<?php

	/**
	 * Activation field. If added to a Members section, it generates and stores
	 * activation codes for new members, handles activation via normal events,
	 * sends emails, and displays as a checkbox in the backend publish area.
	 *
	 * === Events ===
	 * POST data submitted to an activation field can accept one of two kinds of
	 * data: an activation code, which will activate the member, or a response code.
	 * Response codes are predefined codes used to get the field to do something,
	 * like regenerate and reissue an activation code.
	*/

	Class fieldMemberActivation extends Field {

		const CODE_EXPIRY_TIME = 3600; // 1 hour

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Member: Activation';
		}

		function canToggle(){
			return true;
		}

		function canFilter(){
			return true;
		}

		public function mustBeUnique(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public static function createSettingsTable() {
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_memberactivation` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM;
			");
		}

		public function createTable(){
			// How large does the code field need to be? Are we doing SHA1?

			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `activated` enum('yes','no') NOT NULL default 'no',
				  `timestamp` int(11) default NULL,
				  `code` varchar(40)  NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`)
				) ENGINE=MyISAM;"
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public static function generateCode($member_id){

			// First check if a code already exists
			$code = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_codes` WHERE `member_id` = '$member_id' AND `expiry` > ".time()." LIMIT 1");

			if(is_array($code) && !empty($code)) return $code['code'];

			// Generate a code
			do{
				$code = md5(time().rand(0,100000));
				$row = Symphony::Database()->fetchRow(0, "SELECT * FROM `tbl_members_codes` WHERE `code` = '{$code}'");
			} while(is_array($row) && !empty($row));

			Symphony::Database()->insert(
				array(
					'member_id' => $member_id,
					'code' => $code,
					'expiry' => (time() + self::CODE_EXPIRY_TIME)
				),
				'tbl_members_codes', true
			);

			return $code;
		}

		public static function purgeCodes($member_id=NULL){
			Symphony::Database()->query("DELETE FROM `tbl_members_codes` WHERE `expiry` <= ".time().($member_id ? " OR `member_id` = '$member_id'" : NULL));
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			/**
			 * Does this field even need settings?
			 *
			 * Simply adding it makes activation required. And its behavior
			 * is pretty well defined. Do we allow for different types of
			 * code generation? Maybe a flag for whether codes can be
			 * re-requested, or the time window a code can be active?
			 */

			$this->appendShowColumnCheckbox($wrapper);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			fieldMemberActivation::createSettingsTable();

			$fields = array(
				'field_id' => $id
			);

			Symphony::Configuration()->set('activation', $id, 'members');
			Administration::instance()->saveConfig();

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());

		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {

			/**
			 * Displays a checkbox, along with some help text.
			 *
			 * If member is activated, help text shows datetime activated.
			 * If code is still live, displays when the code was generated.
			 * If the code is expired, displays 'Expired' w/the expiration timestamp.
			 */

		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){

		}

		public function prepareTableValue($data, XMLElement $link=NULL){

		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

		}

	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		public function getExampleFormMarkup(){

			/**
			 * field[activation] can accept the activation code itself
			 * or a response code (e.g. 101 to regenerate and resend code)
			 */
		}
	}
