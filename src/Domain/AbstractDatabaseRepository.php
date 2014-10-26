<?php namespace Friendica\Directory\Domain;

abstract class AbstractDatabaseRepository
{
	
	protected $db;
	
	/**
	 * Creates a new database repository.
	 * @param mixed $db An override for the database handler. Takes the global DB by default.
	 */
	public function __construct($db = null)
	{
		
		//In case no override was given, use the global one.
		if(!$db){
			global $db;
		}
		
		$this->db = $db;
		
	}
	
	/**
	 * Creates an INSERT statement based on the given parameters.
	 * @param  string $table The table to perform the query on.
	 * @param  array  $pks   A list of primary keys to remove from the statement.
	 * @param  array  $input The input values as associative array.
	 * @return string
	 */
	protected function buildInsertQuery($table, $pks, $input)
	{
		
		//Remove the primary key field, we don't want to insert that.
		foreach($pks as $pk){
			unset($input[$pk]);
		}
		
		//Check the keys for the fields are all ok.
		$fields = array_keys($input);
		foreach($fields as $field){
			if(!is_string($field))
				throw new \InvalidArgumentException("All fields must have string keys.");
		}
		
		//Build field and value strings.
		$db = $this->db;
		$values = array_map(function($value)use($db){
			return $db->escape($value);
		}, $input);
		
		$fieldStr = '`'.implode($fields, '`,`').'`';
		$valueStr = "'".implode($values, "','")."'";
		
		//Write the full query.
		$table = $db->escape($table);
		return sprintf("INSERT INTO `$table` ($fieldStr) VALUES ($valueStr)");
		
	}
	
	/**
	 * Creates an UPDATE statement based on the given parameters.
	 * @param  string $table The table to perform the query on.
	 * @param  array  $pks   A list of primary keys to use as WHERE conditions.
	 * @param  array  $input The input values as associative array.
	 * @return string
	 */
	protected function buildUpdateQuery($table, $pks, $input)
	{
		
		//Remove the primary key field, we don't want to insert that.
		foreach($pks as $pk){
			unset($input[$pk]);
		}
		
		//Build the SET section.
		$setters = array();
		foreach($input as $field => $value){
			
			//Check the keys for the fields are all ok.
			if(!is_string($field))
				throw new \InvalidArgumentException("All fields must have string keys.");
			
			$setters[] = "`$field`='".$this->db->escape($value)."'";
			
		}
		
		$setterStr = implode(',', $setters);
		
		$wheres = array();
		foreach($pks as $field => $value){
			
			//Check the keys for the fields are all ok.
			if(!is_string($field))
				throw new \InvalidArgumentException("All fields must have string keys.");
			
			if($value === null){
				$wheres[] = "`$field`=NULL";
			}
			else{
				$wheres[] = "`$field`='".$this->db->escape($value)."'";
			}
			
		}
		
		$whereStr = implode(',', $wheres);
		
		//Write the full query.
		$table = $this->db->escape($table);
		return sprintf("UPDATE `$table` SET $setterStr WHERE $whereStr");
		
	}
	
	
	
}