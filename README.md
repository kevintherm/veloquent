<p align="center">
    <img src="public/logo.svg" width="250" />
</p>

# Veloquent

Veloquent is an open-source backend skeleton powered by Laravel. It provides standard BaaS features like real-time broadcasting, multi-provider authentication, and a flexible database abstraction layer, all within a developer-friendly ecosystem.

## Core Features

- **Dynamic Collection**: Create your schema on the fly.
- **Authentication Ready**: Auth collection provide authentication ready-to-use out of the box.
- **Real-time Broadcasting**: Native support for horizontal scaling with Reverb and custom workers.

## Documentation

Comprehensive documentation is available in the `docs` directory:

- [Introduction](docs/getting-started/introduction.md)
- [Installation Guide](docs/getting-started/quickstart.md)

## Installation Quick Start

Install using composer:

```bash
composer create-project veloquent/veloquent awesome-baas
cd awesome-baas
php artisan serve --port=80
```

Veloquent post install script will automatically create a default tenant with the domain localhost, and then serve the project using PHP's built in server at port 80.
Next, visit [http://localhost](http://localhost).

Run these in separate terminals
```
./vendor/bin/sail artisan realtime:worker
./vendor/bin/sail artisan queue:work
```

For more detailed instructions, see the [Installation Guide](https://velophp.com/docs/quickstart).

## Known Issues

- **Circular Dependencies**: Circular dependency and cascade on delete may cause infinite loops in certain edge cases. Use with caution during high-frequency schema modifications.

## Roadmap & Progress

Please refer to [TODO.md](TODO.md) for the latest status and upcoming features.

## License

The Veloquent skeleton is open-sourced software licensed under the [MIT](LICENSE) License.


