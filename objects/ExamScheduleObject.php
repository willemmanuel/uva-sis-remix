<?php

class ExamScheduleObject
{
	public $obj;
	
	public function __construct()
	{
		$this->obj["exams"] = array();
	}
	
	public function addExam($name, $date, $start_time, $end_time, $room)
	{
		array_push($this->obj["exams"], array("name"=>$name, "date"=>$date, "start_time"=>$start_time, "end_time"=>$end_time, "room"=>$room));
	}
	
	public function toArray()
	{
        return $this->obj;
	}
	
	public function __destruct()
	{
		$this->obj = null;
    }
}

?>