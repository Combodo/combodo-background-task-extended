<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2013 XXXXX
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('EN US', 'English', 'English', array(
	// Class DatabaseProcessRule
	'Class:DatabaseProcessRule/Name' => '%1$s',
	'Class:DatabaseProcessRule' => 'Archiving rule',
	'Class:DatabaseProcessRule+' => '',
	'Class:DatabaseProcessRule/Attribute:name' => 'Name',
	'Class:DatabaseProcessRule/Attribute:name+' => '',
	'Class:DatabaseProcessRule/Attribute:target_class' => 'Class',
	'Class:DatabaseProcessRule/Attribute:target_class+' => '',
	'Class:DatabaseProcessRule/Attribute:status' => 'Status',
	'Class:DatabaseProcessRule/Attribute:status+' => '',
	'Class:DatabaseProcessRule/Attribute:status/Value:active' => 'Active',
	'Class:DatabaseProcessRule/Attribute:status/Value:inactive' => 'Inactive',
	'Class:DatabaseProcessRule/Attribute:type' => 'Applied option',
	'Class:DatabaseProcessRule/Attribute:type+' => 'Which option will be used regarding the filled fields. If both are filled, advanced option is applied',
	'Class:DatabaseProcessRule/Attribute:type/Value:simple' => 'Simple',
	'Class:DatabaseProcessRule/Attribute:type/Value:advanced' => 'Advanced',
	'Class:DatabaseProcessRule/Attribute:pre_archiving_status_code' => 'Pre-archiving status code',
	'Class:DatabaseProcessRule/Attribute:pre_archiving_status_code+' => 'Choose attribute to test on objects of the chosen class for the rule to apply',
	'Class:DatabaseProcessRule/Attribute:pre_archiving_status_value' => 'Pre-archiving status value',
	'Class:DatabaseProcessRule/Attribute:pre_archiving_status_value+' => 'Value of attribute defined above in which objects of the chosen class must be for the rule to apply',
	'Class:DatabaseProcessRule/Attribute:date_to_check_att' => 'Date to check',
	'Class:DatabaseProcessRule/Attribute:date_to_check_att+' => 'Attribute code of the date to check',
	'Class:DatabaseProcessRule/Attribute:autoarchive_delay' => 'Autoarchiving delay',
	'Class:DatabaseProcessRule/Attribute:autoarchive_delay+' => 'When this delay in days is passed after the "Date to check", objects are automatically archived',
	'Class:DatabaseProcessRule/Attribute:oql_scope' => 'OQL scope',
	'Class:DatabaseProcessRule/Attribute:oql_scope+' => 'OQL query to define which objects are concerned by this rule.',

	// Integrity errors
	'Class:DatabaseProcessRule/Error:ClassNotValid' => 'Class "%1$s" does not exist in your datamodel',
	'Class:DatabaseProcessRule/Error:ClassNotArchivable' => 'Class "%1$s" is not archivable, please modify first your datamodel',
	'Class:DatabaseProcessRule/Error:AttributeNotValid' => '"%2$s" is not a valid attribute for class "%1$s"',
	'Class:DatabaseProcessRule/Error:AttributeMustBeDate' => '"%3$s" must be a date attribute of class "%1$s", "%2$s" is not a date',
	'Class:DatabaseProcessRule/Error:StatusNotValid' => '"%2$s" is not a valid attribute for class "%1$s"',
	'Class:DatabaseProcessRule/Error:StatusCodeNotValid' => '"%3$s" must be an enum attribute of class "%1$s", "%2$s" is not an enum',
	'Class:DatabaseProcessRule/Error:StatusValueNotValid' => '"%3$s" is not a valid value for attribute "%2$s" of "%1$s" class',
	'Class:DatabaseProcessRule/Error:ExistingRuleForClass' => 'There is already a archiving rule for class "%1$s"',
	'Class:DatabaseProcessRule/Error:NoOptionFilled' => 'Either option 1 or option 2 must be filled',
	'Class:DatabaseProcessRule/Error:OptionOneMissingField' => ' Fields date and delay of option 1 must be filled',

	// Presentation
	'DatabaseProcessRule:general' => 'General informations',
	'DatabaseProcessRule:simple' => 'Fill either option 1 (simple) ...',
	'DatabaseProcessRule:advanced' => '... or option 2 (advanced)',

	// Tabs
	'UI:DatabaseProcessRule:Preview' => 'Preview',
	'UI:DatabaseProcessRule:Title' => '%1$s to be Archive as of now',

));
