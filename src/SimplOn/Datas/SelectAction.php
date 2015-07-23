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

class SelectAction extends ElementLink {

	public function __construct($label, array $sources = array(), $flags=null, $searchOp=null){
		parent::__construct($label,$sources, 'makeSelection', array(), $flags,null,$searchOp);
	}

	public function val($sources = null){
            // verify if $sources is not an array if it's true then $sources is stored into $this->sources
            if(!is_array($sources)) $sources = $this->sources;
            // @href save the URL to the method indicated, in this case is makeSelection
            $href = $this->encodeURL('makeSelection', array($this->parent->getId(), $this->parent->getClass()) );
            // $content save $sources with the correct format
            $content = vsprintf(array_shift($sources), $this->sourcesToValues($sources));
            //return the <a> tag with the correct href.
            return Main::$DEFAULT_RENDERER->link($content, $href, array('class'=>$this->htmlClasses()));
	}
	/**
         * function encodeURL - overwrite parent function just to send 2 parameters
         * @param string $method
         * @param array $method_params
         * @return string
         */
	function encodeURL($method = null, array $method_params = array()) {
		return Main::encodeURL($this->parent->parentClass(), array($this->parent->parentId()), $method, $method_params, $this->parent->dataName());
	}
}
