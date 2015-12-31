<?php

class ApiSchemaController extends ApiController
{

    private static $allowed_actions = [
    "index" => "->isModelAllowed",
  ];


    public function index()
    {
        $modelName = $this->request->param('Model');
        $schema = array();
        $underscoreFields = Config::inst()->get($modelName, 'underscoreFields');
        foreach (singleton($modelName)->inheritedDatabaseFields() as $key => $value) {
            if ($underscoreFields) {
                $key = ApiDataObject::to_underscore($key);
            }
            $schema[$key] = $value;
        }
        return $this->sendData([
      "schema" => $schema
    ]);
    }

    public function isModelAllowed()
    {
        $allowedModels = $this->config()->get('allowedModels');
        $modelName = $this->request->param('Model');
        if (!is_array($allowedModels)) {
            $allowedModels = [];
        }
        return (in_array($modelName, $allowedModels));
    }
}
