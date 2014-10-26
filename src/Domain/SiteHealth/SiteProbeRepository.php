<?php namespace Friendica\Directory\Domain\SiteHealth;

use Friendica\Directory\Domain\AbstractDatabaseRepository;

/**
 * This class takes care of SiteProbe related database queries.
 * If your query is not here, please add it instead of using a query elsewhere.
 */
class SiteProbeRepository extends AbstractDatabaseRepository
{
	
	/**
	 * Gets site probe information by ID.
	 * @param  integer $id
	 * @return array? Null if not found.
	 */
	public function getHealthById($id)
	{
		
		//Get the result.
		$result = $this->db->q(sprintf(
			"SELECT * FROM `site-probe` WHERE `id`=%u",
			intval($id)
		));
		
		if(count($result))
			return $result[0];
		
	}
	
	/**
	 * Creates a new site probe entry.
	 * @param  array $input The array with data to insert into the database.
	 * @return array The resulting entry as is in the database.
	 */
	public function createEntry($input)
	{
		
		//See AbstractDatabaseRepository for this method.
		$query = $this->buildInsertQuery('site-probe', array('id'), $input);
		$this->db->q($query);
		
		//Check for errors.
		if($this->db->getdb()->errno){
			throw new \Exception($this->db->getdb()->error);
		}
		
		$id = $this->db->getdb()->insert_id;
		return $this->getHealthById($id);
		
	}
	
}