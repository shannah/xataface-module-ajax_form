<?php
class actions_ajax_form_test {
	function handle($params){
	
		$mod = Dataface_ModuleTool::getInstance()->loadModule('modules_ajax_form');
		$mod->registerPaths();
		Dataface_JavascriptTool::getInstance()->import('xataface/modules/ajax_form/test.js');
		
		df_display(array(), 'xataface/modules/ajax_form/test.html');
	}
}