<?php

declare(strict_types=1);

// Registered so user groups can grant the module; the menu entry itself is
// added by BackendMenuListener and points to the Symfony route.
$GLOBALS['BE_MOD']['system']['featvalue'] = [
    'hideInNavigation' => true,
];
