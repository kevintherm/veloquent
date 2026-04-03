# Veloquent

Veloquent is an open-source backend skeleton powered by Laravel. It provides standard BaaS features like real-time broadcasting, multi-provider authentication, and a flexible database abstraction layer, all within a developer-friendly ecosystem.

## Core Features

- **Real-time Broadcasting**: Native support for horizontal scaling with Reverb and custom workers.
- **Email System**: Flexible email templating and distribution.
- **Domain-Driven Design**: Clean architecture separating concerns into clear domains.
- **First-Class Testing**: Built with Pest PHP for reliable and readable tests.

## Documentation

Comprehensive documentation is available in the `docs` directory:

- [Introduction](docs/getting-started/introduction.md)
- [Installation Guide](docs/getting-started/installation.md)
- [Architecture Concepts](docs/architecture-concepts/README.md)

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

For more detailed instructions, see the [Installation Guide](docs/getting-started/installation.md).

## Known Issues

- **Circular Dependencies**: Circular dependency and cascade on delete may cause infinite loops in certain edge cases. Use with caution during high-frequency schema modifications.

## Roadmap & Progress

Please refer to [TODO.md](TODO.md) for the latest status and upcoming features.

## License

The Veloquent skeleton is open-sourced software licensed under the MIT License.


