<?php
namespace Cms\Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

class BaseModuleProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot()
    {
    }

    /**
     * Register the defined middleware.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * The commands to register.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Register view composers.
     *
     * @var array
     */
    protected $composers = [];

    /**
     * Register repository bindings to the IoC.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->registerMiddleware($this->app['router']);
        $this->registerModuleCommands();
        $this->registerModuleBindings();
        $this->registerModuleComposers();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    /**
     * Register the middleware.
     *
     * @param Router $router
     */
    private function registerMiddleware(Router $router)
    {
        if (!count($this->middleware)) {
            return;
        }
        foreach ($this->middleware as $module => $middlewares) {
            if (!count($middlewares)) {
                continue;
            }
            foreach ($middlewares as $name => $middleware) {
                $class = sprintf('Cms\Modules\%s\Http\Middleware\%s', $module, $middleware);
                $router->middleware($name, $class);
            }
        }
    }

    /**
     * Register the commands.
     */
    private function registerModuleCommands()
    {
        if (!count($this->commands)) {
            return;
        }
        foreach ($this->commands as $module => $commands) {
            if (!count($commands)) {
                continue;
            }
            foreach ($commands as $command => $class) {
                $this->app[$command] = $this->app->share(function () use ($module, $class) {
                    $class = sprintf('Cms\Modules\%s\Console\Commands\%s', $module, $class);
                    return new $class($this->app);
                });
                $this->commands($command);
            }
        }
    }

    /**
     * Register the bindings for this module.
     */
    private function registerModuleBindings()
    {
        if (!count($this->bindings)) {
            return;
        }
        foreach ($this->bindings as $namespace => $classes) {
            if (!count($classes)) {
                continue;
            }
            foreach ($classes as $class => $bindAs) {
                $this->app->bind(
                    implode('\\', [$namespace, $class]),
                    implode('\\', [$namespace, $bindAs])
                );
            }
        }
    }

    /**
     * Register the View Composers.
     */
    private function registerModuleComposers()
    {
        if (!count($this->composers)) {
            return;
        }
        foreach ($this->composers as $module => $composers) {
            if (!count($composers)) {
                continue;
            }
            foreach ($composers as $class => $views) {
                if (!is_array($views)) {
                    $views = [$views];
                }
                $class = sprintf('Cms\Modules\%1$s\Composers\%2$s', $module, $class);
                view()->composer($views, $class);
            }
        }
    }
}
