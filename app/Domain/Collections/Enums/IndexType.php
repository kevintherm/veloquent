<?php

namespace App\Domain\Collections\Enums;

enum IndexType: string
{
    case Index = 'index';
    case Unique = 'unique';
}
