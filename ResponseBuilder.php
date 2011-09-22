<?php

include_once ('AuthenticatedDbAdapter.php');

class ResponseBuilder
{
	public $json;
	
	public function __construct($status)
	{
		$this->json = array("status"=>$status);
	}
	
	public function addRootAttribute($name, $value)
	{
		$this->json[$name]=$value;
	}
	
	public function addTermObject($id, $name, $select)
	{
		if(!isset($this->json["term"]))
			$this->json["term"] = array();
			
		if(preg_match("/[0-9]{4}/", $name, $matches))
			$name = trim(preg_replace("/[0-9]{4}/", "", $name)) . ' ' . trim($matches[0]);
		array_push($this->json["term"], array("select"=>$select, "sis_id"=>$id, "name"=>$name));
	}
	
	public function addEnrollRequestObject($class_nbr)
	{
		if(!isset($this->json["enroll_req"]))
			$this->json["enroll_req"] = array();
			
		array_push($this->json["enroll_req"], array("class_nbr"=>$class_nbr));
	}
	
	public function addObject($obj)
	{
		if($obj instanceof GradeReportObject) {
			if(!isset($this->json["grade_report"]))
				$this->json["grade_report"] = array();
			array_push($this->json["grade_report"], $obj->toArray());
		}
		
		if($obj instanceof ClassObject) {
			if(!isset($this->json["cls"]))
				$this->json["cls"] = array();
			array_push($this->json["cls"], $obj->toArray());
		}
		
		if($obj instanceof ClassDetailsObject) {
			if(!isset($this->json["cls_details"]))
				$this->json["cls_details"] = array();
			array_push($this->json["cls_details"], $obj->toArray());
		}
		
		if($obj instanceof ExamScheduleObject) {
			if(!isset($this->json["exam_schedule"]))
				$this->json["exam_schedule"] = array();
			array_push($this->json["exam_schedule"], $obj->toArray());
		}
	}
	
	public function echoResponse()
	{
		header('Content-Type: application/json');
		echo json_encode($this->json);
		//error_log(json_encode($this->json));
	}
	
	public function syncResponseWithDatabase($term_name) {
		$dbAdapter = new AuthenticatedDbAdapter();
		if(isset($this->json["cls"]))
			$dbAdapter->setUserClassesForTerm($term_name, $this->json["cls"]);
		if(isset($this->json["exam_schedule"]))
			$dbAdapter->setUserExamsForTerm($term_name, $this->json["exam_schedule"]);
	}
	
	public function __destruct()
	{
		$this->obj = null;
    }
}

?>