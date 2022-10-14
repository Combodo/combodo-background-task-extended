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
	const TEMPORARY_TABLE = 'priv_temporary_ids_';

	private $bUseTemporaryTable;

	public function __construct()
	{
		ComplexBackgroundTaskLog::Enable(APPROOT.'log/error.log');
		$this->bUseTemporaryTable = true;
	}

	/**
	 * Search objects to update/delete and execute update/delete of max_chunk_size elements.
	 * Manage a progress value to keep track of the progression (keep the current value of the key).
	 * This method needs to be called repeatedly until it returns true.
	 *
	 * @param string $sSearchKey Key alias returned by the search query
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
	public function ExecuteSQLQueriesByChunk(string $sSearchKey, string $sSqlSearch, array $aSqlApply, string $sKey, string &$sProgress, int $iMaxChunkSize): bool
	{
		if ($this->bUseTemporaryTable) {
			return $this->ExecuteSQLQueriesByChunkWithTempTable($sSearchKey, $sSqlSearch, $aSqlApply, $sKey, $sProgress, $iMaxChunkSize);
		}

		return $this->ExecuteSQLQueriesByChunkWithIn($sSearchKey, $sSqlSearch, $aSqlApply, $sKey, $sProgress, $iMaxChunkSize);
	}

	/**
	 * Search objects to update/delete and execute update/delete of max_chunk_size elements.
	 * Manage a progress value to keep track of the progression (keep the current value of the key).
	 * This method needs to be called repeatedly until it returns true.
	 *
	 * @param string $sSearchKey Key alias returned by the search query
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
	private function ExecuteSQLQueriesByChunkWithTempTable(string $sSearchKey, string $sSqlSearch, array $aSqlApply, string $sKey, string &$sProgress, int $iMaxChunkSize): bool
	{
		$sId = $sProgress;
		CMDBSource::Query('START TRANSACTION');
		try {
			$sTempTable = $this->GetTempTableName();

			$aQueries = $this->BuildQuerySetForTemporaryTable($sSearchKey, $sSqlSearch, $aSqlApply, $sKey, $sProgress, $sTempTable, $iMaxChunkSize);
			foreach ($aQueries['search'] as $sSQL) {
				ComplexBackgroundTaskLog::Debug($sSQL);
				CMDBSource::Query($sSQL);
			}

			$oResult = CMDBSource::Query("SELECT COUNT(*) AS COUNT, MAX(`$sSearchKey`) AS MAX FROM `$sTempTable`");
			$aRow = $oResult->fetch_assoc();
			$iCount = $aRow['COUNT'];
			$sId = $aRow['MAX'];

			if ($iCount > 0) {
				foreach ($aQueries['delete'] as $sSQL) {
					ComplexBackgroundTaskLog::Debug($sSQL);
					CMDBSource::Query($sSQL);
				}
			}
			CMDBSource::Query('COMMIT');
			// Save progression
			$sProgress = $sId;

			if ($iCount < $iMaxChunkSize) {
				// completed
				$sProgress = -1;

				return true;
			}
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

		// not completed yet
		return false;
	}

	protected function GetTempTableName()
	{
		// avoid collisions
		$random = random_bytes(16);
		return static::TEMPORARY_TABLE.bin2hex($random);
	}

	/**
	 * @param string $sSearchKey
	 * @param string $sSqlSearch
	 * @param array $aSqlApply
	 * @param string $sKey
	 * @param string $sProgress
	 * @param string $sTempTable
	 * @param int $iMaxChunkSize
	 *
	 * @return array
	 */
	public function BuildQuerySetForTemporaryTable(string $sSearchKey, string $sSqlSearch, array $aSqlApply, string $sKey, string $sProgress, string $sTempTable, int $iMaxChunkSize): array
	{
		$aRequests = [];
		$aRequests['search'] = [
			"DROP TEMPORARY TABLE IF EXISTS `$sTempTable`",
			"CREATE TEMPORARY TABLE `$sTempTable` ($sSqlSearch AND `$sSearchKey` > $sProgress ORDER BY `$sSearchKey` LIMIT $iMaxChunkSize)",
		];

		$aDeleteQueries = [];
		foreach ($aSqlApply as $sTable => $sSqlUpdate) {
			$sPattern = "/`?$sTable`?/";
			$sReplacement = "`$sTable` INNER JOIN `$sTempTable` ON `$sTable`.`$sKey` = `$sTempTable`.`$sSearchKey`";
			$aDeleteQueries[] = preg_replace($sPattern, $sReplacement, $sSqlUpdate, 1);
		}
		$aDeleteQueries[] = "DROP TEMPORARY TABLE IF EXISTS $sTempTable";
		$aRequests['delete'] = $aDeleteQueries;

		return $aRequests;
	}

	/**
	 * Search objects to update/delete and execute update/delete of max_chunk_size elements.
	 * Manage a progress value to keep track of the progression (keep the current value of the key).
	 * This method needs to be called repeatedly until it returns true.
	 *
	 * @param string $sSearchKey Key alias returned by the search query
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
	private function ExecuteSQLQueriesByChunkWithIn(string $sSearchKey, string $sSqlSearch, array $aSqlApply, string $sKey, string &$sProgress, int $iMaxChunkSize): bool
	{
		$sId = $sProgress;
		$sSQL = $sSqlSearch." AND $sSearchKey > $sProgress ORDER BY $sSearchKey LIMIT ".$iMaxChunkSize;
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

	/**
	 * Return the keys of the objects to process
	 *
	 * @param string $sSqlSearch Search request
	 * @param string $sKey Object key
	 * @param string $sProgress Current progression
	 * @param int $iMaxChunkSize Limit the number of keys to return
	 *
	 * @return array of concern object's key
	 *
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public function GetSelectedKeys(string $sSqlSearch, string $sKey, string &$sProgress, int $iMaxChunkSize): array
	{
		$sSQL = $sSqlSearch." AND $sKey > $sProgress ORDER BY $sKey LIMIT ".$iMaxChunkSize;
		ComplexBackgroundTaskLog::Debug($sSQL);
		$oResult = CMDBSource::Query($sSQL);

		$aObjects = [];
		if ($oResult->num_rows > 0) {
			while ($oRaw = $oResult->fetch_assoc()) {
				$sProgress = $oRaw[$sKey];
				$aObjects[] = $sProgress;
			}
		}

		return $aObjects;
	}

	/**
	 * Count the total of objects to process
	 *
	 * @param string $sSqlSearch Search request
	 *
	 * @return int The number of objects found
	 *
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public function CountSelectedKeys(string $sSqlSearch)
	{
		$sSQL = "SELECT COUNT(*) AS COUNT FROM ($sSqlSearch) AS _chandrila_";
		ComplexBackgroundTaskLog::Debug($sSQL);
		$oResult = CMDBSource::Query($sSQL);
		$aRow = $oResult->fetch_assoc();

		return $aRow['COUNT'];
	}

	/**
	 * @param bool $bUseTemporaryTable
	 */
	public function SetUseTemporaryTable(bool $bUseTemporaryTable)
	{
		$this->bUseTemporaryTable = $bUseTemporaryTable;
	}
}