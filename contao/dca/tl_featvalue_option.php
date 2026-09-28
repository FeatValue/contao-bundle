<?php

declare(strict_types=1);

use Contao\DC_Table;

/*
 * Connection state (keys, tokens, site type). Not editable in the back end.
 */
$GLOBALS['TL_DCA']['tl_featvalue_option'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'notCreatable' => true,
        'notEditable' => true,
        'notDeletable' => true,
        'notCopyable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'name' => 'unique',
            ],
        ],
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'name' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'value' => [
            'sql' => 'mediumtext NULL',
        ],
    ],
];
