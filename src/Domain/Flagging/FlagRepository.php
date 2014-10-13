<?php namespace Friendica\Directory\Domain\Flagging;

use Friendica\Directory\Domain\AbstractDatabaseRepository;

/**
 * This class takes care of Flag related database queries.
 * If your query is not here, please add it instead of using a query elsewhere.
 */
class FlagRepository extends AbstractDatabaseRepository
{
	
	/**
	 * Get the most flagged profiles.
	 * @param  integer $limit Max amount of profiles to return.
	 * @return array
	 */
	public function getTopFlaggedProfiles($limit=100)
	{
		
		//Sanitize the limit value.
		$limit = intval($limit);
		
		//Find our profiles.
		return $this->db->q(
			"SELECT
				`flag`.*,
				`profile`.`name`,
				`profile`.`homepage`
			FROM `flag`
				JOIN `profile` ON `flag`.`pid`=`profile`.`id`
			ORDER BY `total` DESC LIMIT $limit"
		);
		
	}
	
}