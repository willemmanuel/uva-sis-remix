<?php

class DatabaseAdapter
{
	private $dbConnection;
	
	public function __construct($parser_type)
	{
		if($parser_type=='parser_terms') {
			$this->dbConnection = pg_connect('host=localhost port=5432 dbname=uva_sis_data user=parser_term password=$BUILD$redacted$BUILD$')
			or die ("Unable to establish database connection as parser.");
		} else {
			$this->dbConnection = pg_connect('host=localhost port=5432 dbname=uva_sis_data user=parser_core password=$BUILD$redacted$BUILD$')
			or die ("Unable to establish database connection as parser.");
		}
	}
	
	public function processSQL($cmd) {
		return str_replace("''", "NULL", $cmd);
	}
	
	public function escape($input) {
		if(is_array($input)) {
			foreach($input as $key=>$value) {
				$input[$key] = trim(pg_escape_string($value));
			}
			return $input;
		} else {
			return pg_escape_string($input);
		}
	}
	
	/*
	 * BEGIN
	 * TERM OPERATIONS
	 */
	public function getTerms() {
		$result = pg_exec($this->dbConnection, $this->processSQL('SELECT * FROM data.term;'));
		
		if($result!=false)
			return $result;
		else {
			error_log("CRON JOB FATAL ERROR: Unable to retrieve available terms from database.");
			return false;
		}
	}
	
	public function addTerm($name, $sis_id) {
		$result = pg_exec($this->dbConnection, $this->processSQL("INSERT INTO data.term (name, sis_id) VALUES ('$name', $sis_id);"));
		
		if(!$result) {
			error_log("CRON JOB FATAL ERROR: Unable to add term to database.");
			return false;
		}
	}
	
	public function updateTerm($name, $sis_id) {
		$result = pg_exec($this->dbConnection, $this->processSQL("UPDATE data.term SET sis_id=$sis_id WHERE name='$name';"));
		
		if(!$result) {
			error_log("CRON JOB FATAL ERROR: Unable to update term with name: $name.");
			return false;
		}
	}
	
	public function deleteTerm($name) {
		$result = pg_exec($this->dbConnection, $this->processSQL("DELETE FROM data.term WHERE name='$name';"));
		
		if(!$result) {
			error_log("CRON JOB FATAL ERROR: Unable to delete term with name: $name.");
			return false;
		}
	}
	/*
	 * END
	 * TERM OPERATIONS
	 */
	
	/*
	 * BEGIN
	 * DEPARTMENT OPERATIONS
	 */
	public function getDepartments() {
		$result = pg_exec($this->dbConnection, $this->processSQL("SELECT * FROM data.dept;"));
		if($result) {
			return $result;
		} else {
			error_log("FATAL ERROR: getDepartments() query failed.");
			exit();
		}
	}
	
	public function addDepartment($acronym, $name, $hash) {
		$result = pg_exec($this->dbConnection, $this->processSQL("INSERT INTO data.dept (acronym, name, hash) VALUES ('$acronym', '".$this->escape($name)."', '$hash');"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: addDepartment($acronym, $name, $hash) query failed.");
			exit();
		}
	}
	
	public function updateDepartment($acronym, $name, $hash) {
		$result = pg_exec($this->dbConnection, $this->processSQL("UPDATE data.dept SET name='".$this->escape($name)."', hash='$hash' WHERE acronym='$acronym';"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: updateDepartment($acronym, $name, $hash) query failed.");
			exit();
		}
	}
	
	public function deleteDepartment($acronym) {
		$result = pg_exec($this->dbConnection, $this->processSQL("DELETE FROM data.dept WHERE acronym='$acronym';"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: deleteDepartment($acronym) query failed.");
			exit();
		}
	}
	/*
	 * END
	 * DEPARTMENT OPERATIONS
	 */
	
	
	/*
	 * BEGIN
	 * COURSE OPERATIONS
	 */
	public function getCourse($dept,$num) {
		$result = pg_exec($this->dbConnection, $this->processSQL("SELECT hash FROM data.course WHERE dept='$dept' AND num='$num';"));
		if($result) {
			return $result;
		} else {
			error_log("FATAL ERROR: getCourse($dept, $num) query failed.");
			exit();
		}
	}
	
	public function addCourse($dept_acronym, $course_num, $course_name, $course_hash) {
		//SQL is formatted this way to prevent duplicate key insertion errors (PostgreSQL doesn't have an IGNORE or ON DUPLICATE clause.
		$result = pg_exec($this->dbConnection, $this->processSQL("INSERT INTO data.course (dept, num, name, hash) SELECT '$dept_acronym', $course_num, '".$this->escape($course_name)."', '$course_hash' WHERE NOT EXISTS (SELECT hash FROM data.course WHERE dept = '$dept_acronym' AND num = $course_num);"));
			
			
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: addCourse($dept_acronym, $course_num, $course_name, $course_hash) query failed.");
			exit();
		}
	}
	
	public function updateCourse($dept_acronym, $course_num, $course_name, $course_hash) {
		$result = pg_exec($this->dbConnection, $this->processSQL("UPDATE data.course SET name='".$this->escape($course_name)."', hash='$course_hash' WHERE dept='$dept_acronym' AND num='$course_num';"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: updateCourse($dept_acronym, $course_num, $course_name, $course_hash) query failed.");
			exit();
		}
	}
	
	public function deleteCourse($dept_acronym, $course_num) {
		$result = pg_exec($this->dbConnection, $this->processSQL("DELETE FROM data.course WHERE dept='$dept_acronym' AND num='$course_num';"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: deleteCourse($dept_acronym, $course_num) query failed.");
			exit();
		}
	}
	/*
	 * END
	 * COURSE OPERATIONS
	 */
	
	
	/*
	 * BEGIN
	 * SECTION OPERATIONS
	 */
	public function getSectionsForCourseInTerm($course_dept, $course_num, $term) {
		$result = pg_exec($this->dbConnection, $this->processSQL("SELECT section_id, hash FROM data.section WHERE dept = '$course_dept' AND course_num = '$course_num' AND term = '$term';"));
		if($result) {
			return $result;
		} else {
			error_log("FATAL ERROR: getSectionsForCourseInTerm($course_dept, $course_num, $term) query failed.");
			exit();
		}
	}
	
	public function addSection($section_id, $dept_acronym, $course_num, $term_name, $sql) {
		$sql = $this->escape($sql);
		$result = pg_exec($this->dbConnection, $this->processSQL("INSERT INTO data.section (section_id, dept, course_num, term, component, num, topic, grading_basis, inst_mode, univ_session, career, campus, location, status, description, credits, capacity, enrolled, waitlist_capacity, waitlist_total, hash) VALUES ('$section_id', '$dept_acronym', '$course_num', '$term_name', '".$sql['component']."', '".$sql['section']."', '".$sql['topic']."', '".$sql['grading_basis']."', '".$sql['inst_mode']."', '".$sql['univ_session']."', '".$sql['career']."', '".$sql['campus']."', '".$sql['location']."', '".$sql['status']."', '".$sql['description']."', '".$sql['credits']."', '".$sql['capacity']."', '".$sql['enrolled']."', '".$sql['waitlist_capacity']."', '".$sql['waitlist_total']."', '".$sql['hash']."');"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: addSection($section_id, $dept_acronym, $course_num, $term_name, ".implode($sql).") query failed.");
			exit();
		}
	}
	
	public function updateSection($section_id, $dept_acronym, $course_num, $term_name, $sql) {
		$sql = $this->escape($sql);
		$result = pg_exec($this->dbConnection, $this->processSQL("UPDATE data.section SET dept='$dept_acronym', course_num='$course_num', component='".$sql['component']."', num='".$sql['section']."', topic='".$sql['topic']."', grading_basis='".$sql['grading_basis']."', inst_mode='".$sql['inst_mode']."', univ_session='".$sql['univ_session']."', career='".$sql['career']."', campus='".$sql['campus']."', location='".$sql['location']."', status='".$sql['status']."', description='".$sql['description']."', credits='".$sql['credits']."', capacity='".$sql['capacity']."', enrolled='".$sql['enrolled']."', waitlist_capacity='".$sql['waitlist_capacity']."', waitlist_total='".$sql['waitlist_total']."', hash='".$sql['hash']."' WHERE section_id='$section_id' AND term = '$term_name';"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: updateSection($section_id, $dept_acronym, $course_num, $term_name, ".implode($sql).") query failed.");
			exit();
		}
	}
	
	public function deleteSection($section_id, $term_name) {
		$result = pg_exec($this->dbConnection, $this->processSQL("DELETE FROM data.section WHERE section_id='$section_id' AND term = '$term_name';"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: deleteSection($section_id, $term_name) query failed.");
			exit();
		}
	}
	
	public function addSectionKVPairs($section_id, $term_name, $section_kv_pairs) {
		$section_kv_pairs = $this->escape($section_kv_pairs);
		
		//Clear all previous kv pairs for this section; we'll start clean.
		$result = pg_exec($this->dbConnection, $this->processSQL("DELETE FROM data.section_kv_pair WHERE section_id=$section_id AND term='$term_name';"));
		
		//Cycle through each kv pair and add to database.
		foreach($section_kv_pairs as $key=>$value) {
			$result = pg_exec($this->dbConnection, $this->processSQL("INSERT INTO data.section_kv_pair (section_id, term, \"key\", \"value\") VALUES ($section_id, '$term_name', '".$this->escape($key)."', '$value');"));
			if(!$result) {
				error_log("FATAL ERROR: addSectionKVPairs($section_id, $term_name, ".implode($section_kv_pairs).") query failed.");
				exit();
			}
		}
	}
	
	public function addSectionDependencies($section_id, $comp_deps) {
		$comp_deps = $this->escape($comp_deps);
		
		//Clear all previous section dependencies for this section.
		$result = pg_exec($this->dbConnection, $this->processSQL("DELETE FROM data.section_dependency WHERE section_id=$section_id;"));
		
		//Cycle through each dependency and add to database.
		foreach($comp_deps as $component) {
			$result = pg_exec($this->dbConnection, $this->processSQL("INSERT INTO data.section_dependency (section_id, component) VALUES ('$section_id', '$component');"));
			if(!$result) {
				error_log("FATAL ERROR: addSectionDependencies($section_id, ".implode($comp_deps).") query failed.");
				exit();
			}
		}
	}
	/*
	 * END
	 * SECTION OPERATIONS
	 */
	
	/*
	 * BEGIN
	 * SCHEDULE OPERATIONS
	 */
	public function getScheduleEntriesForSection($section_id, $term_name) {
		$result = pg_exec($this->dbConnection, $this->processSQL("SELECT * FROM data.schedule_entry WHERE section_id=$section_id AND term='$term_name';"));
		if($result) {
			return $result;
		} else {
			error_log("FATAL ERROR: getScheduleEntriesForSection($section_id, $term_name) query failed.");
			exit();
		}
	}
	
	public function addScheduleEntry($section_id, $term_name, $sql) {
		$sql = $this->escape($sql);
		
		$result = pg_exec($this->dbConnection, $this->processSQL("INSERT INTO data.schedule_entry (section_id, term, days, start_time, end_time, room, start_date, end_date, hash) SELECT '$section_id', '$term_name', '".$sql['days']."', '".$sql['start_time']."', '".$sql['end_time']."', '".$sql['room']."', '".$sql['start_date']."', '".$sql['end_date']."', '".$sql['hash']."' WHERE NOT EXISTS (SELECT hash FROM data.schedule_entry WHERE section_id = '$section_id' AND term = '$term_name' AND hash = '".$sql['hash']."');"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: addScheduleEntry($section_id, $term_name, ".implode($sql).") query failed.");
			exit();
		}
	}
	
	public function deleteScheduleEntry($section_id, $term_name, $hash) {
		$result = pg_exec($this->dbConnection, $this->processSQL("DELETE FROM data.schedule_entry WHERE section_id=$section_id AND term = '$term_name' AND hash='$hash';"));
		if($result) {
			return true;
		} else {
			error_log("FATAL ERROR: deleteScheduleEntry($section_id, $term_name, $hash) query failed.");
			exit();
		}
	}
	/*
	 * END
	 * SCHEDULE OPERATIONS
	 */
	
	/*
	 * BEGIN
	 * INSTRUCTOR OPERATIONS
	 */
	public function addInstructors($section_id, $term_name, $instructors) {
		$instructors = $this->escape($instructors);
		
		//First add the instructor to the instructor table
		foreach($instructors as $name) {
			if(strlen($name)==0) {
				continue;
			}
			
			//SQL is formatted this way to prevent duplicate key insertion errors (PostgreSQL doesn't have an IGNORE or ON DUPLICATE clause.
			$result = pg_exec($this->dbConnection, $this->processSQL("INSERT INTO data.instructor SELECT '$name' WHERE NOT EXISTS (SELECT name FROM data.instructor WHERE name = '$name');"));
			
			if(!$result) {
				error_log("FATAL ERROR: addInstructors($section_id, $term_name ".implode($instructors).") query failed.");
				exit();
			}
		}
		
		//Clear all previous records in the instructs table for this section; we'll start clean.
		$result = pg_exec($this->dbConnection, $this->processSQL("DELETE FROM data.instructs WHERE section_id=$section_id AND term='$term_name';"));
			
		//Cycle through each instructor and add to instructs table.
		foreach($instructors as $name) {
			$result = pg_exec($this->dbConnection, $this->processSQL("INSERT INTO data.instructs (section_id, term, name) VALUES ($section_id, '$term_name', '$name');"));
		
			if(!$result) {
				error_log("FATAL ERROR: addInstructors($section_id, $term_name, ".implode($instructors).") query failed.");
				exit();
			}
		}
	}
	/*
	 * END
	 * INSTRUCTOR OPERATIONS
	 */
	
	public function __destruct() {
		//Do nothing for now.
    }
}

?>