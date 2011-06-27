<?php
namespace DOF\Datas;

/**
* ID para las tablas
* --- No imprime un label y manda un input hidden.
*
* @version	1.0
* @author	Ruben Schaffer
* @todo
*/
class Id extends Integer
{
	protected
		$view = false,
		$create = false,
		$update = true;
	
	public function showInput($fill)
	{
		if($this->val())
		{
			return "<input name='".$this->field()."'".(($fill)?" value='".$this->val()."'":"")." type='hidden' />";
		} else {
			throw new \DOF\Exception('Cannot show this field with empty value!');
		}
	}
	
	public function label() {}
}