<?php

namespace Combodo\iTop\BackgroundTaskEx\Test\Service;

use CMDBSource;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Combodo\iTop\BackgroundTaskEx\Service\DatabaseQueryService;
use Combodo\iTop\BackgroundTaskEx\Service\DatabaseService;
use Exception;
use MetaModel;

class DatabaseServiceTest extends ItopDataTestCase {
	/** @var DatabaseService */
	private $oDatabaseService;
	/** @var DatabaseQueryService */
	private $oDatabaseQueryService;

	protected function setUp() {
		parent::setUp();
		require_once(APPROOT.'/env-production/combodo-background-task-extended/vendor/autoload.php');

		$this->oDatabaseQueryService = $this->createMock(DatabaseQueryService::class);
		$this->oDatabaseService = new DatabaseService(BackgroundTaskExLog::DEBUG_FILE, $this->oDatabaseQueryService);
	}

	public function testQueryMaxKey(){
		$this->oDatabaseQueryService
			->expects($this->exactly(1))
			->method("QueryMaxKey")
			->with('id', 'TableXXX');

		$this->oDatabaseService->QueryMaxKey('id', 'TableXXX');
	}

	public function testQueryMinKey(){
		$this->oDatabaseQueryService
			->expects($this->exactly(1))
			->method("QueryMinKey")
			->with('id', 'TableXXX');

		$this->oDatabaseService->QueryMinKey('id', 'TableXXX');
	}

	private function GetRule(){
		$sEventLoginUsage = 'PrivEventLoginUsage';
		$sEvent = 'PrivEvent';
		$sKey = 'id';

		$sSearchQuery = <<<SQL
SELECT $sKey FROM $sEvent
WHERE date < DATE_SUB(NOW(), INTERVAL 30 DAY)
AND realclass='EventLoginUsage'
SQL;
		$aDeleteQueries=[
			$sEventLoginUsage => "DELETE $sEventLoginUsage FROM $sEventLoginUsage /*JOIN*/",
			$sEvent => "DELETE $sEvent FROM $sEvent  /*JOIN*/",
		];

		return [
			'class' => 'EventLoginUsage',
			'name' => 'EventLoginUsage',
			'search_key' => 'id',
			'key' => 'id',
			'search_max_id' => 10000,
			'search_query' => $sSearchQuery,
			'apply_queries' => $aDeleteQueries,
		];
	}

	public function ExecuteQueriesByChunkMissingParamProvider() : array{
		return [
			'class' => ['class'],
			'search_key' => ['search_key'],
			'key' => ['key'],
			'search_max_id' => ['search_max_id'],
			'search_query' => ['search_query'],
			'apply_queries' => ['apply_queries'],
		];
	}

	/**
	 * @dataProvider ExecuteQueriesByChunkMissingParamProvider
	 */
	public function testExecuteQueriesByChunkMissingParam($missingParamKey){
		$aRule = $this->GetRule();
		var_dump($missingParamKey);
		var_dump($aRule);
		unset($aRule[$missingParamKey]);

		try{
			$this->oDatabaseQueryService
				->expects($this->exactly(0))
				->method("ExecuteSQLQueriesByChunkWithTempTable");

			$iProgress = 0;
			$iChunkSize = 100;
			$this->oDatabaseService->ExecuteQueriesByChunk($aRule, $iProgress, $iChunkSize);
			$this->assertFail("Should throw Exception");
		} catch(BackgroundTaskExException $e){
			$this->assertContains("Bad parameters:", $e->getMessage());
		}
	}

	public function ExecuteQueriesByChunkProvider(){
		$aRule = $this->GetRule();

		return [
			'first call / completed' => [
				$aRule, 0, 100, $aRule['search_query']." AND `id` >= 0 AND `id` <= 100"
			],
			'first call / keep going' => [
				$aRule, 0, 100, $aRule['search_query']." AND `id` >= 0 AND `id` <= 100", false, 100
			],
			'first call / min_key / keep going' => [
				array_merge($aRule, ['search_min_id' => 50]), 0, 100, $aRule['search_query']." AND `id` >= 50 AND `id` <= 150", false, 150
			],
			'any call / keep going' => [
				$aRule, 9990, 100, $aRule['search_query']." AND `id` >= 9990 AND `id` <= 10000", false, 10000
			],
			'no need to query again' => [
				$aRule, 10000, 100, null
			],
		];
	}

	/**
	 * @dataProvider ExecuteQueriesByChunkProvider
	 */
	public function testExecuteQueriesByChunkCompleted($aRule, $iProgress, $iChunkSize, $sExpectedSearchQuery, $bCompleted=true, $bExpectedProgress=-1){

		if (is_null($sExpectedSearchQuery)){
			$this->oDatabaseQueryService
				->expects($this->exactly(0))
				->method("ExecuteSQLQueriesByChunkWithTempTable");
		} else {
			$this->oDatabaseQueryService
				->expects($this->exactly(1))
				->method("ExecuteSQLQueriesByChunkWithTempTable")
				->with($aRule['class'], $aRule['search_key'], $sExpectedSearchQuery, $aRule['apply_queries'], $aRule['key'], $iChunkSize)
				->willReturn($bCompleted);
		}


		$res = $this->oDatabaseService->ExecuteQueriesByChunk($aRule, $iProgress, $iChunkSize);
		$this->assertEquals($bCompleted, $res);
		$this->assertEquals($bExpectedProgress, $iProgress);
	}
}
