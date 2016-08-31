<?php

use Bryah\LaravelMixpanel\Http\Controllers\StripeWebhooksController;
use Illuminate\Support\Facades\View;

Route::controller('bryah/laravel-mixpanel/stripe', StripeWebhooksController::class);
