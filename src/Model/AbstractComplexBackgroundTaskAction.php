<?php

/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
abstract class AbstractComplexBackgroundTaskAction extends DBObject
{
	private $oTask;

	protected function GetTask()
	{
		if (!$this->oTask) {
			$this->oTask = MetaModel::GetObject('ComplexBackgroundTask', $this->Get('task_id'), false);
		}
		return $this->oTask;
	}
}