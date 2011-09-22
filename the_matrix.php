<?php

	include_once('$TSR_BUILD$includes_path$TSR_BUILD$Security.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$ResponseBuilder.php');
	include_once('$TSR_BUILD$includes_path$TSR_BUILD$AuthenticatedDbAdapter.php');
	
	if(!isset($_REQUEST['tsr_action'])) {
		exit();
	}
	
	switch($_REQUEST['tsr_action']) {
		case 'authenticate': {
			include_once('$TSR_BUILD$includes_path$TSR_BUILD$procedures/authenticate.php');
			break;
		} case 'synchronize_account': {
			tsr_validate();
			$term_name = $_REQUEST['term'];
			include_once('$TSR_BUILD$includes_path$TSR_BUILD$procedures/synchronize_account.php');
			break;
		} case 'execute_course_search': {
			tsr_validate();
			include_once('$TSR_BUILD$includes_path$TSR_BUILD$procedures/execute_course_search.php');
			break;
		} case 'drop_class_prepare': {
			tsr_validate();
			include_once('$TSR_BUILD$includes_path$TSR_BUILD$procedures/drop_class_prepare.php');
			break;
		} case 'drop_class_execute': {
			tsr_validate();
			include_once('$TSR_BUILD$includes_path$TSR_BUILD$procedures/drop_class_execute.php');
			break;
		} case 'download_calendar': {
			tsr_validate();
			include_once('$TSR_BUILD$includes_path$TSR_BUILD$procedures/download_calendar.php');
			break;
		} case 'anon_comment_submit': {
			tsr_validate();
			mail('thesisremix@gmail.com', 'TSR Anonymous Comment', $_POST['comment'], 'From: mjq4aq@virginia.edu');
			$builder = new ResponseBuilder('success');
			$builder->echoResponse();
			break;
		} default: {
			tsr_validate();
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			break;
		}
	}
	
?>