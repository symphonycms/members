<?php
    
    /**
     * Activation field. If added to a Members section, it generates and stores
     * activation codes for new members, handles activation via normal events,
     * sends emails, and displays as a checkbox in the backend publish area.
     */
	Class fieldMemberActivation extends Field {

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
		
		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `activated` enum('yes','no') NOT NULL default 'no',
				  `timestamp` int(11) default NULL,
				  `code` varchar(32)  NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  UNIQUE KEY `username` (`username`)
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
