<?php

namespace Veloquent\Core\Support\Http\Controllers;

use Veloquent\Core\Http\Controllers\Controller;
use Veloquent\Core\Support\Traits\ApiResponse;

abstract class ApiController extends Controller
{
    use ApiResponse;
}
