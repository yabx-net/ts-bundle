<?php

namespace Yabx\TypeScriptBundle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Method {

	protected ?string $request = null;
	protected ?string $response = null;

	public function __construct(?string $request = null, ?string $response = null) {
		$this->request = $request;
		$this->response = $response;
	}

	public function getRequest(): ?string {
		return $this->request;
	}

	public function getResponse(): ?string {
		return $this->response;
	}

}
