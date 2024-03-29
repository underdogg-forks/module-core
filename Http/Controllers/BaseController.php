<?php
namespace Cms\Modules\Core\Http\Controllers;

use Nwidart\Modules\Routing\Controller;
use Illuminate\Foundation\Http\Response;
use Illuminate\Filesystem\Filesystem;
use Teepluss\Theme\Contracts\Theme;

class BaseController extends Controller
{
    /**
     * Theme instance.
     *
     * @var \Teepluss\Theme\Theme
     */
    protected $theme;

    /**
     * The theme to load.
     *
     * @var string
     */
    protected $themeName = null;

    /**
     * The currently loaded module.
     *
     * @var string
     */
    protected $module = null;

    /**
     * Controls the layout for a controller.
     *
     * @var string
     */
    public $layout = 'basic';

    /**
     * The view path to load.
     *
     * @var string
     */
    private $view = null;

    /**
     * Populates the view with contents.
     *
     * @var string
     */
    private $data = [];

    /**
     * The type of view, module, theme, app etc.
     *
     * @var string
     */
    private $type = null;

    /**
     * File from the IoC.
     *
     * @var string
     */
    private $file = null;

    public function __construct(Theme $theme, Filesystem $file)
    {
        $this->setDependencies($theme, $file);
        if (method_exists($this, 'boot')) {
            $this->boot();
        }
        // start a debug timer going
        class_exists('Debugbar') && app()->environment() !== 'testing'
            ? \Debugbar::startMeasure('module_timer', 'Module Run')
            : null;
    }

    public function __destruct()
    {
        class_exists('Debugbar') && app()->environment() !== 'testing'
            ? \Debugbar::stopMeasure('module_timer')
            : null;
    }

    public function setDependencies(Theme $theme, Filesystem $file)
    {
        $this->file = $file;
        // set some theme options up
        if (!isset($this->themeName)) {
            $this->themeName = config('cms.core.app.themes.frontend', 'default');
        }
        try {
            $this->theme = $theme->uses($this->themeName)->layout($this->layout);
        } catch (\Teepluss\Theme\UnknownThemeException $e) {
            $this->theme = $theme->uses('default')->layout($this->layout);
        }
        // figure out which module we are currently in
        $this->module = $this->getModule($this);
    }

    public function boot()
    {
    }

    /**
     * Gets the current modules name, presuming this is a CMS Module.
     *
     * @param string $class
     *
     * @return object
     */
    public function getModule($class)
    {
        $namespace = get_class($class);
        $module = explode('\\', $namespace);
        $module[2] = app('modules')->find(strtolower($module[2]));
        view()->share('_module', $module);
        return $module[2];
    }

    /**
     * Sets the current theme.
     *
     * @param string $theme
     *
     * @return bool
     */
    public function setTheme($theme = null)
    {
        if ($theme === null) {
            return false;
        }
        if ($this->theme->exists($theme)) {
            $this->themeName = $theme;
            $this->theme->uses($theme);
            return true;
        }
        return false;
    }

    /**
     * @param string $title
     * @param string $seperator
     */
    public function setTitle($title, $seperator = ' | ')
    {
        if (!is_object($this->theme)) {
            return false;
        }
        $this->theme->prependTitle($title . $seperator);
        return true;
    }

    /**
     * Verifies the layout exists before being set.
     *
     * @param string $layout
     *
     * @return bool
     */
    public function setLayout($layout = null)
    {
        if ($layout === null) {
            return false;
        }
        $layoutFile = sprintf(
            '%s/%s/layouts/%s.blade.php',
            public_path(config('theme.themeDir')),
            $this->themeName,
            $layout
        );
        if ($this->file->exists($layoutFile)) {
            $this->layout = $layout;
            $this->theme->layout($layout);
            return true;
        }
        return false;
    }

    /**
     * Returns a valid namespace string for the module.
     *
     * @param string $var The config var
     * @param string $module Overloads the module to use this name instead
     *
     * @return string
     */
    public function getModuleNamespace($var = null, $module = null)
    {
        $module = strtolower($module === null ? $this->module : $module);
        if ($var !== null) {
            return sprintf('%s::%s', $module, $var);
        }
        return $module;
    }

    /**
     * Determines where to load the view from.
     *
     * @param string $view Path to the view file
     * @param array $data Data to pass through to the view
     * @param string $type Where the view is [Module, Theme, App, Custom]
     */
    public function setView($view, $data = [], $type = 'module')
    {
        $type = strtolower($type);
        $supportedTypes = ['theme', 'app', 'module', 'custom'];
        if (!in_array($type, $supportedTypes) && substr($type, 0, 6) !== 'module') {
            $type = 'watch';
        } else {
            switch ($type) {
                case 'theme':
                    $type = 'scope';
                    break;
                case 'app':
                    $type = 'of';
                    break;
                case 'custom':
                    $type = 'load';
                    break;
                default:
                case $type === 'module' || substr($type, 0, 6) === 'module':
                    $module = strtolower($this->module);
                    if (str_contains($type, ':')) {
                        $type = explode(':', $type);
                        $module = $type[1];
                        $type = $type[0];
                    }
                    $type = 'of';
                    $view = $this->getModuleNamespace($view, $module);
                    break;
            }
        }
        $this->view = $view;
        $this->data = $data;
        $this->type = $type;
        return $this->theme->$type(partial($this->view), $this->data)->render();
    }

    public function outputMethod()
    {
        $call = debug_backtrace(); //presume last call?
        $class = $call[1]['class'];
        $method = $call[1]['function'];
        $method = new \ReflectionMethod($class, str_replace(array($class, '::'), '', $method));
        $filename = $call[0]['file'];
        $start_line = $method->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
        $end_line = $method->getEndLine();
        $length = $end_line - $start_line;
        $source = file($filename);
        $body = implode('', array_slice($source, $start_line, $length));
        echo '<pre><code>', print_r($body, true), '</code></pre>';
        echo '<hr />';
    }

    /**
     * Get the authenticated user.
     *
     * @return mixed
     */
    protected function user()
    {
        return app('Illuminate\Auth\AuthManager')->user();
    }

    /**
     * Get the auth instance.
     *
     * @return \Illuminate\Auth\AuthManager
     */
    protected function auth()
    {
        return app('Illuminate\Auth\AuthManager');
    }

    /**
     * Get the response factory instance.
     *
     * @return \Illuminate\Foundation\Http\Response
     */
    protected function response()
    {
        return response();
    }

    /**
     * Get the api dispatched.
     *
     * @return \Dingo\Api\Dispatcher
     */
    protected function api()
    {
        if (!$this->auth()->guest()) {
            return app('Dingo\Api\Dispatcher')->be($this->auth()->user());
        }
        return app('Dingo\Api\Dispatcher');
    }

    /**
     * Magically handle calls to certain properties.
     *
     * @param string $key
     *
     * @throws \ErrorException
     *
     * @return mixed
     */
    public function __get($key)
    {
        $callable = [
            'user', 'auth', 'response',
        ];
        if (in_array($key, $callable) && method_exists($this, $key)) {
            return $this->$key();
        }
        throw new ErrorException('Undefined property ' . get_class($this) . '::' . $key);
    }
}
