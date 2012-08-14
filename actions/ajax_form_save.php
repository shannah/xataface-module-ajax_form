<?php
/**
 * @brief The core action that saves input from ajax forms.  All forms depend on this
 * action for saving their data.  This action will accept input to both local tables
 * and related tables.  It respects the dot/bracket notation for specifying which 
 * related record to update.
 *
 * <h3>Usage</h3>
 * <h4>Adding New Records</h4>
 *	<table>
 *		<tr><th>POST Parameter Name</th><th>Type</th><th>Description</th></tr>
 *		<tr><td>-table</td><td>String</td><td>The table to add the record to.</td></tr>
 *		<tr><td>--data</td><td>JSON-Encoded Associative Array</td><td>The json-encoded data
 *			to insert.  See below for information of the structure of the --data array.</td></tr>
 *	</table>
 *
 *	<h4>Editing Existing Records</h4>
 *	<table>
 *		<tr><th>POST Parameter Name</th><th>Type</th><th>Description</th></tr>
 *		<tr><td>-table</td><td>String</td><td>The name of the table in which the record can be found.</td></tr>
 *		<tr><td>--recordId</td><td>String</td><td>The Xataface record ID.  This should be the output of the Dataface_Record::getId() method for some record.</td></tr>
 *		<tr><td>--data</td><td>JSON-Encoded Associative Array</td><td>The json-encodeda data
 *			to insert.  See below for information about the structure of the --data array.</td></tr>
 *	</table>
 *
 * <h4>Structure of the @e --data Array</h4>
 *
 * <p>The @e --data array is an associative array with key-value pairs that are being 
 * set in the record in question.  It can include local fields or related fields (using
 * a special dot/bracket notation to specify which related record to affect.</p>
 *
 * <p>For example:</p>
 * @code
 * {
 *	firstname: 'Steve',
 *	lastname: 'Hannah'
 * }
 * @endcode
 * <p>Would update the @e firstname field to "Steve" and the @e lastname field to "Hannah" 
 * in the specified record.</p>
 *
 * @code
 * {
 *		firstname: 'Steve',
 *		lastname: 'Hannah',
 *		'student.cgpa' : 2.0
 * }
 * @endcode
 * <p>Would additionally update the @e cgpa field of the first record in the @e student relationship
 * of the current record to "2.0".</p>
 *
 * <p>You could also specify that you want to update the 2nd related record:</p>
 * @code
 * {
 * 		'student{1}.cgpa' : 2.0
 * }
 * @endcode
 *
 * <p>Or you could specify explicitly which related record you want to 
 * update by including the ID of the related record in the key:</p>
 * @code
 * {
 *		'student{contact/student?contact_id=10&student::student_id=11}.cgpa' : 2.0
 * }
 * @endcode
 *
 * <h3>Output</h3>
 *
 * <p>This action outputs mimetype text/json in the following format:</p>
 *
 * @code
 *	{
 *		code => $code:int,
 *		message => $message:string,
 *		recordId => $recordId:string,
 *		relatedIds => {
 *			$relationshipName:string => [
 *				$relatedRecordId:string
 *			]
 *		},
 *		fieldErrors => {
 *			$fieldName:string => $errorMessage:string
 *		},
 *		formErrors => {
 *			$errorName:string => $errorMessage:string
 *		}
 * }
 * @endcode
 *		
 */
class actions_ajax_form_save {

	/**
	 * Constants for returning response codes.
	 */
	const PERMISSION_DENIED = 88400;
	const RECORD_NOT_FOUND = 88404;
	const VALIDATION_ERROR = 88501;
	
	
	/**
	 * @brief Associative array of errors for specific local field
	 * errors in the form.
	 *
	 * Structure [ $fieldName => $message:string ]
	 */
	private $fieldErrors = array();
	
	/**
	 * @brief Associative array of related field errors.
	 *
	 * Structure [ $relationshipName => [ $fieldName => $message:string ] ]
	 */
	private $relationshipErrors = array();
	
	/**
	 * @brief Associative array of form-level errors that occurred.
	 *
	 */
	private $formErrors = array();
	
	
	private $relatedIds = array();
	
	
	/**
	 * @brief Checks if a specific error code is a client error codes.  Client
	 * error codes are those codes that mark errors that should be propagated
	 * back to the client.  These are codes between 88000 and 89000 inclusive.
	 *
	 * Some examples include:
	 * PERMISSION_DENIED
	 * RECORD_NOT_FOUND
	 * VALIDATION_ERROR
	 *
	 * @param int $code The code to check.
	 * @returns boolean True if the code lies within the appropriate range.
	 */
	function isClientErrorCode($code){
		if ( $code >= 88000 and $code < 89000 ){
			return true;
		}
		return false;
	}
	
	/**
	 * @brief The action handler function.  This is the entry point to the action.
	 */
	function handle($params){
		import('Dataface/QuickForm.php');
		$app = Dataface_Application::getInstance();
		$query = $app->getQuery();
		
		// We only accept input to this action via the POST method
		if ( !@$_POST ) throw new Exception("This action only works via POST");
		
		// All save data is provided as a JSON structure
		// in the --data POST parameter.  If this is missing, we can't continue.
		if ( !@$_POST['--data'] ){
			throw new Exception("No data provided");
		}
		$table = Dataface_Table::loadTable($query['-table']);
		
		
		//-------------------------------------------
		// Find out if this is a new record form
		
		
		$new = true;	// A flag to indicate if this is a new record form
		$record = null;	// Container for the record that is to be edited
						// Default to null
						
		
						
		if ( @$_POST['--recordId'] ){
		
			// If a --recordId is provided, we must be editing an 
			// existing record.  Set the $new flag to false
			// and load the specified record.
			$new = false;
			$record = df_get_record_by_id($_POST['--recordId']);
		} else {
		
			// If no --recordId is provided, then we must be inserting
			// a new record.
			// Create a new empty record on the specified table to work with.
			$record = new Dataface_Record($query['-table']);
		}
		
		
		//$portalRecord = $app->getRecordContext($_POST['--recordId']);
		/*
		// Check to make sure that the portalRecord is indeed referring to the same record.
		$match = false;
		if ( $portalRecord ){
			$destRecords = $portalRecord->toRecords();
			foreach ($destRecords as $destRecord){
				if ( $destRecord->getId() == $record->getid() ){
					$match = true;
					break;
				}
			}
			if ( !$match ){
				throw new Exception("The portal record specified (".$portalRecord->getId().") does not encapsulate the target record (".$record->getId().")");
			}
		}
		*/
		
		
		
		//---------------------------------------------
		// Let's do some 2nd tier validation to make sure we were
		// able to load the record OK.
		
		if ( !$record or PEAR::isError($record) ){
		
			// If there is no record at this point, that means that a
			// --recordId was provided, but the record could not be found.
			// We raise en exception to this effect.
			
			// NOTE: RECORD_NOT_FOUND is a client exception so it should
			// be propagated to the client.
			throw new Exception("Record could not be found", self::RECORD_NOT_FOUND);
		}
		
		if ( $record->table()->tablename != $query['-table'] ){
		
			// This is an invariant.  
			// The table of the record that was loaded should match the -table
			// parameter.  We'll stop here because we might get strange results if
			// we continue.
			
			throw new Exception("Record table does not match the current table.");
		}
		
		
		// Decode the JSON data that is to be saved.
		$data = json_decode(trim($_POST['--data']), true);
		$formTool = Dataface_FormTool::getInstance();
		
		// At this point we should have
		// $record : Dataface_Record object to be updated.
		// $data : An associative array of data to save
		
		
		//  We need to do pre-validation ... let's make sure we have permission.
		
		//-----------------------------------------------
		// Let's separate the data out into local data
		// and related data.
		
			
		$relatedData = array(); 	// Associative array of related data
									// [ $relationshipName => 
									//		[ $index:int => 
									//			[ $fieldname:string => $fieldValue: mixed]
									//		]
									//	]
									
		$localData = array();		// Associative array of local data
									// [ $fieldname:string => $fieldValue:mixed]
									
		
		foreach ($data as $key=>$value){
			if ( strpos($key, '.') !== false ){
				// We have a related field.
				list($relationshipName, $fieldName) = explode('.', $key);
				$index = 0;
				if ( preg_match('/(.+)\{([^\}]*)\}$/', $relationshipName, $matches) ){
					if ( strlen($matches[2]) == 0 ){
						$index = '__new__';
					} else if ( preg_match('/^[0-9]+$/', $matches[2]) ){
						$index = intval($matches[2]);
					} else {
						$index = $matches[2];
					}
					$relationshipName = $matches[1];
				}
				if ( $record->table()->hasRelationship($relationshipName) ){
					$relatedData[$relationshipName][$index][$fieldName] = $value;
				}
			} else {
				
				$localData[$key] = $value;
			
			}
		
		}
		
		
		// At this point we should have:
		// $relatedData[ $relationshipName => [ $rowIndex => [ $fieldKey => $fieldValue] ] ]
		// $localData[ $fieldKey => $fieldValue ]
		
		
		
		// Now we need to build the Dataface_RelatedRecord objects that are to be 
		// either inserted or edited.
		
		$savedRecords = array();
		df_q("start transaction");
		try {
			
			
			if ( $localData ){
				// We need to first deal with the local data.
				$perm = $new ? 'new':'edit';
				if ( $new ){
					$localForm = Dataface_QuickForm::createNewRecordForm($table->tablename);
				} else {
					$localForm = Dataface_QuickForm::createEditRecordForm($record);
				}
				// We merge the local data with the current values in case
				// not all of the local data is supplied and the validation
				// requires a field.
				$localForm->_setSubmitValues(array_merge($record->strvals(), $localData ), array());
				if ( !$localForm->validate() ){
					//error_log("Local form validation failed");
					foreach ($localForm->_errors as $errFieldName=>$errFieldMessage){
						if ( array_key_exists($errFieldName, $localData) ){
							$this->fieldErrors[$errFieldName] = $errFieldMessage;
						} else {
							$this->formErrors[$errFieldName] = $errFieldMessage;
						}
					}
					throw new Exception("Local form validation errors.", self::VALIDATION_ERROR);
				}
				$r = $record;
				//if ( $portalRecord){
				//	$r = $portalRecord;
				//}
				if ( !$r->checkPermission($perm) ){
					$this->formErrors['__permissions__'] = 'No permission to add new record';
					throw new Exception("No permission to add new record", self::PERMISSION_DENIED);
				}
				
				
				foreach ($localData as $key=>$value ){
					if ( !$r->checkPermission($perm, array('field'=>$key) ) ){
						$this->fieldErrors[$key] = 'No permission to add new value for field '.$key;
						throw new Exception("No permission to add new value for field '".$key."'", self::PERMISSION_DENIED);
					}
					$field = $table->getField($key);
					if ( PEAR::isError($field) ){
						unset($localData[$key]);
						continue;
					}
					$res = $formTool->pushField($record, $field, $localForm, $key, $new);
					if ( PEAR::isError($res) ){
						$this->fieldErrors[$key] = 'Failed to push value for field '.$key;
						throw new Exception("Failed to push value for key: '".$key."'");
					}
				}
				
				//$record->setValues($localForm->_record->vals(false, true, true));
				$res = $record->save();
				if ( PEAR::isError($res) ){
					throw new Exception($res->getMessage(), $res->getCode());
				}
			}
				
		
			$relatedRecords = array();
			
			foreach ($relatedData as $relationshipName => $relatedRows){
				foreach ($relatedRows as $index=>$relatedRow){
					
					$relatedRecord = null;
					$newRelated = false;
					if ( !$new ){
						if ( $index === '__new__' ){
							// Do nothing at this point.  Wait for it
							// to get picked up in the next if statement
						} else if ( is_int($index) ){
							//error_log("Trying to load related record at index $index for record ".$record->getId());
							$relatedRecord = $record->getRelatedRecord($relationshipName, $index);
						} else if ( preg_match('/^'.preg_quote($record->table()->tablename.'/'.$relationshipName.'?', '/').'/', $index) ){
							$relatedRecord = df_get_record_by_id($index);
							if ( PEAR::isError($relatedRecord) ){
								throw new Exception($relatedRecord->getMessage(), $relatedRecord->getCode());
							}
							if ( !$relatedRecord ){
								// Since the id was specified explicitly
								// we need to return an explicit error.
								$this->relationshipErrors[$relationshipName]['__not_found__'] = 'The related record specified could not be found.';
								throw new Exception("The related record specified was not found.", self::RECORD_NOT_FOUND);
							}
						} else {
							throw new Exception("Invalid related record ID could not be parsed: ".$index);
							
						}
					} else {
						//error_log("This is a NEW record form.");
					}
					if ( !$relatedRecord ){
						//error_log("No related record found at index $index for relationship $relationshipName");
						$newRelated = true;
						$relatedRecord = new Dataface_RelatedRecord($record, $relationshipName, array());
					}
					
					if ( $newRelated and self::arrayIsEmpty($relatedRow) ){
						continue;
					}
					
					$relatedPerm = $newRelated?'new':'edit';
					
					// Check record-level permissions to make sure that we're allowed
					// to add new related records to this relationship.
					if ( !$relatedRecord->checkPermission($relatedPerm) ){
						$this->relationshipErrors[$relationshipName]['__permissions__'] = 'Permission denied.  Requires the '.$perm.' permission.';
						
						throw new Exception("Failed to save record because you don't have permission to add new records to the $relationshipName relationship.", self::PERMISSION_DENIED);
						
					}
					
					
					$rowForms = array();
					$rowVals = array();
					
					
					// We need to build some dummy forms to do some validation
					// on the related row.  If it is comprised of multiple tables
					// then we need multiple forms.
					foreach ( $relatedRow as $relatedKey=>$relatedValue ){
					
					
					
						
						// Now we need to get the data from the form.
						$relationship = $table->getRelationship($relationshipName);
						if ( PEAR::isError($relationship) ){
							throw new Exception($relationship->getMessage(), $relationship->getCode());
						}
						
						// Get the table from which this field originates
						$relatedTable = $relationship->getTable($relatedKey);
						if ( PEAR::isError($relatedTable) ){
							throw new Exception($relatedTable->getMessage(), $relatedTable->getCode());
						}
						
						if ( !isset($rowForms[$relatedTable->tablename]) ){
							$rowForms[$relatedTable->tablename] = Dataface_QuickForm::createNewRecordForm($relatedTable->tablename);
							$rowForms[$relatedTable->tablename]->_build();
							// We need to remove validation rules for fields that won't be 
							// part of the form, i.e. constrained fields.  Constrained fields
							// are those fields that are part of a foreign key and
							// should not be changed in a related record form.
							$constrainedFields = $relatedRecord->getConstrainedFields();
							foreach ($constrainedFields as $constrainedField){
								if ( strpos($constrainedField,'.') !== false ){
									list($junk,$constrainedField) = explode('.', $constrainedField);
								}
								//echo "Removing $constrainedField";exit;
								$rowForms[$relatedTable->tablename]->removeElement($constrainedField);
							}
						}
						
						
						$quickForm = $rowForms[$relatedTable->tablename];
						if ( PEAR::isError($quickForm) ){
							throw new Exception($quickForm->getMessage(), $quickForm->getCode());
						}
						
						$rowVals[$relatedTable->tablename][$relatedKey] = $relatedValue;
						
						
					}
					
					
					foreach ( $relatedRow as $relatedKey=>$relatedValue ){
					
					
					
						if ( !$relatedRecord->checkPermission($relatedPerm, array('field'=>$relatedKey)) ){
						
							$this->fieldErrors[$relationshipName.'.'.$relatedKey] = 'Permission denied on the field '.$relationshipName.'.'.$relatedKey;
							
							throw new Exception("Failed to save record because you don't have permission to add new records to the $relationshipName.$relatedKey field.", self::PERMISSION_DENIED);
						}
						
						
					}
					
					// Now go through the forms that we built and try to validate
					// the input using the validation rules.
					foreach ($rowForms as $rowTableName=>$rowForm){
						
						
						$rowForm->_setSubmitValues(array_merge($relatedRecord->strvals(), $rowVals[$rowTableName]), array());
						
						if ( !$rowForm->validate() ){
							// Failed to validate row data
							foreach ($rowForm->_errors as $errKey=>$errVal){
								if ( array_key_exists($errKey, $relatedRow) ){
									$this->fieldErrors[$relationshipName.'.'.$errKey] = $errVal;
								} else {
									$this->relationshipErrors[$relationshipName]['__permissions__'] = $errVal;
								}
							}
							throw new Exception("Failed to validate related row input.", self::VALIDATION_ERROR);
						
						}
						$tempTable = Dataface_Table::loadTable($rowTableName);
						$tempTableRecord = $relatedRecord->toRecord($rowTableName);
						foreach ($rowVals[$rowTableName] as $key=>$value ){
							
							$field = $tempTable->getField($key);
							$res = $formTool->pushField($tempTableRecord, $field, $rowForm, $key, $newRelated);
							if ( PEAR::isError($res) ){
								//$this->fieldErrors[$key] = 'Failed to push value for field '.$key;
								throw new Exception("Failed to push value for key: '".$key."'");
							}
							$relatedRecord->setValue($key, $tempTableRecord->val($key));
						}
						
						//$relatedRecord->setValues($rowForm->_record->vals(false, true, true));
						
					}
					
					
					
					
					if ( $newRelated ){
						//error_log("Saving new related: ".$relatedRecord->getId());
						$io = new Dataface_IO($record->table()->tablename);
						
						$res = $io->addRelatedRecord($relatedRecord);
						if ( PEAR::isError($res) ){
							throw new Exception($res->getMessage(), $res->getCode());
						}
						$this->relatedIds[$relationshipName][] = $relatedRecord->getId();
						//echo "ID: ".$relatedRecord->getId();exit;
					} else {
						//error_log("Saving existing related ".$relatedRecord->getId());
						$res = $relatedRecord->save();
						if ( PEAR::isError($res) ){
							throw new Exception($res->getMessage(), $res->getCode());
						}
						$this->relatedIds[$relationshipName][] = $relatedRecord->getId();
					}
					
					
					
					
					
					
					
				} // foreach ($relatedRows as $index=>$relatedRow){
			
			} // foreach ($relatedData as $relationshipName => $relatedRows){
		
			
			
			df_q("commit");
			
			
			$this->out(array(
				'code' => 200,
				'message' => 'Saved successfully',
				'recordId' => $record->getId(),
				'relatedIds' => $this->relatedIds
			));
			
			
			
		} catch ( Exception $ex){
			// If an error occurred, we need to clean up all of the records
			// that we already saved.
			df_q("rollback");
			
			if ( $this->isClientErrorCode($ex->getCode()) ){
				$this->out(array(
					'code' => $ex->getCode(),
					'message' => 'Save failed due to some errors.',
					'fieldErrors' => $this->fieldErrors,
					'formErrors' => $this->formErrors,
					'relationshipErrors' => $this->relationshipErrors
				));
			} else {
				throw $ex;	
			}
			
		
		}
		
		
		
	
	}
	
	
	function out($params){
	
		header('Content-type: text/json; charset="'.Dataface_Application::getInstance()->_conf['oe'].'"');
		echo json_encode($params);
	}
	


	static function arrayIsEmpty($arr){
		$match = false;
		foreach ($arr as $key=>$val){
			if ( $val ){
				$match = true;
				break;
			}
		}
		return !$match;
	}
	
	
	
}