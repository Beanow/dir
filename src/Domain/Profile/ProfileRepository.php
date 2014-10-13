<?php namespace Friendica\Directory\Domain\Profile;

use Friendica\Directory\Domain\AbstractDatabaseRepository;

/**
 * This class takes care of Profile related database queries.
 * If your query is not here, please add it instead of using a query elsewhere.
 */
class ProfileRepository extends AbstractDatabaseRepository
{
	
	/**
	 * Count the amount of profiles that require maintenance.
	 * @param integer $maxAge The maximum age in seconds that a profile may have before maintenance.
	 * @return integer? Null if the count is unknown. Otherwise an integer.
	 */
	public function getMaintenanceBacklog($maxAge)
	{
		
		$res = $this->db->q(sprintf(
			"SELECT count(*) as `count` FROM `profile` WHERE `updated` < '%s'",
	    $this->db->escape(date('Y-m-d H:i:s', time()-abs($maxAge)))
	  ));
		
		return count($res) ? $res[0]['count'] : null;
		
	}
	
	/**
	 * Count the amount of profiles matching a given query, or all profiles.
	 * @param  string?  $query  	  The search terms to use.
	 * @param  boolean? $community  Whether or not to show all (null), communities (true) or users (false).
	 * @return integer
	 */
	public function countProfiles($query=null, $community=null)
	{
		
		//Retrieve a piece of SQL from our helper methods.
		$communityFilter = $this->createCommunityQuery($community);
		$search = $this->createSearchQuery($query);
		
		//Execute the query.
		$res = $this->db->q("SELECT COUNT(*) AS `total` FROM `profile` WHERE `censored` = 0 $communityFilter $search");
		return $res[0]['total'];
		
	}
	
	/**
	 * Gets the profiles for a given query, or all profiles.
	 * @param  string?  $query  			The search terms to use.
	 * @param  boolean? $community 		Whether or not to show all (null), communities (true) or users (false).
	 * @param  boolean  $alphabetical	Whether or not to sort on alphabetical order. False = update order.
	 * @param  integer  $offset 			Offset to use, for pagination.
	 * @param  integer  $limit  			Limit to use, for pagination.
	 * @return array A 2D array of results and their columns.
	 */
	public function findProfiles($query=null, $community=null, $alphabetical=false, $offset=0, $limit=50)
	{
		
		//Retrieve a piece of SQL from our helper methods.
		$communityFilter = $this->createCommunityQuery($community);
		$search = $this->createSearchQuery($query);
		
		//Ordering?
		if(!$alphabetical)
			$order = "ORDER BY `updated` DESC, `id` DESC";
		else
			$order = "ORDER BY `name` ASC";
		
		//Execute the query.
		return $this->db->q(sprintf(
			"SELECT * FROM `profile` WHERE `censored` = 0 $communityFilter $search $order LIMIT %d, %d",
			intval($offset),
			intval($limit)
		));
		
	}
	
	/**
	 * Helper method to create a MATCH ... AGAINST statement from a given search terms string.
	 * @param  string? $query  The search terms to use.
	 * @return string A MATCH ... AGAINST statement safe to use in a query.
	 */
	protected function createSearchQuery($query)
	{
		
		//The default is no query.
		$search = "";
		
		//If a search term is given, create a MATCH ... AGAINST statement.
		if($query)
		{
			
			//Escaping as it was done before.
			$query = $this->db->escape($query.'*');
			$query = str_replace('%','%%',$query);
			
			//Create the statement.
			$search =
				"AND MATCH (`name`, `pdesc`, `homepage`, `locality`, `region`, `country-name`, `gender`, `marital`, `tags` ) ".
				"AGAINST ('$query' IN BOOLEAN MODE)";
			
		}
		
		return $search;
		
	}
	
	/**
	 * Creates a filter for the community property of the profiles.
	 * @param  boolean? $community 	Whether or not to show all (null), communities (true) or users (false).
	 * @return string A statement safe to use in a query.
	 */
	protected function createCommunityQuery($community)
	{
		
		//Null or non booleans create no filter.
		if(is_null($community) || !is_bool($community)){
			return "";
		}
		
		//Otherwise use the boolean.
		return "AND `comm`=".($community ? "'1'" : "'0'");
		
	}
	
}