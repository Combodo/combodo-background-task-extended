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
	const MODULE_SETTING_MAX_EXEC_TIME = 'max_execution_time';

	private $iProcessEndTime;

	public function __construct()
	{
		$sMaxExecutionTime = MetaModel::GetModuleSetting(BackgroundTaskExHelper::MODULE_NAME, static::MODULE_SETTING_MAX_EXEC_TIME, 30);

		$this->iProcessEndTime = time() + $sMaxExecutionTime;
		BackgroundTaskExLog::Enable(APPROOT.'log/error.log');
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
		// Avoid parallelization for now
		$oMutex = new iTopMutex('BackgroundTaskExService');
		try {
			if ($oMutex->TryLock()) {
				if (is_null($sMessage)) {
					$sMessage = '';
				}
				// Process Error tasks first
				if (!$this->ProcessTaskList("SELECT `$sClass` WHERE status = 'recovering'", $sMessage)) {
					return false;
				}

				// Process Error tasks first
				if (!$this->ProcessTaskList("SELECT `$sClass` WHERE status = 'running'", $sMessage)) {
					return false;
				}

				// Process paused tasks
				if (!$this->ProcessTaskList("SELECT `$sClass` WHERE status = 'paused'", $sMessage)) {
					return false;
				}

				// New tasks to process
				return $this->ProcessTaskList("SELECT `$sClass` WHERE status = 'created'", $sMessage);
			} else {
				return false;
			}
		}
		finally {
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
			if ($this->ProcessOneTask($oTask) == 'finished') {
				$sMessage .= $oTask->Get('message');
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
						$oAction = $oTask->GetNextAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							// No next action
							$bInProgress = false;
						} else {
							$sStatus = 'starting';
							$oTask->Set('status', $sStatus);
							$oTask->DBWrite();
							BackgroundTaskExLog::Debug("ProcessTask: status: $sStatus, action: {$oAction->Get('friendlyname')} end");

							$bCanContinue = $oAction->InitActionParams();
							if (!$bCanContinue) {
								$sClass = get_class($oAction);
								BackgroundTaskExLog::Info("$sClass {$oAction->Get('friendlyname')} $sStatus stopped, action deleted");
								$sStatus = 'finished';
								$oAction->DBDelete();
								$oAction = null;
								// try to move to the next action
							}
						}
						break;

					case 'running':
						$oAction = $oTask->GetCurrentAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							// try to move to the next action
						} else {
							// remember we try to recover from error
							$sStatus = 'recovering';
							$oTask->Set('status', $sStatus);
							$oTask->DBWrite();
							BackgroundTaskExLog::Debug("ProcessTask: status: $sStatus, action: {$oAction->Get('friendlyname')} try restarting");

							$bCanContinue = $oAction->ChangeActionParamsOnError();
							if (!$bCanContinue) {
								$sClass = get_class($oAction);
								BackgroundTaskExLog::Info("$sClass {$oAction->Get('friendlyname')} $sStatus stopped, action deleted");
								$sStatus = 'finished';
								$oAction->DBDelete();
								$oAction = null;
								// try to move to the next action
							}
						}
						break;

					case 'starting':
					case 'recovering':
						// recovering failed
						$oAction = $oTask->GetCurrentAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							// try to move to the next action
						} else {
							// recovering is hopeless, move to the next action
							$sClass = get_class($oAction);
							BackgroundTaskExLog::Error("$sClass {$oAction->Get('friendlyname')} $sStatus failed, action deleted");
							$sStatus = 'finished';
							$oAction->DBDelete();
							$oAction = null;
						}
						break;

					case 'paused':
						$oAction = $oTask->GetCurrentAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							// try to move to the next action
						}
						break;
				}

				if ($bInProgress && !is_null($oAction)) {
					$sAction = $oAction->Get('friendlyname');
					BackgroundTaskExLog::Debug("ProcessTask: status: $sStatus, action: $sAction begin");
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
					BackgroundTaskExLog::Debug("ProcessTask: status: $sStatus, action: $sAction end");
				}
			}
			catch (Exception $e) {
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