<?php namespace Edgji\DataDoor;

use Edgji\DataDoor\Mapping\MapInterface;
use Illuminate\Support\ServiceProvider;

class DataDoorServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('edgji/data-door', 'edgji::datadoor');
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
        $this->app['datadoor'] = $this->app->share(function($app)
        {
            $config = '';
            $importRepository = $app['importengine.importer.repository'];

            foreach($config['importers'] as $id => $settings)
            {
                if ( ! isset($settings['maps']))
                    continue;

                try
                {
                    $import = $importRepository->get($id);
                    //$import->mappings();
                    foreach($settings['maps'] as $mapClass)
                    {
                        $map = $app->make($mapClass);

                        if ( ! $map instanceof MapInterface) {
                            throw new \InvalidArgumentException();
                        }
                        $map->mapFields($import->mapping());
                    }
                }
                catch(\InvalidArgumentException $e)
                {
                    continue;
                }
            }
            // TODO package into class
            //return new DataDoor();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }
}