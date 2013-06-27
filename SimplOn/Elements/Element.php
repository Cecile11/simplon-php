<?php

/*
Copyright © 2011 Rubén Schaffer Levine and Luca Lauretta <http://simplonphp.org/>

This file is part of “SimplOn PHP”.

“SimplOn PHP” is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation version 3 of the License.

“SimplOn PHP” is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with “SimplOn PHP”.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace SimplOn\Elements;

use SimplOn\Datas\ComplexData;
use SimplOn\DataStorages\DataStorage;
use \SimplOn\Datas,
\SimplOn\Datas\Data,
\SimplOn\BaseObject,
\SimplOn\Main,
\SimplOn\Exception,
\SimplOn\Elements\JS,
\SimplOn\Elements\CSS;

/**
* This is the core element to build the site. Elements are the way to indicate the system all data that conforms it.
* Each Element represents a data set.
*
* In practical terms Elements are just Objets with extended capabilities to handle some common tasks like:
* Print their contents, Store their contents, find and retrieve the proper data from a dataStorage, etc.
*
* Elements are programmed and used like any other regular object except that,
* in order to make their special features work, some of their attributes must be SimplON Data objects.
*
* @author RSL
*/
class Element extends BaseObject {

	/**
* Name of the Data attribute that represents
* the ID field of the Element
* (ie. SQL primary key's column name).
* @todo enable handle of multiple id fields, that should be automatically
* detected, as those should be all instances of \SimplOn\Data\Id).
* @var string
*/
	protected $field_id;

	/**
* What DataStorage to use.
* @var SimplOn\DataStorages\DataStorage
*/
	protected $dataStorage;

	/**
* Name of the storage associated to this Element
* (ie. SQL table name, MongoDB collection name).
* @var string
*/
	protected $storage;

	/**
* Criteria to use for searching.
* @example (.Data1) AND (Data2 == "Hello")
* @var string
*/
	protected $filterCriteria;
	protected $deleteCriteria;

	/**
* Represents the nesting level of the element (1 is the ancestor element,
* greater values means deeper nesting).
* Used in the rendering process.
* @var integer
*/
	protected $nestingLevel = 1;
	protected $parent;
	//------------------------------------------- ???
	/**
* Flag to avoid the system to validate
* DataStorage more than once.
* @var boolean
*/
	protected $storageChecked;
	protected $excetionsMessages = array();
	var $quickDelete;
	//------------------------------------------Performance
	/**
* Stores a list of Element's attributes
* of type Data for better performance.
* @var array containing objects of type SimplOn\Datas\Data
*/
	protected $dataAttributes;
	protected $formMethods = array('create', 'update', 'delete', 'search');
	//-----------------------------------------------------------------------------------------
	//------------------------------ METHODS --------------------------------------------------
	//-----------------------------------------------------------------------------------------

	protected $allowAll = false; // true = AllowAll, false = DenyAll
	protected $permissions;

	/**
* - Calls user defined constructor.
* - Adds default Element's actions.
* - Validates DataStorages.
* - Fills its Datas' values if possible (requires a valid ID or array of values).
* - Fills some of its Datas' meta-datas (parent, names).
* @param mixed $id_or_array ID of the Element or array of Element's Datas values.
* @param DataStorage $specialDataStorage DataStorage to use in uncommon cases.
*/
	public function __construct($id_or_array = null, $storage = null, $specialDataStorage = null) {
		$this->sonMessage = new \SimplOn\Datas\Message();

		//On heirs put here the asignation of SimplOndata and attributes
		if ($storage)
		$this->storage($storage);
		else
		$this->storage(strtr($this->getClass(), '\\', '_'));

		//Assings the storage element for the SimplOnelement. (a global one : or a particular one)
		if (!$specialDataStorage) {
			$this->dataStorage = Main::dataStorage();
		} else {
			$this->dataStorage = &$specialDataStorage;
		}
		//Called to "construct" function
		$this->construct($id_or_array, $storage, $specialDataStorage);

		if (!$this->quickDelete)
		$this->quickDelete = Main::$QUICK_DELETE;

		if (!isset($this->viewAction))
		$this->viewAction = new Datas\ViewAction('', array('View'));
		if (!isset($this->createAction))
		$this->createAction = new Datas\CreateAction('', array('Create'));
		if (!isset($this->updateAction))
		$this->updateAction = new Datas\UpdateAction('', array('Update'));
		if (!isset($this->deleteAction))
		$this->deleteAction = new Datas\DeleteAction('', array('Delete'));
		//if( !isset($this->selectAction) )$this->selectAction = new Datas\SelectAction('', array('Select'));
		//$this->multiSelectAction = new Datas\DeleteAction('', array('Select'));
		//Load the attributes on the fly
		$this->addOnTheFlyAttributes();

		$this->assignDatasName();

		//user_error($this->{$this->field_id()}());
		// Tells the SimplOndata whose thier "container" in case any of it has context dependent info or functions.
		$this->assignAsDatasParent();






		//checking if there is already a dataStorage and storage for this element
		$this->dataStorage->ensureElementStorage($this);

		if (is_array($id_or_array)) {
			$this->fillFromArray($id_or_array);
		} else if ($id_or_array) {
			//if there is a storage and an ID it fills the element with the proper info.
			$this->fillFromDSById($id_or_array);
		}
	}

	/**
* User defined constructor, called within {@link __constructor()},
* useful to declare specific Data attributes.
* @param mixed $id_or_array ID of the Element or array of Element's Datas values.
* @param SimplOn\DataStorages\DataStorage $specialDataStorage DataStorage to use in uncommon cases.
*/
	public function construct($id_or_array = null, &$specialDataStorage = null) {

	}

	/**
* @todo
*
* check why this can't be changed by
* $this->field_id = $this->attributesTypes('\\SimplOn\\Datas\\Id');
* $this->field_id = $this->field_id[0];
* at the _construct Method
*
* Change the hole field_id concept from string to array
*
* @param type $val
* @return type
*/
	public function field_id($val = null) {
		if (!$this->field_id) {
			$this->field_id = $this->attributesTypes('\\SimplOn\\Datas\\Id');
			$this->field_id = $this->field_id[0];
		}

		if ($val) {
			$this->field_id = $val;
		} else {
			return $this->field_id;
		}
	}

	function parent(&$parent = null) {
		if (!$parent) {
			return $this->parent;
		} else {
			$this->parent = $parent;
			$this->nestingLevel($parent->nestingLevel() + 1);
		}
	}

	/**
* Allows some simplicity for coding and declarations, auto makes getters and setters
* so that any Data’s attribute value data->val() can be transparently accessed as a normal
* element attribute by Element->data(); and load all other BasicObject SimplON functionality
* @see SimplOn.BaseObject::__call()
*
*/
	public function __call($name, $arguments) {
		if (@$this->$name instanceof Data) {
			if ($arguments) {
				return $this->$name->val($arguments[0]);
			} else {
				return $this->$name->val();
			}
		} else {

			$letter = substr($name, 0, 1);
			$Xname = substr($name, 1);

			if (($letter == strtoupper($letter)) && (@$this->$Xname instanceof Data)) {
				switch ($letter) {
				case 'O':
					if ($arguments) {
						$this->$Xname->val($arguments[0]);
					} else {
						return $this->$Xname;
					}
					break;
					/*
case 'F':
if($arguments){ $this->$Xname->val($arguments[0]); }
else{ return $this->$Xname->field(); }
break; */
				case 'L':
					if ($arguments) {
						$this->$Xname->val($arguments[0]);
					} else {
						return $this->$Xname->label();
					}
					break;
				default:
					throw new \Exception('Letter ' . $letter . ' not recognized!');
				}
			} else {
				return parent::__call($name, $arguments);
			}
		}
	}

	function htmlClasses($append = '', $nestingLevel = null) {
		if (!$nestingLevel)
		$nestingLevel = $this->nestingLevel();
		return 'SimplOn Element ' . 'SNL-' . $nestingLevel . ' ' . $this->getClassName() . ' ' . $append;
	}

	function cssSelector($append = '', $nestingLevel = null) {
		if (!$nestingLevel)
		$nestingLevel = $this->nestingLevel();
		return '.SimplOn.Element.SNL-' . $nestingLevel . '.' . $this->getClassName() . $append;
	}

	// -- SimplON key methods
	/**
* Assigns to each Data attribute it's corresponding value
* from an array of values.
*
* @param array $array_of_data
*/
	public function fillFromArray(&$array_of_data) {
		$filled = 0;
		if (is_array($array_of_data)) {
			foreach ($array_of_data as $dataName => $value) {
				if (isset($this->$dataName) && ($this->$dataName instanceof Data)) {
					try {
						$this->$dataName($value);
						$filled++;
					} catch (\SimplOn\DataValidationException $ve) {
						$this->excetionsMessages[$dataName] = array($ve->getMessage());
					}
				}
			}
		}
		return $filled;
	}

	public function requiredCheck($array = array()) {

		$requiredDatas = $this->datasWith('required');
		foreach ($requiredDatas as $requiredData) {
			if (!$this->$requiredData->val() && ($this->$requiredData->required() && !@$this->$requiredData->autoIncrement)) {
				$array[$requiredData][] = $this->$requiredData->validationRequired();
			}
		}

		return $array;
	}

	/**
*
* NOTE: This method is not a simple redirection to $this->fillFromArray($_REQUEST) because the file upload requeires the $_FILES array
* Thus the redirection from fillFromRequest to fillFromArray is made at the SimplOnData and there for any SimplOnData that needs to
* distinguish between both can do it.
*
*/
	public function fillFromRequest() {
		//try{
		$this->fillFromArray($_REQUEST);
		//}catch(\SimplOn\ElementValidationException $ev){}
		/**
* COMPLETE THE PART TO HANDLE FILES
*/
	}

	public function fillFromPost() {
		if ($_POST) {
			return $this->fillFromArray($_POST);
		} else {
			return false;
		}
	}

	//------------Data Storage
	/**
* Retrieves the element's Datas values from the DataSotarage,
* using the recived Id or the element's id if no id is provided.
*
* @param mixed $id the id of the element whose data we whant to read from de DS
* @throws Exception
*
* @todo: in  arrays format ????
*/
	public function fillFromDSById($id = null) {
		if (isset($id)) {
			$this->setId($id);
		}
		if ($this->getId() || $this->getId() === 0) {
			$dataArray = $this->dataStorage->readElement($this);
			$this->fillFromArray($dataArray);
		} else {
			throw new Exception('The object of class: ' . $this->getClass() . " has no id so it can't be filled using method fillElementById");
		}
	}

	public function save() {
		return $this->getId() !== false ? $this->update() : $this->create();
	}

	public function create() {
		$this->processData('preCreate');

		$id = $this->dataStorage->createElement($this);
		$this->setId($id);

		$this->processData('postCreate');

		return $id !== false;
	}

	public function update() {
		$this->processData('preUpdate');
		$return = $this->dataStorage->updateElement($this);
		$this->processData('postUpdate');
		return $return;
	}

	public function delete() {
		$this->processData('preDelete');
		$return = $this->dataStorage->deleteElement($this);
		$this->processData('postDelete');
		return $return;
	}

	/* @todo determine if this method is neceary or not
updateInDS // este debe ser automatico desde el save si se tiene id se genera
*/

	function validateForDB() {
		@$excetionsMessages = $this->requiredCheck($this->excetionsMessages);
		if (!empty($excetionsMessages)) {
			throw new \SimplOn\ElementValidationException($excetionsMessages);
		}
	}

	function processCreate($nextStep = null) {

		try {
			$this->fillFromRequest();
			$this->validateForDB();
		} catch (\SimplOn\ElementValidationException $ev) {
			$data = array();
			foreach ($ev->datasValidationMessages() as $key => $value) {
				$data[] = array(
				'func' => 'showValidationMessages',
				'args' => array($key, $value[0])
				);
			}
			$return = array(
			'status' => true,
			'type' => 'commands',
			'data' => $data
			);
			echo json_encode($return);
			return;
		}
		try {
			if ($this->create()) {
				if (empty($nextStep)) {
					$data = array(array(
					'func' => 'redirectNextStep',
					'args' => array($this->encodeURL(array($this->getId())) . '!showAdmin')
					));
					$return = array(
					'status' => true,
					'type' => 'commands',
					'data' => $data
					);
					echo json_encode($return);
					return;
				} else if (substr($nextStep, -1 * strlen('makeSelection')) == 'makeSelection') {
					header('Location: '.$nextStep . '/' . $this->getId().'/"'.Main::fixCode(strtr(urlencode($this->getClass()),'\\','-')).'"');
					return;
				} else {
					$data = array(array(
					'func' => 'redirectNextStep',
					'args' => $nextStep
					));
					$return = array(
					'status' => true,
					'type' => 'commands',
					'data' => $data
					);
					echo json_encode($return);
					return;
				}
			} else {
				// @todo: error handling
				user_error('Cannot update in DS!', E_USER_ERROR);
			}
		} catch (\PDOException $ev) {
			//user_error($ev->errorInfo[1]);
			//@todo handdle the exising ID (stirngID) in the DS
			user_error($ev);
		}
	}

	//function processUpdate($short_template=null, $sid=null){
	function processUpdate($nextStep = null) {
		try {
			$this->fillFromRequest();
			$this->validateForDB();
		} catch (\SimplOn\ElementValidationException $ev) {
			$data = array();
			foreach ($ev->datasValidationMessages() as $key => $value) {
				$data[] = array(
				'func' => 'showValidationMessages',
				'args' => array($key, $value[0])
				);
			}
			$return = array(
			'status' => true,
			'type' => 'commands',
			'data' => $data
			);
			echo json_encode($return);
			return;
		}
		try {
			if ($this->update()) {
				if (empty($nextStep)) {
					$data = array(array(
					'func' => 'redirectNextStep',
					'args' => array($this->encodeURL(array($this->getId())) . '!showAdmin')
					));
					$return = array(
					'status' => true,
					'type' => 'commands',
					'data' => $data
					);
					echo json_encode($return);
					return;
				} else {
					$data = array(array(
					'func' => 'redirectNextStep',
					'args' => array($nextStep),
					));
					$return = array(
					'status' => true,
					'type' => 'commands',
					'data' => $data
					);
					header('Location: '.$nextStep);
					return;
				}
			} else {
				// @todo: error handling
				user_error('Cannot update in DS!', E_USER_ERROR);
			}
		} catch (\PDOException $ev) {
			user_error($ev->errorInfo[1]); //duplicated primary key (Possibel with stringID)
		}
	}

	function processDelete($nextStep = null, $format = 'json') {
		if ($this->delete()) {
			switch ($format) {
			case 'json':
				$return = array(
				'status' => true,
				'type' => 'commands',
				'data' => array(
				array(
				'func' => 'removeHtml',
				),
				array(
				'func' => 'closeLightbox',
				),
				)
				);

				header('Content-type: application/json');
				echo json_encode($return);
				exit;

			case 'html':
				if (empty($nextStep)) {
					header('Location: ' . Main::encodeURL($this->getClass()));
				} else {
					header('Location: ' . $nextStep);
				}
				exit;
			}
		} else {
			// @todo: error handling
			user_error('Cannot delete in DS!', E_USER_ERROR);
		}
	}

	function processSearch() {
		try {
			$this->fillFromRequest();
			$search = new Search(array($this->getClass()));
			return $search->processSearch($this->toArray());
		} catch (\SimplOn\ElementValidationException $ev) {
			user_error($ev->datasValidationMessages());
		}
	}

	/*
function processSelection(){

$parentClass = $_??????['parentClass'];

//-------------------
$jsInstructions = array(
'' => ,
'b' => ,
'c' => ,
'd' => ,
'e' => ,
);
echo json_encode($jsInstructions);
}
*/
	function processCheckBox(){
	return $this->dataStorage->readElements($this, 'array', null, null, null);
	} 

	function processSelect() {
		$this->fillFromRequest();
		$search = new Search(array($this->getClass()));
		// $colums = array_merge( $this->datasWith("list"), array("selectAction","parentClass") );
		//@todo do not add selectAction here but just include it in the listing using VCRSL when adding it on the fly
		$colums = array_merge($this->datasWith("list"), array("selectAction"));
		return $search->processSearch($this->toArray(), $colums);
	}
	
	function processReport($start, $limit = null){
		if ($start < 1) {
			$start = 1;
		}
		$position = ($start - 1) * $limit;
		$this->addOnTheFlyAttribute('SimplOn_count', new Datas\ControlSelect('Count', $this->dataAttributes()));
		$this->addOnTheFlyAttribute('SimplOn_group', new Datas\ControlSelect('Group', $this->dataAttributes()));
		$this->assignDatasName(); 
		$this->fillFromRequest();
		$count = $this->SimplOn_count->val();
		$group = $this->SimplOn_group->val();
		if(isset($count)) {
			$this->SimplOn_count->addCount($count);
		}
		$colums = array_merge($this->datasWith("list"), array("deleteAction", "viewAction", "updateAction"));
		$process = new Report(array($this->getClass()), null, null, $group);
		$tableReport = $process->processRep($this->toArray(), $colums, $position, $limit); 
		$totalRecords = $process->total;
		$links = $this->makePaging($start, $limit, $totalRecords);
		return $tableReport.$links;
	}
 
	function processAdmin($start, $limit = null) {
		if ($start < 1) {
			$start = 1;
		}			
		$position = ($start - 1) * $limit;
		$this->fillFromRequest();
		$search = new Search(array($this->getClass()));
		$admin = $this->encodeURL(array(), 'showAdmin');
		$colums = array_merge($this->datasWith("list"), array("deleteAction", "viewAction", "updateAction"));
		$tableAdmin = $search->processSearch($this->toArray(), $colums, $position, $limit);
		$totalRecords = $search->total;
		$links = $this->makePaging($start, $limit, $totalRecords);
		return $tableAdmin.$links;
	}

	function makePaging($start, $limit, $totalRecords){
		$links = "";
		$totalElements = $totalRecords;
		$division = $limit ? ceil($totalElements / $limit) : 0;
		if ($division > 1) {
			for ($i = 1; $i <= $division; $i++) {
				$links.= "<a class = 'SimplOn_pag' href=\"/$i/$limit\">$i<\a> ";
			}
			$next = $start + 1;
			$prev = $start - 1;
			if ($start > '1') {
				$links = "<a class = 'SimplOn_pag' href=\"/$prev/$limit\">Prev<\a> " . $links;
			}
			if ($next < $i) {
				$links.= "<a class = 'SimplOn_pag' href=\"/$next/$limit\">Next<\a> ";
			}
		}
		return $links;
	}

	public function defaultFilterCriteria($operator = 'AND') {
		//@todo: make a function that returns the data with a specific VCRSL flag ON or OFF
		$searchables = array();
		foreach ($this->dataAttributes() as $dataName) {
			if ($this->{'O' . $dataName}()->search() && $this->{'O' . $dataName}()->fetch() && ($this->$dataName() !== null && $this->$dataName() !== '')) {
				$searchables[] = ' (.' . $dataName . ') ';
			}
		}
		return implode($operator, $searchables);
	}

	/**
* ????????????????????
*
* Possible labels:
* 	name to refer to a data name;
* 	.name to refer to a data filterCriteria;
*  :name to refer to a data value;
* 	"values" to specify a hard-coded value.
*/
	public function filterCriteria($filterCriteria = null) {
		if (isset($filterCriteria))
		$this->filterCriteria = $filterCriteria;
		else {

			//REMOVED so it adapts on every run if necesary
			if (!isset($this->filterCriteria))
			$this->filterCriteria = $this->defaultFilterCriteria();

			//$filterCriteria = $this->filterCriteria;

			$patterns = array();
			$subs = array();
			foreach ($this->dataAttributes() as $dataName) {
				// Regexp thanks to Jens: http://stackoverflow.com/questions/6462578/alternative-to-regex-match-all-instances-not-inside-quotes/6464500#6464500
				$fc = $this->{'O' . $dataName}()->filterCriteria();
				if (!empty($fc)) {
					$patterns[] = '/(\.' . $dataName . ')(?=([^"\\\\]*(\\\\.|"([^"\\\\]*\\\\.)*[^"\\\\]*"))*[^"]*$)/';
					$subs[] = $fc;
				}
			}

			//$ret = preg_replace($patterns, $subs, $filterCriteria);
			return preg_replace($patterns, $subs, $this->filterCriteria);
		}
	}

	public function deleteCriteria($deleteCriteria = null) {
		if (isset($deleteCriteria))
		$this->deleteCriteria = $deleteCriteria;
		else {

			//REMOVED so it adapts on every run if necesary
			if (!isset($this->deleteCriteria))
			$this->deleteCriteria = $this->defaultDeleteCriteria();

			//$filterCriteria = $this->filterCriteria;

			$patterns = array();
			$subs = array();
			foreach ($this->dataAttributes() as $dataName) {
				// Regexp thanks to Jens: http://stackoverflow.com/questions/6462578/alternative-to-regex-match-all-instances-not-inside-quotes/6464500#6464500
				$fc = $this->{'O' . $dataName}()->filterCriteria();
				if (!empty($fc)) {
					$patterns[] = '/(\.' . $dataName . ')(?=([^"\\\\]*(\\\\.|"([^"\\\\]*\\\\.)*[^"\\\\]*"))*[^"]*$)/';
					$subs[] = $fc;
				}
			}

			//$ret = preg_replace($patterns, $subs, $filterCriteria);
			return preg_replace($patterns, $subs, $this->deleteCriteria);
		}
	}

	public function defaultDeleteCriteria($operator = 'AND') {
		//@todo: make a function that returns the data with a specific VCRSL flag ON or OFF
		$searchables = array();
		foreach ($this->dataAttributes() as $dataName) {
			if ($this->{'O' . $dataName}()->fetch() && ($this->$dataName() !== null && $this->$dataName() !== '')) {
				$searchables[] = ' (.' . $dataName . ') ';
			}
		}
		return implode($operator, $searchables);
	}

	/**
* Sets the current instance the as "logical" parent of the Datas.
* Thus the datas may access other element's datas and methods if requeired
* Comments: This is useful in many circumstances for example it enables the existence of ComplexData.
* @see ComplexData
*/
	public function assignAsDatasParent(&$parent = null) {
		if (!isset($parent))
		$parent = $this;

		foreach ($this as $data) {
			if ($data instanceof Data) {
				if ($data->hasMethod('parent')) {
					$data->parent($parent);
				}
			}
		}
	}

	/**
* Sets each Data it’s attribute name within the element instance.
*
* Comment: Usefull to the generate and handle the filtercriteria
*/
	public function assignDatasName() {
		foreach ($this as $name => $data) {
			if (($data instanceof Data) && !$data->name()) {
				$data->name($name);
			}
		}
	}

	//----- Display

	/**
* Default method that will be shown in case no methods have been specified.
*/
	public function index() {
		if (count(Main::$construct_params)) {
			return $this->showView();
		} else {
			return $this->showAdmin();
		}
	}

	public function showCreate($template_file = null, $action = null, $parentClass = null) {
		return $this->obtainHtml(__FUNCTION__, $template_file, $action);
	}

	/* */

	public function showUpdate($template_file = null, $action = null) {
		return $this->obtainHtml(__FUNCTION__, $template_file, $action);
	}

	public function showView($template_file = null, $partOnly = false) {
		return $this->obtainHtml(__FUNCTION__, $template_file, null, null, $partOnly);
	}

	public function showDelete($template_file = null, $action = null) {
		return $this->obtainHtml(__FUNCTION__, $template_file, $action, array(
		'header' => '<p>Following element is going to be removed:' . $this->showView(null, true) . '</p>',
		'footer' => '<p class="warning">This operation cannot be undone!</p>',
		));
	}

	public function callDataMethod($dataName, $method, array $params = array()) {
		return call_user_func_array(array($this->{'O' . $dataName}(), $method), $params);
	}

	public function showSearch($template_file = null, $action = null) {
		return $this->obtainHtml(__FUNCTION__, $template_file, $this->encodeURL(array(), 'showSearch'), array('footer' => $this->processAdmin()));
	}

	public function addOnTheFlyAttribute($attributeName, $attribute = null) {
		Main::addOnTheFlyAttribute($this->getClass(), $attributeName, $attribute);
		$this->$attributeName = $attribute;
		if ($attribute instanceof Data) {
			if (is_array($this->dataAttributes)) {
				$this->dataAttributes[] = $attributeName;
			} else {
				$this->dataAttributes = $this->attributesTypes();
			}
		}
		$this->$attributeName->parent($this);

		return $this;
	}

	public function addOnTheFlyAttributes() {
		foreach (Main::getOnTheFlyAttributes($this->getClass()) as $attributeName => $attribute) {
			$this->$attributeName = clone $attribute;
			if ($attribute instanceof Data) {
				if (is_array($this->dataAttributes)) {
					$this->dataAttributes[] = $attributeName;
				} else {
					$this->dataAttributes = $this->attributesTypes();
				}
			}
		}
	}

	public function clearValues($clearID = false) {
		if (!$clearID) {
			$id = $this->getId();
		}
		foreach ($this->dataAttributes() as $dataName) {
			$this->{'O' . $dataName}()->clearValue();
		}
		$this->setId($id);
	}

	public function clearId() {
		$this->{$this->field_id()}->clearValue();
	}

	public function showSelect($template_file = null, $action = null, $previewTemplate = null, $sid = null) {
		if ($previewTemplate && $sid) {
			$this->addOnTheFlyAttribute('previewTemplate', new Datas\Hidden(null, 'CUSf', $previewTemplate, ''));
			$this->addOnTheFlyAttribute('sid', new Datas\Hidden(null, 'CUSf', $sid, ''));
		}

		return $this->obtainHtml("showSearch", $template_file, $this->encodeURL(array(), 'showSelect'), array('footer' => $this->processSelect(null, 'multi')));
	}

	public function showAdmin($start = 0, $limit = null, $template_file = null, $add_html = array(), $partOnly = false) {
		if (!isset($limit)) {
			$limit = Main::$LIMIT_ELEMENTS;
		}
		$header = '<h1>' . $this->getClass() . '</h1>'
		. '<div id="SimplOn-create-new" class="SimplOn section">' . $this->createAction('', array('Create new %s', 'getClassName')) . '</div>'
		. '<div id="SimplOn-list" class="SimplOn section">' . $this->obtainHtml(
		"showSearch", null, $this->encodeURL(array(), 'showAdmin'), array('footer' => $this->processAdmin($start, $limit)), 'body'
		) . '</div>'
		;
		return $this->obtainHtml(
		"showAdmin", $template_file, null, array_merge($add_html, array('header' => $header)), $partOnly
		);
	}
	
	public function showReport($start = 0, $limit = null, $template_file = null, $add_html = array(), $partOnly = false) {
		if (!isset($limit)) {
			$limit = Main::$LIMIT_ELEMENTS;
		}
		$header = '<h1>'.$this->getClass().'</h1>'
		.'<div id="SimplOn-create-new" class="SimplOn section">'.$this->createAction('', array('Create new %s','getClassName')).'</div>'
		.'<div id="SimplOn-list" class="SimplOn section">'.$this->obtainHtml(
		"showSearch", null, $this->encodeURL(array(),'showReport'), array('footer' => $this->processReport($start, $limit)), 'body'
		).'</div>'
		;
		return $this->obtainHtml(
		"showReport", $template_file, null, array_merge($add_html, array('header' => $header)), $partOnly
		);
	}  
 


	public function obtainHtml($caller_method, $template = null, $action = null, $add_html = array(), $partOnly = false) {

		//$caller_method = end(// explode('::',$caller_method));
		if (strpos($caller_method, 'show') === false) {
			$vcsl = $VCSL = $caller_method;
			$with_form = false;
		} else {
			$VCSL = substr($caller_method, strlen('show'));
			$vcsl = strtolower($VCSL);
			$with_form = in_array($vcsl, $this->formMethods);
		}

		$overwrite_template = Main::$OVERWRITE_LAYOUT_TEMPLATES;
		if (empty($template)) {
			// get default path
			$template_file = $this->templateFilePath($VCSL);

			$template = file_exists($template_file) ? \phpQuery::newDocumentFileHTML($template_file) : '';
		} else if (file_exists($template)) {
			$template_file = $template;
			$template = \phpQuery::newDocumentFileHTML($template);
		} else if (Main::hasNoHtmlTags($template)) {
			$template_file = $template;
			$template = '';
		} else {
			// is an html snippet
			$template = \phpQuery::newDocument($template);
			$overwrite_template = false;
		}

		if (empty($template) OR $overwrite_template) {
			if (empty($template_file))
			$template_file = $this->templateFilePath($VCSL);

			$dom = \phpQuery::newDocumentFileHTML(Main::$MASTER_TEMPLATE);
			$dom['head']->append(
			$this->getCSS($caller_method, 'html') .
			$this->getJS($caller_method, 'html')
			);

			foreach ($this->attributesTypes('\\SimplOn\\Datas\\File') as $fileData) {
				if ($fileData->$vcsl()) {
					$enctype = ' enctype="multipart/form-data" ';
					break;
				}
			}

			// create and fill file
			$html = '';
			if ($with_form) {
				$html.= '<form class="' . $this->htmlClasses($vcsl) . '" '
				. ' action="' . htmlentities(@$action ? : $this->encodeURL(Main::$construct_params, 'process' . $VCSL) ) . '" '
				. ' method="post" '
				. @$enctype
				. '><fieldset><legend>' . $VCSL . ' ' . $this->getClassName() . '</legend>';
			}
			$html.= '<div class="' . $this->htmlClasses() . '">';
			foreach ($this as $keydata => $data) {
				if ($data instanceof Data && $data->hasMethod($vcsl) && $data->$vcsl()) {
					$html.= '<div class="' . $data->htmlClasses() . '">';

					if ($with_form) {
						$data_id = 'SimplOn_' . $data->instanceId();
						$dompart = \phpQuery::newDocumentHTML($data->$caller_method());
						// @todo: Document that class input is MANDATORY
						$dompart['.reference']->attr('ref', $data_id);
						$dompart['.input']->attr('id', $data_id);
						$html.= $dompart;
					} else {
						$html.= $data->$caller_method();
					}

					$html.= '</div>';
				}
			}
			if ($with_form) {
				$html.= '<button type="submit">' . $VCSL . '</button>'
				. '<button class="SimplOn cancel-form">Cancel</button>'
				. '</div></fieldset></form>';
			} else {
				$html.= '</div>';
			}
			$dom['body'] = $html;

			// save file
			if (!empty($template_file))
			Main::createFile($template_file, $dom . '');
		} else {
			// opens file
			$dom = $template;
		}
		/**
* @todo change the way HTL is filled instead of cicle triugh the datas
* and filling the template cicle trough the template and run elment's
* or data's methods as required.
*/
		if ($with_form && $action) {
			$dom['form.SimplOn.Element.' . $this->getClassName()]->attr('action', $action);
		}
		foreach ($dom['.SimplOn.Data.SNL-' . $this->nestingLevel()] as $node) {
			$domNode = pq($node);
			$data = explode(' ', $domNode->attr('class'));
			if (!isset($data[4])) {
				$vladu = $domNode . '';
			}
                        if(isset($data[4]) && $data[4]){
				$data = $this->{'O' . $data[4]}();
				if ($data instanceof Data && $data->hasMethod($caller_method))
				$domNode->html($data->$caller_method($domNode) ? : '');
			}
		}

		$dom['body']
		->prepend(@$add_html['header']? : '')
		->append(@$add_html['footer']? : '');

		/*
switch($partOnly) {
case 0:
case false:
return $dom;

case 1:
case true:
case 'element':
return $dom[$this->cssSelector()];

default:
return $dom[$partOnly];
} */
		if (!$partOnly) {
			return $dom;
		} else if ($partOnly === true OR $partOnly == 'element') {
			return $dom[$this->cssSelector()];
		} else {
			return $dom[$partOnly];
		}
	}

	public function getJS($method, $returnFormat = 'array', $compress = false) {
		$class = $this->getClass('-');

		// gets class' js file
		$a_js = ($local_js = JS::getPath("$class.js")) ? array($local_js) : array();

		// gets method's js file
		if ($local_js = JS::getPath("$class.$method.js"))
		$a_js[] = $local_js;

		// adds

		foreach ($this->dataAttributes() as $data)
		foreach ($this->{'O' . $data}()->getJS($method) as $local_js)
		if ($local_js)
		$a_js[] = $local_js;

		sort($a_js);
		// includes libs
		$a_js = array_unique(array_merge(JS::getLibs(), $a_js));

		if ($compress) {
			// @todo: compress in one file and return the file path
		}

		// converts to remote paths
		$a_js = array_unique(array_map(array('\\SimplOn\\Main', 'localToRemotePath'), $a_js));
		switch ($returnFormat) {
		case 'html':
			$html_js = '';
			foreach ($a_js as $js) {
				$html_js.= '<script type="text/javascript" src="' . $js . '"></script>' . "\n";
			}
			return $html_js;
		default:
		case 'array':
			return $a_js;
		}
	}

	public function getCSS($method, $returnFormat = 'array', $compress = false) {

                $class = $this->getClassName();
		// gets component's css file
		$local_css = CSS::getPath("$class.$method.css");

		$a_css = $local_css ? array($local_css) : array();

		// adds
		foreach ($this->dataAttributes() as $data)
		foreach ($this->{'O' . $data}()->getCSS($method) as $local_css)
		if ($local_css)
		$a_css[] = $local_css;

		$a_css = array_unique($a_css);
		sort($a_css);
		// includes libs
		$a_css = array_unique(array_merge($a_css, CSS::getLibs()));
		if ($compress) {
			// @todo: compress in one file and return the file path
		}
		// converts to remote paths
		$a_css = array_map(array('\\SimplOn\\Main', 'localToRemotePath'), $a_css);
		switch ($returnFormat) {
		case 'html':
			$html_css = '';
			foreach ($a_css as $css) {
				$html_css.= '<link type="text/css" rel="stylesheet" href="' . $css . '" />' . "\n";
			}
			return $html_css;
		default:
		case 'array':
			return $a_css;
		}
	}

	function showMultiPicker() {
		return Main::$DEFAULT_RENDERER->table(array($this->toArray()));
	}

	function getId() {
		//user_error($this->field_id());
		return $this->{$this->field_id()}();
	}

	function setId($id) {
		$this->{$this->field_id()}($id);
		return $this;
	}

	//------------------------------- ????

	function encodeURL(array $construct_params = array(), $method = null, array $method_params = array()) {
		if (empty($construct_params) && $this->getId()) {
			$construct_params = array($this->getId());
		}
		return Main::encodeURL($this->getClass(), $construct_params, $method, $method_params);
	}

	public function templateFilePath($show_type, $alternative = '', $short = false, $template_type = 'html') {
		return ($this->parent) ? $this->parent->templateFilePath($show_type, $alternative, $short, $template_type) : ($short ? '' : Main::$GENERIC_TEMPLATES_PATH) . '/' . $show_type . '/' . $this->getClassName() . $alternative . '.' . $template_type;
		;
	}

	/**
* Returns an array representation of the Element assigning each Data's name
* as the key and the data's value as the value.
*
* @return array
*/
	public function toArray() {
		foreach ($this->dataAttributes() as $dataName) {
			$ret[$dataName] = $this->$dataName();
		}
		return $ret;
	}

	/**
* Applies a method to all the Datas and returns an array containing all the responses.
*
* @param string $method must be a method common to all datas
*/
	public function processData($method) {
		$return = array();
		foreach ($this->dataAttributes() as $dataName) {
			if (isset($this->$dataName)) {
				$r = $this->$dataName->$method();
				if (isset($r))
				$return[] = $r;
			}
		}

		// @todo: verify if it can stay this way
		return $return;
	}

	public function nestingLevel($nestingLevel = null) {
		if (isset($nestingLevel)) {
			$this->nestingLevel = $nestingLevel;
			foreach ($this->datasWith('parent') as $container) {
				$this->{'O' . $container}()->parent($this);
			}
			return $this;
		} else {
			return $this->nestingLevel;
		}
	}

	//------------------------------- Performance

	function dataAttributes() {
		if (!$this->dataAttributes) {
			$this->dataAttributes = $this->attributesTypes();
		}
		return $this->dataAttributes;
	}

	// @todo: change name to attributesOfType
	function attributesTypes($type = '\\SimplOn\\Datas\\Data') {
		foreach ($this as $name => $data) {
			if ($data instanceof $type) {
				$a[] = $name;
			}
		}
		return @$a ? : array();
	}

	// @todo: change name to attributesOfType
	function attributesTypesWith($type = '\\SimplOn\\Datas\\Data', $what = 'fetch') {
		foreach ($this as $name => $data) {
			if ($data instanceof $type && $this->$name->$what()) {
				$a[] = $name;
			}
		}
		return @$a ? : array();
	}

	//vcsrl
	public function datasWith($what) {
		$output = array();
		foreach ($this->dataAttributes() as $data) {
			if ($this->$data->$what()) {
				$output[] = $data;
			}
		}
		return $output;
	}
        
	function allow($validator, $method) {

		$validatorClass = '\\' . Main::$PERMISSIONS;
		$validatorObject = new $validatorClass($validator);
                $rolesArray = $validatorObject->role();
                $roleNameHierarchy = array();
                foreach ($rolesArray as $key){
                    $roleNameHierarchy[$key->level()] = $key->name();
                }
                asort($roleNameHierarchy);
                if(isset($_SESSION['simplonUser'])){
                        foreach ($roleNameHierarchy as $value) {
                            if (isset($this->permissions[$value])) {
                                    return $this->isAllowed($this->permissions[$value], $method); 
                            } elseif (isset($this->permissions['default'])){
                                    return $this->isAllowed($this->permissions['default'], $method);
                            } elseif (isset(Main::$DEFAULT_PERMISSIONS[$value])) {
                                    return $this->isAllowed(Main::$DEFAULT_PERMISSIONS[$value], $method);
                            } else {
                                    return false;
                            }
                    }
                } else {
                            return true;
                    }
	}

	function isAllowed($methods, $method) {
		$values = array(
		'a' => array('processAdmin', 'showAdmin'),
		'c' => array('processCreate', 'showCreate'),
		'd' => array('processDelete', 'showDelete'),
		's' => array('processSearch', 'showSearch'),
		'u' => array('processUpdate', 'showUpdate'),
                'v' => array('showView'),
		'i' => array('index'),
                '*' => array('*')
		);
		$allowedMethods = array();
		$deniedMethods = array();
                $allowedElementMethods = array();
		$deniedElementMethods = array();
		if (isset($methods['Allow'])) {
			$flagsMethods = str_split(strtolower(array_shift($methods['Allow'])));
			foreach ($flagsMethods as $key) {
				foreach ($values[$key] as $value) {
					$allowedMethods[] = $value;
				}
			}
			if (isset($methods['Allow'])) {
				foreach ($methods['Allow'] as $value) {
					$allowedElementMethods[] = $value;
				}
			}
		}
		if (isset($methods['Deny'])) {
			$flagsMethods = str_split(strtolower(array_shift($methods['Deny'])));
			foreach ($flagsMethods as $key) {
				foreach ($values[$key] as $value) {
					$deniedMethods[] = $value;
				}
			}
			if (isset($methods['Deny'][1])) {
				foreach ($methods['Deny'] as $value) {
					$deniedElementMethods[] = $value;
				}
			}
		}
                if ( ( in_array('*', $deniedElementMethods) || in_array('*', $deniedMethods)) || (in_array($method, $deniedElementMethods) || in_array($method, $deniedMethods)) ) {
			return false;
		} elseif ( (in_array('*', $allowedElementMethods) || in_array('*', $allowedMethods)) || ( in_array($method, $allowedElementMethods) || in_array($method, $allowedMethods)) ) {
			return true;
		} else {
			return true;
		}
	}
}