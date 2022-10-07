<?php

namespace Yabx\TypeScriptBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class TypeScriptBundle extends Bundle {

	public function getPath(): string {
		return dirname(__DIR__);
	}

}
