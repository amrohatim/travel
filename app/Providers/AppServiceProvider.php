<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
    public static function redirectTo()
{
    $role = auth()->user()->role;

    if ($role == 'admin') return '/admin';
    if ($role == 'office') return '/office';
    return '/traveler';
}

}
