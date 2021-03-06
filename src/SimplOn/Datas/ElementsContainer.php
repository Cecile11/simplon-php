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
namespace SimplOn\Datas;

use SimplOn\Elements\PivotTable;
use \SimplOn\Main;
use \SimplOn\Elements\Element;


/**
 * Encapsulates an Element so it acts as a Data. 
 * 
 * @author RSL
 */
class ElementsContainer extends Data {
	protected 
		$fetch = false,
        
		/**
		 * Logic parent
		 * @var Element
		 */
		$parent, 
            
		/**
		 * List of Elements
		 * @var array of Element
		 */
		$elements = array(),
        
		/**
		 * Pivot element (for pivot tables)
		 * @var Element
		 */
		$pivot,

        /**
         * @var array of Elements instances or names
         */
        $allowedClassesInstances = array();
	
	public function __construct( array $allowedClassesInstances, $label=null, $flags=null, $element_id=null) {
        foreach($allowedClassesInstances as $e) {
            if (is_string($e) && class_exists($e)) {
                $this->allowedClassesInstances[$e] = new $e;
            } else if( $e instanceof Element ) {
                $this->allowedClassesInstances[$e->getClass()] = $e;
            } else {
                // error elements must be an array of valid classes names or Elements
            }
        }

		parent::__construct($label,$flags,$element_id);
	}
	
	public function getJS($method) {
        $a_js = array();
        foreach($this->allowedClassesInstances as $classInstance) {
            /** @var Element $classInstance */
            $a_js = array_merge($classInstance->getJS($method), $a_js);
        }

		return array_map(
			function($fp) {
				return str_replace(Main::$REMOTE_ROOT, Main::$LOCAL_ROOT, $fp);
			},
            $a_js
		);
	}


    /**
     * @param Element $parent
     * @return Element
     */
    function parent(&$parent = null){
        if($parent === null){
            return $this->parent;
        } else {
            $this->parent = $parent;
            
            if(!$this->pivot){
               $this->pivot = new PivotTable(null, 'Pivot_'.strtr($this->parent->getClass(), '\\', '_').'_'.$this->name);
            }
            
            foreach($this->elements as $element){
                $element->parent($parent);
            }
        
            foreach($this->allowedClassesInstances as $classInstance){
                $classInstance->nestingLevel($parent->nestingLevel()+1);
            }
        }
    }
    
	
	function showView($template = null)
	{
        if($template) {
           
           $dom = Main::loadDom($template);
           $tempTemplate = $dom[$this->cssSelector()];
           
           $elementsViews = '';
           foreach($this->elements as $element){
               $selector = $element->cssSelector();
               $tmp = $tempTemplate.'';
               $elementTemplate=$tempTemplate[$element->cssSelector()];
               $elementsViews.= $element->showView($elementTemplate,true);
           }
           
           $tempTemplate->html($elementsViews);
        
            return $tempTemplate->html();
        } else {
           // creates a dummy template
            
           foreach($this->allowedClassesInstances as $classInstance){
               $template.= $classInstance->nestingLevel(1)->showView(null, true);
           }
           $dom = \phpQuery::newDocument($template);
           $this->nestingLevelFix($dom);
           
           return $dom.'';
        }
	}
	
	public function val($val = null) {
		if($val === '') {
			$this->elements = array();
            return $this;
		} else	if(is_array($val)) {
            $this->elements = array();
            foreach($val as $str_or_elm) {
                if(is_string($str_or_elm)) {
                    list($id, $class) = explode('|', $str_or_elm);
                    // @todo: understand why the client sends an array with weirdly repeated elements
                    $this->elements[$str_or_elm] = new $class($id);
                    $this->elements[$str_or_elm]->parent($this->parent);
                } else if($str_or_elm instanceof \SimplOn\Elements\Element) {
                    $str_or_elm->parent($this->parent);
                    $this->elements[] = $str_or_elm;
                }
            }
            return $this;
		} else {
			return $this->elements;
		}
	}

	
	function showInput($fill)
	{
        
        $ret =  ' <span class="SimplOn label">'.$this->label().'</span>: <ul>';
                
        foreach($this->allowedClassesInstances as $classInstance){
            $nextStep = $this->encodeURL('makeSelection');
            $ret.='<li>'.$classInstance->getClassName()
                .' <a class="SimplOn lightbox" href="'.htmlentities($this->encodeURL('showSelect',array($classInstance->getClass()) )).'">List</a> '
                .' <a class="SimplOn lightbox" href="'.htmlentities($classInstance->encodeURL(array(),'showCreate',array( '', $classInstance->encodeURL(array(), 'processCreate', array($nextStep))  ))).'">Add</a> '
                .'</li>'
            ;
        }
        $ret.=  '</ul> ';
        
        $elementsInputViews = array();
        foreach($this->elements as $element) {
            $elementsInputViews[] = $this->showInputView($element);
        }
        $ret .=  '
            <div class="SimplOn Container elements-box">
                '.implode("\n", $elementsInputViews).'
            </div>
		';
        
        return $ret;
	}
    
	public function showInputView($element)
	{
        if($element->getId()){
            $next = $this->encodeURL('makeUpdate', array($element->getId(),$element->getClass()));
            $nextStep = str_replace("\"","", $next);
            $elementTemplate = \phpQuery::newDocument($this->parent->showView());
            $elementTemplate = $elementTemplate[$this->cssSelector().' '.$element->cssSelector().':first'].'';
            $href = htmlentities(
                $element->encodeURL(
                    array(),
                    'showUpdate',
                    array(
                        '',
                        $element->encodeURL(
                            array(),
                            'processUpdate',
                            array($nextStep)
                        )
                    )
                )
            );
            return '
                    <div class="SimplOn preview element-box '.$element->getId().'">
                        <div class="SimplOn actions">
                            <a class="SimplOn lightbox" href="'.$href.'">Edit</a>
                            <a class="SimplOn delete" href="#">X</a>
                        </div>
                        <div class="SimplOn view">'.$element->showView($elementTemplate, true).'</div>
                        <input class="SimplOn input" name="'.$this->name().'[]" type="hidden" value="'.htmlentities($element->getId().'|'.$element->getClass()).'" />
                    </div>
            ';
        }else{
            return '';
        }
	}
    /// use a search element and add the onthefly params to the search element
  	public function showSelect($class = null) {
        $element = new $class();
        $element->fillFromRequest();
        $parent = $this->parent();
        $element->parent($parent);
        
        $element->addOnTheFlyAttribute('parentClass' , new Hidden(null,'CUSf', $this->parent->getClassName(), '' )    );
        $element->addOnTheFlyAttribute('dataName' , new Hidden(null,'CUSf', $this->name(), '' )    );
        $element->addOnTheFlyAttribute('parentId' , new Hidden(null,'CUSf', $this->parent->getId(), '' )    );
        $element->addOnTheFlyAttribute('selectAction' , new SelectAction('', array('Select')) );
   
        return $element->obtainHtml(
                "showSearch", 
                $element->templateFilePath('Search'), 
                $this->encodeURL('showSelect', array($class) ),
                array('footer' => $element->processSelect())
        );
	}
    
    function nestingLevelFix(&$dom) {
        $startingNestingLevel = $this->parent->nestingLevel();
        foreach($dom['.SimplOn.Element, .SimplOn.Data'] as $node) {
            $domNode = pq($node);
            $classes = explode(' ', $domNode->attr('class'));
            if(substr($classes[2], 0, 4) == 'SNL-') {
                $nestingLevel = substr($classes[2], 4) + $startingNestingLevel;
                $classes[2] = 'SNL-' . $nestingLevel;
                $domNode->attr('class', implode(' ', $classes));
            }
        }
    }

    function makeSelection($id, $class){ 
        $element = new $class($id);
        $element->nestingLevel($this->parent->nestingLevel() + 1);
        $return = array(
			'status' => true,
			'type' => 'commands',
			'data' => array(
                array(
                    //'func' => 'appendContainedElement',
                    'func' => 'prependContainedElement',
                    'args' => array($this->showInputView($element))
                ),
                array(
                    'func' => 'closeLightbox'
                ),
            )
        );
        
        header('Content-type: application/json');
        echo json_encode($return);
    }

    function makeUpdate($id, $class){ 
        $element = new $class($id);
        $element->nestingLevel($this->parent->nestingLevel() + 1);
        $return = array(
            'status' => true,
            'type' => 'commands',
            'data' => array(
                array(
                    'func' => 'replaceHtml',
                    'args' => array(
                        $this->showInputView($element),
                        null,
                        null,
                        $id
                        )
                ),
                array(
                    'func' => 'closeLightbox'
                ),
            )
        );
        header('Content-type: application/json');
        echo json_encode($return);
    }    

	public function doRead(){}
	public function postRead(){
        // loads up
        $this->pivot->parentId($this->parent->getId());
        
        //delete all elements in the table
        $array_elements = $this->pivot->dataStorage()->readElements($this->pivot);
        
        $this->elements = array();
        foreach($array_elements as $data) {
            $element = new $data['childClass']($data['childId']);
            $element->parent($this->parent);
            $this->elements[] = $element;
        }
	}
	
	public function doCreate(){}
	public function postCreate(){
        $this->pivot->parentId($this->parent->getId());
        //delete all elements in the table
        $this->pivot->dataStorage()->delete($this->pivot);
        
        // create
        foreach($this->elements as $element) {
            $this->pivot->childId($element->getId());
            $this->pivot->childClass($element->getClass());
            $this->pivot->create();
        }
    }
		
	public function doUpdate(){
        // update
        $this->postCreate();
	}

	public function doSearch(){}
    
}