<?php

namespace Combodo\iTop\ComplexBackgroundTask\Service;

use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException;
use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskLog;
use ComplexBackgroundTask;
use DBObjectSet;
use DBSearch;
use Exception;
use MetaModel;

class ComplexBackgroundTaskService
{
	private $iProcessEndTime;

	public function __construct()
	{
		$this->iProcessEndTime = time() + 30;
		ComplexBackgroundTaskLog::Enable(APPROOT.'log/error.log');
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
	public function ProcessTasks($sClass)
	{
		// Process Error tasks first
		if (!$this->ProcessTaskList("SELECT `$sClass` WHERE status = 'running'")) {
			return;
		}

		// Process paused tasks
		if (!$this->ProcessTaskList("SELECT `$sClass` WHERE status = 'paused'")) {
			return;
		}

		// New tasks to process
		$this->ProcessTaskList("SELECT `$sClass` WHERE status = 'created'");
	}

	/**
	 * @param string $sOQL
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
	protected function ProcessTaskList(string $sOQL): bool
	{
		$oSearch = DBSearch::FromOQL($sOQL);
		$oSet = new DBObjectSet($oSearch);
		/** @var ComplexBackgroundTask $oTask */
		while ($oTask = $oSet->Fetch()) {
			if ($this->IsTimeoutReached()) {
				return false;
			}
			if ($this->ProcessOneTask($oTask) == 'finished') {
				$oTask->DBDelete();
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param \ComplexBackgroundTask $oTask (ResilientBackgroundTask)
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	protected function ProcessOneTask(ComplexBackgroundTask $oTask)
	{
		$sStatus = $oTask->Get('status');
		/** @var \ComplexBackgroundTaskAction $oAction */
		$oAction = null;
		$sAction = null;
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
						$oAction = MetaModel::GetObject('ComplexBackgroundTaskAction', $oTask->Get('current_action_id'), false);
						if (is_null($oAction)) {
							$sStatus = 'finished';
							$bInProgress = false;
						} else {
							$oAction->ChangeActionParamsOnError();
						}
						break;

					case 'paused':
						$oAction = MetaModel::GetObject('ComplexBackgroundTaskAction', $oTask->Get('current_action_id'), false);
						if (is_null($oAction)) {
							$sStatus = 'finished';
							$bInProgress = false;
						}
						break;
				}

				if (!is_null($oAction)) {
					$sAction = $oAction->Get('friendlyname');
					ComplexBackgroundTaskLog::Debug("ProcessTask: status: $sStatus, action: $sAction");
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
				}
			} catch (ComplexBackgroundTaskException $e) {
				ComplexBackgroundTaskLog::Error('AnonymizerException'.$e->getMessage());
				// stay in 'running' status
				$bInProgress = false;
			} catch (Exception $e) {
				// stay in 'running' status
				ComplexBackgroundTaskLog::Error($e->getMessage());
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