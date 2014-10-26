<?php namespace Friendica\Directory\Domain\SiteHealth;

use Friendica\Directory\Domain\AbstractDatabaseRepository;

/**
 * This class takes care of SiteHealth related database queries.
 * If your query is not here, please add it instead of using a query elsewhere.
 */
class SiteHealthRepository extends AbstractDatabaseRepository
{
	
	/**
	 * Gets the site health of one site, based on it's base URL.
	 * @param  string $baseUrl
	 * @return array? Null if not found.
	 */
	public function getHealthByBaseUrl($baseUrl)
	{
		
		$res = $this->db->q(sprintf(
			"SELECT * FROM `site-health` WHERE `base_url`= '%s' ORDER BY `id` ASC LIMIT 1",
			$this->db->escape($baseUrl)
		));
		
		if(count($res)){
			return $res[0];
		}
		
	}
	
	/**
	 * Gets site health information by ID.
	 * @param  integer $id
	 * @return array? Null if not found.
	 */
	public function getHealthById($id)
	{
		
		//Get the result.
		$result = $this->db->q(sprintf(
			"SELECT * FROM `site-health` WHERE `id`=%u",
			intval($id)
		));
		
		if(count($result))
			return $result[0];
		
	}
	
	/**
	 * Creates a new site health entry.
	 * @param  array $input The array with data to insert into the database.
	 * @return array The resulting entry as is in the database.
	 */
	public function createEntry($input)
	{
		
		//See AbstractDatabaseRepository for this method.
		$query = $this->buildInsertQuery('site-health', array('id'), $input);
		$this->db->q($query);
		
		//Check for errors.
		if($this->db->getdb()->errno){
			throw new \Exception($this->db->getdb()->error);
		}
		
		$id = $this->db->getdb()->insert_id;
		return $this->getHealthById($id);
		
	}
	
	public function updateEntry($id, $input)
	{
		
		//See AbstractDatabaseRepository for this method.
		$query = $this->buildUpdateQuery('site-health', array('id'=>$id), $input);
		$this->db->q($query);
		
		//Check for errors.
		if($this->db->getdb()->errno){
			throw new \Exception($this->db->getdb()->error);
		}
		
		$id = $this->db->getdb()->insert_id;
		return $this->getHealthById($id);
		
	}
	
}