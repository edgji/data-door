<?php namespace Edgji\DataDoor;

use Illuminate\Support\ServiceProvider;
use Mathielen\ImportEngine\ValueObject\ImportConfiguration;

class DataDoorServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('edgji/data-door', 'edgji/data-door');

        $this->conditionallyBindRoutes();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerDataDoor();
    }

    protected function registerDataDoor() {
        $this->app['edgji.datadoor'] = $this->app->share(function($app)
        {
            $config = $app['config']['edgji/data-door::config'];

            if ( ! isset($config['importers']))
                return null;

            $eventDispatcher = $app['importengine.import.eventdispatcher'];

            $importRepository = $app['importengine.importer.repository'];

            return new DataDoor($config['importers'], $eventDispatcher, $importRepository);
        });
    }

    private function conditionallyBindRoutes()
    {
        $config = $this->app['config']['edgji/lie::config'];

        // only bind import routes if default routing is enabled
        if ( ! (isset($config['enable_default_routing']) && $config['enable_default_routing']))
            return;

        // let's pass along the app container
        $app = $this->app;

        $this->app['router']->group($config['routing'], function($router) use ($app, $config)
        {
            $importers = array_keys($config['importers']);

            $storageProvidersRegex = $this->buildStorageProvidersRegex($config);

            foreach($importers as $importer)
            {
                // determine http method
                // if method does not exists or no default is defined skip binding route
                if ( ! $method = $this->importerHttpMethod($config, $importer)) continue;

                $route = $router->$method($importer.'/{storageProviderName?}', function($storageProviderName = false) use ($app, $importer, $config)
                {
                    app('edgji.datadoor')->setImportId($importer);
                    if ( ! $storageProviderName) $storageProviderName = key($config['storageprovider']);

                    $storageProviderType = $config['storageprovider'][$storageProviderName]['type'];

                    //handle the uploaded file
                    $storageLocator = $app['importengine.import.storagelocator'];

                    switch($storageProviderType)
                    {
                        case 'upload':
                            $requestFiles = $app['request']->file();
                            $fileId = reset($requestFiles);
                            break;

                        case 'directory':
                            $fileName = array_get($config['importers'][$importer], 'preconditions.filename', false);
                            if ( ! $fileName) $fileName = array_get($config['storageprovider'], $storageProviderName.'.file', false);

                            $path = str_replace("{app_storage}", $this->app['path.storage'], $config['storageprovider'][$storageProviderName]['path']);
                            $fileId = $fileName ? "{$path}/{$fileName}" : false;
                            break;

                        default:
                            $fileId = false;
                    }

                    if ( ! $fileId) return; // should probably throw invalid file exception instead

                    $storageSelection = $storageLocator->selectStorage($storageProviderName, $fileId);

                    //create a new import configuration with your file for the specified importer
                    //you can also use auto-discovery with preconditions (see config above and omit 2nd parameter here)
                    $importConfiguration = new ImportConfiguration($storageSelection, $importer);

                    //build the import engine
                    $importBuilder = $app['importengine.import.builder'];
                    $importBuilder->build($importConfiguration);

                    //run the import
                    $importRunner = $app['importengine.import.runner'];
                    $importRun = $importRunner->run($importConfiguration->toRun());

                    return $importRun->getStatistics();
                });

                if ($storageProvidersRegex)
                {
                    $route->where('storageProviderName', $storageProvidersRegex);
                }
            }
        });
    }

    private function buildStorageProvidersRegex($config)
    {
        if ( ! isset($config['storageprovider'])) return false;

        $storageProviders = array_keys($config['storageprovider']);

        return "(" . implode('|', $storageProviders) . ")";
    }

    private function importerHttpMethod($config, $importer)
    {
        if (isset($config['importers'][$importer]['http_method']))
        {
            $method = $this->validateHttpMethod($config['importers'][$importer]['http_method']);
            if ($method) return $method;
        }

        return $this->validateHttpMethod($config['default_http_method']) ?: false;
    }

    private function validateHttpMethod($method)
    {
        $method = strtolower($method);

        if ( ! in_array($method, array('get', 'post'))) return false;

        return $method;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('edgji.datadoor');
    }
}