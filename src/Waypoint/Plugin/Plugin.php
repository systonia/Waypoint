<?php

namespace Waypoint\Plugin;

use Waypoint\Container;

/**
 * A plugin is one Composer package with one class implementing this and at
 * most one Options class. It only declares: which classes to attach, which
 * middlewares to add, which hooks (the other interfaces in this namespace)
 * it provides, and which files its compiled output depends on. Registered
 * with `$app->plugin(new XPlugin())` before attach(). See PluginBase for
 * the no-op defaults.
 */
interface Plugin
{
    /** Short unique name ('i18n'): the plan namespace for its route data, and the prefix in logs. */
    public function name(): string;

    /** @return list<class-string<Plugin>> Plugins that must be registered before this one. */
    public function requires(): array;

    /** @return list<class-string> Controllers and #[Manager] classes attach() adds. */
    public function classes(): array;

    /** @return list<callable> App-level middlewares, added at attach() in registration order. */
    public function middlewares(): array;

    /** @return list<object> Instances of Endpoint, RouteAttributeCompiler, Guard, ResponseHook, ArgumentBinder, Renderer, ViewHelper, ClientAsset. */
    public function hooks(): array;

    /** @return array<string, int> {path => mtime} of files whose change must rebuild the route cache. */
    public function cacheInputs(): array;

    /** Called once at attach(), before the Router is built: bind services, read options. */
    public function boot(Container $container): void;
}
