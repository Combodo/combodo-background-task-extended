<?php

namespace Combodo\iTop\BackgroundTaskEx\Test\Service;

use CMDBSource;
use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Exception;
use MetaModel;

class DatabaseQueryServiceTest extends ItopTestCase {
	/** @var QueryInjection */
	private $oMySQLiMock;

	protected function setUp() : void {
		parent::setUp();
		require_once('QueryInjection.php');
		require_once(APPROOT.'/core/cmdbsource.class.inc.php');
		$sEnv = 'production';
		$sConfigFile = APPCONF.$sEnv.'/config-itop.php';

		MetaModel::Startup($sConfigFile, false, true, false, $sEnv);

		$oInitialMysqli = CMDBSource::GetMysqli();
		$this->oMySQLiMock = new QueryInjection();

		$oMockMysqli = $this->getMockBuilder('mysqli')
			->setMethods(['query'])
			->getMock();
		$oMockMysqli->expects($this->any())
			->method('query')
			->will($this->returnCallback(
				function ($sSql) use ($oInitialMysqli) {
					$this->oMySQLiMock->query($sSql);

					return $oInitialMysqli->query($sSql);
				}
			));

		$this->InvokeNonPublicStaticMethod('CMDBSource', 'SetMySQLiForQuery', [$oMockMysqli]);
	}

	public function test(){
		$oDatabaseService = new DatabaseService();
		$oDatabaseService->ExecuteQueriesByChunk();
	}
}
