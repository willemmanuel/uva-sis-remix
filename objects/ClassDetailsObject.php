<?php

class ClassDetailsObject
{
	private $obj;
	
	public function __construct()
	{
        $this->obj = array();
        
        /*
         * Define empty arrays for these properties, even if nothing is returned.
         * This prevents the client-side code from having to run undefined checks
         * on these properties.
         */
        $this->obj["evaluations"] = array();
        $this->obj["deadlines"] = array();
        $this->obj["reqs"] = array();
	}
	
	public function setDescription($data)
	{
		$this->obj["description"] = $data;
	}
	
	public function setLocation($data)
	{
		$this->obj["location"] = $data;
	}
	
	public function setCampus($data)
	{
		$this->obj["campus"] = $data;
	}
	
	public function setInstructionMode($data)
	{
		$this->obj["instruction_mode"] = $data;
	}
	
	public function setGradingMode($data)
	{
		$this->obj["grading_mode"] = trim($data);
	}
	
	public function setCredits($data)
	{
		$this->obj["units"] = trim(str_replace('units', '', str_replace('.00', '', $data)));
	}
	
	public function setUnits($data)
	{
		$this->obj["units"] = trim(str_replace('units', '', str_replace('.00', '', $data)));
	}
	
	public function addEvaluation($data)
	{
		array_push($this->obj["evaluations"], $data);
	}
	
	public function addDeadline($date, $desc)
	{
		array_push($this->obj["deadlines"], array("date"=>$date, "desc"=>$desc));
	}
	
	public function setTopic($data)
	{
		$this->obj["topic"] = $data;
	}
	
	public function addReq($data)
	{
		array_push($this->obj["reqs"], $data);
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