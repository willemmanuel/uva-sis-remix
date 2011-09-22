<?php

	libxml_use_internal_errors(true);
		
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$Job.php');
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$AuthenticatedDbAdapter.php');
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$ResponseBuilder.php');
	
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$objects/ClassObject.php');
	
	try
	{	
		$job = new Job('drop_prepare');
		$dbAdapter = new AuthenticatedDbAdapter();
		
		$class_nbrs = explode(',', $_REQUEST['class_nbr']);
		$class_names = explode(',', $_REQUEST['class_name']);
			
		/*
		 * REQUEST #1 (GET): Request the term selection landing page
		 * for the sole purpose of getting ICStateNum.
		 */
		//$sisResponse = $sisChannel->sisGetRequest(FALSE, 'SA_LEARNER_SERVICES.SSR_SSENRL_DROP.GBL?Page=SSR_SSENRL_DROP&Action=A');
		$sisResponse = $job->transact(array('HttpMethod'=>'GET','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_DROP.GBL?Page=SSR_SSENRL_DROP&Action=A'));
		
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		
		$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
		$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
		
		$dbAdapter->updateAccessVars($_COOKIE['access_key'], $ICSID, $ICStateNum);
		
		/*
		 * If the client specifies a specific term ID,
		 * extra HTTP requests are needed to select the term
		 * and retrieve it's drop page.
		 */
		if(isset($_REQUEST['term_id']) && $_REQUEST['term_id'] !== 'false') {
			
			/*
			 * REQUEST #2 (POST): Request the drop page for the term
			 * being viewed by the user.
			 */
			//$sisResponse = $sisChannel->sisPostRequest(TRUE, 'SA_LEARNER_SERVICES.SSR_SSENRL_DROP.GBL', 'ICType=Panel&ICElementNum=0&ICAction=DERIVED_SSS_SCT_SSR_PB_GO&ICXPos=0&ICYPos=0&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2422%24=9999&SSR_DUMMY_RECV1%24sels%240=' . $_REQUEST['term_id'] . '&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2466%24=9999');		
			$sisResponse = $job->transact(array('HttpMethod'=>'POST','NeedHeader'=>'true','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_DROP.GBL','PostParams'=>'ICType=Panel&ICElementNum=0&ICAction=DERIVED_SSS_SCT_SSR_PB_GO&ICXPos=0&ICYPos=0&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2422%24=9999&SSR_DUMMY_RECV1%24sels%240=' . $_REQUEST['term_id'] . '&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2466%24=9999'));
		
			/*
			 * Get the Location header that will be included in
			 * the 3rd and final GET request.
			 */
			$dropURL = '';
			$headers = preg_split('[\n]', substr($sisResponse, 0, strpos($sisResponse, '<html')));
			for($i = 0; $i < count($headers); $i++)
			{
				if(strpos(trim($headers[$i]), 'Location: ')===0)
				{
					$dropURL = trim(str_replace('Location: ', '', $headers[$i]));
					break;
				}
			}
			
			if($dropURL === '') {
				error_log('FATAL ERROR: Can\'t continue dropping class - empty URL returned by the SIS.');
				$builder = new ResponseBuilder('error');
				$builder->echoResponse();
				exit();
			}
			
			/*
			 * REQUEST #3 (GET): Request the actual drop page
			 * using the previously retrieved drop URL.
			 */
			//$sisResponse = $sisChannel->sisGetRequest(FALSE, $dropURL, FALSE);
			$sisResponse = $job->transact(array('HttpMethod'=>'GET','NeedHeader'=>'false','Path'=>$dropURL,'DoPathPrefix'=>false));
			
			$dom = new DOMDocument;
			$dom->loadHTML($sisResponse);
			$xpath = new DOMXPath($dom);
			
			$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
			$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
			
			$dbAdapter->updateAccessVars($_COOKIE['access_key'], $ICSID, $ICStateNum);
		}
		
		$selectors = $xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/table/tr/td/div/table[starts-with(@id,"STDNT_ENRL_SSV1$scroll$")]/tr[position()>1]/td[position()=1]/div/input[@type="checkbox"]');
		$classNames = $xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/table/tr/td/div/table[starts-with(@id,"STDNT_ENRL_SSV1$scroll$")]/tr[position()>1]/td[position()=2]/div/span/a');
		
		if($selectors->length !== $classNames->length) {
			error_log('ERROR: Mismatched XPath results during stage 1 class drop.');
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
		}
		
		$deletionPostStr = '';
		for($i = 0; $i < $classNames->length; $i++)
		{
			$deletionPostStr .= 'DERIVED_REGFRM1_SSR_SELECT%24chk%24' . str_replace('DERIVED_REGFRM1_SSR_SELECT$', '', $selectors->item($i)->getAttribute('name'));
			for($a = 0; $a < count($class_names); $a++)
			{
				$foundMatch = false;
				if(strpos($classNames->item($i)->nodeValue, $class_names[$a]) !== FALSE && strpos($classNames->item($i)->nodeValue, '(' . $class_nbrs[$a] . ')') !== FALSE)
				{
					 $deletionPostStr .= '=Y&';
					 $deletionPostStr .= $selectors->item($i)->getAttribute('name') . '=Y&';
					 $foundMatch = true;
					 break;
				}
			}
			if(!$foundMatch)
				$deletionPostStr .= '=N&';
		}
		
		/*
		 * REQUEST #4 (POST): Provide the ID of the class
		 * to be deleted. The confirmation page provided in the response
		 * will be parsed and sent back to the client for confirmation.
		 */
		//$sisResponse = $sisChannel->sisPostRequest(FALSE, 'SA_LEARNER_SERVICES.SSR_SSENRL_DROP.GBL', 'ICType=Panel&ICElementNum=0&ICAction=DERIVED_REGFRM1_LINK_DROP_ENRL&ICXPos=0&ICYPos=110&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%244%24=9999&' . $deletionPostStr . '&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%24100%24=9999');			
		$sisResponse = $job->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_DROP.GBL','PostParams'=>'ICType=Panel&ICElementNum=0&ICAction=DERIVED_REGFRM1_LINK_DROP_ENRL&ICXPos=0&ICYPos=110&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%244%24=9999&' . $deletionPostStr . '&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%24100%24=9999'));
		
		/*
		 * Process confirmation page and send back to the client.
		 */
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
			
		$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
		$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
			
		$dbAdapter->updateAccessVars($_COOKIE['access_key'], $ICSID, $ICStateNum);
		
		$classNames = $xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/div/table[starts-with(@id,"CLASS_TBL_VW7$scroll$")]/tr/td[position()=1]/div/span');
		$classDescs = $xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/div/table[starts-with(@id,"CLASS_TBL_VW7$scroll$")]/tr/td[position()=2]/div/span');
		
		$builder = new ResponseBuilder('success');
		
		if($classNames->length !== 0 && $classNames->length === $classDescs->length)
		{
			for($i = 0; $i < $classNames->length; $i++)
			{
				$class = new ClassObject('incomplete', 'drop_confirmation');
			
				if($classNames->item($i)->getAttribute('class') === 'PSHYPERLINK')
					$class->setCourseId($classNames->item($i)->getElementsByTagName('a')->item(0)->nodeValue);
				else
					$class->setCourseId($classNames->item($i)->nodeValue);
				
				$class->setCourseName($classDescs->item($i)->nodeValue);
				$builder->addObject($class);
			}
		}
		else
		{
			error_log('ERROR: Mismatched or empty XPath results during stage 1 class drop, part 2.');
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
		}
		
		$builder->echoResponse();
		$job->end();
	}
	catch (Exception $e)
	{
		error_log($e->getMessage());
		$builder = new ResponseBuilder('error');
		$builder->echoResponse();
		exit();
	}
?>