<?php defined('SYSPATH') OR die('No direct access allowed.');

class Claero_Database_MySQL_Result extends Kohana_Database_MySQL_Result {
    public function num_fields() {
        return mysql_num_fields($this->_result);
    } // function num_fields
} // class Claero_Database_MySQL_Result