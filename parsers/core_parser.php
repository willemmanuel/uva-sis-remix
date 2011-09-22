<?php

	libxml_use_internal_errors(true);
	
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$Log.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$SISBroker.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$ParserDbAdapter.php');
	
	try
	{
		$do_debug = true;
		
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
		$dbAdapter = new DatabaseAdapter('parser_core');
		$broker = new SISBroker('parser_core', $log, $cookie_str);
		
		/**
		 * 
		 * DEPARTMENT PROCESSING STAGE
		 * Occurs separately from course data parsing thanks
		 * to the way the piece of shit SIS groups search results with
		 * different dept. acronyms under the same department. fuck's new?
		 */
		error_log("[DEPT STAGE] Processing...");
		$dept_stats = array('created'=>0,'updated'=>0);
		
		/*
		 * REQUEST (INITIAL)
		 * We need the browse catalog page.
		 */
		$sisResponse = $broker->transact(array('HttpMethod'=>'GET','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSS_BROWSE_CATLG_P.GBL?Page=SSS_BROWSE_CATLG&Action=U'));
		
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		
		$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
		$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
		
		$depts_tracker = array();
		$records = $dbAdapter->getDepartments();
		while($record = pg_fetch_array($records)) {
			$depts_tracker[$record['acronym']] = array('hash'=>$record['hash'],'name'=>$record['name']);
		}
		
		//Iterate through 0 to 9 and then from A to Z (via ASCII)
		for($page_idx = 48; $page_idx <= 90; $page_idx++) {
			/*
			 * REQUEST FOR DEPT PAGE (SPECIFIC NUMBER/LETTER)
			 */
			$sisResponse = $broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSS_BROWSE_CATLG_P.GBL','PostParams'=>'ICAJAX=0&ICType=Panel&ICElementNum=0&ICAction=DERIVED_SSS_BCC_SSR_ALPHANUM_'.chr($page_idx).'&ICXPos=0&ICYPos=0&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&ICModalWidget=0&ICZoomGrid=0&ICZoomGridRt=0&ICModalLongClosed=&ICActionPrompt=false&ICTypeAheadID=&ICFind=&ICAddCount=&DERIVED_SSTSNAV_SSTS_MAIN_GOTO$5$=9999&DERIVED_SSTSNAV_SSTS_MAIN_GOTO$121$=9999','ICSID'=>$ICSID,'ICStateNum'=>$ICStateNum));
						
			$dom = new DOMDocument;
			$dom->loadHTML($sisResponse);
			$xpath = new DOMXPath($dom);
			
			$ICSID = $xpath->evaluate("//input[@name='ICSID']")->item(0)->getAttribute("value");
			$ICStateNum = $xpath->evaluate("//input[@name='ICStateNum']")->item(0)->getAttribute("value");
			
			$dept_nodes = $xpath->evaluate("//div[starts-with(@id,'win0divDERIVED_SSS_BCC_GROUP_BOX_1$')]/table/tr/td[@class='PAGROUPBOXLABELINVISIBLE']");
			
			for($i=0; $i<$dept_nodes->length; $i++) {
				$parts = explode('-', trim($dept_nodes->item($i)->nodeValue), 2);
				$dept_acronym = trim($parts[0]);
				$dept_name = trim($parts[1]);
				
				$hash = hash('sha256',$dept_acronym.$dept_name);
				if(isset($depts_tracker[$dept_acronym])) {
					$record = $depts_tracker[$dept_acronym];
					if($record['hash']!=$hash) {
						$dbAdapter->updateDepartment($dept_acronym, $dept_name, $hash);
						$dept_stats['updated']++;
					}
					unset($depts_tracker[$dept_acronym]);
				} else {
					$dbAdapter->addDepartment($dept_acronym, $dept_name, $hash);
					$dept_stats['created']++;
				}
			}
			
			if($page_idx==57) {
				$page_idx=64;
			}
		}
		
		error_log("[DEPT STAGE] Done :: DPT(".$dept_stats["created"]." | ".$dept_stats["updated"].")");
		
		/**
		 * 
		 * COURSE/SECTION/ETC PROCESSING STAGE
		 * Process core course/section/etc. data.
		 */
		error_log("[CORE DATA STAGE] Processing...");
		$course_stats = array('created'=>0,'updated'=>0);
		$section_stats = array('created'=>0,'updated'=>0,'deleted'=>0);
		$schedule_stats = array('created'=>0,'deleted'=>0);
		
		/*
		 * Get the available terms in TSR - these are the only terms for which
		 * we will collect schedule information for classes.
		 */
		$tsr_terms = array();
		$records = $dbAdapter->getTerms();
		while($term = pg_fetch_array($records)) {
			$tsr_terms[$term['name']] = true;
		}
		
		/*
		 * REQUEST (INITIAL)
		 * We need the JS array to determine the term codes for the terms we have in TSR.
		 */
		$sisResponse = $broker->transact(array('HttpMethod'=>'GET','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.CLASS_SEARCH.GBL?Page=SSR_CLSRCH_ENTRY&Action=U&ExactKeys=Y'));
		
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		
		$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
		$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
					
		$js_array = substr($sisResponse, strpos($sisResponse, 'var optionsArray'));
		$js_array = substr($js_array, 0, strpos($js_array, '];'));
		$js_array = explode("[['', ''],", $js_array);
		
		//2nd array in master array contains all terms.
		$term_matches = array();
		if(count($js_array)>1) {
			$term_array = str_replace("[", '', str_replace("]", '', str_replace("'", '', $js_array[1])));
			$term_array = explode(',', $term_array);
			for($term = count($term_array)-1; $term > 0; $term--) {
				$term_name = trim($term_array[$term]);
				if(array_key_exists($term_name, $tsr_terms)) {
					//The element preceding each valid term is the SIS numeric code for it.
					$term_matches[$term_name] = trim($term_array[$term-1]);
				}
			}
		}
		
		foreach($term_matches as $term_name=>$term_id) {
			if($do_debug) {
				error_log("Reached TERM: $term_name ($term_id)");
				error_log("============");
			}
			
//			if($term_name!='2011 Fall') {
//				continue;
//			}
			
			/*
			 * REQUEST (FOR SPECIFIC TERM)
			 * Get the available departments for a specific term.
			 */
			$sisResponse = $broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.CLASS_SEARCH.GBL','PostParams'=>('ICAJAX=1&ICType=Panel&ICElementNum=0&ICAction=CLASS_SRCH_WRK2_STRM%2449%24&ICXPos=0&ICYPos=0&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&ICModalWidget=0&ICZoomGrid=0&ICZoomGridRt=0&ICModalLongClosed=&ICActionPrompt=false&ICTypeAheadID=&ICFind=&ICAddCount=&DERIVED_SSTSNAV_SSTS_MAIN_GOTO$26$=9999&CLASS_SRCH_WRK2_STRM$49$='.$term_id.'&CLASS_SRCH_WRK2_ACAD_ORG=&CLASS_SRCH_WRK2_RQMNT_DESIGNTN=&CLASS_SRCH_WRK2_CAMPUS=&CLASS_SRCH_WRK2_LOCATION=&CLASS_SRCH_WRK2_SUBJECT$75$=&CLASS_SRCH_WRK2_SSR_EXACT_MATCH1=E&CLASS_SRCH_WRK2_CATALOG_NBR$79$=&CLASS_SRCH_WRK2_ACAD_CAREER=UGRD&CLASS_SRCH_WRK2_SSR_OPEN_ONLY$chk=N&CLASS_SRCH_WRK2_SSR_START_TIME_OPR=GE&CLASS_SRCH_WRK2_MEETING_TIME_START=&CLASS_SRCH_WRK2_SSR_END_TIME_OPR=LE&CLASS_SRCH_WRK2_MEETING_TIME_END=&CLASS_SRCH_WRK2_INCLUDE_CLASS_DAYS=I&CLASS_SRCH_WRK2_MON$chk=&CLASS_SRCH_WRK2_TUES$chk=&CLASS_SRCH_WRK2_WED$chk=&CLASS_SRCH_WRK2_THURS$chk=&CLASS_SRCH_WRK2_FRI$chk=&CLASS_SRCH_WRK2_SAT$chk=&CLASS_SRCH_WRK2_SUN$chk=&CLASS_SRCH_WRK2_SSR_EXACT_MATCH2=E&CLASS_SRCH_WRK2_LAST_NAME=&CLASS_SRCH_WRK2_CLASS_NBR$120$=&CLASS_SRCH_WRK2_DESCR=&CLASS_SRCH_WRK2_SSR_UNITS_MIN_OPR=GE&CLASS_SRCH_WRK2_UNITS_MINIMUM=&CLASS_SRCH_WRK2_SSR_UNITS_MAX_OPR=LE&CLASS_SRCH_WRK2_UNITS_MAXIMUM=&CLASS_SRCH_WRK2_SESSION_CODE$133$=&CLASS_SRCH_WRK2_INSTRUCTION_MODE=&DERIVED_SSTSNAV_SSTS_MAIN_GOTO$152$=9999'),'ICSID'=>$ICSID,'ICStateNum'=>$ICStateNum));
			
			$js_array = substr($sisResponse, strpos($sisResponse, 'var optionsArray'));
			$js_array = substr($js_array, 0, strpos($js_array, '];'));
			$js_array = explode("[['', ''],", $js_array);
			
			//3rd array in master array contains all available departments for the current term.
			$depts_to_parse = array();
			if(count($js_array)>2) {
				$dept_array = str_replace("[", '', str_replace("]", '', str_replace("'", '', $js_array[2])));
				$dept_array = preg_split('/,\n/', $dept_array);
				
				foreach($dept_array as $dept_pair) {
					//error_log(trim($dept_pair));
					$parts = explode(",", $dept_pair, 2);
					//error_log(count($parts));
					if(count($parts)==2) {
						$depts_to_parse[trim($parts[0])] = trim($parts[1]);
					}
				}
			}
			
			//TODO: REMOVE
//			$temp = false;
			foreach($depts_to_parse as $dept_acronym=>$dept_name) {
				if($do_debug) {
					error_log("Reached DEPT: $dept_name ($dept_acronym)");
				}
				
//				if($dept_acronym!='BIOL' && !$temp) {
//					continue;
//				} else {
//					$temp = true;
//				}
				
				/*
				 * REQUEST (FOR ALL SECTIONS IN THIS DEPT OFFERED THIS TERM)
				 */
				$sisResponse = $broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.CLASS_SEARCH.GBL','PostParams'=>('ICAJAX=1&ICType=Panel&ICElementNum=0&ICAction=CLASS_SRCH_WRK2_SSR_PB_CLASS_SRCH&ICXPos=0&ICYPos=122&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&ICModalWidget=0&ICZoomGrid=0&ICZoomGridRt=0&ICModalLongClosed=&ICActionPrompt=false&ICTypeAheadID=&ICFind=&ICAddCount=&DERIVED_SSTSNAV_SSTS_MAIN_GOTO$26$=9999&CLASS_SRCH_WRK2_STRM$49$='.$term_id.'&CLASS_SRCH_WRK2_ACAD_ORG='.$dept_acronym.'&CLASS_SRCH_WRK2_RQMNT_DESIGNTN=&CLASS_SRCH_WRK2_CAMPUS=&CLASS_SRCH_WRK2_LOCATION=&CLASS_SRCH_WRK2_SUBJECT$75$=&CLASS_SRCH_WRK2_SSR_EXACT_MATCH1=E&CLASS_SRCH_WRK2_CATALOG_NBR$79$=&CLASS_SRCH_WRK2_ACAD_CAREER=UGRD&CLASS_SRCH_WRK2_SSR_OPEN_ONLY$chk=N&CLASS_SRCH_WRK2_SSR_START_TIME_OPR=GE&CLASS_SRCH_WRK2_MEETING_TIME_START=&CLASS_SRCH_WRK2_SSR_END_TIME_OPR=LE&CLASS_SRCH_WRK2_MEETING_TIME_END=&CLASS_SRCH_WRK2_INCLUDE_CLASS_DAYS=I&CLASS_SRCH_WRK2_MON$chk=&CLASS_SRCH_WRK2_TUES$chk=&CLASS_SRCH_WRK2_WED$chk=&CLASS_SRCH_WRK2_THURS$chk=&CLASS_SRCH_WRK2_FRI$chk=&CLASS_SRCH_WRK2_SAT$chk=&CLASS_SRCH_WRK2_SUN$chk=&CLASS_SRCH_WRK2_SSR_EXACT_MATCH2=E&CLASS_SRCH_WRK2_LAST_NAME=&CLASS_SRCH_WRK2_CLASS_NBR$120$=&CLASS_SRCH_WRK2_DESCR=&CLASS_SRCH_WRK2_SSR_UNITS_MIN_OPR=GE&CLASS_SRCH_WRK2_UNITS_MINIMUM=&CLASS_SRCH_WRK2_SSR_UNITS_MAX_OPR=LE&CLASS_SRCH_WRK2_UNITS_MAXIMUM=&CLASS_SRC'),'ICSID'=>$ICSID,'ICStateNum'=>$ICStateNum));
				
				$crs_dom = new DOMDocument;
				$crs_dom->loadHTML($sisResponse);
				$crs_xpath = new DOMXPath($crs_dom);
				
				$crs_listing_ICSID = $crs_xpath->evaluate("/html/body/form/input[@name='ICSID']");
				$crs_listing_ICStateNum = $crs_xpath->evaluate("/html/body/form/input[@name='ICStateNum']");
				
				if($crs_listing_ICSID->length > 0 && $crs_listing_ICStateNum->length > 0) {
					$crs_listing_ICSID = $crs_listing_ICSID->item(0)->getAttribute("value");
					$crs_listing_ICStateNum = $crs_listing_ICStateNum->item(0)->getAttribute("value");
				} else {
					error_log("Passing over $dept_name ($dept_acronym); unusable response from SIS (probably no classes).");
					continue;
				}
				
				/*
				 * BEGIN
				 * Over 300 Prompt Check
				 */
				$over300prompt = $crs_xpath->evaluate('/html/body/form/div/div/table/tr/td/div/table/tr/td/table/tr/td/div/table/tr/td/table/tr/td/div/span[@id="DERIVED_SSE_DSP_SSR_MSG_TEXT"]');
				if($over300prompt->length==1) {
					//NOTE: This transaction overrides the default transaction timeout with 300 (60 seconds) to accomodate the large amount of data being transferred.
					$sisResponse = $broker->transact(array('OverrideTransactionTimeout'=>300,'HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.CLASS_SEARCH.GBL','PostParams'=>('ICAJAX=1&ICType=Panel&ICElementNum=0&ICAction=%23ICSave&ICXPos=0&ICYPos=0&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&ICModalWidget=0&ICZoomGrid=0&ICZoomGridRt=0&ICModalLongClosed=&ICActionPrompt=false&ICTypeAheadID=&ICFind=&ICAddCount='),'ICSID'=>$crs_listing_ICSID,'ICStateNum'=>$crs_listing_ICStateNum));
					
					$crs_dom = new DOMDocument;
					$crs_dom->loadHTML($sisResponse);
					$crs_xpath = new DOMXPath($crs_dom);
					
					$crs_listing_ICSID = $crs_xpath->evaluate("/html/body/form/input[@name='ICSID']");
					$crs_listing_ICStateNum = $crs_xpath->evaluate("/html/body/form/input[@name='ICStateNum']");
					
					if($crs_listing_ICSID->length > 0 && $crs_listing_ICStateNum->length > 0) {
						$crs_listing_ICSID = $crs_listing_ICSID->item(0)->getAttribute("value");
						$crs_listing_ICStateNum = $crs_listing_ICStateNum->item(0)->getAttribute("value");
					} else {
						error_log("Passing over $dept_name ($dept_acronym); unusable response from SIS (probably no classes).");
						continue;
					}
				}
				/*
				 * END
				 * Over 300 Prompt Check
				 */
				$course_cells = $crs_xpath->evaluate('//span[starts-with(@id,"DERIVED_CLSRCH_DESCR200")]');
				$section_blocks = $crs_xpath->evaluate('//div[starts-with(@id,"win0divDERIVED_CLSRCH_GROUPBOX1$")]');
				
				if($course_cells->length == $section_blocks->length) {
					for($crs = 0; $crs < $course_cells->length; $crs++) {
						$parts = explode(' ', trim($course_cells->item($crs)->nodeValue), 2);
						$course_dept = trim(preg_replace('/\W/', '', $parts[0]));
						$parts = explode('-', trim($parts[1]));
						$course_num = preg_replace('/\D/', '', $parts[0]);
						$course_name = trim($parts[1]);
						
						if($do_debug) {
							error_log("Reached COURSE: $course_dept - $course_name ($course_num)");
						}
						
						/*
						 * BEGIN
						 * course processing
						 */
						$course_hash = hash('sha256',$course_dept.$course_num.$course_name);
						$records = $dbAdapter->getCourse($course_dept, $course_num);
						if(pg_num_rows($records)>0) {
							$record = pg_fetch_array($records);
							
							if($record['hash']!=$course_hash) {
								//Update existing course record.
								$dbAdapter->updateCourse($course_dept, $course_num, $course_name, $course_hash);
								$course_stats['updated']++;
							}
								
						} else {
							//Add course record to database.
							$dbAdapter->addCourse($course_dept, $course_num, $course_name, $course_hash);
							$course_stats['created']++;
						}
						/*
						 * END
						 * course processing
						 */
					
						$section_cells = $crs_xpath->evaluate('.//a[starts-with(@id,"DERIVED_CLSRCH_SSR_CLASSNAME_LONG$")]', $section_blocks->item($crs));
						
						$sections_tracker = array();
						$records = $dbAdapter->getSectionsForCourseInTerm($course_dept, $course_num, $term_name);
						while($record = pg_fetch_array($records)) {
							$sections_tracker[$record['section_id']] = $record['hash'];
						}
						
						for($sect = 0; $sect < $section_cells->length; $sect++) {
							if($do_debug) {
								//error_log("Reached SECTION: ".trim($section_cells->item($sect)->nodeValue));
							}
							
							/*
							 * REQUEST (FOR SECTION DETAILS PAGE - FINAL)
							 */
							$sisResponse = $broker->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.CLASS_SEARCH.GBL','PostParams'=>('ICAJAX=1&ICType=Panel&ICElementNum=0&ICAction=DERIVED_CLSRCH_SSR_CLASSNAME_LONG%24'.str_replace('DERIVED_CLSRCH_SSR_CLASSNAME_LONG$', '', trim($section_cells->item($sect)->getAttribute('name'))).'&ICXPos=0&ICYPos=398&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&ICModalWidget=0&ICZoomGrid=0&ICZoomGridRt=0&ICModalLongClosed=&ICActionPrompt=false&ICTypeAheadID=&ICFind=&ICAddCount=&DERIVED_SSTSNAV_SSTS_MAIN_GOTO$5$=9999&DERIVED_SSTSNAV_SSTS_MAIN_GOTO$99$=9999'),'ICSID'=>$crs_listing_ICSID,'ICStateNum'=>$crs_listing_ICStateNum));
							
							$sql = array();
							$sql['topic'] = '';
							$sql['description'] = '';
							$section_dom = new DOMDocument;
							$section_dom->loadHTML($sisResponse);
							$section_xpath = new DOMXPath($section_dom);
							
							$link_parts = explode('-', $section_cells->item($sect)->nodeValue, '2');
							$section_id = intval(trim(preg_replace('/\D/', '', $link_parts[1])));
							$sql['section'] = intval(trim($link_parts[0]));
							
							$description = $section_xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/table/tr/td[2]/div/span[@class="PSLONGEDITBOX"]');
							$evals = $section_xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table[starts-with(@id,"UV_CLSRCH_INSTR$scroll$")]/tr/td[1]/div/span[@class="PSEDITBOX_DISPONLY"]');
							$component_cell = $section_xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/span[@class="SSSKEYTEXT"]');
							
							if($description->length > 0) {
								$sql['description'] = trim($description->item(0)->nodeValue);
							}
							
							if($component_cell->length > 0) {
								$parts = explode('|', $component_cell->item(0)->nodeValue);
								$sql['component'] = trim($parts[2]);
							}
							
							/*
							 * Collect top-most information cells.
							 */
							$details = $section_xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/table/tr/td/div/table/tr/td/table/tr/td[2]/div/span');
							for($i=0; $i<$details->length; $i++)
							{
								$attr = $details->item($i)->getAttribute('id');
								if($attr=='CAMPUS_LOC_VW_DESCR')
									$sql['location'] = $details->item($i)->nodeValue;
								else if(strpos($attr, 'PSXLATITEM_XLATLONGNAME$')!==false)
									$sql['univ_session'] = $details->item($i)->nodeValue;
								else if($attr=='CAMPUS_TBL_DESCR')
									$sql['campus'] = $details->item($i)->nodeValue;
								else if($attr=='INSTRUCT_MODE_DESCR')
									$sql['inst_mode'] = $details->item($i)->nodeValue;
								else if($attr=='SSR_CLS_DTL_WRK_UNITS_RANGE')
									$sql['credits'] = floatval(trim(str_replace('units', '', $details->item($i)->nodeValue)));
								else if($attr=='CRSE_TOPICS_DESCR')
									$sql['topic'] = $details->item($i)->nodeValue;
								else if($attr=='GRADE_BASIS_TBL_DESCRFORMAL')
									$sql['grading_basis'] = $details->item($i)->nodeValue;
								else if($attr=='PSXLATITEM_XLATLONGNAME')
									$sql['career'] = $details->item($i)->nodeValue;
							}
							
							/*
							 * Get status of class.
							 */
							$details = $section_xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/table/tr/td/div/table/tr/td/table/tr/td/div/div/img[@class="SSSIMAGECENTER"]');
							if($details->length > 0) {
								if(strpos($details->item(0)->getAttribute('src'), 'PS_CS_STATUS_OPEN_ICN_1.gif') !== false)
									$sql['status'] = 'Open';
								else if(strpos($details->item(0)->getAttribute('src'), 'PS_CS_STATUS_CLOSED_ICN_1.gif') !== false)
									$sql['status'] = 'Closed';
								else if(strpos($details->item(0)->getAttribute('src'), 'PS_CS_STATUS_WAITLIST_ICN_1.gif') !== false)
									$sql['status'] = 'Waitlist';
								else
									$sql['status'] = 'Unknown';
							}
							
							/*
							 * Collect enrollment/waitlist information cells.
							 */
							$details = $section_xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/table/tr/td/div/span[@class="PSEDITBOX_DISPONLY"]');
							for($i=0; $i<$details->length; $i++)
							{
								$attr = $details->item($i)->getAttribute('id');
								if($attr=='SSR_CLS_DTL_WRK_ENRL_TOT')
									$sql['enrolled'] = intval($details->item($i)->nodeValue);
								else if($attr=='SSR_CLS_DTL_WRK_ENRL_CAP')
									$sql['capacity'] = intval($details->item($i)->nodeValue);
								else if($attr=='SSR_CLS_DTL_WRK_WAIT_TOT')
									$sql['waitlist_total'] = intval($details->item($i)->nodeValue);
								else if($attr=='SSR_CLS_DTL_WRK_WAIT_CAP')
									$sql['waitlist_capacity'] = intval($details->item($i)->nodeValue);
							}
							
							/*
							 * Collect consent/satisfies info if available.
							 */
							$addtl_info = array();
							$keys = $section_xpath->evaluate('.//table[@id="ACE_SSR_CLS_DTL_WRK_GROUP2"]/tr/td/div/label');
							$values = $section_xpath->evaluate('.//table[@id="ACE_SSR_CLS_DTL_WRK_GROUP2"]/tr/td/div/span');
							for($i=0; $i<$keys->length && $i<$values->length && $keys->length == $values->length; $i++) {
								$addtl_info[trim($keys->item($i)->nodeValue)] = trim($values->item($i)->nodeValue);	
							}
							
							if($do_debug) {
								//error_log(implode(', ', $sql));
							}
							
							/*
							 * BEGIN
							 * section insertion
							 */
							$sql['hash'] = hash('sha256',implode($sql));
							if(isset($sections_tracker[$section_id])) {
								$record_hash = $sections_tracker[$section_id];
								if($record_hash!=$sql['hash']) {
									$dbAdapter->updateSection($section_id, $course_dept, $course_num, $term_name, $sql);
									$section_stats['updated']++;
								}
								unset($sections_tracker[$section_id]);
							} else {
								$dbAdapter->addSection($section_id, $course_dept, $course_num, $term_name, $sql);
								$section_stats['created']++;
							}
							/*
							 * END
							 * section insertion
							 */
							
							/*
							 * BEGIN
							 * additional section info insertion
							 */
							$dbAdapter->addSectionKVPairs($section_id, $term_name, $addtl_info);
							/*
							 * END
							 * additional section info insertion
							 */
							
							//MQUINN: Don't need for now; we'll just use all components for a particular course.
//							/*
//							 * BEGIN
//							 * component dependency insertion
//							 */
//							$comp_deps = array();
//							$component_deps = $section_xpath->evaluate('//span[starts-with(@id,"SSR_CLS_DTL_WRK_DESCR$")]');
//							for($i=0; $i<$component_deps->length; $i++) {
//								array_push($comp_deps, trim($component_deps->item($i)->nodeValue));
//							}
//							$dbAdapter->addSectionDependencies($section_id, $comp_deps);
//							/*
//							 * END
//							 * component dependency insertion
//							 */
							
							$scheduleRows = $section_xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table[@id="SSR_CLSRCH_MTG$scroll$0"]/tr[position()>2]');
							
							$schedules_tracker = array();
							$records = $dbAdapter->getScheduleEntriesForSection($section_id, $term_name);
							while($record = pg_fetch_array($records)) {
								$schedules_tracker[$record['hash']] = $record['section_id'];
							}
							
							/*
							 * BEGIN
							 * Parsing schedule entries for the current section.
							 */
							for($row = 0; $row < $scheduleRows->length; $row++)
							{
								$sql = array();
								$dtg = $scheduleRows->item($row)->getElementsByTagName('td')->item(0)->nodeValue;
								$dtg_parts = explode(' ', $dtg, 2);
								
								if(trim($dtg) != 'TBA') {
									$sql['days'] = trim($dtg_parts[0]);
									$dtg_parts = explode('-', $dtg_parts[1], 2);
									$sql['start_time'] = date('H:i:s', strtotime(trim($dtg_parts[0])));
									$sql['end_time'] = date('H:i:s', strtotime(trim($dtg_parts[1])));
								} else {
									$sql['days'] = '';
									$sql['start_time'] = '';
									$sql['end_time'] = '';
								}
								
								$sql['room'] = trim($scheduleRows->item($row)->getElementsByTagName('td')->item(1)->nodeValue);
								
								$dbAdapter->addInstructors($section_id, $term_name, explode(",", $scheduleRows->item($row)->getElementsByTagName('td')->item(2)->nodeValue));
								
								$start_end = $scheduleRows->item($row)->getElementsByTagName('td')->item(3)->nodeValue;
								$times = array();
								$time_parts = explode('-', $start_end, 2);
								
								if(trim($start_end) != 'TBA') {
									$sql['start_date'] = date('Y-m-d', strtotime(trim($time_parts[0])));
									$sql['end_date'] = date('Y-m-d', strtotime(trim($time_parts[1])));
								} else {
									$sql['start_date'] = '';
									$sql['end_date'] = '';
								}
								
								$sql['hash'] = hash('sha256',implode($sql));
								
								if(isset($schedules_tracker[$sql['hash']])) {
									unset($schedules_tracker[$sql['hash']]);
								} else {
									$dbAdapter->addScheduleEntry($section_id, $term_name, $sql);
									$schedule_stats['created']++;
								}
							}
							//Clean up schedule entries not found in latest SIS scan.
							foreach($schedules_tracker as $key=>$id) {
								$dbAdapter->deleteScheduleEntry($section_id, $term_name, $key);
								$schedule_stats['deleted']++;
							}
						}
						
						//Clean up sections not found in latest SIS scan.
						foreach($sections_tracker as $key=>$value) {
							$dbAdapter->deleteSection($key, $term_name);
							$section_stats['deleted']++;
						}
					}
				} else {
					if($do_debug) {
						error_log("NOTICE: NO COURSES FOUND");
					}
				}
			}
		}
		
		error_log("[CORE DATA STAGE] Done :: CRS(".$course_stats["created"]." | ".$course_stats["updated"].") :: SCT(".$section_stats["created"]." | ".$section_stats["updated"]." | ".$section_stats["deleted"].") :: SCD(".$schedule_stats["created"]." | ".$schedule_stats["deleted"].")");
		exit();
	}
	catch (Exception $e) {
		error_log($e->getMessage());
	}
?>