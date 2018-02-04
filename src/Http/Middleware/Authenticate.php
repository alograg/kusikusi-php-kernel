<?php

namespace Cuatromedios\Kusikusi\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Cuatromedios\Kusikusi\Models\Http\ApiResponse;

class Authenticate
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if ($this->auth->guard($guard)->guest()) {
            $response = new ApiResponse(NULL, NULL, ApiResponse::TEXT_UNAUTHENTICATED, ApiResponse::STATUS_UNAUTHENTICATED);
            return ($response)->response();
        }

        return $next($request);
    }
}
