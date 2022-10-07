<?php

namespace Combodo\iTop\ComplexBackgroundTask\Service;

use Combodo\iTop\ComplexBackgroundTask\Action\ActionFactory;
use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException;
use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskLog;
use DBObject;
use DBObjectSet;
use DBSearch;
use Exception;

class ComplexBackgroundTaskService
{
	private $oActionFactory;
	private $iProcessEndTime;
	private $aActions;

	public function __construct()
	{
		$this->oActionFactory = new ActionFactory();
		$this->iProcessEndTime = time() + 30;
		$this->aActions = [];
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
	 * @param \DBObject $oTask (ResilientBackgroundTask)
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	protected function ProcessOneTask(DBObject $oTask)
	{
		$sStatus = $oTask->Get('status');
		/** @var \Combodo\iTop\ComplexBackgroundTask\Action\iAction $oAction */
		$oAction = null;
		$sAction = null;
		$bInProgress = true;
		while ($bInProgress) {
			try {
				switch ($sStatus) {
					case 'created':
					case 'finished':
						$sAction = $this->GetNextAction($sAction);
						ComplexBackgroundTaskLog::Debug("ProcessTask: status: $sStatus, action: $sAction");
						$oAction = $this->oActionFactory->GetAction($sAction, $oTask, $this->iProcessEndTime);
						if (is_null($oAction)) {
							$sStatus = 'finished';
							$bInProgress = false;
						} else {
							$oAction->Init();
						}
						break;

					case 'running':
						$sAction = $oTask->Get('action');
						ComplexBackgroundTaskLog::Debug("ProcessTask: status: $sStatus, action: $sAction");
						$oAction = $this->oActionFactory->GetAction($sAction, $oTask, $this->iProcessEndTime);
						if (is_null($oAction)) {
							$sStatus = 'finished';
							$bInProgress = false;
						} else {
							$oAction->Retry();
						}
						break;

					case 'paused':
						$sAction = $oTask->Get('action');
						ComplexBackgroundTaskLog::Debug("ProcessTask: status: $sStatus, action: $sAction");
						$oAction = $this->oActionFactory->GetAction($sAction, $oTask, $this->iProcessEndTime);
						if (is_null($oAction)) {
							$sStatus = 'finished';
							$bInProgress = false;
						}
						break;
				}

				if (!is_null($oAction)) {
					$sStatus = 'running';
					$oTask->Set('status', $sStatus);
					$oTask->Set('action', $sAction);
					$oTask->DBWrite();

					$bActionFinished = $oAction->Execute();
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

	protected function GetNextAction($sAction)
	{
		if (is_null($sAction)) {
			if (isset($this->aActions[0])) {
				return $this->aActions[0];
			}
		}

		foreach ($this->aActions as $key => $sValue) {
			if ($sValue == $sAction) {
				if (isset($this->aActions[$key + 1])) {
					return $this->aActions[$key + 1];
				}
			}
		}

		return null;
	}

	protected function IsTimeoutReached()
	{
		return (time() > $this->iProcessEndTime);
	}

	/**
	 * @param \Combodo\iTop\ComplexBackgroundTask\Action\ActionFactory $oActionFactory
	 */
	public function SetActionFactory(ActionFactory $oActionFactory)
	{
		$this->oActionFactory = $oActionFactory;
	}

	/**
	 * @param int $iProcessEndTime
	 */
	public function SetProcessEndTime(int $iProcessEndTime)
	{
		$this->iProcessEndTime = $iProcessEndTime;
	}

	/**
	 * @param array $aActions
	 */
	public function SetActions(array $aActions)
	{
		$this->aActions = $aActions;
	}


}