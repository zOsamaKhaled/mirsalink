<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language each page is served in: Arabic unless the person chose
 * English. A `?lang=` link makes the choice and a cookie keeps it.
 */
class SetLocale
{
    /**
     * The languages the interface is written in, the default first.
     *
     * @var list<string>
     */
    public const SUPPORTED = ['ar', 'en'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::localeFor($request);

        App::setLocale($locale);

        if ($request->cookie('locale') !== $locale) {
            Cookie::queue(cookie()->forever('locale', $locale));
        }

        return $next($request);
    }

    public static function localeFor(Request $request): string
    {
        foreach ([$request->query('lang'), $request->cookie('locale')] as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::SUPPORTED, true)) {
                return $candidate;
            }
        }

        $default = config('app.locale');

        return in_array($default, self::SUPPORTED, true) ? $default : self::SUPPORTED[0];
    }

    public static function direction(?string $locale = null): string
    {
        return ($locale ?? App::getLocale()) === 'ar' ? 'rtl' : 'ltr';
    }
}
