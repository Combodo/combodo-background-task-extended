<?php

/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

class MockDatabaseProcessRule extends DatabaseProcessRule
{
	public static function Init()
	{
		$aParams = [
			'category' => 'bizmodel,searchable',
			'key_type' => 'autoincrement',
			'name_attcode' => ['name'],
			'state_attcode' => '',
			'reconc_keys' => ['target_class'],
			'db_table' => 'database_process_rule_mock',
			'db_key_field' => 'id',
			'db_finalclass_field' => 'finalclass',
		];
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();
	}

}
