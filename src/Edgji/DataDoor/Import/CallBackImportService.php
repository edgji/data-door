<?php namespace Edgji\DataDoor\Import;

class CallBackImportService {

    protected $callbacks = array();

    public function __construct($callbacks = array())
    {
        if (empty($callbacks))
            return;

        $this->callbacks = $callbacks;
    }

    public function importRow($objectOrItem = false)
    {
        dd($objectOrItem);
        foreach($this->callbacks as $callback)
        {
            if (is_callable($callback))
            {
                call_user_func($callback, $objectOrItem);
            }
        }
    }
}