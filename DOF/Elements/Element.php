<?php
namespace DOF\Elements;

/**
 * This is the core element to build the site. DOF (Data Oriented FrameWork) is based on data representation, stoarge and manipulation.
 * Elements are the way to indicate the system all data that conforms it. Each Element represents a data set.
 *
 * In practical terms Elements are just Objets with extended capabilities to handdle some comon tasks like:
 * Print their contets, Store their contents, find and retrive the proper data froma a dataStorage, etc.
 *
 * Elemnts are programed and used like any other regular object except
 * that in orther to make their special features work some of their attributes must be DOF data objects.
 *
 * @author RSL
 */
class Element extends \DOF\BaseObject {
	protected $field_id = 'id';
	protected $dir;
	protected $storage;
	protected $tempFormPrefix;

	/*@var dataStorage DataStorage*/
	protected $dataStorage;
	
	protected $dataAttributes;
	
	protected $storageChecked;
	
	/**
	* Costructor.
	*
	* Meant to be added at the end of all heir's constructors width:
	*
	* parent::__construct($id=null);
	*
	*
	* beacuse it perfroms DOFdata dependant methods but that are common to all DOFelements
	*
	* @param int $id
	*/
	public function __construct($id=null,&$specialDataStorage=null)
	{
		//On heirs put here the asignation of DOFdata and attributes
		
		if(!$this->storage()) $this->storage(end(explode('::',$this->getClass())));
		
		//Asings the storage element for the DOFelement. (a global one : or a particular one)
		if(!$specialDataStorage){
			$this->dataStorage = \DOF\Main::$DATA_STORAGE;
		}else{
			$this->dataStorage=&$specialDataStorage;
		}
		
		//checking if there is already a dataStorage and storage for this element
		$this->dataStorage->ensureElementStorage($this);
		
		//if there is a storage and an ID it fills the element with the proper info.
		if($id) {
			$this->fillFromDSById($id);
		}

		// Tells the DOFdata whose thier "container" in case any of it has context dependent info or functions.
		$this->assignAsDatasParent();
		
		$this->assignDatasName();
	}
	
	public function index() {
		return '
			<h1>'.$this->getClass().' works!</h1>
			<p><a href="'.$this->encodeURL(array(), 'showCreate').'">Create a new '.$this->getClass().'</a></p>
		';
	}

	public function fillFromDSById($id = null)
	{
		if(isset($id)) $this->id($id);
		
		if($this->id()){
			/*@var $this->dataStorage DataStorage*/
			$dataArray = $this->dataStorage->getElementData( $this );
			
			$this->fillFromArray($dataArray);
		}else{
			throw new Exception('The object of class: '.$this->getClass()." has no id so it can't be filled using method fillElementById" );
		}
	}
	
	public function fillFromArray(array &$array_of_data)
	{
		foreach($this->dataAttributes() as $dataName) {
			$this->$dataName($array_of_data[$dataName]);
		}
	}
	
	/**
	 *
	 * NOTE: This method is not a simple redirection to $this->fillFromArray($_REQUEST) because the file upload requeires the $_FILES array
	 * Thus the redirection from fillFromRequest to fillFromArray is made at the DOFData and there for any DOFData that needs to
	 * distinguish between both can do it.
	 *
	 */
	public function fillFromRequest()
	{
		return $this->fillFromArray($_REQUEST);
	}
	
	public function saveInDS()
	{
		/*@var $this->dataStorage DataStorage*/
		return $this->dataStorage->saveElement($this);
	}
	
	public function createInDS()
	{
		/*@var $this->dataStorage DataStorage*/
		return $this->dataStorage->createElement($this);
	}
		
	public function updateInDS()
	{
		/*@var $this->dataStorage DataStorage*/
		return $this->dataStorage->updateElement($this);
	}

	public function deleteFromDS()
	{
		/*@var $this->dataStorage DataStorage*/
		return $this->dataStorage->deleteElement($this);
	}
	
	public function templateFilePath($show_type, $alternative = '', $template_type = 'html') {
		return \DOF\Main::$GENERIC_TEMPLATES_PATH . '/' . $show_type . '/' .$this->getClass() . $alternative . '.' .$template_type;
	}
	
	public function showCreate($template_file = null, $action = null)
	{
		return $this->obtainHtml(__METHOD__, $template_file, $action);
	}
	
	/* */
	public function showUpdate($template_file = null, $action = null)
	{
		return $this->obtainHtml(__METHOD__, $template_file, $action);
	}

	public function showView($template_file = '')
	{
		return $this->obtainHtml(__METHOD__, $template_file, null);
	}
		
	// @todo: allow to obtain only the dom part inherent to the element (and not the whole web page)
	public function obtainHtml($caller_method, $template_file = null, $action = null)
	{	
		$caller_method = end(explode('::',$caller_method));
		$VCSL = substr($caller_method, strlen('show'));
		$vcsl = strtolower($VCSL);
		$with_form = in_array($vcsl, array('create', 'update'));
		 
		if(!@$template_file) {
			// get default path
			$template_file = $this->templateFilePath($VCSL);
		}
		
		if(!file_exists($template_file) || \DOF\Main::$OVERWRITE_LAYOUT_TEMPLATES) {
			$dom = \phpQuery::newDocumentFileHTML(\DOF\Main::$MASTER_TEMPLATE);
			$dom['head']->append($this->getJS($caller_method, 'html'));
			
			foreach($this->attributesTypes('\\DOF\\Datas\\File') as $fileData)
			{
				if( $fileData->$vcsl() ){
					$enctype = ' enctype="multipart/form-data" ';
					break;
				}
			}
			
			// create and fill file
			$html = '';
			if($with_form) {
				$html.= '<form class="DOF '.$this->getClass().'" '
					. ' action="'. (@$action ?: $this->encodeURL(array(), 'process'.$VCSL) ) .'" ' 
					. ' method="post" ' 
					. @$enctype 
					.'>';
			}
			$html.= '<div class="DOF '.$this->getClass().'">';
			foreach($this as $keydata => $data)
			{
				if( $data instanceof \DOF\Datas\Data && $data->$vcsl() )
				{					
					$html.= '<div class="DOF '.$keydata.'">';
					
					if($with_form) {
						$data_id = 'DOF_'.$data->instanceId();
						$dompart = \phpQuery::newDocumentHTML($data->$caller_method());
						// @todo: Document that class input is MANDATORY
						$dompart['.input']->attr('id', $data_id);
						
						if($data->label())
							$html.='<label for="'.$data_id.'">'.$data->label().': </label>';
						
						$html.= $dompart['.input'];
					} else {
						$html.= $data->$caller_method();
					}
					
					$html.= '</div>';
				}
			}
			if($with_form) {
				$html.= '<button name="commit" type="submit">Save</button>'
					.'<button name="cancel" onclick="javascript:history.back()">Cancel</button>'
					.'</div></form>';
			} else {
				$html.= '</div>';
			}
			$dom['body'] = $html;
			
			// save file
			file_put_contents($template_file, $dom.'');
		} else {
			// opens file
			$dom = \phpQuery::newDocumentFileHTML($template_file);
			
			// fill file with data 
			if($vcsl != 'create') {
				foreach($this as $keydata=>$data) {
					if($data instanceof \DOF\Datas\Data && $data->$vcsl())
					{
						$dom['.DOF.'.$this->getClass().' .DOF.'.$keydata] = $data->$caller_method($dom['.DOF.'.$this->getClass().' .DOF.'.$keydata]);
					}
				}
			}
		}
		
		return $dom;
	}

	public function getJS($method, $returnFormat = 'array', $compress = false) {
		$method = end(explode('::',$method));
		$class = end(explode('\\',$this->getClass()));
		
		$local_js = \DOF\Main::$JS_FLAVOUR_BASE . "/Inits/$class.$method.js";
		$a_js = file_exists($local_js) ? array($local_js) : array();
		
		foreach($this->dataAttributes() as $data) {
			/*
			$class = end(explode('\\',$this->{'O'.$data}()->getClass()));
			$local_js = \DOF\Main::$JS_FLAVOUR_BASE . "/Inits/$class.$method.js";
			
			if(file_exists($local_js))
				$a_js[] = $local_js;
			 * */
			foreach($this->{'O'.$data}()->getJS($method) as $local_js)
				if(file_exists($local_js))
					$a_js[] = $local_js;
		}
		$a_js = array_unique($a_js);
		
		if($compress) {
			// @todo: compress in one file and return the file path
		}
		
		// converts to remote paths
		$a_js = array_map(
			function($fp) {
				return str_replace(\DOF\Main::$LOCAL_ROOT, \DOF\Main::$REMOTE_ROOT, $fp);
			},
			array_unique(array_merge(
				glob(\DOF\Main::$JS_FLAVOUR_BASE . '/Libs/*'),
				$a_js
			))
		);
		
		switch($returnFormat) {
			case 'html':
				return ($a_js)
					? '<script type="text/javascript" src="'. implode('"></script>'."\n".'<script type="text/javascript" src="', $a_js) . '"></script>'
					: '';
			case 'array':
				return $a_js;
		}
	}
	
	/**
	* Tells the DOFdata whose their "container" in case any of it has context dependent info or functions.
	*
	* @param &$dataParent Reference to the logical data parent.
	*/
	public function assignAsDatasParent(&$parent=null)
	{
		if(!isset($parent)) $parent = $this;
		
		foreach($this as $data)
		{
			if($data instanceof \DOF\Datas\Data)
			{
				if( $data->hasMethod('parent')  )
				{
					$data->parent($parent);
				}
			}
		}
	}
	
	public function assignDatasName()
	{
		foreach($this as $name => $data) {
			if($data instanceof \DOF\Datas\Data && !$data->name()) {
				$data->name($name);
			}
		}
	}
	
    public function __call($name, $arguments)
    {
        
    	if(@$this->$name instanceof \DOF\Datas\Data)
        {
        	if($arguments){ $this->$name->val($arguments[0]); return; }
        	else{ return $this->$name->val(); }
        	
        } else {
        	
        	$letter=substr($name,0,1);
        	$Xname=substr($name,1);
        	
			if(($letter == strtoupper($letter)) && (@$this->$Xname instanceof \DOF\Datas\Data)) {
				switch($letter) {
					case 'O': 
		   				if($arguments){ $this->$Xname->val($arguments[0]); }
			        	else{ return $this->$Xname; }
						break;
						/*
					case 'F':
						if($arguments){ $this->$Xname->val($arguments[0]); }
        				else{ return $this->$Xname->field(); }
						break;*/
					case 'L':
						if($arguments){ $this->$Xname->val($arguments[0]); }
	        			else{ return $this->$Xname->label(); }
						break;
					default:
						throw new \Exception('Letter '.$letter.' not recognized!');
				}
			} else {
        		return parent::__call($name, $arguments);
        	}
        }
    }
	
	/*@todo determina if this method is neceary or not
	 updateInDS // este debe ser automatico desde el save si se tiene id se genera
	*/
	
	function encodeURL(array $construct_params, $method, array $method_params = array()) {
		return \DOF\Main::encodeURL($this->getClass(), $construct_params, $method, $method_params);
	}
	
	function processCreate(){
		$this->fillFromRequest();
		if($this->saveInDS()) {
			header('Location: '.$this->encodeURL(array($this->id()), 'showUpdate'));
		} else {
			// @todo: error handling
			user_error('Cannot save in DS!', E_USER_ERROR);
		}
	}
	
	function processUpdate(){
		$this->fillFromRequest();
		if($this->updateInDS()) {
			header('Location: '.$this->encodeURL(array($this->id()), 'showUpdate'));
		} else {
			// @todo: error handling
			user_error('Cannot save in DS!', E_USER_ERROR);
		}
	}
	
	// @todo: change name to attributesOfType
	function attributesTypes($type = '\\DOF\\Datas\\Data') {
		foreach($this as $name => $data) {
			if($data instanceof $type) {
				$a[] = $name;
			}
		}
		
		return @$a ?: array();
	}
	
	function dataAttributes() {
		if(!$this->dataAttributes) {
			$this->dataAttributes = $this->attributesTypes();
		}
		
		return $this->dataAttributes;
	}
	
	//vcsrl
	public function datasForView(){
		foreach($this as $data) {
			if($data instanceof \DOF\Datas\Data && $data->$what()) {
				$output.= $data->{'show'.ucfirst($what)}();
			}
		}		
	}
	
	public function datasForCreate(){
		
	}
	
	public function datasForUpdate(){
		
	}	
	
	public function datasForSearch(){
		
	}	
	
	public function datasForList(){
		
	}	
		
	public function datasRequired(){
		
	}
}