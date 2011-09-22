<?php

class ClassObject
{
	public $obj;
	
	public function __construct($type = '', $origin)
	{
        if(isset($type))
        	$this->obj = array("status"=>$type);
        $this->obj["origin"] = $origin;
	}
	
	public function setCourseId($data)
	{
		$this->obj["course_id"] = $data;
	}
	
	public function setCourseName($data)
	{
		$this->obj["course_name"] = $data;
	}
	
	public function setActionId($data)
	{
		$this->obj["sis_action_id"] = $data;
	}
	
	public function setStatus($data)
	{
		$this->obj["status"] = $data;
	}
	
	public function setWaitlistPos($data)
	{
		$this->obj["waitlist_pos"] = $data;
	}
	
	public function setUnits($data)
	{
		$this->obj["units"] = trim(str_replace('units', '', str_replace('.00', '', $data)));
	}
	
	public function getUnits()
	{
		return $this->obj["units"];
	}
	
	public function setGrading($data)
	{
		$this->obj["grading"] = trim($data);
	}
	
	public function setDeadlinesId($data)
	{
		$this->obj["sis_deadlines_id"] = $data;
	}
	
	public function setClassNbr($data)
	{
		$this->obj["class_nbr"] = $data;
	}
	
	public function setSection($data)
	{
		$this->obj["section"] = intval($data);
	}
	
	public function setDetailsId($data)
	{
		$this->obj["sis_details_id"] = $data;
	}
	
	public function setComponent($data)
	{
		$this->obj["component"] = $data;
	}
	
	public function getComponent()
	{
		return $this->obj["component"];
	}
	
	/* Added for Class Search */
	
	public function setSectionStatus($data)
	{
		$this->obj["section_status"] = trim($data);
	}
	
	public function setEnrollmentMax($data)
	{
		$this->obj["enrollment_max"] = $data;
	}
	
	public function setEnrollmentTotal($data)
	{
		$this->obj["enrollment_total"] = $data;
	}
	
	public function setWaitlistMax($data)
	{
		$this->obj["waitlist_max"] = $data;
	}
	
	public function setWaitlistTotal($data)
	{
		$this->obj["waitlist_total"] = $data;
	}
	
	public function addObject($obj)
	{
		if($obj instanceof ScheduleObject) {
			if(!isset($this->obj["schedule"]))
				$this->obj["schedule"] = array();
			array_push($this->obj["schedule"], $obj->toArray());
		}
		
		if($obj instanceof ClassDetailsObject) {
			if(!isset($this->obj["cls_details"]))
				$this->obj["cls_details"] = array();
			array_push($this->obj["cls_details"], $obj->toArray());
		}
	}
	
	public function toArray()
	{
		$this->obj["id"] = sha1(json_encode($this->obj));
        return $this->obj;
	}
	
	public function __destruct()
	{
		$this->obj = null;
    }
}

?>