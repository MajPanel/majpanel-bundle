# MajpanelBundle

Reusable Majpanel administration functionality for Symfony applications.

## Requirements

- PHP 8.2 or newer
- Symfony 7.4 LTS or Symfony 8.x

## Local development

Install dependencies and run the test suite:

```bash
composer install
composer test
```

## Install in an application

During local development, add this repository as a Composer path repository in
the Symfony application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../majpanel-bundle",
            "options": { "symlink": true }
        }
    ]
}
```

Then install it:

```bash
composer require majpanel/majpanel-bundle:@dev
```

Symfony Flex normally enables the bundle automatically. Without Flex, add
`Majpanel\MajpanelBundle\MajpanelBundle::class => ['all' => true]` to
`config/bundles.php`.

## Source layout

- `src/Controller/` HTTP controllers
- `src/Command/` Symfony console commands
- `src/DependencyInjection/` bundle configuration and service loading
- `src/Entity/` optional Doctrine entities owned by the bundle
- `src/Repository/` persistence repositories
- `src/Service/` application services
- `assets/controllers/` Stimulus controllers
- `assets/react/` React and TypeScript source files
- `assets/styles/` Tailwind and application styles
- `config/` services and route definitions
- `templates/` Twig templates
- `translations/` translation catalogues
- `public/` publishable browser assets
- `tests/` automated tests
- `migrations/` production migrations owned exclusively by Majpanel

Application/demo migrations belong under `tests/Fixtures/Migrations/`; they
must not be registered as production migrations by applications using the
bundle.

Generated menu examples for demo entities belong under
`tests/Fixtures/templates/`. The runtime menu is written to the host
application's `templates/admin/_generated_menu.html.twig` by the `majpanel`
command.

## Frontend development

The frontend toolchain is kept in this repository so distributable browser
assets can be built as part of a release. Install PHP dependencies first,
because the Symfony UX npm packages resolve from `vendor/`:

```bash
composer install
npm install
npm run build
```

Compiled assets belong in `public/` and are installed into the host
application by Symfony's `assets:install` command.

The build uses `/bundles/majpanel/build` as its public path so it does not
collide with the host application's own `/build` directory.

## API Platform

An opt-in API Platform configuration is available at
`docs/config-examples/api_platform.yaml`. Copy its values into the host
application's `config/packages/api_platform.yaml` only when all API resources
should be restricted to authenticated administrators under `/admin`.

The bundle does not load these defaults automatically because API Platform's
`defaults` section affects every API resource in the host application. Public
operations must explicitly override `security` and, when needed, their route
prefix.

## Security

The bundle loads its `AdminUser` password hasher and entity provider from
`config/packages/security.yaml` and registers `MajpanelAuthenticator` as a
service. Symfony requires every firewall name and its order to be declared in
one application configuration section, so reserve the dedicated `majpanel`
firewall in the host application's `config/packages/security.yaml`, before its
catch-all `main` firewall:

```yaml
security:
    firewalls:
        majpanel:
            pattern: ^/(?:majpanel/admin(?:/|$)|api/admin(?:/|$)|api/docs(?:[./]|$))
        main:
            # Keep the host application's existing firewall configuration.
```

The bundle completes the declared `majpanel` firewall with its entity
provider, custom authenticator, login entry point, and CSRF-protected logout.

Symfony does not allow `security.access_control` to be merged from multiple
configuration files. Add the Majpanel rules to the host application's existing
`access_control` section:

```yaml
security:
    access_control:
        - { path: '^/majpanel/admin/login$', roles: PUBLIC_ACCESS }
        - { path: '^/api/docs(?:[./]|$)', roles: ROLE_ADMIN }
        - { path: '^/api/admin(?:/|$)', roles: ROLE_ADMIN }
        - { path: '^/majpanel/admin(?:/|$)', roles: ROLE_ADMIN }
```

When the host application has no `access_control` section, the bundle adds
these rules automatically. The `/api/docs` UI and its format variants are
therefore available only to authenticated `ROLE_ADMIN` users. When an
`access_control` section already exists, the bundle leaves it
untouched to avoid Symfony's non-mergeable configuration error.

The access-control rules are order-sensitive. Public login and API rules must
remain above the broader `/api` and `/admin` administrator rules.

Projects whose Doctrine entities do not use the default `App\Entity`
namespace can override the generator parameter in `config/services.yaml`:

```yaml
parameters:
    majpanel.entity_namespace: 'Domain\Entity'
```

All Majpanel route definitions and controllers live in the bundle. Symfony
still needs one route-discovery import in the host application's
`config/routes.yaml` (a Symfony Flex recipe can install this automatically):

```yaml
majpanel:
    resource: '@MajpanelBundle/config/routes.yaml'
```

Run the bundle migration and initialize a development installation:

```bash
php bin/console doctrine:migrations:migrate
php bin/console majpanel:init
```

The initializer also installs the Majpanel React/Encore scaffold when it is
missing, including `package.json`, the `majpanel` entry, Tailwind CSS, and the
shared `EntityCrudGrid` component. The grid uses API Platform's server-side
pagination links; its search box filters the currently loaded page. Compile it
after the first initialization:

```js
// webpack.config.js
Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('majpanel', './assets/majpanel.ts')
    .enableReactPreset()
    .enableStimulusBridge('./assets/controllers.json')
    .enablePostCssLoader()
    .enableTypeScriptLoader()
;
```

`majpanel:init` adds the entry automatically to an existing Encore chain. The
line can also be added manually as shown above if the host maintains a custom
Webpack configuration.

```bash
npm install
npm run dev
```

Existing files are preserved. JSON configuration such as `package.json` and
`assets/controllers.json` is merged so existing application dependencies and
Stimulus controllers remain intact.

When the host also uses AssetMapper, the bundle excludes Majpanel's raw
Tailwind input from its asset map. That stylesheet is compiled exclusively by
the Majpanel Webpack Encore entry.

`majpanel:create-admin` is an alias of `majpanel:init`. In development, the
defaults are `admin` and `123456`, and two sample Blog records are created.
Use `--no-demo` to skip sample data. The default password is rejected in the
production environment.

Reset an existing administrator password explicitly:

```bash
php bin/console majpanel:create-admin admin 'a-new-password' --reset-password --no-demo
```
