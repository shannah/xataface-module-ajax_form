<?php
class modules_ajax_form {

	/**
	 * @brief The base URL to the datepicker module.  This will be correct whether it is in the 
	 * application modules directory or the xataface modules directory.
	 *
	 * @see getBaseURL()
	 */
	private $baseURL = null;
	
	private $pathsRegistered = false;
	
	/**
	 * @brief Returns the base URL to this module's directory.  Useful for including
	 * Javascripts and CSS.
	 *
	 */
	public function getBaseURL(){
		if ( !isset($this->baseURL) ){
			$this->baseURL = Dataface_ModuleTool::getInstance()->getModuleURL(__FILE__);
		}
		return $this->baseURL;
	}
	
	
	public function registerPaths(){
		if ( !$this->pathsRegistered ){
			$this->pathsRegistered = true;
			$s = DIRECTORY_SEPARATOR;
			df_register_skin('ajax_form', dirname(__FILE__).$s.'templates');
			import('Dataface/JavascriptTool.php');
			Dataface_JavascriptTool::getInstance()->addPath(dirname(__FILE__).$s.'js', $this->getBaseURL().'/js');
			import('Dataface/CSSTool.php');
			Dataface_CSSTool::getInstance()->addPath(dirname(__FILE__).$s.'css', $this->getBaseURL().'/css');
		}
	
	}
}