<?php

namespace Yabx\TypeScriptBundle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class Definition {

	public ?string $value;

    public function __construct(?string $value = null) {
		$this->value = $value;
    }

	public function getValue(): string {
		return $this->value;
	}

}
