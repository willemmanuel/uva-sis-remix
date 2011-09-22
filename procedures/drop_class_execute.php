<?php

	libxml_use_internal_errors(true);
		
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$AuthenticatedDbAdapter.php');
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$ResponseBuilder.php');
	
	/*
	 * MQUINN (1/1/2011)
	 * Note: The HTTP request issued below
	 * has not been tested in production using
	 * the new Job routine.
	 */
	
	try
	{
		/*
		 * REQUEST #1 (POST): Complete the user's drop request
		 * after having prompted them for confirmation.
		 */
		$job = new Job('drop_execute');
		//$sisResponse = $sisChannel->sisPostRequest(FALSE, 'SA_LEARNER_SERVICES.SSR_SSENRL_DROP.GBL', 'ICType=Panel&ICElementNum=0&ICAction=DERIVED_REGFRM1_SSR_PB_SUBMIT&ICXPos=0&ICYPos=0&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%244%24=9999&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2457%24=9999');		
		$sisResponse = $job->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_DROP.GBL','PostParams'=>'ICType=Panel&ICElementNum=0&ICAction=DERIVED_REGFRM1_SSR_PB_SUBMIT&ICXPos=0&ICYPos=0&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%244%24=9999&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2457%24=9999'));
		
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		
		$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
		$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
		
		$dbAdapter = new AuthenticatedDbAdapter();
		$dbAdapter->updateAccessVars($_COOKIE['access_key'], $ICSID, $ICStateNum);
		
		$result = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/div[starts-with(@id,'win0divDERIVED_REGFRM1_SS_MESSAGE_LONG$')]/div");
		$result = $result->item(0)->nodeValue;
		
		if(strpos($result,'Error')!==FALSE) {
			$builder = new ResponseBuilder('failure');
			$result = trim(str_replace('Error:','',$result));
			$builder->addRootAttribute('reason',$result);
			$builder->echoResponse();
		} else {
			$builder = new ResponseBuilder('success');
			$builder->echoResponse();
		}
		
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