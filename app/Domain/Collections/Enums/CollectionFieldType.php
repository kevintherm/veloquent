<?php

namespace App\Domain\Collections\Enums;

enum CollectionFieldType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Float = 'float';
    case Double = 'double';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Datetime = 'datetime';
    case Timestamp = 'timestamp';
    case Json = 'json';
    case Longtext = 'longtext';
    case Bigint = 'bigint';
    case Tinyint = 'tinyint';
    case Char = 'char';
}
