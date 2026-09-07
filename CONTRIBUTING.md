# Contributing to Targus

Thank you for your interest in contributing to Targus! This document provides guidelines and information for developers working on the codebase.

## Table of Contents

- [Code Style](#code-style)
- [AI and OpenAI integrations](#ai-and-openai-integrations) (includes [RAG](#rag-retrieval-augmented-generation))
- [List pages and data tables (Livewire)](#list-pages-and-data-tables-livewire)
- [Multi-Language Support](#multi-language-support)
- [Security by Design](#security-by-design)
- [Client portal separation (mandatory)](#client-portal-separation-mandatory)
- [Concurrency & multi-user editing](#concurrency--multi-user-editing)
- [Development Workflow](#development-workflow)
- [Architecture Overview](#architecture-overview)
- [Project context quick links (menu-as-workflow)](#project-context-quick-links-menu-as-workflow)
- [Key Features](#key-features)
- [Testing](#testing)
- [Database Migrations](#database-migrations)
- [Pull Request Process](#pull-request-process)

## Code Style

### PHP (Laravel)

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
- Use type hints and return types where possible
- Follow Laravel conventions for naming and structure
- Use meaningful variable and method names

### JavaScript

- Use ES6+ features
- Follow consistent naming conventions (camelCase for variables/functions, PascalCase for classes)
- Add JSDoc comments for classes and complex functions
- Use async/await instead of promises where possible

### CSS/Tailwind

- Use Tailwind CSS utility classes
- Follow the existing design system
- Ensure dark mode support where applicable

## AI and OpenAI integrations

Heavy chat completions (large prompts, high `max_tokens`, executive summaries) need **longer HTTP timeouts** than quick calls. The UI should also **block interaction** for multi‑minute operations so users do not navigate away or trigger duplicate actions.

### HTTP timeouts (backend)

- **Default** short timeout: `config('services.openai.timeout')` (env: `OPENAI_TIMEOUT`, often 30s).
- **Long** calls: `config('services.openai.long_timeout')` (env: `OPENAI_LONG_TIMEOUT`, default **300s** in `config/services.php`). Raise or lower per deployment; keep this **at or below** your reverse proxy / PHP `max_execution_time` / `fastcgi_read_timeout` in production.
- Use **`App\Support\AIHttpTimeout::mergeForChat($options, $maxTokens, $promptCharLength)`** for non–`AIPromptProfile` `generateChat` / `chat` calls, or ensure **`AIPromptProfile::toChatOptions()`** is used: long timeout applies when `maxCompletionTokens` is high or the prompt is very long.
- **Unit tests** for timeout helpers: `tests/Unit/AIHttpTimeoutTest.php` (add cases when you change the rules).

### Long-running request UI (frontend)

- The global **blocking overlay** is **`resources/views/components/ai-long-request-overlay.blade.php`**, included from `layouts.app` as **`<x-ai-long-request-overlay />`**.
- **API:** `window.openAiLongRequestModal({ title?, detail?, hint? })` and `window.closeAiLongRequestModal()`. **Always** call `close` in a `finally` block after the `fetch` (or equivalent) completes or fails.
- **Copy:** Prefer **`window.TargusAiLongRequest.strings`** (keyed in the component script from `__()`), not hardcoded English in page scripts.
- **i18n:** Add user-visible strings to **`common.ai.*`** in both **`lang/en/common.php`** and **`lang/de/common.php`** (e.g. `long_request_title`, `long_request_executive_summary`, `long_request_hint`).
- **When to use the overlay** vs. a small button spinner: use the **overlay** for long server round-trips (executive summaries, questionnaire summaries, PPT generation with AI, AI schedule, workflow full assessment summary, maturity executive summary). **Short** one-off comments can keep **button + spinner** only.

### PR checklist (AI changes)

- [ ] New or changed AI endpoints use **appropriate** timeout merging (`AIHttpTimeout` and/or `AIPromptProfile`).
- [ ] Long user-visible waits use the **overlay** and **`finally`** to close it.
- [ ] New strings exist in **en** and **de** under **`common.ai`**.
- [ ] Large project/RFP/notes/catalog context uses **RAG retrieval** (`RagContextService` / typed helpers) when RAG is enabled — do not dump entire documents into prompts.
- [ ] New domain content that should be searchable is wired through **`RagIngest`** (or an existing adapter) so chunks stay in sync.

### RAG (Retrieval-Augmented Generation)

App OLTP stays on MySQL/SQLite (`DB_*`). Vectors live on a **separate** PostgreSQL + pgvector connection (`rag` / `RAG_DB_*`). Do **not** overwrite Laravel Cloud `DB_*` with Postgres RAG credentials.

| Concern | Where |
|---------|--------|
| Design, ingest/retrieve, ops | [docs/RAG_ARCHITECTURE.md](./docs/RAG_ARCHITECTURE.md) |
| AI provider layer | [docs/AI_ARCHITECTURE.md](./docs/AI_ARCHITECTURE.md) |
| Feature flag | `config('rag.enabled')` / `RAG_ENABLED` |
| Shared retrieve entry | `App\Services\Rag\RagContextService` |
| Ingest | `RagIngest` → `ProcessRagSourceJob` (`ai_processing` queue) |

**Local setup (when working on RAG):**

```bash
docker compose -f docker-compose.rag.yml up -d
# Set RAG_ENABLED=true and RAG_DB_* (see .env.example / RAG_ARCHITECTURE.md)
php artisan migrate          # app DB + rag connection migrations
# or: php artisan rag:migrate --force
php artisan queue:work --queue=ai_processing,default
```

**Finding propose (measures / root causes):** hybrid path — candidates come from the **database** (library / orphans); when RAG is on, **vector similarity re-ranks** those candidates. Only a capped top slice (`finding_ai.prompt_candidate_cap`) is embedded in the LLM prompt so **new external ideas** are still required (`min_external_ideas`). The modal still shows **DB field excerpts**, not raw vector chunk text. Extra project context may use `RagTypedContextService` / RFP+notes helpers. Prefer extending that pattern over stuffing full libraries into the prompt.

## List pages and data tables (Livewire)

New **project-scoped list** screens (searchable indexes with filters and tabular data) should follow the same layout patterns as existing list UIs so headers, spacing, tables, and dark mode stay consistent. Align new Blade with the structural conventions below; for authorization, query scoping, and `$queryString` wiring, see [Livewire Best Practices](#livewire-best-practices) and *Index components with search & filters* in that section.

### Page header

- **Top block:** `mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between`.
- **Title:** `text-3xl font-bold text-secondary-500 dark:text-white`; optional **subtitle** under it: `text-gray-400 dark:text-gray-400 mt-1`.
- **Actions:** `flex flex-wrap items-center gap-2 shrink-0`. Use `<x-back-button>` to the parent project (or appropriate parent) when the list is nested under a project. Primary actions (e.g. create) use the standard filled button styles (`px-4 py-2`, `bg-secondary-500 dark:bg-white`, rounded-lg) and **`@can` / policy** checks.

### Collapsed statistics accordion

- Prefer a **collapsed statistics panel** above filters (same chrome as Measures / Findings), not always-visible KPI grids.
- **Wrapper:** `mb-3 bg-white dark:bg-secondary-500 rounded-lg shadow border border-gray-200 dark:border-gray-600` with Alpine `x-data="{ statsOpen: false }"`.
- **Toggle:** full-width button with `data-collapsed-panel-toggle`, chart icon, translated Statistics label (`*.tab_statistics` / `*.overview.tab_statistics`), and compact **summary badges** (e.g. Total / Open / Completed counts) on the closed header.
- **Expanded body:** `x-show="statsOpen"` with `x-cloak` / `x-transition`; place the existing summary cards (and optional charts) inside. Start **closed** so the table remains the primary content.
- Skip this block only when the feature truly has no aggregate metrics.

### Search and filters

- **Collapsible panel:** `mb-6 bg-white dark:bg-secondary-500 rounded-lg shadow border border-gray-200 dark:border-gray-600`, with Alpine.js `x-data="{ filtersOpen: @js($filtersOpen) }"` on the wrapper. In `mount()`, set `$filtersOpen` from `request()->anyFilled([...])` (and/or when default filter values are non-empty) so shared URLs open the panel when filters apply.
- **Panel header:** full-width button toggling `filtersOpen`, with filter icon + translated “Search” and “Filter” (`common.actions.search`, `common.actions.filter`). When any filter is active, a small **badge** may show the current result count.
- **Fields:** labeled controls (`block text-sm font-medium text-secondary-500 dark:text-gray-200 mb-2`); **search** input uses `wire:model.live.debounce.500ms="search"` and shared input classes (`w-full px-4 py-3 text-base border … rounded-lg … touch-manipulation`). **Selects** use `wire:model.live` for immediate refiltering.
- **Clear:** when at least one filter is set, show a **Clear filters** action that resets filter properties, calls `resetPage()`, and refreshes any dependent counts.
- **Livewire:** use `WithPagination`; define `$queryString` for each filter with `['except' => '']`; in `updatingSearch()` / `updating{Filter}()` call `resetPage()` (and update stats if applicable).

### Loading state

- A **global loading** strip for the list: `wire:loading` with spinner + translated loading label (`common.actions.loading`). Wrap the main **table/card** in `wire:loading.remove` so content is replaced while Livewire runs.

### Data table

- **Outer card:** `bg-white dark:bg-secondary-500 rounded-lg shadow-md border border-gray-200 dark:border-gray-600 overflow-hidden` (optionally only on the table section if the header/filters are separate).
- **Scroll:** inner `overflow-x-auto` so wide tables work on small screens.
- **Table:** `w-full divide-y divide-gray-200 dark:divide-gray-600 min-w-full`.
- **Header row:** `thead` with `bg-gray-50 dark:bg-secondary-600`; `th` cells use `px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider` (add `text-right` / `whitespace-nowrap` / `min-w-[…]` as needed).
- **Body:** `tbody` with `divide-y divide-gray-200 dark:divide-gray-600`; each **`<tr wire:key="…">`** must have a stable key; row hover: `hover:bg-gray-50 dark:hover:bg-secondary-600 transition-colors`.
- **Actions column:** icon links with consistent padding (`p-2`, primary/red hover backgrounds) and `title` / `aria` from translated strings.
- **Empty state:** `@forelse` / `@empty` row spanning all columns, centered padding (`px-6 py-12 text-center`), translated empty copy.

### Pagination

- Below the table, **only if** the paginator has multiple pages: `@if($items->hasPages())` wrapping a footer `div` with `px-6 py-4 border-t border-gray-200 dark:border-gray-600` and `{{ $items->links() }}`.

### Copy and i18n

- Headings, placeholders, filter labels, empty messages, and column titles must use `__()` keys (module files under `lang/en` and `lang/de`). Prefer **`common.*`** for shared labels (e.g. search, filter, actions, loading) where they already exist.

## Multi-Language Support

**⚠️ IMPORTANT: All user-facing strings MUST be translatable. Never hardcode English text in views, controllers, or components.**

Targus supports multiple languages (currently English and German). All user-facing text must use Laravel's translation system to ensure the application can be localized.

### Translation Files Location

Translation files are located in:
- `lang/en/` - English translations
- `lang/de/` - German translations

Each module should have its own translation file (e.g., `projects.php`, `plants.php`, `admin.php`).

### Using Translations in Blade Templates

**✅ DO:**
```blade
{{ __('projects.title') }}
{{ __('projects.create.name') }}
{{ __('common.actions.save') }}
```

**❌ DON'T:**
```blade
<h1>Projects</h1>
<label>Name *</label>
<button>Save</button>
```

### Translation Key Naming Convention

Organize translations hierarchically by module and view:

```php
// lang/en/projects.php
return [
    'title' => 'Projects',
    'create' => [
        'title' => 'Create Project',
        'name' => 'Name *',
        'description' => 'Description',
    ],
    'edit' => [
        'title' => 'Edit Project',
        'update' => 'Update Project',
    ],
    'table' => [
        'code' => 'Code',
        'status' => 'Status',
        'actions' => 'Actions',
    ],
];
```

### Avoiding Duplicate Translation Keys

**⚠️ IMPORTANT: Do NOT duplicate translation keys for form fields between `create` and `edit` sections.**

Form fields (labels, placeholders, validation messages) are typically identical between create and edit modes. Only duplicate keys for elements that actually differ (title, back button, submit button).

**✅ DO: Use `create` keys for all form fields**

```php
// lang/en/master_data.php
'assessment_areas' => [
    'create' => [
        'title' => 'Create Assessment Area',
        'back' => '← Back',
        'basic_information' => 'Basic Information',
        'name' => 'Name *',
        'code' => 'Code',
        'description' => 'Description',
        'cancel' => 'Cancel',
        'create_button' => 'Create Assessment Area',
        // ... all form field labels
    ],
    'edit' => [
        'title' => 'Edit Assessment Area',  // Only differs from create
        'back' => '← Back',                  // Only differs from create
        'update_button' => 'Update Assessment Area', // Only differs from create
        // Do NOT duplicate form field labels (name, code, description, etc.)
    ],
],
```

**In Livewire form components, use this pattern:**

```blade
@php
    $isEdit = $model !== null;
    $formTrans = 'master_data.module.create'; // Use create keys for all fields (they're identical)
    $modeTrans = $isEdit ? 'master_data.module.edit' : 'master_data.module.create'; // Only for title, back, button
@endphp

<div>
    <h1>{{ __($modeTrans . '.title') }}</h1>  <!-- Uses edit/create based on mode -->
    
    <form>
        <label>{{ __($formTrans . '.name') }}</label>  <!-- Always uses create keys -->
        <input wire:model="name">
        
        <button>
            {{ $isEdit ? __('master_data.module.edit.update_button') : __('master_data.module.create.create_button') }}
        </button>
    </form>
</div>
```

**Benefits:**
- **No duplication** - Form field labels defined once
- **Easier maintenance** - Update labels in one place
- **Consistent translations** - Same labels in create and edit
- **Smaller translation files** - Less code to maintain

**❌ DON'T: Duplicate identical keys**

```php
// ❌ BAD: Duplicating identical form fields
'create' => [
    'name' => 'Name *',
    'code' => 'Code',
    'description' => 'Description',
],
'edit' => [
    'name' => 'Name *',        // ❌ Duplicate - same as create
    'code' => 'Code',          // ❌ Duplicate - same as create
    'description' => 'Description', // ❌ Duplicate - same as create
],
```

**✅ DO: Only duplicate what differs**

```php
// ✅ GOOD: Only duplicate title, back, and button
'create' => [
    'title' => 'Create Item',
    'back' => '← Back',
    'name' => 'Name *',        // Form field - use in both modes
    'code' => 'Code',          // Form field - use in both modes
    'create_button' => 'Create',
],
'edit' => [
    'title' => 'Edit Item',    // ✅ Differs - keep separate
    'back' => '← Back',        // ✅ Differs - keep separate
    'update_button' => 'Update', // ✅ Differs - keep separate
    // Form fields (name, code) NOT duplicated - use create keys
],
```

### Common Translations

Use `lang/en/common.php` for shared strings across modules:
- Actions: `save`, `cancel`, `delete`, `edit`, `view`, `back`
- Status labels: `active`, `inactive`, `pending`, `completed`
- Messages: `confirm_delete`, `no_data_found`, `success`, `error`

**Example:**
```blade
<button>{{ __('common.actions.save') }}</button>
<span>{{ __('common.status.active') }}</span>
```

### Creating New Translation Files

When adding a new module:

1. **Create translation files** for both languages:
   ```bash
   # Create lang/en/module_name.php
   # Create lang/de/module_name.php
   ```

2. **Structure the file** by views/features:
   ```php
   <?php
   return [
       'title' => 'Module Name',
       'index' => [
           'title' => 'Module List',
           'add_new' => 'Add New',
       ],
       'create' => [
           'title' => 'Create Item',
           'name' => 'Name *',
       ],
       'common' => [
           'actions' => 'Actions',
           'status' => 'Status',
       ],
   ];
   ```

3. **Update all views** to use translation keys immediately

### JavaScript/Alpine.js Translations

For dynamic content in JavaScript/Alpine.js components:

1. **Pass translations from Blade to JavaScript:**
   ```blade
   <script>
       const translations = @json([
           'save' => __('common.actions.save'),
           'cancel' => __('common.actions.cancel'),
           'confirm_delete' => __('common.messages.confirm_delete'),
       ]);
   </script>
   ```

2. **Use in JavaScript:**
   ```javascript
   alert(translations.confirm_delete);
   ```

3. **For Alpine.js components:**
   ```blade
   <div x-data="myComponent(@js(__('module.key')))">
   ```

### Translation Checklist

Before submitting code, ensure:

- [ ] All user-facing strings use `__()` or `@lang()` helpers
- [ ] Translation files exist for both `en` and `de`
- [ ] Translation keys follow the naming convention
- [ ] Common strings use `common.php` when appropriate
- [ ] JavaScript/Alpine.js strings are passed via `@json()`
- [ ] No hardcoded English text in views, controllers, or components
- [ ] Form labels, buttons, placeholders, and messages are translated
- [ ] Error messages and validation messages are translated

### Examples

**Blade Template:**
```blade
@extends('layouts.app')

@section('title', __('projects.create.title'))

@section('content')
    <h1>{{ __('projects.create.title') }}</h1>
    
    <form>
        <label>{{ __('projects.create.name') }}</label>
        <input type="text" placeholder="{{ __('projects.create.name_placeholder') }}">
        
        <button type="submit">{{ __('common.actions.save') }}</button>
        <a href="{{ route('projects.index') }}">{{ __('common.actions.cancel') }}</a>
    </form>
@endsection
```

**Controller:**
```php
public function store(Request $request)
{
    // Validation messages are automatically translated
    $request->validate([
        'name' => 'required|string|max:255',
    ], [
        'name.required' => __('projects.validation.name_required'),
    ]);
    
    // Success messages
    return redirect()->route('projects.index')
        ->with('success', __('projects.messages.created_successfully'));
}
```

**JavaScript Component:**
```blade
<div x-data="projectForm()">
    <script>
        const projectTranslations = @json([
            'name_required' => __('projects.validation.name_required'),
            'save_success' => __('projects.messages.save_success'),
        ]);
        
        function projectForm() {
            return {
                save() {
                    // Use translations
                    if (!this.name) {
                        alert(projectTranslations.name_required);
                        return;
                    }
                    // ... save logic
                }
            }
        }
    </script>
</div>
```

### Common Mistakes to Avoid

1. **Hardcoding strings:**
   ```blade
   ❌ <h1>Projects</h1>
   ✅ <h1>{{ __('projects.title') }}</h1>
   ```

2. **Forgetting German translations:**
   - Always create both `en` and `de` files
   - Keep them in sync

3. **Inconsistent key naming:**
   ```php
   ❌ 'project_title' => 'Projects'
   ✅ 'title' => 'Projects'  // (within projects.php)
   ```

4. **Not using common.php for shared strings:**
   ```php
   ❌ 'save' => 'Save'  // in every module file
   ✅ Use 'common.actions.save' everywhere
   ```

5. **Hardcoding in JavaScript:**
   ```javascript
   ❌ alert('Are you sure?');
   ✅ alert(translations.confirm_delete);
   ```

### Testing Translations

1. **Switch language** in the application
2. **Verify all strings** are translated
3. **Check for missing keys** (Laravel will show the key if translation is missing)
4. **Test JavaScript translations** in both languages

### Resources

- [Laravel Localization Documentation](https://laravel.com/docs/localization)
- Existing translation files in `lang/en/` and `lang/de/` for examples

## Security by Design

**⚠️ CRITICAL: Security is not optional. All code must follow security best practices from the start.**

Security by design means building security into every feature from the beginning, not adding it as an afterthought. This section outlines mandatory security practices for all contributions.

### Core Security Principles

1. **Never trust user input** - Always validate and sanitize
2. **Principle of least privilege** - Users should only have access to what they need
3. **Defense in depth** - Multiple layers of security (route, controller, model)
4. **Fail securely** - Default to denying access when in doubt
5. **Client isolation** - Users can never access data from other clients

### 1. Input Validation (MANDATORY)

**All user input MUST be validated before use.**

#### ✅ DO: Validate All Input

```php
// Controller method
public function store(Request $request)
{
    // Always validate first
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'project_id' => 'required|exists:projects,id',
        'status' => 'required|in:pending,active,completed',
    ]);
    
    // Use validated data, not raw request
    $project = Project::create($validated);
}
```

#### ❌ DON'T: Use Unvalidated Input

```php
// NEVER do this
public function index(Request $request)
{
    // ❌ BAD: No validation
    $query->where('project_id', $request->project_id);
    
    // ❌ BAD: Direct use in query
    $query->where('status', $request->get('status'));
}
```

#### Filter Parameters Must Be Validated

When filtering by foreign keys (project_id, plant_id, etc.), **always verify ownership**:

```php
// ✅ GOOD: Validate and verify ownership
$validated = $request->validate([
    'project_id' => 'nullable|exists:projects,id',
]);

if (isset($validated['project_id'])) {
    $project = Project::find($validated['project_id']);
    // Verify project belongs to current client
    if ($project && $project->client_id == $currentClientId) {
        $query->where('project_id', $validated['project_id']);
    }
}
```

#### Search Input Sanitization

```php
// ✅ GOOD: Sanitize search input
$validated = $request->validate([
    'search' => 'nullable|string|max:255',
]);

if (isset($validated['search'])) {
    // Remove SQL wildcards to prevent injection
    $search = preg_replace('/[%_]/', '', $validated['search']);
    $query->where('name', 'like', "%{$search}%");
}
```

### 2. Authorization (MANDATORY)

**Every controller method that accesses or modifies data MUST check authorization using Laravel Policies.**

#### ✅ ALWAYS Use Laravel Policies

**All authorization MUST be done through Laravel Policies. Never use direct `hasPermission()` checks in controllers.**

For ALL controller methods (index, create, store, show, edit, update, destroy), use policies:

```php
// ✅ GOOD: Use Laravel Policy for ALL authorization
public function index()
{
    // Policy checks permission for listing resources
    $this->authorize('viewAny', Project::class);
    
    // ... rest of method
}

public function create()
{
    // Policy checks permission for creating resources
    $this->authorize('create', Project::class);
    
    // ... rest of method
}

public function store(Request $request)
{
    // Policy checks permission for creating resources
    $this->authorize('create', Project::class);
    
    // ... rest of method
}

public function show(Project $project)
{
    // Policy checks permission AND resource access
    $this->authorize('view', $project);
    
    // ... rest of method
}

public function edit(Project $project)
{
    // Policy checks permission AND resource access
    $this->authorize('update', $project);
    
    // ... rest of method
}

public function update(Request $request, Project $project)
{
    // Policy checks permission AND resource access
    $this->authorize('update', $project);
    
    // ... rest of method
}

public function destroy(Project $project)
{
    // Policy checks permission AND resource access
    $this->authorize('delete', $project);
    
    // ... rest of method
}
```

#### ❌ NEVER Use Direct Permission Checks

**Never use direct `hasPermission()` checks in controllers. All authorization logic belongs in Policies.**

```php
// ❌ BAD: Direct permission check in controller
public function create()
{
    $clientId = session('current_client_id');
    if (!auth()->user()->hasPermission('projects.create', $clientId)) {
        abort(403, __('common.permissions.projects.create'));
    }
    // ...
}

// ✅ GOOD: Use policy instead
public function create()
{
    $this->authorize('create', Project::class);
    // ...
}
```

#### Authorization Patterns

**Always use Laravel Policies:**
- `$this->authorize('viewAny', Model::class)` - Check if user can list resources (index)
- `$this->authorize('create', Model::class)` - Check if user can create resources (create/store)
- `$this->authorize('view', $model)` - Check if user can view the resource (show)
- `$this->authorize('update', $model)` - Check if user can update the resource (edit/update)
- `$this->authorize('delete', $model)` - Check if user can delete the resource (destroy)

**Policy Method Mapping:**
- `index()` → `viewAny()` - List all resources
- `show()` → `view()` - View single resource
- `create()` → `create()` - Show create form
- `store()` → `create()` - Store new resource
- `edit()` → `update()` - Show edit form
- `update()` → `update()` - Update resource
- `destroy()` → `delete()` - Delete resource

**Custom Policy Methods for Business Actions:**

For non-CRUD actions (like `export`, `complete`, `assignUsers`), add custom methods to policies:

```php
// In ScreeningPolicy
public function export(User $user, Screening $screening): bool
{
    $clientId = Session::get('current_client_id');
    
    // System-wide admins can export all screenings
    if ($user->hasRole('admin', null)) {
        return true;
    }
    
    // Check permission for exporting
    if (!$user->hasPermission('screenings.export', $clientId)) {
        return false;
    }
    
    // Then check resource access (same as view)
    return $this->view($user, $screening);
}

// Usage in controller
public function export(Screening $screening)
{
    $this->authorize('export', $screening);
    // ... export logic
}
```

**Common Custom Methods:**
- `export()` - Export resource to file (Excel, PDF, etc.)
- `complete()` - Mark resource as complete
- `assignUsers()` - Assign users to resource
- `syncEvents()` - Sync related events
- `cancel()` - Cancel an action

**Never skip authorization checks**, even if the route has middleware.

#### Creating New Policies

When creating authorization for a new resource, create a Policy:

1. **Create the Policy:**
```php
// app/Policies/ResourcePolicy.php
<?php

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class ResourcePolicy
{
    public function view(User $user, Resource $resource): bool
    {
        $clientId = Session::get('current_client_id');
        
        // System-wide admins can access all resources
        if ($user->hasRole('admin', null)) {
            return true;
        }
        
        // Check resource access logic
        // ...
        
        // Check permission
        return $user->hasPermission('resources.view', $clientId);
    }
    
    public function update(User $user, Resource $resource): bool
    {
        $clientId = Session::get('current_client_id');
        
        // Check permission first
        if (!$user->hasPermission('resources.edit', $clientId)) {
            return false;
        }
        
        // Then check resource access (reuse view logic)
        return $this->view($user, $resource);
    }
    
    public function delete(User $user, Resource $resource): bool
    {
        $clientId = Session::get('current_client_id');
        
        // Check permission first
        if (!$user->hasPermission('resources.delete', $clientId)) {
            return false;
        }
        
        // Then check resource access
        return $this->view($user, $resource);
    }
}
```

2. **Register the Policy:**
```php
// app/Providers/AppServiceProvider.php
protected $policies = [
    Comment::class => CommentPolicy::class,
    Project::class => ProjectPolicy::class,
    Plant::class => PlantPolicy::class,
    Resource::class => ResourcePolicy::class, // Add this
];
```

3. **Use in Controller:**
```php
public function show(Resource $resource)
{
    $this->authorize('view', $resource);
    // ... rest of method
}
```

**Resources:**
- `docs/LARAVEL_POLICIES.md` - Laravel Policies overview
- `docs/POLICY_MIGRATION_GUIDE.md` - Migration guide from custom methods
- `docs/CUSTOM_POLICY_METHODS_GUIDE.md` - Guide for custom policy methods (export, complete, etc.)

### 3. SQL Injection Prevention (MANDATORY)

**Never concatenate user input into SQL queries.**

#### ✅ DO: Use Parameterized Queries

```php
// ✅ GOOD: Eloquent automatically parameterizes
$query->where('project_id', $validated['project_id']);
$query->whereIn('id', $validated['ids']);

// ✅ GOOD: Raw queries with bindings
DB::select('SELECT * FROM projects WHERE client_id = ?', [$clientId]);
```

#### ❌ DON'T: String Concatenation

```php
// ❌ BAD: Never do this
$sql = "SELECT * FROM projects WHERE id = " . $request->id;
DB::select($sql);

// ❌ BAD: Even with Eloquent
$query->whereRaw("name = '" . $request->name . "'");
```

#### Raw Queries

If you must use raw queries, **always use bindings**:

```php
// ✅ GOOD: Use bindings
$query->whereRaw('status = ? AND created_at > ?', ['active', $date]);

// ❌ BAD: String interpolation
$query->whereRaw("status = '{$status}'");
```

### 4. XSS Prevention (MANDATORY)

**All user-generated content MUST be escaped in views.**

#### ✅ DO: Escape Output

```blade
{{-- ✅ GOOD: Automatic escaping --}}
<h1>{{ $project->name }}</h1>
<p>{{ $user->description }}</p>

{{-- ✅ GOOD: For JSON in JavaScript --}}
<script>
    const data = @json($safeData);
</script>
```

#### ❌ DON'T: Unescaped Output

```blade
{{-- ❌ BAD: Never do this with user input --}}
{!! $user->description !!}

{{-- ❌ BAD: Even if "trusted" --}}
{!! $project->notes !!}
```

#### When to Use `{!! !!}`

Only use `{!! !!}` for:
1. **Trusted HTML from markdown parsers** (with sanitization)
2. **SVG paths in components** (not user input)
3. **Pre-escaped content** (e.g., `{!! nl2br(e($content)) !!}`)

**Always verify the source** before using `{!! !!}`.

### 5. IDOR Prevention (MANDATORY)

**IDOR (Insecure Direct Object Reference) vulnerabilities occur when users can access resources they shouldn't.**

#### Always Verify Ownership

```php
// ✅ GOOD: Verify resource belongs to user/client
public function update(Request $request, Measure $measure)
{
    $this->authorizeProjectAccess($project);
    
    // Verify measure belongs to project
    if ($measure->project_id !== $project->id) {
        abort(404, 'Measure not found for this project.');
    }
    
    // Verify measure belongs to current client
    $currentClientId = session('current_client_id');
    if ($measure->client_id !== $currentClientId) {
        abort(403, 'Unauthorized access to this measure.');
    }
    
    // ... rest of method
}
```

#### Client Isolation

**All queries MUST filter by client_id:**

```php
// ✅ GOOD: Use forClient() scope
$projects = Project::forClient($currentClientId)->get();

// ✅ GOOD: Explicit client filter
$query->where('client_id', $currentClientId);

// ❌ BAD: Missing client filter
$projects = Project::all(); // Exposes all clients' data!
```

### 6. File Upload Security (MANDATORY)

**All file uploads MUST be validated and secured.**

#### Centralized rules: `App\Support\UploadRules`

**Do not** scatter ad-hoc `mimes:…|max:…` strings across controllers and Livewire forms for upload types we already support. Use **`UploadRules`** so allowlists and size limits stay consistent and auditable.

| Upload context | Use |
|----------------|-----|
| Measure / production-log attachments (PDF, Office, images; **no** zip/exe) | `UploadRules::attachmentFieldRules()` or `attachmentFieldRuleString()` |
| Admin master data CSV import (Plant KPIs, production lines, KPI benchmarks; 5 MB) | `masterDataCsvImportRules()` or `masterDataCsvImportRuleString()` |
| Import wizard (`ImportController`; CSV; 10 MB) | `importWizardCsvRules()` or `importWizardCsvRuleString()` |
| Client presentation templates (`.pptx` only; required by default) | `presentationTemplateRules()` / `presentationTemplateRuleString()`; pass `required: false` on edit when a file already exists |
| Plant screening photos (images only) | `screeningPhotoRules()` or `screeningPhotoRuleString()` |

**Array vs string:** Controllers often use `$request->validate(['field' => UploadRules::…Rules()])` (array of rule tokens). Livewire often needs `…RuleString()` for a single pipe-separated rule.

```php
use App\Support\UploadRules;

// ✅ GOOD: Controller — master data CSV
$request->validate([
    'file' => UploadRules::masterDataCsvImportRules(),
], [/* custom messages */]);

// ✅ GOOD: Livewire — measure attachments
'attachments.*' => UploadRules::attachmentFieldRuleString(),

// ✅ GOOD: New upload type not covered above — add a new named method on UploadRules
// with a strict allowlist, then call it from your feature (do not copy-paste mimes lists).
```

```php
// ❌ BAD: Duplicated validation strings that drift from UploadRules
'file' => 'required|file|mimes:csv,txt|max:5120',
```

Constants **`UploadRules::MAX_KB_LARGE`** and **`UploadRules::MAX_KB_MASTER_DATA_CSV`** define the default size ceilings; extend the class if you add a new category that needs different limits.

See also [docs/SECURITY_TEST_EVIDENCE.md](./docs/SECURITY_TEST_EVIDENCE.md) (upload evidence for testers).

#### Additional server-side checks (when needed)

For high-risk uploads (e.g. photos), you may **add** a second check on the stored `UploadedFile` (actual MIME vs allowlist) **after** validation passes—see plant screening photo upload for an example. This complements `UploadRules`, it does not replace it.

```php
// ✅ GOOD: Secondary MIME check (after UploadRules validation)
$allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
$actualMimeType = $file->getMimeType();
if (!in_array($actualMimeType, $allowedMimes, true)) {
    return response()->json(['error' => 'Invalid file type.'], 422);
}
```

#### Filename Sanitization

```php
// ✅ GOOD: Sanitize filename
$filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
$extension = $file->getClientOriginalExtension();
$safeFilename = $filename . '.' . $extension;
```

#### CSV Import (Master Data)

HTTP entry validation uses **`UploadRules::masterDataCsvImport*`** as above. For **content** validation (structure, columns, types, enums, row limits, no raw SQL), use the existing **`MasterDataCsvImportService`**. See [docs/MASTER_DATA_MASS_UPLOAD_CONCEPT.md](./docs/MASTER_DATA_MASS_UPLOAD_CONCEPT.md).

### 7. CSRF Protection (MANDATORY)

**All state-changing operations MUST include CSRF protection.**

#### Forms

```blade
{{-- ✅ GOOD: Always include @csrf --}}
<form method="POST" action="{{ route('projects.store') }}">
    @csrf
    {{-- form fields --}}
</form>
```

#### AJAX Requests

```javascript
// ✅ GOOD: Include CSRF token in AJAX
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
});
```

### 8. Error Handling (MANDATORY)

**Never expose sensitive information in error messages.**

#### ✅ DO: Generic Error Messages

```php
// ✅ GOOD: Generic error message
try {
    $user = User::findOrFail($id);
} catch (\Exception $e) {
    Log::error('User not found', ['user_id' => $id]);
    abort(404, __('common.errors.not_found'));
}
```

#### ❌ DON'T: Expose Details

```php
// ❌ BAD: Exposes database structure
catch (\Exception $e) {
    abort(500, $e->getMessage()); // Exposes SQL errors, file paths, etc.
}
```

### 9. Sensitive Data Protection (MANDATORY)

**Never log or expose sensitive data.**

#### Logging

**⚠️ IMPORTANT: All important actions MUST be logged using the centralized `LoggingService`.**

Targus uses a centralized `LoggingService` that provides structured, consistent logging across the application. This service integrates with Laravel's logging system and optionally writes to the `ActivityLog` database table.

**✅ DO: Use LogsActivity trait (Recommended)**

```php
use App\Traits\LogsActivity;

class PlantController extends Controller
{
    use LogsActivity;

    public function store(Request $request)
    {
        try {
            $plant = Plant::create($request->validated());
            
            // Log successful creation
            $this->logCrud('created', $plant, [
                'name' => $plant->name,
                'code' => $plant->code,
            ]);
            
            return redirect()->route('plants.index');
        } catch (\Exception $e) {
            // Log exception with context
            $this->logError('Failed to create plant', [
                'error' => $e->getMessage(),
                'input' => $request->except(['password', '_token']),
            ]);
            
            throw $e;
        }
    }

    public function update(Request $request, Plant $plant)
    {
        $validated = $request->validate([...]);
        $oldValues = $plant->only(['name']);
        
        $plant->update($validated);
        $changes = $plant->getChanges();

        // Log update with changes
        $this->logCrud('updated', $plant, [
            'old_values' => $oldValues,
            'new_values' => $changes,
        ]);

        return redirect()->back();
    }

    public function destroy(Plant $plant)
    {
        $plantName = $plant->name;
        $plant->delete();

        // Log deletion
        $this->logCrud('deleted', $plant, [
            'name' => $plantName,
        ]);

        return redirect()->route('plants.index');
    }
}
```

**Option 2: Inject LoggingService directly**

```php
use App\Services\LoggingService;

class ProjectController extends Controller
{
    public function __construct(
        protected LoggingService $logger
    ) {}

    public function store(Request $request)
    {
        $project = Project::create($request->validated());
        
        $this->logger->crud('created', 'Project', $project->id, [
            'name' => $project->name,
        ], 'activity', $project); // Last parameter triggers ActivityLog
    }
}
```

**Available Logging Methods:**

```php
// Basic logging (via trait or direct service)
$this->logger()->info('User viewed dashboard', ['project_id' => $project->id]);
$this->logger()->success('Operation completed', ['item_id' => $item->id]);
$this->logger()->warning('Low disk space', ['usage' => '85%']);
$this->logger()->error('Failed to send email', ['error' => $e->getMessage()]);

// With custom channel (default is 'activity')
$this->logger()->info('Message', ['context' => 'value'], 'custom-channel');

// CRUD operations (automatically logs with model name and ID)
// Via trait (recommended - automatically extracts model info)
$this->logCrud('created', $model, ['additional' => 'context']);
$this->logCrud('updated', $model, ['changes' => $model->getChanges()]);
$this->logCrud('deleted', $model, ['name' => $model->name]);

// Via service directly (full signature)
$this->logger()->crud(
    'created',           // Action: created|updated|deleted|viewed|restored
    'Plant',             // Model name (string or class name)
    $plant->id,          // Model ID (optional)
    ['name' => '...'],   // Additional context (optional)
    'activity',          // Channel (optional, default: 'activity')
    $plant               // Subject model for ActivityLog (optional)
);

// Database operations
$this->logger()->database(
    'inserted',          // Operation: inserted|updated|deleted|selected
    'table_name',        // Table name
    ['id' => $id],       // Data array (optional)
    true,                // Success flag (optional, default: true)
    'activity'           // Channel (optional, default: 'activity')
);

// API calls
$this->logger()->api(
    'ServiceName',       // Service name (e.g., 'OpenAI', 'Stripe')
    '/endpoint',         // Endpoint path
    true,                // Success flag (optional, default: true)
    ['tokens' => 150],   // Response data (optional, default: [])
    ['model' => 'gpt-4'], // Additional context (optional, default: [])
    'activity'           // Channel (optional, default: 'activity')
);

// Authentication events
$this->logger()->auth(
    'login',             // Event: login|logout|failed_login|password_reset|password_changed
    $user->id,           // User ID (optional, defaults to Auth::id())
    ['ip' => '...'],     // Additional context (optional, default: [])
    'activity'           // Channel (optional, default: 'activity')
);
$this->logger()->auth('logout', $user->id);
$this->logger()->auth('failed_login', null, ['email' => $request->email]);

// Validation errors
$this->logger()->validation(
    'ModelName',                              // Model name
    $validator->errors()->toArray(),          // Validation errors array
    ['input' => $request->except(['password'])], // Input data (optional, default: [])
    'activity'                                // Channel (optional, default: 'activity')
);

// Exceptions
try {
    // Some code
} catch (\Exception $e) {
    $this->logger()->exception(
        $e,                                    // Exception instance
        ['additional_context' => 'value'],     // Additional context (optional, default: [])
        null                                   // Channel (optional; null = default LOG_CHANNEL, usually laravel.log)
    );
    throw $e;
}
```

**Log Channels:**

The `LoggingService` supports Laravel log channels. Passing `null` (or omitting the channel where applicable) uses the **default** `LOG_CHANNEL` (typically `stack` → `laravel.log`). The dedicated **`activity`** channel in `config/logging.php` writes to `storage/logs/activity-*.log` and is only used when you pass `'activity'` explicitly:

```php
// Use default channel (laravel.log via LOG_CHANNEL)
$this->logger()->info('Message', ['context' => 'value']);

// Dedicated activity file
$this->logger()->info('Message', ['context' => 'value'], 'activity');

// Channels are configured in config/logging.php
```

**❌ DON'T: Use Laravel's Log facade directly**

```php
// ❌ BAD: Direct Log usage (inconsistent format)
\Log::info('Plant created', ['id' => $plant->id]);

// ✅ GOOD: Use LoggingService
$this->logCrud('created', $plant, ['id' => $plant->id]);
```

**Logging Best Practices:**

1. **Log Important Actions**: Log all CRUD operations, authentication events, and errors
2. **Include Context**: Always include relevant context (IDs, names, etc.)
3. **Use Appropriate Levels**: 
   - `info`: Normal operations
   - `warning`: Potential issues
   - `error`: Actual errors
4. **Don't Log Sensitive Data**: Never log passwords, tokens, or sensitive personal information
5. **Structured Context**: Use arrays for context, not concatenated strings
6. **Exception Handling**: Always log exceptions with full context

**Exclude Sensitive Fields:**

```php
// ✅ GOOD: Exclude sensitive fields
$this->logger()->error('Exception occurred', [
    'input' => request()->except(['password', '_token', 'api_key', 'secret']),
]);

// ❌ BAD: Logs passwords
$this->logger()->error('Login failed', ['request' => $request->all()]);
```

**ActivityLog Database Integration:**

The `LoggingService` automatically writes to the `ActivityLog` database table when a model instance is provided as the `$subject` parameter. This is handled automatically when using the `logCrud()` trait method:

```php
// Via trait (automatic ActivityLog)
$this->logCrud('created', $plant, ['name' => $plant->name]);
// The $plant model instance automatically triggers ActivityLog database entry

// Via service directly (manual ActivityLog)
$this->logger()->crud(
    'created',
    'Plant',
    $plant->id,
    ['name' => $plant->name],
    null,   // null = default log file; use 'activity' only if you want activity-*.log
    $plant  // This parameter triggers ActivityLog database entry
);
```

**Note:** The ActivityLog database entry is only created if:
1. A model instance is provided as the `$subject` parameter
2. The `ActivityLog` model class exists
3. The database write succeeds (failures are logged but don't break the application)

**Service Architecture:**

The logging system consists of:

1. **`LoggingService`** (`app/Services/LoggingService.php`):
   - Core service providing all logging methods
   - Handles Laravel log channels
   - Integrates with ActivityLog database
   - Adds automatic context (timestamp, request_id, user_id, client_id)

2. **`LogsActivity` Trait** (`app/Traits/LogsActivity.php`):
   - Convenience trait for controllers
   - Provides `logger()`, `logCrud()`, `logSuccess()`, `logError()`, `logWarning()`, `logInfo()` methods
   - Automatically extracts model information for CRUD operations
   - Recommended approach for controllers

3. **ActivityLog Model** (`app/Models/ActivityLog.php`):
   - Database model for activity tracking
   - Stores structured activity data
   - Queryable for audit trails

**Resources:**

- [Logging System Guide](./docs/LOGGING_GUIDE.md) - Complete logging documentation
- [LoggingService](./app/Services/LoggingService.php) - Core service implementation
- [LogsActivity Trait](./app/Traits/LogsActivity.php) - Trait implementation for controllers
- [ActivityLog Model](./app/Models/ActivityLog.php) - Database model for activity tracking

#### JSON Responses

```php
// ✅ GOOD: Hide sensitive fields
return response()->json([
    'user' => $user->makeHidden(['password', 'api_token']),
]);

// ❌ BAD: Exposes all fields
return response()->json($user);
```

### 10. Security Checklist

Before submitting a PR, verify:

- [ ] **All user input is validated** using Laravel validation
- [ ] **All foreign key filters verify ownership** (project → client, etc.)
- [ ] **All controller methods use Laravel Policies** (`$this->authorize()`) - Never use direct `hasPermission()` checks
- [ ] **All CRUD methods** (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) use policies
- [ ] **All custom actions** (`export`, `complete`, `assignUsers`, etc.) use custom policy methods
- [ ] **All show/edit/update/destroy methods verify resource access** via policies
- [ ] **All queries filter by client_id** (use `forClient()` scope)
- [ ] **All user output is escaped** in Blade templates (use `{{ }}`)
- [ ] **All file uploads validate MIME type and size** via **`App\Support\UploadRules`** (or a new method added there—no duplicated `mimes|max` strings for covered upload types)
- [ ] **All forms include `@csrf`** token
- [ ] **All AJAX requests include CSRF token**
- [ ] **No sensitive data in logs** or error messages
- [ ] **No SQL injection vulnerabilities** (no string concatenation)
- [ ] **No XSS vulnerabilities** (no unescaped user output)
- [ ] **No IDOR vulnerabilities** (verify resource ownership)

### Security Resources

- [Authorization Policy Assessment](./docs/AUTHORIZATION_POLICY_ASSESSMENT.md) - Role hierarchy and Gate behavior
- [Security Test Evidence](./docs/SECURITY_TEST_EVIDENCE.md) - Security test coverage notes
- [OWASP Top 10](https://owasp.org/www-project-top-ten/) - Common vulnerabilities
- [Laravel Security Documentation](https://laravel.com/docs/security)

### Common Security Mistakes

1. **Skipping validation** - "It's just a filter parameter"
2. **Assuming route middleware is enough** - Always check in controller too
3. **Trusting user input** - "It's from a dropdown, it's safe"
4. **Forgetting client isolation** - "The query works, that's enough"
5. **Using `{!! !!}` for user content** - "It's from the database, it's safe"

**Remember: Security is everyone's responsibility. When in doubt, ask for review.**

## Client portal separation (mandatory)

**⚠️ CRITICAL: The client portal (`/portal`) is a separate product surface from the consultant app. Always keep views, controllers, and data contracts isolated.**

Portal users (`client-user` role) must never land in consultant Livewire screens, never see internal/commercial fields, and never inherit consultant UI chrome (sidebar, admin nav, master-data tools).

### Why this exists

- Consultant screens expose notes, ROI/cost/effort, root-cause AI, CI tooling, and master data.
- Portal users are external stakeholders with dual isolation: **client + assigned project**.
- Reusing a consultant Blade/Livewire view “just this once” is an IDOR / data-leak risk, even if the route looks gated.

### Hard rules (do not bend)

1. **Separate views always**
   - Portal pages live under `resources/views/portal/**` and extend **`layouts.client-portal` only**.
   - Portal-only Blade components live under `resources/views/components/portal/**`.
   - **Never** `@extends('layouts.app')`, `@include` consultant list/detail views, or mount consultant Livewire components from portal routes.
   - Portal Alpine/UI JS loads via `resources/js/portal.js` (standalone Alpine). Do **not** pull `@livewireScripts` into the portal shell.
2. **Separate controllers / presenters**
   - HTTP entry points: `App\Http\Controllers\Portal\*`.
   - Customer-safe payloads: `App\Support\Portal\*` (e.g. `PortalMeasurePresenter`) and `App\Services\Portal\*`.
   - Do not return Eloquent models (or `$measure->toArray()`) into portal JSON/views when those models contain internal fields — map through a presenter.
3. **Separate middleware**
   - Portal routes: `portal.user` + `portal.project` (`EnsurePortalUser`, `EnsurePortalProjectAccess`).
   - Consultant routes stay behind `consultant.only` so portal users are redirected to `portal.overview`.
   - Helpers: `App\Support\PortalAccess` for role/project checks.
4. **Dual isolation on every query**
   - Scope by **current client** and **current project** (or plants belonging to that project).
   - Cross-project IDs → **404**, not 403 with leakage.
5. **Redaction boundaries (never expose in portal)**
   - Internal notes, risks, ROI / cost / effort scores
   - Root-cause AI suggestions and commercial fields
   - Continuous-improvement internal tooling
   - Master-data create/edit, measure creation by clients
   - Client **file uploads** (download of consultant-shared attachments only)
6. **No SMTP / onboarding tours unless explicitly scoped** — keep portal collaboration in-app first.

### Correct pattern

```php
// ✅ Portal controller: authorize + dual scope + presenter
public function show(Request $request, Measure $measure): View
{
    $project = PortalAccess::requireCurrentProject($request->user());
    // abort 404 if measure.client_id / measure.project_id mismatch
    return view('portal.measures.show', [
        'measure' => PortalMeasurePresenter::detail($measure),
    ]);
}
```

```blade
{{-- ✅ Portal view --}}
@extends('layouts.client-portal')

@section('content')
    {{-- portal-only markup; no Livewire consultant components --}}
@endsection
```

### Incorrect pattern

```blade
{{-- ❌ NEVER: reuse consultant shell or Livewire measure UI --}}
@extends('layouts.app')
<livewire:measures-index />
```

```php
// ❌ NEVER: dump the full model into the portal
return view('portal.measures.show', ['measure' => $measure]);
```

### PR checklist (portal changes)

- [ ] New portal UI is under `resources/views/portal/**` (or `components/portal/**`) and uses `layouts.client-portal`
- [ ] No consultant Livewire components or `layouts.app` includes
- [ ] Controllers live under `App\Http\Controllers\Portal` (or portal Support/Services)
- [ ] Queries enforce **client_id + project** (or project plants); foreign IDs 404
- [ ] Customer-facing measure/export payloads go through a redacting presenter/service
- [ ] No client uploads; no internal notes / ROI / AI / commercial fields in Blade or PDF
- [ ] Feature tests cover: portal user blocked from consultant routes, consultant blocked from portal, cross-project 404, sensitive field not rendered

### Key references

- Architecture (surfaces, middleware, route map): [docs/ARCHITECTURE.md — Client portal surface](./docs/ARCHITECTURE.md#client-portal-surface-secure-segregation)
- Layout: `resources/views/layouts/client-portal.blade.php`
- Routes: `routes/web.php` → `portal.*` group (`portal.user`, `portal.project`)
- Access helpers: `app/Support/PortalAccess.php`
- Measure redaction: `app/Support/Portal/PortalMeasurePresenter.php`
- Safe PDF export: `app/Services/Portal/PortalSafeExportService.php`
- Tests: `tests/Feature/Portal/*`

## Development Workflow

1. **Create a feature branch** from `main`
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** following the code style guidelines

3. **Test your changes** thoroughly

4. **Commit with clear messages**
   ```bash
   git commit -m "feat: add new feature"
   ```

5. **Push and create a Pull Request**

## Architecture Overview

High-level application architecture (consultant app **and** client portal segregation): [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md). Portal isolation rules above are normative for contributors; the architecture doc describes the same split for system context.  
AI providers and domain AI services: [docs/AI_ARCHITECTURE.md](./docs/AI_ARCHITECTURE.md).  
RAG (pgvector, dual-DB, ingest/retrieve): [docs/RAG_ARCHITECTURE.md](./docs/RAG_ARCHITECTURE.md).  
Project strip / empty-state setup: [Project context quick links (menu-as-workflow)](#project-context-quick-links-menu-as-workflow).

### Maturity Assessment Module

The Maturity Assessment module is a comprehensive system for evaluating organizational maturity across multiple dimensions.

#### Key Components

1. **Templates** (`MaturityAssessmentTemplate`)
   - Define the structure (domains, subdomains, criteria, levels)
   - Can be industry-specific
   - Support AI generation

2. **Assessments** (`MaturityAssessment`)
   - Instances of templates applied to assessable entities (Plant, Project, etc.)
   - Store responses, comments, and scores
   - Support versioning and autosave

3. **Collaboration Features**
   - Multi-user editing with conflict detection
   - User presence tracking
   - Recent changes tracking
   - Activity logging

### Concurrency & multi-user editing

Shared platform for optimistic concurrency and advisory presence. **Do not invent per-feature version comparisons.**

Full reference: [`docs/CONCURRENCY.md`](docs/CONCURRENCY.md).

**Core pieces (`App\Concurrency\`):**

- `OptimisticLock` — `assertWritable` / `applyBump` under `lockForUpdate()`
- `ConcurrentEditable` — model contract (`HasUpdatedAtConcurrency`, `HasOptimisticLocking`, `HasVersionConcurrency`)
- `ConcurrentEditConflictException` + `ConflictPayload` — stable 409 / Livewire conflict shape
- `Presence\PresenceStore` — polymorphic `edit_presences` (advisory only)

**Write recipe:**

```php
DB::transaction(function () use ($model, $clientToken, $user) {
    $lock = app(\App\Concurrency\OptimisticLock::class);
    $locked = $lock->assertWritable($model, $clientToken, $user->id);
    $locked->fill($data);
    $lock->persist($locked, $user->id);
});
```

**Adopted today:**
- Measures + phases (`updated_at` token) + presence banner
- Hypotheses, findings, root causes (`lock_version`) + presence banners
- Questionnaire responses (`version` via `HasVersionConcurrency` on full save + autosave) + assessment-area presence
- Org structure (`OrgElement` / `OrgElementType` `lock_version`) + tree/form/type/KPI-matrix presence
- Screening non-autosave (summary/metadata `lock_version`; assessment-area status/notes/workforce/KPIs `version`; tool responses + mark-complete; photo metadata) + shared presence banners; legacy `screening_presence` / `project_screening_presence` removed
- Portal maturity response updates (`version` via `HasVersionConcurrency`) when an edit UI sends a token (portal maturity view is currently read-only)
- Plants / projects / production lines (`lock_version`) + edit/shifts/line presence banners; checklist template assign/apply locks the project

**UX rule:** when adopting `OptimisticLock` on an edit surface, also wire `x-concurrency.presence-banner` (same amber “also working on this record” banner). Do not invent a different presence UI.

**Still feature-local (migrate later):** maturity autosave (has its own presence; align later).

### Multi-User Collaboration System

The maturity assessment collaboration system enables multiple consultants to work on assessments simultaneously.


#### Architecture

**Backend Services:**
- `MaturityAssessmentAutosaveService`: Handles autosaving with version tracking and conflict detection
- `MaturityAssessmentCollaborationService`: Manages user presence and active users
- `MaturityAssessmentActivityLog`: Tracks all changes for audit trail

**Frontend JavaScript Classes:**
- `MaturityAssessmentAutosave`: Manages autosave with debouncing and conflict resolution
- `MaturityAssessmentCollaboration`: Handles user presence tracking
- `MaturityAssessmentRecentChanges`: Manages recent changes sidebar and notifications

#### Key Features

1. **Autosaving (Phase 1)**
   - Debounced saving (2 seconds after user stops typing)
   - Visual save status indicators (unsaved, saving, saved, error)
   - Version tracking for conflict detection
   - Automatic retry on failure

2. **Conflict Detection (Phase 2)**
   - Optimistic locking using version numbers
   - Conflict resolution dialog (Keep Mine / Use Theirs)
   - Only triggers when different users modify the same section

3. **User Presence (Phase 3)**
   - Tracks which users are viewing/editing the assessment
   - Shows active users in header
   - Updates presence every 30 seconds
   - Cleans up stale presence entries

4. **Recent Changes (Phase 5)**
   - Activity log of all changes
   - Recent changes sidebar with toggle
   - Change notifications for other users' edits
   - Jump-to-section functionality

5. **Performance Optimizations (Phase 6)**
   - Caching for recent changes API (5-second cache)
   - Query optimization (eager loading, limits)
   - Non-blocking activity logging

#### Database Schema

**Key Tables:**
- `maturity_assessments`: Main assessment records with autosave fields
- `maturity_assessment_responses`: Individual criterion responses with version tracking
- `maturity_assessment_domain_comments`: Domain-level comments with version tracking
- `maturity_assessment_presence`: User presence tracking
- `maturity_assessment_activity_log`: Audit trail of all changes

**Version Tracking:**
- Each response and comment has a `version` field
- Versions increment on each save
- Used for conflict detection (optimistic locking)

#### API Endpoints

**Autosave:**
- `POST /{assessableType}/{assessableId}/maturity-assessment/{assessment}/autosave-response`
- `POST /{assessableType}/{assessableId}/maturity-assessment/{assessment}/autosave-domain-comment`
- `POST /{assessableType}/{assessableId}/maturity-assessment/{assessment}/autosave-summary`
- `POST /{assessableType}/{assessableId}/maturity-assessment/{assessment}/autosave-metadata`

**Collaboration:**
- `POST /{assessableType}/{assessableId}/maturity-assessment/{assessment}/update-presence`
- `GET /{assessableType}/{assessableId}/maturity-assessment/{assessment}/active-users`
- `GET /{assessableType}/{assessableId}/maturity-assessment/{assessment}/recent-changes`

#### Conflict Resolution Flow

1. User makes a change → Client sends request with current version
2. Server checks version:
   - If server version > client version AND different user → Conflict detected (409)
   - Otherwise → Save succeeds, increment version
3. Client receives conflict → Shows resolution dialog
4. User chooses:
   - **Keep Mine**: Force save with updated version
   - **Use Theirs**: Accept server version, update UI

#### Activity Logging

All changes are logged to `maturity_assessment_activity_log`:
- Response changes (maturity level selections)
- Domain comment changes
- Summary updates
- Metadata changes (title, description, date, notes)

Each log entry includes:
- User who made the change
- Section type and ID
- Old and new values
- Human-readable description
- Timestamp

#### Extending the Collaboration System

**Adding New Autosave Types:**

1. Add method to `MaturityAssessmentAutosaveService`
2. Add endpoint to `MaturityAssessmentController`
3. Register in `MaturityAssessmentAutosave` JavaScript class
4. Add activity logging

**Adding New Collaboration Features:**

1. Add database migration if needed
2. Add service methods
3. Add API endpoints
4. Create/update JavaScript classes
5. Update UI components

## Project context quick links (menu-as-workflow)

The consultant **project strip** is the only project setup path. Creating a project always opens `projects.show`. Empty strip items stay visible (grey) and open a setup page; filled items are colored and jump to the working page. Do **not** add guided wizard steps, `assessment_type` on project create, or `isWorkflow` / `?workflow=1` / `?standalone=1` routing.

### How it is structured

| Piece | Where |
|--------|--------|
| Build + URLs | `App\Support\Navigation\ProjectContextQuickLinksBuilder` |
| Filled / grey + empty destinations | `App\Support\Navigation\ProjectContextQuickLinkAvailability` |
| Strip / submenu order | `App\Support\Navigation\ProjectContextQuickLinkGroups` |
| Icons | `App\Support\Navigation\NavigationSectionIcons` |
| Labels | `lang/en/navigation.php` and `lang/de/navigation.php` (`quick_link_*`) |
| Chrome | `<x-chrome-compact>` + `<x-project-context-quick-links-compact />` (the only consultant shell) |

Groups:

- **`primary`** — fixed top-12 strip (`primaryOrder()`): Project, Hypothesis, Data Request, Plant, Checklists, Documents, Assessment areas, Findings, Workstreams, Performance map, Initiatives, Roadmap.
- **`plant_context`** — extra strip when a plant (or screening) is in context.
- **`other`** — Project tools submenu (notes, RAG chat, review, maturity, screenings, org structure, …).

`GROUP_WORKFLOW` is a deprecated alias of `GROUP_PRIMARY`. Do not add new `workflow` group keys.

Project context comes from `session('current_project_id')` / `current_plant_id`, then the route. Compact chrome keeps the strip when a project is in session.

### Filled vs empty, and where empty clicks go

`isFilled($key)` greys a primary icon until that object exists. Clicks still work. Put **empty destinations** on `*NavigationUrl()` (or the URL in the builder), not behind a disabled link.

Conventions already in use:

- **Jump when one, overview when many** (plants, screenings, assessment areas).
- **Zero items → setup**, not a dead empty table.
- **Project vs plant is context**, not a create-time `assessment_type`. A selected plant, or the only plant on the project, is plant-level; otherwise project-level. Assessment Areas shows labeled **Project screening** / **Plant screening** cards when both paths are open.

| Primary key | Empty / setup destination (today) |
|-------------|-----------------------------------|
| `plants` | `projects.plants.create` |
| `assessment_areas` | `/projects/{id}/assessment-areas` (template / plant-or-project setup) |
| `performance_map` | `/projects/{id}/org-structure?edit=1` |
| `findings` / `measures` | `/projects/{id}/findings` and `/projects/{id}/measures` (prerequisite cards when empty) |

### Classic sidebar vs project strip (Assessments & Analysis)

Classic **Assessments & Analysis** lists (`/assessment-areas`, `/findings`, `/measures`) are **filters + browse only** — like `/root-causes`. No session auto-project, no attach/create/tracker workflow CTAs.

Project strip items for those three open **`/projects/{project}/…`** pages with mutations (attach areas, create from template, create finding, measures tracker, etc.). Do **not** share one Livewire component with `if ($project)` dual-mode; use separate routes, views, and Livewire classes, and share only query/helpers/traits.

### Adding or changing a strip item

1. Add the key to `primaryOrder()` or `otherOrder()` (keep the top strip at 12 primary icons unless product agrees to change that).
2. Register the link in `ProjectContextQuickLinksBuilder` (`url`, `route_patterns`, `filled`).
3. Implement `isFilled()` and any empty/jump URL on `ProjectContextQuickLinkAvailability`.
4. Map the icon in `NavigationSectionIcons::quickLinkMap()`.
5. Add `quick_link_*` strings in **en** and **de**.
6. If the empty page needs setup UI, put it on the **project** page (same pattern as Assessment Areas / Findings / Initiatives). Do not invent a new wizard step.
7. Extend `tests/Unit/Support/Navigation/ProjectContextQuickLinksBuilderTest.php`. For setup UI, add a Livewire layout test (see `ProjectAssessmentAreasIndexLayoutTest`, `ProjectFindingsIndexLayoutTest`).

## Key Features

### Maturity Assessment

- **4-Level Hierarchy**: Domain → Subdomain (optional) → Criterion → Level
- **AI Template Generation**: Generate templates via chat interface
- **Multi-User Collaboration**: Multiple consultants can work simultaneously
- **Autosave**: Automatic saving prevents data loss
- **Conflict Detection**: Handles simultaneous edits gracefully
- **Scoring**: Automatic score calculation with roll-up logic

### Plant Screening

- Plant and project screenings, created from Assessment Areas (context decides the level)
- Assessment area management on the project Assessment Areas page and screening pages

### Master Data Export / Import

- **CSV export**: Download master data (e.g. Plant KPIs, KPI Benchmarks) with current filters; first row = field explanations, second row = column names, then data.
- **CSV import**: Upload CSV to create/update records; server-side validation (types, enums, lengths, FKs), security checks (file size/type, no injection), per-row error reporting.
- **Reference implementation**: Plant KPIs and KPI Benchmarks (`/admin/master-data/plant-kpis`, `/admin/master-data/kpi-benchmarks`). Pattern is reusable for other master data entities.
- **Concept and services**: See [docs/MASTER_DATA_MASS_UPLOAD_CONCEPT.md](./docs/MASTER_DATA_MASS_UPLOAD_CONCEPT.md); services in `app/Services/MasterData/` (schema, export, import, executor).

## Testing

### Running Tests

```bash
php artisan test
```

### Test Coverage

- Unit tests for services
- Integration tests for API endpoints
- Feature tests for critical workflows

### Testing Collaboration Features

When testing multi-user collaboration:

1. Open assessment in multiple browser windows/tabs
2. Make changes in different sections simultaneously
3. Test conflict scenarios by editing the same section
4. Verify presence tracking updates correctly
5. Check recent changes appear in sidebar

## Database Migrations

### Creating Migrations

```bash
php artisan make:migration create_example_table
```

### Migration Guidelines

- Always check if tables/columns exist before creating (idempotent migrations)
- Use custom foreign key names to avoid MySQL 64-character limit
- Add indexes for frequently queried columns
- Include `down()` method for rollback

### Example: Idempotent Migration

```php
public function up(): void
{
    if (!Schema::hasTable('example_table')) {
        Schema::create('example_table', function (Blueprint $table) {
            $table->id();
            // ...
        });
    }
    
    if (!Schema::hasColumn('example_table', 'new_column')) {
        Schema::table('example_table', function (Blueprint $table) {
            $table->string('new_column')->nullable();
        });
    }
}
```

## Pull Request Process

1. **Ensure your code follows style guidelines**
2. **Verify multi-language support**
   - All user-facing strings use translations
   - Both `en` and `de` translation files are updated
   - No hardcoded English text
3. **Verify security requirements** (see [Security by Design](#security-by-design))
   - Complete the security checklist
   - All input is validated
   - All authorization checks are in place
   - No security vulnerabilities introduced
4. **Write clear commit messages**
   - Use prefixes: `feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `security:`
   - Example: `feat: add recent changes sidebar`
   - Example: `security: add input validation to filter parameters`
5. **Update documentation** if needed
6. **Test thoroughly** before submitting
   - Test in both English and German
   - Verify all translations work correctly
   - Test security scenarios (unauthorized access, invalid input, etc.)
7. **Request review** from team members
   - Security-sensitive changes require security review

## Common Issues and Solutions

### Migration Errors

**Issue**: "Column already exists" or "Table already exists"
**Solution**: Make migrations idempotent with `Schema::hasTable()` / `Schema::hasColumn()` checks

### Foreign Key Name Too Long

**Issue**: MySQL 64-character limit for constraint names
**Solution**: Use custom constraint names: `$table->foreign('column', 'short_name_fk')`

### Conflict Detection False Positives

**Issue**: Conflicts appearing for same user
**Solution**: Ensure versions are initialized from existing data on page load

### Performance Issues

**Issue**: Slow autosave or recent changes loading
**Solution**: 
- Add caching for recent changes API
- Optimize queries with eager loading
- Limit result sets

## Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Alpine.js Documentation](https://alpinejs.dev/)
- [Multi-User Collaboration System](#multi-user-collaboration-system) - Collaboration patterns in this guide
- [docs/MASTER_DATA_MASS_UPLOAD_CONCEPT.md](./docs/MASTER_DATA_MASS_UPLOAD_CONCEPT.md) - Master data CSV export/import concept and reference implementation

## Form Validation and Messaging

**⚠️ IMPORTANT: All forms MUST use Livewire components (Laravel's recommended best practice) for consistent user experience.**

Targus uses **Livewire** for form handling, which is Laravel's recommended best practice. Livewire provides:
- Real-time validation as users type
- Server-side validation with automatic error display
- Toast notifications for success/error messages
- Loading states without custom JavaScript
- Better UX with instant feedback

**⚠️ Migration Note**: Existing AJAX forms are being migrated to Livewire. New forms MUST use Livewire.

### Livewire Forms (Recommended Best Practice)

**✅ DO: Use Livewire components for all forms**

Livewire is Laravel's recommended approach for form handling. It provides:
- Real-time validation as users type
- Server-side validation with automatic error display
- No JavaScript required
- Better UX with instant feedback

#### Creating a Livewire Form Component

**✅ DO: Create Livewire components for forms**

```php
// app/Livewire/Forms/PlantForm.php
namespace App\Livewire\Forms;

use App\Livewire\Forms\FormValidationTrait;
use App\Livewire\Forms\WithToastNotifications;
use App\Traits\LogsActivity;
use Livewire\Component;

class PlantForm extends Component
{
    use FormValidationTrait;
    use WithToastNotifications;
    use LogsActivity;

    // Public properties (automatically bound to form fields)
    public string $name = '';
    public ?string $code = null;
    public ?string $description = null;

    // Component state
    public ?Plant $plant = null;
    public ?Project $project = null;

    /**
     * Get validation rules for this form.
     * Maps snake_case keys from FormValidationService to camelCase Livewire properties.
     */
    protected function getValidationRules(): array
    {
        $rules = $this->getValidationService()->getValidationRules('plant');

        // Map snake_case keys from service to camelCase property names
        // Livewire requires rule keys to match component property names exactly
        $mappedRules = [];
        foreach ($rules as $key => $rule) {
            // Convert snake_case to camelCase for Livewire properties
            // Handle both regular keys and wildcard array keys (e.g., market_ids.*)
            $camelKey = match(true) {
                $key === 'project_id' => 'projectId',
                $key === 'market_ids' => 'marketIds',
                $key === 'market_ids.*' => 'marketIds.*', // Array validation wildcard
                default => $key,
            };
            
            // Convert rule string to handle references to other fields
            // e.g., 'after_or_equal:start_date' -> 'after_or_equal:startDate'
            if (is_string($rule)) {
                $rule = str_replace('project_id', 'projectId', $rule);
            } elseif (is_array($rule)) {
                $rule = array_map(function($r) {
                    if (is_string($r)) {
                        return str_replace('project_id', 'projectId', $r);
                    }
                    return $r;
                }, $rule);
            }
            
            $mappedRules[$camelKey] = $rule;
        }

        // Add component-specific rules (e.g., unique code in edit mode)
        if ($this->plant) {
            $mappedRules['code'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('plants', 'code')->ignore($this->plant->id)->whereNull('deleted_at'),
            ];
        } else {
            $mappedRules['code'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('plants', 'code')->whereNull('deleted_at'),
            ];
        }

        return $mappedRules;
    }

    /**
     * Get validation messages for this form.
     * Maps snake_case keys from FormValidationService to camelCase Livewire properties.
     */
    protected function getValidationMessages(): array
    {
        $messages = $this->getValidationService()->getValidationMessages('plant');
        
        // Map snake_case keys from service to camelCase property names
        $mappedMessages = [];
        foreach ($messages as $key => $message) {
            // Handle both regular keys and wildcard array keys (e.g., market_ids.*)
            $camelKey = match(true) {
                str_starts_with($key, 'project_id.') => str_replace('project_id.', 'projectId.', $key),
                str_starts_with($key, 'market_ids.') => str_replace('market_ids.', 'marketIds.', $key),
                default => $key,
            };
            $mappedMessages[$camelKey] = $message;
        }
        
        return $mappedMessages;
    }

    /**
     * Real-time validation as user types.
     * Livewire automatically calls this when a property is updated.
     */
    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }
    
    /**
     * Get validation rules for Livewire.
     * Livewire requires this method (or $rules property) for validation.
     * The FormValidationTrait provides this, which delegates to getValidationRules().
     */
    public function rules(): array
    {
        return $this->getValidationRules();
    }
    
    /**
     * Get validation messages for Livewire.
     * Livewire uses this method (or $messages property) for custom messages.
     * The FormValidationTrait provides this, which delegates to getValidationMessages().
     */
    public function messages(): array
    {
        return $this->getValidationMessages();
    }

    // Form submission
    public function save()
    {
        $this->validate();

        try {
            // Save logic
            $this->logCrud('created', $plant, ['name' => $plant->name]);
            $this->toastSuccess(__('plants.messages.created'));
            
            return redirect()->route('plants.index');
        } catch (\Exception $e) {
            $this->logger()->exception($e);
            $this->toastError(__('common.errors.form_submission_failed'));
        }
    }

    public function render()
    {
        $industries = Industry::forClient(session('current_client_id'))->get();
        return view('livewire.forms.plant-form', compact('industries'));
    }
}
```

#### Livewire Form Blade Template

**✅ DO: Use `wire:model` for two-way data binding**

```blade
{{-- resources/views/livewire/forms/plant-form.blade.php --}}
<form wire:submit.prevent="save" class="space-y-6">
    {{-- Real-time validation with blur --}}
    <div>
        <label for="name">{{ __('plants.create.name') }}</label>
        <input 
            type="text" 
            id="name"
            wire:model.blur="name"
            class="w-full px-3 py-2 border rounded-lg @error('name') border-red-500 @enderror"
        >
        @error('name')
            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Loading state button --}}
    <button 
        type="submit"
        wire:loading.attr="disabled"
        wire:target="save"
        class="px-4 py-2 bg-primary-500 text-white rounded-lg"
    >
        <span wire:loading.remove wire:target="save">
            {{ __('common.actions.save') }}
        </span>
        <span wire:loading wire:target="save">
            {{ __('common.actions.saving') }}
        </span>
    </button>
</form>
```

#### Using Livewire Components in Views

**✅ DO: Include Livewire component in controller views**

```php
public function create(?Project $project = null)
{
    return view('plants.create', [
        'project' => $project,
    ]);
}
```

```blade
{{-- resources/views/plants/create.blade.php --}}
@extends('layouts.app')

@section('content')
    @livewire('forms.plant-form', [
        'plant' => null,
        'project' => $project ?? null,
    ])
@endsection
```

#### Server-Side Validation

**Server-side validation is MANDATORY** - Livewire handles this automatically.

**✅ DO: Use FormValidationService for centralized rules**

```php
// Controller method
use App\Services\FormValidationService;

public function create()
{
    $validationService = app(FormValidationService::class);
    
    return view('plants.create', [
        'validationRules' => $validationService->getValidationRulesForForm('plant', forClient: true),
    ]);
}

public function store(Request $request, FormValidationService $validationService)
{
    // Get validation rules from service (single source of truth)
    $rules = $validationService->getValidationRules('plant');
    $messages = $validationService->getValidationMessages('plant');
    
    $validated = $request->validate($rules, $messages);
    
    // Use validated data
    $item = Model::create($validated);
    
    if ($request->ajax() || $request->wantsJson()) {
        return response()->json([
            'success' => true,
            'message' => __('plants.messages.created'),
            'redirect' => route('plants.index'),
        ]);
    }
    
    return redirect()->route('plants.index')
        ->with('success', __('plants.messages.created'));
}
```

**❌ DON'T: Hardcode validation rules in controllers**

```php
// ❌ BAD: Rules duplicated in controller
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255', // Duplicated from service
        'email' => 'required|email|unique:users,email',
    ]);
}
```

**Benefits of FormValidationService:**
- **Single source of truth** - Rules defined once, used everywhere
- **Automatic client-side conversion** - Rules automatically converted for frontend
- **Consistent validation** - Same rules for client and server
- **Easy maintenance** - Update rules in one place
- **Translation support** - Built-in translation key support

### User Feedback Messages

**All success, error, warning, and info messages MUST use toast notifications in the upper right corner.**

#### Success Messages

**✅ DO: Use Laravel Redirect Pattern with Session Flash Messages (Best Practice)**

For Livewire forms, always use Laravel's redirect helper with session flash messages:

```php
// Livewire Component save() method
public function save()
{
    $this->validate();
    
    if ($this->model) {
        $this->model->update($data);
        $message = __('module.messages.updated');
    } else {
        Model::create($data);
        $message = __('module.messages.created');
    }
    
    return redirect()
        ->route('module.index')
        ->with('success', $message);
}
```

**Pattern:**
- Use `redirect()->route()->with('success', $message)` 
- Never use `$this->toastSuccess()` followed by `$this->redirect()` (toast disappears on redirect)
- Session flash messages persist across redirects and are displayed by the toast notification component
- Translation keys follow pattern: `{module}.messages.{action}` or `{module}.{resource}.{action}.success_message`

**Examples:**
```php
// Admin forms
return redirect()
    ->route('admin.clients.index')
    ->with('success', __('admin.clients.edit.success_message'));

// Project forms
return redirect()
    ->route('projects.show', $this->project)
    ->with('success', __('projects.messages.updated'));

// Plant forms
return redirect()
    ->route('plants.show', $this->plant)
    ->with('success', __('plants.messages.updated'));
```

**❌ DON'T: Custom DOM Elements or Alerts**

```javascript
// ❌ BAD: Custom DOM element
const successMsg = document.createElement('div');
successMsg.className = 'fixed top-4 right-4 bg-green-500...';
document.body.appendChild(successMsg);

// ❌ BAD: Alert dialog
alert('Plant saved successfully');
```

#### Error Messages

**✅ DO: Use Toast for Errors**

```javascript
// Validation errors
ToastNotification.error('{{ __('common.validation.validation_failed') }}');

// Server errors
ToastNotification.error('{{ __('common.errors.form_submission_failed') }}');
```

```php
// Controller with validation errors
return redirect()->back()
    ->withErrors($validator)
    ->withInput();
```

#### Translation Keys for Messages

**All messages MUST use translation keys:**

```php
// ✅ GOOD: Module-specific messages
'messages' => [
    'saved' => 'Plant saved successfully.',
    'created' => 'Plant created successfully.',
],

// ✅ GOOD: Common messages
'validation' => [
    'required' => 'The :field field is required.',
    'validation_failed' => 'Validation failed',
],
```

**Usage in Blade:**

```blade
{{ __('plants.messages.saved') }}
{{ __('common.validation.required', ['field' => __('common.labels.name')]) }}
```

**Usage in JavaScript:**

```blade
<script>
    const translations = @json([
        'saved' => __('plants.messages.saved'),
        'validation_failed' => __('common.validation.validation_failed'),
    ]);
</script>
```

### Form Submission Patterns

**⚠️ IMPORTANT: AJAX submission is the recommended default for better UX. Use traditional POST only for complex forms with file uploads or when AJAX is not suitable.**

#### Livewire Submission (Recommended - Best Practice)

**✅ DO: Use Livewire for all new forms**

Livewire handles form submission automatically:

```blade
<form wire:submit.prevent="save">
    {{-- Form fields with wire:model --}}
    <input wire:model.blur="name">
    
    <button type="submit" wire:loading.attr="disabled">
        <span wire:loading.remove>Save</span>
        <span wire:loading>Saving...</span>
    </button>
</form>
```

**Benefits:**
- No JavaScript required
- Real-time validation as user types
- Automatic error display
- Loading states built-in
- Session flash messages persist across redirects

**Important: Success Messages Pattern**

Always use Laravel's redirect pattern with session flash messages:

```php
public function save()
{
    $this->validate();
    $this->model->update($data);
    
    return redirect()
        ->route('module.index')
        ->with('success', __('module.messages.updated'));
}
```

**❌ DON'T:**
```php
// ❌ BAD: Toast disappears on redirect
$this->toastSuccess(__('module.messages.updated'));
return $this->redirect(route('module.index'), navigate: true);
```

**✅ DO:**
```php
// ✅ GOOD: Session flash persists across redirect
return redirect()
    ->route('module.index')
    ->with('success', __('module.messages.updated'));
```

#### AJAX Submission (Legacy - Being Migrated)

**✅ DO: Use AJAX for most forms**

AJAX submission provides:
- **No page reload** - Faster, smoother user experience
- **Instant feedback** - Immediate validation errors and success messages
- **Better error handling** - Direct error display without page refresh
- **Loading states** - Visual feedback during submission
- **Modern UX** - Industry standard for web applications

**Using the `<x-form>` component (Recommended):**

```blade
<x-form 
    action="{{ route('plants.store') }}" 
    method="POST"
    ajax-submit="true"
    :validation-rules="$validationRules"
    success-message="{{ __('plants.messages.created') }}"
    success-redirect="{{ route('plants.index') }}"
>
    @csrf
    <!-- Form fields -->
</x-form>
```

**Manual AJAX implementation:**

```blade
<form method="POST" action="{{ route('plants.store') }}" 
      data-ajax-submit="true"
      data-success-message="{{ __('plants.messages.created') }}"
      data-success-redirect="{{ route('plants.index') }}">
    @csrf
    <!-- Form fields -->
    <button type="submit">{{ __('common.actions.save') }}</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[data-ajax-submit="true"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            FormSubmissionHandler.submitAjax(form, {
                successMessage: form.dataset.successMessage,
                successRedirect: form.dataset.successRedirect,
            });
        });
    }
});
</script>
```

**Controller response for AJAX requests:**

```php
// Controller method
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
    ], [
        'name.required' => __('common.validation.required', ['field' => __('common.labels.name')]),
        'email.email' => __('common.validation.email', ['field' => __('common.labels.email')]),
    ]);
    
    $item = Model::create($validated);
    
    // ✅ GOOD: Return JSON for AJAX requests
    if ($request->ajax() || $request->wantsJson()) {
        return response()->json([
            'success' => true,
            'message' => __('plants.messages.created'),
            'redirect' => route('plants.index'),
        ]);
    }
    
    // Fallback for traditional form submission
    return redirect()->route('plants.index')
        ->with('success', __('plants.messages.created'));
}
```

**Handling PUT/PATCH/DELETE with AJAX:**

```blade
{{-- Method spoofing for PUT/PATCH/DELETE --}}
<x-form 
    action="{{ route('plants.update', $plant) }}" 
    method="PUT"
    ajax-submit="true"
    :validation-rules="$validationRules"
    success-message="{{ __('plants.messages.updated') }}"
>
    @csrf
    <!-- Form fields -->
</x-form>
```

The `<x-form>` component automatically handles method spoofing via `_method` field and `X-HTTP-Method-Override` header for AJAX requests.

**Handling array fields with AJAX:**

```blade
{{-- Multiple inputs with same name (markets[]) --}}
<input type="text" name="markets[]" value="{{ old('markets.0') }}" />
<input type="text" name="markets[]" value="{{ old('markets.1') }}" />

{{-- Form with JSON submission for arrays --}}
<x-form 
    action="{{ route('plants.update', $plant) }}" 
    method="PUT"
    ajax-submit="true"
    json-submit="true"
    :validation-rules="$validationRules"
>
```

When `json-submit="true"`, array fields are automatically serialized into JSON format for the request.

#### Traditional POST Submission

**Use traditional POST only when:**
- Form includes file uploads (without AJAX file handling)
- Complex multi-step forms
- Legacy compatibility requirements
- When AJAX is explicitly not desired

```blade
<x-form 
    action="{{ route('plants.store') }}" 
    method="POST"
    :validation-rules="$validationRules"
    success-message="{{ __('plants.messages.created') }}"
    success-redirect="{{ route('plants.index') }}"
>
    @csrf
    <!-- Form fields -->
</x-form>
```

**Controller response for traditional forms:**

```php
public function store(Request $request)
{
    $validated = $request->validate([...]);
    $item = Model::create($validated);
    
    // Traditional redirect with flash message
    return redirect()->route('plants.index')
        ->with('success', __('plants.messages.created'));
}
```

The toast notification system automatically displays flash messages on page load.

### Form Components

**Use standardized form components when available:**

```blade
<x-form 
    action="{{ route('plants.store') }}" 
    method="POST"
    ajax-submit="true"
    :validation-rules="$validationRules"
    success-message="{{ __('plants.messages.created') }}"
    success-redirect="{{ route('plants.index') }}"
>
    <x-form-field name="name" label="{{ __('plants.create.name') }}" required :errors="$errors">
        <input type="text" name="name" id="name" value="{{ old('name') }}" />
    </x-form-field>
    
    <!-- More fields -->
</x-form>
```

**Available `<x-form>` attributes:**

- `action` - Form action URL (required)
- `method` - HTTP method: `POST`, `GET`, `PUT`, `PATCH`, `DELETE` (default: `POST`)
- `ajax-submit` - Enable AJAX submission: `"true"` or `"false"` (default: `"false"`)
- `json-submit` - Submit as JSON (for array fields): `"true"` or `"false"` (default: `"false"`)
- `validation-rules` - Array of validation rules from `FormValidationService` (required)
- `success-message` - Success message translation key (optional)
- `success-redirect` - Redirect URL after success (optional)
- `loading-text` - Custom loading button text (optional, default: "Saving...")

### Message Types

1. **Success**: Green toast, upper right corner
   - Use for: Successful saves, creates, updates, deletes
   - Duration: 5 seconds
   - Example: "Plant saved successfully"

2. **Error**: Red toast, upper right corner
   - Use for: Validation errors, server errors, failures
   - Duration: 7 seconds
   - Example: "Validation failed"

3. **Warning**: Yellow toast, upper right corner
   - Use for: Warnings, important notices
   - Duration: 6 seconds
   - Example: "Unsaved changes will be lost"

4. **Info**: Blue toast, upper right corner
   - Use for: Informational messages
   - Duration: 5 seconds
   - Example: "Processing your request"

### AJAX Best Practices

#### When to Use AJAX

**✅ Use AJAX for:**
- CRUD forms (create, update)
- Simple forms without file uploads
- Forms requiring instant feedback
- Forms with dynamic validation
- Forms that benefit from no page reload

**❌ Avoid AJAX for:**
- Forms with file uploads (unless using AJAX file handling)
- Complex multi-step wizards
- Forms requiring full page context after submission
- Legacy compatibility requirements

#### Controller Response Patterns

**✅ DO: Detect AJAX requests and return JSON**

```php
public function store(Request $request)
{
    $validated = $request->validate([...]);
    $item = Model::create($validated);
    
    // Always check for AJAX/JSON requests
    if ($request->ajax() || $request->wantsJson()) {
        return response()->json([
            'success' => true,
            'message' => __('plants.messages.created'),
            'redirect' => route('plants.index'), // Optional redirect
            'data' => $item, // Optional: return created/updated data
        ]);
    }
    
    // Fallback for traditional forms
    return redirect()->route('plants.index')
        ->with('success', __('plants.messages.created'));
}
```

**✅ DO: Return validation errors as JSON**

```php
public function store(Request $request)
{
    try {
        $validated = $request->validate([...]);
    } catch (ValidationException $e) {
        // Laravel automatically returns JSON for AJAX requests
        throw $e; // FormSubmissionHandler will handle this
    }
}
```

Laravel's `ValidationException` automatically returns JSON with 422 status for AJAX requests.

#### Error Handling

**✅ DO: Handle all error types**

```javascript
// FormSubmissionHandler automatically handles:
// - Validation errors (422) - Shows field errors and toast
// - Server errors (500) - Shows error toast
// - Network errors - Shows network error toast
// - Timeout errors - Shows timeout error toast
```

**✅ DO: Display specific error messages**

```blade
{{-- Validation errors are automatically displayed --}}
<x-form-field name="email" :errors="$errors">
    <input type="email" name="email" />
    {{-- @error directive shows server-side errors --}}
    @error('email')
        <p class="text-red-600">{{ $message }}</p>
    @enderror
</x-form-field>
```

#### Loading States

**✅ DO: Show loading state during submission**

The `<x-form>` component automatically:
- Disables submit button during submission
- Shows loading text/spinner
- Prevents double submission
- Re-enables button on error

**Manual loading state:**

```blade
<button type="submit" id="submit-btn">
    <span class="btn-text">{{ __('common.actions.save') }}</span>
    <span class="btn-loading hidden">Saving...</span>
</button>

<script>
form.addEventListener('submit', function() {
    document.getElementById('submit-btn').disabled = true;
    document.querySelector('.btn-text').classList.add('hidden');
    document.querySelector('.btn-loading').classList.remove('hidden');
});
</script>
```

#### Array Field Handling

**✅ DO: Use JSON submission for array fields**

```blade
{{-- Multiple inputs with same name --}}
<input type="text" name="markets[]" />
<input type="text" name="markets[]" />

{{-- Enable JSON submission --}}
<x-form json-submit="true" ...>
```

**Server-side handling:**

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'markets.*' => 'nullable|string|max:255',
    ]);
    
    // Handle array data
    $markets = array_filter($validated['markets'] ?? [], fn($m) => !empty($m));
    $item->markets = $markets;
    $item->save();
}
```

#### Method Spoofing

**✅ DO: Use proper HTTP methods**

```blade
{{-- PUT for updates --}}
<x-form method="PUT" action="{{ route('plants.update', $plant) }}">

{{-- DELETE for deletions --}}
<x-form method="DELETE" action="{{ route('plants.destroy', $plant) }}">
```

The component automatically handles:
- `_method` field for traditional forms
- `X-HTTP-Method-Override` header for AJAX requests

### Form Validation Checklist

Before submitting code, ensure:

- [ ] **AJAX submission** is used for simple forms (default)
- [ ] **Client-side validation** runs before form submission
- [ ] **Server-side validation** is implemented in controller
- [ ] **Controller returns JSON** for AJAX requests (`$request->ajax()` check)
- [ ] **Success messages** use toast notifications (upper right)
- [ ] **Error messages** use toast notifications or field-level display
- [ ] **All messages** use translation keys (no hardcoded strings)
- [ ] **Translation files** exist for both `en` and `de`
- [ ] **Loading states** are shown during submission
- [ ] **Network errors** are handled gracefully
- [ ] **No `alert()` or `confirm()` dialogs** (use toast/modals instead)
- [ ] **Field-level errors** display below relevant fields
- [ ] **Validation summary** shows at top of form if needed
- [ ] **Array fields** use JSON submission when needed
- [ ] **Method spoofing** is handled correctly (PUT/PATCH/DELETE)
- [ ] **CSRF token** is included in all forms

### Common Mistakes to Avoid

1. **Hardcoded success messages:**
   ```javascript
   ❌ alert('Plant saved successfully');
   ✅ ToastNotification.success(translations.plants.saved);
   ```

2. **Custom DOM elements for messages:**
   ```javascript
   ❌ const msg = document.createElement('div'); msg.className = '...';
   ✅ ToastNotification.success(message);
   ```

3. **Missing translation keys:**
   ```php
   ❌ ->with('success', 'Plant saved successfully');
   ✅ ->with('success', __('plants.messages.saved'));
   ```

4. **Skipping client-side validation:**
   ```javascript
   ❌ formElement.submit(); // No validation
   ✅ if (validator.validate()) { formElement.submit(); }
   ```

5. **Using alert() for confirmations:**
   ```javascript
   ❌ if (confirm('Delete?')) { ... }
   ✅ Use confirmation modal component
   ```

6. **Not checking for AJAX requests in controller:**
   ```php
   ❌ return redirect()->route('plants.index'); // Always redirects, breaks AJAX
   ✅ if ($request->ajax()) { return response()->json([...]); }
   ```

7. **Missing JSON response for AJAX:**
   ```php
   ❌ return redirect()->back()->withErrors($validator); // HTML response for AJAX
   ✅ throw ValidationException::withMessages($errors); // Auto JSON for AJAX
   ```

8. **Not handling array fields correctly:**
   ```blade
   ❌ <input name="markets[]" /> <!-- Without json-submit="true" -->
   ✅ <x-form json-submit="true"> <!-- Enables proper array serialization -->
   ```

9. **Forgetting CSRF token in AJAX:**
   ```javascript
   ❌ fetch(url, { method: 'POST', body: formData }); // Missing CSRF
   ✅ FormSubmissionHandler.submitAjax(form); // Automatically includes CSRF
   ```

10. **Not showing loading states:**
    ```blade
    ❌ <button type="submit">Save</button> <!-- No loading feedback -->
    ✅ <x-form> <!-- Automatically shows loading state -->
    ```

### Resources

- [Form Validation Usage](./docs/FORM_VALIDATION_USAGE.md) - `x-form` component usage
- [Validation Best Practices](./docs/VALIDATION_BEST_PRACTICES.md) - Validation patterns
- [Toast Notification Component](./resources/views/components/toast-notification.blade.php) - Toast system reference
- [Laravel Validation Documentation](https://laravel.com/docs/validation) - Server-side validation
- [Logging System Guide](./docs/LOGGING_GUIDE.md) - Complete logging documentation and best practices

## Livewire Best Practices

**⚠️ IMPORTANT: Livewire is the preferred approach for dynamic, interactive components in Targus. Use Livewire for forms, index pages with filtering, and modals.**

Livewire provides a full-stack framework that allows building dynamic interfaces with server-side logic while maintaining the reactivity of a single-page application. This section outlines best practices for using Livewire in Targus.

### When to Use Livewire

**✅ Use Livewire for:**
- Forms (create/edit) - Better validation, state management, and UX
- Index pages with search/filtering - Real-time filtering without page reloads
- Modals (confirmation, enrichment, template selection) - Server-side validation and state management
- Dynamic components that need server-side logic
- Components that benefit from real-time updates

**❌ Don't use Livewire for:**
- Simple static content
- Components that don't need server interaction
- Heavy client-side calculations that don't need server data

### Livewire Component Structure

#### Component Location

- **Form components:** `app/Livewire/Forms/` (e.g., `ProjectForm.php`, `PlantForm.php`)
- **Index components:** `app/Livewire/` (e.g., `ProjectsIndex.php`, `PlantsIndex.php`)
- **Modal components:** `app/Livewire/Modals/` (e.g., `ConfirmationModal.php`, `EnrichmentModal.php`)
- **View templates:** `resources/views/livewire/` (mirror the component structure)

#### Component Naming Convention

- **Forms:** `{Model}Form` (e.g., `ProjectForm`, `PlantForm`)
- **Index pages:** `{Model}Index` (e.g., `ProjectsIndex`, `PlantsIndex`)
- **Modals:** `{Purpose}Modal` (e.g., `ConfirmationModal`, `EnrichmentModal`)
- **Admin components:** `Admin\{Model}Index` (e.g., `Admin\ClientsIndex`)

### Form Components

#### Basic Structure

```php
<?php

namespace App\Livewire\Forms;

use App\Models\Project;
use App\Services\FormValidationService;
use App\Traits\FormValidationTrait;
use App\Traits\WithToastNotifications;
use App\Traits\LogsActivity;
use Livewire\Component;

class ProjectForm extends Component
{
    use FormValidationTrait;
    use WithToastNotifications;
    use LogsActivity;

    // Public properties for form fields (use camelCase)
    public string $name = '';
    public string $code = '';
    public ?string $description = null;
    public array $userIds = [];
    
    // Component state
    public ?Project $project = null;
    
    protected FormValidationService $validationService;

    public function boot()
    {
        $this->validationService = app(FormValidationService::class);
    }

    public function mount(?Project $project = null)
    {
        $this->project = $project;
        
        if ($project) {
            // Edit mode - load existing data
            $this->name = $project->name;
            $this->code = $project->code;
            // ... load other fields
        } else {
            // Create mode - initialize defaults
            $this->name = '';
            $this->code = '';
            // ... set defaults
        }
        
        $this->loadDependencies();
    }

    public function save()
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $data = [
                    'name' => $this->name,
                    'code' => $this->code,
                    // ... map all fields
                ];

                if ($this->project) {
                    $this->project->update($data);
                    $this->logCrud('updated', $this->project);
                    $this->toastSuccess(__('projects.updated_successfully'));
                } else {
                    $this->project = Project::create($data);
                    $this->logCrud('created', $this->project);
                    $this->toastSuccess(__('projects.created_successfully'));
                }
            });

            return redirect()->route('projects.show', $this->project);
        } catch (\Exception $e) {
            \Log::error('Project save error: ' . $e->getMessage());
            $this->toastError(__('common.error.generic_save'));
        }
    }

    protected function getValidationRules(): array
    {
        $rules = $this->getValidationService()->getValidationRules('project');
        
        // Map snake_case keys to camelCase Livewire properties
        $mappedRules = [];
        foreach ($rules as $key => $rule) {
            $camelKey = match(true) {
                $key === 'user_ids' => 'userIds',
                default => $key,
            };
            $mappedRules[$camelKey] = $rule;
        }
        
        return $mappedRules;
    }

    public function render()
    {
        return view('livewire.forms.project-form');
    }
}
```

#### Form View Template

```blade
<form wire:submit.prevent="save">
    <!-- Name Field -->
    <div>
        <label for="name" class="block text-sm font-medium text-secondary-500 dark:text-gray-200 mb-2">
            {{ __('projects.fields.name') }}
        </label>
        <input 
            type="text" 
            id="name"
            wire:model.blur="name"
            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-400 rounded-lg bg-white dark:bg-secondary-500 text-secondary-500 dark:text-white focus:ring-2 focus:ring-primary-500 @error('name') border-red-500 @enderror"
        >
        @error('name')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <!-- Submit Button -->
    <div class="mt-6 flex justify-end">
        <button 
            type="submit"
            wire:loading.attr="disabled"
            wire:target="save"
            class="px-6 py-3 bg-primary-500 text-white rounded-lg font-medium hover:bg-primary-600 transition-colors disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="save">
                {{ __('common.actions.save') }}
            </span>
            <span wire:loading wire:target="save">
                {{ __('common.actions.saving') }}
            </span>
        </button>
    </div>
</form>
```

### Index Components with Search & Filters

For the **Blade layout** of list pages (page header, collapsible filters, data table, pagination footer), follow [List pages and data tables (Livewire)](#list-pages-and-data-tables-livewire). The snippets below focus on **component PHP** and a minimal view fragment.

#### Component Structure

```php
<?php

namespace App\Livewire;

use App\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectsIndex extends Component
{
    use WithPagination;

    // Filter properties
    public $search = '';
    public $status = '';
    public $isActive = '';

    // UI state
    public $filtersOpen = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'isActive' => ['except' => ''],
    ];

    public function mount()
    {
        $this->filtersOpen = request()->anyFilled(['search', 'status', 'is_active']);
        $this->search = request('search', '');
        $this->status = request('status', '');
        $this->isActive = request('is_active', '');
    }

    public function updatingSearch()
    {
        $this->resetPage(); // Reset pagination when searching
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->status = '';
        $this->isActive = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = Project::query();

        if (!empty($this->search)) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        if (!empty($this->status)) {
            $query->where('status', $this->status);
        }

        $projects = $query->latest()->paginate(15);
        
        return view('livewire.projects-index', compact('projects'));
    }
}
```

#### Index View Template

```blade
<div>
    <!-- Search Input -->
    <input 
        type="text" 
        wire:model.live.debounce.500ms="search"
        placeholder="{{ __('projects.search_placeholder') }}"
        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-400 rounded-lg"
    >

    <!-- Loading State -->
    <div wire:loading class="mb-4">
        <div class="flex items-center justify-center">
            <svg class="w-6 h-6 animate-spin text-primary-500">...</svg>
            <span>{{ __('common.actions.loading') }}...</span>
        </div>
    </div>

    <!-- Results -->
    <div wire:loading.remove>
        @foreach($projects as $project)
            <div wire:key="project-{{ $project->id }}">
                <!-- Project card -->
            </div>
        @endforeach
    </div>

    <!-- Pagination -->
    {{ $projects->links() }}
</div>
```

### Modal Components

#### Confirmation Modal

**✅ DO: Use the reusable ConfirmationModal component**

```blade
<!-- In your component/view -->
<button 
    type="button"
    onclick="window.livewire.find('confirmation-modal').call('open', 
        '{{ __('common.confirm_delete') }}',
        '{{ __('projects.confirm_delete_message') }}',
        'deleteProject',
        [{{ $project->id }}]
    )"
>
    Delete
</button>

<!-- Listen for the event in your component -->
#[On('deleteProject')]
public function deleteProject($projectId)
{
    Project::find($projectId)->delete();
    $this->toastSuccess(__('projects.deleted_successfully'));
}
```

**❌ DON'T: Use browser confirm() dialogs**

```blade
❌ <form onsubmit="return confirm('Delete?');">
```

#### Enrichment Modal

```blade
<!-- Dispatch event to show enrichment modal -->
$this->dispatch('showEnrichmentModal', 
    suggestions: $suggestions,
    title: __('common.ai.enrichment_suggestions'),
    onSelect: 'applyEnrichment'
);

<!-- Listen in component -->
#[On('applyEnrichment')]
public function applyEnrichment($suggestion)
{
    $this->description = $suggestion['description'];
    // ... apply other fields
}
```

### Key Livewire Patterns

#### 1. Property Binding

**✅ DO: Use appropriate wire:model modifiers**

```blade
<!-- Real-time updates (debounced for search) -->
<input wire:model.live.debounce.500ms="search">

<!-- Update on blur (for form fields) -->
<input wire:model.blur="name">

<!-- Update on change (for selects) -->
<select wire:model.live="status">
```

#### 2. Loading States

**✅ DO: Show loading indicators**

```blade
<button wire:click="save" wire:loading.attr="disabled">
    <span wire:loading.remove wire:target="save">Save</span>
    <span wire:loading wire:target="save">Saving...</span>
</button>

<!-- Or for entire sections -->
<div wire:loading>
    Loading...
</div>
<div wire:loading.remove>
    Content
</div>
```

#### 3. Dynamic Lists

**✅ DO: Always use wire:key for dynamic lists**

```blade
@foreach($items as $item)
    <div wire:key="item-{{ $item->id }}">
        <!-- Item content -->
    </div>
@endforeach

<!-- For arrays with index -->
@foreach($markets as $index => $market)
    <div wire:key="market-{{ $index }}">
        <!-- Market content -->
    </div>
@endforeach
```

#### 4. Events and Listeners

**✅ DO: Use Livewire events for component communication**

```php
// In one component
$this->dispatch('item-updated', $itemId);

// In another component
#[On('item-updated')]
public function refreshData($itemId)
{
    // Refresh data
}
```

#### 5. Validation

**✅ DO: Use FormValidationTrait for consistent validation**

```php
use App\Traits\FormValidationTrait;

class ProjectForm extends Component
{
    use FormValidationTrait;
    
    protected function getValidationRules(): array
    {
        $rules = $this->getValidationService()->getValidationRules('project');
        // Map snake_case to camelCase
        return $mappedRules;
    }
}
```

#### 6. Toast Notifications

**✅ DO: Use WithToastNotifications trait**

```php
use App\Traits\WithToastNotifications;

class ProjectForm extends Component
{
    use WithToastNotifications;
    
    public function save()
    {
        // ... save logic
        $this->toastSuccess(__('projects.saved_successfully'));
    }
}
```

### Authorization in Livewire Components

**⚠️ IMPORTANT: All Livewire components that perform CRUD operations MUST check authorization using Laravel Policies.**

#### ✅ DO: Use Authorization in Livewire Components

```php
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProjectForm extends Component
{
    use AuthorizesRequests;
    
    public function mount(?Project $project = null)
    {
        if ($project) {
            // Check authorization for editing
            $this->authorize('update', $project);
        } else {
            // Check authorization for creating
            $this->authorize('create', Project::class);
        }
    }
    
    public function save()
    {
        // Re-check authorization before saving
        if ($this->project) {
            $this->authorize('update', $this->project);
        } else {
            $this->authorize('create', Project::class);
        }
        
        // ... save logic
    }
}
```

#### ✅ DO: Use Authorization in Index Components

```php
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProjectsIndex extends Component
{
    use AuthorizesRequests;
    
    public function mount()
    {
        // Check authorization for listing
        $this->authorize('viewAny', Project::class);
    }
    
    #[On('deleteProject')]
    public function deleteProject($projectId)
    {
        $project = Project::findOrFail($projectId);
        
        // Check authorization for deletion
        $this->authorize('delete', $project);
        
        $project->delete();
    }
}
```

#### ❌ DON'T: Skip Authorization Checks

```php
// ❌ BAD: No authorization check
public function mount(?Project $project = null)
{
    $this->project = $project;
    // Missing authorization check
}

// ✅ GOOD: Always check authorization
public function mount(?Project $project = null)
{
    if ($project) {
        $this->authorize('update', $project);
    } else {
        $this->authorize('create', Project::class);
    }
    $this->project = $project;
}
```

**Authorization Checklist for Livewire Components:**
- [ ] **Form components** check `create` or `update` in `mount()` and `save()`
- [ ] **Index components** check `viewAny` in `mount()` or `render()`
- [ ] **Delete actions** check `delete` before deletion
- [ ] **Custom actions** use custom policy methods (e.g., `export`, `complete`)
- [ ] **Use `AuthorizesRequests` trait** in all Livewire components that need authorization

### Livewire Checklist

Before submitting code, ensure:

- [ ] **Component location** follows naming convention (`Forms/`, `Modals/`, etc.)
- [ ] **Public properties** use camelCase (not snake_case)
- [ ] **wire:model** uses appropriate modifiers (`.live`, `.blur`, `.debounce`)
- [ ] **wire:key** is used for all dynamic lists
- [ ] **wire:loading** states are implemented
- [ ] **Validation** uses `FormValidationTrait` and `FormValidationService`
- [ ] **Toast notifications** use `WithToastNotifications` trait
- [ ] **Query string** preservation for filters (`$queryString` property)
- [ ] **Pagination** uses `WithPagination` trait
- [ ] **Events** are used for component communication
- [ ] **Controller methods** are simplified (logic moved to Livewire)
- [ ] **Translation keys** are used (no hardcoded strings)
- [ ] **Error handling** is implemented with try-catch
- [ ] **Logging** uses `LogsActivity` trait for CRUD operations
- [ ] **Authorization** uses `AuthorizesRequests` trait and Laravel Policies

### Common Livewire Mistakes to Avoid

1. **Missing wire:key in loops:**
   ```blade
   ❌ @foreach($items as $item)<div>...</div>@endforeach
   ✅ @foreach($items as $item)<div wire:key="item-{{ $item->id }}">...</div>@endforeach
   ```

2. **Not resetting pagination on filter change:**
   ```php
   ❌ public function updatingSearch() { /* Missing resetPage() */ }
   ✅ public function updatingSearch() { $this->resetPage(); }
   ```

3. **Using snake_case for Livewire properties:**
   ```php
   ❌ public $user_ids = [];
   ✅ public $userIds = [];
   ```

4. **Not mapping validation rules:**
   ```php
   ❌ return $this->getValidationService()->getValidationRules('project');
   ✅ // Map snake_case keys to camelCase properties
   ```

5. **Missing loading states:**
   ```blade
   ❌ <button wire:click="save">Save</button>
   ✅ <button wire:click="save" wire:loading.attr="disabled">
        <span wire:loading.remove>Save</span>
        <span wire:loading>Saving...</span>
      </button>
   ```

6. **Using browser confirm() instead of Livewire modal:**
   ```blade
   ❌ <form onsubmit="return confirm('Delete?');">
   ✅ Use ConfirmationModal component
   ```

7. **Not using query string preservation:**
   ```php
   ❌ // Filters lost on pagination
   ✅ protected $queryString = ['search' => ['except' => '']];
   ```

### Resources

- [Livewire Documentation](https://livewire.laravel.com/docs) - Official Livewire documentation
- [Livewire Components Reference](./app/Livewire/) - See existing components for examples
- [Form Validation Service](./app/Services/FormValidationService.php) - Centralized validation rules
- [Form Validation Trait](./app/Traits/FormValidationTrait.php) - Reusable validation logic

## Questions?

If you have questions or need help, please:
1. Check the existing documentation
2. Review similar code in the codebase
3. Ask in team discussions
4. Create an issue for clarification

Thank you for contributing to Targus! 🚀
