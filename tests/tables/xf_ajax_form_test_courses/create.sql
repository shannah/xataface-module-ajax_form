create table xf_ajax_form_test_courses (
	course_id int(11) not null auto_increment primary key,
	department_id int(11),
	course_number varchar(10),
	course_description text
)