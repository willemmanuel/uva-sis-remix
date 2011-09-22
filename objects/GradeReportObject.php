<?php

class GradeReportObject
{
	private $obj;
	
	public function __construct()
	{
        $this->obj = array("id"=>sha1(mt_rand()));
	}
	
	public function setGPA($data)
	{
		$this->obj["gpa"] = $data;
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