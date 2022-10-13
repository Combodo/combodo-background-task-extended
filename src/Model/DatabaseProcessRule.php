<?php
/*
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Model;

use DBObjectSearch;
use MetaModel;

class DatabaseProcessRule
{
	/** @var string */
	private $sName;
	/** var string */
	private $sKey;
	/** @var string */
	private $sSearchQuery;
	/** @var array */
	private $aDeleteQueries;
	/** @var string */
	private $sType;
	/** @var string */
	private $sSearchKey;

	/**
	 * @param string $sName
	 * @param string $sSearchKey key alias returned by the search query
	 * @param string $sSearchQuery Query to find all the ids to update/delete
	 * @param array $aApplyQuery Queries to update the data with the ids found ['table alias' => 'SQL update/delete query']
	 * @param string $sKey real table key
	 */
	public function __construct(string $sName, string $sSearchKey, string $sSearchQuery, array $aApplyQuery, string $sKey)
	{
		$this->sName = $sName;
		$this->sType = 'SQL';
		$this->sSearchQuery = $sSearchQuery;
		$this->aDeleteQueries = $aApplyQuery;
		$this->sKey = $sKey;
		$this->sSearchKey = $sSearchKey;
	}

	public function Toarray()
	{
		return [
			'name' => $this->GetName(),
			'search_key' => $this->GetSearchKey(),
			'key' => $this->GetKey(),
			'search_query' => $this->GetSearchQuery(),
			'delete_queries' => $this->GetApplyQueries(),
		];
	}

	public static function FromArray(array $aParams)
	{
		return new DatabaseProcessRule($aParams['name'], $aParams['search_key'], $aParams['search_query'], $aParams['delete_queries'], $aParams['key']);
	}

	public static function GetPurgeRuleFromOQL(string $sOQL, $aArgs = [])
	{
		$oFilter = DBObjectSearch::FromOQL($sOQL);
		$aCountAttToLoad = [];
		$sMainClass = null;
		$sMainClassAlias = null;
		foreach ($oFilter->GetSelectedClasses() as $sClassAlias => $sClass)
		{
			$aCountAttToLoad[$sClassAlias] = [];
			if (empty($sMainClass))
			{
				$sMainClassAlias = $sClassAlias;
				$sMainClass = $sClass;
			}
		}
		$sSearchQuery = $oFilter->MakeSelectQuery([], $aArgs, $aCountAttToLoad);

		$aDeleteQueries = [];
		foreach (MetaModel::EnumParentClasses($sClass, ENUM_PARENT_CLASSES_ALL) as $sParentClass) {
			$sParentTable = MetaModel::DBGetTable($sParentClass);
			$aDeleteQueries[$sParentTable] = "DELETE FROM `$sParentTable`";
		}
		$sKey = MetaModel::DBGetKey($sClass);

		return new DatabaseProcessRule($sClass, $sMainClassAlias.$sKey, $sSearchQuery, $aDeleteQueries, $sKey);
	}

	/**
	 * @return string
	 */
	public function GetType()
	{
		return $this->sType;
	}

	/**
	 * @param string $sType OQL or SQL
	 */
	public function SetType($sType)
	{
		$this->sType = $sType;
	}

	/**
	 * @return string
	 */
	public function GetName(): string
	{
		return $this->sName;
	}

	/**
	 * @param string $sName
	 */
	public function SetName(string $sName)
	{
		$this->sName = $sName;
	}

	/**
	 * @return string
	 */
	public function GetKey(): string
	{
		return $this->sKey;
	}

	/**
	 * @param string $sKey
	 */
	public function SetKey(string $sKey)
	{
		$this->sKey = $sKey;
	}

	/**
	 * @return string
	 */
	public function GetSearchQuery(): string
	{
		return $this->sSearchQuery;
	}

	/**
	 * @param string $sSearchQuery
	 */
	public function SetSearchQuery(string $sSearchQuery)
	{
		$this->sSearchQuery = $sSearchQuery;
	}

	/**
	 * @return array
	 */
	public function GetApplyQueries(): array
	{
		return $this->aDeleteQueries;
	}

	/**
	 * @param array $aApplyQuery ['table alias' => 'SQL update/delete query']
	 */
	public function SetApplyQueries(array $aApplyQuery)
	{
		$this->aDeleteQueries = $aApplyQuery;
	}

	/**
	 * @return string
	 */
	public function GetSearchKey(): string
	{
		return $this->sSearchKey;
	}

	/**
	 * @param string $sSearchKey
	 */
	public function SetSearchKey(string $sSearchKey)
	{
		$this->sSearchKey = $sSearchKey;
	}
}