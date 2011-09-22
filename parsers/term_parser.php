<?php
	
	libxml_use_internal_errors(true);
	
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$Log.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$SISBroker.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$ParserDbAdapter.php');
	
	try {
		$debug_on = false;
		
		/*
		 * Signal to the authentication process that this isn't user requested;
		 * therefore, forgo database modifications.
		 */
		$PARSER_ORIGIN_FLAG = true;
		include_once('$TSR_BUILD$includes_path$TSR_BUILD$procedures/authenticate.php');
		
		/*
		 * Initialize required resources.
		 */
		$log = new Log();
		$dbAdapter = new DatabaseAdapter('parser_terms');
		$broker = new SISBroker('parser_terms', $log, $cookie_str);
		
		$term_stats = array('created'=>0,'updated'=>0,'deleted'=>0);
		
		if($debug_on) {
			$log->log("TERMS","INFO","Executing...");
		}
		
		/*
		 * $cookie_str comes from the above authentication script;
		 * all key/value pairs needed for SIS interaction are included there.
		 */
		$sisResponse = $broker->transact(array('HttpMethod'=>'GET','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_LIST.GBL?Page=SSR_SSENRL_LIST&Action=A'));
		
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		
		$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
		$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
		
		/*
		 * These evaluations check to see if there are multiple class
		 * schedule terms available for display by the SIS.
		 */
		$termIds = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td[@class='PSLEVEL2GRIDROW']/div/input[@type='radio']");
		$termNames = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td[@class='PSLEVEL2GRIDROW'][position()=2]/div/span[@class='PSEDITBOX_DISPONLY']");
		
		/*
		 * These evaluations check to see if the SIS immediately returns
		 * a class schedule (i.e., only one term is available for viewing).
		 */
		$classNames = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/div/table[@class='PSGROUPBOXWBO']/tr/td[@class='PAGROUPDIVIDER']");
		$detailTables = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/div/table[@class='PSGROUPBOXWBO']/tr/td/table[@class='PSGROUPBOX']/tr/td/div/table[@class='PSLEVEL3GRIDNBO']");
		
		$term_array = array();
			
		/*
		 * Determine if schedule has just been returned (i.e., only 1 term available for viewing).
		 */
		if($classNames->length > 0 && $detailTables->length > 0 && ($classNames->length == ($detailTables->length)/2)) {
			$termName = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/table/tr/td/div/span[@class='SSSPAGEKEYTEXT']");
			if($termName->length > 0) {
				$terms_tracker = array();
				$records = $dbAdapter->getTerms();
				while($record = pg_fetch_array($records)) {
					$terms_tracker[$record['name']] = array("sis_id"=>$record['sis_id']);
				}
				
				$parts = explode('|', $termName->item(0)->nodeValue);
				$name = trim($parts[0]);
				$sis_id = 0;
//				if(preg_match("/[0-9]{4}/", $name, $matches))
//					$name = trim(preg_replace("/[0-9]{4}/", "", $name)) . ' ' . trim($matches[0]);
				$term_array[$sis_id] = $name;
				
				if(isset($terms_tracker[$name])) {
					$record = $terms_tracker[$name];
					if($record['sis_id']!==$sis_id) {
						$dbAdapter->updateTerm($name, $sis_id);
						$term_stats['updated']++;
					}
					unset($terms_tracker[$name]);
				} else {
					$dbAdapter->addTerm($name, $sis_id);
					$term_stats['created']++;
				}
				
				//Clean up terms not found in latest SIS scan.
				foreach($terms_tracker as $key=>$value) {
					$dbAdapter->deleteTerm($key);
					$term_stats['deleted']++;
				}
				
				$log->log("TERMS","INFO","Done :: (".$term_stats['created']." | ".$term_stats['updated']." | ".$term_stats['deleted'].")");
				
			} else {
				$log->log("TERMS","FATAL","Unable to determine the name of the only available term in SIS.");
				exit();
			}
		} else if($termIds->length > 0) {
			$terms_tracker = array();
			$records = $dbAdapter->getTerms();
			while($record = pg_fetch_array($records)) {
				$terms_tracker[$record['name']] = array("sis_id"=>$record['sis_id']);
			}
				
			for($i = 0; $i < $termIds->length; $i++) {
				$name = $termNames->item($i)->nodeValue;
				$sis_id = $termIds->item($i)->getAttribute('value');
//				if(preg_match("/[0-9]{4}/", $name, $matches))
//					$name = trim(preg_replace("/[0-9]{4}/", "", $name)) . ' ' . trim($matches[0]);
				$term_array[$sis_id] = $name;
				
				if(isset($terms_tracker[$name])) {
					$record = $terms_tracker[$name];
					if($record['sis_id']!==$sis_id) {
						$dbAdapter->updateTerm($name, $sis_id);
						$term_stats['updated']++;
					}
					unset($terms_tracker[$name]);
				} else {
					$dbAdapter->addTerm($name, $sis_id);
					$term_stats['created']++;
				}
			}
			
			//Clean up terms not found in latest SIS scan.
			foreach($terms_tracker as $key=>$value) {
				$dbAdapter->deleteTerm($key);
				$term_stats['deleted']++;
			}
			
			$log->log("TERMS","INFO","Done :: (".$term_stats['created']." | ".$term_stats['updated']." | ".$term_stats['deleted'].")");
		} else {
			$log->log("TERMS","FATAL","Unable to locate any term data in response from SIS.");
		}
	}
	catch (Exception $e) {
		$log->log("TERMS","FATAL",$e->getMessage());
	}
?>