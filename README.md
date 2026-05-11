<p align="center">
    <img src="resources/assets/logo.svg" width="250" />
</p>

[![Tests](https://github.com/kevintherm/veloquent/actions/workflows/run-tests.yml/badge.svg)](https://github.com/your-org/firelaravel/actions)


# Veloquent

Veloquent is an open-source Backend-as-a-Service (BaaS) built on Laravel. It provides authentication, database, storage, real-time capabilities, and multi-tenancy out of the box.

## Core Philosophy

Veloquent is designed to power multiple applications from a single instance, bringing multi-tenant capabilities directly to your workflow. Built on Laravel, it can be deployed anywhere a Laravel application runs. It also includes a built-in admin panel for easy management of your applications.

## Key Features

- **Dynamic Collections**: Create and manage your database tables through a simple UI or API.
- **Rich Field Types**: Support for a variety of field types, including `Text`, `Number`, `Boolean`, `Datetime`, `Email`, `Relation`, and more.
- **Expression-based Rules**: Secure your data with granular access control using Firebase-like rules.
- **User Management**: Built-in authentication for your users with support for standard and OAuth flows.
- **Real-time Subscriptions**: Build reactive applications with ease using WebSockets.
- **Multi-tenancy**: Power multiple applications from a single instance.

## Agent Support

You can access the documentation in pure Markdown format by appending a `.md` extension to any page URL.

Example:
[docs/2.x/getting-started/introduction](https://velophp.com/docs/2.x/getting-started/introduction) → [docs/2.x/getting-started/introduction.md](https://velophp.com/docs/2.x/getting-started/introduction.md)

Alternatively, you can use `/llms.txt` or `/llms-full.txt` to retrieve fully compiled Markdown documentation, ready for use in agent development.

[https://velophp.com/llms.txt](https://velophp.com/llms.txt)

## Getting Started

To get started with Veloquent, the quickest path is following the [Quickstart Guide](https://velophp.com/docs/quickstart).

### v2.0 Transition

Starting with version 2.0, Veloquent has transitioned to a package-based architecture (`veloquent/core`). This change streamlines updates and allows for better integration into existing Laravel 13+ applications.

For users, installation via Composer remains unchanged. For developers, the Packagist namespace has been updated: `veloquent/core` is now used for the core package, while `veloquent/veloquent` provides the full project skeleton.


## Roadmap & Progress

Please refer to [TODO.md](TODO.md) for the latest status and upcoming features.

## License

The Veloquent skeleton is open-sourced software licensed under the [MIT](LICENSE) License.


