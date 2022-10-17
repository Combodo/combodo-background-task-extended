<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\BackgroundTaskEx\Test\Model;

use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use DatabaseProcessRule;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class DatabaseProcessRuleTest extends ItopDataTestCase
{
	const USE_TRANSACTION = false;
	const CREATE_TEST_ORG = false;
	private $TEST_LOG_FILE;

	protected function setUp(): void
	{
		parent::setUp();

		$this->debug('----- Test '.$this->getName());

		$this->TEST_LOG_FILE = APPROOT.'log/test.log';
		BackgroundTaskExLog::Enable($this->TEST_LOG_FILE);
		@unlink($this->TEST_LOG_FILE);
	}

	protected function tearDown(): void
	{
		if (file_exists($this->TEST_LOG_FILE)) {
			$sLogs = file_get_contents($this->TEST_LOG_FILE);
			$this->debug($sLogs);
		}
		parent::tearDown();
	}

	/**
	 * @dataProvider GetPurgeRuleFromOQLProvider
	 */
	public function testGetPurgeRuleFromOQL($sOQL, $sExpSearchQuery, $sExpKey)
	{
		$oRule = DatabaseProcessRule::GetPurgeRuleFromOQL($sOQL);
		$this->assertEquals($sExpSearchQuery, $oRule->GetSearchQuery());
		$this->assertEquals($sExpKey, $oRule->GetKey());
		$this->debug($oRule->GetApplyQueries());
	}

	public function GetPurgeRuleFromOQLProvider()
	{
		return [
			'SELECT Person' => ['SELECT Person', 'SELECT
 DISTINCT `Person`.`id` AS `Personid`
 FROM 
   `person` AS `Person`
 WHERE 1
  ', 'id'],
			'SELECT Person AS p' => ['SELECT Person AS p', 'SELECT
 DISTINCT `p`.`id` AS `pid`
 FROM 
   `person` AS `p`
 WHERE 1
  ', 'id'],
			'SELECT CMDBChange' => ['SELECT CMDBChange', 'SELECT
 DISTINCT `CMDBChange`.`id` AS `CMDBChangeid`
 FROM 
   `priv_change` AS `CMDBChange`
 WHERE 1
  ', 'id'],
		];
	}
}
