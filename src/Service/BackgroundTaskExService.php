<?php

namespace Combodo\iTop\BackgroundTaskEx\Service;

use BackgroundTaskEx;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExHelper;
use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;
use DBObjectSet;
use DBSearch;
use Exception;
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
	 */
	public function ProcessTasks($sClass, &$sMessage): bool
	{
		if (is_null($sMessage)) {
			$sMessage = '';
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
							$bInProgress = false;
						} else {
							$oAction->InitActionParams();
						}
						break;

					case 'running':
						$oAction = $oTask->GetCurrentAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							$bInProgress = false;
						} else {
							$bCanContinue = $oAction->ChangeActionParamsOnError();
							if (!$bCanContinue) {
								$sStatus = 'finished';
								$bInProgress = false;
							}
						}
						break;

					case 'paused':
						$oAction = $oTask->GetCurrentAction();
						if (is_null($oAction)) {
							$sStatus = 'finished';
							$bInProgress = false;
						}
						break;
				}

				if ($bInProgress) {
					$sAction = $oAction->Get('friendlyname');
					BackgroundTaskExLog::Debug("ProcessTask: status: $sStatus, action: $sAction begin");
					$sStatus = 'running';
					$oTask->Set('status', $sStatus);
					$oTask->DBWrite();

					$bActionFinished = $oAction->ExecuteAction($this->iProcessEndTime);
					if ($bActionFinished) {
						$sStatus = 'finished';
					} else {
						$sStatus = 'paused';
						$oTask->Set('status', $sStatus);
						$oTask->DBWrite();
						$bInProgress = false;
					}
					BackgroundTaskExLog::Debug("ProcessTask: status: $sStatus, action: $sAction end");
				}
			} catch (Exception $e) {
				// stay in 'running' status
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