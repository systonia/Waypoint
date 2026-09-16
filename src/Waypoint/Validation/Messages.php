<?php

namespace Waypoint\Validation;

/**
 * Translates validation messages. The Validator looks this up in the container
 * (bound by a plugin, e.g. i18n) and interpolates the English text itself when
 * nothing is bound. $text is the English source text; it is also the catalog key.
 */
interface Messages
{
    /** @param array<string, scalar> $params Replace {placeholders} in the translated text. */
    public function translate(string $text, array $params): string;
}
