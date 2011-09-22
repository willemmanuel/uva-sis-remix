<?php

include_once ('ResponseBuilder.php');
	
class AuthenticatedDbAdapter
{
	private $dbConnection;
	
	public function __construct()
	{
		$this->dbConnection = pg_connect('host=localhost port=5432 dbname=thesisremix user=auth_user password=$BUILD$redacted$BUILD$');
		
		if(!$this->dbConnection) {
			error_log('FATAL ERROR: Unable to connect to database as auth_user.');
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
		}
	}
	
	public function updateUser($computing_id) {
		$result = pg_exec($this->dbConnection, ("UPDATE users.history SET sessions=sessions+1, latest_visit = 'now()' WHERE computing_id = '$computing_id';"));
		
		if($result!=false)
			if(pg_affected_rows($result) === 0)
				$result = pg_exec($this->dbConnection, ("INSERT INTO users.history (computing_id) VALUES ('$computing_id');"));

		if($result==false) {
			error_log("FATAL ERROR: Unable to update user's record in users table.");
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
		}
	}
	
	public function addSession($access_key, $computing_id, $cookie_jar)
	{
		$result = pg_exec($this->dbConnection, ("DELETE FROM users.sessions WHERE computing_id='$computing_id'; INSERT INTO users.sessions (access_key, cookie_jar, computing_id) VALUES ('$access_key', '$cookie_jar', '$computing_id');"));
	
		if($result!=false)
			return true;
		else {
			error_log("FATAL ERROR: Unable to add user's authenticated session to database.");
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
		}
	}
	
	public function deleteSession($access_key)
	{
		$result = pg_exec($this->dbConnection, ("DELETE FROM users.sessions WHERE access_key='$access_key';"));
	
		if($result!=false)
			return true;
		else {
			error_log("FATAL ERROR: Unable to delete user's authenticated session from database.");
		}
	}
	
	public function getDisplayableContributors() {
		$records = pg_exec($this->dbConnection, ("SELECT *, CASE WHEN ts > now() - interval '10 seconds' THEN 'online' ELSE CASE WHEN id = '8c511e28492ed414b1272fea40ce912274aef9d7' THEN 'online' ELSE 'offline' END END AS online_status FROM tunnels WHERE status='activated' AND char_length(display_name) > 0 AND display_online = TRUE ORDER BY http_counter DESC;"));
		
		if($records==false) {
			error_log("FATAL ERROR: Unable to access contributors data.");
			return false;
		} else {
			if(pg_num_rows($records)>0) {
				return $records;
			} else {
				return false;
			}
		}
	}
	
	public function getUserSession($access_key) {
		$records = pg_exec($this->dbConnection, ("SELECT * FROM users.sessions WHERE access_key='$access_key';"));
		
		if($records==false) {
			error_log("FATAL ERROR: Unable to access the given user's session.");
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
			return false;
		} else {
			if(pg_num_rows($records)==1) {
				return pg_fetch_assoc($records);
			} else {
				return false;
			}
		}
	}
	
	public function updateAccessVars($access_key, $ICSID, $ICStateNum)
	{
		$records = pg_exec($this->dbConnection, "UPDATE users.sessions SET \"ICSID\"='$ICSID', \"ICStateNum\"='$ICStateNum' WHERE access_key='$access_key';");
		
		if($records==false) {
			error_log("FATAL ERROR: Failed to update ICSID and/or ICStateNum. Check PostgreSQL log.");
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
		}
	}
	
	public function addEnrollRequest($class_nbr, $term)
	{
		$result = pg_exec($this->dbConnection, ('INSERT INTO enroll_requests (class_nbr, term, computing_id) VALUES (\''.$class_nbr.'\', \''.$term.'\', (SELECT computing_id FROM users.sessions WHERE session_id = \'' . $_COOKIE['session_id'] . '\' AND "PS_TOKEN" = \'' . str_replace(" ", "+", $_COOKIE['PS_TOKEN']) . '\' LIMIT 1));'));
		
		if($result==false)
			error_log("Failed to add enrollment request. Check PostgreSQL log.");
	}
	
	public function deleteEnrollRequest($class_nbr, $term)
	{
		$result = pg_exec($this->dbConnection, ('DELETE FROM enroll_requests WHERE class_nbr = \''.$class_nbr.'\' AND term = \''.$term.'\' AND computing_id = (SELECT computing_id FROM users.sessions WHERE session_id = \'' . $_COOKIE['session_id'] . '\' AND "PS_TOKEN" = \'' . str_replace(" ", "+", $_COOKIE['PS_TOKEN']) . '\' LIMIT 1);'));
		
		if($result==false)
			error_log("Failed to delete enrollment request. Check PostgreSQL log.");
	}
	
	public function getEnrollRequests($term)
	{
		$result= pg_exec($this->dbConnection, ('SELECT class_nbr FROM enroll_requests WHERE term = \''.$term.'\' AND computing_id = (SELECT computing_id FROM users.sessions WHERE session_id = \'' . $_COOKIE['session_id'] . '\' AND "PS_TOKEN" = \'' . str_replace(" ", "+", $_COOKIE['PS_TOKEN']) . '\' LIMIT 1);'));
		
		if($result!=false)
			return $result;
		else {
			error_log("Unable to retrieve enrollment requests. Check PostgreSQL log.");
			return false;
		}
	}
	
	public function getAvailableTerms()
	{
		$dbConnection = pg_connect('host=localhost port=5432 dbname=uva_sis_data user=auth_user password=$BUILD$redacted$BUILD$');
		$result = pg_exec($dbConnection, "SELECT sis_id, name FROM data.term ORDER BY sis_id DESC;");
		
		if($result!=false)
			return $result;
		else {
			error_log("Unable to retrieve available terms from database.");
			return false;
		}
	}
	
	public function getClassesForTerm($computing_id, $term_name)
	{
		return pg_exec($this->dbConnection, "SELECT json, sha256 FROM users.classes a LEFT JOIN data.term b ON a.term = b.name WHERE computing_id = '$computing_id' AND a.term = '$term_name';");
	}
	
	public function getExamScheduleForTerm($computing_id, $term_name)
	{
		return pg_exec($this->dbConnection, "SELECT json, sha256 FROM users.exams a LEFT JOIN data.term b ON a.term = b.name WHERE computing_id = '$computing_id' AND a.term = '$term_name';");
	}
	
	public function getSisIdForTermName($term_name)
	{
		$dbConnection = pg_connect('host=localhost port=5432 dbname=uva_sis_data user=auth_user password=$BUILD$redacted$BUILD$');
		return pg_exec($dbConnection, "SELECT sis_id FROM data.term WHERE name = '$term_name';");
	}
	
	public function hasUserInitializedScheduleForTerm($computing_id, $term_name) {
		$result = pg_exec($this->dbConnection, "SELECT * FROM users.init_history WHERE computing_id = '$computing_id' AND term = '$term_name';");
		
		if($result!=false) {
			if(pg_num_rows($result) === 0)
				return false;
			return true;
		} else {
			error_log("Unable to determine term init history for user.");
			exit();
		}
	}
	
	public function setUserClassesForTerm($term_name, &$cls_array) {
		$access_vars = $this->getUserSession($_COOKIE['access_key']);
		if($access_vars === false) {
			error_log('Unable to get the computing id for the provided session ID.');
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
		}
		
		$computing_id = $access_vars['computing_id'];
		
		for($i=0; $i<count($cls_array); $i++) {
			$cls_array[$i]['json'] = json_encode($cls_array[$i]);
			$cls_array[$i]['sha'] = hash('sha256', $cls_array[$i]['json']);
		}
		
		$last_rcvd_classes = array();
		$result = $this->getClassesForTerm($computing_id, $term_name);
		while($lr_class = pg_fetch_assoc($result)) {
			array_push($last_rcvd_classes, $lr_class);
		}
		
		/*
		 * Tell the client which classes to keep and add;
		 * it will delete all others held from the previous update.
		 */
		for($i=0; $i<count($cls_array); $i++) {
			$match = false;
			foreach($last_rcvd_classes as $lr_cls) {
				if($cls_array[$i]['sha'] == $lr_cls['sha256']) {
					$match = true;
					$cls_array[$i]['action'] = 'no_change';
				}
			}
			if(!$match) {
				$cls_array[$i]['action'] = 'incorporate';
			}
		}
		
		pg_exec($this->dbConnection, "DELETE FROM users.classes WHERE computing_id = '$computing_id' AND term = '$term_name';");
		for($i=0; $i<count($cls_array); $i++) {
			$result = pg_exec($this->dbConnection, "INSERT INTO users.classes (computing_id, term, json, sha256) VALUES ('$computing_id', '$term_name', '".pg_escape_string($cls_array[$i]['json'])."', '".$cls_array[$i]['sha']."');");
			if($result === false) {
				error_log('Unable to insert the JSON representation of a given class into the database.');
				$builder = new ResponseBuilder('error');
				$builder->echoResponse();
			}
		}
		
		//Make a note that the user's schedule for this term was initialized.
		$result = pg_exec($this->dbConnection, "DELETE FROM users.init_history WHERE computing_id = '$computing_id' AND term = '$term_name'; INSERT INTO user_init_history (computing_id, term) VALUES ('$computing_id', '$term_name');");
		if($result===false) {
			error_log("Unable to modify init history for user.");
			exit();
		}
	}
	
	public function setUserExamsForTerm($term_name, &$exam_array) {
		$access_vars = $this->getUserSession($_COOKIE['access_key']);	
		if($access_vars === false) {
			error_log('Unable to get the computing id for the provided session ID.');
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
		}
		
		$computing_id = $access_vars['computing_id'];
		
		$json = json_encode($exam_array[0]);
		$sha256 = hash('sha256', $json);
		
		$result = pg_fetch_assoc($this->getExamScheduleForTerm($computing_id, $term_name));
		if($result['sha256']==$sha256) {
			$exam_array['action'] = 'no_change';
		} else {
			$exam_array['action'] = 'incorporate';
			pg_exec($this->dbConnection, "DELETE FROM users.exams WHERE computing_id = '$computing_id' AND term = '$term_name';");
			$result = pg_exec($this->dbConnection, "INSERT INTO users.exams (computing_id, term, json, sha256) VALUES ('$computing_id', '$term_name', '".pg_escape_string($json)."', '".$sha256."');");
			if($result === false) {
				error_log('Unable to insert the JSON representation of an exam schedule into the database.');
				$builder = new ResponseBuilder('error');
				$builder->echoResponse();
			}
		}
	}
    
    /*
     * BEGIN
     * Functions specific to the TSR query parser.
     */
	public function remove_all_hits($query_parts, $hits) {
		//Remove names from the master query term list
		$new_query_parts = array();
		foreach($query_parts as $part) {
			if(!in_array($part, $hits)) {
				array_push($new_query_parts, $part);
			}
		}
		return $new_query_parts;
	}
	
	public function run_query_tests($query_parts, &$sql_clauses) {
		$key = 0;
		$term = $query_parts[0];
		
		/*
		 * INSTRUCTOR TEST
		 */
		$records = pg_exec($this->dbConnection, "SELECT id FROM schedule_entries WHERE lower(instructor) LIKE '%|$term|%';");
		if($records!=false && pg_num_rows($records) != 0) {
			$hits = array();
			array_push($hits, $term);	//Load the first matching term as a name.
			
			for($i = $key+1; $i < count($query_parts); $i++) {
				$sql_form = '%|';
				foreach($hits as $name) {
					$sql_form.=$name.'|';
				}
				$sql_form .= $query_parts[$i] . '|%';
				
				$records = pg_exec($this->dbConnection, "SELECT id FROM schedule_entries WHERE lower(instructor) LIKE '$sql_form';");
				
				if($records!=false && pg_num_rows($records) != 0) {
					array_push($hits, $query_parts[$i]);
				} else {
					break;
				}
			}
			
			$sql_form = '%|';
			foreach($hits as $name) {
				$sql_form.=$name.'|';
			}
			$sql_form .= '%';
			array_push($sql_clauses['instructor'], $sql_form);
			
			return $this->remove_all_hits($query_parts, $hits);
		}
		
		/*
		 * DEPT/COURSE NUM TEST
		 */
		$records = pg_exec($this->dbConnection, "SELECT id FROM depts WHERE acronym='".strtoupper($term)."';");
		if($records!=false && pg_num_rows($records) != 0) {
			$hits = array();
			array_push($hits, $term);
			if($key+1 < count($query_parts) && is_numeric($query_parts[$key+1])) {
				$records = pg_exec($this->dbConnection, "SELECT b.id FROM depts a LEFT JOIN courses b ON a.id = b.dept_id_fk WHERE b.course_num = '".$query_parts[$key+1]."';");
				if($records!=false && pg_num_rows($records) != 0) {
					array_push($sql_clauses['dept_id'], array('dept'=>$term, 'id'=>$query_parts[$key+1]));
					array_push($hits, $query_parts[$key+1]);
				} else {
					array_push($sql_clauses['dept_id'], array('dept'=>$term));
				}
			} else {
				array_push($sql_clauses['dept_id'], array('dept'=>$term));
			}
			return $this->remove_all_hits($query_parts, $hits);
		}
		
		/*
		 * COURSE NUM (NO DEPT) TEST
		 */
		if(is_numeric($term) && strlen($term)==4) {
			$records = pg_exec($this->dbConnection, "SELECT id FROM courses WHERE course_num=$term;");
			if($records!=false && pg_num_rows($records) != 0) {
				$hits = array();
				array_push($hits, $term);
				array_push($sql_clauses['dept_id'], array('id'=>$term));
				return $this->remove_all_hits($query_parts, $hits);
			}
		}
		
		/*
		 * 5-DIGIT SIS ID TEST
		 */
		if(is_numeric($term)) {
			$records = pg_exec($this->dbConnection, "SELECT id FROM sections WHERE class_nbr='".$term."';");
			if($records!=false && pg_num_rows($records) != 0) {
				$hits = array();
				array_push($hits, $term);
				array_push($sql_clauses['sis_id'], $term);
				return $this->remove_all_hits($query_parts, $hits);
			}
		}
		
		/*
		 * SECTION STATUS TEST
		 */
		$records = pg_exec($this->dbConnection, "SELECT id FROM sections WHERE lower(status)='".$term."';");
		if($records!=false && pg_num_rows($records) != 0) {
			$hits = array();
			array_push($hits, $term);
			array_push($sql_clauses['status'], $term);
			return $this->remove_all_hits($query_parts, $hits);
		}
		
		/*
		 * SECTION COMPONENT TEST
		 */
		$records = pg_exec($this->dbConnection, "SELECT id FROM sections WHERE lower(component)='".$term."';");
		if($records!=false && pg_num_rows($records) != 0) {
			$hits = array();
			array_push($hits, $term);
			array_push($sql_clauses['component'], $term);
			return $this->remove_all_hits($query_parts, $hits);
		}
		
		/*
		 * DEFAULT TEST
		 * TODO: Implement. This will operate on the course description field eventually.
		 */
		$hits = array();
		array_push($hits, $term);
		array_push($sql_clauses['desc'], $term);
		return $this->remove_all_hits($query_parts, $hits);
	}
	
	public function parse_query($query, $sis_term_id) {
		$query_parts = preg_split('/\s+/', $query);
		foreach($query_parts as $key=>$value) {
			$query_parts[$key] = pg_escape_string(strip_tags(strtolower($value)));
		}
		$sql_clauses = array('instructor'=>array(),
							 'dept_id'=>array(),
			  				 'sis_id'=>array(),
							 'status'=>array(),
							 'component'=>array(),
							 'desc'=>array());

		for($i=0; $i<15 && count($query_parts) > 0; $i++) {
			$query_parts = $this->run_query_tests($query_parts, $sql_clauses);
		}
		
		/*
		 * Prepare the SQL statement.
		 */
		$records = pg_exec($this->dbConnection, "SELECT id FROM term WHERE sis_id=$sis_term_id;");
		if($records==false || pg_num_rows($records) == 0) {
			error_log("ERROR: Unable to find match for the given SIS term ID.");
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
		}
		$record = pg_fetch_array($records);
		$term_id = $record['id'];
		
		$sql = "SELECT * FROM
				(SELECT d.id AS section_id, c.*, d.class_nbr, d.component, d.section_nbr, d.status, d.credits, d.inst_mode, d.loc, d.campus, d.capacity, d.enrolled, d.available, d.waitlist_capacity, d.waitlist_total FROM 
				(SELECT a.id AS course_id, b.acronym, b.full_name, a.course_num, a.course_name, a.grading_basis, a.requirement, a.description FROM courses a
				LEFT JOIN depts b
				ON a.dept_id_fk = b.id";
		
		if(count($sql_clauses['dept_id']) > 0) {
			$sql.=" WHERE ";
			foreach($sql_clauses['dept_id'] as $dept_id) {
				if(isset($dept_id['dept']) && isset($dept_id['id'])) {
					$sql.="(lower(b.acronym)='".$dept_id['dept']."' AND a.course_num = ".$dept_id['id'].") OR ";
				} else if(isset($dept_id['dept'])) {
					$sql.="(lower(b.acronym)='".$dept_id['dept']."') OR ";
				} else {
					$sql.="(a.course_num = ".$dept_id['id'].") OR ";
				}
			}
			$sql = substr($sql, 0, strlen($sql)-4);
		}
		
		$sql.=") c LEFT JOIN sections d ON c.course_id = d.course_id_fk WHERE term_id_fk = $term_id";
		
		if(count($sql_clauses['sis_id']) > 0) {
			$sql.=" AND (";
			foreach($sql_clauses['sis_id'] as $sis_id) {
				$sql.="class_nbr=$sis_id OR ";
			}
			$sql = substr($sql, 0, strlen($sql)-4).')';
		}
		
		if(count($sql_clauses['status']) > 0) {
			$sql.=" AND (";
			foreach($sql_clauses['status'] as $status) {
				$sql.="lower(status)='$status' OR ";
			}
			$sql = substr($sql, 0, strlen($sql)-4).')';
		}
		
		if(count($sql_clauses['component']) > 0) {
			$sql.=" AND (";
			foreach($sql_clauses['component'] as $component) {
				$sql.="lower(component)='$component' OR ";
			}
			$sql = substr($sql, 0, strlen($sql)-4).')';
		}
		
		$sql.=") e LEFT JOIN schedule_entries f ON f.section_id_fk = e.section_id";
		
		if(count($sql_clauses['instructor']) > 0) {
			$sql.=" WHERE ";
			foreach($sql_clauses['instructor'] as $instructor) {
				$sql.="lower(instructor) LIKE '$instructor' OR ";
			}
			$sql = substr($sql, 0, strlen($sql)-4).'ORDER BY course_num ASC;';
		}
		
		$records = pg_exec($this->dbConnection, $sql);
		if($records==false) {
			error_log("ERROR: Unable to execute the generated SQL search query.");
			$builder = new ResponseBuilder('error');
			$builder->echoResponse();
			exit();
		}
		
		return $records;
	}
	/*
	 * END
	 * TSR query parser functions.
	 */
	
	/*
	 * BEGIN
	 * Main page statistics functions.
	 */
	public function getUniqueLogins() {
		$result = pg_exec($this->dbConnection, "SELECT COUNT(*) AS uniques FROM users.history;");
		
		if($result==false) {
			error_log("NOTICE: Unable to get unique logins.");
			return false;
		} else {
			$records = pg_fetch_array($result);
			return $records['uniques'];
		}
	}
	
	public function getTotalLogins() {
		$result = pg_exec($this->dbConnection, "SELECT SUM(sessions) AS total_logins FROM users.history;");
		
		if($result==false) {
			error_log("NOTICE: Unable to get total logins.");
			return false;
		} else {
			$records = pg_fetch_array($result);
			return $records['total_logins'];
		}
	}
	
	public function getTotalClasses() {
		$result = pg_exec($this->dbConnection, "SELECT COUNT(*) AS total_classes FROM users.classes;");
		
		if($result==false) {
			error_log("NOTICE: Unable to get total classes.");
			return false;
		} else {
			$records = pg_fetch_array($result);
			return $records['total_classes'];
		}
	}
	/*
	 * END
	 * Main page statistics functions.
	 */
	
	public function __destruct() {}
}

?>