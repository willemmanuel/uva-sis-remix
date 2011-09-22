<?php

class SISBroker
{
	private $log;
	private $job_type;
	private $curl_handle;
	private $persistentCookieJar;
	private $transactionCookieJar;
									  
	const GBL_PATH_PREFIX = 'https://sisuvacs.admin.virginia.edu/psc/csprd/EMPLOYEE/PSFT_HR_CSPRD/c/';
	
	public function __construct($job_type, $log, $cookie_jar = '') {
		$this->job_type = $job_type;
		$this->log = $log;
		
		$this->curl_handle = curl_init();
		curl_setopt($this->curl_handle, CURLOPT_SSLVERSION, 3);
		curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->curl_handle, CURLOPT_VERBOSE, FALSE);
		curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->curl_handle, CURLOPT_USERAGENT, 'Windows 3.1');
		curl_setopt($this->curl_handle, CURLOPT_COOKIE, '');
		
		$this->persistentCookieJar = $cookie_jar;
	}
	
	public function transact($job_args) {
		if($this->job_type!='authenticate' && $this->job_type!='parser_authenticate') {
			$job_args['CookieJar'] = $this->persistentCookieJar;
		}
		
		/*
		 * Process any request customizations for
		 * non-authentication jobs.
		 */
		if($this->job_type!='authenticate' && $this->job_type!='parser_authenticate') {
			if(isset($job_args['DoPathPrefix']) && !$job_args['DoPathPrefix']) {
				//Don't alter path.
			} else {
				$job_args['Path'] = self::GBL_PATH_PREFIX . $job_args['Path'];
			}
			
			if($job_args['HttpMethod']=='POST') {
				if(strpos($this->job_type, 'parser_')===false) {
					$records = pg_exec($this->dbConnection, "SELECT \"ICStateNum\", \"ICSID\" FROM authenticated_sessions WHERE access_key='".$_COOKIE['access_key']."';");
					
					if($records!=false && (pg_num_rows($records)==1)) {
						$access_vars = pg_fetch_assoc($records);
						$job_args['PostParams'].='&ICSID='.$access_vars['ICSID'].'&ICStateNum='.$access_vars['ICStateNum'];
					} else {
						error_log("FATAL ERROR: Unable to retrieve access vars from database.");
					}
				} else {
					$job_args['PostParams'].='&ICSID='.$job_args['ICSID'].'&ICStateNum='.$job_args['ICStateNum'];
				}
			}
		}
		
		if(!isset($job_args['CookieJar'])) {
			$job_args['CookieJar'] = '';
		}
		
		/*
		 * Execute job using local cURL.
		 */
		if($job_args['HttpMethod']=='GET') {
			return $this->GET($job_args['NeedHeader'], $job_args['Path'], $job_args['CookieJar']);
		} else {
			return $this->POST($job_args['NeedHeader'], $job_args['Path'], $job_args['PostParams'], $job_args['CookieJar']);
		}
	}
	
	private function GET($needHeader, $path, $cookie_str = '') {
		if(strlen($cookie_str)!=0) {
			$this->transactionCookieJar = $cookie_str;
		}
		
		curl_setopt($this->curl_handle, CURLOPT_POST, false);
		//TODO: Don't be a fucking dipshit about this.
		if($needHeader=='true') {
			curl_setopt($this->curl_handle, CURLOPT_HEADER, true);
		} else {
			curl_setopt($this->curl_handle, CURLOPT_HEADER, false);
		}
		curl_setopt($this->curl_handle, CURLOPT_URL, $path);
		curl_setopt($this->curl_handle, CURLOPT_COOKIE, $this->transactionCookieJar);
		
		$response = curl_exec($this->curl_handle);
		//error_log($response);
		
		if($response==false) {
			error_log("FATAL: SIS_Channel GET request failed to: $path.");
			exit();
		} else {
			return $response;
		}
	}
	
	private function POST($needHeader, $path, $postString, $cookie_str = '') {
		if(strlen($cookie_str)!=0) {
			$this->transactionCookieJar = $cookie_str;
		}
		
		curl_setopt($this->curl_handle, CURLOPT_POST, true);
		//TODO: Don't be a fucking dipshit about this.
		if($needHeader=='true') {
			curl_setopt($this->curl_handle, CURLOPT_HEADER, true);
		} else {
			curl_setopt($this->curl_handle, CURLOPT_HEADER, false);
		}
		curl_setopt($this->curl_handle, CURLOPT_URL, $path);
		curl_setopt($this->curl_handle, CURLOPT_COOKIE, $this->transactionCookieJar);
		curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $postString);
		
		$response = curl_exec($this->curl_handle);
		//error_log($response);
		
		if($response==false) {
			error_log("FATAL: SIS_Channel POST request failed to: $path.");
			exit();
		} else {
			return $response;
		}
	}
	
	public function end() {
		//Do nothing for now.
	}
	
	public function __destruct() {
		curl_close($this->curl_handle);
    }
}

?>