<?php
use \DOF\Elements\Element, \DOF\Datas;

class Example_ComplexData extends Element
{
	public function construct($id = null, &$specialDataStorage = null) {
	    $this->id = new Datas\Id('Id');
		$this->firstname = new Datas\String('First name');
		$this->lastname = new Datas\String('Last name');
		
		$this->fullname = new Datas\Concat('Full name', array('firstname','lastname'));
	}
}