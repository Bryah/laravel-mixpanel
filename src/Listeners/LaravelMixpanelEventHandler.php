<?php namespace Bryah\LaravelMixpanel\Listeners;

use Bryah\LaravelMixpanel\LaravelMixpanel;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as CurrentRequest;

class LaravelMixpanelEventHandler
{
    protected $guard;
    protected $mixPanel;
    protected $request;

    /**
     * @param Request         $request
     * @param Guard           $guard
     * @param LaravelMixpanel $mixPanel
     */
    public function __construct(Request $request, Guard $guard, LaravelMixpanel $mixPanel)
    {
        $this->guard = $guard;
        $this->mixPanel = $mixPanel;
        $this->request = $request;
    }

    /**
     * @param array $event
     */
    public function onUserLoginAttempt(array $event)
    {
        $email = (array_key_exists('email', $event) ? $event['email'] : '');
        $password = (array_key_exists('password', $event) ? $event['password'] : '');

        $user = App::make(config('auth.model'))->where('email', $email)->first();

        if ($user
            && ! $this->guard->getProvider()->validateCredentials($user, ['email' => $email, 'password' => $password])
        ) {
            $this->mixPanel->identify($user->getKey());
            $this->mixPanel->track('Session', ['Status' => 'Login Failed']);
        }
    }

    /**
     * @param Model $user
     */
    public function onUserLogin(Model $user)
    {
        if (!@config('services.mixpanel.ignoredIds') || !in_array($user->id, config('services.mixpanel.ignoredIds'))) {
            $firstName = $user->first_name;
            $lastName = $user->last_name;

            if ($user->name) {
                $nameParts = explode(' ', $user->name);
                array_filter($nameParts);
                $lastName = array_pop($nameParts);
                $firstName = implode(' ', $nameParts);
            }

            $data = [
                '$first_name' => $firstName,
                '$last_name' => $lastName,
                '$name' => $user->name,
                '$email' => $user->email,
                '$created' => ($user->created_at
                    ? $user->created_at->format('Y-m-d\Th:i:s')
                    : null),
            ];

            if (config('services.mixpanel.appendData')) {
                $helperFunction = config('services.mixpanel.appendData');
                $appendData = $helperFunction($user);
                if (count($appendData) > 0) {
                    $data = array_merge($data,$appendData);
                }
            }
            array_filter($data);

			if (Cookie::get('DMWAdminUser')) {
				// skip it
			} else {
            	$this->mixPanel->identify($user->getKey());
				$this->mixPanel->people->set($user->getKey(), $data, $this->request->ip());
				$this->mixPanel->track('Session', ['Status' => 'Logged In']);
			}
        }
    }

    /**
     * @param Model $user
     */
    public function onUserLogout(Model $user = null)
    {
        if (@$user && (!@config('services.mixpanel.ignoredIds') ||
                                                    !in_array($user->id, config('services.mixpanel.ignoredIds')))) {
            $this->mixPanel->identify($user->getKey());
        }

        $this->mixPanel->track('Session', ['Status' => 'Logged Out']);
    }

    /**
     * @param $route
     */
    public function onViewLoad($route)
    {
        if (Auth::check()) {
            $this->mixPanel->identify(Auth::user()->id);
        }

        $routeAction = $route->getAction();
        $route = (is_array($routeAction) && array_key_exists('as', $routeAction) ? $routeAction['as'] : null);

        $trackIt = true;
        if (@config('services.mixpanel.ignoredRoutes')) {
            foreach(config('services.mixpanel.ignoredRoutes') as $ignoredRoute) {
                if (strstr($route, $ignoredRoute)) {
                    $trackIt = false;
                }
            }
        }

        if ($trackIt === true) {
            $this->mixPanel->track('Page View', ['Route' => $route]);
        }
    }

    /**
     * @param Dispatcher $events
     */
    public function subscribe(Dispatcher $events)
    {
        /* We did these in our own events handler
        $events->listen('auth.attempt', 'Bryah\LaravelMixpanel\Listeners\LaravelMixpanelEventHandler@onUserLoginAttempt');
        $events->listen('auth.login', 'Bryah\LaravelMixpanel\Listeners\LaravelMixpanelEventHandler@onUserLogin');
        $events->listen('auth.logout', 'Bryah\LaravelMixpanel\Listeners\LaravelMixpanelEventHandler@onUserLogout');
        $events->listen('router.matched', 'Bryah\LaravelMixpanel\Listeners\LaravelMixpanelEventHandler@onViewLoad');
        */
    }
}
