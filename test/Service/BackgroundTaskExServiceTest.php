<?php
/*
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\BackgroundTaskEx\Test\Service;

use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;
use Combodo\iTop\BackgroundTaskEx\Service\BackgroundTaskExService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use MetaModel;


/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class BackgroundTaskExServiceTest extends ItopDataTestCase
{
	const USE_TRANSACTION = true;
	const CREATE_TEST_ORG = false;
	private $TEST_LOG_FILE;

	protected function setUp(): void
	{
		parent::setUp();

		$this->debug("----- Test ".$this->getName());

		require_once 'MockTestAction.php';
		require_once 'MockTestTask.php';

		\MockTestTask::Init();
		\MockTestAction::Init();


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
	 * @dataProvider ProcessOneTaskProvider
	 * @param $sExpectedStatus
	 * @param $sInitialStatus
	 * @param $sInitialAction
	 * @param $sExpectedActionParams
	 * @param $aActions
	 * @param $aActionParams
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \ReflectionException
	 */
	public function testProcessOneTask($sExpectedStatus, $sInitialStatus, $sInitialAction, $sExpectedActionParams, $aActions, $aActionParams)
	{
		$oService = new BackgroundTaskExService();
		BackgroundTaskExLog::Enable($this->TEST_LOG_FILE);

		/** @var \MockTestTask $oTask */
		$oTask = MetaModel::NewObject('MockTestTask');
		$oTask->Set('name', 'Test');
		$oTask->Set('status', $sInitialStatus);
		if ($sInitialAction !== '') {
			$oTask->Set('current_action_id', $sInitialAction);
		}

		$aTaskActions = [];
		foreach ($aActions as $index => $sAction) {
			/** @var \MockTestAction $oAction */
			$oAction = MetaModel::NewObject('MockTestAction', ['name' => $sAction, 'rank' => $index]);
			$oAction->SetParams($aActionParams[$index]);
			$oAction->SetTask($oTask);
			$aTaskActions[] = $oAction;
		}
		$oTask->SetActions($aTaskActions);

		$sStatus = $this->InvokeNonPublicMethod(BackgroundTaskExService::class, 'ProcessOneTask', $oService, [$oTask]);

		$this->assertEquals($sExpectedStatus, $sStatus, 'Checking status');
		$this->assertEquals($sExpectedActionParams, $oTask->Get('action_params'), 'Checking action_params');
	}

	public function ProcessOneTaskProvider()
	{
		return [
			'no action'               => [
				'finished',
				'created',
				'',
				'',
				[],
				[[]],
			],
			'one action finished' => [
				'finished',
				'created',
				'',
				' - Task1 init - Task1 execute',
				['Action1'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => true,
					],
				],
			],
			'one action paused'   => [
				'paused',
				'created',
				'',
				' - Task1 init - Task1 execute',
				['Action1'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => false,
					],
				],
			],
			'one action error'    => [
				'running',
				'created',
				'',
				' - Task1 init - Task1 execute',
				['Action1'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => 'Exception',
					],
				],
			],
			'one action continue' => [
				'finished',
				'paused',
				'1',
				' - Task1 execute',
				['Action1'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => true,
					],
				],
			],
			'one action retry on error' => [
				'finished',
				'running',
				'1',
				' - Task1 retry - Task1 execute',
				['Action1'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => true,
					],
				],
			],
			'two actions finished' => [
				'finished',
				'created',
				'',
				' - Task1 init - Task1 execute - Task2 init - Task2 execute',
				['Action1', 'Action2'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => true,
					],
					[
						'Init'       => 'Task2 init',
						'Retry'      => 'Task2 retry',
						'Execute'    => 'Task2 execute',
						'ExecReturn' => true,
					],
				],
			],
			'two actions, first paused' => [
				'paused',
				'created',
				'',
				' - Task1 init - Task1 execute',
				['Action1', 'Action2'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => false,
					],
					[
						'Init'       => 'Task2 init',
						'Retry'      => 'Task2 retry',
						'Execute'    => 'Task2 execute',
						'ExecReturn' => true,
					],
				],
			],
		];
	}
}
