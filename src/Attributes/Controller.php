<?php

namespace Yabx\TypeScriptBundle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Controller {

	protected ?string $title = null;

	public function __construct(?string $title = null) {
		$this->title = $title;
	}

	public function getTitle(): ?string {
		return $this->title;
	}

}
