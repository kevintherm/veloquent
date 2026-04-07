<?php

namespace App\Http\Controllers;

use App\Domain\Faq\FaqManager;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(FaqManager $faq): View
    {
        return view('faq', [
            'faqs' => $faq->getAll(),
        ]);
    }
}
