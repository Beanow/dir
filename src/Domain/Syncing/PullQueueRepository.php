<?php namespace Friendica\Directory\Domain\Syncing;

use Friendica\Directory\Domain\AbstractDatabaseRepository;

/**
 * This class takes care of PullQueue related database queries.
 * If your query is not here, please add it instead of using a query elsewhere.
 */
class PullQueueRepository extends AbstractDatabaseRepository
{
	
	/**
	 * Gets the backlog for the pulling queue.
	 * @return integer
	 */
	public function getBacklog()
	{
		
		$res = q("SELECT count(*) as `count` FROM `sync-pull-queue`");
		return count($res) ? $res[0]['count'] : 0;
		
	}
	
}