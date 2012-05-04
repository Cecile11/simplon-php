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
use \SimplOn\Main;


/**
 * 
 * Encapsulates an Element so it acts as a Data. 
 * 
 * @author RSL
 */
class ElementContainer extends Data {
	protected 
		/**
		 * Logic parent
		 * @var SimplOn\Elements\Element
		 */
		$parent, 
            
		/**
		 * Encapsulated element
		 * @var SimplOn\Elements\Element
		 */
		$element;
	
	public function __construct( \SimplOn\Elements\Element $element, $label=null, $flags=null, $element_id=null) {
		
		$this->element($element);
        
		parent::__construct($label,$flags,$element_id);
        
	}
	
	public function getJS($method) {
		return array_map(
			function($fp) {
				return str_replace(Main::$REMOTE_ROOT, Main::$LOCAL_ROOT, $fp);
			},
			$this->element->getJS($method)
		);
	}
    
    function parent(&$parent = null){
        if(!$parent){
            return $this->parent;
        } else {
            $this->parent = $parent;
            $this->element->parent($parent);
        }
    }
    
	
	function showView($template = null)
	{
        if($template) {
            $template = Main::loadDom($template);
            $template = $this->element->showView($template[$this->cssSelector().' '.$this->element->cssSelector()],true);
        } else {
           // creates a dummy template
           $element = $this->element->getClass();
           $element = new $element($this->element->getId());
           $template = $element->showView(null, true);
        }
        $template=$template.'';
        $dom = \phpQuery::newDocument($template);

        if(@$element) {
            $this->nestingLevelFix($dom);
        }
        
        return $dom.'';
	}
	
	public function val($val = null) {
		if($val === '') {
			$class = $this->element->getClass();
			$this->element = new $class;
		} else	if($val !== null) {
			$this->element->fillFromDSById($val);
		} else {
			return @$this->element->getId();
		}

        $this->element->addOnTheFlyAttribute('parentClass' , new Hidden(null,'CUSf', $this->parent->getClassName(), '' )    );
        $this->element->addOnTheFlyAttribute('dataName' , new Hidden(null,'CUSf', $this->name(), '' )    );
        $this->element->addOnTheFlyAttribute('parentId' , new Hidden(null,'CUSf', $this->parent->getId(), '' )    );

        $this->element->addOnTheFlyAttribute('selectAction' , new SelectAction('', array('Select')) );
    
	}
	
	function showInput($fill)
	{
        $nextStep = $this->encodeURL('makeSelection');
        $addHref = htmlentities(
			$this->element->encodeURL(
				array(),
				'showCreate',
				array(
					'',
					$this->element->encodeURL(
						array(),
						'processCreate',
						array($nextStep)
					)
				)
			)
		);
        return  '
            <span class="SimplOn label">'.$this->label().'</span>:
			<a class="SimplOn lightbox" href="'.htmlentities($this->encodeURL('showSelect')).'">List</a>
            <a class="SimplOn lightbox" href="'.$addHref.'">Add</a>
            <div class="SimplOn preview">
                '.$this->showInputView().'
            </div>
            <input class="SimplOn input" name="'.$this->name().'" type="hidden" value="'.($fill?$this->val():'').'" />
		';
	}
    
	public function showInputView()
	{
        $template=$this->parent->templateFilePath('View');
        if( !file_exists($template) ){
            $this->parent->showView();
        }
        
        if($this->element->getId()){
            $nextStep = $this->encodeURL('makeSelection', array($this->element->getId()));
            $href = htmlentities(
					$this->element->encodeURL(
							array(),
							'showUpdate',
							array(
								'',
								$this->element->encodeURL(
										array(),
										'processUpdate',
										array($nextStep)
								)
							)
					)
			);
			return '<div class="SimplOn actions">
                        <a class="SimplOn lightbox" href="'.$href.'">Edit</a>
                        <a class="SimplOn delete" href="#">X</a>
                    </div>
                    <div class="SimplOn view">'.$this->element->showView($template, true).'</div>
            ';
        }else{
            return '';
        }
	}

    
  	public function showSelect()
	{
        $element = $this->element->getClass();
        $element = new $element();
        $element->fillFromRequest();
        
        $element->addOnTheFlyAttribute('parentClass' , new Hidden(null,'CUSf', $this->parent->getClassName(), '' )    );
        $element->addOnTheFlyAttribute('dataName' , new Hidden(null,'CUSf', $this->name(), '' )    );
        $element->addOnTheFlyAttribute('parentId' , new Hidden(null,'CUSf', $this->parent->getId(), '' )    );
        $element->addOnTheFlyAttribute('selectAction' , new SelectAction('', array('Select')) );
        // http://localhost/SimplON/sample_site/Fe         /2       |callDataMethod/"home    "/"makeSelection"
        // http://localhost/SimplON/sample_site/parentClass/parentId|callDataMethod/"dataName"/"makeSelection"
   
        return $element->obtainHtml(
                "showSearch", 
                $element->templateFilePath('Search'), 
                $this->encodeURL('showSelect'),
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

    function makeSelection($id){ 
        /*@var parentElement /SimplOn/Elements/Element */
        //$orig_sid = Main::$globalSID;
       
        $this->element->fillFromDSById($id);
        //$parentElement = new $parentClass();
        //Main::$globalSID = $orig_sid;
        
        
        //$template = $parentElement->templateFilePath('View');
        
        $return = array(
			'status' => true,
			'type' => 'commands',
			'data' => array(
                array(
                    'func' => 'changeValue',
                    'args' => array($this->element->getId())
                ),
                array(
                    'func' => 'changePreview',
                    //'args' => array($this->showInputView(Main::$GENERIC_TEMPLATES_PATH . $short_template, true).'')
                    'args' => array($this->showInputView())
                ),
                array(
                    'func' => 'closeLightbox'
                ),
            )
        );
        
        header('Content-type: application/json');
        echo json_encode($return);

        
    }
}