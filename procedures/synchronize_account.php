<?php

	libxml_use_internal_errors(true);
		
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$Job.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$AuthenticatedDbAdapter.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$ResponseBuilder.php');
	
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$objects/ClassObject.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$objects/ClassDetailsObject.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$objects/ScheduleObject.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$objects/ExamScheduleObject.php');
	
	/*
	 * Given a details ID and deadlines ID, this function will
	 * save the data at those locations and append it, in the form of a details object,
	 * to the given class object.
	 */
	function parse_class_details($job, $sis_details_id, $sis_deadlines_id, $class_obj) {
		//$sisResponse = $sisChannel->sisPostRequest(FALSE, 'SA_LEARNER_SERVICES.SSR_SSENRL_LIST.GBL', 'ICType=Panel&ICElementNum=0&ICAction=MTG_SECTION%24' . $sis_details_id . '&ICXPos=0&ICYPos=184&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2422%24=9999&DERIVED_REGFRM1_SSR_SCHED_FORMAT%2436%24%24rad=L&DERIVED_REGFRM1_SSR_SCHED_FORMAT%2436%24=L&DERIVED_REGFRM1_SA_STUDYLIST_E%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_E=Y&DERIVED_REGFRM1_SA_STUDYLIST_D%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_D=Y&DERIVED_REGFRM1_SA_STUDYLIST_W%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_W=Y&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2492%24=9999');
		$sisResponse = $job->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_LIST.GBL','PostParams'=>('ICType=Panel&ICElementNum=0&ICAction=MTG_SECTION%24' . $sis_details_id . '&ICXPos=0&ICYPos=184&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2422%24=9999&DERIVED_REGFRM1_SSR_SCHED_FORMAT%2436%24%24rad=L&DERIVED_REGFRM1_SSR_SCHED_FORMAT%2436%24=L&DERIVED_REGFRM1_SA_STUDYLIST_E%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_E=Y&DERIVED_REGFRM1_SA_STUDYLIST_D%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_D=Y&DERIVED_REGFRM1_SA_STUDYLIST_W%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_W=Y&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2492%24=9999')));
		
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		
		$description = $xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/table/tr/td[2]/div/span[@class="PSLONGEDITBOX"]');
		$details = $xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/table/tr/td/div/table/tr/td/table/tr/td[2]');
		$evals = $xpath->evaluate('/html/body/form/div/table/tr/td/div/table/tr/td/div/table[starts-with(@id,"UV_CLSRCH_INSTR$scroll$")]/tr/td[1]/div/span[@class="PSEDITBOX_DISPONLY"]');
		
		$details_obj = new ClassDetailsObject();
		
		if($description->length > 0)
			$details_obj->setDescription($description->item(0)->nodeValue);
		
		for($i=0; $i<$details->length; $i++)
		{
			$cell = $dom->saveXML($details->item($i));
			if(strpos($cell, 'SSR_CLS_DTL_WRK_LOCATION')!=FALSE)
				$details_obj->setLocation($details->item($i+1)->nodeValue);
			else if(strpos($cell, 'SSR_CLS_DTL_WRK_CAMPUS')!=FALSE)
				$details_obj->setCampus($details->item($i+1)->nodeValue);
			else if(strpos($cell, 'SSR_CLS_DTL_WRK_INSTRUCTION_MODE')!=FALSE)
				$details_obj->setInstructionMode($details->item($i+1)->nodeValue);
			else if(strpos($cell, 'SSR_CLS_DTL_WRK_GRADING_BASIS')!=FALSE)
				$details_obj->setGradingMode($details->item($i+1)->nodeValue);
			else if(strpos($cell, 'SSR_CLS_DTL_WRK_UNITS_RANGE')!=FALSE)
				$details_obj->setUnits(trim(str_replace('units', '', $details->item($i+1)->nodeValue)));
		}
		
		for($i=0; $i<$evals->length; $i++)
			$details_obj->addEvaluation($evals->item($i)->nodeValue);
		
		/*
		 * Get deadlines for this class.
		 */
		//$sisResponse = $sisChannel->sisPostRequest(FALSE, 'SA_LEARNER_SERVICES.SSR_SSENRL_LIST.GBL', 'ICType=Panel&ICElementNum=0&ICAction=DEADLINES%24' . $sis_deadlines_id . '&ICXPos=0&ICYPos=270&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2422%24=9999&DERIVED_REGFRM1_SSR_SCHED_FORMAT%2436%24%24rad=L&DERIVED_REGFRM1_SSR_SCHED_FORMAT%2436%24=L&DERIVED_REGFRM1_SA_STUDYLIST_E%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_E=Y&DERIVED_REGFRM1_SA_STUDYLIST_D%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_D=Y&DERIVED_REGFRM1_SA_STUDYLIST_W%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_W=Y&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2492%24=9999');
		$sisResponse = $job->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_LIST.GBL','PostParams'=>('ICType=Panel&ICElementNum=0&ICAction=DEADLINES%24' . $sis_deadlines_id . '&ICXPos=0&ICYPos=270&ICFocus=&ICSaveWarningFilter=0&ICChanged=-1&ICResubmit=0&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2422%24=9999&DERIVED_REGFRM1_SSR_SCHED_FORMAT%2436%24%24rad=L&DERIVED_REGFRM1_SSR_SCHED_FORMAT%2436%24=L&DERIVED_REGFRM1_SA_STUDYLIST_E%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_E=Y&DERIVED_REGFRM1_SA_STUDYLIST_D%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_D=Y&DERIVED_REGFRM1_SA_STUDYLIST_W%24chk=Y&DERIVED_REGFRM1_SA_STUDYLIST_W=Y&DERIVED_SSTSNAV_SSTS_MAIN_GOTO%2492%24=9999')));
		
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		$deadlineCells = $xpath->evaluate("/html/body/form/div/div/table/tr/td/div/table/tr/td/table/tr/td/div/table/tr[position()=3]/td[position()>1]/div/span[@class='PSEDITBOX_DISPONLY']");
		
		for($i=0; $i+1 < $deadlineCells->length; $i+=2)
			$details_obj->addDeadline($deadlineCells->item($i)->nodeValue, $deadlineCells->item($i+1)->nodeValue);
		
		$class_obj->addObject($details_obj);
	}

	/*
	 * Main parsing function responsible for saving all necessary information
	 * found in the given response string. All information is sent to a ResponseBuilder
	 * for JSON storage.
	 */
	function parse_class_schedule($job, $sisResponse, $builder) {
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		
		$classNames = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/div/table[@class='PSGROUPBOXWBO']/tr/td[@class='PAGROUPDIVIDER']");
		$detailTables = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td/div/table[@class='PSGROUPBOXWBO']/tr/td/table[@class='PSGROUPBOX']/tr/td/div/table[@class='PSLEVEL3GRIDNBO']");
		
		if($classNames->length > 0 && $detailTables->length > 0 && ($classNames->length == ($detailTables->length)/2))
		{
			$status_counter = 0;
			$detail_counter = 1;
			$actionable_id = 0;
			
			$cls_obj_array = array();
			
			for($i = 0; $i < $classNames->length; $i++)
			{
				$class = new ClassObject('complete', 'schedule');
				$classParts = explode('-', $classNames->item($i)->nodeValue);
				$class->setCourseId(trim($classParts[0]));
				$class->setCourseName(trim($classParts[1]));
				$class->setActionId($actionable_id);
				
				$statusDom = new DOMDocument;
				$statusDom->loadHTML('<html><body>' . $dom->saveXML($detailTables->item($status_counter)) . '</body></html>');
				$statusXpath = new DOMXPath($statusDom);
				
				$status_cells = $statusXpath->evaluate("/html/body/table/tr[position()=last()]/td/div/span");
				$deadlineLink = $statusXpath->evaluate("/html/body/table/tr[position()=last()]/td[position()=last()]/div/a");
				
				//If class status indicates Dropped, skip the class entirely.
				if(trim($status_cells->item(0)->nodeValue) === 'Dropped')
					continue;
				
				$class->setStatus($status_cells->item(0)->nodeValue);
				$class->setDeadlinesId(str_replace('DEADLINES$', '', $deadlineLink->item(0)->getAttribute('name')));
				
				/*
				 * A 'Waiting' status indicates that a waitlist position cell is present in the response.
				 * This means some cells are shifted from where they would normally be if the status was 'Enrolled' instead.
				 */
				if(trim($status_cells->item(0)->nodeValue) === 'Waiting') {
					$class->setWaitlistPos($status_cells->item(1)->nodeValue);
					$class->setUnits($status_cells->item(2)->nodeValue);
					$credit_cap = $status_cells->item(2)->nodeValue;
					$class->setGrading($status_cells->item(3)->nodeValue);
				} else {
					$class->setUnits($status_cells->item(1)->nodeValue);
					$credit_cap = $status_cells->item(1)->nodeValue;
					$class->setGrading($status_cells->item(2)->nodeValue);
				}
				
				$detailsDom = new DOMDocument;
				$detailsDom->loadHTML('<html><body>' . $dom->saveXML($detailTables->item($detail_counter)) . '</body></html>');
				$detailsXpath = new DOMXPath($detailsDom);
				
				/*
				 * The SIS is a piece of shit. In some cases, lab/lecture classes, which are really two different
				 * classes, are grouped together as one, and different classes could have different statuses.
				 * We need to check if multiple class numbers are found for a single class entry in the SIS - if this is the case,
				 * the client will be informed to trust the details page for the class to get relevant information for each separate class.
				 */
				$class_nbrs = $detailsXpath->evaluate("/html/body/table/tr[position()>1]/td[position()=1]/div/span");
				
				$unique_classes = 0;
				for($a = 0; $a < $class_nbrs->length; $a++) {
					if(intval($class_nbrs->item($a)->nodeValue) !== 0)
						$unique_classes++;
				}
				
				if($unique_classes > 1)
				{
					$sections = $detailsXpath->evaluate("/html/body/table/tr[position()>1]/td[position()=2]");
					$components = $detailsXpath->evaluate("/html/body/table/tr[position()>1]/td[position()=3]/div/span");
					
					//Erase this class's XML entry so far. We'll need to inject multiple instances instead.
					$section_objs = array();
					for($a = 0; $a < $class_nbrs->length; $a++)
					{
						$deadlines_id = str_replace('DEADLINES$', '', $deadlineLink->item(0)->getAttribute('name'));
						$class = new ClassObject('incomplete', 'schedule');
						$class->setCourseId(trim($classParts[0]));
						$class->setCourseName(trim($classParts[1]));
						$class->setActionId($actionable_id);
						$class->setStatus($status_cells->item(0)->nodeValue);
						$class->setDeadlinesId($deadlines_id);
						
						/*
						 * A 'Waiting' status indicates that a waitlist position cell is present in the response.
						 * This means some cells are shifted from where they would normally be if the status was 'Enrolled'.
						 */
						if(trim($status_cells->item(0)->nodeValue) === 'Waiting') {
							$class->setWaitlistPos($status_cells->item(1)->nodeValue);
							$class->setUnits($status_cells->item(2)->nodeValue);
							$class->setGrading($status_cells->item(3)->nodeValue);
						} else {
							$class->setUnits($status_cells->item(1)->nodeValue);
							$class->setGrading($status_cells->item(2)->nodeValue);
						}
						
						$sectionNode = $sections->item($a)->getElementsByTagName('span')->item(0)->getElementsByTagName('a')->item(0);
						$class->setClassNbr($class_nbrs->item($a)->nodeValue);
						$class->setSection($sectionNode->nodeValue);
						$class->setComponent($components->item($a)->nodeValue);
						
						$deadlines_id = str_replace('DEADLINES$', '', $deadlineLink->item(0)->getAttribute('name'));
						$class->setDetailsId(str_replace('MTG_SECTION$', '', $sectionNode->getAttribute('name')));
						$class->setDeadlinesId($deadlines_id);
						
						$dtg = $detailsXpath->evaluate("/html/body/table/tr[position()>1]/td[position()=4]/div/span");
						$room = $detailsXpath->evaluate("/html/body/table/tr[position()>1]/td[position()=5]/div/span");
						$instructor = $detailsXpath->evaluate("/html/body/table/tr[position()>1]/td[position()=6]/div/span");
						$start_end = $detailsXpath->evaluate("/html/body/table/tr[position()>1]/td[position()=7]/div/span");
						
						/*
						 * This iterator checks if the next row is an additional schedule
						 * entry for the current class; if it is, it stores those details
						 * for the current class. If it reaches the end of the class's DOM region
						 * or if it detects an additional class in the next record, it stops and
						 * sets $a to be of a value that allows the main class iterator to continue. 
						 */
						$scheduleIterator = $a;
						do {
							$schedule = new ScheduleObject();
							$schedule->setDTG($dtg->item($scheduleIterator)->nodeValue);
							$schedule->setRoom($room->item($scheduleIterator)->nodeValue);
							$schedule->setInstructor($instructor->item($scheduleIterator)->nodeValue);
							$schedule->setStartEnd($start_end->item($scheduleIterator)->nodeValue);
							$class->addObject($schedule);
						} while((++$scheduleIterator) < $class_nbrs->length && intval($class_nbrs->item($scheduleIterator)->nodeValue) === 0);
						$a = $scheduleIterator-1;
						
						parse_class_details($job, $class->obj['sis_details_id'], $class->obj['sis_deadlines_id'], $class);
						array_push($section_objs, $class);
					}
					
					/*
					 * The following code attempts to resolve a SIS problem involving
					 * the number of credits assigned to a section. TSR trusts the details page
					 * for a section more than the class schedule page, but often Lab sections
					 * for a class will have the same # of credits as the Lecture - i.e., a 3 credit
					 * class where the Lab is really 0 credits shows up as 6 credits when the details
					 * pages in the SIS are consulted. On the schedule page, we interpret the credits
					 * shown as the *combined* credit value of all underlying sections. Since some Lab
					 * sections could be 1 credit, TSR runs the following check to give credit priority
					 * to the Lecture section (if exists), and assigns the remaining credit to the remaining sections.
					 */
					foreach($section_objs as $cls) {
						if($cls->getComponent()=='Lecture') {
							$credit_cap -= floatval($cls->getUnits());
							break;
						}
					}
					
					foreach($section_objs as $cls) {
						if($cls->getComponent()!='Lecture') {
							if(floatval($cls->getUnits()) > $credit_cap) {
								$cls->setUnits($credit_cap);
								$credit_cap = 0;
							} else {
								$credit_cap -= floatval($cls->getUnits());
								if($credit_cap < 0)
									$credit_cap = 0;
							}
						}
						//Only add the class to the ResponseBuilder once the credits have been adjusted.
						$builder->addObject($cls);
					}
				}
				else
				{
					$class_nbr = $detailsXpath->evaluate("/html/body/table/tr[position()=2]/td[position()=1]/div/span");
					$section = $detailsXpath->evaluate("/html/body/table/tr[position()=2]/td[position()=2]/div/span/a");
					$component = $detailsXpath->evaluate("/html/body/table/tr[position()=2]/td[position()=3]/div/span");
					
					$deadlines_id = str_replace('DEADLINES$', '', $deadlineLink->item(0)->getAttribute('name'));
					$class->setClassNbr($class_nbr->item(0)->nodeValue);
					$class->setSection($section->item(0)->nodeValue);
					$class->setDetailsId(str_replace('MTG_SECTION$', '', $section->item(0)->getAttribute('name')));
					$class->setComponent($component->item(0)->nodeValue);
					$class->setDeadlinesId($deadlines_id);
					
					$scheduleRows = $detailsXpath->evaluate("/html/body/table/tr[position()>1]");
					
					for($schedule_counter = 0; $schedule_counter < $scheduleRows->length; $schedule_counter++)
					{
						$dtg = $detailsXpath->evaluate("/html/body/table/tr[position()=".($schedule_counter+2)."]/td[position()=4]/div/span");
						$room = $detailsXpath->evaluate("/html/body/table/tr[position()=".($schedule_counter+2)."]/td[position()=5]/div/span");
						$instructor = $detailsXpath->evaluate("/html/body/table/tr[position()=".($schedule_counter+2)."]/td[position()=6]/div/span");
						$start_end = $detailsXpath->evaluate("/html/body/table/tr[position()=".($schedule_counter+2)."]/td[position()=7]/div/span");
						
						$schedule = new ScheduleObject();
						$schedule->setDTG($dtg->item(0)->nodeValue);
						$schedule->setRoom($room->item(0)->nodeValue);
						$schedule->setInstructor($instructor->item(0)->nodeValue);
						$schedule->setStartEnd($start_end->item(0)->nodeValue);
						$class->addObject($schedule);
					}
					
					parse_class_details($job, $class->obj['sis_details_id'], $class->obj['sis_deadlines_id'], $class);
					$builder->addObject($class);
				}
				
				$status_counter+=2;
				$detail_counter+=2;
				$actionable_id++;
			}
		} else {
			//error_log('FATAL ERROR: Unexpected error while parsing the class schedule for the specified term.');
			//$builder = new ResponseBuilder('error');
			
			/*
			 * MQuinn: 10/15/2010
			 * Previously, reaching this was considered an error. This will now assume there is no schedule
			 * for the selected term, and thus no classes will be appended to the JSON response.  The client-side JS
			 * should interpret this as an indication that no classes are enrolled in for the selected term.  Thus, simply
			 * return to the calling code and allow an echoResponse to occur.
			 */
		}
	}
	
	/*
	 * Execution starts here.
	 */
	try
	{
		$job = new Job('synchronize');
	
		//$sisResponse = $sisChannel->sisGetRequest(FALSE, 'SA_LEARNER_SERVICES.SSR_SSENRL_LIST.GBL?Page=SSR_SSENRL_LIST&Action=A');
		$sisResponse = $job->transact(array('HttpMethod'=>'GET','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_LIST.GBL?Page=SSR_SSENRL_LIST&Action=A'));
		
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		
		$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
		$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
		
		$dbAdapter = new AuthenticatedDbAdapter();
		$dbAdapter->updateAccessVars($_COOKIE['access_key'], $ICSID, $ICStateNum);
		
		/*
		 * These evaluations check to see if there are multiple class
		 * schedule terms available for display by the SIS.
		 */
		$termIds = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td[@class='PSLEVEL2GRIDROW']/div/input[@type='radio']");
		$termNames = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td[@class='PSLEVEL2GRIDROW'][position()=2]/div/span[@class='PSEDITBOX_DISPONLY']");
		
		if($termIds->length > 0 && $termIds->length === $termNames->length) {
			//Find the SIS ID that matches the term name supplied by the client.
			$sis_id = $dbAdapter->getSisIdForTermName($term_name);
			if($sis_id === false) {
				error_log('Unable to match ' . $term_name . ' to a SIS ID in the database.');
				$builder = new ResponseBuilder('error');
				$builder->echoResponse();
				exit();
			} else {
				$sis_id = pg_fetch_assoc($sis_id);
				$sis_id = $sis_id['sis_id'];
			}
			
			//$sisResponse = $sisChannel->sisPostRequest(FALSE, 'SA_LEARNER_SERVICES.SSR_SSENRL_LIST.GBL', 'ICAction=DERIVED_SSS_SCT_SSR_PB_GO&SSR_DUMMY_RECV1%24sels%240='.$sis_id);
			$sisResponse = $job->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_LIST.GBL','PostParams'=>('ICAction=DERIVED_SSS_SCT_SSR_PB_GO&SSR_DUMMY_RECV1%24sels%240='.$sis_id)));
		
			$dom = new DOMDocument;
			$dom->loadHTML($sisResponse);
			$xpath = new DOMXPath($dom);
			
			$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
			$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
	
			$dbAdapter->updateAccessVars($_COOKIE['access_key'], $ICSID, $ICStateNum);
		}
		
		$builder = new ResponseBuilder('success');
		parse_class_schedule($job, $sisResponse, $builder);
		
		/*
		 * END COURSE SCHEDULE PARSING
		 * BEGIN EXAM SCHEDULE PARSING
		 */
		
		//$sisResponse = $sisChannel->sisGetRequest(FALSE, 'SA_LEARNER_SERVICES.SSR_SSENRL_EXAM_L.GBL?Page=SSR_SSENRL_EXAM_L&Action=U');
		$sisResponse = $job->transact(array('HttpMethod'=>'GET','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_EXAM_L.GBL?Page=SSR_SSENRL_EXAM_L&Action=U'));
		
		$dom = new DOMDocument;
		$dom->loadHTML($sisResponse);
		$xpath = new DOMXPath($dom);
		
		$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
		$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
		
		$dbAdapter = new AuthenticatedDbAdapter();
		$dbAdapter->updateAccessVars($_COOKIE['access_key'], $ICSID, $ICStateNum);
		
		/*
		 * These evaluations check to see if there are multiple exam
		 * schedule terms available for display by the SIS.
		 */
		$termIds = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td[@class='PSLEVEL2GRIDROW']/div/input[@type='radio']");
		$termNames = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table/tr/td[@class='PSLEVEL2GRIDROW'][position()=2]/div/span[@class='PSEDITBOX_DISPONLY']");
		
		if($termIds->length > 0 && $termIds->length === $termNames->length) {
			//Find the SIS ID that matches the term name supplied by the client.
			$sis_id = $dbAdapter->getSisIdForTermName($term_name);
			if($sis_id === false) {
				/*THIS IS NOT AN ERROR; simply return the response if no exam schedule is available.*/
				$builder->echoResponse();
				exit();
			} else {
				$sis_id = pg_fetch_assoc($sis_id);
				$sis_id = $sis_id['sis_id'];
			}
			
			/*
			 * MQUINN (1/1/2011)
			 * This Job transaction hasn't been tested in
			 * a situation where exam schedules are available.
			 */
			//$sisResponse = $sisChannel->sisPostRequest(FALSE, 'SA_LEARNER_SERVICES.SSR_SSENRL_EXAM_L.GBL', 'ICAction=DERIVED_SSS_SCT_SSR_PB_GO&SSR_DUMMY_RECV1%24sels%240='.$sis_id);
			$sisResponse = $job->transact(array('HttpMethod'=>'POST','NeedHeader'=>'false','Path'=>'SA_LEARNER_SERVICES.SSR_SSENRL_EXAM_L.GBL','PostParams'=>('ICAction=DERIVED_SSS_SCT_SSR_PB_GO&SSR_DUMMY_RECV1%24sels%240='.$sis_id)));
			
			$dom = new DOMDocument;
			$dom->loadHTML($sisResponse);
			$xpath = new DOMXPath($dom);
			
			$ICSID = $xpath->evaluate("/html/body/form/input[@name='ICSID']")->item(0)->getAttribute("value");
			$ICStateNum = $xpath->evaluate("/html/body/form/input[@name='ICStateNum']")->item(0)->getAttribute("value");
		}
		
		$exams = $xpath->evaluate("/html/body/form/div/table/tr/td/div/table/tr/td/div/table[@class='PSLEVEL1GRIDWBO']/tr[position()>1]");
		
		if($exams->length > 0) {
			$examsObj = new ExamScheduleObject();
		
			for($i=0; $i<$exams->length; $i++) {
				$parts = explode('-', trim($exams->item($i)->getElementsByTagName('td')->item(0)->nodeValue));
				$name = $parts[0];
				$date = trim($exams->item($i)->getElementsByTagName('td')->item(3)->nodeValue);
				$parts = explode('-', trim($exams->item($i)->getElementsByTagName('td')->item(4)->nodeValue));
				$start_time = trim($parts[0]);
				$end_time = trim($parts[1]);
				$room = trim($exams->item($i)->getElementsByTagName('td')->item(5)->nodeValue);
				$examsObj->addExam($name, $date, $start_time, $end_time, $room);
			}
			
			$builder->addObject($examsObj);
		}
		
		/*
		 * END EXAM SCHEDULE PARSING
		 */
		
		$builder->syncResponseWithDatabase($term_name);
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