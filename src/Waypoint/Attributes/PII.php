<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Marks a DTO property as personally identifiable information (GDPR/DSGVO
 * Art. 4) -- a name, email address, phone number, or similar, as opposed
 * to #[Sensitive] (a credential/secret). Handled identically by
 * Waypoint::redact(): either attribute on a property is enough to replace
 * its value in the redacted copy. Kept as a separate attribute from
 * #[Sensitive] rather than folded into it so the two concerns stay
 * independently visible on a DTO -- a property can carry one, the other,
 * both, or neither, and a future consumer (e.g. a data-retention/export
 * tool) can still tell PII apart from a plain credential by which
 * attribute it finds.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class PII
{
    public function __construct(
        public string $placeholder = '**redacted**',
    ) {
    }
}
