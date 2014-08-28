<?php namespace Edgji\DataDoor;

use Edgji\DataDoor\Mapping\MapInterface;
use Mathielen\ImportEngine\Event\ImportConfigureEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DataDoor {

    private $import = null;

    private $importId = null;

    private $importerSettings = array();

    public function __construct(EventDispatcherInterface $eventDispatcher, array $importerSettings)
    {
        $this->importerSettings = $importerSettings;

        foreach($this->importerSettings as $importId => $settings)
        {
            $eventName = ImportConfigureEvent::AFTER_BUILD.'.'.$importId;
            $eventDispatcher->addListener($eventName, array($this, 'importConfigureEventListener'));
        }
    }

    public function importConfigureEventListener($event, $eventName)
    {
        if ($event instanceof ImportConfigureEvent) {
            $importId = str_replace(ImportConfigureEvent::AFTER_BUILD.'.', '', $eventName);

            $this->import = $event->getImport();
            $this->importId = $importId;

            $this->addMaps();
        }
    }

    protected function addMaps()
    {
        if ( ! isset($this->importerSettings[$this->importId]['maps']))
            return;

        foreach($this->importerSettings[$this->importId]['maps'] as $mapClass)
        {
            if ( ! class_exists($mapClass))
            {
                // TODO throw error / implement error handling
                continue;
            }

            if ( ! ($map = app($mapClass)) instanceof MapInterface) {
                throw new \InvalidArgumentException();
            }

            $map->mapFields($this->import->mappings());
        }
    }
}