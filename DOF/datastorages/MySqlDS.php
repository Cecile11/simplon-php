<?php
namespace DOF;

class MySqlDS extends DataStorage
{
	/*@var db MySqlDataBase */
	public $db;

	public function __construct($server,$user,$password,$dataBase)
	{
		$this->db = new MySqlDataBase($server,$user,$password,$dataBase);
	}
	
	//@todo implement ORDER BY in a simple way
	public function getElementsData(&$element,$filters=null,$range='0,500' )
	{
		/*@var element Element*/
		
		if($element instanceof Data)
		{
			$whatToGet = $this->getWhatFromElement($element);
			$fromWhere = $element->repository();
			if(!$filters){ $filters = $this->getFiltersFromElement($element); }else
			if($filters instanceof Filter){ $filters = $this->formatFilters($filters); }
			//if($range){ $range = $this->formatRange($range); }
			 
			return $this->db->queryAsArray( "Select ".$whatToGet." FROM ".$fromWhere." ".(($filters)?" WHERE ".$filters:'').' '.(($range)?" LIMIT ".$range:'') );
			
		}else if(!is_string($where)){
			throw new Exception($this->getClass().'->getElementsData needs a valid DOFelement');
		}
	}
	
	public function formatFilters($filters)
	{
		if($filters instanceof DsEval)
		{
			foreach( $filters->operatorsOperadsArray() as $operator=>$operand )
			{
				$ret[]= $DsEval->firstOperand().' '.$operator.' '.$operand;
			}
			
			return implode($DsEval->boolOperator(),$ret);
			
		}else if($filters instanceof DsBoolOp){
			
			foreach($filters->operands() as $BoolOp)
			{
				$ret[] = $this->formatFilters($BoolOp);
			}
			return implode($filters->boolOperator(),$ret);
			
		}else{
			return $filters;
		}
	}
	

	public function saveElement(&$element)
	{
		if( $element->id() ){
			$this->updateElement($element);
		}else{
			$this->createElement($element);
		}
	}
	
	public function createElement(&$element)
	{
		//check( $this->createQuery($element) );
		$this->db->query( $this->createQuery($element) );
	}
	
	public function updateElement(&$element)
	{
		//check($this->updateQuery($element, $element->Fid()."=".$element->id() ) );
		$this->db->query( $this->updateQuery($element, $element->Fid()."=".$element->id()) );
	}
	
	public function deleteElement(&$element)
	{
		//check($this->deleteQuery($element, $element->Fid()."=".$element->id() ) );
		$this->db->query( $this->deleteQuery($element, $element->Fid()."=".$element->id() ) );
	}


	
	
	public function getElementData(&$element)
	{
		return $this->db->queryAsUniArray( $this->getQuery( $element, $element->Fid()."=".$element->id() )  );
	}
	
	
	
	
	
//--------------SQL dependant
	public function processRange($range)
	{
		return 'LIMIT '.$range[0].', '.$range[1];
	}
	
	public function processConditions($conditions)
	{
		return $conditions;
	}

	
	//@todo implement ORDER by in a simple way
	public function getQuery( &$element,$conditions=null, $range=array(0,100) )
	{
		if($range){ $range = $this->processRange($range); }
		
		if($conditions){ $conditions = $this->processConditions($conditions); }
		
		foreach($element->getDOFDataAttributeKeys() as $dataKey)
		{
			$dataKey = 'F'.$dataKey;
			$selectColumns[] = $element->$dataKey();
		}
		$selectColumns = implode(', ',$selectColumns);
		
		return "Select ".$selectColumns." FROM ".$element->repository()." ".(($conditions)?" WHERE ".$conditions:'').' '.$range ;
	}
	
	public function createQuery( &$element,$conditions=null )
	{
		if($conditions){ $conditions = $element->Fid()."=".$element->id(); }
		
		foreach($element->getDOFDataAttributeKeys() as $dataKey)
		{
			$value = $element->$dataKey();
			
			$objectKey = 'O'.$dataKey;
			
			if( !($element->$objectKey() instanceof Id) ){
				if($this->evalQuotesUse($element->$objectKey()) && $value ){
					$value="'$value'";
					//@todo implement escape quotes to allow the use of single quotes in the string
					//@todo implement anti injection code
				}
				if( $value===0 ){ $value='0'; }else
				if( empty($value) ){ $value='NULL'; }
	
				$fieldKey = 'F'.$dataKey;
				
				$colums[] = $dataKey;
				$values[] = $value;
			}
		}
		$colums = implode(', ',$colums);
		$values = implode(', ',$values);
		
		return "INSERT INTO ".$element->repository().' ('.$colums.') VALUES ('.$values.') ' ;
	}
		
	public function updateQuery( &$element,$conditions=null )
	{
		if($conditions){ $conditions = $element->Fid()."=".$element->id(); }
		
		foreach($element->getDOFDataAttributeKeys() as $dataKey)
		{
			$value = $element->$dataKey();
			
			$objectKey = 'O'.$dataKey;
			
			if( !($element->$objectKey() instanceof Id) ){
				if($this->evalQuotesUse($element->$objectKey()) && $value ){
					$value="'$value'";
					//@todo implement escape quotes to allow the use of single quotes in the string
					//@todo implement anti injection code
				}
				if( $value===0 ){ $value='0'; }else
				if( empty($value) ){ $value='NULL'; }
	
				$fieldKey = 'F'.$dataKey;
				
				$updateValues[] = $element->$fieldKey().'='.$value;
			}
		}
		$updateValues = implode(', ',$updateValues);
		
		return "UPDATE ".$element->repository().' set '.$updateValues.' '.(($conditions)?" WHERE ".$conditions:'') ;
	}

		
	public function deleteQuery( &$element,$conditions=null )
	{
		if(!$conditions){
			foreach($element->getDOFDataAttributeKeys() as $dataKey)
			{
				$value = $element->$dataKey();
				
				$objectKey = 'O'.$dataKey;
				
				//if( !($element->$objectKey() instanceof Id) ){
					if($this->evalQuotesUse($element->$objectKey()) && $value ){
						$value="'$value'";
						//@todo implement escape quotes to allow the use of single quotes in the string
						//@todo implement anti injection code
					}
					if( $value===0 ){ $value='0'; }else
					if( empty($value) ){ $value='NULL'; }
		
					$fieldKey = 'F'.$dataKey;
					
					$conditions.= ' AND '.$element->$fieldKey().'='.$value;
				//}
			}
		}
		
		return "DELETE FROM ".$element->repository().' '.(($conditions)?" WHERE ".$conditions:'') ;
	}
		
	
	
	public function evalQuotesUse(&$data)
	{
		if (  strpos($data->sqlType(),'INT' ) !== false  ){ return false; }else
		if (  strpos($data->sqlType(),'FLOAT' ) !== false  ){ return false; }else
		if (  strpos($data->sqlType(),'DECIMAL' ) !== false  ){ return false; }else
		if (  strpos($data->sqlType(),'DOUBLE' ) !== false  ){ return false; }else
		if (  strpos($data->sqlType(),'INT' ) !== false  ){ return false; }else
		{return true; }
	}
	
	public function isSetElementRepository(&$element) {
		return in_array($element->repository(), $this->db->queryAsUniArray('SHOW TABLES'));
	}
	
	public function isValidElementRepository(&$element) {
		foreach($element as $attribute)
			if(($value instanceof Data) && !in_array($columns ,$this->db->queryAsUniArray('SHOW COLUMNS FROM '.$element->repository()))) {
				return false;
				//$this->db->query('CREATE TABLE `'.$element->repository.'` ()');
			}
	}
	
	public function ensureElementRepository(&$element) {
		if(!$this->isSetElementRepository($element)) {
			// $this->db->query('CREATE SCHEMA `'.$element->repository.'`');
			/*
			$this->db->query('CREATE TABLE `'.$element->repository().'` (
				`idnew_table` INT NOT NULL ,
				`hola` VARCHAR(45) NULL ,
				`banana` POINT NULL ,
				PRIMARY KEY (`idnew_table`) ,
				INDEX `sdfgs` (`hola` ASC, `banana` DESC)
			)');
			*/
		} else if(!$this->isValidElementRepository($element)) {
		
		}
		return true;
	}
}