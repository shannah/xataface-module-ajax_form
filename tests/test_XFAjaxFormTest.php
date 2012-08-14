<?php
/*
 * Xataface Translation Memory Module
 * Copyright (C) 2011  Steve Hannah <steve@weblite.ca>
 * 
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Library General Public
 * License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Library General Public License for more details.
 * 
 * You should have received a copy of the GNU Library General Public
 * License along with this library; if not, write to the
 * Free Software Foundation, Inc., 51 Franklin St, Fifth Floor,
 * Boston, MA  02110-1301, USA.
 *
 */
import('PHPUnit.php');
import(dirname(__FILE__).'/../classes/XFAjaxForm.class.php');
class modules_ajax_form_XFAjaxFormTest extends PHPUnit_TestCase {

	private $mod = null;
	
	function modules_ajax_form_XFAjaxFormTest( $name = 'modules_ajax_form_XFAjaxFormTest'){
		$this->PHPUnit_TestCase($name);
		
	}

	function setUp(){
		$s = DIRECTORY_SEPARATOR;
		$tableDirs = glob(dirname(__FILE__).$s.'tables'.$s.'xf_ajax_form_test_*');
		foreach ($tableDirs as $dir){
			if ( is_dir($dir) ){
				
				df_q("drop table if exists `".basename($dir)."`");
				$createFile = $dir.$s.'create.sql';
				if ( file_exists($createFile) ){
					df_q(trim(file_get_contents($createFile)));
				}
				
				Dataface_Table::setBasePath(basename($dir), dirname(__FILE__));
				
			}
		}
		
		
	}
	

	
	
	
	
	
	function tearDown(){
		
		

	}
	
	
	function testSaveForm(){
	
		$data = array(
			'firstname' => 'Steve',
			'lastname' => 'Hannah',
			'date_created' => '2012-02-13 12:32:13'
		);
		$dataJson = json_encode($data);
		$query =& Dataface_Application::getInstance()->getQuery();
		$query['-table'] = 'xf_ajax_form_test_contacts';

		$_POST = array(
			'--data' => $dataJson,
			'-table' => 'xf_ajax_form_test_contacts'
		);
		
		import('modules/ajax_form/actions/ajax_form_save.php');
		$action = new actions_ajax_form_save;
		ob_start();
		$action->handle(array());
		header('Content-type: text/html; charset="'.Dataface_Application::getInstance()->_conf['oe'].'"');
		
		
		$outputJson = ob_get_contents();
		ob_end_clean();
		$output = json_decode($outputJson);
		
		//print_r($output);
		
		$this->assertEquals(200, $output->code);
		$inserted = df_get_record_by_id($output->recordId);
		$this->assertEquals('Steve', $inserted->val('firstname'));
		$this->assertEquals('Hannah', $inserted->val('lastname'));
		$this->assertEquals('2012-02-13 12:32:13', $inserted->strval('date_created'));
		
		$data = array(
			'lastname' => 'Changed'
		);
		
		$dataJson = json_encode($data);
		$_POST['--data'] = $dataJson;
		$query['--recordId'] = $_POST['--recordId'] = $inserted->getId();
		ob_start();
		$action = new actions_ajax_form_save;
		$action->handle(array());
		header('Content-type: text/html; charset="'.Dataface_Application::getInstance()->_conf['oe'].'"');
		
		$outputJson = ob_get_contents();
		ob_end_clean();
		$output = json_decode($outputJson);
		$this->assertEquals(200, $output->code);
		$this->assertEquals($inserted->getId(), $output->recordId);
		$updated = df_get_record_by_id($output->recordId);
		$this->assertEquals('Steve', $updated->val('firstname'));
		$this->assertEquals('Changed', $updated->val('lastname'));
		$this->assertEquals('2012-02-13 12:32:13', $updated->strval('date_created'));
		
		
		// Now let's try to insert a related record
		
		$data = array(
			'student.cgpa' => '3.5'
		);

		$dataJson = json_encode($data);
		$_POST['--data'] = $dataJson;
		ob_start();
		$action = new actions_ajax_form_save;
		$action->handle(array());
		header('Content-type: text/html; charset="'.Dataface_Application::getInstance()->_conf['oe'].'"');
		
		$outputJson = ob_get_contents();
		ob_end_clean();
		$output = json_decode($outputJson);

		$this->assertEquals(200, $output->code);
		$this->assertEquals($inserted->getId(), $output->recordId);
		$this->assertTrue(is_object($output->relatedIds));
		$this->assertTrue(property_exists($output->relatedIds, 'student'));
		$this->assertTrue(is_array($output->relatedIds->student));
		$studentRecord = df_get_record_by_id($output->relatedIds->student[0]);
		if ( PEAR::isError($studentRecord) ){
			//print_r($output);exit;
			throw new Exception($studentRecord->getMessage());
		}
		$this->assertEquals('Dataface_RelatedRecord', get_class($studentRecord));
		$this->assertEquals('3.5', $studentRecord->strval('cgpa'));
		
		$studentRecords = $inserted->getRelatedRecordObjects('student');
		$this->assertEquals(1, count($studentRecords));
		$this->assertEquals($studentRecord->getId(), $studentRecords[0]->getId());
		
		$relatedRecord = $inserted->getRelatedRecord('student', 0);
		$this->assertEquals($studentRecord->getId(), $relatedRecord->getId());
		
		
		// Now let's try to update the related record
		
		$data = array(
			'student.cgpa' => '3.0'
		);
		$dataJson = json_encode($data);
		$_POST['--data'] = $dataJson;
		ob_start();
		$action = new actions_ajax_form_save;
		$action->handle(array());
		header('Content-type: text/html; charset="'.Dataface_Application::getInstance()->_conf['oe'].'"');
		
		$outputJson = ob_get_contents();
		ob_end_clean();
		$output = json_decode($outputJson);
		$this->assertEquals(200, $output->code);
		$this->assertEquals($inserted->getId(), $output->recordId);
		$this->assertTrue(is_object($output->relatedIds));
		$this->assertTrue(property_exists($output->relatedIds, 'student'));
		$this->assertTrue(is_array($output->relatedIds->student));
		$studentRecord = df_get_record_by_id($output->relatedIds->student[0]);
		$this->assertEquals('Dataface_RelatedRecord', get_class($studentRecord));
		$this->assertEquals('3.0', $studentRecord->strval('cgpa'));
		
		
		// Now let's try updating related data using the related record id.
		
		$data = array(
			'student{'.$studentRecord->getId().'}.cgpa' => '2.0'
		);
		$dataJson = json_encode($data);
		$_POST['--data'] = $dataJson;
		ob_start();
		$action = new actions_ajax_form_save;
		$action->handle(array());
		header('Content-type: text/html; charset="'.Dataface_Application::getInstance()->_conf['oe'].'"');
		
		$outputJson = ob_get_contents();
		ob_end_clean();
		$output = json_decode($outputJson);
		$this->assertEquals(200, $output->code);
		$this->assertEquals($inserted->getId(), $output->recordId);
		$this->assertTrue(is_object($output->relatedIds));
		$this->assertTrue(property_exists($output->relatedIds, 'student'));
		$this->assertTrue(is_array($output->relatedIds->student));
		$studentRecord = df_get_record_by_id($output->relatedIds->student[0]);
		$this->assertEquals('Dataface_RelatedRecord', get_class($studentRecord));
		$this->assertEquals('2.0', $studentRecord->strval('cgpa'));
		
		
		
		
		
		
			
			
		
	}
	
	function untestTranslationMemory(){
	
	
		$html = <<<END
		<form method="post">
			<p>
				First name: <input type="text" name="firstname"/>
			</p>
			<p>
				Last name: <input type="text" name="lastname"/>
				
			</p>
			<p>
				Date created: <input type="text" name="date_created"/>
			</p>
			</div>
			<div class="xf-portal" data-xf-relationship="student">
				<p> CGPA: <input type="text" name="student.cgpa"/></p>
			
			</div>
		</form>
END;
		
		
		$contact = new Dataface_Record('xf_ajax_form_test_contacts', array());
		
		
		$form = new XFAjaxForm($contact, true);
		echo $form->compile($html);
		exit;
	
	}
		


}

// Add this test to the suite of tests to be run by the testrunner
Dataface_ModuleTool::getInstance()->loadModule('modules_testrunner')
		->addTest('modules_ajax_form_XFAjaxFormTest');
