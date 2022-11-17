<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\BackgroundTaskEx\Service;

use CMDBSource;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExHelper;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;
use DatabaseProcessRule;
use DBSearch;
use Exception;
use MetaModel;
use MySQLHasGoneAwayException;

class DatabaseService
{
	const TEMPORARY_TABLE = 'priv_temporary_ids_';

	private $aSQLUpdateExtensions;

	public function __construct()
	{
		BackgroundTaskExLog::Enable(APPROOT.'log/error.log');
		$this->aSQLUpdateExtensions = null;
		set_time_limit(0);
	}

	/**
	 * @param \DatabaseProcessRule $oDBProcessRule
	 * @param int $sProgress
	 * @param int $iMaxChunkSize
	 *
	 * @return bool
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public function ExecuteRuleByChunk(DatabaseProcessRule $oDBProcessRule, int &$sProgress, int $iMaxChunkSize): bool
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

		$aApplyQueries = [];
		$aClasses = array_merge(MetaModel::EnumParentClasses($sMainClass, ENUM_PARENT_CLASSES_ALL), MetaModel::EnumChildClasses($sMainClass));
		foreach ($aClasses as $sClass) {
			$sDBTable = MetaModel::DBGetTable($sClass);
			$aApplyQueries[$sDBTable] = "DELETE `$sDBTable` FROM `$sDBTable` /*JOIN*/";
		}
		$sKey = MetaModel::DBGetKey($sMainClass);
		$sTable = MetaModel::DBGetTable($sMainClass);
		$iMaxProgress = $this->QueryMaxKey($sKey, $sTable);
		$sSqlSearch = $this->GetSqlFromOQL($oFilter->ToOQL(true));

		return [
			'class'         => $sMainClass,
			'name'          => $sMainClass,
			'search_key'    => $sMainClassAlias.$sKey,
			'key'           => $sKey,
			'search_max_id' => $iMaxProgress,
			'search_query'  => $sSqlSearch,
			'apply_queries' => $aApplyQueries,
		];
	}

	/**
	 * @param array $aRule
	 * @param int $iProgress
	 * @param int $iChunkSize
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
	public function ExecuteQueriesByChunk(array $aRule, int &$iProgress, int $iChunkSize): bool
	{
		$sClass = $aRule['class'] ?? null;
		$sSearchKey = $aRule['search_key'] ?? null;
		$sSqlSearch = $aRule['search_query'] ?? null;
		$aSqlApply = $aRule['apply_queries'] ?? null;
		$sKey = $aRule['key'] ?? null;
		$iMaxProgress = $aRule['search_max_id'] ?? null;
		$iMinProgress = $aRule['search_min_id'] ?? null;

		if (is_null($sClass) || is_null($sSearchKey) || !is_array($aSqlApply) || is_null($sKey) || is_null($sSqlSearch) || is_null($iMaxProgress)) {
			throw new BackgroundTaskExException("Bad parameters: ".var_export($aRule, true));
		}

		if (!is_null($iMinProgress) && $iProgress < $iMinProgress) {
			//first time only
			$iProgress = $iMinProgress;
		}

		if ($iProgress >= $iMaxProgress) {
			$iProgress = -1;
			$bCompleted = true;
		} else {
			$iMaxKey = $iProgress + $iChunkSize;
			$sSqlSearch = "$sSqlSearch AND `$sSearchKey` > $iProgress AND `$sSearchKey` <= $iMaxKey";

			$bCompleted = $this->ExecuteSQLQueriesByChunkWithTempTable($sClass, $sSearchKey, $sSqlSearch, $aSqlApply, $sKey, $iChunkSize);
			if ($bCompleted) {
				$iProgress = -1;
			} else {
				$iProgress = $iMaxKey;
			}
		}

		return $bCompleted;
	}

	/**
	 * Search objects to update/delete and execute update/delete of chunk_size elements.
	 * Manage a progress value to keep track of the progression (keep the current value of the key).
	 * This method needs to be called repeatedly until it returns true.
	 *
	 * @param string $sSearchKey Key alias returned by the search query
	 * @param string $sSqlSearch SQL request to find the rows to compute (should return a list of keys)
	 * @param array $aSqlApply array to update/delete elements found by $sSqlSearch, don't specify the where close
	 * @param string $sKey primary key of updated table
	 * @param int $iChunkSize limit the size of processed data
	 *
	 * @return bool true if all objects where computed, false if other objects need to be computed later
	 *
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	private function ExecuteSQLQueriesByChunkWithTempTable(string $sClass, string $sSearchKey, string $sSqlSearch, array $aSqlApply, string $sKey, int $iChunkSize): bool
	{
		$aExtensions = $this->GetSQLUpdateExtensions();

		BackgroundTaskExLog::Debug('START TRANSACTION');
		CMDBSource::Query('START TRANSACTION');
		try {
			$sTempTable = $this->GetTempTableName();

			$aQueries = $this->BuildQuerySetForTemporaryTable($sSearchKey, $sSqlSearch, $aSqlApply, $sKey, $sTempTable);
			foreach ($aQueries['search'] as $sSQL) {
				BackgroundTaskExLog::Debug($sSQL);
				$fStart = microtime(true);
				CMDBSource::Query($sSQL);
				$this->DebugDuration($fStart, '');
			}

			$oResult = CMDBSource::Query("SELECT COUNT(*) AS COUNT, MAX(`$sSearchKey`) AS MAX FROM `$sTempTable`");
			$aRow = $oResult->fetch_assoc();
			$iCount = $aRow['COUNT'];
			$sId = $aRow['MAX'];

			BackgroundTaskExLog::Debug("Found $iCount entries up to id $sId to process");

			if ($iCount > 0) {
				foreach ($aQueries['apply'] as $sSQL) {
					BackgroundTaskExLog::Debug($sSQL);
					$fStart = microtime(true);
					CMDBSource::Query($sSQL);
					$this->DebugDuration($fStart, '');
				}
			}

			BackgroundTaskExLog::Debug('COMMIT');
			CMDBSource::Query('COMMIT');

			if (count($aExtensions) > 0) {
				$oResult = CMDBSource::Query("SELECT `$sSearchKey` FROM `$sTempTable`");
				$aObjects = [];
				while ($oRaw = $oResult->fetch_assoc()) {
					$sId = $oRaw[$sSearchKey];
					$aObjects[] = $sId;
				}

				if (count($aObjects) > 0) {
					// Inform that modifications have been done on that list of objects
					foreach ($aExtensions as $sExtension) {
						BackgroundTaskExLog::Debug("Call iSQLUpdateExtension $sExtension");
						/** @var \Combodo\iTop\BackgroundTaskEx\Service\iSQLUpdateExtension $oExtension */
						$oExtension = new $sExtension();
						$oExtension->OnSQLUpdate($sClass, $aObjects);
					}
				}
			}

			BackgroundTaskExLog::Debug($aQueries['cleanup']);
			CMDBSource::Query($aQueries['cleanup']);
		}
		catch (MySQLHasGoneAwayException $e) {
			// Allow to retry the same set
			BackgroundTaskExLog::Error('ROLLBACK: '.$e->getMessage());
			CMDBSource::Query('ROLLBACK');
			if ($iChunkSize == 1) {
				// This is hopeless for this entry
				throw new BackgroundTaskExException($e->getMessage());
			}
			throw $e;
		}
		catch (Exception $e) {
			BackgroundTaskExLog::Error('ROLLBACK: '.$e->getMessage());
			CMDBSource::Query('ROLLBACK');
			if ($iChunkSize == 1) {
				// Ignore current entries and skip to the next one
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
	 *
	 * @return array
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 */
	public function BuildQuerySetForTemporaryTable(string $sSearchKey, string $sSqlSearch, array $aSqlApply, string $sKey, string $sTempTable): array
	{
		$aRequests = [];
		$aRequests['search'] = [
			"DROP TEMPORARY TABLE IF EXISTS `$sTempTable`",
			"CREATE TEMPORARY TABLE `$sTempTable` ($sSqlSearch)",
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
		$aRequests['apply'] = $aApplyQueries;
		$aRequests['cleanup'] = "DROP TEMPORARY TABLE IF EXISTS $sTempTable";

		return $aRequests;
	}

	/**
	 * @param $sOqlSearch
	 *
	 * @return string
	 * @throws \ConfigException
	 * @throws \CoreException
	 * @throws \MissingQueryArgument
	 * @throws \OQLException
	 */
	private function GetSqlFromOQL($sOqlSearch): string
	{
		$oFilter = DBSearch::FromOQL($sOqlSearch);

		$aAttToLoad = [];
		foreach ($oFilter->GetSelectedClasses() as $sClassAlias => $sClass) {
			$aAttToLoad[$sClassAlias] = [];
		}

		return $oFilter->MakeSelectQuery([], [], $aAttToLoad);
	}

	public function QueryMaxKey($sKey, $sTable)
	{
		$fStart = microtime(true);
		$oRes = CMDBSource::Query("SELECT COALESCE(MAX(`$sKey`), 0) FROM `$sTable`");
		$aRow = $oRes->fetch_array(MYSQLI_NUM);
		$this->DebugDuration($fStart, "Query max $sKey for $sTable: $aRow[0]");

		return $aRow[0];
	}

	public function QueryMinKey($sKey, $sTable)
	{
		$fStart = microtime(true);
		$oRes = CMDBSource::Query("SELECT COALESCE(MIN(`$sKey`), 0) FROM `$sTable`");
		$aRow = $oRes->fetch_array(MYSQLI_NUM);
		$this->DebugDuration($fStart, "Query min $sKey for $sTable: $aRow[0]");

		return $aRow[0];
	}

	private function DebugDuration($fStart, $sMessage)
	{
		$fDuration = microtime(true) - $fStart;
		BackgroundTaskExLog::Debug(sprintf("$sMessage duration: %.2fs", $fDuration));
	}

	private function GetSQLUpdateExtensions()
	{
		if (is_null($this->aSQLUpdateExtensions)) {
			$oBackgroundTaskExHelper = new BackgroundTaskExHelper();
			$this->aSQLUpdateExtensions = $oBackgroundTaskExHelper->GetClassesForInterface(iSQLUpdateExtension::class);
		}

		return $this->aSQLUpdateExtensions;
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
