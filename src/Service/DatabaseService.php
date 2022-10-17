<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\BackgroundTaskEx\Service;

use CMDBSource;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;
use DatabaseProcessRule;
use DBSearch;
use Exception;
use MetaModel;
use MySQLHasGoneAwayException;

class DatabaseService
{
	const TEMPORARY_TABLE = 'priv_temporary_ids_';

	public function __construct()
	{
		BackgroundTaskExLog::Enable(APPROOT.'log/error.log');
	}

	/**
	 * @param \DatabaseProcessRule $oDBProcessRule
	 * @param string $sProgress
	 * @param int $iMaxChunkSize
	 *
	 * @return bool
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public function ExecuteRuleByChunk(DatabaseProcessRule $oDBProcessRule, string &$sProgress, int $iMaxChunkSize): bool
	{
		$aParams = $this->GetParamsForPurgeProcess($oDBProcessRule);

		return $this->ExecuteQueriesByChunk($aParams, $sProgress, $iMaxChunkSize);
	}

	/**
	 * @param \DatabaseProcessRule $oDBProcessRule
	 *
	 * @return array|null
	 * @throws \CoreException
	 */
	public function GetParamsForPurgeProcess(DatabaseProcessRule $oDBProcessRule)
	{
		if ($oDBProcessRule->Get('status') == 'inactive') {
			return null;
		}
		$oFilter = $oDBProcessRule->GetFilter();
		$sMainClass = $oFilter->GetClass();
		$sMainClassAlias = $oFilter->GetClassAlias();

		$aDeleteQueries = [];
		foreach (MetaModel::EnumParentClasses($sMainClass, ENUM_PARENT_CLASSES_ALL) as $sParentClass) {
			$sParentTable = MetaModel::DBGetTable($sParentClass);
			$aDeleteQueries[$sParentTable] = "DELETE `$sParentTable` FROM `$sParentTable` /*JOIN*/";
		}
		$sKey = MetaModel::DBGetKey($sMainClass);

		return [
			'name' => $sMainClass,
			'search_key' => $sMainClassAlias.$sKey,
			'key' => $sKey,
			'search_oql' => $oFilter->ToOQL(true),
			'apply_queries' => $aDeleteQueries,
		];
	}

	/**
	 * @param array $aRule
	 * @param string $sProgress
	 * @param int $iMaxChunkSize
	 *
	 * @return bool
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 * @throws \ConfigException
	 * @throws \CoreException
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	public function ExecuteQueriesByChunk(array $aRule, string &$sProgress, int $iMaxChunkSize): bool
	{
		$sSearchKey = $aRule['search_key'] ?? null;
		$sSqlSearch = $aRule['search_query'] ?? null;
		$sOqlSearch = $aRule['search_oql'] ?? null;
		$aSqlApply = $aRule['apply_queries'] ?? null;
		$sKey = $aRule['key'] ?? null;

		if (is_null($sSearchKey) || !is_array($aSqlApply) || is_null($sKey) || (is_null($sSqlSearch) && is_null($sOqlSearch))) {
			throw new BackgroundTaskExException("Bad parameters: ".var_export($aRule, true));
		}

		if (!is_null($sOqlSearch)) {
			$oFilter = DBSearch::FromOQL($sOqlSearch);

			$aCountAttToLoad = [];
			foreach ($oFilter->GetSelectedClasses() as $sClassAlias => $sClass) {
				$aCountAttToLoad[$sClassAlias] = [];
			}
			$sSqlSearch = $oFilter->MakeSelectQuery([], [], $aCountAttToLoad);
		} else {
			$sSqlSearch = "$sSqlSearch AND `$sSearchKey` > $sProgress ORDER BY `$sSearchKey`";
		}

		return $this->ExecuteSQLQueriesByChunkWithTempTable($sSearchKey, $sSqlSearch, $aSqlApply, $sKey, $sProgress, $iMaxChunkSize);
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
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
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

			$aQueries = $this->BuildQuerySetForTemporaryTable($sSearchKey, $sSqlSearch, $aSqlApply, $sKey, $sTempTable, $iMaxChunkSize);
			foreach ($aQueries['search'] as $sSQL) {
				BackgroundTaskExLog::Debug($sSQL);
				CMDBSource::Query($sSQL);
			}

			$oResult = CMDBSource::Query("SELECT COUNT(*) AS COUNT, MAX(`$sSearchKey`) AS MAX FROM `$sTempTable`");
			$aRow = $oResult->fetch_assoc();
			$iCount = $aRow['COUNT'];
			$sId = $aRow['MAX'];

			if ($iCount > 0) {
				foreach ($aQueries['delete'] as $sSQL) {
					BackgroundTaskExLog::Debug($sSQL);
					CMDBSource::Query($sSQL);
				}
			}
			CMDBSource::Query($aQueries['cleanup']);
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
				throw new BackgroundTaskExException($e->getMessage());
			}
			throw $e;
		} catch (Exception $e) {
			CMDBSource::Query('ROLLBACK');
			if ($iMaxChunkSize == 1) {
				// Ignore current entries and skip to the next ones
				$sProgress = $sId;
				BackgroundTaskExLog::Error($e->getMessage());

				return false;
			}

			// Try with a reduced set in order to find the entries in error
			throw $e;
		}

		// not completed yet
		return false;
	}

	private function GetTempTableName()
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
	 * @param string $sTempTable
	 * @param int $iMaxChunkSize
	 *
	 * @return array
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 */
	public function BuildQuerySetForTemporaryTable(string $sSearchKey, string $sSqlSearch, array $aSqlApply, string $sKey, string $sTempTable, int $iMaxChunkSize): array
	{
		$aRequests = [];
		$aRequests['search'] = [
			"DROP TEMPORARY TABLE IF EXISTS `$sTempTable`",
			"CREATE TEMPORARY TABLE `$sTempTable` ($sSqlSearch LIMIT $iMaxChunkSize)",
		];

		$aApplyQueries = [];
		foreach ($aSqlApply as $sTable => $sSqlUpdate) {
			$sPattern = "@/\*JOIN\*/@";
			$sReplacement = "INNER JOIN `$sTempTable` ON `$sTable`.`$sKey` = `$sTempTable`.`$sSearchKey`";
			$iCount = 0;
			$sQuery = preg_replace($sPattern, $sReplacement, $sSqlUpdate, 1, $iCount);
			if ($iCount == 1) {
				$aApplyQueries[] = $sQuery;
			} else {
				throw new BackgroundTaskExException("DANGER: request $sSqlUpdate is missing /*JOIN*/ for filtering");
			}
		}
		$aRequests['delete'] = $aApplyQueries;
		$aRequests['cleanup'] = "DROP TEMPORARY TABLE IF EXISTS $sTempTable";

		return $aRequests;
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
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	private function ExecuteSQLQueriesByChunkWithIn(string $sSqlSearch, array $aSqlApply, string $sKey, string &$sProgress, int $iMaxChunkSize): bool
	{
		$sId = $sProgress;
		$sSQL = $sSqlSearch." LIMIT ".$iMaxChunkSize;
		BackgroundTaskExLog::Debug($sSQL);
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
					BackgroundTaskExLog::Debug($sSQL);
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
					throw new BackgroundTaskExException($e->getMessage());
				}
				throw $e;
			} catch (Exception $e) {
				CMDBSource::Query('ROLLBACK');
				if ($iMaxChunkSize == 1) {
					// Ignore current entries and skip to the next ones
					$sProgress = $sId;
					BackgroundTaskExLog::Error($e->getMessage());

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
	public function GetSelectedKeys(string $sSqlSearch, string $sKey, string $sProgress, int $iMaxChunkSize): array
	{
		$sSQL = $sSqlSearch." AND $sKey > $sProgress ORDER BY $sKey LIMIT ".$iMaxChunkSize;
		BackgroundTaskExLog::Debug($sSQL);
		$oResult = CMDBSource::Query($sSQL);

		$aObjects = [];
		if ($oResult->num_rows > 0) {
			while ($oRaw = $oResult->fetch_assoc()) {
				$aObjects[] = $oRaw[$sKey];;
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
		BackgroundTaskExLog::Debug($sSQL);
		$oResult = CMDBSource::Query($sSQL);
		$aRow = $oResult->fetch_assoc();

		return $aRow['COUNT'];
	}
}