<?php
class actions_ajax_form_new_related_record {

	function handle($params){
	
	
		$app = Dataface_Application::getInstance();
		$query = $app->getQuery();
		$mod = Dataface_ModuleTool::getInstance()->loadModule('modules_ajax_form');
		$jt = Dataface_JavascriptTool::getInstance();
		$mod->registerPaths();
		
		if ( !@$query['-relationship'] ) throw new Exception("No relationship specified");
		$relationshipName = $query['-relationship'];
		
		if ( !@$query['--recordId'] ) throw new Exception("No record ID specified");
		$recordId = $query['--recordId'];
		
		$record = df_get_record_by_id($recordId);
		if ( !$record ) throw new Exception("Record could not be found: ".$recordId);
		import('modules/ajax_form/classes/XFAjaxForm.class.php');
		$form = new XFAjaxForm($record, false);
		$template = $form->generateNewRelatedRecordFormTemplate($relationshipName);

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