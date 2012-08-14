<?php
class XFAjaxList {
	
	private $record;
	private $relationshipName;
	
	private $query;
	private $start=0;
	private $limit=30;
	
	private $rows;
	
	private $singleId = null;
	
	
	public function __construct(){
	
	
	}
	
	
	private function loadDependencies(){
		$s = DIRECTORY_SEPARATOR;
		if ( !class_exists('simple_html_dom_node') ){
			require_once dirname(__FILE__).$s.'..'.$s.'lib'.$s.'simple_html_dom.php';
		}
	}
	
	public function initRelated(Dataface_Record $record, $relationshipName, $query = array(), $start=0, $limit=30){
		$this->record = $record;
		$this->relationshipName = $relationshipName;
		$this->query = $query;
		$this->start = $start;
		$this->limit = $limit;
		$this->rows = null;
		$this->singleId = null;
	}
	
	public function init(array $query = array(), $start = 0, $limit = 30){
		$this->record = null;
		$this->relationshipName = null;
		$this->query = $query;
		$this->start = $start;
		$this->limit = $limit;
		$this->rows = null;
		$this->singleId = null;
	}
	
	public function setSingleId($id){
		$this->singleId = $id;
	}
	
	private function getRows(){
		if ( !isset($this->rows) ){
			if ( isset($this->singleId )){
				$this->rows = array(
					df_get_record_by_id($this->singleId)
				);
			} else if ( isset($this->record) and isset($this->relationshipName) ){
				// We are showing related records
				$this->rows = $this->record->getRelatedRecordObjects($this->relationshipName, $this->start, $this->limit);
				
			} else {
				$query = $this->query;
				$query['-limit'] = $this->limit;
				$query['-skip'] = $this->start;
				$this->rows = df_get_records_array($query['-table'], $query);
			}
		}
		return $this->rows;
	}
	
	
	public function renderCell($record, $fieldname){
		$relationshipName = null;
		$tdel = null;
		$rtdel = null;
		if ( is_a($record, 'Dataface_Record') ){
			$table = $record->table();
			$tdel = $table->getDelegate();
			
			$m = basename($fieldname).'__renderCell';
			$out = null;
			if ( isset($tdel) and method_exists($tdel, $m) ){
				$out = $tdel->$m($record);
			}
			if ( isset($out) ) return $out;
			
			return htmlspecialchars($record->preview($fieldname));


		} else if ( is_a($record, 'Dataface_RelatedRecord')) {
			if ( strpos($fieldname, '.') !== false ){
				list($junk, $fieldname) = explode('.', $fieldname);
			}
			$table = $record->getParent()->table();
			$relationshipName = $record->_relationshipName;
			$m = 'rel_'.basename($relationshipName).'__'.basename($fieldname).'__renderCell';
			
			$tdel = $table->getDelegate();
			if ( isset($tdel) and method_exists($tdel, $m) ){
				$out = $tdel->m($record);
			}
			if ( isset($out) ) return $out;
			
			$table = $record->_relationship->getTable($fieldname);
			$m = basename($fieldname).'__renderCell';
			$tdel = $table->getDelegate();
			if ( isset($tdel) and method_exists($tdel, $m) ){
				$out = $tdel->$m($record->toRecord($fieldname));
			}
			
			if ( isset($out) ) return $out;
			
			return htmlspecialchars($record->preview($fieldname));
			
			
			
		} else {
			throw new Exception("Invalid operand.  Expected Dataface_Record or Dataface_RelatedRecord, but received a ".get_class($record)." object.");
			
		}
		
	}
	
	
	public function getFieldType($record, $fieldname){
		if ( is_a($record, 'Dataface_Record') ){
			return $record->table()->getType($fieldname);
		} else if ( is_a($record, 'Dataface_RelatedRecord') ){
			if ( strpos($fieldname, '.') !== false ){
				list($junk, $fieldname) = explode('.', $fieldname);
			}
			return $record->_relationship->getTable($fieldname)->getType($fieldname);
		}
	}
	
	
	private function getTable(){
		$table = null;
		if ( $this->record ) $table = $this->record->table();
		else $table = Dataface_Table::loadTable($this->query['-table']);
		return $table;
	}
	
	
	private function checkViewPermission($fieldname){
	
		if ( $this->record ){
			if ( strpos($fieldname,'.') !== false ){
				list($relationshipName, $justField) = explode('.', $fieldname);
				return $this->record->checkPermission('view', array('relationship'=>$relationshipName, 'field'=>$justField));
			} else {
				return $this->record->checkPermission('view', array('field'=>$fieldname));
			}
			
		} else {
			$perms = $this->getTable()->getPermissions(array('field'=>$fieldname));
			return @$perms['view'] ? true:false;
		}
	}
	
	
	public function compile($template=null){
		
		if ( !isset($template) ){
			$template = $this->generateHtmlTemplate();
		}
		
		$this->loadDependencies();
		$table = $this->getTable();
		$tableClass[] = 'xf-ajax-list';
		if ( $this->record and $this->relationshipName ){
			$tableClass[] = 'xf-ajax-related-list';
			$tableClass[] = 'xf-ajax-related-list--'.$table->tablename.'-'.$this->relationshipName;
		} else {
			$tableClass[] = 'xf-ajax-result-list';
			$tableClass[] = 'xf-ajax-result-list--'.$table->tablename;
		}
		
		$tableAtts['data-xf-table'] = $table->tablename;
		if ( $this->relationshipName ){
			$tableAtts['data-xf-relationship'] = $this->relationshipName;
		}
		if ( $this->record ){
			$tableAtts['data-xf-record-id'] = $this->record->getId();
		}
		if ( $this->query ){
			$tableAtts['data-xf-query'] = json_encode($this->query);
		}
		$tableAtts['data-xf-start'] = $this->start;
		$tableAtts['data-xf-limit'] = $this->limit;
		
		$tableClass = htmlspecialchars(implode(' ', $tableClass));
		$tableAttsStr = array();
		foreach ($tableAtts as $k=>$v){
			$tableAttsStr[] = $k.'="'.htmlspecialchars($v).'"';
		}
		$tableAtts = implode(' ', $tableAttsStr);
		
		$dom = str_get_html($template);
		$form = $dom->find('form');
		if ( !$form ){
			throw new Exception("Template did not contain a form.");
		}
		
		$fields = $form[0]->find('input, textarea, select');
		
		$namedFields = array();
		foreach ($fields as $element){
			$name = $element->{'name'};
			if ( !$this->checkViewPermission($name) ){
				continue;
			}
			$namedFields[$name] = $element;
		}
		
		$html[] = '<table class="'.$tableClass.'" '.$tableAtts.'><thead><tr>';
		foreach ($namedFields as $name=>$element){
			//$name = $element->{'name'};
			$field = $table->getField($name);
			if ( PEAR::isError($field) ){
				throw new Exception("Failed to load field: ".$name." => ".$field->getMessage());
			}
			$columnClass = array();
			$columnClass[] = 'xf-column';
			$columnClass[] = 'xf-column--'.str_replace('.','-', $name);
			$columnClass = implode(' ', $columnClass);
			$columnAtts = array(
				'data-xf-column-field' => $name
			);
			$columnAttsStr = array();
			foreach ($columnAtts as $k=>$v){
				$columnAttsStr[] = $k.'="'.htmlspecialchars($v).'"';
			}
			$columnAtts = implode(' ', $columnAttsStr);
			
			$label = $field['widget']['label'];
			if ( @$field['column']['label'] ){
				$label = $field['column']['label'];
			}
			$html[] = '<th class="'.htmlspecialchars($columnClass).'" '.$columnAtts.'>';
			$html[] = '<span class="xf-column-label">'.htmlspecialchars($label).'</span>';
			$html[] = '</th>';
			
		}
		$html[] = '</tr></thead>';
		$html[] = '<tbody>';
		
		$rows = $this->getRows();
		foreach ($rows as $row){
			if ( !$row->checkPermission('view') ) continue;
			$rowAtts = array(
				'data-xf-record-id'=>$row->getId()
			);
			$rowClass = array(
				'xf-row'
			
			);
			
			if ( $row->checkPermission('edit') ){
				$rowClass[] = 'xf-editable';
			}
			
			$rowClass = implode(' ', $rowClass);
			$rowAttsStr = array();
			foreach ($rowAtts as $k=>$v){
				$rowAttsStr[] = $k.'="'.htmlspecialchars($v).'"';
			}
			$rowAtts = implode(' ', $rowAttsStr);
			$html[] = '<tr class="'.htmlspecialchars($rowClass).'" '.$rowAtts.'>';
			
			foreach ($namedFields as $k=>$v){
				$fieldname = $k;
				if ( strpos($fieldname,'.') !== false ){
					list($junk, $fieldname) = explode('.', $fieldname);
				}
				$cellClass = array(
					'xf-cell',
					'xf-cell-'.$this->getFieldType($row, $fieldname)
				);
				if ( !$row->checkPermission('view', array('field'=>$fieldname)) ){
					$cellClass[] = 'xf-cell-no-access';
				}
				
				$cellAtts = array(
					'data-xf-field-type'=>$this->getFieldType($row,$fieldname),
					'data-xf-field-name'=>$fieldname
				);
				$cellClass = implode(' ', $cellClass);
				$cellAttsStr = array();
				foreach ($cellAtts as $ck=>$cv){
					$cellAttsStr[] = $ck.'="'.htmlspecialchars($cv).'"';
				}
				$cellAtts = implode(' ', $cellAttsStr);
				$html[] = '<td class="'.htmlspecialchars($cellClass).'" '.$cellAtts.'>';
				
				$html[] = '<div class="xf-cell-wrapper">'.$this->renderCell($row, $fieldname).'</div>';
				$html[] = '</td>';
			}
			
			$html[] = '</tr>';
		}
		$html[] = '</tbody></table>';
		
		
		
		
		
		return implode("\r\n", $html);
	}
	
	public function generateHtmlTemplate(){
	
		$html = array();
		$html[] = '<form>';
		if ( !isset($this->record) ){
			$table = $this->getTable();
			$fields = $table->fields(false, true);
			foreach ($fields as $name=>$field){
				if ( @$field['visibility']['list'] == 'hidden' ) continue;
				$html[] = '<input type="hidden" name="'.htmlspecialchars($name).'"/>';
			}
		} else {
			$table = $this->getTable();
			
			$relationship = $table->getRelationship($this->relationshipName);
			$fields = $relationship->fields(true);
			
			foreach ($fields as $fieldname){
				$field = $relationship->getField($fieldname);
				$fullname = $this->relationshipName.'.'.$field['name'];
				
				$rVis = @$relationship->_schema['visibility'][$field['name']];
			
				$vis = $field['visibility']['list'];
				
				if ( $rVis == 'hidden' ){
					continue;
				} else if ( $rVis != 'visible' ){
					if ( $vis == 'hidden' ) continue;
				}
				
				$html[] = '<input type="hidden" name="'.htmlspecialchars($fullname).'"/>';
				
			}
			
		}
		$html[] = '</form>';
		return implode("\r\n", $html);
		
	}
	
}