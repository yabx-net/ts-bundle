<?php

namespace Yabx\TypeScriptBundle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Method {

	protected ?string $title = null;
	protected ?string $request = null;
	protected ?string $response = null;

	public function __construct(?string $title = null, ?string $request = null, ?string $response = null) {
		$this->title = $title;
		$this->request = $request;
		$this->response = $response;
	}

	public function getTitle(): ?string {
		return $this->title;
	}

	public function getRequest(): ?string {
		return $this->request;
	}

	public function getResponse(): ?string {
		return $this->response;
	}

}
