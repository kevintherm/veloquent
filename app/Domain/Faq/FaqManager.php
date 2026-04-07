<?php

namespace App\Domain\Faq;

use Illuminate\Support\Collection;

class FaqManager
{
    /**
     * Retrieve all FAQ items.
     */
    public function getAll(): Collection
    {
        return collect([
            [
                'question' => 'Why?',
                'answer' => 'I wanted a PocketBase-like experience in Laravel without the VPS cost. While VPS hosting is affordable, PHP hosting is often even cheaper. Veloquent is heavily inspired by PocketBase\'s features, and although I don\'t know the exact implementations of PocketBase, I did study some implementations to get the essentials right.',
            ],
            [
                'question' => 'Do you offer hosting?',
                'answer' => 'Not at the moment. But since Veloquent is pure Laravel, you can host it anywhere PHP runs, shared hosting, VPS, or specialized Laravel platforms.',
            ],
            [
                'question' => 'Does it scale?',
                'answer' => 'Absolutely. It\'s Laravel at its core, so scaling is as straightforward as scaling any Laravel application.',
            ],
            [
                'question' => 'How can I run custom code?',
                'answer' => 'Right now, you can modify the source code directly. In the future, I plan to introduce hooks or an extension system so custom code can be separated from the core.',
            ],
            [
                'question' => 'Does it support Google or Facebook login?',
                'answer' => 'Veloquent uses Laravel Socialite under the hood. Any provider supported by Socialite will work, though not all are preconfigured. You\'re free to implement additional providers yourself, and there are many community Socialite drivers available.',
            ],
            [
                'question' => 'Does it come with a UI?',
                'answer' => 'Only the admin panel is included, no frontend UI is provided. You can integrate it with any frontend framework you like using the first-party SDK.',
            ],
            [
                'question' => 'Which databases can I use?',
                'answer' => 'Currently, Veloquent officially supports MySQL, PostgreSQL, and SQLite. MySQL required a workaround due to transactional DDL limitations. Other databases might work, but they haven\'t been tested yet. Future updates may expand support.',
            ],
            [
                'question' => 'How do I import or export data?',
                'answer' => 'Use the schema transfer feature available in the admin panel, it handles importing and exporting in a convenient way.',
            ],
            [
                'question' => 'Can I donate?',
                'answer' => 'Not yet planned, but contributions may be considered in the future.',
            ],
            [
                'question' => 'Where can I find help?',
                'answer' => 'You can ask questions on GitHub Discussions or reach out directly via [support@velophp.com](mailto:support@velophp.com).',
            ],
        ]);
    }
}