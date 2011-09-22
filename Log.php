<?php
	
class Log {
//	private $log_file;
	
	public function __construct() {
		
	}
	
	public function log($entity_name, $level, $msg) {
//		if($this->log_file!=false) {
//			fwrite($this->log_file, date("D M j G:i:s T Y")." [$entity_name] [$level]: $msg\n");
//		} else {
			error_log(date("D M j G:i:s T Y")." [$entity_name] [$level]: $msg\n");
//		}
		if($level=='FATAL') {
			exit();
		}
	}
	
	public function __destruct() {
//		if($this->log_file != false) {
//			fclose($this->log_file);
//		}
    }
}

?>