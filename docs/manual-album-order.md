# Manual album order

In an existing album, choose **Sort mode**. The editor appears directly
in the album content area, without a dialog. Images do not open while editing.
Drag photos into order with a mouse or touch; dragging near the top or bottom
edge scrolls the grid. A line marks the insertion position. Alternatively,
use the arrow buttons (also available with Alt+Left/Right on a focused tile).
Choose **Save order** to publish the order for everyone viewing this album in
Memories. **Cancel** leaves the stored order unchanged.

Owners and user/group collaborators may edit. Public link visitors can only
view the saved order. The separate Nextcloud Photos app does not use this order.

New album memberships appear at the end. Removing and adding the same file again
also places it at the end. Each album has its own order. **Restore date order**
returns the album to the existing chronological view.

If the album or its order changed while the editor was open, saving is rejected;
choose **Reload album** to load the current state and reorder again.

## Development

The feature adds the Memories-owned table memories_album_order. When testing
in an existing installation without changing the app version, apply the migration:

    php occ migrations:execute memories 900001Date20260918120000

Run the focused integration tests inside the Nextcloud development container:

    vendor/bin/phpunit --filter AlbumOrderTest

The tests create temporary users, albums and files and remove them afterward.
Nextcloud release images omit the core test autoloader; the Memories bootstrap
loads it only if present, while always bootstrapping Nextcloud itself.

The manual view loads the album metadata as one group. Thumbnails are loaded
lazily and the timeline remains virtualized, but very large albums require
proportionally more metadata memory. No automated browser tests have been run.
