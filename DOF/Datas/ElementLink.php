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
namespace DOF\Datas;
use \DOF\Main;

class ElementLink extends ComplexData {
	protected
		$view = false,
		$list = false,
		$required = false;
	
	public function __construct($label, array $sources, $method, array $method_params = array(), $flags=null, $searchOp=null){
		$this->method = $method;
		$this->method_params = $method_params;
		
		parent::__construct($label,$sources,$flags,null,$searchOp);
	}

	public function val($sources = null){
		if(!is_array($sources)) $sources = $this->sources;
		
		$href = $this->encodeURL($this->method, $this->method_params);
		$content = vsprintf(array_shift($sources), $this->sourcesToValues($sources));
		
		return Main::$DEFAULT_RENDERER->link($content, $href, array('class'=>$this->htmlClasses()));
	}
	
	function htmlClasses($append = '', $nestingLevel = null) {
        return parent::htmlClasses('Action '.$append, $nestingLevel);
    }
	
	function cssSelector($append = '', $nestingLevel = null) {
        return parent::cssSelector('.Action'.$append, $nestingLevel);
    }
}
