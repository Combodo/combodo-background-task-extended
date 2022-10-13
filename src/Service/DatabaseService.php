<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Service;

use CMDBSource;
use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException;
use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskLog;
use Exception;
use MySQLHasGoneAwayException;

class DatabaseService
{

	public function __construct()
	{
		ComplexBackgroundTaskLog::Enable(APPROOT.'log/error.log');
	}

	/**
	 * Search objects to update/delete and execute update/delete of max_chunk_size elements.
	 * Manage a progress value to keep track of the progression (keep the current value of the key).
	 * This method needs to be called repeatedly until it returns true.
	 *
	 * @param string $sSqlSearch SQL request to find the rows to compute (should return a list of keys)
	 * @param array $aSqlApply array to update/delete elements found by $sSqlSearch, don't specify the where close
	 * @param string $sKey primary key of updated table
	 * @param string $sProgress start the search after this value => updated with the last id computed
	 * @param int $iMaxChunkSize limit the size of processed data
	 *
	 * @return bool true if all objects where computed, false if other objects need to be computed later
	 *
	 * @throws \Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public function ExecuteSQLQueriesByChunk(string $sSqlSearch, array $aSqlApply, string $sKey, string &$sProgress, int $iMaxChunkSize): bool
	{
		$sId = $sProgress;
		$sSQL = $sSqlSearch." AND $sKey > $sProgress ORDER BY $sKey LIMIT ".$iMaxChunkSize;
		ComplexBackgroundTaskLog::Debug($sSQL);
		$oResult = CMDBSource::Query($sSQL);

		$aObjects = [];
		if ($oResult->num_rows > 0) {
			while ($oRaw = $oResult->fetch_assoc()) {
				$sId = $oRaw[$sKey];
				$aObjects[] = $sId;
			}
			CMDBSource::Query('START TRANSACTION');
			try {
				foreach ($aSqlApply as $sSqlUpdate) {
					$sSQL = $sSqlUpdate." WHERE `$sKey` IN (".implode(', ', $aObjects).');';
					ComplexBackgroundTaskLog::Debug($sSQL);
					CMDBSource::Query($sSQL);
				}
				CMDBSource::Query('COMMIT');
				// Save progression
				$sProgress = $sId;
			} catch (MySQLHasGoneAwayException $e) {
				// Allow to retry the same set
				CMDBSource::Query('ROLLBACK');
				if ($iMaxChunkSize == 1) {
					// This is hopeless for this entry
					throw new ComplexBackgroundTaskException($e->getMessage());
				}
				throw $e;
			} catch (Exception $e) {
				CMDBSource::Query('ROLLBACK');
				if ($iMaxChunkSize == 1) {
					// Ignore current entries and skip to the next ones
					$sProgress = $sId;
					ComplexBackgroundTaskLog::Error($e->getMessage());

					return false;
				}

				// Try with a reduced set in order to find the entries in error
				throw $e;
			}
			if (count($aObjects) < $iMaxChunkSize) {
				// completed
				$sProgress = -1;

				return true;
			}
		} else {
			$sProgress = -1;

			return true;
		}

		// not completed yet
		return false;
	}
}