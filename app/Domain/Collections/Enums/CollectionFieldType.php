<?php

namespace App\Domain\Collections\Enums;

enum CollectionFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case Datetime = 'timestamp';
    case Email = 'email';
    case Url = 'url';
    case Json = 'json';
    case Relation = 'relation';
}
