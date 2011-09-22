<?php
	
	include_once ('AuthenticatedDbAdapter.php');
	
	function sanitize($input) {
		return pg_escape_string(strip_tags(trim($input)));
	}
	
	function tsr_validate() {
		try {
			foreach($_REQUEST as $key=>$input) {
				$_REQUEST[$key] = sanitize($input);
			}
			
			if(isset($_COOKIE['access_key'])) {
				$dbAdapter = new AuthenticatedDbAdapter();
				
				$session_record = $dbAdapter->getUserSession($_COOKIE['access_key']);
				if(!$session_record) {
					header('Location: $TSR_BUILD$http_prefix$TSR_BUILD$index.php?notice=expired');
					exit();
				} else {
					return $session_record['computing_id'];
				}
			} else {
				header('Location: $TSR_BUILD$http_prefix$TSR_BUILD$index.php?notice=expired');
				exit();
			}
		}
		catch (Exception $e) {
			error_log('FATAL ERROR: Exception in Security.php: '.$e->getMessage());
			header('Location: $TSR_BUILD$http_prefix$TSR_BUILD$index.php?notice=expired');
			exit();
		}
	}
?>