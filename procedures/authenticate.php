<?php

	/*
	 * TODO: Securitize all incoming inputs.
	 */

	libxml_use_internal_errors(true);
	
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$Log.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$SISBroker.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$ResponseBuilder.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$AuthenticatedDbAdapter.php');
	
	function parse_cookies($response) {
		$cookie_str = '';
		$headers = preg_split("[\n|\r]", substr($response, 0, strpos($response, '<html')));
		foreach($headers as $header) {
			if(strpos($header, 'Set-Cookie: ')!==FALSE) {
				$parts = preg_split('[Set-Cookie: ]', $header);
				$cookie = $parts[1];
				$breakEquals = strpos($cookie, '=');
				$breakSemi = strpos($cookie, '; ');
				$key = substr($cookie, 0, $breakEquals);
				$value = substr($cookie, $breakEquals+1, $breakSemi-$breakEquals-1);
				$cookie_str .= $key . '=' . $value . '; ';
			}
		}
		return $cookie_str;
	}

	function authenticate() {
		
		/*
		 * Initialize required resources.
		 */
		$log = new Log();
	
		try {
			//Default the parser_origin_flag to false.
			global $PARSER_ORIGIN_FLAG;
			if(!isset($PARSER_ORIGIN_FLAG)) {
				$PARSER_ORIGIN_FLAG=false;
				$broker = new SISBroker('authenticate', $log);
			} else {
				$broker = new SISBroker('parser_authenticate', $log);
			}
			
			$cookie_str = '';
			
			/*
			 * REQUEST #1 (POST)
			 * Issued to get the pubcookie_pre_s and pubcookie_g_req cookies.
			 */
			$response = $broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'true','Path'=>'https://www.virginia.edu/ssp/login/login.sucgi?env=/EPPRD','PostParams'=>'%C3%82%E2%80%9Dsubmit%C3%82%E2%80%9D=SIS+Login'));
			
			$pre_s_pos = strpos($response,'pubcookie_pre_s=');
			if($pre_s_pos!=false) {
				$pubcookie_pre_s = substr($response, $pre_s_pos);
				$parts = preg_split('[;\s]', $pubcookie_pre_s);
				if(count($parts)>0)
					$pubcookie_pre_s = $parts[0];
			}
			
			$g_req_pos = strpos($response,'pubcookie_g_req=');
			if($g_req_pos!=false) {
				$pubcookie_g_req = substr($response, $g_req_pos);
				$parts = preg_split('[;\s]', $pubcookie_g_req);
				if(count($parts)>0)
					$pubcookie_g_req = $parts[0];
			}
			
			/*
			 * REQUEST #2 (POST)
			 * Retrieves the login form that will be POSTed next.
			 *  - Note: CURLOPT_HEADER is set to false since the header is not needed in this response.
			 */
			$response = $broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'https://netbadge.virginia.edu/','PostParams'=>'post_stuff=%25C3%2582%25E2%2580%259Dsubmit%25C3%2582%25E2%2580%259D%3DSIS%2BLogin&submit=Click+here+to+continue','CookieJar'=>$pubcookie_g_req));
			
			$dom = new DOMDocument;
			$dom->loadHTML($response);
			$xpath = new DOMXPath($dom);
			$inputs = $xpath->evaluate('/html/body/div/div/div/fieldset/span/form[@action="index.cgi"]/input[@type="hidden"]');
			
			if(!$PARSER_ORIGIN_FLAG) {
				$formPostStr = 'user=' . $_POST['comp_id'] . '&pass=' . $_POST['pass'];
			} else {
				$formPostStr = 'user=mjq4aq&pass=$BUILD$redacted$BUILD$';
			}
			
			for($i = 0; $i < $inputs->length; $i++)
				$formPostStr .= '&' . $inputs->item($i)->getAttribute('name') . '=' . $inputs->item($i)->getAttribute('value');
			
			/*
			 * REQUEST #3 (POST)
			 * Sends the login credentials to NetBadge.
			 * We need pubcookie_g from the response.
			 */
			$response = $broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'true','Path'=>'https://netbadge.virginia.edu/index.cgi','PostParams'=>$formPostStr));
			
			global $TSRTUNNEL_VERIFY_FLAG;
			global $TSRTUNNEL_VERIFY_RESULT;
			if(strpos($response, '<div id="loginError">') == true) {
				if(isset($TSRTUNNEL_VERIFY_FLAG) && $TSRTUNNEL_VERIFY_FLAG) {
					$TSRTUNNEL_VERIFY_RESULT = false;
					return;
				}
				$builder = new ResponseBuilder('invalid_credentials');
				$builder->echoResponse();
				exit();
			} else {
				if(isset($TSRTUNNEL_VERIFY_FLAG) && $TSRTUNNEL_VERIFY_FLAG) {
					$TSRTUNNEL_VERIFY_RESULT = true;
					return;
				}
			}
			
			$g_pos = strpos($response,'pubcookie_g=');
			if($g_pos!=false) {
				$pubcookie_g = substr($response, $g_pos);
				$parts = preg_split('[;\s]', $pubcookie_g);
				if(count($parts)>0)
					$pubcookie_g = $parts[0];
			}
			
			/*
			 * REQUEST #4 (POST)
			 * Sends pubcookie_g and pubcookie_pre_s to server.
			 * We need the form with sha1, random, time, etc. from the response.
			 */
			$response = $broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'https://www.virginia.edu/ssp/login/login.sucgi?env=/EPPRD','PostParams'=>'%C3%82%E2%80%9Dsubmit%C3%82%E2%80%9D=SIS+Login','CookieJar'=>($pubcookie_pre_s.'; '.$pubcookie_g.'; ')));
			
			$dom = new DOMDocument;
			$dom->loadHTML($response);
			$xpath = new DOMXPath($dom);
			$inputs = $xpath->evaluate('/html/body/form/input[@type="hidden"]');
			
			$formPostStr = '';
			for($i = 0; $i < $inputs->length; $i++)
				$formPostStr .= $inputs->item($i)->getAttribute('name') . '=' . $inputs->item($i)->getAttribute('value') . '&';
				
			$formPostStr = substr($formPostStr, 0, strlen($formPostStr)-1);
			
			/*
			 * REQUEST #5 (POST)
			 * Sends sha1, time, random, username to server.
			 * We (finally) get PS_TOKEN from the response.
			 */
			$cookie_str.=parse_cookies($broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'true','Path'=>'https://sisuva.admin.virginia.edu/psp/epprd/EMPLOYEE/EMPL/h/?tab=DEFAULT','PostParams'=>$formPostStr)));
			
			/*
			 * REQUEST #6 (POST)
			 * This gets cookies specific to executing SIS queries.
			 */
			$cookie_str.=parse_cookies($broker->transact(array('HttpMethod'=>'GET','NeedHeader'=>'true','Path'=>'https://sisuva.admin.virginia.edu/psp/epprd/EMPLOYEE/EMPL/h/?tab=DEFAULT','CookieJar'=>$cookie_str)));
			
			/*
			 * REQUEST #7 (POST)
			 * This also gets cookies specific to executing SIS queries.
			 */
			$cookie_str.=parse_cookies($broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'true','Path'=>'https://sisuvacs.admin.virginia.edu/psc/csprd/EMPLOYEE/PSFT_HR_CSPRD/c/SA_LEARNER_SERVICES.SSS_STUDENT_CENTER.GBL','CookieJar'=>$cookie_str,'PostParams'=>$formPostStr)));
			
			//If the authentication process wasn't requested by a parser (i.e., by an actual user)
			//make the appropriate database modifications.
			if(!$PARSER_ORIGIN_FLAG) {
				$access_key = hash('sha256', $cookie_str);
				setcookie('access_key', $access_key, time()+60*60*2, '/');
				
				$dbAdapter = new AuthenticatedDbAdapter();
				$dbAdapter->updateUser($_POST['comp_id']);
				$dbAdapter->addSession($access_key, $_POST['comp_id'], $cookie_str);
				
				$builder = new ResponseBuilder('success');
				$builder->echoResponse();
			}
			
			return $cookie_str;
		}
		catch (Exception $e) {
			error_log('Exception Caught in (authenticate.php): ' . $e->getMessage());
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
		}
	}
	
	/*
	 * Execution starts here.
	 * Store the cookie string for parsers to retrieve if
	 * this has been called by a parser.
	 */
	$cookie_str = authenticate();
?>