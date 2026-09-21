<template>
  <div class="top-matter">
    <NcActions v-if="!isAlbumList">
      <NcActionButton :aria-label="t('memories', 'Back')" @click="back()">
        {{ t('memories', 'Back') }}
        <template #icon> <BackIcon :size="20" /> </template>
      </NcActionButton>
    </NcActions>

    <div class="name">{{ name }}</div>

    <div class="right-actions album-actions">
      <NcButton v-if="showResetOrder" variant="secondary" @click="resetOrder()">
        <template #icon><UndoIcon :size="20" /></template>
        {{ t('memories', 'Reset order') }}
      </NcButton>
      <ViewSortMenu v-if="!isAlbumList" setting="sort_album_order" :manual="true" />
      <NcActions v-if="isAlbumList" :title="t('memories', 'Sorting order')" :forceMenu="true">
        <template #icon>
          <template v-if="isDateSort">
            <SortDateDIcon v-if="isDescending" :size="20" />
            <SortDateAIcon v-else :size="20" />
          </template>
          <template v-else-if="config.album_list_sort & c.ALBUM_SORT_FLAGS.NAME">
            <SlotAlphabeticalDIcon v-if="isDescending" :size="20" />
            <SlotAlphabeticalAIcon v-else :size="20" />
          </template>
          <template v-else>
            <SortIcon :size="20" />
          </template>
        </template>

        <NcActionRadio
          name="sort"
          :aria-label="t('memories', 'Last updated')"
          :model-value="sortField"
          value="last_update"
          @change="changeSort(c.ALBUM_SORT_FLAGS.LAST_UPDATE)"
          close-after-click
        >
          {{ t('memories', 'Last updated') }}
        </NcActionRadio>

        <NcActionRadio
          name="sort"
          :aria-label="t('memories', 'Creation date')"
          :model-value="sortField"
          value="created"
          @change="changeSort(c.ALBUM_SORT_FLAGS.CREATED)"
          close-after-click
        >
          {{ t('memories', 'Creation date') }}
        </NcActionRadio>

        <NcActionRadio
          name="sort"
          :aria-label="t('memories', 'Album name')"
          :model-value="sortField"
          value="name"
          @change="changeSort(c.ALBUM_SORT_FLAGS.NAME)"
          close-after-click
        >
          {{ t('memories', 'Album name') }}
        </NcActionRadio>

        <NcActionSeparator />

        <NcActionRadio
          name="sort-dir"
          :aria-label="isDateSort ? t('memories', 'Oldest first') : t('memories', 'Ascending')"
          :model-value="sortDir"
          value="asc"
          @change="setDescending(false)"
          close-after-click
        >
          {{ isDateSort ? t('memories', 'Oldest first') : t('memories', 'Ascending') }}
        </NcActionRadio>

        <NcActionRadio
          name="sort-dir"
          :aria-label="isDateSort ? t('memories', 'Newest first') : t('memories', 'Descending')"
          :model-value="sortDir"
          value="desc"
          @change="setDescending(true)"
          close-after-click
        >
          {{ isDateSort ? t('memories', 'Newest first') : t('memories', 'Descending') }}
        </NcActionRadio>
      </NcActions>

      <NcActions :inline="isMobile ? 1 : 3">
        <NcActionButton
          :aria-label="t('memories', 'Create new album')"
          :title="t('memories', 'Create new album')"
          @click="refs().createModal.open(false)"
          close-after-click
          v-if="isAlbumList"
        >
          {{ t('memories', 'Create new album') }}
          <template #icon> <PlusIcon :size="20" /> </template>
        </NcActionButton>
        <NcActionButton
          :aria-label="t('memories', 'Share album')"
          :title="t('memories', 'Share album')"
          @click="openShareModal()"
          close-after-click
          v-if="canEditAlbum"
        >
          {{ t('memories', 'Share album') }}
          <template #icon> <ShareIcon :size="20" /> </template>
        </NcActionButton>
        <NcActionButton
          :aria-label="t('memories', 'Download album')"
          :title="t('memories', 'Download album')"
          @click="downloadAlbum()"
          close-after-click
          v-if="!isAlbumList"
        >
          {{ t('memories', 'Download album') }}
          <template #icon> <DownloadIcon :size="20" /> </template>
        </NcActionButton>
        <NcActionButton
          :aria-label="t('memories', 'Edit album details')"
          :title="t('memories', 'Edit album details')"
          @click="refs().createModal.open(true)"
          close-after-click
          v-if="canEditAlbum"
        >
          {{ t('memories', 'Edit album details') }}
          <template #icon> <EditIcon :size="20" /> </template>
        </NcActionButton>
        <NcActionButton
          :aria-label="t('memories', 'Remove album')"
          :title="t('memories', 'Remove album')"
          @click="refs().deleteModal.open()"
          close-after-click
          v-if="!isAlbumList"
        >
          {{ t('memories', 'Remove album') }}
          <template #icon> <DeleteIcon :size="20" /> </template>
        </NcActionButton>
      </NcActions>
    </div>

    <AlbumCreateModal ref="createModal" />
    <AlbumDeleteModal ref="deleteModal" />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

import UserConfig from '@mixins/UserConfig';
import NcButton from '@nextcloud/vue/components/NcButton';
import NcActions from '@nextcloud/vue/components/NcActions';
import NcActionButton from '@nextcloud/vue/components/NcActionButton';
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox';
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio';
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator';

import axios from '@nextcloud/axios';
import { showError } from '@nextcloud/dialogs';

import AlbumCreateModal from '@components/modal/AlbumCreateModal.vue';
import AlbumDeleteModal from '@components/modal/AlbumDeleteModal.vue';
import ViewSortMenu from './ViewSortMenu.vue';

import * as albumOrderApi from '@services/album-order';
import { downloadWithHandle } from '@services/dav';
import { API } from '@services/API';
import * as utils from '@services/utils';

import BackIcon from 'vue-material-design-icons/ArrowLeft.vue';
import DownloadIcon from 'vue-material-design-icons/Download.vue';
import EditIcon from 'vue-material-design-icons/Pencil.vue';
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue';
import PlusIcon from 'vue-material-design-icons/Plus.vue';
import ShareIcon from 'vue-material-design-icons/ShareVariant.vue';
import SortIcon from 'vue-material-design-icons/SortVariant.vue';
import UndoIcon from 'vue-material-design-icons/Undo.vue';
import SlotAlphabeticalAIcon from 'vue-material-design-icons/SortAlphabeticalAscending.vue';
import SlotAlphabeticalDIcon from 'vue-material-design-icons/SortAlphabeticalDescending.vue';
import SortDateAIcon from 'vue-material-design-icons/SortCalendarAscending.vue';
import SortDateDIcon from 'vue-material-design-icons/SortCalendarDescending.vue';

export default defineComponent({
  name: 'AlbumTopMatter',
  components: {
    NcButton,
    NcActions,
    NcActionButton,
    NcActionCheckbox,
    NcActionRadio,
    NcActionSeparator,

    AlbumCreateModal,
    AlbumDeleteModal,
    ViewSortMenu,

    BackIcon,
    DownloadIcon,
    EditIcon,
    DeleteIcon,
    PlusIcon,
    ShareIcon,
    SortIcon,
    UndoIcon,
    SlotAlphabeticalAIcon,
    SlotAlphabeticalDIcon,
    SortDateAIcon,
    SortDateDIcon,
  },

  mixins: [UserConfig],

  data: () => ({
    /** Whether the album is currently shown in manual order */
    manualOrder: false,
  }),

  created() {
    utils.bus.on('memories:album-order:state', this.onAlbumOrderState);
  },

  beforeUnmount() {
    utils.bus.off('memories:album-order:state', this.onAlbumOrderState);
  },

  computed: {
    isAlbumList(): boolean {
      return !this.$route.params.name?.toString();
    },

    /** Offer to remove the manual order of this album */
    showResetOrder(): boolean {
      return !this.isAlbumList && this.manualOrder;
    },

    canEditAlbum(): boolean {
      return !this.isAlbumList && this.$route.params.user?.toString() === utils.uid;
    },

    name(): string {
      // Album name is displayed in the dynamic top matter (timeline)
      return this.isAlbumList ? this.t('memories', 'Albums') : String();
    },

    isMobile(): boolean {
      return utils.isMobile();
    },

    isDateSort(): boolean {
      return (
        !!(this.config.album_list_sort & this.c.ALBUM_SORT_FLAGS.CREATED) ||
        !!(this.config.album_list_sort & this.c.ALBUM_SORT_FLAGS.LAST_UPDATE)
      );
    },

    isDescending(): boolean {
      return !!(this.config.album_list_sort & this.c.ALBUM_SORT_FLAGS.DESCENDING);
    },

    sortField(): string {
      if (this.config.album_list_sort & this.c.ALBUM_SORT_FLAGS.CREATED) return 'created';
      if (this.config.album_list_sort & this.c.ALBUM_SORT_FLAGS.NAME) return 'name';
      return 'last_update';
    },

    sortDir(): string {
      return this.isDescending ? 'desc' : 'asc';
    },
  },

  methods: {
    refs() {
      return this.$refs as {
        createModal: InstanceType<typeof AlbumCreateModal>;
        deleteModal: InstanceType<typeof AlbumDeleteModal>;
      };
    },

    /** The timeline reports whether the album uses a manual order */
    onAlbumOrderState({ manual }: utils.BusEvent['memories:album-order:state']) {
      this.manualOrder = !!manual;
    },

    /** Identifier of the album in the current view */
    albumId(): string {
      const user = this.$route.params.user?.toString();
      const name = this.$route.params.name?.toString();
      return user && name ? `${user}/${name}` : String();
    },

    /** Remove the manual order of the album */
    async resetOrder() {
      const album = this.albumId();
      if (!album) return;

      const confirm = await utils.dialogs.resetAlbumOrder();
      if (!confirm) return;

      try {
        await albumOrderApi.resetAlbumOrder(album);
      } catch {
        showError(this.t('memories', 'Could not reset the order of this album.'));
        return;
      }

      // Fall back to the default date order of this view
      const fallback = this.config.sort_album_month ? 'date-asc' : 'date';
      if (this.config.sort_album_order !== fallback) {
        this.config.sort_album_order = fallback;
        await this.updateSetting('sort_album_order', 'sortAlbumOrder');
      }

      utils.bus.emit('memories:timeline:hard-refresh', null);
    },

    back() {
      this.$router.go(-1);
    },

    openShareModal() {
      _m.modals.albumShare(this.$route.params.user?.toString(), this.$route.params.name?.toString());
    },

    async downloadAlbum() {
      const res = await axios.post(
        API.ALBUM_DOWNLOAD(this.$route.params.user?.toString(), this.$route.params.name?.toString()),
      );
      if (res.status === 200 && res.data.handle) {
        downloadWithHandle(res.data.handle, this.$route.params.name?.toString());
      }
    },

    /** Set sort choice */
    changeSort(flag: number) {
      const dir = this.config.album_list_sort & this.c.ALBUM_SORT_FLAGS.DESCENDING;
      this.config.album_list_sort = flag | dir;
      this.updateSetting('album_list_sort');
    },

    /** Set sort direction */
    setDescending(val: boolean) {
      if (val) {
        this.config.album_list_sort |= this.c.ALBUM_SORT_FLAGS.DESCENDING;
      } else {
        this.config.album_list_sort &= ~this.c.ALBUM_SORT_FLAGS.DESCENDING;
      }
      this.updateSetting('album_list_sort');
    },
  },
});
</script>

<style scoped>
.album-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>
