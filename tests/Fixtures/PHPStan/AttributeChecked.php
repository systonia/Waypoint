<?php

namespace Waypoint\Tests\Fixtures\PHPStan;

use Waypoint\Attributes\ListOf;
use Waypoint\Attributes\Returns;

final class AttributeChecked
{
    #[ListOf(AttributeChecked::class)]
    public array $children = [];

    public function __construct(
        #[ListOf(
            type: 'int',
            map: true
        )]
        public array $counts = [],
        #[ListOf('string')]
        public ?array $tags = null,
    ) {
    }

    #[Returns(
        type: AttributeChecked::class,
        list: true
    )]
    public function children(): array
    {
        return $this->children;
    }

    #[Returns(
        type: 'string',
        list: true
    )]
    public function wrongReturn(): array
    {
        return [1, 2];
    }

    #[Returns('int')]
    public function body(): \stdClass
    {
        return new \stdClass();
    }

    public function reads(): void
    {
        \PHPStan\dumpType($this->children);
        \PHPStan\dumpType($this->counts);
        \PHPStan\dumpType($this->tags);
        \PHPStan\dumpType($this->children());
        \PHPStan\dumpType($this->body());
    }

    public function count(string $key): int
    {
        return $this->counts[$key] ?? $this->counts['all'];
    }

    public function writes(): void
    {
        $this->children = [new self()];
        $this->children = ['wrongAssign'];
        new self(counts: ['a' => 1], tags: ['x']);
        new self(['wrongArgument']);
        new self(tags: [5]);
    }
}
