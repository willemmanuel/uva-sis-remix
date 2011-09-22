<?php

	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$AuthenticatedDbAdapter.php');
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$ResponseBuilder.php');
	
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$objects/ClassObject.php');
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$objects/ClassDetailsObject.php');
	include_once ('$TSR_BUILD$includes_path$TSR_BUILD$objects/ScheduleObject.php');
	
	try
	{
		$builder = new ResponseBuilder('success');
		
		$dbAdapter = new AuthenticatedDbAdapter();
		$search_results = $dbAdapter->parse_query(trim($_REQUEST['query']), $_REQUEST['term']);
		
		$prev_section_id = false;
		$requires_init = true;
		$sct = new ClassObject('', 'search');
		$details_obj = new ClassDetailsObject();
		$instructors = array();
		
		while($record = pg_fetch_array($search_results)) {
			if($prev_section_id != $record['section_id'] && $prev_section_id!=false) {
				foreach($instructors as $key=>$value) {
					$details_obj->addEvaluation($key);
				}
				$sct->addObject($details_obj);
				$builder->addObject($sct);
				$sct = new ClassObject('', 'search');
				$details_obj = new ClassDetailsObject();
				$instructors = array();
				$requires_init = true;
			}
			
			if($requires_init) {
				$sct->setCourseId($record['acronym'] . ' ' . $record['course_num']);
				$sct->setCourseName($record['course_name']);
				$details_obj->setGradingMode($record['grading_basis']);
				
				$sct->setSection($record['section_nbr']);
				$sct->setComponent($record['component']);
				$sct->setClassNbr($record['class_nbr']);
				$sct->setSectionStatus($record['status']);
				
				$sct->setEnrollmentMax($record['capacity']);
				$sct->setEnrollmentTotal($record['enrolled']);
				$sct->setWaitlistMax($record['waitlist_capacity']);
				$sct->setWaitlistTotal($record['waitlist_total']);
	
				$details_obj->setDescription($record['description']);
				$details_obj->setLocation($record['loc']);
				$details_obj->setCampus($record['campus']);
				$details_obj->setCredits($record['credits']);
				$details_obj->setInstructionMode($record['inst_mode']);
				if($record['requirement'] != '')
					$details_obj->addReq($record['requirement']);
					
				$requires_init = false;
			}
			
			$schedule = new ScheduleObject();
			$schedule->setStartTime($record['start_time']);
			$schedule->setEndTime($record['end_time']);
			$schedule->setRoom($record['room']);
			$schedule->setInstructor(trim(str_replace('|', ' ', $record['instructor'])));
			
			if(strlen($record['instructor'])!==0) {
				$instructors[trim(str_replace('|', ' ', $record['instructor']))] = true;
			}
			
			$schedule->setStartDate($record['start_date']);
			$schedule->setEndDate($record['end_date']);
			
			for($i = 0; ($i+1)<strlen($record['days']); $i+=2)
				$schedule->addDay(substr($record['days'], $i, 2), $record['start_time'], $record['end_time'], $record['class_nbr']);
			
			$sct->addObject($schedule);
			$prev_section_id = $record['section_id'];
		}
		foreach($instructors as $key=>$value) {
			$details_obj->addEvaluation($key);
		}
		$sct->addObject($details_obj);
		$builder->addObject($sct);
		$builder->echoResponse();
	}
	catch (Exception $e)
	{
		error_log($e->getMessage());
		$builder = new ResponseBuilder('error');
		$builder->echoResponse();
		exit();
	}
?>