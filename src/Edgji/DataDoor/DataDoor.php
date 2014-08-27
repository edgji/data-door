<?php namespace Edgji\DataDoor;

use Edgji\DataDoor\Mapping\MapInterface;
use Mathielen\DataImport\Event\ImportProcessEvent;
use Mathielen\ImportEngine\Importer\ImporterRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DataDoor {

    public function __construct(array $importers, EventDispatcherInterface $eventDispatcher, ImporterRepository $importRepository)
    {
        $this->importers = $importers;
        $this->importRepository = $importRepository;
        $eventDispatcher->addListener(ImportProcessEvent::AFTER_PREPARE, array($this, 'importProcessEventListener'));
    }

    public function importProcessEventListener($event)
    {
        if ($event instanceof ImportProcessEvent) {
            $this->addMaps();
        }
    }

    public function setImportId($id)
    {
        $this->importId = $id;
    }

    protected function addMaps()
    {
        if (isset($this->importers) && isset($this->importers[$this->importId]))
        {
            $settings = $this->importers[$this->importId];
            $this->linkMapsByImportId($this->importId, $settings);
        }
        else
        {
            foreach($this->importers as $importId => $settings)
            {
                $this->linkMapsByImportId($this->importId, $settings);
            }
        }

    }

    private function linkMapsByImportId($id, $settings)
    {
        if ( ! isset($settings['maps']))
            return;

        try
        {
            $import = $this->importRepository->get($id);
        }
        catch(\InvalidArgumentException $e)
        {
            return;
        }

        foreach($settings['maps'] as $mapClass)
        {
            if ( ! class_exists($mapClass))
            {
                // TODO throw error / implement error handling
                continue;
            }

            $map = app()->make($mapClass);

            if ( ! $map instanceof MapInterface) {
                throw new \InvalidArgumentException();
            }
            $map->mapFields($import->mappings());
        }
    }
}