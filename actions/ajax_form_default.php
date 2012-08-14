<?php
class actions_ajax_form_default {

	function handle($params){
	
		$app = Dataface_Application::getInstance();
		$query = $app->getQuery();
		$mod = Dataface_ModuleTool::getInstance()->loadModule('modules_ajax_form');
		$mod->registerPaths();
		
		$jt = Dataface_JavascriptTool::getInstance();
		$jt->import('xataface/modules/ajax_form/ajax_form_default.js');
		
		$context = array();
		$context['tablename'] = $query['-table'];
		
		df_display(array(), 'xataface/modules/ajax_form/ajax_form_default.html');
		
	}
}