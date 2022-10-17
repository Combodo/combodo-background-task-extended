<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\BackgroundTaskEx\Test\Model;

use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;
use Combodo\iTop\BackgroundTaskEx\Service\DatabaseService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

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

		require_once 'MockDatabaseProcessRule.php';

		\MockDatabaseProcessRule::Init();

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
	public function testGetPurgeRuleFromOQL($sTargetClass, $sOQL, $sExpSearchQuery, $sExpSearchKey, $sExpKey)
	{
		/** @var \MockDatabaseProcessRule $oRule */
		$oRule = \MetaModel::NewObject('MockDatabaseProcessRule', [
			'type' => 'advanced',
			'name' => uniqid('', true),
			'target_class' => $sTargetClass,
			'oql_scope' => $sOQL,
		]);
		$oService = new DatabaseService();
		$aParams = $oService->GetParamsForPurgeProcess($oRule);
		$this->assertEquals($sExpSearchQuery, $aParams['search_query']);
		$this->assertEquals($sExpKey, $aParams['key']);
		$this->assertEquals($sExpSearchKey, $aParams['search_key']);

		$aQueries = $oService->BuildQuerySetForTemporaryTable($aParams['search_key'], $aParams['search_query'], $aParams['apply_queries'], $aParams['key'], 'temp', 1000);

		$this->debug($aQueries);
	}

	public function GetPurgeRuleFromOQLProvider()
	{
		return [
			'SELECT Person' => ['Person', 'SELECT Person', 'SELECT
 DISTINCT `Person`.`id` AS `Personid`
 FROM 
   `person` AS `Person`
 WHERE 1
  ', 'Personid', 'id'],
			'SELECT Contact' => ['Contact', 'SELECT Contact', 'SELECT
 DISTINCT `Contact`.`id` AS `Contactid`
 FROM 
   `contact` AS `Contact`
 WHERE 1
  ', 'Contactid', 'id'],
			'SELECT Person AS p' => ['Person', 'SELECT Person AS p', 'SELECT
 DISTINCT `p`.`id` AS `pid`
 FROM 
   `person` AS `p`
 WHERE 1
  ', 'pid', 'id'],
			'SELECT CMDBChange' => ['CMDBChange', 'SELECT CMDBChange', 'SELECT
 DISTINCT `CMDBChange`.`id` AS `CMDBChangeid`
 FROM 
   `priv_change` AS `CMDBChange`
 WHERE 1
  ', 'CMDBChangeid', 'id'],
		];
	}
}
