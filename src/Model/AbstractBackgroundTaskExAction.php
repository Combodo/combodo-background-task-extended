<?php

/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
abstract class AbstractBackgroundTaskExAction extends DBObject
{
	private $oTask;

	protected function GetTask()
	{
		if (!$this->oTask) {
			$this->oTask = MetaModel::GetObject('BackgroundTaskEx', $this->Get('task_id'), false);
		}
		return $this->oTask;
	}
}