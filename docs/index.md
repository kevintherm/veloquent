---
layout: default
title: Home
nav_order: 1
has_children: true
description: "Veloquent - The Open Source Laravel Backend"
permalink: /
---

# Veloquent

Veloquent is an open-source backend skeleton powered by Laravel. It provides standard BaaS features like real-time broadcasting, multi-provider authentication, and a flexible database abstraction layer, all within a developer-friendly ecosystem.

## Core Features

- **Dynamic Collection**: Create your schema on the fly.
- **Authentication Ready**: Auth collection provide authentication ready-to-use out of the box.
- **Real-time Broadcasting**: Native support for horizontal scaling with Reverb and custom workers.

## Documentation

Comprehensive documentation is available in the `docs` directory:

- [Introduction](getting-started/introduction.md)
- [Installation Guide](getting-started/quickstart.md)

## Installation Quick Start

If you have Docker installed, you can get started in minutes using Laravel Sail:

```bash
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed

# In separate terminals
./vendor/bin/sail artisan realtime:worker
./vendor/bin/sail artisan queue:work
```

For more detailed instructions, see the [Installation Guide](getting-started/quickstart.md).

## Known Issues

- **Circular Dependencies**: Circular dependency and cascade on delete may cause infinite loops in certain edge cases. Use with caution during high-frequency schema modifications.

## Roadmap & Progress

Please refer to [TODO.md](https://github.com/kevintherm/veloquent/blob/main/TODO.md) for the latest status and upcoming features.

## License

The Veloquent skeleton is open-sourced software licensed under the MIT License.


