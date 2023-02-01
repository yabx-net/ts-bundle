<?php

namespace Yabx\TypeScriptBundle\Service;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionNamedType;
use ReflectionUnionType;
use Yabx\RestBundle\Validator\EnumChoice;
use Yabx\TypeScriptBundle\Attributes\Hidden;
use Yabx\TypeScriptBundle\Attributes\Definition;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class TypeScript {

	protected array $definitions = [];
	protected ClassResolver $classResolver;
	protected array $groups = [];

	protected const MAPPING = [
		'mixed' => 'any',
		'int' => 'number',
		'float' => 'number',
		'bool' => 'boolean',
		'array' => '[]',
		'DateTimeInterface' => 'TDateTime',
		'DateTimeImmutable' => 'TDateTime',
		'DateTime' => 'TDateTime',
		'DateTimeZone' => 'TDateTimeZone',
	];

	public static function factory(): self {
		return new self();
	}

	public function __construct() {
		$this->classResolver = new ClassResolver;
		$this->registerType('TDateTime', 'string');
		$this->registerType('TDateTimeZone', 'string');
		$this->registerType('TIdentifier', 'string | number');
	}

	public function registerType(string $name, string $type): self {
		$this->registerRaw($name, 'export type ' . $name . ' = ' . $type . ';');
		return $this;
	}

	public function registerTypeOf(string $name, array $values): self {
		array_walk($values, function(&$value) {
			$value = json_encode($value);
		});
		$definition = 'export type ' . $name . ' = ' . implode(' | ', $values) . ';';
		return $this->registerRaw($name, $definition);
	}

	public function registerArrayEnum(string $name, array $enum): self {
		$definition = [];
		foreach($enum as $key => $value) {
			if(is_numeric($key)) {
				$definition[] = $value;
			} else {
				$definition[] = "{$key} = " . (is_numeric($value) ? $value : json_encode($value));
			}
		}
		$definition = 'export enum ' . $name . ' { ' . implode(', ', $definition) . ' };';
		return $this->registerRaw($name, $definition);
	}

	public function registerObject(string $name, string $type, array $data, ?array $values = null): self {
		if(isset($values)) $data = array_combine($data, $values);
		$definition = [];
		foreach($data as $key => $value) {
			$key = json_encode($key);
			$definition[] = "{$key}: '{$value}'";
		}
		$this->registerRaw($name, "export const {$name}: {$type} = { " . implode(', ', $definition) . " };");
		return $this;
	}

	public function registerInterface(string $class): self {
		if(!class_exists($class)) throw new Exception('Invalid class: ' . $class);
        $rc = new ReflectionClass($class);
        if($rc->isEnum()) {
            $slug = $this->getSlug($class, 'E');
            $values = [];
            foreach($class::cases() as $case) {
                $values[$case->name] = $case->value;
            }
            $this->registerArrayEnum($slug, $values);
            return $this;
        }

        $slug = $this->getSlug($class);

        if($definition = $this->getInterfaceDefinition($class)) {
            $this->registerRaw($slug, $definition);
        }

		// Groups parsing

		$rc = new ReflectionClass($class);

		foreach($rc->getMethods() as $rm) {
			foreach($rm->getAttributes(Groups::class) as $a) {
				foreach($a->getArguments() as $groups) {
					foreach($groups as $group) {
						if(!in_array($group, $this->groups)) {
							$this->groups[] = $group;
						}
					}
				}
			}
		}

		foreach($rc->getProperties() as $rp) {
			/** @var Groups $a */
			foreach($rp->getAttributes(Groups::class) as $a) {
				foreach($a->getArguments() as $groups) {
					foreach($groups as $group) {
						if(!in_array($group, $this->groups)) {
							$this->groups[] = $group;
						}
					}
				}
			}
		}

		// Groups parsing end

		return $this;
	}

	public function registerRaw(string $name, string $definition): self {
		if(isset($this->definitions[$name]))
			throw new Exception('Duplicate definition: ' . $name);
		$this->definitions[$name] = $definition;
		return $this;
	}

	public function getInterfaceDefinition(?string $class): ?string {
		if(!$class) return '';
		$rc = new ReflectionClass($class);
		/** @var Definition|null $definition */
		$definition = $this->classResolver->getAttribute($rc, Definition::class);

		$result = 'export interface ' . $this->getSlug($class) .
            ($definition ? ($definition->getValue() ? '<T>' : null) : null) . ' {' . PHP_EOL;

		$defaults = $rc->getDefaultProperties();

        $names = [];

        // properties
		foreach($rc->getProperties() as $rp) {
            $result .= $this->getRefDefinition($rp, $defaults, $names);
		}

        // methods (getters)
        foreach($rc->getMethods() as $rm) {
            if(preg_match('/^get/', $rm->getName())) {
                $result .= $this->getRefDefinition($rm, $defaults, $names);
            }
        }

		$result .= '}' . PHP_EOL . PHP_EOL;
		return $result;
	}

    private function getRefDefinition(ReflectionProperty|ReflectionMethod $r, array $defaults = [], array & $names = []): ?string {

        $result = '';
        $name = $r->getName();

        if($r instanceof ReflectionMethod) {
            $name = preg_replace('/^get/', '', $name);
            $name = strtolower(substr($name, 0, 1)) . substr($name, 1);
        }

        if($r instanceof ReflectionProperty) {
            if(!$r->getDeclaringClass()->hasMethod("get{$name}") && !$r->isPublic()) return null;
        } else {
            if(!$r->isPublic()) return null;
        }

        if(in_array($name, $names)) return null;
        $names[] = $name;

        //$rc = $r->getDeclaringClass();

        $groups = $this->classResolver->getAttribute($r, Groups::class);
        $hidden = $this->classResolver->getAttribute($r, Hidden::class);

        if($hidden) return null;

        $notBlank = $this->classResolver->getAttribute($r, NotBlank::class);
        $definition = $this->classResolver->getAttribute($r, Definition::class);
        $choice = $this->classResolver->getAttribute($r, Choice::class);
        /** @var EnumChoice|null $enumChoice */
        $enumChoice = $this->classResolver->getAttribute($r, EnumChoice::class);

        if($r instanceof ReflectionMethod) {
            $type = $r->getReturnType();
        } else {
            $type = $r->getType();
        }

        if($type instanceof ReflectionUnionType) {
            $types = [];
            foreach($type->getTypes() as $type) {
                $types[] = $this->getTypeDefinition($type, $definition);
            }
            $typeName = implode(' | ', $types);
            $nullable = false;
        } else {
            $typeName = $this->getTypeDefinition($type, $definition);
            $nullable = $type->allowsNull();
        }

        $q = '';

        if(isset($defaults[$name])) $q = '?';
        if(!isset($defaults[$name]) && !$notBlank) $q = '?';
        if(in_array('main', $groups?->getGroups() ?? [])) $q = '';

        if($choice && !$definition) $typeName = "'" . implode("' | '", $choice->choices) . "'" . ($nullable ? ' | null' : '');
        if($enumChoice && !$definition) {
            $typeName = $this->getSlug($enumChoice->enum, 'E') . ($nullable ? ' | null' : '');
        }
        $result .= "  " . $name . $q .  ': ' . $typeName  . ';' . PHP_EOL;
        return $result;
    }

    private function getTypeDefinition(ReflectionNamedType $type, ?Definition $definition): string {
        $typeName = $type->getName();
        if(isset(self::MAPPING[$typeName])) {
            $typeName = self::MAPPING[$typeName];
        } elseif(class_exists($typeName)) {
            $rc = new ReflectionClass($typeName);
            $typeName = $this->getSlug($typeName, $rc->isEnum() ? 'E' : 'I');
        }

        if($class = $definition?->value) {
            if(class_exists($class)) {
                $rc = new ReflectionClass($class);
                if($rc->isEnum()) {
                    $definition->value = $this->getSlug($class, 'E');
                }
            }
        }

        return ($definition?->value ?? $typeName) . ($type->allowsNull() ? ' | null' : '');
    }

	public function getSlug(?string $name, string $prefix = 'I'): string {
		if($name === 'bool') return 'boolean';
		if($name === 'int' || $name === 'integer' || $name === 'float' || $name === 'double') return 'number';
		if($name === 'mixed') return 'any';
		if(!class_exists($name)) return $name . '';
		if(!$name) return 'null';
		$name = explode('\\', $name);
		$name = array_pop($name);
		return $prefix . $name;
	}

	public function registerInterfacesFromDir(string $dir, string $namespace = 'App'): self {
		foreach($this->classResolver->getNames($dir, $namespace) as $class) {
			$this->registerInterface($class);
		}
		return $this;
	}

	public function getTypeScriptCode(): string {
		return trim(implode(PHP_EOL . PHP_EOL, array_values($this->definitions)) . PHP_EOL . PHP_EOL);
	}

    public function registerGroups(): void {
        $groups = array_values(array_unique($this->groups));
        $groups = array_filter($groups, fn(string $group) => !str_starts_with($group, 'ROLE_') && $group !== 'main');
        $this->registerArrayEnum('EFieldGroup', array_combine(array_map(function(string $group) {
            $group = preg_replace('/[^a-z0-9]/i', ' ', $group);
            $group = mb_convert_case($group, MB_CASE_TITLE);
            return preg_replace('/\s+/', '', $group);
        } , $groups), $groups));
    }

	public function getGroups(): array {
		return $this->groups;
	}

}
