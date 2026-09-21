# Manual album order

In an existing album, choose **Manual order** in the sorting menu. The grid
stays the same, except that the photos are shown without date headers, in the
order stored for this album.

Drag one or more photos to another position to change the order. With multiple
photos selected (selection icon, Ctrl/Shift click or touch selection), the whole
selection is moved together. The new order is stored immediately for everyone
viewing this album in Memories; a toast offers to undo the change.

**Reset order** in the album header removes the stored order, so the album is
shown in the default date order again. The button only appears while the album
is displayed in manual order.

Owners and user/group collaborators may edit. Public link visitors can only
view the saved order. The separate Nextcloud Photos app does not use this order.

New album memberships appear at the end. Removing and adding the same file again
also places it at the end. Each album has its own order.

If the album or its order changed while the view was open (e.g. on another
device), the change is rejected and the view is reloaded to show the current
state.

Reordering starts from the order stored on the server, because the grid can hide
photos (stacked RAW files and identical duplicates). Those hidden photos keep
their place.

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

Drag and drop uses native HTML5 drag events, so reordering requires a mouse or
pen; touch devices cannot reorder photos yet.

