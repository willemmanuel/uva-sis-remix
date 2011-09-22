<?php

class ScheduleObject
{
	private $obj;
	
	public function __construct()
	{
        $this->obj = array();
        $this->obj['day'] = array();
	}
	
	public function setDTG($data)
	{
		if(trim($data) === 'TBA') {
			$this->obj['dtg'] = 'TBA';
			return;
		}
			
		$parts = explode(' ', $data);
		for($i = 0; ($i+1)<strlen($parts[0]); $i+=2)
			array_push($this->obj['day'], array("id"=>sha1(substr($parts[0], $i, 2).trim($parts[1]).trim($parts[3])), "code"=>substr($parts[0], $i, 2)));

		$this->obj['start_time'] = trim($parts[1]);
		$this->obj['end_time'] = trim($parts[3]);
	}
	
	/*
	 * The last three parameters are used in the hashing computation only.
	 * They are required to guarantee uniqueness as the Calendar module
	 * manipulates entries by the generated hash.
	 */
	public function addDay($day,$start_time,$end_time,$class_nbr) {
		array_push($this->obj['day'], array("id"=>sha1($day.trim($start_time).trim($end_time).$class_nbr), "code"=>$day));
	}
	
	public function setStartTime($start_time) {
		$this->obj['start_time'] = trim($start_time);
	}
	
	public function setEndTime($end_time) {
		$this->obj['end_time'] = trim($end_time);
	}
	
	public function setRoom($data)
	{
		$this->obj['room'] = trim($data);
	}
	
	public function setInstructor($data)
	{
		$this->obj['instructor'] = trim($data);
	}
	
	public function setStartDate($data) {
		$this->obj['start_date'] = trim($data);
	}
	
	public function setEndDate($data) {
		$this->obj['end_date'] = trim($data);
	}
	
	public function setStartEnd($data)
	{
		$parts = explode('-', $data);
		$this->obj['start_date'] = trim($parts[0]);
		$this->obj['end_date'] = trim($parts[1]);
	}
	
	public function toArray()
	{
		if(!isset($this->obj['start_time']) || !isset($this->obj['end_time'])) {
			$this->obj['dtg'] = 'TBA';
		}
		return $this->obj;
	}
	
	public function __destruct()
	{
		$this->obj = null;
    }
}

?>