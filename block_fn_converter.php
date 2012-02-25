<?php

/**
 * fn converter block for upgrading 
 * the fnassignment that is custom assignemnt 
 * * */
class block_fn_converter extends block_list {

    function init() {
        $this->title = get_string('titleofblock', 'block_fn_converter');
        $this->version = 2007101509;
    }
    
    function instance_allow_config() {
        return true;
    }
    
   function specialization() {

        // load userdefined title and make sure it's never empty
        if (empty($this->config->title)) {
            $this->title = get_string('blockafnstitle', 'block_fn_converter');
        } else {
            $this->title = $this->config->title;
        }
    }


    function get_content() {
        global $CFG, $COURSE;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        if ($COURSE->id == $this->instance->pageid) {
            $course = $COURSE;
        } else {
            $course = get_record('course', 'id', $this->instance->pageid);
        }

        if (empty($course)) {
            return '';
        }

        require_once($CFG->dirroot . '/course/lib.php');
        $fnassignments = get_all_instances_in_course('fnassignment', $course);

        if (has_capability('moodle/course:update', get_context_instance(CONTEXT_SYSTEM))) {
            if ($fnassignments) {
                $this->content->items[] = '<a href="' . $CFG->pagepath . '?id=' . $this->instance->pageid . '&value=upgradefnassignment">Upgarde FN Assignment</a>';
                $this->content->icons[] = "";
                require_once($CFG->dirroot . '/course/lib.php');
                $updatemod = new stdClass();
                $assignment = new stdClass();
                $submittedass = new stdClass();
                $resource = new stdClass();
                $gradeitem = new stdClass();
                $updategradeentry = new stdClass();
                $count1 = 0;
                $count2 = 0;
                $count3 = 0;
                $count4 = 0;
                $totalfnassignment = count($fnassignments);
                foreach ($fnassignments as $fnassignment) {
//                    if (!$fnassignment->visible) {
//                        continue;
//                    }
                    $fnassignment = get_record('fnassignment', 'id', $fnassignment->id);
                    $fnassignmentid = $fnassignment->id;
                    $rescmoduleid = get_field('modules', 'id', 'name', 'resource');
                    $fnmoduleid = get_field('modules', 'id', 'name', 'fnassignment');
                    $assignmentmoduleid = get_field('modules', 'id', 'name', 'assignment');
                    $action = optional_param('value', '', PARAM_ALPHA);
                    if ($action == 'upgradefnassignment') {
                        /**                         * ******** if the following condition met then convert the current fnassignment in resource mod ************** */
                        if (($fnassignment->var2 == 0) && ($fnassignment->var3 == 0) && ($fnassignment->var4 == 0)) {
                            //ACTION:I make a new entry in mdl_resource table 
                            $resource->course = $COURSE->id;
                            $resource->name = $fnassignment->name;
                           // $resource->type = 'file';
                            // check whether $fnassignment->name contain prefix {{ and suffix }} to differenciate between file /url or edit content text
                            $pattern = "/^{{(.)*}}$/";
                            $subject = $fnassignment->description;
                            $match = preg_match($pattern, $subject);
                            if ($match) {
                                 $resource->type = 'file';
                                 $resource->reference = trim($fnassignment->description, '{,}');
                                 $resource->summary = 'This is link to website';// added on dec 13
                                 $resource->alltext = '';                       // added on dec 13
                            } else {
                                // added on 13 dec 2011 to convert the read only assignment in web page
                                $resource->type = 'html';
                                $resource->reference = "";
                                $resource->summary = addslashes($fnassignment->description);// added on dec 13
                                $resource->alltext = addslashes($fnassignment->description);                      // added on dec 13 
                                // added on 13 dec 2011                                
                            }                            
                            $resource->popup = '';
                            $resource->options = '';
                            $resource->timemodified = $fnassignment->timemodified;
                            $returnid = insert_record('resource', $resource);

                            //ACTION:II update the course_module table
                            $courseid = $COURSE->id;
                            $idfrommoduletable = get_field("course_modules", "id", "course", $courseid, "module", $fnmoduleid, "instance", $fnassignmentid);
                            $updatemod->id = $idfrommoduletable;
                            $updatemod->module = $rescmoduleid;
                            $updatemod->instance = $returnid;
                            $updateid = update_record('course_modules', $updatemod);

                            //ACTION:III update the grade item table
                            $entrymexist = record_exists('grade_items', 'itemmodule', 'fnassignment', 'iteminstance', $fnassignment->id);
                            if ($entrymexist) {
                                $getentry = get_record('grade_items', 'itemmodule', 'fnassignment', 'iteminstance', $fnassignment->id);
                                delete_records('grade_items_history', 'itemmodule', 'fnassignment', 'iteminstance', $fnassignment->id, 'idnumber', $getentry->idnumber);
                                delete_records('grade_items', 'itemmodule', 'fnassignment', 'iteminstance', $fnassignment->id);
                            }
                            $entryexitwithnullitemistance = record_exists('grade_items', 'courseid', $courseid, 'itemname', $fnassignment->name, 'itemmodule', 'fnassignment', 'iteminstance', 'NULL', 'idnumber', '');
                            if ($entryexitwithnullitemistance) {
                                delete_records('grade_items', 'courseid', $courseid, 'itemname', $fnassignment->name, 'itemmodule', 'fnassignment', 'iteminstance', NULL, 'idnumber', '');
                            }
                            $submissionwithoutuseridexist = record_exists('fnassignment_submissions ', 'id', $fnassignment->var5);
                            if ($submissionwithoutuseridexist) {
                                $deletefnsubmissionentry = delete_records('fnassignment_submissions ', 'id', $fnassignment->var5);
                            }
                            //ACTION:IV delete the entry from fnassignment and its submission by user
                            $submissionwithuserid = record_exists('fnassignment_submissions ', 'assignment', $fnassignment->id);
                            if ($submissionwithuserid) {
                                delete_records('fnassignment_submissions ', 'assignment', $fnassignment->id);
                            }
                            $deletefnentry = delete_records('fnassignment', 'id', $fnassignment->id);
                            if ($returnid && $updateid) {
                                $count1++;
                            }
                            rebuild_course_cache($COURSE->id);
                        }/*                         * case one end here */
                        /*                         * *****if the following condition met then convert the current fnassignment in online type of assignment******* */
                        ///case 2-make online assignment and update grade_items and course_module table
                        if ($fnassignment->var5 != Null && empty($fnassignment->var3) && !empty($fnassignment->var2)) {

                            //Action I-create a new entry in assignment table  
                            $assignment->course = $COURSE->id;
                            $assignment->name = $fnassignment->name;
                            $assignment->description = addslashes($fnassignment->description); //$fnassignment->description;
                            $assignment->format = '0';
                            $assignment->assignmenttype = 'online';
                            $assignment->resubmit = $fnassignment->resubmit;
                            $assignment->preventlate = $fnassignment->preventlate;
                            $assignment->emailteachers = $fnassignment->emailteachers;
                            $assignment->var1 = '';
                            $assignment->var2 = '';
                            $assignment->var3 = '';
                            $assignment->var4 = '';
                            $assignment->var5 = '';
                            $assignment->maxbytes = $fnassignment->maxbytes;
                            $assignment->timedue = $fnassignment->timedue;
                            $assignment->timeavailable = $fnassignment->timeavailable;
                            $assignment->grade = $fnassignment->grade;
                            $assignment->timemodified = $fnassignment->timemodified;
                            //print_object($assignment);die();
                            $insertedid = insert_record('assignment', $assignment);

                            //Action II-update the course module table                            
                            $idincmtable = get_field('course_modules', 'id', 'course', $COURSE->id, 'module', $fnmoduleid, 'instance', $fnassignmentid);
                            $updaterecord->id = $idincmtable;
                            $updaterecord->module = $assignmentmoduleid;
                            $updaterecord->instance = $insertedid;
                            $updateid = update_record('course_modules', $updaterecord);
                            if ($insertedid && $updateid) {
                                $count2++;                               
                            }


                            //Action III-get all student and their all submission and restore them
                            $students = get_course_students($COURSE->id, $sort = 'id ASC');
                            if ($students) {
                                foreach ($students as $student) {
                                    $studentid = $student->id;
                                    $recordexists = record_exists('fnassignment_submissions', 'assignment', $fnassignmentid, 'userid', $studentid);
                                    if ($recordexists) {
                                        $studentsubmission = get_record('fnassignment_submissions', 'assignment', $fnassignmentid, 'userid', $studentid);
                                        $submittedass->assignment = $insertedid;
                                        $submittedass->userid = $student->id;
                                        $submittedass->timecreated = $studentsubmission->timecreated;
                                        $submittedass->timemodified = $studentsubmission->timemodified;
                                        $submittedass->numfiles = $studentsubmission->numfiles; //                                     
                                        $data1 = unserialize($studentsubmission->data1);
                                        $submittedtext = $data1->content->text;
                                        $submittedass->data1 = $submittedtext;
                                        $submittedass->data2 = '0'; // 
                                        if ($studentsubmission->timemarked > 0) {

                                            $submittedass->grade = $studentsubmission->grade;
                                        } else {
                                            $submittedass->grade = '-1';
                                        }


                                        $submittedass->submissioncomment = addslashes($studentsubmission->submissioncomment);
                                        $submittedass->format = $studentsubmission->format;
                                        $submittedass->teacher = $studentsubmission->teacher;
                                        $submittedass->timemarked = $studentsubmission->timemarked;
                                        $submittedass->mailed = $studentsubmission->mailed;
                                        $insertinsubmissiontable = insert_record('assignment_submissions', $submittedass);
                                    }
                                    unset($recordexists);
                                }
                            }

                            //Action IV-Update the grade item table
                            $entryexitwithitemistance = record_exists('grade_items', 'itemmodule', 'fnassignment', 'iteminstance', $fnassignment->id);

                            if ($entryexitwithitemistance) {
                                $getgradeentry = get_record('grade_items', 'itemmodule', 'fnassignment', 'iteminstance', $fnassignment->id);
                                $updategradeentry->id = $getgradeentry->id;
                                $updategradeentry->itemmodule = 'assignment';
                                $updategradeentry->iteminstance = $insertedid;
                                update_record('grade_items', $updategradeentry);
                            }

                            if (!$entryexitwithitemistance) {
                                $entryexitwithnullitemistance = record_exists('grade_items', 'itemname', $fnassignment->name, 'itemmodule', 'fnassignment', 'iteminstance', NULL);
                                if ($entryexitwithnullitemistance) {
                                    $getentry = get_record('grade_items', 'itemname', $fnassignment->name, 'itemmodule', 'fnassignment', 'iteminstance', NULL);
                                    $updategradeentry->id = $getentry->id;
                                    $updategradeentry->itemmodule = 'assignment';
                                    $updategradeentry->iteminstance = $insertedid;
                                    update_record('grade_items', $updategradeentry);
                                }
                            }

                            //Action V-Delete the fn assignment and fnassignment submission entries 
                            $submissionwithuserid = record_exists('fnassignment_submissions ', 'assignment', $fnassignment->id);
                            if ($submissionwithuserid) {
                                delete_records('fnassignment_submissions ', 'assignment', $fnassignment->id);
                              
                            }
                            $submissionwithoutuseridexist = record_exists('fnassignment_submissions ', 'timecreated', $fnassignment->timemodified);
                            if ($submissionwithoutuseridexist) {
                                $deletefnsubmissionentry = delete_records('fnassignment_submissions ', 'timecreated', $fnassignment->timemodified);
                               
                            }

                            $deletefnentry = delete_records('fnassignment', 'id', $fnassignment->id);

                            rebuild_course_cache($COURSE->id);
                        }
                        /*                         * case 2 end here */

                        /*                         * *************Case-III make a upload type assignment based on condition ********************** */
                        if ($fnassignment->var5 != Null && !empty($fnassignment->var3) && empty($fnassignment->var2)) {

//Action-I make a new entry in assignment table
                            $assignment->course = $fnassignment->course;
                            $assignment->name = $fnassignment->name;
                            $assignment->description = addslashes($fnassignment->description); //trim($fnassignment->description, '{,}');
                            $assignment->format = $fnassignment->format;
                            $assignment->assignmenttype = 'upload';
                            $assignment->resubmit = $fnassignment->resubmit;
                            $assignment->preventlate = $fnassignment->preventlate;
                            $assignment->emailteachers = $fnassignment->emailteachers;
                            $assignment->var1 = $fnassignment->var3;
                            $assignment->var2 = '';
                            $assignment->var3 = '';
                            $assignment->var4 = '1';
                            $assignment->var5 = '';
                            $assignment->maxbytes = $fnassignment->maxbytes;
                            $assignment->timedue = $fnassignment->timedue;
                            $assignment->timeavailable = $fnassignment->timeavailable;
                            $assignment->grade = $fnassignment->grade;
                            $assignment->timemodified = $fnassignment->timemodified;
                            $insertedid = insert_record('assignment', $assignment);

//Action-II update the course module table               
                            $idincmtable = get_field('course_modules', 'id', 'course', $COURSE->id, 'module', $fnmoduleid, 'instance', $fnassignment->id);
                            $updaterecord->id = $idincmtable;
                            $updaterecord->module = $assignmentmoduleid;
                            $updaterecord->instance = $insertedid;
                            $updatecmtable = update_record('course_modules', $updaterecord);

                            if ($insertedid && $updatecmtable) {
                                $count3++;
                            }

                            //Action III-get all student and their all submission and restore them
                            $students = get_course_students($COURSE->id, $sort = 'id ASC');
                            if ($students) {
                                foreach ($students as $student) {
                                    $studentid = $student->id;
                                    $recordexists = record_exists('fnassignment_submissions', 'assignment', $fnassignmentid, 'userid', $studentid);
                                    if ($recordexists) {
                                        $studentsubmission = get_record('fnassignment_submissions', 'assignment', $fnassignmentid, 'userid', $studentid);
                                        $submittedass->assignment = $insertedid;
                                        $submittedass->userid = $student->id;
                                        $submittedass->timecreated = $studentsubmission->timecreated;
                                        $submittedass->timemodified = $studentsubmission->timemodified;
                                        $submittedass->numfiles = $studentsubmission->numfiles;
                                        $submittedass->data1 = '';
                                        $submittedass->data2 = 'submitted'; // 
                                        if ($studentsubmission->timemarked > 0) {

                                            $submittedass->grade = $studentsubmission->grade;
                                        } else {
                                            $submittedass->grade = '-1';
                                        }

                                        $submittedass->submissioncomment = addslashes($studentsubmission->submissioncomment);
                                        $submittedass->format = $studentsubmission->format;
                                        $submittedass->teacher = $studentsubmission->teacher;
                                        $submittedass->timemarked = $studentsubmission->timemarked;

                                        $submittedass->mailed = $studentsubmission->mailed;
                                        $insertinsubmissiontable = insert_record('assignment_submissions', $submittedass);
                                        $oldarea = $CFG->dataroot . '/' . $fnassignment->course . '/' . $CFG->moddata . '/fnassignment/' . $fnassignment->id . '/' . $student->id;
                                        $newarea = $CFG->dataroot . '/' . $fnassignment->course . '/' . $CFG->moddata . '/assignment/' . $insertedid . '/' . $student->id; //                                 
                                        check_dir_exists($newarea, true, true);
                                        rename($oldarea, $newarea);
                                    }
                                    unset($recordexists);
                                }
                            }
                            //Action IV-Delete the fn assignment and fnassignment submission entries 
                            $submissionwithuserid = record_exists('fnassignment_submissions ', 'assignment', $fnassignment->id);
                            if ($submissionwithuserid) {
                                delete_records('fnassignment_submissions ', 'assignment', $fnassignment->id);
                            }
                            $submissionwithoutuseridexist = record_exists('fnassignment_submissions ', 'timecreated', $fnassignment->timemodified);
                            if ($submissionwithoutuseridexist) {
                                $deletefnsubmissionentry = delete_records('fnassignment_submissions ', 'timecreated', $fnassignment->timemodified);
                            }

                            $deletefnentry = delete_records('fnassignment', 'id', $fnassignment->id);
                            rebuild_course_cache($COURSE->id);
                        }
                        /*                         * case 3 end here* */
                        /*                         * ***CASE:IV make a upload type assignment with note facility************* */
                        if ($fnassignment->var5 != Null && $fnassignment->var3 > 0 && !empty($fnassignment->var2)) {

                            //ACTION:I make a new entry in assignment table
                            $assignment->course = $fnassignment->course;
                            $assignment->name = $fnassignment->name;
                            $assignment->description = addslashes($fnassignment->description); //trim($fnassignment->description, '{,}');
                            $assignment->format = $fnassignment->format;
                            $assignment->assignmenttype = 'upload';
                            $assignment->resubmit = $fnassignment->resubmit;
                            $assignment->preventlate = $fnassignment->preventlate;
                            $assignment->emailteachers = $fnassignment->emailteachers;
                            $assignment->var1 = $fnassignment->var3;
                            $assignment->var2 = '1';
                            $assignment->var3 = '';
                            $assignment->var4 = '1';
                            $assignment->var5 = '';
                            $assignment->maxbytes = $fnassignment->maxbytes;
                            $assignment->timedue = $fnassignment->timedue;
                            $assignment->timeavailable = $fnassignment->timeavailable;
                            $assignment->grade = $fnassignment->grade;
                            $assignment->timemodified = $fnassignment->timemodified;
                            $insertedid = insert_record('assignment', $assignment);

                            //ACTION:II update the course module table                                                    
                            $idincmtable = get_field('course_modules', 'id', 'course', $COURSE->id, 'module', $fnmoduleid, 'instance', $fnassignment->id);
                            $updaterecord->id = $idincmtable;
                            $updaterecord->module = $assignmentmoduleid;
                            $updaterecord->instance = $insertedid;
                            $updatecmtable = update_record('course_modules', $updaterecord);

                            if ($insertedid && $updatecmtable) {
                                $count4++;
                            }
                            //ACTION:III get all student submission and restore them  
                            $students = get_course_students($COURSE->id, $sort = 'id ASC');
                            if ($students) {
                                foreach ($students as $student) {
                                    $studentid = $student->id;
                                    $recordexists = record_exists('fnassignment_submissions', 'assignment', $fnassignmentid, 'userid', $studentid);
                                    if ($recordexists) {
                                        $studentsubmission = get_record('fnassignment_submissions', 'assignment', $fnassignmentid, 'userid', $studentid);
                                        $submittedass->assignment = $insertedid;
                                        $submittedass->userid = $student->id;
                                        $submittedass->timecreated = $studentsubmission->timecreated;
                                        $submittedass->timemodified = $studentsubmission->timemodified;
                                        $submittedass->numfiles = $studentsubmission->numfiles;
                                        $data1 = unserialize($studentsubmission->data1);
                                        $submittedtext = $data1->content->text;
                                        $submittedass->data1 = $submittedtext;
                                        $submittedass->data2 = 'submitted';
                                        if ($studentsubmission->timemarked > 0) {

                                            $submittedass->grade = $studentsubmission->grade;
                                        } else {
                                            $submittedass->grade = '-1';
                                        }

                                        $submittedass->submissioncomment = addslashes($studentsubmission->submissioncomment);
                                        $submittedass->format = $studentsubmission->format;
                                        $submittedass->teacher = $studentsubmission->teacher;
                                        $submittedass->timemarked = $studentsubmission->timemarked;

                                        $submittedass->mailed = $studentsubmission->mailed;
                                        $insertinsubmissiontable = insert_record('assignment_submissions', $submittedass);
                                        $oldarea = $CFG->dataroot . '/' . $fnassignment->course . '/' . $CFG->moddata . '/fnassignment/' . $fnassignment->id . '/' . $student->id;
                                        $newarea = $CFG->dataroot . '/' . $fnassignment->course . '/' . $CFG->moddata . '/assignment/' . $insertedid . '/' . $student->id; //                                 
                                        check_dir_exists($newarea, true, true);
                                        rename($oldarea, $newarea);
                                    }
                                }
                            }
                            //ACTION:IV delete entry from fnassignment and fnassignment_submission table 
                            $submissionwithuserid = record_exists('fnassignment_submissions ', 'assignment', $fnassignment->id);
                            if ($submissionwithuserid) {
                                $a = delete_records('fnassignment_submissions ', 'assignment', $fnassignment->id);
//                               
                            }
                            $submissionwithoutuseridexist = record_exists('fnassignment_submissions ', 'timecreated', $fnassignment->timemodified);
                            if ($submissionwithoutuseridexist) {
                                $deletefnsubmissionentry = delete_records('fnassignment_submissions ', 'timecreated', $fnassignment->timemodified);
//                               
                            }

                            $deletefnentry = delete_records('fnassignment', 'id', $fnassignment->id);

                            rebuild_course_cache($COURSE->id);
                        }

                        /*                         * case 4 ends here* */
                    }
                }
                if ($count1) {
                    notify("$count1 FN Assignments  converted into resource");
                }
                if ($count2) {
                    notify("$count2 FN Assignments  converted into online assignments");
                }
                if ($count3) {
                    notify("$count3 FN Assignments  converted into Advanced assignments");
                }
                if ($count4) {
                    notify("$count4 FN Assignments  converted into Advanced assignments with notes facility");
                }
                if ($count1 || $count2 || $count3 || $count4) {
                    $totalconverted = $count1 + $count2 + $count3 + $count4;
                    $totalnotconverted = $totalfnassignment - $totalconverted;
                    if ($totalnotconverted > 0) {
                        notify("$totalnotconverted FN Assignments  failed in conversion");
                    }
                }
                if ($count1 || $count2 || $count3 || $count4) {
                    redirect($CFG->wwwroot . '/course/view.php?id=' . $COURSE->id);
                }
            } else {
                $this->content->items[] = "No FN Assignments in this course";
                $this->content->icons[] = "";
            }
        }
        return $this->content;
    }

    function applicable_formats() {
        return array('course' => true);
    }

}

?>
