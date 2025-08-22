<?php

namespace Hakam\AiLogInspector\Model;

use Symfony\AI\Platform\Model;

class LogDocumentModel implements LogDocumentModelInterface
{
    private Model $model;

    public function __construct(
        string $modelName,
        array  $capabilities = [],
        array  $options = [])
    {
        $this->model = new Model($modelName, $capabilities, $options);
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}