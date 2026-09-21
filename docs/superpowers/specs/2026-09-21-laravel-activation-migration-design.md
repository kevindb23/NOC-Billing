# Laravel Activation Page Migration Design

## Goal

Migrate the Activation page from the React frontend to a Laravel-rendered Blade/Livewire page while retaining Vite for JavaScript and CSS asset building. The existing database, authentication, permissions, activation services, ACS integration, and network automation remain the source of truth.

## Scope

This first slice covers the Activation page only:

- Activation history and pagination.
- Loading, empty, error, and unavailable states.
- New activation form.
- Activation preview and creation.
- Deactivation and deletion actions.
- Preset management required by the page.
- Existing permission checks and organization scoping.

ONT, ACS, OLT, BNG, billing, and administration pages remain on the current frontend until their own migration slices are approved.

## Architecture

Laravel owns page rendering, authorization, validation, queries, and mutations. Blade provides the page shell and Livewire provides server-backed interaction for tables, forms, previews, and action feedback. Alpine.js handles only local UI behavior such as modal visibility and small form presentation state. Vite remains a build tool for the compiled JavaScript and CSS; it is not run as a production web server.

The existing `ActivationController` and domain services will be reused where their contracts are appropriate. Any page-specific Livewire actions will call application services or controller-level request validation rather than duplicating OLT, ACS, RADIUS, or subscriber provisioning logic.

## User flow

1. User opens `/activation`.
2. Laravel verifies authentication and activation permissions before rendering the page.
3. The initial page query loads a bounded, paginated activation history and the counts needed by the empty state.
4. User opens the activation form; available OLT, ONT, subscriber, plan, and preset data is loaded through Livewire actions with explicit pagination or scoped selectors.
5. User requests a preview; the existing preview rules validate the selection without provisioning equipment.
6. User confirms creation; the existing activation workflow performs the persisted setup and queued device actions.
7. Deactivate and delete actions require confirmation and display the server response without a full-page JavaScript API loop.

## Performance requirements

- The initial page must use one server-rendered request for the page state rather than five independent browser API requests.
- Activation history must be paginated and must not request `per_page=100` by default.
- Related records must be eager-loaded explicitly to avoid N+1 queries.
- Remote ACS, OLT, BNG, and RADIUS operations must not run while rendering the page.
- Long-running provisioning remains queued or explicitly triggered by a mutation.
- Vite must produce compiled assets during deployment and must not be required at runtime.

## Error and state behavior

- Authorization failures return the existing application permission response.
- Validation errors remain attached to the relevant form fields.
- Empty history shows the existing activation guidance and a create action when permitted.
- Failed previews and mutations show the backend message without exposing credentials or raw device output.
- A failed remote operation must leave the activation status and audit information consistent with the existing workflow.

## Compatibility and rollback

- Existing `/api/v1/activations` endpoints remain available for the React pages during the staged migration.
- The React Activation page remains in the repository until the Blade page passes functional and deployment verification.
- The migrated page will use a separate route or feature flag during validation, then replace the current `/activation` route only after approval.
- No database reset or destructive migration is part of this slice.

## Testing

- Feature tests cover authenticated access, permission denial, paginated history, empty state data, validation failures, preview, creation, deactivation, and deletion.
- Livewire/component tests cover form state, validation display, loading/error state transitions, and action confirmation behavior.
- Existing activation service and API tests remain green.
- The frontend build remains green because Vite continues to compile the shared asset entrypoints.

## Out of scope

- Rewriting the ACS, ONT, OLT, BNG, billing, or administration pages.
- Removing React or TypeScript from the repository.
- Removing Node.js from build tooling.
- Changing the activation database schema unless an existing query is proven insufficient and the change is separately approved.
