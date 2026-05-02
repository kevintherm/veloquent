<?php

namespace Veloquent\Core\Domain\SchemaManagement\Enums;

enum SchemaOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Drop = 'drop';
}
