<?php namespace Edgji\DataDoor;

use Illuminate\Support\ServiceProvider;
use Edgji\DataDoor\Mapping\MapInterface;

class DataDoorServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('edgji/data-door', 'edgji/data-door');
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
                return;

            $importRepository = $app['importengine.importer.repository'];

            foreach($config['importers'] as $id => $settings)
            {
                if ( ! isset($settings['maps']))
                    continue;

                try
                {
                    $import = $importRepository->get($id);
                }
                catch(\InvalidArgumentException $e)
                {
                    continue;
                }

                foreach($settings['maps'] as $mapClass)
                {
                    if ( ! class_exists($mapClass))
                    {
                        // TODO throw error / implement error handling
                        continue;
                    }

                    $map = $app->make($mapClass);

                    if ( ! $map instanceof MapInterface) {
                        throw new \InvalidArgumentException();
                    }
                    $map->mapFields($import->mappings());
                }
            }
            // TODO package into class
            //return new DataDoor();
        });

        $this->app['edgji.datadoor'];
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