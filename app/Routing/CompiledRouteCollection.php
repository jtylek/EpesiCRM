<?php

namespace App\Routing;

use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection as BaseCompiledRouteCollection;

/**
 * Laravel's cached-route matcher, with the home page of an installation in a
 * folder (XAMPP's htdocs/epesi, a shared host's public_html/crm) fixed.
 *
 * Before matching, Laravel strips the trailing slash off REQUEST_URI.
 * "/epesi/" becomes "/epesi", which no longer starts with the script's folder
 * "/epesi/", so Symfony finds no base URL and matches the path "/epesi"
 * instead of "/". That fails, and the fallback to the routes outside the
 * cache answers 405 Method Not Allowed. The root has no slash worth trimming.
 */
class CompiledRouteCollection extends BaseCompiledRouteCollection
{
    protected function requestWithoutTrailingSlash(Request $request)
    {
        return $request->getPathInfo() === '/' ? $request : parent::requestWithoutTrailingSlash($request);
    }
}
