<?php
    require_once(TOOLKIT . '/class.field.php');
    
    Class Identity extends Field {

        public function mustBeUnique() {
            return true;
        }

    }
