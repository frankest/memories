import axios from '@nextcloud/axios';

import { API } from '@services/API';

/** Stored order of an album */
export type AlbumOrderState = {
  /** File IDs in the stored order */
  fileIds: number[];
  /** Revision to pass with the next change */
  revision: string;
  /** Whether the stored order is in effect */
  manual: boolean;
};

/**
 * Fetch the stored order of an album.
 * Only file IDs are requested, since the timeline already displays the photos.
 */
export async function getAlbumOrder(album: string): Promise<AlbumOrderState> {
  const { data } = await axios.get<AlbumOrderState>(API.ALBUM_ORDER(album, true));
  return data;
}

/**
 * Store a new order and return the revision to use for the next change.
 */
export async function saveAlbumOrder(
  album: string,
  fileIds: number[],
  revision: string,
  manual = true,
): Promise<string> {
  const { data } = await axios.put<{ revision: string }>(API.ALBUM_ORDER(album), {
    fileIds,
    revision,
    manual,
  });
  return data.revision;
}

/**
 * Remove the stored order of an album, restoring its default date order.
 */
export async function resetAlbumOrder(album: string): Promise<void> {
  await axios.delete(API.ALBUM_ORDER(album));
}

/**
 * Move a group of files to the position of an anchor file.
 * Files that are not moved keep their relative order, so that photos
 * which are hidden in the timeline (e.g. stacked RAW files) stay in place.
 *
 * @param fileIds Complete order of the album
 * @param moved File IDs to move
 * @param anchor File ID to insert the moved files at
 * @param after Whether to insert after the anchor instead of before
 * @returns The new order
 */
export function moveInAlbumOrder(fileIds: number[], moved: number[], anchor: number, after: boolean): number[] {
  const moving = new Set(moved);
  const rest = fileIds.filter((id) => !moving.has(id));

  const at = rest.indexOf(anchor);
  if (at < 0) {
    throw new Error(`Cannot move files: ${anchor} is not part of the album`);
  }

  const index = after ? at + 1 : at;
  return [...rest.slice(0, index), ...fileIds.filter((id) => moving.has(id)), ...rest.slice(index)];
}
