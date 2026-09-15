<?php

namespace Waypoint\Models;

/**
 * Undocumented class
 */
class Route
{
    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $rawPath;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $method;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $regex;

    /**
     * @var string[]
     */
    public array $paramNames;

    /**
     * [controllerClass, methodName], verbatim.
     *
     * @var array{0: class-string, 1: string}
     */
    public array $handlerSpec;

    /**
     * @param string $method
     * @param string $path
     * @param array{0: class-string, 1: string} $handlerSpec
     */
    public function __construct(string $method, string $path, array $handlerSpec)
    {
        $this->method = strtoupper($method);
        $this->rawPath = '/' . ltrim(rtrim($path, '/'), '/');
        $this->handlerSpec = $handlerSpec;

        preg_match_all('#\{(\w+)\}#', $this->rawPath, $m);
        $this->paramNames = $m[1];
        $pattern = preg_replace('#\{\w+\}#', '([^/]+)', $this->rawPath);
        $this->regex = '#^' . $pattern . '$#';
    }
}
