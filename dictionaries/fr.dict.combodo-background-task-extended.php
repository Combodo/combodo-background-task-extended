<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

 /**
 * Localized data
 */

Dict::Add('FR FR', 'French', 'Français', [
    // Class DatabaseProcessRule
    'Class:DatabaseProcessRule/Name' => '%1$s',
    'Class:DatabaseProcessRule' => 'Database process rule',
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
    'Class:DatabaseProcessRule/Attribute:date_to_check_att' => 'Date to check',
    'Class:DatabaseProcessRule/Attribute:date_to_check_att+' => 'Attribute code of the date to check',
    'Class:DatabaseProcessRule/Attribute:autoarchive_delay' => 'Delay',
    'Class:DatabaseProcessRule/Attribute:autoarchive_delay+' => 'When this delay in days is passed after the "Date to check", objects are automatically processed',
    'Class:DatabaseProcessRule/Attribute:oql_scope' => 'OQL scope',
    'Class:DatabaseProcessRule/Attribute:oql_scope+' => 'OQL query to define which objects are concerned by this rule.',

    // Integrity errors
    'Class:DatabaseProcessRule/Error:ClassNotValid' => 'Class "%1$s" does not exist in your datamodel',
    'Class:DatabaseProcessRule/Error:AttributeNotValid' => '"%2$s" is not a valid attribute for class "%1$s"',
    'Class:DatabaseProcessRule/Error:AttributeMustBeDate' => '"%3$s" must be a date attribute of class "%1$s", "%2$s" is not a date',
    'Class:DatabaseProcessRule/Error:NoOptionFilled' => 'Either option 1 or option 2 must be filled',
    'Class:DatabaseProcessRule/Error:OptionOneMissingField' => 'Fields date and delay of option 1 must be filled',

    // Presentation
    'DatabaseProcessRule:general' => 'General informations',
    'DatabaseProcessRule:simple' => 'Fill either option 1 (simple) ...',
    'DatabaseProcessRule:advanced' => '... or option 2 (advanced)',

    // Tabs
    'UI:DatabaseProcessRule:Preview' => 'Preview',
    'UI:DatabaseProcessRule:Title' => '%1$s to be processed as of now',
]);
