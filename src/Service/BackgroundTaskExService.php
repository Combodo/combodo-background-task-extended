<?php

namespace Combodo\iTop\BackgroundTaskEx\Service;

use BackgroundTaskEx;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExHelper;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;
use DBObjectSet;
use DBSearch;
use Exception;
use iTopMutex;
use MetaModel;

class BackgroundTaskExService
{
	public const MODULE_SETTING_MAX_EXEC_TIME = 'max_execution_time';

	private $iProcessEndTime;

	public function __construct($sDebugFile = BackgroundTaskExLog::DEBUG_FILE)
	{
		$sMaxExecutionTime = MetaModel::GetModuleSetting(BackgroundTaskExHelper::MODULE_NAME, static::MODULE_SETTING_MAX_EXEC_TIME, 30);

		$this->iProcessEndTime = time() + $sMaxExecutionTime;
		BackgroundTaskExLog::Enable($sDebugFile);
	}

	/**
	 * @throws \CoreException
	 * @throws \DeleteException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \CoreUnexpectedValue
	 * @throws \OQLException
	 * @throws \ArchivedObjectException
	 * @throws \Exception
	 */
	public function ProcessTasks($sClass, &$sMessage): bool
	{
		$aStatuses = [
			'starting',
			'recovering',
			'running',
			'paused',
			'created',
		];

		// Avoid parallelization for now
		$oMutex = new iTopMutex('BackgroundTaskExService');
		try {
			if ($oMutex->TryLock()) {
				if (is_null($sMessage)) {
					$sMessage = '';
				}
				// Process tasks
				foreach (['interactive', 'cron'] as $sType) {
					foreach ($aStatuses as $sStatus) {
						if (!$this->ProcessTaskList("SELECT `$sClass` WHERE `status` = '$sStatus' AND `type` = '$sType'", $sMessage)) {
							return false;
						}
					}
				}

				// New tasks to process
				return true;
			} else {
				return false;
			}
		} finally {
			$oMutex->Unlock();
		}
	}

	/**
	 * @param string $sOQL
	 * @param string $sMessage
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \DeleteException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	protected function ProcessTaskList(string $sOQL, &$sMessage): bool
	{
		$oSearch = DBSearch::FromOQL($sOQL);
		$oSet = new DBObjectSet($oSearch);
		/** @var BackgroundTaskEx $oTask */
		while ($oTask = $oSet->Fetch()) {
			if ($this->IsTimeoutReached()) {
				return false;
			}
			$sStatus = $this->ProcessOneTask($oTask);
			$sMessage .= $oTask->Get('message');
			if ($sStatus == 'finished') {
				$oTask->DBDelete();
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param \BackgroundTaskEx $oTask (ResilientBackgroundTask)
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	protected function ProcessOneTask(BackgroundTaskEx $oTask)
	{
		$sStatus = $oTask->Get('status');
		/** @var \BackgroundTaskExAction $oAction */
		$oAction = null;
		$bInProgress = true;
		while ($bInProgress) {
			try {
				switch ($sStatus) {
					case 'created':
					case 'finished':
						// Currently no action is selected
						$oAction = $oTask->GetNextAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							// No next action
							$bInProgress = false;
						} else {
							$sStatus = 'starting';
							$oTask->Set('status', $sStatus);
							$oTask->DBWrite();
							BackgroundTaskExLog::Debug("ProcessTask: action: {$oAction->Get('friendlyname')} starting");

							$bCanContinue = $oAction->InitActionParams();
							if (!$bCanContinue) {
								BackgroundTaskExLog::Debug("ProcessTask: action: {$oAction->Get('friendlyname')} status: $sStatus cannot continue => the action is deleted");
								$sStatus = 'finished';
								$oAction->DBDelete();
								$oAction = null;
								// try to move to the next action
							}
						}
						break;

					case 'running':
						// Arriving in "running" status means that ExecuteAction() failed brutally
						$oAction = $oTask->GetCurrentAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							// try to move to the next action
						} else {
							// remember we try to recover from error
							$sStatus = 'recovering';
							$oTask->Set('status', $sStatus);
							$oTask->DBWrite();
							BackgroundTaskExLog::Debug("ProcessTask: action: {$oAction->Get('friendlyname')} try recovering from previous error");

							$bCanContinue = $oAction->ChangeActionParamsOnError();
							if (!$bCanContinue) {
								BackgroundTaskExLog::Debug("ProcessTask: action: {$oAction->Get('friendlyname')} recovering failed => the action is deleted");
								$sStatus = 'finished';
								$oAction->DBDelete();
								$oAction = null;
								// try to move to the next action
							}
						}
						break;

					case 'starting':
						// InitActionParams() failed brutally
					case 'recovering':
						// ChangeActionParamsOnError() failed brutally
						$oAction = $oTask->GetCurrentAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							// try to move to the next action
						} else {
							// recovering is hopeless, move to the next action
							BackgroundTaskExLog::Error("ProcessTask: action: {$oAction->Get('friendlyname')} $sStatus failed brutally => the action is deleted");
							$sStatus = 'finished';
							$oAction->DBDelete();
							$oAction = null;
						}
						break;

					case 'paused':
						// The previous execution stopped normally on timeout, just continue
						$oAction = $oTask->GetCurrentAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							// try to move to the next action
						}
						break;

					default:
						BackgroundTaskExLog::Error("ProcessTask: task: {$oTask->Get('name')} status: $sStatus is unknown => the task is deleted");
						$sStatus = 'finished';
						$bInProgress = false;
						break;
				}

				if ($bInProgress && !is_null($oAction)) {
					$sAction = $oAction->Get('friendlyname');
					BackgroundTaskExLog::Debug("ProcessTask: action: $sAction running");
					$sStatus = 'running';
					$oTask->Set('status', $sStatus);
					$oTask->DBWrite();

					$bActionFinished = $oAction->ExecuteAction($this->iProcessEndTime);
					if ($bActionFinished) {
						$sStatus = 'finished';
						$oAction->DBDelete();
						$oAction = null;
					} else {
						$sStatus = 'paused';
						$oTask->Set('status', $sStatus);
						$oTask->DBWrite();
						$bInProgress = false;
					}
					BackgroundTaskExLog::Debug("ProcessTask: action: $sAction status: $sStatus, end");
				}
			} catch (Exception $e) {
				// stay in 'previous' status
				BackgroundTaskExLog::Error($e->getMessage());
				$bInProgress = false;
			}
		}

		return $sStatus;
	}

	protected function IsTimeoutReached()
	{
		return (time() > $this->iProcessEndTime);
	}

	/**
	 * @param int $iProcessEndTime
	 */
	public function SetProcessEndTime(int $iProcessEndTime)
	{
		$this->iProcessEndTime = $iProcessEndTime;
	}

}
