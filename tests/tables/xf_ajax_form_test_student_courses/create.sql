CREATE TABLE `xf_ajax_form_test_student_courses` (
  `course_offering_id` int(10) unsigned NOT NULL,
  `student_contact_id` int(10) unsigned NOT NULL,
  `grade` decimal(3,2) DEFAULT NULL,
  PRIMARY KEY (`course_offering_id`,`student_contact_id`)
 )