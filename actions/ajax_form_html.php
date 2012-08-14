<?php
import('modules/ajax_form/classes/XFAjaxForm.class.php');
class actions_ajax_form_html {

	function handle($params){
	
		$app = Dataface_Application::getInstance();
		$query = $app->getQuery();
		$mod = Dataface_ModuleTool::getInstance()->loadModule('modules_ajax_form');
		
		$record = null;
		$template = null;
		$fields = null;
		
		$table = Dataface_Table::loadTable($query['-table']);
		
		if ( @$query['--recordId'] ){
			$record = df_get_record_by_id($query['--recordId']);
			if ( !$record ){
				throw new Exception("Record could not be found.");
			}
			$table = $record->table();
		}
		
		
		$new = true;
		if ( $record ){
			$new = false;
		} else {
			$record = new Dataface_Record($table->tablename, array());
		}
		
		$form = new XFAjaxForm($record, $new);
		$template = null;
		if ( @$query['--template'] ){
			$template = $query['--template'];
		}
		
		
		header('Content-type: text/html; charset="'.Dataface_Application::getInstance()->_conf['oe'].'"');
		
		$jt = Dataface_JavascriptTool::getInstance();
		$mod->registerPaths();
		$jt->import('xataface/modules/ajax_form/ajax_form_html.js');
		ob_start();
		echo $form->compile($template);
		if ( !@$query['-full'] and $jt->getScripts() ){
			echo $jt->getHtml();
		} else if ( @$query['-full'] ){
			$formContent = ob_get_contents();
			ob_end_clean();
			ob_start();
			df_display(array('formContent'=>$formContent), 'xataface/modules/ajax_form/ajax_form_html.html');
		}
		ob_end_flush();
		
		
	
	}
}