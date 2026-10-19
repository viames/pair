# Pair Framework — Architecture

Read this when changing framework internals or making an architectural decision.
[AGENTS.md](AGENTS.md) owns workflow and conventions; [SKILL.md](SKILL.md) maps
components and local commands. The [v4 design](PAIR_V4_DESIGN.md) explains the
migration rationale in more detail.

## Runtime boundaries

Pair is a server-rendered MVC framework. Applications define modules under
`modules/`; the framework supplies dispatch, persistence, responses and optional
integrations. The explicit v4 path keeps these responsibilities separate:

| Layer | Responsibility | Source |
| --- | --- | --- |
| Application and router | Bootstrap, module/action resolution, access checks and dispatch | [Application](src/Core/Application.php), [Router](src/Core/Router.php) |
| Web controller | Orchestrate work, build state, return a response | [Controller](src/Web/Controller.php) |
| Request input | Immutable query/body/header access and typed accessors | [Input](src/Http/Input.php) |
| Persistence | Table mapping, casting, relations and queries | [ActiveRecord](src/Orm/ActiveRecord.php), [Query](src/Orm/Query.php) |
| Read contract | Explicit data exported to HTML state or API responses | [ReadModel](src/Data/ReadModel.php), [RecordMapper](src/Data/RecordMapper.php) |
| Output | Send a response; render page or fragment layouts with `$state` | [ResponseInterface](src/Http/ResponseInterface.php), [PageResponse](src/Web/PageResponse.php), [FragmentResponse](src/Web/FragmentResponse.php) |

Persistence objects and public read contracts serve different purposes. Map the
required data explicitly rather than exposing an entire record as an API payload.
A page state can be a plain typed object; it does not have to implement `ReadModel`.

## Web request flow

1. The application entry point loads Composer and calls `Application::getInstance()->run()`.
2. `initializeController()` selects API dispatch or the web controller flow.
   Web requests initialize the session and access checks before module execution.
3. The router selects the module and `<action>Action()` method, defaulting to `defaultAction()`.
4. The explicit controller constructor captures `Input` and calls `boot()`;
   it does not implicitly load a model or view.
5. The action performs application work and returns a `Pair\Http\ResponseInterface`.
6. The application calls `send()`. A `PageResponse` includes the module layout
   with `$state`; `renderTemplate()` wraps the captured page in the application template.
7. Other explicit responses, including fragments and JSON, own their body and
   bypass page-template wrapping. API modules and headless mode skip `renderTemplate()`.

See `Application::run()`, `initializeController()`, `runController()`, and
`renderTemplate()` for the dispatch and wrapping decisions.

## API request flow

[ApiController](src/Api/ApiController.php) extends the explicit web controller
with authentication helpers, [Request](src/Api/Request.php) validation and a
middleware pipeline. Custom validated request objects implement
[RequestData](src/Api/RequestData.php); response data can implement `ReadModel`.

Use the [API reference](https://github.com/viames/pair/wiki/API) for endpoint
contracts and [patterns](PAIR_PATTERNS.md) for implementation entry points.

## Legacy MVC bridge

`Pair\Core\Controller`, `Pair\Core\Model` and `Pair\Core\View` preserve the
older implicit model/view lifecycle. Its `_init()` hook and `renderView()` path
are distinct from the explicit controller's `boot()` and returned responses.

Inspect a module's base class before choosing a pattern. A local legacy fix does
not require a module migration. Use [UPGRADE_V4.md](UPGRADE_V4.md) for migration
steps and remaining manual work.

## ORM relationships

`ActiveRecord` resolves relations from model metadata, foreign keys and mapped
classes. `getParent()` caches parent results on the record; `getRelateds()`
returns a collection. Dynamic helpers and explicit model methods can use or
customize those paths. See [persistence and relation patterns](PAIR_PATTERNS.md#persistence-and-relations)
for prerequisites, examples and query-cost considerations.

## Frontend and extension boundaries

[PairUI](assets/PairUI.js) enhances server-rendered HTML through lightweight
`data-*` directives, stores and DOM bindings. It has no mandatory build step.
Form validation and other frontend helpers live beside it under `assets/`.

Installable packages in `src/Packages` use ZIP/manifests for modules, templates,
providers or custom records. Runtime extensions use explicit bootstrap
registrations through [RuntimeExtensionInterface](src/Core/RuntimeExtensionInterface.php)
and [AdapterRegistry](src/Core/AdapterRegistry.php). These are separate mechanisms;
see the [integration reference](https://github.com/viames/pair/wiki/Integrations).
