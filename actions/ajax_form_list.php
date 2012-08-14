<?php
require_once 'modules/ajax_form/classes/XFAjaxList.class.php';
class actions_ajax_form_list {
	function handle($params){
	
	
		$app = Dataface_Application::getInstance();
		$query = $app->getQuery();
		$jt = Dataface_JavascriptTool::getInstance();
		$mod = Dataface_ModuleTool::getInstance()->loadModule('modules_ajax_form');
		$mod->registerPaths();
		
		$list = new XFAjaxList();
		
		if ( @$query['-relationship'] and @$query['--recordId'] ){
			$record = df_get_record_by_id($query['--recordId']);
			if ( !$record ) throw new Exception("Record ".$query['--recordId']." could not be found.");
			
			$relationshipName = $query['-relationship'];
			if ( !$record->table()->hasRelationship($relationshipName) ){
				throw new Exception("Relationship $relationshipName does not exist.");
			}
			
			$start = $query['-skip'];
			$limit = $query['-limit'];
			
			$keys = array_keys($query);
			$keys = preg_grep('/^'.preg_quote($relationshipName).'\./', $keys);
			
			$q = array();
			foreach ($keys as $k){
				list($r,$f) = explode('.', $k);
				$q[$f] = $query[$k];
			}
			
			$list->initRelated($record, $relationshipName, $q, $start, $limit);
			
		} else {
			$list->init($query, $query['-skip'], $query['-limit']);
		}
		if ( @$query['--single-row'] ){
			$list->setSingleId($query['--single-row']);
		}
		
		
		$template = null;
		if ( @$query['--templateHtml'] ){
			$template = $query['--templateHtml'];
		} else if ( @$query['--templateId'] ){
			$template = $this->getTemplateById($query['--templateId']);
		}
		
		
		
		$out = $list->compile($template);
		$jt->import('xataface/modules/ajax_form/ajax_form_list.js');
		
		
		
		header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
		
		if ( @$query['-full'] ){
			$context['listContent'] = $out;
			df_display($context, 'xataface/modules/ajax_form/ajax_form_list.html');
		} else {
		
			echo $out;
			if ( $jt->getScripts() ){
				echo $jt->getHtml();
			} 
		}
		
		
	}
}