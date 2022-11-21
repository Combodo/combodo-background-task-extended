<?php
/**
 * @copyright   Copyright (C) 2010-2021 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


namespace Combodo\iTop\BackgroundTaskEx\Test\Service;

use CMDBSource;
use Exception;
use IssueLog;
use MySQLException;
use utils;

class QueryInjection
{
	private $bShowRequest = true;
	private $aQueries = true;

	/**
	 *
	 * @param int $iFailAt
	 */
	public function SetFailAt($iFailAt)
	{
		$this->aQueries = [];
		$this->bShowRequest = true;
	}

	/**
	 * @param bool $bShowRequest
	 */
	public function SetShowRequest($bShowRequest)
	{
		$this->bShowRequest = $bShowRequest;
	}


	public function query($sSQL)
	{
		if ($this->bShowRequest) {
			$sShortSQL = substr(preg_replace("/\s+/", " ", substr($sSQL, 0, 180)), 0, 150);
			echo "$sShortSQL\n";
		}

		$this->aQueries[]=$sSQL;
	}
}
