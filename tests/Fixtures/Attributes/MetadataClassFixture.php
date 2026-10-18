<?php

namespace Waypoint\Tests\Fixtures\Attributes;

use Waypoint\Attributes\{Ignore, Schema, Property, Inject, NoGzip, Sensitive, PII, Authenticated, Role, Permissions};

#[Ignore]
#[Schema('Widget')]
#[NoGzip]
#[Authenticated]
#[Role('admin')]
#[Permissions(['users.manage', 'users.delete'])]
class MetadataClassFixture
{
    #[Property]
    public string $name = '';

    #[Inject]
    public $dependency;

    #[Sensitive]
    public string $password = '';

    #[Sensitive(
        placeholder: '***'
    )]
    public string $customPlaceholder = '';

    #[PII]
    public string $email = '';
}
