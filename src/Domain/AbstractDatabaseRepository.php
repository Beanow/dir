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
	
}