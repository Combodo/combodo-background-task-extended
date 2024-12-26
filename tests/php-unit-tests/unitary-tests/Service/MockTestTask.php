<?php

use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;

/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
class MockTestTask extends BackgroundTaskEx
{
	private $aActions = [];

	public static function Init()
	{
		$aParams = [
			'category'            => '',
			'key_type'            => 'autoincrement',
			'name_attcode'        => ['name'],
			'state_attcode'       => '',
			'reconc_keys'         => [],
			'db_table'            => 'priv_complex_background_task_mock',
			'db_key_field'        => 'id',
			'db_finalclass_field' => 'finalclass',
		];
		MetaModel::Init_Params($aParams);
		MetaModel::Init_AddAttribute(new AttributeText('action_params', array('allowed_values' => null, 'sql' => 'action_params', 'default_value' => '', 'is_null_allowed' => true, 'depends_on' => array(), 'always_load_in_tables' => false, 'tracking_level' => ATTRIBUTE_TRACKING_NONE)));
		MetaModel::Init_InheritAttributes();

		MetaModel::Init_SetZListItems('list', [
			0 => 'name',
			1 => 'status',
		]);
	}

	public function GetNextAction()
	{
		$iActionId = $this->Get('current_action_id');
		if (!$iActionId) {
			if (isset($this->aActions[0])) {
				$this->Set('current_action_id', 1);
				BackgroundTaskExLog::Info("GetNextAction: Next action is [1]");

				return $this->aActions[0];
			}
			BackgroundTaskExLog::Info('GetNextAction: No further action');

			return null;
		}
		$iActionId++;
		if (isset($this->aActions[$iActionId - 1])) {
			BackgroundTaskExLog::Info("GetNextAction: Next action is [$iActionId]");
			$this->Set('current_action_id', $iActionId);

			return $this->aActions[$iActionId - 1];
		}
		BackgroundTaskExLog::Info("GetNextAction: No further action");

		return null;
	}

	public function GetCurrentAction()
	{
		$iActionId = $this->Get('current_action_id');
		if (!$iActionId) {
			return null;
		}

		return $this->aActions[$iActionId - 1];
	}

	public function DBWrite()
	{
		BackgroundTaskExLog::Info('Task '.$this->Get('name').' is Written to DB');
	}

	public function DBDelete(&$oDeletionPlan = null)
	{
		BackgroundTaskExLog::Info("Task ".$this->Get('name')." is Deleted from DB");
	}

	/**
	 * @param array $aActions
	 */
	public function SetActions(array $aActions)
	{
		$this->aActions = $aActions;
	}

}