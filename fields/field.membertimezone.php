<?php
    
    /**
     * Activation field. If added to a Members section, it generates and stores
     * activation codes for new members, handles activation via normal events,
     * sends emails, and displays as a checkbox in the backend publish area.
     */
	Class fieldMemberTimezone extends Field {

		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Member: Timezone';
		}
		
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
			
			// do stuff
			
			$this->appendShowColumnCheckbox($wrapper);
		}
		
		public function commit(){
			if(!parent::commit()) return false;

			// do stuff
		}
		
	}
