<?php

declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_module']['palettes']['featvalue_dashboard'] = '{title_legend},name,headline,type;{featvalue_legend},featvalue_height;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_module']['fields']['featvalue_height'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 64, 'tl_class' => 'w50', 'placeholder' => 'min(800px, 80vh)'],
    'sql' => "varchar(64) NOT NULL default ''",
];
