<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SupabaseService;

class SupabaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(SupabaseService::class, function ($app) {
            // لا حاجة لتمرير معطيات هنا بعد التعديل في SupabaseService
            return new SupabaseService();
        });
    }

    public function boot()
    {
        //
    }
}
