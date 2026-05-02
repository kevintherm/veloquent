<?php

namespace Veloquent\Core\Infrastructure\Http\Controllers;

use Veloquent\Core\Http\Controllers\Controller;
use Veloquent\Core\Infrastructure\Traits\ApiResponse;

abstract class ApiController extends Controller
{
    use ApiResponse;
}
