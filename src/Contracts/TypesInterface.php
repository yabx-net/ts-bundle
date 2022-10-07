<?php

namespace Yabx\TypeScriptBundle\Contracts;

use Yabx\TypeScriptBundle\Service\TypeScript;

interface TypesInterface {

	public static function registerTypes(TypeScript $ts): void;

	public static function codePostProcessor(string $code): string;

}
