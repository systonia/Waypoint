<?php

namespace Waypoint\Routing;

use ReflectionClass;
use Waypoint\Attributes\Inject;

/** Follows #[Inject] properties/constructor parameters transitively from the attached controllers to find every class the container must construct at boot. */
final class ServiceDiscovery
{
    /**
     * @param class-string[] $roots
     * @return class-string[] $roots plus everything they (transitively) inject, in discovery order.
     */
    public static function discover(array $roots): array
    {
        $all = $roots;
        $queue = $roots;
        $seen = [];

        while ($queue) {
            $class = array_shift($queue);
            if (!class_exists($class) || isset($seen[$class])) {
                continue;
            }
            $seen[$class] = true;

            $rc = new ReflectionClass($class);
            $members = $rc->getProperties();
            $constructor = $rc->getConstructor();
            if ($constructor !== null) {
                $members = [...$members, ...$constructor->getParameters()];
            }

            foreach ($members as $member) {
                if ($member->getAttributes(Inject::class) === []) {
                    continue;
                }
                $type = PropertyInjector::namedType($member);
                if ($type !== null && class_exists($type) && !in_array($type, PropertyInjector::PER_REQUEST, true) && !in_array($type, $all, true)) {
                    $all[] = $type;
                    $queue[] = $type;
                }
            }
        }

        return $all;
    }
}
