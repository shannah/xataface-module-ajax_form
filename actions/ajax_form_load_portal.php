<?php
/**
 * @brief This action loads a portal as it is requested via the Javascript API.
 * A portal can work differently in different contexts.  This action takes two 
 * parameter:  --recordId and --name.
 *
 * --recordId Is the ID of a particular record.  The interpretation of the --name
 * parameter depends on whether the record is a Dataface_Record or a Dataface_RelatedRecord.
 *
 * If the record is a Dataface_Record (i.e. a main record), then this will return
 * the appropriate subview for the record's relationship named '--name'.  If it is 
 * a related record, however, it will return form for the particular destination table
 * identified by "Name".  
 *
 * This allows forms to be expanded potentially infinitely.  
 *
 * E.g.
 * 
 * Record 1
 *		- Relationship A
 *			- Record 2 (table b)
 *			- Record 1 (table c)
 *				- Edit Form
 *					- Relationship A
 */
class actions_ajax_form_load_portal {

	function handle($params){
		


		$app = Dataface_Application::getInstance();
		$query = $app->getQuery();
		$jt = Dataface_JavascriptTool::getInstance();
		$mod = Dataface_ModuleTool::getInstance()->loadModule('modules_ajax_form');
		$mod->registerPaths();
		
		if ( !@$query['--recordId'] ){
			throw new Exception("No record id provided.");
		}
		
		$portalName = null;
		
		if ( @$query['--name'] and !preg_match('/^[a-zA-Z0-9_]*$/', $query['--name']) ){
			throw new Exception("Invalid portal name");
		}
		
		$portalName = @$query['--name'];
		
		
		//$portalRecord = null;
		//if ( @$query['--portalRecordId'] ){
		//	$portalRecord = df_get_record_by_id($query['--portalRecordId']);
		//}
		
		$record = df_get_record_by_id($query['--recordId']);
		if ( PEAR::isError($record) ){
			throw new Exception($record->getMessage(), $record->getCode());
		}
		
		if ( !$record ){
			throw new Exception("Record could not be found.");
		}
		
		if ( !$record->checkPermission('view') ){
			header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
			echo '<div class="xf-no-access" data-xf-no-access="1">You don\'t have permission to view this record.</div>';
			return;
		}
		
		if ( is_a($record, 'Dataface_Record') ){
		
			// If the record is a Dataface_Record then we treat the 
			// --name parameter as the name of a relationship.
			if ( $portalName ){
				$relationship = $record->table()->getRelationship($portalName);
				
				if ( $relationship->getMaxCardinality() == 1 ){
					// We have a max cardinality of 1... just return the ajax form.
					$relatedRecord = $record->getRelatedRecord($portalName);
					
					
					$new = false;
					import('modules/ajax_form/classes/XFAJaxForm.class.php');
					if ( !$relatedRecord ){
						// The record hasn't been created yet.  We will
						// produce a new related record form
						$relatedRecord = new Dataface_RelatedRecord($record, $portalName, array());
						$new = true;
						$jt->import('xataface/modules/ajax_form/ajax_form_html.js');
						$form = new XFAjaxForm($record, false, $relatedRecord);
						header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
						$template = $form->generateNewRelatedRecordFormTemplate($portalName);
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
					} else {
						$app->addRecordContext($relatedRecord);
						// We need ot produce a 
						$unconstrainedTables = $relatedRecord->getUnconstrainedTables();
						
						$destRecords = array();
						foreach ($unconstrainedTables as $t){
							$destRecords[] = $relatedRecord->toRecord($t);
						}
						
						if ( count($destRecords) == 1 ){
							$jt->import('xataface/modules/ajax_form/ajax_form_html.js');
							import('modules/ajax_form/classes/XFAjaxForm.class.php');
							$form = new XFAjaxForm($destRecords[0], false, $relatedRecord);
							header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
							ob_start();
							echo $form->compile();
							if ( !@$query['-full'] and $jt->getScripts() ){
								echo $jt->getHtml();
							} else if ( @$query['-full'] ){
								$formContent = ob_get_contents();
								ob_end_clean();
								ob_start();
								df_display(array('formContent'=>$formContent), 'xataface/modules/ajax_form/ajax_form_html.html');
							}
							ob_end_flush();
							
						
						} else {
							
							header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
							
							echo $this->getRelatedTablesPortal($destRecords, $relatedRecord);
						}
					
					}
					
				} else {
					import('modules/ajax_form/classes/XFAjaxList.class.php');
					import('modules/ajax_form/classes/XFAjaxForm.class.php');
					$form = new XFAjaxForm(
						$record,
						true
					);
					
					$list = new XFAjaxList();
					$list->initRelated($record, $portalName);
					header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
					
					echo $list->compile();
				}
			} else {
			
				// If the record is a dataface record, then we just create
				// an AjaxForm
				import('modules/ajax_form/classes/XFAjaxForm.class.php');
				$form = new XFAjaxForm($record, false);
				header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
				
				echo $form->compile();
			}
		} else if ( is_a($record, 'Dataface_RelatedRecord') ){
			if ( $portalName ){
				// We have a max cardinality of 1... just return the ajax form.
				$destRecord = $record->toRecord($portalName);
				
				import('modules/ajax_form/classes/XFAjaxForm.class.php');
				$form = new XFAjaxForm($destRecord, false, $record);
				header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
				echo $form->compile();
			} else {
			
				$unconstrainedTables = $record->getUnconstrainedTables();
				$destRecords = array();
				foreach ($unconstrainedTables as $t){
					$destRecords[] = $record->toRecord($t);
				}
				if ( count($destRecords) == 1 ){
					import('modules/ajax_form/classes/XFAjaxForm.class.php');
					$form = new XFAjaxForm($destRecords[0], false, $record);
					header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
					echo $form->compile();
				
				} else {
					
					header('Content-type: text/html; charset="'.$app->_conf['oe'].'"');
					
					echo $this->getRelatedTablesPortal($destRecords, $record);
				}
				
			}
			
		}
	
	
	}
	
	
	function getRelatedTablesPortal($destRecords, $portalRecord){
		
		$html = array();
		$html[] = '<ul class="xf-portal-subtables" data-xf-portal-recordid="'.htmlspecialchars($portalRecord->getId()).'">';
		foreach ($destRecords as $record){
			$name = $record->table()->tablename;
			$label = $record->table()->getSingularLabel();
			
			$html[] = '<li class="xf-portal-subtable xf-portal-subtable-'.htmlspecialchars($name).'" data-xf-tablename="'.htmlspecialchars($name).'" data-xf-table-label="'.htmlspecialchars($label).'" data-xf-recordid="'.htmlspecialchars($record->getId()).'">';
			$html[] = '<div class="xf-portal-label">'.htmlspecialchars($label).'</div>';
			$html[] = '<div class="xf-portal-body collapsed"></div>';
			$html[] = '</li>';
			
		}
		$html[] = '</ul>';
		return implode("\r\n", $html);
	
	}
}