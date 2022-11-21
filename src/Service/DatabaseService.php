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
	private $oDatabaseQueryService;

	public function __construct($sDebugFile = BackgroundTaskExLog::DEBUG_FILE, $oDatabaseQueryService=null)
	{
		BackgroundTaskExLog::Enable($sDebugFile);
		$this->oDatabaseQueryService = (is_null($oDatabaseQueryService)) ? new DatabaseQueryService() : $oDatabaseQueryService;
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
		$iMaxProgress = $this->oDatabaseQueryService->QueryMaxKey($sKey, $sTable);
		$sSqlSearch = $this->oDatabaseQueryService->GetSqlFromOQL($oFilter->ToOQL(true));

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

		if (is_null($sClass) || is_null($sSearchKey) || !is_array($aSqlApply) || is_null($sKey) || is_null($iMaxProgress) || is_null($sSqlSearch)) {
			throw new BackgroundTaskExException("Bad parameters: ".var_export($aRule, true));
		}

		if (!is_null($iMinProgress) && $iProgress < $iMinProgress) {
			//first time only
			$iProgress = $iMinProgress;
		}

		if ($iProgress >= $iMaxProgress) {
			$iProgress = -1;

			return true;
		}

		$iMaxKey = $iProgress + $iChunkSize;
		if ($iMaxProgress < $iMaxKey){
			$iMaxKey = $iMaxProgress;
		}

		$sSqlSearch = "$sSqlSearch AND `$sSearchKey` >= $iProgress AND `$sSearchKey` <= $iMaxKey";

		$bCompleted = $this->oDatabaseQueryService->ExecuteSQLQueriesByChunkWithTempTable($sClass, $sSearchKey, $sSqlSearch, $aSqlApply, $sKey, $iChunkSize);
		if ($bCompleted) {
			$iProgress = -1;
		} else {
			$iProgress = $iMaxKey;
		}

		return $bCompleted;
	}

	public function QueryMaxKey($sKey, $sTable)
	{
		return $this->oDatabaseQueryService->QueryMaxKey($sKey, $sTable);
	}

	public function QueryMinKey($sKey, $sTable)
	{
		return $this->oDatabaseQueryService->QueryMinKey($sKey, $sTable);
	}

}
