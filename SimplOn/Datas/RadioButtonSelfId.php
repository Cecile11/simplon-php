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

/**
* Hidden para las tablas
* --- No imprime un label y manda un input hidden.
*
* @version	1.0
* @author	Ruben Schaffer
* @todo fix so val retuns the value and only the inputmethod retuns the hidden inpunt
*/
class RadioButtonSelfId extends Integer
{
	protected $options = array();
	protected $showValues = true;
	
	protected $fetch = false;

	protected
		$view = false,
		$create = false,
		$update = true,
		$required = false;	
	
	
	
	public $valudationNotAnOption='The value given is not a valid option';
	//public $valudationNaN = 'This field must be an integer number.';
	
	public function __construct($label=null, $flags=null, $val=null, $filterCriteria=null)
	{
		$this->showValues = false;
		parent::__construct($label, $flags, $val, $filterCriteria);
		$this->fetch = false;
	}	
	
	public function val($val = null){
		if($val){
			if(in_array($val, $this->options)){
				parent::val($val);
			}else{
				throw new \SimplOn\DataValidationException($this->valudationNotAnOption);
			}
		}else{
			return $this->val;
		}
	}
		
	function showView($template = null){

		return $this->showInput();
	}

	function showList($fill = true){
		$value = $this->parent->getId();
		return '<input class="SimplOn input '. $this->getClass() .'" name="'. $this->name() .'"  value="'.$value.'"'.' '.(($fill && $this->val==$value)?' checked="checked"':'').' type="radio" />';
	}	
	
	public function showInput($fill = false)
	{
		$this->options = array($this->parent->getid());
		$data_id = 'SimplOn_'.$this->instanceId();
		$ret=($this->label() ? '<label for="'.$data_id.'">'.$this->label().': </label>' : '');
		foreach($this->options as $key=>$value){
			$ret.=($this->showValues ? $key:'').'<input class="SimplOn input '. $this->getClass() .'" name="'. $this->name() .'"  value="'.$value.'"'.' '.(($fill && $this->val==$value)?' checked="checked"':'').' type="radio" />  ';
		}
		return  $ret; 
	}
}