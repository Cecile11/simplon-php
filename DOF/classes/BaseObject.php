<?php
namespace DOF;

/**
 * Object to add Geters, Seters and other desired functionality to all DOF objects
 *
 * @author RSL
 */
class BaseObject
{
	/**
	 * Returns the object class
	 *
	 * @return string Class of the object
	 */
	public function getClass()
	{
		return get_class($this);
	}
	
	/**
	 * Returns the Methods of the Object
	 *
	 * @return array[int]string
	 */
	public function getMethods()
	{
		return array_merge( get_class_methods($this->getClass()) , array_keys(get_class_vars( $this->getClass() )));
	}

	
	/**
	 * Returns the Keys of the Object attributes.
	 *
	 * @param string $class Filter only attributes instance of $class.
	 *
	 * @return array(int => string)
	 */
	public function getAttributeKeys($class=null)
	{
		foreach($this as $key => $data)
		{
			if($class){
				if($data instanceof $class ){$ret[] = $key;}
			}else{
				$ret[] = $key;
			}
		}
		return $ret;
	}
	
	/**
	 * Checks if a an object has a specific method
	 *
	 * @return boolean
	 */
	public function hasMethod($method)
	{
		return in_array($method,$this->getMethods());
	}

	
	
	/**
	 * Auto Makes the Seters and Geters
	 *
	 * @param $name
	 * @param $arguments
	 */
    public function __call($name, $arguments)
    {
    	//check($name.$arguments[0]);
    	
    	//Get and Set
    	if($this->hasMethod($name))
        {
        	if($arguments){ $this->$name=$arguments[0]; return;}
        	else { return $this->$name; }
        }else{
    	  	//Adds the ability to automaticaly print any 'str' method of a DOF's object by just calling it changing the 'str' by a 'prt'
        	$function=substr($name, 0, 1);
    		$stringAttrib=substr($name, 1);
    		if($function == 'P'){
    			echo call_user_func_array( array($this,$stringAttrib),$arguments );
    		}else{
    			throw new Exception('The method: '.$name.' is not defined in the object: '.$this->getClass());
    		}
        }
    }
}

?>
