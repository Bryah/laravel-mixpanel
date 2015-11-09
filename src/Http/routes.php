<?php

use Emergingdzns\LaravelMixpanel\Http\Controllers\StripeWebhooksController;
use Illuminate\Support\Facades\View;

Route::controller('emergingdzns/laravel-mixpanel/stripe', StripeWebhooksController::class);
