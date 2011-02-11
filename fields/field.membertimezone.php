<?php
    
    /**
     * Timezone field.
     * Not sure how much this field actually needs to do...
     */
	Class fieldMemberTimezone extends Field {
	
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Member: Timezone';
		}
		
	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/
		
		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` varchar(32) NOT NULL DEFAULT '',
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				) ENGINE=MyISAM;"
			);
		}
		
	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/
	
		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			
			$this->appendShowColumnCheckbox($wrapper);
		}
		
		public function commit(){
			if(!parent::commit()) return false;

		}
	
	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/
	
		public function displayPublishPanel(&$wrapper, $data=NULL, $error =NULL, $prefix =NULL, $postfix =NULL, $entry_id = null){
		
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
		
	}
