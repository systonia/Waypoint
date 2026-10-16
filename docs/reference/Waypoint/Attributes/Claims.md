---
title: "Claims"
description: "Binds the signed-in user's JWT claims (`$req->jwt`) to a parameter typed with a FromArray DTO: `#[Claims] UserClaims $user`."
order: 100
generated: true
---

`Waypoint\Attributes\Claims` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Claims
```

Binds the signed-in user's JWT claims (`$req->jwt`) to a parameter typed with a FromArray DTO:
`#[Claims] UserClaims $user`. It implies #[Authenticated]: without a valid token the route answers 401 before
it runs, and so does a token whose claims don't fill the DTO. A nullable parameter (`?UserClaims`) is
optional instead: null without a token.
