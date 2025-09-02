<?php

namespace Hakam\AiLogInspector\Model;

class LogDocumentModelFactory
{
    public static function create(array $modelConfig): LogDocumentModel
    {
        return new LogDocumentModel(
            $modelConfig['name'],
            $modelConfig['capabilities'] ?? [],
            $modelConfig['options'] ?? []
        );
    }
}