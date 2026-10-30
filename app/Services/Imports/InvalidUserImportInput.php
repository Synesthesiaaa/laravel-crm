<?php

namespace App\Services\Imports;

/** Only explicitly safe input diagnostics may be printed by the command. */
final class InvalidUserImportInput extends \InvalidArgumentException {}
