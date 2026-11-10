<?php

namespace App\Support\Modules;

use RuntimeException;

/** A store asked for something its module state does not allow. The message is safe to show. */
final class ModuleActivationRefused extends RuntimeException {}
