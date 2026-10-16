<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Binds the signed-in user's JWT claims (`$req->jwt`) to a parameter typed with a FromArray DTO:
 * `#[Claims] UserClaims $user`. It implies #[Authenticated]: without a valid token the route answers 401 before
 * it runs, and so does a token whose claims don't fill the DTO. A nullable parameter (`?UserClaims`) is
 * optional instead: null without a token.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Claims
{
}
