<?php
use \DOF\Elements\Element, \DOF\Datas;

class Home extends Element
{
	public function construct($id = null, &$specialDataStorage = null)
	{
	    $this->id = new Datas\Id('Id');
		$this->cabeza = new Datas\String('Cabeza','VCUSL');
		$this->contenido = new Datas\String('Contenido', 'sL');
		$this->owner = new Datas\ElementContainer(new Person(), 'Owner');
		$this->parasite = new Datas\ElementContainer(new Person(), 'Parasite');
		$this->storage('home');
		
		//$this->filterCriteria('.cabeza OR contenido == "igual" OR contenido ^= "empieza" OR contenido $= "acaba" OR contenido ~= "papas a \"la\" .contenido francesa"');
		
	}
}