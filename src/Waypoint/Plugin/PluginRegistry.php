<?php

namespace Waypoint\Plugin;

use RuntimeException;
use Waypoint\Container;

/** The registered plugins, in registration order, and their hooks sorted by type. */
final class PluginRegistry
{
    /** @var array<class-string<Plugin>, Plugin> */
    private array $plugins = [];

    public function add(Plugin $plugin): void
    {
        $class = get_class($plugin);
        if (isset($this->plugins[$class])) {
            throw new RuntimeException("Plugin {$class} is already registered.");
        }
        foreach ($plugin->requires() as $required) {
            if (!isset($this->plugins[$required])) {
                throw new RuntimeException("Plugin {$class} requires {$required}, which must be registered first.");
            }
        }
        foreach ($this->plugins as $other) {
            if ($other->name() === $plugin->name()) {
                throw new RuntimeException("Plugin name '{$plugin->name()}' is already taken by " . get_class($other) . '.');
            }
        }
        $this->plugins[$class] = $plugin;
    }

    /** @return list<Plugin> */
    public function all(): array
    {
        return array_values($this->plugins);
    }

    public function boot(Container $container): void
    {
        foreach ($this->plugins as $plugin) {
            $plugin->boot($container);
        }
    }

    /** @return list<class-string> */
    public function classes(): array
    {
        return array_merge([], ...array_map(fn(Plugin $p): array => $p->classes(), $this->all()));
    }

    /** @return list<callable> */
    public function middlewares(): array
    {
        return array_merge([], ...array_map(fn(Plugin $p): array => $p->middlewares(), $this->all()));
    }

    /** @return array<string, int> */
    public function cacheInputs(): array
    {
        return array_merge([], ...array_map(fn(Plugin $p): array => $p->cacheInputs(), $this->all()));
    }

    /**
     * Every hook of one type, with the owning plugin's name.
     * @template T of object
     * @param class-string<T> $type
     * @return array<string, T> plugin name => hook (one per plugin; a plugin provides each hook type at most once)
     */
    public function hooks(string $type): array
    {
        $hooks = [];
        foreach ($this->plugins as $plugin) {
            foreach ($plugin->hooks() as $hook) {
                if ($hook instanceof $type) {
                    $hooks[$plugin->name()] = $hook;
                }
            }
        }
        return $hooks;
    }

    /** @return array<string, callable> All view helpers; a name offered twice is a boot error. */
    public function viewHelpers(): array
    {
        $helpers = [];
        foreach ($this->hooks(ViewHelper::class) as $name => $hook) {
            foreach ($hook->helpers() as $helper => $callable) {
                if (isset($helpers[$helper])) {
                    throw new RuntimeException("View helper '{$helper}' from plugin '{$name}' is already provided by another plugin.");
                }
                $helpers[$helper] = $callable;
            }
        }
        return $helpers;
    }
}
